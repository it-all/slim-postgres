<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles;

use SlimPostgres\ListViewModels;

// model 
class Role implements ListViewModels
{
    private $id;
    private $roleName;
    private $level;

    public function __construct(int $id, string $roleName, int $level)
    {
        $this->id = $id;
        $this->roleName = $roleName;
        $this->level = $level;
    }

    public function getId(): int 
    {
        return $this->id;
    }

    public function getRoleName(): string 
    {
        return $this->roleName;
    }

    public function getLevel(): int 
    {
        return $this->level;
    }

    /** returns array of list view fields [fieldName => fieldValue] */
    public function getListViewFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->roleName,
            'level' => $this->level,
        ];
    }

    /** whether model is allowed to be updated */
    public function isUpdatable(): bool
    {
        return (RolesMapper::getInstance())->isUpdatable($this->id);
    }

    /** whether this model is allowed to be deleted 
     *  do not allow roles in use (assigned to administrators) to be deleted
     */
    public function isDeletable(): bool
    {
        return (RolesMapper::getInstance())->isDeletable($this->id);
    }

    public function getUniqueId(): ?string
    {
        return (string) $this->id;
    }
}
