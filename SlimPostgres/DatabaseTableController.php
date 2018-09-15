<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Exceptions;
use SlimPostgres\ResponseUtilities;
use SlimPostgres\BaseController;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Forms\FormHelper;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\DatabaseTableInsertFormValidator;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class DatabaseTableController extends BaseController
{
    use ResponseUtilities;

    protected $mapper;
    protected $view;
    protected $routePrefix;

    public function __construct(Container $container, $mapper, $view, $routePrefix)
    {
        $this->mapper = $mapper;
        $this->view = $view;
        $this->routePrefix = $routePrefix;
        parent::__construct($container);
    }

    public function getMapper(): TableMapper
    {
        return $this->mapper;
    }

    protected function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->mapper->getColumns() as $column) {
            $listViewColumns[$column->getName()] = $column->getName();
        }

        return $listViewColumns;
    }

    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), $this->view);
        return $this->view->indexView($response);
    }

    public function routePostInsert(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'insert'))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->mapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            $insertResult = $this->mapper->insert($this->requestInput);
        } catch (\Exception $e) {
            throw new \Exception("Insert failure. ".$e->getMessage());
        }

        $noteStart = "Inserted " . $this->mapper->getTableName(false);
        $adminNotification = $noteStart;
        $eventNote = "";

        if (null !== $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $insertResult"; // if primary key is set the new id is returned by mapper insert method
            $eventNote = "$primaryKeyColumnName: $insertedRecordId";
        }
        
        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminNotification, App::STATUS_ADMIN_NOTICE_SUCCESS];

        return $response->withRedirect($this->router->pathFor(App::getRouteName(true, $this->routePrefix, 'index')));
    }

    public function getBooleanFieldNames(): array
    {
        $booleanFieldNames = [];
        foreach ($this->mapper->getColumns() as $column) {
            if ($column->isBoolean()) {
                $booleanFieldNames[] = $column->getName();
            }
        }
        return $booleanFieldNames;
    }

    /** the table must have a primary key column defined */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'update'))) {
            throw new \Exception('No permission.');
        }

        $primaryKeyValue = $args['primaryKey'];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->mapper), $this->getBooleanFieldNames());

        $redirectRoute = App::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (!$record = $this->mapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->mapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["No changes made", App::STATUS_ADMIN_NOTICE_FAILURE];
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->mapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[App::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        try {
            // $this->update($response, $args, $changedColumnsValues, $record);
            $this->mapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue);
        } catch (\Exception $e) {
            throw new \Exception("Update failure. ".$e->getMessage());
        }

        $noteStart = "Updated " . $this->mapper->getTableName(false);
        $adminNotification = "$noteStart $primaryKeyValue";
        $eventNote = $this->mapper->getPrimaryKeyColumnName() . ": " . $primaryKeyValue;

        $this->systemEvents->insertInfo($noteStart, (int) $this->authentication->getAdministratorId(), $eventNote);

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminNotification, App::STATUS_ADMIN_NOTICE_SUCCESS];

        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        return $this->deleteHelper($response, $args['primaryKey']);
    }

    /**
     * this can be called by child classes
     * $emailTo is an email title from $settings['emails']
     */
    public function deleteHelper(Response $response, $primaryKey, ?string $returnColumn = null, ?string $emailTo = null, $routeType = 'index')
    {
        if (!$this->authorization->isFunctionalityAuthorized(App::getRouteName(true, $this->routePrefix, 'delete'))) {
            throw new \Exception('No permission.');
        }

        $this->delete($primaryKey, $returnColumn, $emailTo); // sets success or failure notices

        $redirectRoute = App::getRouteName(true, $this->routePrefix, $routeType);
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    private function getChangedFieldsString(array $changedFields, array $record): string 
    {
        $changedString = "";
        foreach ($changedFields as $fieldName => $newValue) {
            $changedString .= " $fieldName: ".$record[$fieldName]." => $newValue, ";
        }

        return substr($changedString, 0, strlen($changedString)-2);
    }

    /**
     * $emailTo is an email title from $settings['emails']
     */
    protected function update(Response $response, $args, array $changedColumnValues = [], array $record = [], ?string $emailTo = null)
    {
        // get changed column values if not sent in arg. will need record too.
        if (count($changedColumnValues) == 0) {
            if (count($record) == 0) {
                $record = $this->mapper->selectForPrimaryKey($args['primaryKey']);
            }
            $changedColumnValues = $this->mapper->getChangedColumnsValues($this->requestInput, $record);
        }

        $this->mapper->updateByPrimaryKey($changedColumnValues, $args['primaryKey']);

        $primaryKeyColumnName = $this->mapper->getPrimaryKeyColumnName();
        $updatedRecordId = $args['primaryKey'];
        $tableName = $this->mapper->getTableName(false);

        $this->systemEvents->insertInfo("Updated $tableName", (int) $this->authentication->getAdministratorId(), "$primaryKeyColumnName:$updatedRecordId|".$this->getChangedFieldsString($changedColumnValues, $record));

        if ($emailTo !== null) {
            $this->sendEventNotificationEmail("Updated $tableName", $emailTo);
        }

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ["Updated $tableName $updatedRecordId", App::STATUS_ADMIN_NOTICE_SUCCESS];
    }

    /**
     * $emailTo is an email title from $settings['emails']
     */
    protected function delete($primaryKey, ?string $returnColumn = null, ?string $emailTo = null)
    {
        try {
            $dbResult = $this->mapper->deleteByPrimaryKey($primaryKey, $returnColumn);
        } catch (Exceptions\QueryResultsNotFoundException $e) {

            // enter system event
            $this->systemEvents->insertWarning('Query Results Not Found', (int) $this->authentication->getAdministratorId(), $this->mapper->getPrimaryKeyColumnName().":$primaryKey|Table:".$this->mapper->getTableName());

            // set admin notice
            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$primaryKey.' not found', App::STATUS_ADMIN_NOTICE_FAILURE];
            throw $e;

        } catch (\Exception $e) {

            $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = ['Deletion Failure', App::STATUS_ADMIN_NOTICE_FAILURE];
            throw $e;
            
        }

        $this->deleted($dbResult, $primaryKey, $returnColumn, $emailTo);
    }

    /**
     * call after a record has been successfully deleted
     * $emailTo is an email title from $settings['emails']
     */
    protected function deleted($dbResult, $primaryKey, ?string $returnColumn = null, ?string $emailTo = null)
    {
        $tableName = $this->mapper->getTableName(false);
        $eventNote = $this->mapper->getPrimaryKeyColumnName().":$primaryKey";

        $adminMessage = "Deleted $tableName $primaryKey";
        if ($returnColumn != null) {
            $returned = pg_fetch_all($dbResult);
            $eventNote .= "|$returnColumn:".$returned[0][$returnColumn];
            $adminMessage .= " ($returnColumn ".$returned[0][$returnColumn].")";
        }

        $this->systemEvents->insertInfo("Deleted $tableName", (int) $this->authentication->getAdministratorId(), $eventNote);

        if ($emailTo !== null) {
            $this->sendEventNotificationEmail("Deleted $tableName", $emailTo);
        }

        $_SESSION[App::SESSION_KEY_ADMIN_NOTICE] = [$adminMessage, App::STATUS_ADMIN_NOTICE_SUCCESS];
    }
}
