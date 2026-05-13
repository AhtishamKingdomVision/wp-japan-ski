<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Utils\Crypt;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Libs\WpConfig\WPConfigTransformer;
use Error;
use Exception;
use Throwable;
use VendorDuplicator\phpseclib3\Crypt\Blowfish;

class CryptBlowfish implements CryptInterface
{
    const AUTH_DEFINE_NAME_OLD = 'DUP_SECURE_KEY'; // OLD define name
    const AUTH_DEFINE_NAME     = 'DUPLICATOR_AUTH_KEY';
    const AUTO_SALT_LEN        = 32;
    const CBC_MARKER           = 'CBC:'; // Marker to identify CBC encrypted strings
    const IV_LENGTH            = 8; // Blowfish block size in bytes

    /** @var string */
    protected static $tempDefinedKey;

    /**
     * Check if encryption is available
     *
     * @return bool
     */
    public static function isEncryptAvailable()
    {
        // Check only once to avoid too much trace
        static $check = null;
        if ($check === null) {
            try {
                $stringToEncrypt = 'Check Encryption';
                $test            = self::encrypt($stringToEncrypt);
                $result          = self::decrypt($test);
                $check           = ($result === $stringToEncrypt);
            } catch (Exception | Error $e) {
                $check = false;
            }

            if ($check === false) {
                DupLog::trace('Encryption is not available, check failed');
            }
        }
        return $check;
    }

    /**
     * Create wp-config dup secure key
     *
     * @param bool $overwrite  if it is false and the key already exists it is not modified
     * @param bool $fromLegacy if true save legacy key
     *
     * @return bool
     */
    public static function createWpConfigSecureKey($overwrite = false, $fromLegacy = false)
    {
        $result = false;

        try {
            if (($wpConfig = SnapWP::getWPConfigPath()) == false) {
                return false;
            }

            if (!is_writeable($wpConfig)) {
                throw new Exception('wp-config isn\'t writeable');
            }

            $authVal = $fromLegacy ? self::getLegacyKey() : SnapUtil::generatePassword(64, true, true);

            $transformer = new WPConfigTransformer($wpConfig);

            if ($transformer->exists('constant', self::AUTH_DEFINE_NAME_OLD) && !$transformer->exists('constant', self::AUTH_DEFINE_NAME)) {
                $authVal = $transformer->getValue('constant', self::AUTH_DEFINE_NAME_OLD);
                $result  = $transformer->update('constant', self::AUTH_DEFINE_NAME, $authVal);
            } elseif ($transformer->exists('constant', self::AUTH_DEFINE_NAME)) {
                if ($overwrite) {
                    $result = $transformer->update('constant', self::AUTH_DEFINE_NAME, $authVal);
                }
            } else {
                $result = $transformer->add('constant', self::AUTH_DEFINE_NAME, $authVal);
            }

            if ($result) {
                self::$tempDefinedKey = $authVal;
            }

            // Remove old constant if new one is prepared/exists
            if ($transformer->exists('constant', self::AUTH_DEFINE_NAME_OLD) && $transformer->exists('constant', self::AUTH_DEFINE_NAME)) {
                $transformer->remove('constant', self::AUTH_DEFINE_NAME_OLD);
            }
        } catch (Exception | Error $e) {
            DupLog::trace('Can\'t create wp-config secure key, error: ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Get default key encryption
     *
     * @return string
     */
    public static function getDefaultKey()
    {
        if (self::$tempDefinedKey !== null) {
            return self::$tempDefinedKey;
        } elseif (strlen(constant(self::AUTH_DEFINE_NAME)) > 0) {
            return constant(self::AUTH_DEFINE_NAME);
        } elseif (defined(self::AUTH_DEFINE_NAME_OLD) && strlen(constant(self::AUTH_DEFINE_NAME_OLD)) > 0) {
            return constant(self::AUTH_DEFINE_NAME_OLD);
        } else {
            return self::getLegacyKey();
        }
    }


    /**
     * Get legacy key encryption
     *
     * NOTE: This method uses MD5 intentionally for backward compatibility with deprecated code.
     * It is only used as a temporary fallback until createWpConfigSecureKey() generates a secure key.
     * Do not change the algorithm as it would break decryption of existing encrypted data.
     *
     * @return string
     */
    protected static function getLegacyKey(): string
    {
        $auth_key  = defined('AUTH_KEY') ? AUTH_KEY : 'atk';
        $auth_key .= defined('DB_HOST') ? DB_HOST : 'dbh';
        $auth_key .= defined('DB_NAME') ? DB_NAME : 'dbn';
        $auth_key .= defined('DB_USER') ? DB_USER : 'dbu';
        return hash('md5', $auth_key);
    }

    /**
     * Return encrypt string using CBC mode with random IV
     *
     * @param string $string  string to encrypt
     * @param string $key     hash key
     * @param bool   $addSalt if true add HASH salt to string
     *
     * @return string renturn empty string if error
     */
    public static function encrypt($string, $key = null, $addSalt = false): string
    {
        try {
            if ($key == null) {
                $key = self::getDefaultKey();
            }

            if ($addSalt) {
                $string = SnapUtil::generatePassword(self::AUTO_SALT_LEN, true, true) . $string . SnapUtil::generatePassword(self::AUTO_SALT_LEN, true, true);
            }

            // Generate random IV for CBC mode
            $iv = random_bytes(self::IV_LENGTH);

            $crypt = new Blowfish('cbc');
            $crypt->setKey($key);
            $crypt->setIV($iv);
            $crypt->disablePadding();

            $expectedLength = self::IV_LENGTH * (int) ceil(strlen($string) / self::IV_LENGTH);
            $string         = str_pad($string, $expectedLength, "\0");

            $encrypted_value = $crypt->encrypt($string);

            // Prepend IV to ciphertext for decryption
            $encrypted_value = $iv . $encrypted_value;
        } catch (Exception | Error $e) {
            DupLog::traceException($e, "Error encrypting string");
            return '';
        }

        // Add CBC marker prefix to identify new format
        return self::CBC_MARKER . base64_encode($encrypted_value);
    }

    /**
     * Encrypt if encryption is available or return the original string
     *
     * @param string $string  string to encrypt
     * @param string $key     hash key
     * @param bool   $addSalt if true add HASH salt to string
     *
     * @return string renturn empty string if error
     */
    public static function encryptIfAvaiable($string, $key = null, $addSalt = false)
    {
        if (self::isEncryptAvailable()) {
            return self::encrypt($string, $key, $addSalt);
        }
        return $string;
    }

    /**
     * Return decrypt string (supports both CBC and legacy ECB formats)
     *
     * @param string $string     string to decrypt
     * @param string $key        hash key
     * @param bool   $removeSalt if true remove HASH salt from string
     *
     * @return string renturn empty string if error
     */
    public static function decrypt($string, $key = null, $removeSalt = false): string
    {
        try {
            $string = (string) $string;
            if (strlen($string) === 0) {
                throw new Exception("Empty string to decrypt");
            }

            if ($key == null) {
                $key = self::getDefaultKey();
            }

            // Check if this is CBC format (new) or ECB format (legacy)
            $isCbc = strpos($string, self::CBC_MARKER) === 0;

            if ($isCbc) {
                $decrypted_value = self::decryptCbc($string, $key);
            } else {
                $decrypted_value = self::decryptEcbLegacy($string, $key);
            }

            $decrypted_value = str_replace("\0", '', $decrypted_value);

            if ($removeSalt) {
                $decrypted_value = substr($decrypted_value, self::AUTO_SALT_LEN, (strlen($decrypted_value) - (self::AUTO_SALT_LEN * 2)));
            }
        } catch (Throwable $e) {
            DupLog::traceException($e, "Error decrypting string");
            return '';
        }

        return (string) $decrypted_value;
    }

    /**
     * Decrypt string encrypted with CBC mode
     *
     * @param string $string CBC encrypted string with marker prefix
     * @param string $key    encryption key
     *
     * @return string decrypted value
     *
     * @throws Exception if decryption fails
     */
    protected static function decryptCbc(string $string, string $key): string
    {
        // Remove CBC marker prefix
        $string = substr($string, strlen(self::CBC_MARKER));

        $decoded = base64_decode($string);
        if ($decoded === false) {
            throw new Exception("Bad CBC encrypted string base64 encoded");
        }

        // Extract IV (first 8 bytes) and ciphertext
        if (strlen($decoded) < self::IV_LENGTH) {
            throw new Exception("CBC encrypted data too short");
        }

        $iv         = substr($decoded, 0, self::IV_LENGTH);
        $ciphertext = substr($decoded, self::IV_LENGTH);

        $crypt = new Blowfish('cbc');
        $crypt->setKey($key);
        $crypt->setIV($iv);
        $crypt->disablePadding();

        return $crypt->decrypt($ciphertext);
    }

    /**
     * Decrypt string encrypted with legacy ECB mode
     *
     * @param string $string ECB encrypted string (base64 encoded)
     * @param string $key    encryption key
     *
     * @return string decrypted value
     *
     * @throws Exception if decryption fails
     */
    protected static function decryptEcbLegacy(string $string, string $key): string
    {
        $decoded = base64_decode($string);
        if ($decoded === false) {
            throw new Exception("Bad ECB encrypted string: invalid base64 encoding");
        }

        $crypt = new Blowfish('ecb');
        $crypt->disablePadding();
        $crypt->setKey($key);

        return $crypt->decrypt($decoded);
    }

    /**
     * Decrypt if encryption is available or return the original string
     *
     * @param string $string     string to decrypt
     * @param string $key        hash key
     * @param bool   $removeSalt if true remove HASH salt from string
     *
     * @return string renturn empty string if error
     */
    public static function decryptIfAvaiable($string, $key = null, $removeSalt = false): string
    {
        if (self::isEncryptAvailable()) {
            return self::decrypt($string, $key, $removeSalt);
        }
        return $string;
    }
}
