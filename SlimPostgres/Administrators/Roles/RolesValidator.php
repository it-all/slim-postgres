<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\DatabaseTableFormValidator;

// can add custom validation rules to roles 
class RolesValidator extends DatabaseTableFormValidator
{
    public function __construct(array $inputData, string $databaseAction = 'insert', array $record = null)
    {
        if ($databaseAction != 'insert' && $databaseAction != 'update') {
            throw new \InvalidArgumentException("databaseAction must be insert or update: $databaseAction");
        }
        if ($databaseAction == 'insert' && $record !== null) {
            throw new \InvalidArgumentException("insert action must not have record");
        }

        parent::__construct($inputData, RolesMapper::getInstance());

        if ($databaseAction == 'update') {
            $skipUniqueForUnchanged = true;
        } else {
            $skipUniqueForUnchanged = false;
            $record = []; // override even if entered
        }

        parent::setRules($skipUniqueForUnchanged, $record);

        // add any custom rules below ie to level (not necessary because already picked up by column type and constraint):
        // $this->rule('integer', 'level');
        // $this->rule('min', 'level', 0);
    }
}
