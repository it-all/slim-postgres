<?php
declare(strict_types=1);

namespace SlimPostgres\Administrators\Roles\Permissions\View\Forms;

use Slim\Http\Request;
use Slim\Container;
use SlimPostgres\App;
use SlimPostgres\Administrators\Roles\Permissions\Model\PermissionsMapper;
use SlimPostgres\Administrators\Roles\RolesMapper;
use SlimPostgres\Administrators\Roles\Role;
use SlimPostgres\BaseMVC\View\Forms;
use SlimPostgres\BaseMVC\View\Form as BaseForm;
use SlimPostgres\Forms\DatabaseTableForm;
use SlimPostgres\Forms\FormHelper;
use It_All\FormFormer\Form;
use It_All\FormFormer\Fieldset;
use It_All\FormFormer\Fields\InputField;
use It_All\FormFormer\Fields\InputFields\CheckboxRadioInputField;
use SlimPostgres\Database\Postgres;

abstract class PermissionForm extends BaseForm implements Forms
{
    protected $formAction;

    /** @var string 'put' or 'post'
     *  if 'put' the hidden put method field is inserted at the beginning of the form
     */
    private $formMethod;
    private $csrf;
    private $mapper;
    private $permissionValue;
    private $descriptionValue;

    /** @var array */
    private $rolesValue; 

    /** @var bool */
    private $activeValue; 

    const TITLE_FIELD_NAME = 'title';
    const DESCRIPTION_FIELD_NAME = 'description';
    const ROLES_FIELDSET_NAME = 'roles';
    const ACTIVE_FIELD_NAME = 'active';

    /** bool, controls whether insert checkbox defaults to checked (true) or unchecked (false) */
    const DEFAULT_ACTIVE_VALUE = true;

    public static function getFieldNames(): array
    {
        return [
            self::TITLE_FIELD_NAME,
            self::DESCRIPTION_FIELD_NAME,
            self::ROLES_FIELDSET_NAME,
            self::ACTIVE_FIELD_NAME,
        ];
    }
    
    public function __construct(string $formAction, Container $container, bool $isPutMethod = false, array $fieldValues = [])
    {
        parent::__construct($formAction, $container, $isPutMethod);
        $this->authorization = $container->authorization;
        $this->mapper = PermissionsMapper::getInstance();
        $this->setFieldValues($fieldValues);
    }

    protected function setFieldValues(array $fieldValues = [])
    {
        $this->permissionValue = (isset($fieldValues[self::TITLE_FIELD_NAME])) ? $fieldValues[self::TITLE_FIELD_NAME] : '';

        $this->descriptionValue = (isset($fieldValues[self::DESCRIPTION_FIELD_NAME])) ? $fieldValues[self::DESCRIPTION_FIELD_NAME] : '';

        $this->rolesValue = (isset($fieldValues[self::ROLES_FIELDSET_NAME])) ? $fieldValues[self::ROLES_FIELDSET_NAME] : [];

        /** this must be set to a bool. it may come in as "on" or may not be set. set to default if not set, or false if already false otherwise true */
        if (!isset($fieldValues[self::ACTIVE_FIELD_NAME])) {
            $this->activeValue = self::DEFAULT_ACTIVE_VALUE;
        } else {
            $this->activeValue = ($fieldValues[self::ACTIVE_FIELD_NAME] === false) ? false : true;
        }
    }

    private function getTitleField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::TITLE_FIELD_NAME), null, $this->permissionValue);
    }

    private function getDescriptionField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::DESCRIPTION_FIELD_NAME), null, $this->descriptionValue);
    }

    private function getRolesFieldset() 
    {
        // Roles Checkboxes
        $rolesMapper = RolesMapper::getInstance();
        $rolesCheckboxes = [];
        foreach ($rolesMapper->getRoles() as $roleId => $roleData) {
            $rolesCheckboxAttributes = [
                'type' => 'checkbox',
                'name' => self::ROLES_FIELDSET_NAME . '[]',
                'value' => $roleId,
                'id' => 'roles' . $roleData['role'],
                'class' => 'inlineFormField'
            ];
            // checked? owner is always
            if ($roleData['role'] == Role::TOP_ROLE || (isset($this->rolesValue) && in_array($roleId, $this->rolesValue))) {
                $rolesCheckboxAttributes['checked'] = 'checked';
                if ($roleData['role'] == 'owner') {
                    $rolesCheckboxAttributes['disabled'] = 'disabled';
                }
            }
            $rolesCheckboxes[] = new CheckboxRadioInputField($roleData['role'], $rolesCheckboxAttributes);
        }
        
        return new Fieldset($rolesCheckboxes, [], true, 'Roles', null, FormHelper::getFieldError(self::ROLES_FIELDSET_NAME, true));
    }

    private function getActiveField()
    {
        return DatabaseTableForm::getFieldFromDatabaseColumn($this->mapper->getColumnByName(self::ACTIVE_FIELD_NAME), null, Postgres::convertBoolToPostgresBool($this->activeValue));
    }

    protected function getNodes(): array 
    {
        $nodes = [];
        
        $nodes[] = $this->getActiveField();
        $nodes[] = $this->getTitleField();
        $nodes[] = $this->getDescriptionField();
        $nodes[] = $this->getRolesFieldset();

        $nodes = array_merge($nodes, $this->getCommonNodes());
        return $nodes;
    }
}
