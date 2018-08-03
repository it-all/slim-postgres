<?php
declare(strict_types=1);

namespace SlimPostgres;

use SlimPostgres\App;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\DataMappers\TableMappers;
use SlimPostgres\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

abstract class AdminListView extends AdminView
{
    /** user entered value of the filter field. subkey of request input */
    protected $sessionFilterFieldKey;

    protected $filterKey;

    protected $indexRoute;
    protected $mapper;

    /** defaults to resultsList but objectsList can be passed in */
    protected $template;
    protected $filterResetRoute;

    /** false or ['text' => {link text}, 'route' => {route}] */
    protected $insertLinkInfo; 

    protected $updatesPermitted;
    protected $updateColumn;
    protected $updateRoute;
    protected $deletesPermitted;
    protected $deleteRoute;
    
    const SESSION_FILTER_COLUMNS_KEY = 'columns';
    const SESSION_FILTER_VALUE_KEY = 'value';

    public function __construct(Container $container, string $filterFieldsPrefix, string $indexRoute, TableMappers $mapper, string $filterResetRoute, string $template = 'admin/lists/resultsList.php')
    {
        $this->sessionFilterFieldKey = $filterFieldsPrefix . 'Filter';
        $this->filterKey = $filterFieldsPrefix;

        $this->indexRoute = $indexRoute;
        $this->mapper = $mapper;
        $this->template = $template;
        $this->filterResetRoute = $filterResetRoute;
        $this->updatesPermitted = false; // initialize
        $this->updateColumn = null; // initialize
        $this->updateRoute = null; // initialize
        $this->deletesPermitted = false; // initialize
        $this->deleteRoute = null; // initialize
        $this->insertLinkInfo = null; // initialize
        parent::__construct($container);
    }

    public function getFilterKey(): string 
    {
        return $this->filterKey;
    }

    protected function setInsert($insertLinkInfo)
    {
        $this->insertLinkInfo = $insertLinkInfo;
    }

    protected function setUpdate(bool $updatesPermitted, ?string $updateColumn, ?string $updateRoute)
    {
        $this->updatesPermitted = $updatesPermitted; // initialize
        $this->updateColumn = $updateColumn; // initialize
        $this->updateRoute = $updateRoute; // initialize
    }

    protected function setDelete(bool $deletesPermitted, ?string $deleteRoute)
    {
        if ($deletesPermitted && $deleteRoute == null) {
            throw new \Exception("delete route must be defined");
        }
        $this->deletesPermitted = $deletesPermitted;
        $this->deleteRoute = $deleteRoute;
    }

    public function routeIndex(Request $request, Response $response, $args)
    {
        return $this->indexView($response);
    }

    public function routeIndexResetFilter(Request $request, Response $response, $args)
    {
        // redirect to the clean url
        return $this->indexView($response, true);
    }

    /** display items can be passed in as an array of records or objects, if objects, the appropriate template should be passed to this constructor. */
    public function indexView(Response $response, bool $resetFilter = false, ?array $displayItems = null)
    {
        if ($resetFilter) {
            return $this->resetFilter($response, $this->indexRoute);
        }

        if ($displayItems === null) {
            $filterColumnsInfo = $this->getFilterColumnsInfo();
            if (!$displayItems = pg_fetch_all($pgResults = $this->mapper->select($this->mapper->getSelectColumnsString(), $filterColumnsInfo))) {
                $displayItems = [];
            }
    
            pg_free_result($pgResults);
        }

        /** save error in var prior to unsetting */
        $filterErrorMessage = FormHelper::getFieldError($this->sessionFilterFieldKey);
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            $this->template,
            [
                'title' => $this->mapper->getFormalTableName(),
                'insertLinkInfo' => $this->insertLinkInfo,
                'filterOpsList' => QueryBuilder::getWhereOperatorsText(),
                'filterValue' => $this->getFilterFieldValue(),
                'filterErrorMessage' => $filterErrorMessage,
                'filterFormActionRoute' => $this->indexRoute,
                'filterFieldName' => $this->sessionFilterFieldKey,
                'isFiltered' => $this->getFilterFieldValue() != '',
                'resetFilterRoute' => $this->filterResetRoute,
                'updatesPermitted' => $this->updatesPermitted,
                'updateColumn' => $this->updateColumn,
                'updateRoute' => $this->updateRoute,
                'deletesPermitted' => $this->deletesPermitted,
                'deleteRoute' => $this->deleteRoute,
                'displayItems' => $displayItems,
                'columnCount' => $this->mapper->getCountSelectColumns(),
                'sortColumn' => $this->mapper->getOrderByColumnName(),
                'sortByAsc' => $this->mapper->getOrderByAsc(),
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    protected function getFilterColumnsInfo(): ?array 
    {
        return (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY])) ? $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY] : null;
    }

    /** either session value or empty string */
    protected function getFilterFieldValue(): string
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY])) {
            return $_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY];
        } else {
            return '';
        }
    }
        
    protected function resetFilter(Response $response, string $redirectRoute)
    {
        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY])) {
            unset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_COLUMNS_KEY]);
        }

        if (isset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY])) {
            unset($_SESSION[App::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$this->getFilterKey()][self::SESSION_FILTER_VALUE_KEY]);
        }

        // redirect to the clean url
        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function getSessionFilterFieldKey(): string
    {
        return $this->sessionFilterFieldKey;
    }
}
