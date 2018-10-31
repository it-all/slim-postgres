<?php
declare(strict_types=1);

namespace Entities\Roles;

use Entities\Roles\Model\RolesTableMapper;
use Infrastructure\SlimPostgres;
use Infrastructure\BaseMVC\Controller\DatabaseTableController;
use Exceptions;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\BaseMVC\View\Forms\FormHelper;

class RolesController extends DatabaseTableController
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesTableMapper::getInstance(), new RolesView($container), ROUTEPREFIX_ROLES);
    }

    /** override to call objects view */
    public function routePostIndexFilter(Request $request, Response $response, $args)
    {
        $this->setIndexFilter($request, $response, $args, $this->getListViewColumns(), $this->view);
        return $this->view->indexViewObjects($response);
    }

    // override to check exceptions
    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(ROLES_DELETE_RESOURCE)) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->tableMapper->getFormalTableName(false);
        $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName();

        try {
            $this->tableMapper->deleteByPrimaryKey($primaryKey);
            $this->events->insertInfo(EVENT_ROLE_DELETE, "$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\UnallowedActionException $e) {
            $this->events->insertWarning(EVENT_UNALLOWED_ACTION, $e->getMessage());
            SlimPostgres::setAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryResultsNotFoundException $e) {
define('EVENT_QUERY_NO_RESULTS', 'Query Results Not Found');
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, $e->getMessage());
            SlimPostgres::setAdminNotice($e->getMessage(), 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            $this->events->insertError(EVENT_QUERY_FAIL, $e->getMessage());
            SlimPostgres::setAdminNotice('Delete Failed', 'failure');
        }

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }
}
