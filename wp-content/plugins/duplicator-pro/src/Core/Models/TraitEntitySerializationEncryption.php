<?php

namespace Duplicator\Core\Models;

use Duplicator\Models\StaticGlobal;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Logging\DupLog;
use Exception;
use Throwable;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

/**
 * Trait for standardized entity serialization with encryption support.
 *
 * Provides encryption/decryption infrastructure for entity properties.
 * Classes using this trait must declare their own $encryptedProperties array
 * to specify which properties need encryption.
 *
 * Example:
 * protected static array $encryptedProperties = ['password', 'apiKey'];
 */
trait TraitEntitySerializationEncryption
{
    /**
     * Encrypt properties declared in static::$encryptedProperties
     *
     * @param array<string,mixed> $data Data array to encrypt
     *
     * @return array<string,mixed> Data array with encrypted values and __encrypted list
     */
    protected function encryptSerializedProperties(array $data): array
    {
        $this->assertEncryptedPropertiesDefined();

        // Always add __encrypted marker to distinguish new format from legacy
        $encrypted = [];

        // Check if encryption is globally enabled
        if (!StaticGlobal::getCryptOption()) {
            $data['__encrypted'] = $encrypted;
            return $data;
        }

        foreach (static::$encryptedProperties as $property) {
            if (!isset($data[$property])) {
                continue;
            }

            $value = $data[$property];

            $value = JsonSerialize::serialize($value);
            if ($value === false) {
                continue; // Skip if JSON serialization fails
            }

            $encryptedValue = CryptBlowfish::encryptIfAvaiable($value, null, true);

            // Only mark as encrypted if encryption actually changed the value
            if (strlen($encryptedValue) > 0) {
                $data[$property] = $encryptedValue;
                $encrypted[]     = $property;
            }
        }

        // Always add __encrypted array (even if empty) to mark as new format
        $data['__encrypted'] = $encrypted;
        return $data;
    }

    /**
     * Decrypt properties from serialized data.
     *
     * On decryption failure, properties are set to null rather than throwing an exception.
     * The entity's __unserialize() method is responsible for validating decrypted properties
     * and applying appropriate defaults when values are null or invalid.
     *
     * @param array<string,mixed> $data Serialized data array
     *
     * @return array<string,mixed> Data array with decrypted properties
     */
    protected function decryptSerializedProperties(array $data): array
    {
        $this->assertEncryptedPropertiesDefined();

        // Check if there are encrypted properties
        if (!isset($data['__encrypted'])) {
            // Fallback for legacy format without __encrypted tracking
            return $this->legacyDecryptProperties($data);
        }

        $encryptedList = $data['__encrypted'];
        unset($data['__encrypted']); // Remove tracking array from data

        foreach ($encryptedList as $property) {
            if (!isset($data[$property])) {
                continue;
            }

            try {
                // Decrypt the value
                $decryptedValue = CryptBlowfish::decryptIfAvaiable($data[$property], null, true);
                if (strlen($decryptedValue) == 0) {
                    throw new Exception('Decrypt property failed');
                }

                $unserialized = JsonSerialize::unserialize($decryptedValue);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Invalid json Error:' . json_last_error());
                }

                $data[$property] = $unserialized;
            } catch (Throwable $e) {
                DupLog::trace('Decrypt property ' . static::class . ':' . $property . ' Failed error: ' . $e->getMessage());
                // Error invalidate the value
                $data[$property] = null;
            }
        }

        return $data;
    }

    /**
     * Entity override point for legacy format decryption.
     *
     * @param array<string,mixed> $data Serialized data array in legacy format
     *
     * @return array<string,mixed> Data array with legacy format decrypted
     */
    protected function legacyDecryptProperties(array $data): array
    {
        // Default implementation: no legacy encrypted properties
        return $data;
    }

    /**
     * Assert that $encryptedProperties is defined in the class using this trait.
     *
     * @return void
     *
     * @throws Exception If $encryptedProperties is not defined
     */
    private function assertEncryptedPropertiesDefined(): void
    {
        if (
            !property_exists(static::class, 'encryptedProperties') ||
            !is_array(static::$encryptedProperties) // @phpstan-ignore function.alreadyNarrowedType
        ) {
            throw new Exception(
                'Class ' . static::class . ' must define protected static array $encryptedProperties'
            );
        }
    }
}
