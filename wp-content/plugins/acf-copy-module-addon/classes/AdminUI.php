<?php

namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin interface for viewing, managing, and exporting/importing ACF Modules.
 */
class AdminUI
{
    protected $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;

        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_acfml_delete_module', [$this, 'handle_delete']);

        // Bulk API
        add_action('wp_ajax_acfml_bulk_delete', [$this, 'handle_bulk_delete']);
        add_action('wp_ajax_acfml_export_selected', [$this, 'handle_export_selected']);

        // Import API
        add_action('wp_ajax_acfml_import', [$this, 'handle_import']);

        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    /** -----------------------------------------
     * Add submenu under ACF
     ------------------------------------------*/
    public function register_menu()
    {
        add_submenu_page(
            'edit.php?post_type=acf-field-group',
            __('Modules', 'acf-module-library'),
            __('Modules', 'acf-module-library'),
            'edit_posts',
            'acfml-modules',
            [$this, 'render_page']
        );
    }

    /** -----------------------------------------
     * Admin CSS + JS
     ------------------------------------------*/
    public function enqueue_admin_assets()
    {
        $css = "
        .acfml-modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,.45);
            z-index: 99999; display: flex;
            align-items: center; justify-content: center;
        }
        .acfml-modal {
            background: #fff; border-radius: 8px; padding: 20px;
            max-width: 900px; width: 90%; max-height: 80vh;
            overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.25);
            animation: fadeIn .25s ease-in-out;
        }
        .acfml-modal h2 { margin-top: 0; }
        .acfml-actions-top {
            margin: 15px 0; padding: 10px;
            background: #f0f0f0; border: 1px solid #ccc;
        }
        table.acfml-table td input[type=checkbox] {
            transform: scale(1.3);
        }
        @keyframes fadeIn { from {opacity: 0;} to {opacity: 1;} }
        ";

        wp_register_style('acfml-admin-inline', false);
        wp_enqueue_style('acfml-admin-inline');
        wp_add_inline_style('acfml-admin-inline', $css);

        /** JS */
        $js = "
        jQuery(document).ready(function($){

            // Select/Deselect All
            $('#acfml-select-all').on('change', function(){
                $('.acfml-row-select').prop('checked', this.checked);
            });

            // Bulk Delete
            $('#acfml-bulk-delete').on('click', function(){
                let ids = [];
                $('.acfml-row-select:checked').each(function(){
                    ids.push($(this).data('id'));
                });

                if (!ids.length) return alert('No modules selected.');
                if (!confirm('Delete selected modules?')) return;

                $.post(ajaxurl, {
                    action: 'acfml_bulk_delete',
                    ids: JSON.stringify(ids),
                    nonce: '" . wp_create_nonce('acfml_admin_ops') . "'
                }, function(){
                    location.reload();
                });
            });

            // Export Selected
            $('#acfml-export-selected').on('click', function(){
                let ids = [];
                $('.acfml-row-select:checked').each(function(){
                    ids.push($(this).data('id'));
                });

                if (!ids.length) return alert('No modules selected.');

                window.location = ajaxurl + '?action=acfml_export_selected&ids=' + JSON.stringify(ids);
            });

            // Import JSON
            $('#acfml-import-btn').on('click', function(){
                let file = $('#acfml-import-file')[0].files[0];
                if (!file) return alert('Select a JSON file first.');

                let form = new FormData();
                form.append('file', file);
                form.append('action', 'acfml_import');
                form.append('nonce', '" . wp_create_nonce('acfml_admin_ops') . "');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: form,
                    processData: false,
                    contentType: false,
                    success: function(){
                        alert('Import Complete');
                        location.reload();
                    }
                });
            });

            // View popup
            $('body').on('click', '.view-module', function(e){
                e.preventDefault();
                var slug = $(this).data('slug');
                var url  = wpApiSettings.root + 'atl/v1/modules/' + slug;

                $('.acfml-modal-overlay').remove();
                var loader = $('<div class=\"acfml-modal-overlay\"><div class=\"acfml-modal\"><h2>Loading…</h2></div></div>');
                $('body').append(loader);

                $.ajax({
                    url: url,
                    method: 'GET',
                    beforeSend: function(xhr){
                        xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
                    },
                    success: function(res){
                        var html = '<div class=\"acfml-modal-overlay\">';
                        html += '<div class=\"acfml-modal\">';
                        html += '<button class=\"button\" style=\"float:right;\" onclick=\"document.querySelector(\\'.acfml-modal-overlay\\').remove()\">×</button>';
                        html += '<h2>' + res.name + '</h2>';
                        html += '<pre>' + JSON.stringify(res.data, null, 4) + '</pre>';
                        html += '</div></div>';

                        $('.acfml-modal-overlay').remove();
                        $('body').append(html);
                    },
                    error: function(){
                        $('.acfml-modal-overlay').remove();
                        alert('Failed to load module.');
                    }
                });
            });

        });
        ";

        wp_add_inline_script('jquery-core', $js);

        wp_localize_script('jquery-core', 'wpApiSettings', [
            'root'  => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ]);
    }

    /** -----------------------------------------
     * Render Admin Page
     ------------------------------------------*/
    public function render_page()
    {
        $modules = $this->repo->get_all();

        echo '<div class="wrap"><h1>Saved Modules</h1>';

        echo '<div class="acfml-actions-top">
                <button id="acfml-bulk-delete" class="button button-danger">Bulk Delete</button>
                <button id="acfml-export-selected" class="button button-primary">Export Selected</button>
                <input type="file" id="acfml-import-file" accept=\"application/json\"/>
                <button id="acfml-import-btn" class="button">Import</button>
              </div>';

        if (empty($modules)) {
            echo '<p><em>No modules saved yet.</em></p></div>';
            return;
        }

        echo '<table class="widefat striped acfml-table"><thead>
            <tr>
                <th><input type="checkbox" id="acfml-select-all"></th>
                <th>Name</th>
                <th>Slug</th>
                <th>Post Type</th>
                <th>Type</th>
                <th>Saved Date</th>
                <th>Saved By</th>
                <th>Actions</th>
            </tr>
        </thead><tbody>';

        foreach ($modules as $m) {

            if (!is_array($m) || !isset($m['id'])) continue;

            $id   = intval($m['id']);
            $name = $m['name'] ?? '(no name)';
            $slug = $m['slug'] ?? '(no-slug)';
            $type = $m['type'] ?? 'section';

            $saved_at = $m['saved_at'] ?? '-';
            $saved_by = $m['saved_by']['name'] ?? 'Unknown';

            $del_link = wp_nonce_url(
                admin_url('admin-post.php?action=acfml_delete_module&id=' . $id),
                'acfml_delete_module'
            );

            echo '<tr>';
            echo '<td><input type="checkbox" class="acfml-row-select" data-id="' . $id . '"></td>';
            echo '<td>' . esc_html($name) . '</td>';
            echo '<td><code>' . esc_html($slug) . '</code></td>';
            echo '<td><code>' . esc_html($m['post_type'] ?? '—') . '</code></td>';
            echo '<td>' . esc_html($type) . '</td>';
            echo '<td>' . esc_html($saved_at) . '</td>';
            echo '<td>' . esc_html($saved_by) . '</td>';
            echo '<td>';
            echo '<a href="#" class="button view-module" data-slug="' . esc_attr($slug) . '">View</a> ';
            echo '<a href="' . esc_url($del_link) . '" class="button button-secondary">Delete</a>';
            echo '</td></tr>';
        }

        echo '</tbody></table></div>';
    }

    /** -----------------------------------------
     * Single Delete
     ------------------------------------------*/
    public function handle_delete()
    {
        if (!isset($_GET['id']) || !wp_verify_nonce($_GET['_wpnonce'] ?? '', 'acfml_delete_module')) {
            wp_die('Invalid request.');
        }

        $this->repo->delete(absint($_GET['id']));
        wp_redirect(admin_url('edit.php?post_type=acf-field-group&page=acfml-modules'));
        exit;
    }

    /** -----------------------------------------
     * Bulk Delete
     ------------------------------------------*/
    public function handle_bulk_delete()
    {
        check_ajax_referer('acfml_admin_ops', 'nonce');

        $ids = json_decode(stripslashes($_POST['ids']), true);
        foreach ($ids as $id) {
            $this->repo->delete($id);
        }

        wp_send_json(['ok' => true]);
    }

    /** -----------------------------------------
     * Export selected items
     ------------------------------------------*/
    public function handle_export_selected()
    {
        if (!isset($_GET['ids'])) {
            wp_die('No modules selected.');
        }

        $ids = json_decode(stripslashes($_GET['ids']), true);

        if (!is_array($ids) || empty($ids)) {
            wp_die('Invalid module selection.');
        }

        $result = [];
        $firstSlug = null;

        foreach ($ids as $id) {
            $m = $this->repo->get_by_id($id);
            if ($m) {
                if (!$firstSlug) {
                    $firstSlug = sanitize_title($m['slug']);
                }
                $result[] = $m;
            }
        }

        if (empty($result)) {
            wp_die('No modules found to export.');
        }

        // ---- Build Dynamic Filename ----
        if (count($result) === 1) {
            // Export single module: acfml-slider-with-content.json
            $filename = "acfml-{$firstSlug}.json";
        } else {
            // Export multiple modules: acfml-export-YYYY-MM-DD-HH-MM.json
            $timestamp = current_time('timestamp');
            $filename = "acfml-export-" . date('Y-m-d-H-i', $timestamp) . ".json";
        }

        // ---- Clean Headers ----
        nocache_headers();
        header("Content-Type: application/json; charset=utf-8");
        header("Content-Disposition: attachment; filename={$filename}");
        header("Expires: 0");

        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        exit;
    }



    /** -----------------------------------------
     * Import JSON (merge)
     ------------------------------------------*/
    public function handle_import()
    {
        check_ajax_referer('acfml_admin_ops', 'nonce');

        if (empty($_FILES['file']['tmp_name'])) {
            wp_send_json(['error' => 'No file'], 400);
        }

        $json = file_get_contents($_FILES['file']['tmp_name']);
        $items = json_decode($json, true);

        if (!is_array($items)) {
            wp_send_json(['error' => 'Invalid JSON'], 400);
        }

        foreach ($items as $module) {
            $this->repo->save($module);
        }

        wp_send_json(['ok' => true]);
    }
}
