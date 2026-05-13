<?php
/**
 * Plugin Name: ACF Copy Module Addon
 * Description: Extends Advanced Custom Fields (ACF) by allowing users to copy, save, export, import, and reuse individual Flexible Content layouts as reusable modules. This addon requires ACF and may need updates whenever the ACF plugin receives major updates.
 * Version: 2.0.1
 * Author: Kingdom Vision
 * Author URI: https://kingdom-vision.com
 * Plugin URI: https://kingdom-vision.com
 * Text Domain: acf-copy-module-addon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ACFML_PATH', plugin_dir_path(__FILE__));
define('ACFML_URL', plugin_dir_url(__FILE__));
define('ACFML_VER', '2.0.1');

require_once ACFML_PATH . 'classes/Installer.php';
require_once ACFML_PATH . 'classes/Security.php';
require_once ACFML_PATH . 'classes/Repository.php';
require_once ACFML_PATH . 'classes/Renderer.php';
require_once ACFML_PATH . 'classes/Rest.php';
require_once ACFML_PATH . 'classes/ACFHooks.php';
require_once ACFML_PATH . 'classes/AdminUI.php';

add_action('plugins_loaded', function () {
    new ACFML\Installer();
    new ACFML\Security();
    $repo = new ACFML\Repository();
    $rest = new ACFML\Rest($repo);
    $hooks = new ACFML\ACFHooks();
    new ACFML\AdminUI($repo, $rest, $hooks);
});

/**
 * Enqueue admin JS + CSS only on edit screens.
 */
add_action('acf/input/admin_enqueue_scripts', function () {
    wp_enqueue_script(
        'acfml-admin',
        ACFML_URL . 'assets/admin.js',
        ['jquery', 'acf-input'],
        ACFML_VER,
        true
    );
    wp_localize_script('acfml-admin', 'ATL', [
        'rest'  => esc_url_raw(rest_url('atl/v1/')),
        'nonce' => wp_create_nonce('wp_rest'),
        'i18n'  => [
            'saveModule'   => __('Save Module', 'acf-module-library'),
            'insertModule' => __('Insert Module', 'acf-module-library'),
            'notFound'     => __('Module not found', 'acf-module-library'),
            'insertTitle'  => __('Insert Module', 'acf-module-library'),
            'search'       => __('Search…', 'acf-module-library'),
        ],
    ]);
    wp_enqueue_style(
        'acfml-admin',
        ACFML_URL . 'assets/admin.css',
        [],
        ACFML_VER
    );
});
