<?php
declare(strict_types=1);

namespace SlimPostgres\Database;

Interface TableModel
{
    public function select(array $whereColumnsInfo = null);
    public function getTableName(bool $plural = true): string;
    public function getOrderByColumnName(): ?string;
    public function getOrderByAsc(): bool;
}