<?php

namespace Duplicator\Core;

use Duplicator\Utils\Logging\DupLog;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Libs\Snap\SnapLog;
use Exception;
use Throwable;

/**
 * Duplicator Capabilites
 */
class CapMng
{
    const OPTION_KEY         = 'dupli_opt_capabilities';
    const CAP_PREFIX         = 'duplicator_';
    const CAP_BASIC          = self::CAP_PREFIX . 'basic';
    const CAP_CREATE         = self::CAP_PREFIX . 'create';
    const CAP_SCHEDULE       = self::CAP_PREFIX . 'schedule';
    const CAP_STORAGE        = self::CAP_PREFIX . 'storage';
    const CAP_IMPORT         = self::CAP_PREFIX . 'import';
    const CAP_EXPORT         = self::CAP_PREFIX . 'export';
    const CAP_BACKUP_RESTORE = self::CAP_PREFIX . 'backup_restore';
    const CAP_STAGING        = self::CAP_PREFIX . 'staging';
    const CAP_SETTINGS       = self::CAP_PREFIX . 'settings';
    const CAP_LICENSE        = self::CAP_PREFIX . 'license';

    const ROLE_SUPERADMIN = 'dup_role_superadmin';

    /** @var ?self */
    private static $instance;

    /** @var array<string, array{roles: string[], users: int[]}> */
    private $capabilities = [];
    /** @var bool if false skip license check on capabilities update */
    private $updateLicenseCheck = true;

    /**
     *
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Class contructor
     */
    private function __construct()
    {
        if (($cap = get_option(self::OPTION_KEY)) == false) {
            $this->reset();
        } else {
            $this->capabilities = $cap;
            $this->normalizeCapabilities();
        }
    }

    /**
     * Ensure all required capability keys exist with valid structure
     * and remove any unknown capabilities
     *
     * @return void
     */
    private function normalizeCapabilities(): void
    {
        $defaultCaps = self::getDefaultCaps();
        $updated     = false;

        // Remove unknown capabilities
        foreach (array_keys($this->capabilities) as $cap) {
            if (!isset($defaultCaps[$cap])) {
                unset($this->capabilities[$cap]);
                $updated = true;
            }
        }

        // Add missing or fix invalid capabilities
        foreach ($defaultCaps as $cap => $default) {
            if (
                !isset($this->capabilities[$cap]) ||
                !is_array($this->capabilities[$cap]['roles'] ?? null) ||
                !is_array($this->capabilities[$cap]['users'] ?? null)
            ) {
                $this->capabilities[$cap] = $default;
                $updated                  = true;
            }
        }

        if ($updated) {
            // Check if WordPress user functions are available
            // During early plugin loading (e.g., updates), get_user_by may not exist yet
            if (!function_exists('get_user_by')) {
                // Defer save to when WordPress is fully loaded
                add_action('init', [$this, 'deferredSave'], 1);
            } else {
                $this->save();
            }
        }
    }

    /**
     * Deferred save for when WordPress functions weren't available during initialization
     *
     * @return void
     */
    public function deferredSave(): void
    {
        $this->save();
    }

    /**
     * Save capabilities
     *
     * @return bool true if success false otherwise
     */
    private function save(): bool
    {
        $roles         = wp_roles();
        $origUseDb     = $roles->use_db;
        $roles->use_db = false;
        $updateRoles   = false;

        foreach ($this->capabilities as $cap => $data) {
            foreach ($data['roles'] as $role) {
                $updateRoles = true;
                $roles->add_cap($role, $cap);
            }
            foreach ($data['users'] as $user) {
                $user = get_user_by('id', $user);
                if ($user) {
                    $user->add_cap($cap);
                }
            }
        }
        $roles->use_db = $origUseDb;

        // This logic was created to perform one and only one role saving
        if ($updateRoles) {
            update_option($roles->role_key, $roles->roles);
        }
        update_option(self::OPTION_KEY, $this->capabilities);
        return true;
    }

    /**
     * Update capabilities, Only the capabilities in the list are overwritten
     *
     * @param array<string, array{roles: string[], users: int[]}> $capabilities capabilities
     *
     * @return bool true if success false otherwise
     */
    public function update($capabilities): bool
    {
        // user can must be check before capabitilies update
        $userCanLicense  = self::realCanCheck(self::CAP_LICENSE, false);
        $userCanSettings = self::realCanCheck(self::CAP_SETTINGS, false);

        $this->removeAll();

        if ($this->updateLicenseCheck && !License::can(License::CAPABILITY_CAPABILITIES_MNG)) {
            $capabilities = self::getDefaultCaps();
        }

        foreach ($capabilities as $cap => $data) {
            if (!isset($this->capabilities[$cap])) {
                continue;
            }

            if ($cap == self::CAP_LICENSE && !$userCanLicense) {
                // Don't edit license cap if user can't edit it
                continue;
            }

            $this->capabilities[$cap] = [
                'roles' => [],
                'users' => [],
            ];

            $selectableRoles = array_keys(self::getSelectableRoles());
            foreach ($data['roles'] as $role) {
                if (!in_array($role, $selectableRoles)) {
                    continue;
                }
                $this->capabilities[$cap]['roles'][] = $role;
            }

            if ($this->updateLicenseCheck == false || License::can(License::CAPABILITY_CAPABILITIES_MNG_PLUS)) {
                foreach ($data['users'] as $user) {
                    $user = get_user_by('id', $user);
                    if ($user) {
                        $this->capabilities[$cap]['users'][] = $user->ID;
                    }
                }
            } else {
                $this->capabilities[$cap]['users'] = [];
            }
        }

        $this->addCapatibiliesDependecies();

        if ($userCanLicense) {
            $this->capabilities[self::CAP_LICENSE] = $this->addCurrentUserRole($this->capabilities[self::CAP_LICENSE]);
        }

        if ($userCanSettings) {
            $this->capabilities[self::CAP_SETTINGS] = $this->addCurrentUserRole($this->capabilities[self::CAP_SETTINGS]);
        }

        // The second time to be sure to add the user roles
        $this->addCapatibiliesDependecies();

        return $this->save();
    }

    /**
     * Update capabitibiles after migration
     *
     * @return bool true if success false otherwise
     */
    public function migrationUpdate()
    {
        $update = false;
        if (!is_multisite()) {
            foreach ($this->capabilities as $cap => $data) {
                if (in_array(self::ROLE_SUPERADMIN, $data['roles'])) {
                    $newRoles   = array_values(array_diff($data['roles'], [self::ROLE_SUPERADMIN]));
                    $newRoles[] = 'administrator';

                    $this->capabilities[$cap]['roles'] = $newRoles;
                    $update                            = true;
                }
            }
        }

        if ($update) {
            $this->updateLicenseCheck = false;

            $result = $this->update($this->capabilities);

            $this->updateLicenseCheck = true;
            return $result;
        } else {
            return true;
        }
    }

    /**
     * Add capatbilies dependecies follow parents
     *
     * @return void
     */
    protected function addCapatibiliesDependecies()
    {
        $cInfo = self::getCapsInfo();

        foreach ($cInfo as $cap => $capInfo) {
            $roles     = $this->capabilities[$cap]['roles'];
            $users     = $this->capabilities[$cap]['users'];
            $parentCap = $capInfo['parent'];
            while ($parentCap != '') {
                $this->capabilities[$parentCap]['roles'] = array_values(array_unique(array_merge($this->capabilities[$parentCap]['roles'], $roles)));
                $this->capabilities[$parentCap]['users'] = array_values(array_unique(array_merge($this->capabilities[$parentCap]['users'], $users)));
                $parentCap = $cInfo[$parentCap]['parent'];
            }
        }
    }

    /**
     * Returns capabilities with the current user permissions to prevent the user from blocking himself by mistake.
     *
     * @param array{roles: string[], users: int[]} $roles roles or users
     *
     * @return array{roles: string[], users: int[]}
     */
    protected function addCurrentUserRole($roles)
    {
        $user = wp_get_current_user();
        if (is_multisite() && is_super_admin() && in_array(self::ROLE_SUPERADMIN, $roles['roles'])) {
            return $roles;
        }

        if (count(array_intersect($roles['roles'], (array) $user->roles)) > 0) {
            return $roles;
        }

        if (in_array($user->ID, $roles['users'])) {
            return $roles;
        }

        if (License::can(License::CAPABILITY_CAPABILITIES_MNG_PLUS)) {
            $roles['users'][] = $user->ID;
        } else {
            $roles['roles'] = array_merge($roles['roles'], (array) $user->roles);
            if (is_multisite() && is_super_admin()) {
                $roles['roles'][] = self::ROLE_SUPERADMIN;
            };
            $roles['roles'] = array_values(array_unique($roles['roles']));
        }

        return $roles;
    }

    /**
     * Get capability roles
     *
     * @param string $cap capability
     *
     * @return string[]
     */
    public function getCapRoles($cap)
    {
        if (!isset($this->capabilities[$cap])) {
            return [];
        }
        return $this->capabilities[$cap]['roles'];
    }

    /**
     * Get capability users
     *
     * @param string $cap capability
     *
     * @return int[]
     */
    public function getCapUsers($cap)
    {
        if (!isset($this->capabilities[$cap])) {
            return [];
        }
        return $this->capabilities[$cap]['users'];
    }

    /**
     * Get all current capabilities
     *
     * @return array<string, array{roles: string[], users: int[]}>
     */
    public function getAllCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Check if capabilities are the default ones
     *
     * @return bool true if default false otherwise
     */
    public function isDefault(): bool
    {
        return $this->capabilities == self::getDefaultCaps();
    }

    /**
     * Check if cababilitise have users capabilities set
     *
     * @return bool true if users capabilities are set false otherwise
     */
    public function hasUsersCapabilities(): bool
    {
        foreach ($this->capabilities as $cap => $data) {
            if (count($data['users']) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Reset default capabilities
     *
     * @return void
     */
    public function reset(): void
    {
        $this->removeAll();
        $this->capabilities = self::getDefaultCaps();
        $this->save();
    }

    /**
     * Remoe all capabilities
     *
     * @return bool true on success false otherwise
     */
    private function removeAll()
    {
        $roles     = wp_roles();
        $origUseDb = $roles->use_db;

        try {
            $roles->use_db = false;

            foreach ($this->capabilities as $cap => $data) {
                foreach ($data['roles'] as $role) {
                    $wpRole = get_role($role);
                    if ($wpRole) {
                        $wpRole->remove_cap($cap);
                    }
                }
                foreach ($data['users'] as $user) {
                    $user = get_user_by('id', $user);
                    if ($user) {
                        $user->remove_cap($cap);
                    }
                }
            }

            update_option($roles->role_key, $roles->roles);

            return delete_option(self::OPTION_KEY);
        } catch (Throwable $e) {
            DupLog::trace('Capabilities removeAll failed: ' . $e->getMessage());
            return false;
        } finally {
            $roles->use_db = $origUseDb;
        }
    }

    /**
     * Capabilities hard reset, check all users and roles and remove all capabilities
     *
     * @return bool
     */
    public function hardReset()
    {
        $roles     = null;
        $origUseDb = null;

        try {
            $ids     = get_users(['fields' => 'ID']);
            $capList = self::getCapsList();

            foreach ($ids as $id) {
                $user = get_user_by('id', $id);
                if (!$user) {
                    continue;
                }
                foreach ($capList as $cap) {
                    $user->remove_cap($cap);
                }
            }

            $roles         = wp_roles();
            $origUseDb     = $roles->use_db;
            $roles->use_db = false;

            foreach (get_editable_roles() as $roleName => $info) {
                $role = get_role($roleName);
                if ($role === null) {
                    continue;
                }
                foreach ($capList as $cap) {
                    $role->remove_cap($cap);
                }
            }

            update_option($roles->role_key, $roles->roles);

            delete_option(self::OPTION_KEY);
            $this->capabilities = self::getDefaultCaps();
            return $this->save();
        } catch (Throwable $e) {
            DupLog::trace('Capabilites hard reset failed: ' . $e->getMessage());
        } finally {
            if ($roles !== null) {
                $roles->use_db = $origUseDb;
            }
        }

        return false;
    }

    /**
     * Check if current user have the capability
     *
     * @param string $cap  capability
     * @param bool   $thow throw exception if the user don't have the capability
     *
     * @return bool return true if the user have the capability or throw an exception
     */
    protected static function realCanCheck($cap, $thow = true): bool
    {
        /**
         * @var string[] $super_admins (array) An array of user IDs that should be granted super admin privileges (multisite).
         *                              This global is only set by the site owner (e.g., in wp-config.php),
         *                              and contains an array of IDs of users who should have super admin privileges.
         *                              If set it will override the list of super admins in the database.
         * @see https://codex.wordpress.org/Global_Variables
         */
        global $super_admins;
        $originalSuperAdmins = $super_admins;
        $restoreSuperAdmins  = false;

        try {
            $user = wp_get_current_user();

            if (strpos($cap, self::CAP_PREFIX) === 0 && is_multisite()) {
                if (!is_super_admin()) {
                    throw new Exception('User is not super admin');
                }

                $capRoles = self::getInstance()->capabilities[$cap]['roles'] ?? [];
                if (!in_array(self::ROLE_SUPERADMIN, $capRoles)) {
                    // The default super_admin users have all the capabilities so
                    // it temporarily removes the current user from the super admins to do the check
                    $tempSuperAdmins = get_super_admins();
                    if (($key = array_search($user->user_login, $tempSuperAdmins)) !== false) {
                        unset($tempSuperAdmins[$key]);
                        $super_admins       = array_values($tempSuperAdmins);
                        $restoreSuperAdmins = true;
                    }
                }
            }

            if (!$user->has_cap($cap)) {
                throw new Exception('User don\'t have the capability');
            }

            // $capEnabled Filter to dynamically disable a capability
            if (!apply_filters('duplicator_cap_enabled', true, $cap)) {
                throw new Exception('Capability disabled by filter');
            }
        } catch (Exception $e) {
            if ($thow) {
                DupLog::trace('SECUTIRY ISSUE: USER ID ' . get_current_user_id() . ' cap: ' . $cap);
                DupLog::trace(SnapLog::getTextException($e));
                throw new Exception('Security issue.');
            } else {
                return false;
            }
        } finally {
            if ($restoreSuperAdmins) {
                $super_admins = $originalSuperAdmins;
            }
        }

        return true;
    }

    /**
     * Check if current user have the capability
     *
     * @param string $cap  capability enum self::CAP_*
     * @param bool   $thow throw exception if the user don't have the capability
     *
     * @return bool return true if the user have the capability or throw an exception
     */
    public static function can($cap, $thow = true)
    {
        $info        = self::getCapsInfo();
        $currentInfo = ($info[$cap] ?? null);
        while ($currentInfo !== null) {
            if (constant($currentInfo['disConstName']) === true) {
                if ($thow) {
                    DupLog::trace('SECUTIRY ISSUE HARDCODED cap: ' . $cap);
                    throw new Exception('Security issue.');
                } else {
                    return false;
                }
            }
            $currentInfo = ($info[$currentInfo['parent']] ?? null);
        }

        return self::realCanCheck($cap, $thow);
    }

    /**
     * Get selectable roles
     *
     * @return array<string, string>
     */
    public static function getSelectableRoles()
    {
        if (is_multisite()) {
            return [self::ROLE_SUPERADMIN => 'Super Admin'];
        } else {
            $result = [];
            foreach (get_editable_roles() as $role => $roleInfo) {
                $result[$role] = $roleInfo['name'];
            }
            return $result;
        }
    }

    /**
     * Get capabilities list
     *
     * @return string[]
     */
    public static function getCapsList()
    {
        static $list = null;
        if (is_null($list)) {
            $list = array_keys(self::getDefaultCaps());
        }
        return $list;
    }

    /**
     * Get default capabilities
     *
     * @return array<string, array{roles: string[], users: int[]}>
     */
    public static function getDefaultCaps(): array
    {
        $defRoles = (is_multisite() ? [self::ROLE_SUPERADMIN] : ['administrator']);

        return [
            self::CAP_BASIC          => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_CREATE         => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_SCHEDULE       => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_STORAGE        => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_BACKUP_RESTORE => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_IMPORT         => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_STAGING        => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_EXPORT         => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_SETTINGS       => [
                'roles' => $defRoles,
                'users' => [],
            ],
            self::CAP_LICENSE        => [
                'roles' => $defRoles,
                'users' => [],
            ],
        ];
    }

    /**
     * Get capabilities info
     *
     * @return array<string,array{parent:string,label:string,desc:string,disConstName:string}>
     */
    public static function getCapsInfo(): array
    {
        return [
            self::CAP_BASIC          => [
                'parent'       => '',
                'label'        => __('Backup Read', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to read the list of Backups and their characteristics.',
                    'duplicator-pro'
                ) . ' ' . __(
                    'Without this capability, Duplicator is not visible. This is the basis of all the other capabilities listed below.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_BASIC',
            ],
            self::CAP_CREATE         => [
                'parent'       => self::CAP_BASIC,
                'label'        => __('Backup Create', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to create and delete Backups.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_CREATE',
            ],
            self::CAP_SCHEDULE       => [
                'parent'       => self::CAP_CREATE,
                'label'        => __('Manage Schedules', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to manage Backup Schedules.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_SCHEDULE',
            ],
            self::CAP_STORAGE        => [
                'parent'       => self::CAP_CREATE,
                'label'        => __('Manage Storage', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to create and modify storage. Those with the "Backup Create" capability can select existing storage but cannot edit it',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_STORAGE',
            ],
            self::CAP_BACKUP_RESTORE => [
                'parent'       => self::CAP_BASIC,
                'label'        => __('Restore Backup', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to set up and execute a recovery point',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_BACKUP_RESTORE',
            ],
            self::CAP_IMPORT         => [
                'parent'       => self::CAP_BACKUP_RESTORE,
                'label'        => __('Backup Import', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to import a Backup and overwrite the current site.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_IMPORT',
            ],
            self::CAP_STAGING        => [
                'parent'       => self::CAP_IMPORT,
                'label'        => __('Staging Sites', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to create and manage staging sites.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_STAGING',
            ],
            self::CAP_EXPORT         => [
                'parent'       => self::CAP_BASIC,
                'label'        => __('Backup Export', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to download existing Backup.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_EXPORT',
            ],
            self::CAP_SETTINGS       => [
                'parent'       => self::CAP_BASIC,
                'label'        => __('Manage Settings', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to change settings.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_SETTINGS',
            ],
            self::CAP_LICENSE        => [
                'parent'       => self::CAP_SETTINGS,
                'label'        => __('License Settings', 'duplicator-pro'),
                'desc'         => __(
                    'The capability to change the license settings.',
                    'duplicator-pro'
                ),
                'disConstName' => 'DUPLICATOR_DISABLE_CAP_LICENSE',
            ],
        ];
    }
}
