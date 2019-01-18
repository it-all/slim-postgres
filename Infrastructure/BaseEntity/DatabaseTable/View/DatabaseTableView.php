<?php
declare(strict_types=1);

namespace Infrastructure\BaseEntity\DatabaseTable\View;

use Infrastructure\SlimPostgres;
use Infrastructure\BaseEntity\BaseMVC\View\AdminListView;
use Infrastructure\BaseEntity\BaseMVC\View\InsertUpdateViews;
use Infrastructure\BaseEntity\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseEntity\DatabaseTable\View\DatabaseTableForm;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseEntity\BaseMVC\View\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

// a list view class for a single database table
abstract class DatabaseTableView extends AdminListView implements InsertUpdateViews
{
    use ResponseUtilities;

    protected $routePrefix;
    protected $tableMapper;

    public function __construct(Container $container, TableMapper $tableMapper, string $routePrefix, bool $addDeleteColumnToListView = true, string $listViewTemplate = 'Admin/Lists/resultsList.php')
    {
        $this->tableMapper = $tableMapper;
        $this->routePrefix = $routePrefix;

        parent::__construct($container, $routePrefix, SlimPostgres::getRouteName(true, $routePrefix, 'index'), $this->tableMapper, SlimPostgres::getRouteName(true, $routePrefix, 'index.reset'), $listViewTemplate);

        $this->setInsert();
        $this->setUpdate();
        $this->setDelete();
    }

    /** overrides in order to get objects and send to indexView */
    public function routeIndex(Request $request, Response $response, $args)
    {
        return $this->indexView($response);
    }

    public function routeGetInsert(Request $request, Response $response, $args)
    {
        return $this->insertView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function insertView(Request $request, Response $response, $args)
    {
        $formFieldData = ($request->isPost() && isset($args[SlimPostgres::USER_INPUT_KEY])) ? $args[SlimPostgres::USER_INPUT_KEY] : null;

        $form = new DatabaseTableForm($this->tableMapper, $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'insert', 'post')), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'insert', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => 'Insert '. $this->tableMapper->getFormalTableName(false),
                'form' => $form,
                'navigationItems' => $this->navigationItems
            ]
        );
    }

    public function routeGetUpdate(Request $request, Response $response, $args)
    {
        return $this->updateView($request, $response, $args);
    }

    /** this can be called for both the initial get and the posted form if errors exist (from controller) */
    public function updateView(Request $request, Response $response, $args)
    {
        $primaryKeyValue = $args[ROUTEARG_PRIMARY_KEY];

        // make sure there is a record for the mapper
        if (null === $record = $this->tableMapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->tableMapper, 'update');
        }

        $formFieldData = ($request->isPut() && isset($args[SlimPostgres::USER_INPUT_KEY])) ? $args[SlimPostgres::USER_INPUT_KEY] : $record;

        $form = new DatabaseTableForm($this->tableMapper, $this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'update', 'put'), [ROUTEARG_PRIMARY_KEY => $primaryKeyValue]), $this->csrf->getTokenNameKey(), $this->csrf->getTokenName(), $this->csrf->getTokenValueKey(), $this->csrf->getTokenValue(), 'update', $formFieldData);
        
        FormHelper::unsetSessionFormErrors();

        return $this->view->render(
            $response,
            'Admin/form.php',
            [
                'title' => 'Update ' . $this->tableMapper->getFormalTableName(false),
                'form' => $form,
                'primaryKey' => $primaryKeyValue,
                'navigationItems' => $this->navigationItems,
                'hideFocus' => true
            ]
        );
    }
}
