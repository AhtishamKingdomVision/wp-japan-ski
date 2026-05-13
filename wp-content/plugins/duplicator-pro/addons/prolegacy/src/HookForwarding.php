<?php

declare(strict_types=1);

namespace Duplicator\Addons\ProLegacy;

/**
 * Hook Forwarding Class
 *
 * Forwards old hook names (duplicator_pro_*) to new hook names (duplicator_*)
 * for backward compatibility with third-party code
 */
class HookForwarding
{
    /**
     * Version when old hooks were deprecated
     */
    private const DEPRECATION_VERSION = '4.5.25';

    /**
     * Registry of public hooks requiring backward compatibility
     * Maps new hook names to array with old hook name and accepted args count
     *
     * Only 4 public actions are documented and supported for backward compatibility.
     * All other hooks are internal use only.
     */
    private const HOOKS_REGISTRY = [
        'duplicator_build_before_start'        => [
            'old_hook'      => 'duplicator_pro_build_before_start',
            'accepted_args' => 1,
        ],
        'duplicator_build_completed'           => [
            'old_hook'      => 'duplicator_pro_build_completed',
            'accepted_args' => 1,
        ],
        'duplicator_build_fail'                => [
            'old_hook'      => 'duplicator_pro_build_fail',
            'accepted_args' => 1,
        ],
        'duplicator_first_login_after_install' => [
            'old_hook'      => 'duplicator_pro_first_login_after_install',
            'accepted_args' => 1,
        ],
    ];

    /**
     * Initialize hook forwarding system
     *
     * @return void
     */
    public static function init(): void
    {
        self::registerForwarding();
    }

    /**
     * Register hook forwarding for all registered hooks
     *
     * When new code fires do_action('duplicator_build_before_start', $package),
     * this callback triggers do_action_deprecated() for the old hook name,
     * which shows a deprecation warning in WP_DEBUG mode and executes callbacks.
     *
     * Priority 9999 ensures this runs last, after all other callbacks on the new hook.
     *
     * @return void
     */
    private static function registerForwarding(): void
    {
        foreach (self::HOOKS_REGISTRY as $newHook => $hookData) {
            add_action($newHook, function (...$args) use ($newHook, $hookData) {
                do_action_deprecated(
                    $hookData['old_hook'],
                    $args,
                    self::DEPRECATION_VERSION,
                    $newHook
                );
            }, 9999, $hookData['accepted_args']);
        }
    }
}
