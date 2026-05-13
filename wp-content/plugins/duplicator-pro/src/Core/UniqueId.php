<?php

/**
 * Website identifier manager
 *
 * This class manages a unique identifier for the website that is used for:
 * - Duplicator Cloud authentication and connection
 * - Usage statistics tracking
 * - Website identification across migrations and restorations
 *
 * The identifier is a 44-character random string that remains persistent
 * across WordPress updates but can be carried over during site migrations.
 *
 * @package   Duplicator
 * @copyright (c) 2025, Snap Creek LLC
 */

declare(strict_types=1);

namespace Duplicator\Core;

/**
 * Website Identifier Manager
 *
 * Manages the unique identifier for this WordPress installation.
 */
final class UniqueId
{
    /**
     * WordPress option key for storing the website identifier
     */
    const OPTION_KEY = 'dupli_opt_unique_id';

    /**
     * Characters allowed in the identifier
     */
    const IDENTIFIER_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_-.,;=+&';

    /**
     * Length of the generated identifier
     */
    const IDENTIFIER_LENGTH = 44;

    /**
     * Singleton instance
     *
     * @var ?self
     */
    private static $instance = null;

    /**
     * The website identifier
     *
     * @var string
     */
    private $identifier = '';

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Class constructor
     *
     * Loads or generates the website identifier.
     * If the identifier doesn't exist in the database, a new one is generated.
     * Migration from old location is handled by UpgradeFunctions::migrateWebsiteIdentifier()
     */
    private function __construct()
    {
        $identifier = get_option(self::OPTION_KEY, false);

        if ($identifier !== false && strlen($identifier) > 0) {
            $this->identifier = $identifier;
        } else {
            $this->identifier = self::generateIdentifier();
            $this->save();
        }
    }

    /**
     * Get the website identifier
     *
     * @return string The unique website identifier
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Get a short hex hash derived from the website identifier
     *
     * NOTE: This is NOT a unique identifier. Collisions are rare but possible,
     * especially with shorter lengths. Do not use where absolute uniqueness is
     * required (e.g. authentication, deduplication). Suitable for best-effort
     * disambiguation like lock names or file suffixes.
     *
     * @param int $length Number of hex characters (1-32)
     *
     * @return string Hex hash of the requested length
     */
    public function getShortId(int $length = 8): string
    {
        return substr(md5($this->identifier), 0, max(1, min($length, 32)));
    }

    /**
     * Update the website identifier from migration data
     *
     * This method is called during site migration/restoration to update
     * the identifier from the source site.
     *
     * @param string $identifier The identifier from the source site
     *
     * @return bool True if the identifier was updated, false otherwise
     */
    public function updateFromMigration(string $identifier): bool
    {
        if (strlen($identifier) === 0) {
            return false;
        }

        if ($identifier === $this->identifier) {
            return true;
        }

        $this->identifier = $identifier;
        return $this->save();
    }

    /**
     * Save the identifier to WordPress options
     *
     * @return bool True if saved successfully, false otherwise
     */
    private function save(): bool
    {
        return update_option(self::OPTION_KEY, $this->identifier, true);
    }

    /**
     * Generate a new random identifier
     *
     * Creates a 44-character random string using the allowed character set.
     *
     * @return string The generated identifier
     */
    protected static function generateIdentifier(): string
    {
        $maxRand = strlen(self::IDENTIFIER_CHARS) - 1;
        $result  = '';

        for ($i = 0; $i < self::IDENTIFIER_LENGTH; $i++) {
            $result .= substr(self::IDENTIFIER_CHARS, wp_rand(0, $maxRand), 1);
        }

        return $result;
    }
}
