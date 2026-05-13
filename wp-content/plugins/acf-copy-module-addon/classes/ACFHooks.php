<?php
namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles ACF hooks — injecting buttons and custom UI elements for Flexible Content fields.
 */
class ACFHooks
{
    public function __construct()
    {
        // Run after ACF fields are rendered
        add_action('acf/render_field_settings', [$this, 'inject_field_controls'], 999);
    }

    /**
     * Inject “Insert Module” button and per-section Save Module buttons.
     * Only for Flexible Content fields.
     */
    public function inject_field_controls($field)
    {
        if ($field['type'] !== 'flexible_content') {
            return;
        }

        // Output the HTML control wrapper (top Insert button)
        echo '<div class="atl-controls" data-acf-field-key="' . esc_attr($field['key']) . '" data-acf-group-key="' . esc_attr($field['parent']) . '" data-type="section">';
        echo '  <div class="atl-actions">';
        echo '      <button type="button" class="button atl-insert-module">' . esc_html__('Insert Module', 'acf-module-library') . '</button>';
        echo '  </div>';
        echo '</div>';

        // Per-section Save button is now handled dynamically via JS (admin.js)
    }
}
