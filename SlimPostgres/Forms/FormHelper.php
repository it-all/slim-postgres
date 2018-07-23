<?php
declare(strict_types=1);

namespace SlimPostgres\Forms;

use It_All\FormFormer\Fields\InputField;
use SlimPostgres\App;
use SlimPostgres\Database\DataMappers\ColumnMapper;
use SlimPostgres\Database\DataMappers\TableMapper;
use SlimPostgres\Database\Postgres;

class FormHelper
{
    const SESSION_ERRORS_KEY = 'formErrors';
    const GENERAL_ERROR_KEY = 'generalFormError';
    const FIELD_ERROR_CLASS = 'formFieldError';

    public static function setGeneralError(string $errorMessage)
    {
        $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] = $errorMessage;
    }

    public static function setFieldErrors(array $fieldErrors)
    {
        $_SESSION[self::SESSION_ERRORS_KEY] = $fieldErrors;
    }

    public static function getGeneralError(): string
    {
        return (isset($_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY])) ? $_SESSION[self::SESSION_ERRORS_KEY][self::GENERAL_ERROR_KEY] : '';
    }

    /**
     * @param string $fieldName
     * returns empty string rather than null to be compatible with FormFormer field instantiation
     */
    public static function getFieldError(string $fieldName): string
    {
        if (isset($_SESSION[self::SESSION_ERRORS_KEY][$fieldName])) {
            return $_SESSION[self::SESSION_ERRORS_KEY][$fieldName];
        }

        return '';
    }

    public static function getFieldValue(string $fieldName): string
    {
        return (isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT][$fieldName])) ? $_SESSION[App::SESSION_KEY_REQUEST_INPUT][$fieldName] : '';
    }

    private static function getCommonFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        $attributes = [];

        // name: use field name if supplied, otherwise addAttributes['name'] will be used if supplied
        if (mb_strlen($fieldName) > 0) {
            $attributes['name'] = $fieldName;
            unset($addAttributes['name']);
        }

        // error class
        if (mb_strlen(self::getFieldError($fieldName)) > 0) {
            if (array_key_exists('class', $addAttributes)) {
                $attributes['class'] = $addAttributes['class'] . " " . self::FIELD_ERROR_CLASS;
                unset($addAttributes['name']);
            } else {
                $attributes['class'] = self::FIELD_ERROR_CLASS;
            }
        }

        return array_merge($attributes, $addAttributes);
    }

    public static function getInputFieldAttributes(string $fieldName = '', array $addAttributes = [], bool $insertValue = true): array
    {
        $attributes = [];

        // value - does not overwrite if in addAttributes
        if (!array_key_exists('value', $addAttributes) && $insertValue) {
            $attributes['value'] = self::getFieldValue($fieldName);
        }
        return array_merge(self::getCommonFieldAttributes($fieldName, $addAttributes), $attributes);
    }

    public static function getTextareaFieldAttributes(string $fieldName = '', array $addAttributes = []): array
    {
        return self::getCommonFieldAttributes($fieldName, $addAttributes);
    }

    public static function getCsrfNameField(string $csrfNameKey, string $csrfNameValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfNameKey, 'value' => $csrfNameValue]);
    }

    public static function getCsrfValueField(string $csrfValueKey, string $csrfValueValue)
    {
        return new InputField('', ['type' => 'hidden', 'name' => $csrfValueKey, 'value' => $csrfValueValue]);
    }

    public static function getPutMethodField()
    {
        return new InputField('', ['type' => 'hidden', 'name' => '_METHOD', 'value' => 'PUT']);
    }

    public static function getSubmitField(string $value = 'Enter')
    {
        return new InputField('', ['type' => 'submit', 'name' => 'submit', 'value' => $value]);
    }

    // note: this is confusing.
    public static function getCancelField(string $value = 'Cancel')
    {
        return new InputField('', ['type' => 'submit', 'name' => 'cancel', 'value' => $value, 'onclick' => 'if(confirm(\'Press OK to cancel\nPress Cancel to cancel canceling\')){return true;}']);
    }

    public static function unsetSessionInput()
    {
        if (isset($_SESSION[App::SESSION_KEY_REQUEST_INPUT])) {
            unset($_SESSION[App::SESSION_KEY_REQUEST_INPUT]);
        }
    }

    public static function unsetSessionFormErrors()
    {
        if (isset($_SESSION[self::SESSION_ERRORS_KEY])) {
            unset($_SESSION[self::SESSION_ERRORS_KEY]);
        }
    }

    public static function unsetFormSessionVars()
    {
        self::unsetSessionInput();
        self::unsetSessionFormErrors();
    }
}
