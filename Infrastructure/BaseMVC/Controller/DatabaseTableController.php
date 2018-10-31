<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\Controller;

use Infrastructure\SlimPostgres;
use Exceptions;
use Infrastructure\BaseMVC\View\ResponseUtilities;
use Infrastructure\BaseMVC\Controller\AdminController;
use Infrastructure\Database\DataMappers\TableMapper;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Infrastructure\BaseMVC\View\Forms\DatabaseTableForm;
use Infrastructure\Validation\DatabaseTableInsertFormValidator;
use Infrastructure\Validation\DatabaseTableUpdateFormValidator;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;

class DatabaseTableController extends AdminController
{
    use ResponseUtilities;

    protected $tableMapper;
    protected $view;
    protected $routePrefix;

    public function __construct(Container $container, TableMapper $tableMapper, $view, $routePrefix)
    {
        $this->tableMapper = $tableMapper;
        $this->view = $view;
        $this->routePrefix = $routePrefix;
        parent::__construct($container);
    }

    public function getMapper(): TableMapper
    {
        return $this->tableMapper;
    }

    protected function getListViewColumns(): array
    {
        $listViewColumns = [];
        foreach ($this->tableMapper->getColumns() as $column) {
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
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_INSERT_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->getBooleanFieldNames());

        $validator = new DatabaseTableInsertFormValidator($this->requestInput, $this->tableMapper);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->insertView($request, $response, $args);
        }

        try {
            /** the last true bool means that boolean columns that don't exist in $changedColumnsValues get inserted as false */
            $insertResult = $this->tableMapper->insert($this->requestInput, true);
        } catch (\Exception $e) {
            throw new \Exception("Insert failure. ".$e->getMessage());
        }

        $tableNameSingular = $this->tableMapper->getFormalTableName(false);
        $noteStart = "Inserted $tableNameSingular";

        /** use constant if defined, squelch warning */
        $eventTitle = @constant("EVENT_".strtoupper($tableNameSingular)."_INSERT") ?? $noteStart;
        $adminNotification = $noteStart;
        $eventNote = "";

        if (null !== $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName()) {
            $adminNotification .= " $insertResult"; // if primary key is set the new id is returned by mapper insert method
            $eventNote = "$primaryKeyColumnName: $insertResult";
        }
        
        $this->events->insertInfo($eventTitle, $eventNote);
        SlimPostgres::setAdminNotice($adminNotification);

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }

    public function getBooleanFieldNames(): array
    {
        $booleanFieldNames = [];
        foreach ($this->tableMapper->getColumns() as $column) {
            if ($column->isBoolean()) {
                $booleanFieldNames[] = $column->getName();
            }
        }
        return $booleanFieldNames;
    }

    /** the table must have a primary key column defined */
    public function routePutUpdate(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_UPDATE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKeyValue = $args['primaryKey'];

        $this->setRequestInput($request, DatabaseTableForm::getFieldNames($this->tableMapper), $this->getBooleanFieldNames());

        $redirectRoute = SlimPostgres::getRouteName(true, $this->routePrefix, 'index');

        // make sure there is a record for the primary key
        if (null === $record = $this->tableMapper->selectForPrimaryKey($primaryKeyValue)) {
            return $this->databaseRecordNotFound($response, $primaryKeyValue, $this->tableMapper, 'update');
        }

        // if no changes made stay on page with error
        $changedColumnsValues = $this->getMapper()->getChangedColumnsValues($this->requestInput, $record);
        if (count($changedColumnsValues) == 0) {
            SlimPostgres::setAdminNotice("No changes made", 'failure');
            return $this->view->updateView($request, $response, $args);
        }

        $validator = new DatabaseTableUpdateFormValidator($this->requestInput, $this->tableMapper, $record);

        if (!$validator->validate()) {
            // redisplay the form with input values and error(s)
            FormHelper::setFieldErrors($validator->getFirstErrors());
            $args[SlimPostgres::USER_INPUT_KEY] = $this->requestInput;
            return $this->view->updateView($request, $response, $args);
        }

        /** the last true bool means that boolean columns that don't exist in $changedColumnsValues get inserted as false ('f') */
        $this->tableMapper->updateByPrimaryKey($changedColumnsValues, $primaryKeyValue, true, [], true);

        $tableNameSingular = $this->tableMapper->getFormalTableName(false);

        $noteStart = "Updated $tableNameSingular";
        /** use constant if defined, squelch warning */
        $eventTitle = @constant("EVENT_".strtoupper($tableNameSingular)."_UPDATE") ?? $noteStart;
        $adminNotification = "$noteStart $primaryKeyValue";
        $eventNote = $this->tableMapper->getPrimaryKeyColumnName() . ": " . $primaryKeyValue;

        $this->events->insertInfo($eventTitle, $eventNote);
        SlimPostgres::setAdminNotice($adminNotification);

        return $response->withRedirect($this->router->pathFor($redirectRoute));
    }

    public function routeGetDelete(Request $request, Response $response, $args)
    {
        if (!$this->authorization->isAuthorized(constant(strtoupper($this->routePrefix)."_DELETE_RESOURCE"))) {
            throw new \Exception('No permission.');
        }

        $primaryKey = $args['primaryKey'];
        $tableName = $this->tableMapper->getFormalTableName(false);
        $primaryKeyColumnName = $this->tableMapper->getPrimaryKeyColumnName();

        try {
            $this->tableMapper->deleteByPrimaryKey($primaryKey);

            /** use constant if defined, squelch warning */
            $eventTitle = @constant("EVENT_".strtoupper($tableName)."_DELETE") ?? "Deleted $tableName";

            $this->events->insertInfo($eventTitle, "$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("Deleted $tableName $primaryKey");
        } catch (Exceptions\QueryResultsNotFoundException $e) {
            $this->events->insertWarning(EVENT_QUERY_NO_RESULTS, "Table: $tableName|$primaryKeyColumnName: $primaryKey");
            SlimPostgres::setAdminNotice("$tableName $primaryKey Not Found", 'failure');
        } catch (Exceptions\QueryFailureException $e) {
            SlimPostgres::setAdminNotice('Deletion Query Failure', 'failure');
        }

        return $response->withRedirect($this->router->pathFor(SlimPostgres::getRouteName(true, $this->routePrefix, 'index')));
    }

    private function getChangedFieldsString(array $changedFields, array $record): string 
    {
        $changedString = "";
        foreach ($changedFields as $fieldName => $newValue) {
            $changedString .= " $fieldName: ".$record[$fieldName]." => $newValue, ";
        }

        return substr($changedString, 0, strlen($changedString)-2);
    }
}
