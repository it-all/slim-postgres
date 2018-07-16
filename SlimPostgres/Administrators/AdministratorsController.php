<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\Logins\LoginAttemptsMapper;
use SlimPostgres\App;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
use SlimPostgres\DatabaseTableController;
use SlimPostgres\Forms\FormHelper;
use SlimPostgres\Exceptions;
use SlimPostgres\Utilities\Functions;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class AdministratorsController extends BaseController
{
    use ResponseUtilities;

    private $administratorsMapper;
    private $view;
    private $routePrefix;
    private $changedFieldsString;

    public function __construct(Container $container)
    {
        $this->administratorsMapper = AdministratorsMapper::getInstance();
        $this->view = new AdministratorsView($container);
        $this->routePrefix = ROUTEPREFIX_ADMINISTRATORS;
        parent::__construct($container);
    }

    public function postIndexFilter(Request $request, Response $response, $args)
    {
        return $this->setIndexFilter($request, $response, $args, $this->administratorsMapper::SELECT_COLUMNS, $this->view);
    }

    public function postInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request); // no boolean fields to add

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        $validator = new AdministratorsValidator($input);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->getInsert($request, $response, $args);
        }

        try {
            $administratorId = $this->administratorsMapper->create($input['name'], $input['username'], $input['password'], $input['roles']);
        } catch (\Exception $e) {
            throw new \Exception("Administrator create failure. ".$e->getMessage());
        }

        $this->systemEvents->insertInfo("Inserted Administrator", (int) $this->authentication->getAdministratorId(), "id:$administratorId");

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Inserted administrator $administratorId", App::STATUS_ADMIN_NOTICE_SUCCESS];
        return $response->withRedirect($this->router->pathFor(ROUTE_ADMINISTRATORS));
    }

    public function putUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];

        $this->setRequestInput($request);
        // no boolean fields to add

        $redirectRoute = App::getRouteName(true, $this->routePrefix,'index');

        // make sure there is an administrator for the primary key
        if (null === $administrator = $this->administratorsMapper->getObjectById((int) $primaryKey)) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'update');
        }

        $input = $_SESSION[App::SESSION_KEY_REQUEST_INPUT];

        // if all roles have been unchecked it won't be included in user input
        if (!isset($input['roles'])) {
            $input['roles'] = [];
        }

        // check for changes made
        // only check the password if it has been supplied (entered in the form)
        $changedFields = $administrator->getChangedFieldValues($input['name'], $input['username'], $input['roles'], mb_strlen($input['password']) > 0, $input['password']);

        // if no changes made, display error message
        if (count($changedFields) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made", 'adminNoticeFailure'];
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new AdministratorsValidator($input, $changedFields);
        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            return $this->view->updateView($request, $response, $args);
        }
        
        $this->administratorsMapper->update((int) $primaryKey, $changedFields);

        // if the administrator changed her/his own info, update the session
        if ($primaryKey == $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ID]) {
            $this->updateAdministratorSession($changedFields);
        }

        $this->systemEvents->insertInfo("Updated Administrator", (int) $this->authentication->getAdministratorId(), "id:$primaryKey|".$administrator->getChangedFieldsString($changedFields, $administrator));

        FormHelper::unsetFormSessionVars();

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated administrator $primaryKey", App::STATUS_ADMIN_NOTICE_SUCCESS];
        
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
    }

    /** update whatever has changed of name, username, roles if the currently logged on administrator has changed own info */
    private function updateAdministratorSession(array $changedFields)
    {
        foreach ($changedFields as $fieldName => $fieldValue) {
            if ($fieldName == 'name') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_NAME] = $fieldValue;
            } elseif ($fieldName == 'username') {
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_USERNAME] = $fieldValue;
            } elseif ($fieldName == 'role_id') {
                $rolesMapper = RolesMapper::getInstance();
                if (!$newRole = $rolesMapper->getRoleForRoleId((int) $fieldValue)) {
                    throw new \Exception('Role not found for changed role id: '.$fieldValue);
                }
                $_SESSION[App::SESSION_KEY_ADMINISTRATOR][App::SESSION_ADMINISTRATOR_KEY_ROLES] = $newRole;
            }
        }
    }

    // override for custom validation and return column
    public function getDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = (int) $args['primaryKey'];

        try {
            $username = $this->administratorsMapper->delete($primaryKey, $this->container->authentication, $this->container->systemEvents);
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            return $this->databaseRecordNotFound($response, $primaryKey, $this->administratorsMapper->getPrimaryTableMapper(), 'delete', 'Administrator');
        } catch (Exceptions\UnallowedActionException $e) {
            $this->systemEvents->insertWarning('Unallowed Action', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$e->getMessage(), 'adminNoticeFailure'];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        } catch (\Exception $e) {
            $this->systemEvents->insertError('Administrator Deletion Failure', (int) $this->authentication->getAdministratorId(), $e->getMessage());
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Deletion Failure', 'adminNoticeFailure'];
            return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix,'index')));
        }

        $eventNote = $this->administratorsMapper->getPrimaryTableMapper()->getPrimaryKeyColumnName() . ":$primaryKey|username: $username";
        $this->systemEvents->insertInfo("Deleted Administrator", (int) $this->authentication->getAdministratorId(), $eventNote);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Deleted administrator $primaryKey(username: $username)", App::STATUS_ADMIN_NOTICE_SUCCESS];
                
        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }
}
