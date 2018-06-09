<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators;

use SlimPostgres\Database\SingleTable\SingleTableModel;
use SlimPostgres\Database\Queries\QueryBuilder;
use SlimPostgres\Database\Queries\SelectBuilder;
use SlimPostgres\Database\MultiTable\MultiTableModel;

class AdministratorsModel extends MultiTableModel
{
    const TABLE_NAME = 'administrators';

    const SELECT_COLUMNS = [
        'id' => 'administrators.id',
        'name' => 'administrators.name',
        'username' => 'administrators.username',
        'role' => 'roles.role',
        'level' => 'roles.level'
    ];

    public function __construct()
    {
        parent::__construct(new SingleTableModel(self::TABLE_NAME), self::SELECT_COLUMNS);
    }

    public function getOrderByColumnName(): ?string
    {
        return 'level';
    }

    public function insert(string $name, string $username, string $password, int $roleId)
    {
        $q = new QueryBuilder("INSERT INTO ".self::TABLE_NAME." (name, username, password_hash, role_id) VALUES($1, $2, $3, $4) RETURNING id", $name, $username, password_hash($password, PASSWORD_DEFAULT));
        return $q->execute();
    }

    public function getChangedColumnsValues(array $inputValues, array $record): array
    {
        $changedColumns = [];
        foreach ($inputValues as $columnName => $value) {
            // throw out any new values that are not table columns
            if ($this->getColumnByName($columnName) !== null && $value != $record[$columnName]) {
                // do not add blank password to changed columns
                if ($columnName == 'password') {
                    if (mb_strlen($value) > 0) {
                        $passwordHash = password_hash($value, PASSWORD_DEFAULT);
                        if ($passwordHash != $record['password_hash']) {
                            $changedColumns['password_hash'] = $passwordHash;
                        }
                    }
                } else {
                    $changedColumns[$columnName] = $value;
                }
            }
        }
        return $changedColumns;
    }

    public function checkRecordExistsForUsername(string $username): bool
    {
        $q = new QueryBuilder("SELECT id FROM ".self::TABLE_NAME." WHERE username = $1", $username);
        $res = $q->execute();
        return pg_num_rows($res) > 0;
    }

    public function selectForUsername(string $username)
    {
        $q = new QueryBuilder("SELECT a.*, r.role FROM ".self::TABLE_NAME." a JOIN roles r ON a.role_id = r.id WHERE a.username = $1", $username);
        return $q->execute();
    }

    public function select(array $whereColumnsInfo = null)
    {
        $selectClause = "SELECT ";
        $columnCount = 1;
        foreach (self::SELECT_COLUMNS as $columnNameSql) {
            $selectClause .= $columnNameSql;
            if ($columnCount != count(self::SELECT_COLUMNS)) {
                $selectClause .= ",";
            }
            $columnCount++;
        }
        $fromClause = "FROM ".self::TABLE_NAME." JOIN roles ON ".self::TABLE_NAME.".role_id = roles.id";
        $orderByClause = "ORDER BY roles.level";
        if ($whereColumnsInfo != null) {
            $this->validateFilterColumns($whereColumnsInfo);
        }
        $q = new SelectBuilder($selectClause, $fromClause, $whereColumnsInfo, $orderByClause);
        return $q->execute();
    }

}
