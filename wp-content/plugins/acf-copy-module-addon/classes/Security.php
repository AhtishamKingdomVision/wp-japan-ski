<?php
namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles basic security helpers such as nonce verification and capability checks.
 */
class Security
{
    public function __construct()
    {
        add_action('init', [$this, 'register_rest_nonce']);
    }

    /**
     * Generates and exposes REST nonce for AJAX and admin usage.
     */
    public function register_rest_nonce(): void
    {
        if (!is_admin()) {
            return;
        }

        // Allow JS access to nonce (ATL.nonce)
        add_action('admin_enqueue_scripts', function () {
            wp_localize_script('acfml-admin', 'ACFMLSecurity', [
                'nonce' => wp_create_nonce('wp_rest'),
            ]);
        });
    }

    /**
     * Validates a REST request nonce.
     *
     * @param string|null $nonce
     * @return bool
     */
    public function verify_nonce(?string $nonce): bool
    {
        return (bool) wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Ensures user has management permission.
     *
     * @return bool
     */
    public function user_can_manage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * Verifies REST permissions directly.
     */
    public function check_rest_permissions(): bool
    {
        return $this->user_can_manage();
    }
}
