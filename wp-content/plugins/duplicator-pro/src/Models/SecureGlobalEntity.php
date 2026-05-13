<?php

namespace Duplicator\Models;

use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitGenericModelSingleton;
use Duplicator\Core\Models\UpdateFromInputInterface;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use ReflectionClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * Secure Global Entity. Used to store settings requiring encryption.
 *
 * @deprecated Use DynamicGlobalEntity key basic_auth_password and license_key_visible_pwd instead
 */
class SecureGlobalEntity extends AbstractEntity implements UpdateFromInputInterface, ModelMigrateSettingsInterface
{
    use TraitGenericModelSingleton;

    /**
     * @var        ?string
     * @deprecated Use DynamicGlobalEntity key basic_auth_password instead
     */
    public $basic_auth_password = '';
    /**
     * @var        ?string
     * @deprecated Use DynamicGlobalEntity key license_key_visible_pwd instead
     */
    public $lkp = '';

    /**
     * Class contructor
     */
    protected function __construct()
    {
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType(): string
    {
        return 'Secure_Global_Entity';
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string,mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        if (strlen($this->basic_auth_password)) {
            $data['basic_auth_password'] = CryptBlowfish::encryptIfAvaiable($this->basic_auth_password);
        }
        if (strlen($this->lkp)) {
            $data['lkp'] = CryptBlowfish::encryptIfAvaiable($this->lkp);
        }
        return $data;
    }

    /**
     * Serialize
     *
     * Wakeup method.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->basic_auth_password = (string) $this->basic_auth_password; // for old versions
        if (strlen($this->basic_auth_password)) {
            $this->basic_auth_password = CryptBlowfish::decryptIfAvaiable($this->basic_auth_password);
        }

        $this->lkp = (string) $this->lkp; // for old versions
        if (strlen($this->lkp)) {
            $this->lkp = CryptBlowfish::decryptIfAvaiable($this->lkp);
        }
    }

    /**
     * Set data from query input
     *
     * @param int $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV, SnapUtil::INPUT_REQUEST
     *
     * @return bool true on success or false on failure
     */
    public function setFromInput(int $type): bool
    {
        $input = SnapUtil::getInputFromType($type);

        $this->basic_auth_password = isset($input['basic_auth_password']) ? SnapUtil::sanitizeNSCharsNewlineTrim($input['basic_auth_password']) : '';
        $this->basic_auth_password = stripslashes($this->basic_auth_password);
        return true;
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport(): array
    {
        $skipProps = [
            'id',
            // Skip deprecated properties during settings export
            'lkp',
            'basic_auth_password',
        ];

        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        foreach ($skipProps as $prop) {
            unset($data[$prop]);
        }
        return $data;
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = []): bool
    {
        $skipProps = [
            'id',
            // Skip deprecated properties during settings import
            'lkp',
            'basic_auth_password',
        ];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            if (PHP_VERSION_ID < 80100) {
                $prop->setAccessible(true);
            }
            $prop->setValue($this, $data[$prop->getName()]);
        }
        return true;
    }

    /**
     * Set data from import data
     *
     * @param object $global_data Global data
     *
     * @return void
     */
    public function setFromImportData($global_data): void
    {
        $this->basic_auth_password = $global_data->basic_auth_password;
        // skip in import settings
        //$this->lkp                 = $global_data->lkp;
    }
}
