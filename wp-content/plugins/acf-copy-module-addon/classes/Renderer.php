<?php
namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Responsible for rendering or preparing module data for output.
 */
class Renderer
{
    /**
     * Converts module array data into readable HTML or JSON for viewing in admin modal.
     *
     * @param array $module
     * @return string
     */
    public function render(array $module): string
    {
        if (empty($module['data'])) {
            return '<p><em>' . esc_html__('No data in this module.', 'acf-module-library') . '</em></p>';
        }

        $json = json_encode($module['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $html = '<pre class="atl-modal-json">' . esc_html($json) . '</pre>';

        return '<div class="atl-modal-inner">'
             . '<h2>' . esc_html($module['name']) . '</h2>'
             . $html
             . '</div>';
    }

    /**
     * Converts all modules to exportable JSON.
     *
     * @param array $modules
     * @return string
     */
    public function render_export(array $modules): string
    {
        return json_encode($modules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Prints a small message in admin debug logs.
     */
    public function debug_log(string $message, $data = null): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[ACFML] ' . $message . ' ' . print_r($data, true));
        }
    }
}
