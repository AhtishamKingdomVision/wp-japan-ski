<?php

namespace Duplicator\Models;

use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Core\Models\TraitEntitySerializationEncryption;
use Duplicator\Core\Models\TraitGenericModelSingleton;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Exception;
use ReflectionClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * Dynamic Global Entity values
 */
class DynamicGlobalEntity extends AbstractEntity implements ModelMigrateSettingsInterface
{
    use TraitGenericModelSingleton;
    use TraitEntitySerializationEncryption;

    /**
     * Properties to encrypt during serialization
     *
     * @var string[]
     */
    protected static array $encryptedProperties = ['data'];

    /** @var array<string,scalar> Entity data */
    protected array $data = [];

    /**
     * Class constructor
     */
    protected function __construct()
    {
    }

    /**
     * Serialize the entity
     *
     * @return array<string,mixed>
     */
    public function __serialize(): array
    {
        $data = JsonSerialize::serializeToData(
            $this,
            JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME
        );

        return $this->encryptSerializedProperties($data);
    }

    /**
     * Unserialize the entity
     *
     * @param array<string,mixed> $data Data to unserialize
     *
     * @return void
     */
    public function __unserialize(array $data): void
    {
        $data = $this->decryptSerializedProperties($data);

        if (!is_array($data['data'])) {
            error_log("ERROR UNSERIALIZE DATA: " . json_encode($data['data'], JSON_PRETTY_PRINT) . " RESET TO EMPTY ARRAY");
            // In case of error, set the default value
            $data['data'] = [];
        }

        foreach ($data as $pName => $val) {
            if (!property_exists($this, $pName)) {
                continue;
            }
            $this->$pName = $val;
        }
    }

    /**
     * Handle legacy format decryption (dataIsEncrypted marker)
     *
     * @param array<string,mixed> $data Serialized data in legacy format
     *
     * @return array<string,mixed> Decrypted data
     */
    protected function legacyDecryptProperties(array $data): array
    {
        if (isset($data['dataIsEncrypted']) && $data['dataIsEncrypted']) {
            $decrypted    = CryptBlowfish::decryptIfAvaiable($data['data'], null, true);
            $data['data'] = json_decode($decrypted, true);
        }
        unset($data['dataIsEncrypted']);

        return $data;
    }

    /**
     * Retrieve the value of a key
     *
     * @param string  $key     Option name
     * @param ?scalar $default Default value to return if the key doesn't exist
     *
     * @return ?scalar
     */
    public function getVal(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get a value as integer
     *
     * @param string $key     Option name
     * @param int    $default Default value to return if the key doesn't exist
     *
     * @return int
     */
    public function getValInt(string $key, int $default = 0): int
    {
        $value = $this->getVal($key, $default);
        return (int) $value;
    }

    /**
     * Get a value as string
     *
     * @param string $key     Option name
     * @param string $default Default value to return if the key doesn't exist
     *
     * @return string
     */
    public function getValString(string $key, string $default = ''): string
    {
        $value = $this->getVal($key, $default);
        return (string) $value;
    }

    /**
     * Get a value as boolean
     *
     * @param string $key     Option name
     * @param bool   $default Default value to return if the key doesn't exist
     *
     * @return bool
     */
    public function getValBool(string $key, bool $default = false): bool
    {
        $value = $this->getVal($key, $default);
        return (bool) $value;
    }

    /**
     * Get a value as float
     *
     * @param string $key     Option name
     * @param float  $default Default value to return if the key doesn't exist
     *
     * @return float
     */
    public function getValFloat(string $key, float $default = 0.0): float
    {
        $value = $this->getVal($key, $default);
        return (float) $value;
    }

    /**
     * Set option value
     *
     * @param string  $key   Option name
     * @param ?scalar $value Option value
     * @param bool    $save  Save on DB
     *
     * @return bool
     */
    public function setVal(string $key, $value = null, bool $save = false): bool
    {
        if (strlen($key) == 0) {
            throw new Exception('Invalid key');
        }
        if (!is_scalar($value) && $value !== null) {
            throw new Exception('Invalid value, only scalar or null values are allowed');
        }
        $this->data[$key] = $value;
        return ($save ? $this->save() : true);
    }

    /**
     * Set an integer value
     *
     * @param string $key   Option name
     * @param int    $value Option value
     * @param bool   $save  If true the entity is saved
     *
     * @return void
     */
    public function setValInt(string $key, int $value = 0, bool $save = false): void
    {
        $this->setVal($key, $value, $save);
    }

    /**
     * Set a string value
     *
     * @param string $key   Option name
     * @param string $value Option value
     * @param bool   $save  If true the entity is saved
     *
     * @return void
     */
    public function setValString(string $key, string $value = '', bool $save = false): void
    {
        $this->setVal($key, $value, $save);
    }

    /**
     * Set a boolean value
     *
     * @param string $key   Option name
     * @param bool   $value Option value
     * @param bool   $save  If true the entity is saved
     *
     * @return void
     */
    public function setValBool(string $key, bool $value = false, bool $save = false): void
    {
        $this->setVal($key, $value, $save);
    }

    /**
     * Set a float value
     *
     * @param string $key   Option name
     * @param float  $value Option value
     * @param bool   $save  If true the entity is saved
     *
     * @return void
     */
    public function setValFloat(string $key, float $value = 0.0, bool $save = false): void
    {
        $this->setVal($key, $value, $save);
    }

    /**
     * Value exists
     *
     * @param string $key Option name
     *
     * @return bool
     */
    public function valExists(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Delete option value
     *
     * @param string $key  Option name
     * @param bool   $save Save on DB
     *
     * @return bool
     */
    public function removeVal(string $key, bool $save = false): bool
    {
        if (!isset($this->data[$key])) {
            return true;
        }

        unset($this->data[$key]);
        return ($save ? $this->save() : true);
    }

    /**
     * @return string
     */
    public static function getType(): string
    {
        return 'Dynamic_Entity';
    }

    /**
     * Get reset data to skip on user settings reset
     *
     * @return array<string>
     */
    public static function getResetDataToSkip(): array
    {
        return apply_filters('duplicator_dynamic_data_skip_reset', []);
    }

    /**
     * Reset user settings
     *
     * @return bool True if success, otherwise false
     */
    public function resetUserSettings(): bool
    {
        $skipResetData = self::getResetDataToSkip();
        foreach ($this->data as $key => $value) {
            if (in_array($key, $skipResetData)) {
                continue;
            }
            $this->removeVal($key);
        }
        return $this->save();
    }

    /**
     * Get data to skip on export
     *
     * @return array<string>
     */
    public function getSkipDataExport(): array
    {
        return apply_filters('duplicator_dynamic_skip_data_export', []);
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport(): array
    {
        $data           = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        $skipDataExport = $this->getSkipDataExport();

        foreach ($data['data'] as $key => $value) {
            if (in_array($key, $skipDataExport)) {
                unset($data['data'][$key]);
            }
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
    public function settingsImport(array $data, string $dataVersion, array $extraData = []): bool
    {
        $skipProps      = [
            'id',
            'data',
        ];
        $skipDataExport = $this->getSkipDataExport();

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

        foreach ($data['data'] as $key => $value) {
            if (in_array($key, $skipDataExport)) {
                continue;
            }
            $this->data[$key] = $value;
        }

        return true;
    }
}
