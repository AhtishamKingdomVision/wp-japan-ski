<?php

namespace ACFML;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * REST API for ACF Module Library
 */
class Rest
{
    protected Repository $repo;

    public function __construct(Repository $repo)
    {
        $this->repo = $repo;
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Registers all /modules REST routes
     */
    public function register_routes(): void
    {
        register_rest_route('atl/v1', '/modules', [
            [
                'methods'             => 'GET',
                'callback'            => [$this, 'list_modules'],
                'permission_callback' => [$this, 'check_permission_view'],
            ],
            [
                'methods'             => 'POST',
                'callback'            => [$this, 'save_module'],
                'permission_callback' => [$this, 'check_permission_save'],
            ],
        ]);

        register_rest_route('atl/v1', '/modules/(?P<slug>[a-z0-9-_]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_module'],
            'permission_callback' => [$this, 'check_permission_view'],
        ]);

        register_rest_route('atl/v1', '/modules/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_module'],
            'permission_callback' => [$this, 'check_permission_delete'],
        ]);

        register_rest_route('atl/v1', '/export', [
            'methods'             => 'GET',
            'callback'            => [$this, 'export_modules'],
            'permission_callback' => [$this, 'check_permission_view'],
        ]);

        register_rest_route('atl/v1', '/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'import_modules'],
            'permission_callback' => [$this, 'check_permission_save'],
        ]);
    }

    /** 🛡️ View permission (basic) */
    public function check_permission_view(): bool
    {
        return current_user_can('edit_posts');
    }

    /** 🛡️ Save permission */
    public function check_permission_save(): bool
    {
        return current_user_can('edit_posts');
    }

    /** 🛡️ Delete permission */
    public function check_permission_delete(): bool
    {
        return current_user_can('delete_posts');
    }

    /** 📋 List all modules */
    public function list_modules(WP_REST_Request $req): WP_REST_Response
    {
        $type = sanitize_text_field($req->get_param('type') ?? '');
        $modules = $this->repo->get_all($type);
        return new WP_REST_Response($modules, 200);
    }

    /** 💾 Save a new module */
    public function save_module(WP_REST_Request $req): WP_REST_Response
    {
        // FIX: Properly parse JSON body
        $data = $req->get_json_params();

        if (empty($data)) {
            $data = json_decode($req->get_body(), true);
        }

        if (!$data || empty($data['name']) || empty($data['data'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid data'], 400);
        }

        // Add user + date info
        $user = wp_get_current_user();
        $date_format = get_option('date_format') . ' ' . get_option('time_format');
        $saved_time  = date_i18n($date_format, current_time('timestamp'));

        $data['saved'] = $saved_time;
        $data['saved_by'] = [
            'id'   => $user->ID,
            'name' => $user->display_name ?: $user->user_login,
        ];

        // Permission check
        if (!current_user_can('edit_posts')) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Permission denied'], 403);
        }

        // SAVE
        $id = $this->repo->save($data);

        return new WP_REST_Response([
            'ok'        => (bool)$id,
            'id'        => $id,
            'saved'     => $data['saved'],
            'saved_by'  => $data['saved_by'],
            'post_type' => $data['post_type'] ?? '(missing)',
        ], 200);
    }


    /** 📦 Get a single module */
    public function get_module(WP_REST_Request $req): WP_REST_Response
    {
        $slug = sanitize_title($req->get_param('slug'));
        $module = $this->repo->get_by_slug($slug);
        if (!$module) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Not found'], 404);
        }
        return new WP_REST_Response($module, 200);
    }

    /** ❌ Delete a module */
    public function delete_module(WP_REST_Request $req): WP_REST_Response
    {
        $id = absint($req->get_param('id'));

        if (!current_user_can('delete_posts')) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Permission denied'], 403);
        }

        $ok = $this->repo->delete($id);
        return new WP_REST_Response(['ok' => $ok], 200);
    }

    /** ⬇️ Export all modules */
    public function export_modules(): WP_REST_Response
    {
        $items = $this->repo->get_all();
        return new WP_REST_Response(['items' => $items, 'ok' => true], 200);
    }

    /** ⬆️ Import modules */
    public function import_modules(WP_REST_Request $req): WP_REST_Response
    {
        $payload = json_decode($req->get_body(), true);
        if (empty($payload) || !is_array($payload)) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
        }

        $count = 0;
        foreach ($payload as $m) {
            if (!empty($m['name']) && !empty($m['data'])) {
                // Add timestamp and user meta for imported modules
                $m['saved'] = date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    current_time('timestamp')
                );
                $user = wp_get_current_user();
                $m['saved_by'] = [
                    'id'   => $user->ID,
                    'name' => $user->display_name ?: $user->user_login,
                ];
                $this->repo->save($m);
                $count++;
            }
        }

        return new WP_REST_Response(['ok' => true, 'imported' => $count], 200);
    }
}
