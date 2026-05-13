<?php

namespace Duplicator\Utils;

use Duplicator\Core\UniqueId;
use Duplicator\Models\GlobalEntity;
use Duplicator\Utils\Logging\DupLog;

/**
 * Lock utility
 */
class LockUtil
{
    const LOCK_MODE_FILE = 0;
    const LOCK_MODE_SQL  = 1;

    /** @var false|resource */
    protected static $lockingFile = false;

    /**
     * Get unique site hash (8-char hex)
     *
     * Used for both SQL lock names and file lock paths to ensure
     * per-site uniqueness on shared servers.
     *
     * @return string 8-character hex hash
     */
    protected static function getSiteHash(): string
    {
        static $hash = null;
        if ($hash === null) {
            $hash = UniqueId::getInstance()->getShortId();
        }

        return $hash;
    }

    /**
     * Get site-specific SQL lock name
     *
     * GET_LOCK() is server-wide in MySQL, so a hardcoded name would cause
     * different WordPress sites on the same MySQL server to block each other.
     *
     * @return string Lock name unique to this WordPress installation
     */
    protected static function getSqlLockName(): string
    {
        return 'dupli_lock_' . self::getSiteHash();
    }

    /**
     * Get lock file path with unique hash based on site identifier
     *
     * This ensures the lock file is in a writable directory (backup storage)
     * and uses a unique name per WordPress installation
     *
     * @return string Lock file path
     */
    protected static function getLockFilePath(): string
    {
        static $lockFilePath = null;

        if ($lockFilePath === null) {
            $lockFilePath = DUPLICATOR_SSDIR_PATH . '/building_lock_' . self::getSiteHash() . '.tmp';
        }

        return $lockFilePath;
    }

    /**
     * Return default lock type
     *
     * Strategy:
     * 1. Test if SQL lock works (preferred — reliable on all filesystems)
     * 2. If SQL works → use SQL
     * 3. If SQL fails → fallback to FILE lock
     *
     * @return int Enum lock type (LOCK_MODE_FILE or LOCK_MODE_SQL)
     */
    public static function getDefaultLockType(): int
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }

        // Use a separate test name so detection doesn't collide with a running backup's production lock
        $testName = 'dupli_test_' . self::getSiteHash();

        // Prefer SQL lock: works reliably regardless of filesystem (NFS, CIFS, etc.)
        if (self::getSqlLock($testName)) {
            $sqlWorks = self::checkSqlLock($testName);
            self::releaseSqlLock($testName);

            if ($sqlWorks) {
                $cached = self::LOCK_MODE_SQL;
                return $cached;
            }
        }

        // Fallback to file lock if SQL isn't available
        $cached = self::LOCK_MODE_FILE;
        return $cached;
    }

    /**
     * Return lock mode
     *
     * @return int Lock mode ENUM self::LOCK_MODE_FILE or self::LOCK_MODE_SQL
     */
    public static function getLockMode()
    {
        return GlobalEntity::getInstance()->lock_mode;
    }

    /**
     * Lock process
     *
     * @return bool true if lock acquired
     */
    public static function lockProcess()
    {
        if (self::getLockMode() == self::LOCK_MODE_SQL) {
            return self::getSqlLock();
        } else {
            return self::getFileLock();
        }
    }

    /**
     * Unlock process
     *
     * @return bool true if lock released
     */
    public static function unlockProcess()
    {
        if (self::getLockMode() == self::LOCK_MODE_SQL) {
            return self::releaseSqlLock();
        } else {
            return self::releaseFileLock();
        }
    }

    /**
     * Get file lock
     *
     * Attempts to acquire an exclusive file lock. If the file cannot be opened,
     * automatically switches to SQL lock mode permanently.
     *
     * @return bool True if file lock acquired
     */
    protected static function getFileLock()
    {
        $global = GlobalEntity::getInstance();
        if ($global->lock_mode == self::LOCK_MODE_SQL) {
            return false;
        }

        $lockFilePath = self::getLockFilePath();

        if (
            self::$lockingFile === false &&
            (self::$lockingFile = fopen($lockFilePath, 'c+')) === false
        ) {
            // Problem opening the locking file - auto switch to SQL lock mode
            $error = error_get_last();
            DupLog::trace(
                "Problem opening lock file at {$lockFilePath}: " .
                ($error['message'] ?? 'unknown error') .
                ", auto-switching to SQL lock mode"
            );
            $global->lock_mode = self::LOCK_MODE_SQL;
            $global->save();
            return false;
        }

        $acquired_lock = flock(self::$lockingFile, LOCK_EX | LOCK_NB);

        if (!$acquired_lock) {
            DupLog::trace("File lock denied: {$lockFilePath} (another process is running)");
        }

        return $acquired_lock;
    }

    /**
     * Release file lock
     *
     * @return bool True if file lock released
     */
    protected static function releaseFileLock()
    {
        if (self::$lockingFile === false) {
            return true;
        }

        $success = true;
        if (!flock(self::$lockingFile, LOCK_UN)) {
            DupLog::trace("File lock can't release");
            $success = false;
        }

        if (fclose(self::$lockingFile) === false) {
            DupLog::trace("Can't close file lock file");
        }

        self::$lockingFile = false;

        return $success;
    }

    /**
     * Acquire an SQL lock
     *
     * @see releaseSqlLock()
     *
     * @param string $lock_name The lock name. Empty string uses the site-specific name.
     *
     * @return bool Returns true if an SQL lock request was successful
     */
    protected static function getSqlLock(string $lock_name = ''): bool
    {
        global $wpdb;

        if ($lock_name === '') {
            $lock_name = self::getSqlLockName();
        }

        $query_string = $wpdb->prepare("SELECT GET_LOCK(%s, 0)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        if ($ret_val == 0) {
            return false;
        } elseif ($ret_val == null) {
            DupLog::trace("Error retrieving mysql lock {$lock_name}");
            return false;
        }

        return true;
    }

    /**
     * Return true if SQL lock is held
     *
     * @param string $lock_name The lock name. Empty string uses the site-specific name.
     *
     * @return bool
     */
    protected static function checkSqlLock(string $lock_name = ''): bool
    {
        global $wpdb;

        if ($lock_name === '') {
            $lock_name = self::getSqlLockName();
        }

        $query_string = $wpdb->prepare("SELECT IS_USED_LOCK(%s)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        return $ret_val > 0;
    }

    /**
     * Release the SQL lock
     *
     * @see getSqlLock()
     *
     * @param string $lock_name The lock name. Empty string uses the site-specific name.
     *
     * @return bool
     */
    protected static function releaseSqlLock(string $lock_name = ''): bool
    {
        global $wpdb;

        if ($lock_name === '') {
            $lock_name = self::getSqlLockName();
        }

        $query_string = $wpdb->prepare("SELECT RELEASE_LOCK(%s)", $lock_name);
        $ret_val      = $wpdb->get_var($query_string);

        if ($ret_val == 0) {
            DupLog::trace("Failed releasing sql lock {$lock_name} because it wasn't established by this thread");
            return false;
        } elseif ($ret_val == null) {
            DupLog::trace("Tried to release sql lock {$lock_name} but it didn't exist");
            return false;
        }

        return true;
    }
}
