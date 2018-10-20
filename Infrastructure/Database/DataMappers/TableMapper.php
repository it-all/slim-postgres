<?php
declare(strict_types=1);

namespace Infrastructure\Database\DataMappers;

use Infrastructure\Database\Postgres;
use Infrastructure\Database\Queries\InsertBuilder;
use Infrastructure\Database\Queries\InsertUpdateBuilder;
use Infrastructure\Database\Queries\QueryBuilder;
use Infrastructure\Database\Queries\SelectBuilder;
use Infrastructure\Database\Queries\UpdateBuilder;
use Exceptions;

class TableMapper implements TableMappers
{
    /** @var string  */
    protected $tableName;

    /** @var  array of column mapper objects */
    protected $columns;

    /** @var array of columnNames */
    protected $columnNames;

    /** @var string or null if no primary key column exists */
    protected $primaryKeyColumnName;

    protected $orderByColumnName;

    /** @var bool  */
    protected $orderByAsc;

    /**
     * @var array of columnNames with UNIQUE constraint or index
     * NOTE this does not handle multi-column unique constraints
     */
    private $uniqueColumnNames;

    private $uniqueColumns;

    private $defaultSelectColumnsString;

    public function __construct(string $tableName, $defaultSelectColumnsString = "*", ?string $orderByColumnName = null, bool $orderByAsc = true)
    {
        $this->tableName = $tableName;
        $this->primaryKeyColumnName = null; // initialize

        $this->uniqueColumns = [];
        $this->uniqueColumnNames = [];

        // $this->primaryKeyColumnName will be updated if exists
        // $this->uniqueColumnNames added (then used to set $column->isUnique
        $this->setConstraints();

        $this->defaultSelectColumnsString = $defaultSelectColumnsString;

        if ($orderByColumnName != null) {
            $this->orderByColumnName = $orderByColumnName;
        } elseif ($this->primaryKeyColumnName != null) {
            $this->orderByColumnName = $this->primaryKeyColumnName;
        } else {
            $this->orderByColumnName = null;
        }
        $this->orderByAsc = $orderByAsc;

        // $this->uniqueColumns added
        $this->setColumns();
    }

    /** note this will set uniqueColumnNames whether they are set as a constraint or an index */
    private function setConstraints()
    {
        $q = new QueryBuilder("SELECT ccu.column_name, tc.constraint_type FROM INFORMATION_SCHEMA.constraint_column_usage ccu JOIN information_schema.table_constraints tc ON ccu.constraint_name = tc.constraint_name WHERE tc.table_name = ccu.table_name AND ccu.table_name = $1", $this->tableName);
        $qResult = $q->execute();
        while ($qRow = pg_fetch_assoc($qResult)) {
            switch($qRow['constraint_type']) {
                case 'PRIMARY KEY':
                    $this->primaryKeyColumnName = $qRow['column_name'];
                    break;
                case 'UNIQUE':
                    $this->uniqueColumnNames[] = $qRow['column_name'];
            }
        }
    }

    protected function setColumns()
    {
        try {
            $rs = Postgres::getTableMetaData($this->tableName);
        } catch (\Exception $e) {
            throw new \Exception("Unable to set columns for table $this->tableName");
        }

        $this->columnNames = [];

        while ($columnInfo = pg_fetch_assoc($rs)) {
            $columnInfo['is_unique'] = in_array($columnInfo['column_name'], $this->uniqueColumnNames);
            $c = new ColumnMapper($this, $columnInfo);
            $this->columns[] = $c;
            $this->columnNames[] = $columnInfo['column_name'];
            if ($columnInfo['is_unique']) {
                $this->uniqueColumns[] = $c;
            }
        }
    }

    // make protected since ORM does not sniff out every constraint, some must be added manually when table mapper is extended
    protected function addColumnConstraint(ColumnMapper $column, string $constraint, $context = true)
    {
        $column->addConstraint($constraint, $context);
    }

    protected function addColumnNameConstraint(string $columName, string $constraint)
    {
        $this->addColumnConstraint($this->getColumnByName($columName), $constraint);
    }

    public function getColumnConstraints(): array
    {
        $constraints = [];
        foreach($this->columns as $column) {
            $constraints[$column->getName()] = $column->getConstraints();
        }
        return $constraints;
    }

    public function getSelectColumnsString(): string 
    {
        return $this->defaultSelectColumnsString;
    }

    public function select(string $columns = "*", array $where = null)
    {
        $q = new SelectBuilder("SELECT $columns", "FROM $this->tableName", $where, $this->getOrderBy($this->orderByColumnName, $this->orderByAsc));
        return $q->execute();
    }

    private function getOrderBy(string $orderByColumn = null, bool $orderByAsc = true): ?string
    {
        if ($orderByColumn != null) {
            if ($orderByColumn == 'PRIMARYKEY') {
                if ($this->primaryKeyColumnName === false) {
                    throw new \Exception("Cannot order by Primary Key since it does not exist for table ".$this->tableName);
                }
                $orderByColumn = $this->primaryKeyColumnName;
            }
            $orderByString = "$orderByColumn";
            if (!$orderByAsc) {
                $orderByString .= " DESC";
            }
            return $orderByString;
        }
        return null;
    }

    public function hasColumnValue(ColumnMapper $databaseColumnMapper, $value): bool
    {
        $q = new QueryBuilder("SELECT ".$this->getPrimaryKeyColumnName()." FROM $this->tableName WHERE ".$databaseColumnMapper->getName()." = $1", [$value]);
        return (bool) $q->getOne();
    }

    public function selectForPrimaryKey($primaryKeyValue, string $columns = "*")
    {
        $primaryKeyName = $this->getPrimaryKeyColumnName();

        $q = new QueryBuilder("SELECT $columns FROM $this->tableName WHERE $primaryKeyName = $1", $primaryKeyValue);
        if(!$res = $q->execute()) {
            // this is for a query error not a not found condition
            throw new \Exception("Invalid $primaryKeyName for $this->table: $primaryKeyValue");
        }
        return pg_fetch_assoc($res); // returns false if no records are found
    }

    protected function addBooleanColumnValues(array $columnValues): array
    {
        foreach ($this->columns as $column) {
            if ($column->isBoolean() && !isset($columnValues[$column->getName()])) {
                $columnValues[$column->getName()] = Postgres::BOOLEAN_FALSE;
            }
        }

        return $columnValues;
    }

    /** returns primary key if set, otherwise returns pg result */
    public function insert(array $columnValues, bool $addBooleanColumnValues = false)
    {
        if ($addBooleanColumnValues) {
            $columnValues = $this->addBooleanColumnValues($columnValues);
        }
        $ib = new InsertBuilder($this->tableName);
        if ($this->getPrimaryKeyColumnName() !== null) {
            $ib->setPrimaryKeyName($this->getPrimaryKeyColumnName());
        }
        $this->addColumnsToBuilder($ib, $columnValues);
        return $ib->runExecute();
    }

    public function getChangedColumnsValues(array $inputValues, array $record): array
    {
        $changedColumns = [];
        foreach ($inputValues as $columnName => $value) {
            // throw out any new values that are not table columns
            if ($this->getColumnByName($columnName) !== null && $value != $record[$columnName]) {
                $changedColumns[$columnName] = $value;
            }
        }
        return $changedColumns;
    }

    /**
     * @param array $input
     * @param $primaryKeyValue
     * @param bool $getChangedValues :: default true. if true calls getChangedColumnsValues in order to send only changed to update builder, otherwise all $input is sent to update builder. set false if input only includes changed values in order to not duplicate checking for changes.
     * @param array $record :: best to include if $getChangedValues is true in order to not duplicate select query
     * @param bool $addBooleanColumnValues if true calls method which adds in boolean columns that don't exist in the input
     * @return \SlimPostgres\Database\Queries\recordset
     */
    public function updateByPrimaryKey(array $input, $primaryKeyValue, bool $getChangedValues = true, array $record = [], bool $addBooleanColumnValues = false)
    {
        if ($addBooleanColumnValues) {
            $input = $this->addBooleanColumnValues($input);
        }

        if ($getChangedValues) {
            if (count($record) == 0) {
                $record = $this->selectForPrimaryKey($primaryKeyValue);
            }
            $updateColumnValues = $this->getChangedColumnsValues($input, $record);
        } else {
            $updateColumnValues = $input;
        }

        $ub = new UpdateBuilder($this->tableName, $this->getPrimaryKeyColumnName(), $primaryKeyValue);
        $this->addColumnsToBuilder($ub, $updateColumnValues);
        return $ub->runExecute();
    }

    // returns query result
    public function deleteByPrimaryKey($primaryKeyValue, string $returning = null)
    {
        $query = "DELETE FROM $this->tableName WHERE ".$this->getPrimaryKeyColumnName()." = $1";
        if ($returning !== null) {
            $query .= " RETURNING $returning";
        }
        $q = new QueryBuilder($query, $primaryKeyValue);

        $dbResult = $q->execute();

        if (pg_affected_rows($dbResult) == 0) {
            throw new Exceptions\QueryResultsNotFoundException();
        }

        return $dbResult;
    }

    private function addColumnsToBuilder(InsertUpdateBuilder $builder, array $columnValues)
    {
        foreach ($columnValues as $name => $value) {
            // make sure this is truly a column
            if (null !== $column = $this->getColumnByName($name)) {
                if (is_string($value) && mb_strlen($value) == 0) {
                    $value = $this->handleBlankValue($column);
                }
                $builder->addColumn($name, $value);
            }
        }
    }

    private function handleBlankValue(ColumnMapper $column)
    {
        // set to null if field is nullable
        if ($column->getIsNullable()) {
            return null;
        }

        // set to 0 if field is numeric
        if ($column->isNumericType()) {
            return 0;
        }

        // set to f if field is boolean
        if ($column->isBoolean()) {
            return Postgres::BOOLEAN_FALSE;
        }

        return '';
    }

    // getters

    /**
     * @param bool $plural - if false last character is removed
     * @return string
     */
    public function getFormalTableName(bool $plural = true): string
    {
        $name = ucwords(str_replace('_', ' ', $this->tableName));
        if (!$plural) {
            $name = substr($name, 0, mb_strlen($this->tableName) - 1);
        }
        return $name;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getPrimaryKeyColumnName(): ?string
    {
        return $this->primaryKeyColumnName;
    }

    public function getUpdateColumnName(): ?string
    {
        return $this->getPrimaryKeyColumnName();
    }

    public function getOrderByColumnName(): ?string
    {
        return $this->orderByColumnName;
    }

    public function getOrderByAsc(): bool
    {
        return $this->orderByAsc;
    }

    public function getColumns(): array
    {
        if (count($this->columns) == 0) {
            throw new \Exception('No columns in table '.$this->tableName);
        }
        return $this->columns;
    }

    public function getColumnNames(): array 
    {
        return $this->columnNames;
    }

    public function getColumnByName(string $columnName): ?ColumnMapper
    {
        foreach ($this->columns as $column) {
            if ($column->getName() == $columnName) {
                return $column;
            }
        }

        return null;
    }

    public function getUniqueColumnNames(): array
    {
        return $this->uniqueColumnNames;
    }

    public function getUniqueColumns(): array
    {
        return $this->uniqueColumns;
    }

    public function getCountSelectColumns(): int
    {
        if ($this->defaultSelectColumnsString == '*') {
            return count($this->columns);
        }

        return count(explode(",", $this->defaultSelectColumnsString));
    }
}
