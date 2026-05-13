<?php
/**
 * Plugin Name: Menu Icons (Class + Media Image)
 * Description: Add menu icons using Font Awesome OR Media Library image.
 * Version: 1.2.0
 */

if (!defined('ABSPATH')) exit;

/* Admin field */
add_action('wp_nav_menu_item_custom_fields', function ($item_id) {
    $icon_class = get_post_meta($item_id, '_menu_icon_class', true);
    $icon_id = get_post_meta($item_id, '_menu_icon_image_id', true);
    $img = $icon_id ? wp_get_attachment_image($icon_id, 'thumbnail') : '';
    ?>
    <p class="description description-wide">
        <label>Icon Class</label><br>
        <input type="text" name="menu_icon_class[<?php echo $item_id; ?>]" value="<?php echo esc_attr($icon_class); ?>" placeholder="fa-solid fa-house" />
    </p>

    <p class="description description-wide">
        <label>Icon Image</label><br>
        <span class="menu-icon-preview"><?php echo $img; ?></span><br>
        <input type="hidden" class="menu-icon-id" name="menu_icon_image_id[<?php echo $item_id; ?>]" value="<?php echo esc_attr($icon_id); ?>" />
        <button class="button upload-menu-icon">Add Image</button>
        <button class="button remove-menu-icon">Remove</button>
    </p>
    <?php
}, 10);

/* Save */
add_action('wp_update_nav_menu_item', function ($menu_id, $item_id) {
    if (isset($_POST['menu_icon_class'][$item_id])) {
        update_post_meta($item_id, '_menu_icon_class', sanitize_text_field($_POST['menu_icon_class'][$item_id]));
    }
    if (isset($_POST['menu_icon_image_id'][$item_id])) {
        update_post_meta($item_id, '_menu_icon_image_id', absint($_POST['menu_icon_image_id'][$item_id]));
    }
}, 10, 2);

/* Frontend */
add_filter('nav_menu_item_title', function ($title, $item) {
    $icon_id = get_post_meta($item->ID, '_menu_icon_image_id', true);
    $icon_class = get_post_meta($item->ID, '_menu_icon_class', true);

    if ($icon_id) {
        $icon = wp_get_attachment_image($icon_id, 'full', false, ['class'=>'menu-icon-image']);
        return $icon . '<span class="menu-text">'.$title.'</span>';
    }
    if ($icon_class) {
        return '<i class="'.esc_attr($icon_class).'"></i> <span class="menu-text">'.$title.'</span>';
    }
    return $title;
}, 10, 2);

/* Scripts */
add_action('admin_enqueue_scripts', function ($hook) {
    if ($hook !== 'nav-menus.php') return;
    wp_enqueue_media();
    wp_enqueue_script('menu-icon-media', plugin_dir_url(__FILE__) . 'media.js', ['jquery'], null, true);
});

/* Font Awesome */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('fa', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css');
});
