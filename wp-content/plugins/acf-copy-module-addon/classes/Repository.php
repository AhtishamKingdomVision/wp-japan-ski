<?php

namespace ACFML;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handles CRUD operations for ACF Modules stored in a JSON file.
 */
class Repository
{
    protected $file_path;

    public function __construct()
    {
        $upload_dir = wp_upload_dir();
        $this->file_path = trailingslashit($upload_dir['basedir']) . 'acfml-modules.json';
        $this->ensure_file_exists();
    }

    /** 🧱 Ensure JSON file exists */
    protected function ensure_file_exists()
    {
        if (!file_exists($this->file_path)) {
            file_put_contents($this->file_path, json_encode([], JSON_PRETTY_PRINT));
        }
    }

    /** 🧾 Retrieve all modules (optionally filtered by type) */
    public function get_all($type = null)
    {
        $all = json_decode(file_get_contents($this->file_path), true) ?: [];
        if ($type) {
            $all = array_filter($all, function ($m) use ($type) {
                return isset($m['type']) && $m['type'] === $type;
            });
        }
        return array_values($all);
    }

    /** 💾 Save or update a module (PHP 7-safe) */
    public function save(array $data)
    {
        $all = json_decode(file_get_contents($this->file_path), true) ?: [];
        $user = wp_get_current_user();

        $slug = sanitize_title(isset($data['slug']) ? $data['slug'] : $data['name']);
        $id   = isset($data['id']) ? intval($data['id']) : time();

        $date_format = get_option('date_format') . ' ' . get_option('time_format');
        $saved_time  = date_i18n($date_format, current_time('timestamp'));

        $module = array(
            'id'            => $id,
            'name'          => sanitize_text_field($data['name']),
            'slug'          => $slug,
            'acf_group_key' => isset($data['acf_group_key']) ? sanitize_text_field($data['acf_group_key']) : '',
            'post_type' => isset($data['post_type']) ? sanitize_text_field($data['post_type']) : '',
            'type'          => isset($data['type']) ? sanitize_text_field($data['type']) : 'section',
            'data'          => isset($data['data']) ? $data['data'] : array(),
            'saved_at'      => $saved_time,
            'saved_by'      => array(
                'id'   => $user->ID,
                'name' => $user->display_name ? $user->display_name : $user->user_login,
            ),
        );

        // Remove old version with same slug
        $all = array_filter($all, function ($m) use ($slug) {
            return $m['slug'] !== $slug;
        });
        $all[] = $module;

        file_put_contents(
            $this->file_path,
            json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $id;
    }

    public function get_by_id($id)
    {
        $modules = $this->get_all();

        foreach ($modules as $m) {
            if ((int)$m['id'] === (int)$id) {
                return $m;
            }
        }

        return null;
    }

    /** 📦 Get module by slug */
    public function get_by_slug($slug)
    {
        $all = json_decode(file_get_contents($this->file_path), true) ?: [];
        foreach ($all as $m) {
            if ($m['slug'] === $slug) {
                return $m;
            }
        }
        return null;
    }

    /** ❌ Delete module by ID */
    public function delete($id)
    {
        $all = json_decode(file_get_contents($this->file_path), true) ?: [];
        $before = count($all);
        $all = array_filter($all, function ($m) use ($id) {
            return intval($m['id']) !== intval($id);
        });
        file_put_contents($this->file_path, json_encode(array_values($all), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return count($all) < $before;
    }

    /** ⬆️ Import modules (overwrite existing) */
    public function import(array $items)
    {
        if (!is_array($items)) {
            return false;
        }
        file_put_contents($this->file_path, json_encode(array_values($items), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return true;
    }

    /** ⬇️ Export modules */
    public function export()
    {
        return file_get_contents($this->file_path);
    }

    /** 🧹 Clear file */
    public function wipe()
    {
        file_put_contents($this->file_path, json_encode([], JSON_PRETTY_PRINT));
    }
}
