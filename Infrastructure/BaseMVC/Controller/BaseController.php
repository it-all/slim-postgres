<?php
declare(strict_types=1);

namespace Infrastructure\BaseMVC\Controller;

use Infrastructure\SlimPostgres;
use Infrastructure\Database\Postgres;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\BaseMVC\View\Forms\FormHelper;
use Slim\Container;
use Slim\Http\Request;
use Slim\Http\Response;
use Infrastructure\BaseMVC\View\AdminListView;

abstract class BaseController
{
    protected $container; // dependency injection container
    protected $requestInput; // user input data

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __get($name)
    {
        return $this->container->{$name};
    }

    protected function setRequestInput(Request $request, array $fieldNames, array $booleanFieldNames = [])
    {
        $this->requestInput = [];

        foreach ($fieldNames as $fieldName) {
            /** will be null if $fieldName does not exist in parsed body
             *  note, this is true of array fields with no checked options
             */
            $this->requestInput[$fieldName] = $request->getParsedBodyParam($fieldName);

            /** trim if necessary depending on config */
            if (is_string($this->requestInput[$fieldName]) && $this->settings['trimAllUserInput']) {
                $this->requestInput[$fieldName] = trim($this->requestInput[$fieldName]);
            }

            /** handle boolean fields 
             *  if not set convert to false
             *  if 'on' convert to true
             */
            if (in_array($fieldName, $booleanFieldNames)) {
                if ($this->requestInput[$fieldName] === null) {
                    $this->requestInput[$booleanFieldName] = Postgres::BOOLEAN_FALSE;
                } elseif ($this->requestInput[$fieldName] === 'on') {
                    $this->requestInput[$booleanFieldName] = Postgres::BOOLEAN_TRUE;
                } else {
                    throw new \Exception('Invalid value for boolean input var '.$booleanFieldName.': '.$this->requestInput[$booleanFieldName]);
                }
            }
        }
    }

    /** called by children for posted filter form entry methods */
    protected function setIndexFilter(Request $request, Response $response, $args, array $listViewColumns, AdminListView $view)
    {
        $this->setRequestInput($request, [$view->getSessionFilterFieldKey()]);

        if (!isset($this->requestInput[$view->getSessionFilterFieldKey()])) {
            throw new \Exception("session filter input must be set");
        }

        $this->storeFilterFieldValueInSession($view);

        /** if there is an error in the filter field getFilterColumns will set the form error and return null */
        if (null !== $filterColumnsInfo = $this->getFilterColumns($view->getSessionFilterFieldKey(), $listViewColumns)) {
            $this->storeFilterColumnsInfoInSession($filterColumnsInfo, $view);
        }
    }

    private function storeFilterColumnsInfoInSession(array $filterColumnsInfo, AdminListView $view)
    {
        $_SESSION[SlimPostgres::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_COLUMNS_KEY] = $filterColumnsInfo;
    }

    private function storeFilterFieldValueInSession(AdminListView $view) 
    {
        /** store entered field value in session so form field can be repopulated */
        $_SESSION[SlimPostgres::SESSION_KEY_ADMIN_LIST_VIEW_FILTER][$view->getFilterKey()][$view::SESSION_FILTER_VALUE_KEY] = $this->requestInput[$view->getSessionFilterFieldKey()];
    }

    // parse the where filter field into [ column name => [operators, values] ] 
    protected function getFilterColumns(string $filterFieldName, array $listViewColumns): ?array
    {
        $filterColumnsInfo = [];
        $filterParts = explode(",", $this->requestInput[$filterFieldName]);
        if (mb_strlen($filterParts[0]) == 0) {
            FormHelper::setFieldErrors([$filterFieldName => 'Not Entered']);
            return null;
        } else {

            foreach ($filterParts as $whereFieldOperatorValue) {
                //field:operator:value
                $whereFieldOperatorValueParts = explode(":", $whereFieldOperatorValue);
                if (count($whereFieldOperatorValueParts) != 3) {
                    FormHelper::setFieldErrors([$filterFieldName => 'Malformed']);
                    return null;
                }
                $columnName = trim($whereFieldOperatorValueParts[0]);
                $whereOperator = strtoupper(trim($whereFieldOperatorValueParts[1]));
                $whereValue = trim($whereFieldOperatorValueParts[2]);

                // validate the column name
                if (isset($listViewColumns[strtolower($columnName)])) {
                    $columnNameSql = $listViewColumns[strtolower($columnName)];
                } else {
                    FormHelper::setFieldErrors([$filterFieldName => "$columnName column not found"]);
                    return null;
                }

                // validate the operator
                if (!QueryBuilder::validateWhereOperator($whereOperator)) {
                    FormHelper::setFieldErrors([$filterFieldName => "Invalid Operator $whereOperator"]);
                    return null;
                }

                // null value only valid with IS and IS NOT operators
                if (strtolower($whereValue) == 'null') {
                    if ($whereOperator != 'IS' && $whereOperator != 'IS NOT') {
                        FormHelper::setFieldErrors([$filterFieldName => "Mismatched null, $whereOperator"]);
                        return null;
                    }
                    $whereValue = null;
                }

                if (!isset($filterColumnsInfo[$columnNameSql])) {
                    $filterColumnsInfo[$columnNameSql] = [];
                    $filterColumnsInfo[$columnNameSql]['operators'] = [];
                    $filterColumnsInfo[$columnNameSql]['values'] = [];
                }
                $filterColumnsInfo[$columnNameSql]['operators'][] = $whereOperator;
                $filterColumnsInfo[$columnNameSql]['values'][] = $whereValue;
            }
        }

        return $filterColumnsInfo;
    }

    /** 
     * @param string $emailTo must be in $settings['emails'] array or error will be inserted to events
     * @param string $mainBody
     * @param bool $addEventLogStatement defaults true, if true adds 'See event log for details' after $mainBody
     * @param bool $throwExceptionOnError defaults false, if true exception is thrown if no match for $emailTo
     */
    protected function sendEventNotificationEmail(string $emailTo, string $mainBody, bool $addEventLogStatement = true, bool $throwExceptionOnError = false)
    {
        if ($emailTo !== null) {
            $settings = $this->container->get('settings');
            if (isset($settings['emails'][$emailTo])) {
                $emailBody = $mainBody;
                if ($addEventLogStatement) {
                    $emailBody .= PHP_EOL . "See event log for details.";
                }
                $this->mailer->send(
                    $_SERVER['SERVER_NAME'] . " Event",
                    $emailBody,
                    [$settings['emails'][$emailTo]]
                );
            } else {
                $this->events->insertError("Email Not Found", (int) $this->authentication->getAdministratorId(), $emailTo);
                if ($throwExceptionOnError) {
                    throw new \InvalidArgumentException("Email Not Found: $emailTo");
                }
            }
        }
    }
}
