<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\SingleTable\SingleTableView;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Response;

class RolesView extends SingleTableView
{
    public function __construct(Container $container)
    {
        parent::__construct($container, new RolesModel(), ROUTEPREFIX_ROLES);
    }

    // override in order to not show delete link for roles in use
    public function indexView(Response $response, bool $resetFilter = false)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        $filterColumnsInfo = (isset($_SESSION[$this->sessionFilterColumnsKey])) ? $_SESSION[$this->sessionFilterColumnsKey] : null;
        if ($results = pg_fetch_all($this->model->select($filterColumnsInfo))) {
            $numResults = count($results);
        } else {
            $numResults = 0;
        }

        $allowDeleteRoles = [];
        foreach ($results as $row) {
            if (!$this->model::hasAdmin((int) $row['id'])) {
                $allowDeleteRoles[] = $row['id'];
            }
        }

        $filterFieldValue = $this->getFilterFieldValue();
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);

        // make sure all session input necessary to send to template is produced above
        FormHelper::unsetSessionVars();

        return $this->view->render(
            $response,
            'admin/lists/rolesList.php',
            [
                'title' => $this->model->getTableName(),
                'insertLink' => $this->insertLink,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $filterFieldValue,
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $filterColumnsInfo,
                'resetFilterRoute' => $this->filterResetRoute,
                'updateColumn' => $this->updateColumn,
                'updatePermitted' => $this->updatePermitted,
                'updateRoute' => $this->updateRoute,
                'addDeleteColumn' => $this->addDeleteColumn,
                'deleteRoute' => $this->deleteRoute,
                'allowDeleteRoles' => $allowDeleteRoles,
                'results' => $results,
                'numResults' => $numResults,
                'numColumns' => $this->model->getCountSelectColumns(),
                'sortColumn' => $this->model->getOrderByColumnName(),
                'sortByAsc' => $this->model->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }
}
