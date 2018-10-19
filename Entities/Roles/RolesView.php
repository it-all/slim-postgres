<?php
declare(strict_types=1);

namespace Entities\Roles;

use Entities\Roles\Model\RolesMapper;
use Infrastructure\SlimPostgres;
use Exceptions\QueryFailureException;
use Infrastructure\BaseMVC\View\ObjectsListViews;
use Infrastructure\BaseMVC\View\InsertUpdateViews;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\BaseMVC\View\DatabaseTableView;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class RolesView extends DatabaseTableView implements ObjectsListViews, InsertUpdateViews
{
    public function __construct(Container $container)
    {
        parent::__construct($container, RolesMapper::getInstance(), ROUTEPREFIX_ROLES, true, 'admin/lists/objectsList.php');
    }

    protected function getResource(string $which): string 
    {
        switch ($which) {
            case 'insert':
                return ROLES_INSERT_RESOURCE;
                break;
            case 'update':
                return ROLES_UPDATE_RESOURCE;
                break;
            case 'delete':
                return ROLES_DELETE_RESOURCE;
                break;
            default:
                throw new \InvalidArgumentException("Undefined resource $which");
        }
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndex($request, Response $response, $args)
    {
        return $this->indexViewObjects($response);
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexViewObjects($response, true);
    }

    /** get role objects and send to parent indexView */
    public function indexViewObjects(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        try {
            $roles = $this->mapper->getObjects($this->getFilterColumnsInfo());
        } catch (QueryFailureException $e) {
            $roles = [];
            // warning is inserted when query fails
            SlimPostgres::setAdminNotice('Query Failed', 'failure');
        }
        
        return $this->indexView($response, $roles);
    }
}
