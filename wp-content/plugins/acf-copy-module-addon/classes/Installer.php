<?php
namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles initial plugin setup, activation, and version management.
 */
class Installer
{
    protected string $version_key = 'acfml_version';
    protected string $current_version = '2.0.0';

    public function __construct()
    {
        register_activation_hook(ACFML_PATH . 'acf-module-library.php', [$this, 'on_activate']);
        add_action('plugins_loaded', [$this, 'check_version']);
    }

    /**
     * Run on plugin activation — ensures data storage exists.
     */
    public function on_activate(): void
    {
        if (get_option('acfml_modules') === false) {
            add_option('acfml_modules', []);
        }
        update_option($this->version_key, $this->current_version);
    }

    /**
     * Check plugin version and handle upgrades.
     */
    public function check_version(): void
    {
        $saved_version = get_option($this->version_key);
        if (version_compare($saved_version, $this->current_version, '<')) {
            $this->upgrade($saved_version);
        }
    }

    /**
     * Perform version upgrade tasks if needed.
     */
    protected function upgrade(?string $old_version): void
    {
        // Example: migrate old template data to new module naming
        if ($old_version && version_compare($old_version, '2.0.0', '<')) {
            $templates = get_option('atl_templates');
            if ($templates && is_array($templates)) {
                update_option('acfml_modules', $templates);
                delete_option('atl_templates');
            }
        }
        update_option($this->version_key, $this->current_version);
    }
}
