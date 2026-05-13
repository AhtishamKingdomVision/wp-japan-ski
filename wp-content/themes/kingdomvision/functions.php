<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// $debug = $_GET['debug'] ?? false;
// if ($debug) {
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// }

define('THEME_PATH', get_template_directory());
define('THEME_URL', get_template_directory_uri());

// Automatically include all PHP files from the /functions directory
function include_all_functions_files()
{
    $functions_path = THEME_PATH . '/functions/';

    // Check if folder exists
    if (is_dir($functions_path)) {
        foreach (glob($functions_path . '*.php') as $file) {
            require_once $file;
        }
    }
}

function theme_files()
{
    // Main style.css
    $style_path = get_stylesheet_directory() . '/style.css';
    $style_uri  = get_stylesheet_uri();
    $style_version = file_exists($style_path) ? filemtime($style_path) : false;

    wp_register_style('theme-style', $style_uri, array(), $style_version);
    wp_enqueue_style('theme-style');

    wp_register_style('taha-style', THEME_URL . '/t-style.css', false, '0.8');
    wp_enqueue_style('taha-style');

    wp_register_style('theme-styler', THEME_URL . '/css/responsive.css', false, '0.8');
    wp_enqueue_style('theme-styler');

    wp_register_style('font-css', THEME_URL . '/css/fonts.css', false, null);
    wp_enqueue_style('font-css');
	
	// Font Awesome
    wp_register_style( 'theme-fontawesome', get_stylesheet_directory_uri().'/fontawesome/css/all.css', false, filemtime(get_theme_file_path('/fontawesome/css/all.css')));
    wp_enqueue_style('theme-fontawesome');

    // wp_register_script('kv-script', THEME_URL . '/kv-script.js', array('jquery'), false, true);
    // wp_enqueue_script('kv-script');

    // KV-Script
    wp_register_script('kv-script', get_template_directory_uri() . '/kv-script.js', array('jquery'), filemtime(get_theme_file_path('/kv-script.js')), true);
	wp_enqueue_script('kv-script');

    /*Hamza scrip*/
    // wp_register_script('hamza-script', THEME_URL . '/hamza-script.js', array('jquery'), false, true);
    // wp_enqueue_script('hamza-script');

    wp_localize_script('kv-script', 'kingdomVision', array(
        'themeUrl' => get_template_directory_uri()
    ));

    // slick-carousel
    wp_register_style('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css', false);
    wp_enqueue_style('slick-carousel');
    wp_register_script('slick-carousel', '//cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), true);
    wp_enqueue_script('slick-carousel');

    // Fancybox
    wp_register_style('fancybox', THEME_URL . '/fancybox/jquery.fancybox.min.css', false);
    wp_enqueue_style('fancybox');
    wp_register_script('fancybox', THEME_URL . '/fancybox/jquery.fancybox.min.js', array('jquery', 'kv-script',), true);
    wp_enqueue_script('fancybox');

    // Date Dropper
    wp_register_script('datedropper_js', THEME_URL . '/js/datedropper.min.js', array('jquery', 'kv-script',), '1.0', true);
    wp_enqueue_script('datedropper_js');

    wp_register_script('select2_js', '//cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', true);

    $weekStartDate = get_field('check_start_date', 'option');
    $weekEndDate = get_field('check_end_date', 'option');
    $minDaysOption = get_field('check_min_days_option', 'option');
    $dateDropperContent = get_field('date_dropper_content', 'option');
    $minDays = get_field('check_min_days', 'option');

    $weekStartDate_in_time = strtotime($weekStartDate);
    $weekEndDate_in_time = strtotime($weekEndDate);
    $current_date_in_time = strtotime(date('m/d/Y'));

    if ($current_date_in_time > $weekStartDate_in_time) {
        $weekStartDate = date('m/d/Y');
    }

    if ($current_date_in_time > $weekEndDate_in_time) {
        $weekEndDate = date('F j, Y"', strtotime('+6 months')); /*6 month ahead of current date*/
    }

    $max_price = get_acc_price('max');
    $min_price = get_acc_price('min');
    wp_localize_script(
        'kv-script',
        'kv_object',
        array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'check_start_date' => $weekStartDate,
            'current_date_in_time' => $current_date_in_time,
            'weekStartDate_in_time' => $weekStartDate_in_time,
            'check_end_date' => $weekEndDate,
            'check_min_days_option' => $minDaysOption,
            'check_min_days' => $minDays,
            'range_values' => ['min_price' => $min_price, 'max_price' => $max_price],
            'date_dropper_content' => !empty($dateDropperContent) ? $dateDropperContent : false,
        )
    );

    if (!is_front_page()) {
        wp_enqueue_script('select2_js');
        wp_enqueue_style('css-select2');
        wp_enqueue_style('jquery-ui');
    }
}

add_action('wp_enqueue_scripts', 'theme_files');

include_all_functions_files();

// Enable Classic Editor
add_filter('use_block_editor_for_post', '__return_false', 10);

// Theme Options
if (function_exists('acf_add_options_page')) {
    acf_add_options_page(array(
        'page_title' => 'Theme Options',
        'menu_title' => 'Theme Options',
        'menu_slug' => 'theme-pptions',
        'capability' => 'edit_posts',
        'redirect' => false
    ));
}

// Register Sidebar
add_action('widgets_init', 'kv_widgets_init');
function kv_widgets_init() {
    $sidebar_attr = array(
        'name' => '',
        'before_widget' => '<li id="%1$s" class="widget %2$s">',
        'after_widget' => '</li>',
        'before_title' => '<h2 class="widgettitle">',
        'after_title' => '</h2>',
    );
    $sidebar_id = 0;
    $gdl_sidebar = array("Blog");
    foreach ($gdl_sidebar as $sidebar_name) {
        $sidebar_attr['name'] = $sidebar_name;
        $sidebar_attr['id'] = 'custom-sidebar' . $sidebar_id++;
        register_sidebar($sidebar_attr);
    }
}

// Register Navigation
function register_menu()
{
    register_nav_menu('main-menu', __('Main Menu'));
}
add_action('init', 'register_menu');

// Featured Image Function
add_theme_support('post-thumbnails');


// Disables the block editor from managing widgets in the Gutenberg plugin.
add_filter('gutenberg_use_widgets_block_editor', '__return_false');

// Disables the block editor from managing widgets.
add_filter('use_widgets_block_editor', '__return_false');

// SVG Image Code
function my_theme_custom_upload_mimes($existing_mimes)
{
    $existing_mimes['svg'] = 'image/svg+xml';
    // Return the array back to the function with our added mime type.
    return $existing_mimes;
}

add_filter('mime_types', 'my_theme_custom_upload_mimes');

function my_custom_mime_types($mimes)
{

    // New allowed mime types.
    $mimes['svg'] = 'image/svg+xml';
    $mimes['svgz'] = 'image/svg+xml';
    $mimes['doc'] = 'application/msword';

    // Optional. Remove a mime type.
    unset($mimes['exe']);

    return $mimes;
}

add_filter('upload_mimes', 'my_custom_mime_types');

add_filter('wpseo_breadcrumb_output', 'wp_kama_wpseo_breadcrumb_output_filter', 10, 2);
function wp_kama_wpseo_breadcrumb_output_filter($presentation, $output)
{
    $presentation = str_replace('Home', "<i class='fa fa-home' aria-hidden='true'></i>", $presentation);
    return $presentation;
}

// Size Limit
add_filter('wp_handle_upload_prefilter', 'uh_custom_upload_error');
function uh_custom_upload_error($file)
{

    $limit = 2 * 1024 * 1024; // 2 MB

    if ($file['size'] > $limit) {

        // Custom clear message for your site
        $file['error'] = 'Upload Failed: Only images under 2MB are allowed. Your file is ' . size_format($file['size'], 2) . '.';
    }

    return $file;
}

// Add rel="nofollow" to ANY <a target="_blank"> on the whole site
add_action('template_redirect', function () {
    ob_start(function ($buffer) {

        return preg_replace_callback(
            '/<a\s+([^>]*target="_blank"[^>]*)>/i',
            function ($m) {
                $tag = $m[0];

                // If rel exists: append nofollow
                if (preg_match('/rel="([^"]*)"/i', $tag, $relMatch)) {
                    if (stripos($relMatch[1], 'nofollow') === false) {
                        $newRel = trim($relMatch[1] . ' nofollow');
                        return preg_replace('/rel="[^"]*"/i', 'rel="' . $newRel . '"', $tag);
                    }
                    return $tag;
                }

                // If rel doesn't exist: add it
                return str_replace('<a', '<a rel="nofollow"', $tag);
            },
            $buffer
        );
    });
});

// preview size to acf Image
add_filter('acf/load_field/type=image', function ($field) {
    // Set default preview size to 'thumbnail' (150x150)
    $field['preview_size'] = 'thumbnail';
    return $field;
});

add_filter('gform_disable_css', '__return_true');


//Dynamic Class And ID for Sections
function SectionAttributes($section, $base_classes = '')
{
    $section_id    = !empty($section['section_id']) ? $section['section_id'] : '';
    $section_class = !empty($section['section_class']) ? $section['section_class'] : '';

    $attributes = '';

    // ID
    if ($section_id) {
        $attributes .= ' id="' . esc_attr($section_id) . '"';
    }

    // Merge Classes
    $classes = trim($base_classes);

    if ($section_class) {
        $classes .= ' ' . $section_class;
    }

    if ($classes) {
        $attributes .= ' class="' . esc_attr(trim($classes)) . '"';
    }

    return $attributes;
}


// Dynamic Background + Padding From Section
function BackgroundFromSection($section)
{

    $bg_options      = !empty($section['bg_options']) ? $section['bg_options'] : '';
    $bg_image        = !empty($section['bg_image']) ? $section['bg_image'] : '';
    $bg_color        = !empty($section['bg_color']) ? $section['bg_color'] : '';
    $padding_top     = !empty($section['padding_top']) ? $section['padding_top'] : '';
    $padding_bottom  = !empty($section['padding_bottom']) ? $section['padding_bottom'] : '';

    $style = '';

    if ($bg_options === 'color' && $bg_color) {
        $style .= 'background-color: ' . esc_attr($bg_color) . ';';
    }

    if ($bg_options === 'image' && $bg_image) {
        $image_url = is_array($bg_image) && isset($bg_image['url']) ? $bg_image['url'] : $bg_image;
        $style .= 'background-image: url(' . esc_url($image_url) . ');';
        $style .= 'background-size: cover;';
        $style .= 'background-position: center;';
    }

    if ($padding_top) {
        $style .= 'padding-top: ' . esc_attr($padding_top) . 'px;';
    }

    if ($padding_bottom) {
        $style .= 'padding-bottom: ' . esc_attr($padding_bottom) . 'px;';
    }

    if (!$style) return '';

    return 'style="' . trim($style) . '"';
}

// Dynamic Title From Section
function TitleFromSection($section)
{

    // Safe Values
    $title       = $section['title'] ?? '';
    $font_size   = $section['font_size'] ?? '';
    $title_tag   = $section['title_tag'] ?? 'h2';
    $title_color = $section['title_color'] ?? '';
    $title_align = $section['title_align'] ?? 'left';

    // Return nothing if no title
    if (empty($title)) {
        return '';
    }

    // Validate tag (fallback h2)
    $allowed_tags = ['h1', 'h2', 'h3', 'h4'];
    if (!in_array($title_tag, $allowed_tags)) {
        $title_tag = 'h2';
    }

    // Build inline styles
    $style = '';

    if (!empty($font_size)) {
        $style .= 'font-size:' . esc_attr($font_size) . 'px;';
    }

    if (!empty($title_color)) {
        $style .= 'color:' . esc_attr($title_color) . ';';
    }
    if (!empty($title_align)) {
        $style .= 'text-align:' . esc_attr($title_align);
    }
    // Final HTML Output
    $output  = '<div class="section-title-wrapper ' . ($title_align ? $title_align : '') . '">';
    $output .= '<' . $title_tag . ' class="section-title" style="' . $style . '" aria-label="' . esc_attr($title) . '" tabindex="0">';
    $output .= esc_html($title);
    $output .= '</' . $title_tag . '>';
    $output .= '</div>';

    return $output;
}

function HZoverviewTitleFromSection($section)
{

    // Safe Values
    $title       = $section['title'] ?? '';
    $font_size   = $section['font_size'] ?? '';
    $title_tag   = $section['title_tag'] ?? 'h2';
    $title_color = $section['title_color'] ?? '';
    $title_align = $section['title_align'] ?? 'left';

    // Return nothing if no title
    $cat_name = str_replace(' Accommodation', '', hz_get_parent_category( get_the_ID() ));
    $title = get_the_title();
     if ( ! empty( $cat_name ) && strpos( strtolower( $title ), strtolower( $cat_name ) ) !== false ) {
        $title .= ' Overview';
    } elseif ( ! empty( $cat_name ) ) {
        $title .= ' ' . $cat_name . ' Overview';
    } else {
        $title .= ' Overview';
    }

    // Validate tag (fallback h2)
    $allowed_tags = ['h1', 'h2', 'h3', 'h4'];
    if (!in_array($title_tag, $allowed_tags)) {
        $title_tag = 'h2';
    }

    // Build inline styles
    $style = '';

    if (!empty($font_size)) {
        $style .= 'font-size:' . esc_attr($font_size) . 'px;';
    }

    if (!empty($title_color)) {
        $style .= 'color:' . esc_attr($title_color) . ';';
    }
    if (!empty($title_align)) {
        $style .= 'text-align:' . esc_attr($title_align);
    }
    // Final HTML Output
    $output  = '<div class="section-title-wrapper ' . ($title_align ? $title_align : '') . '">';
    $output .= '<' . $title_tag . ' class="section-title" style="' . $style . '" aria-label="' . esc_attr($title) . '" tabindex="0">';
    $output .= esc_html($title);
    $output .= '</' . $title_tag . '>';
    $output .= '</div>';

    return $output;
}

// Dyanmic Team Member from Team Specialists
add_filter('acf/load_field/name=select_member', 'acf_load_team_members_as_select_choices');
function acf_load_team_members_as_select_choices($field)
{
    $field['choices'] = array(); // reset choices

    if (have_rows('team_members', 'option')) {
        while (have_rows('team_members', 'option')) {
            the_row();
            $name = get_sub_field('name');

            if ($name) {
                // value => label
                $field['choices'][$name] = $name;
            }
        }
    }

    return $field;
}

// WYSIWYG Editor Read More Read Less
function WysiwygReadMoreLess($wysiwyg, $class = '')
{
    $printable = '';
    if ($wysiwyg) {
        $parts = explode('<!--more-->', $wysiwyg);
        $printable .= '<div class="contentWrapper ' . esc_attr($class) . '">';
        if (count($parts) > 1) {
            $printable .= '<div class="wysiwygShortContent">' . wpautop($parts[0]) . '</div>';
            $printable .= '<div class="wysiwygFullContent" style="display:none;">' . wpautop($parts[1]) . '</div>';
            $printable .= '<div class="wysiwygToggleWrap" >';
            $printable .= '<a href="#" class="wysiwygReadMore"> Read More </a>';
            $printable .= '<a href="#" class="wysiwygReadLess" style="display:none;"> Read Less </a>';
            $printable .= '</div>';
        } else {
            $printable .= wpautop($wysiwyg);
        }
        $printable .= '</div>'; #contentWrapper
    }
    return $printable;
}

// get member from Specialists
function getMemberFromSpecialists($select_author, $teamMembersOption, $date)
{
    $printable = '';
    if ($select_author && $teamMembersOption) {
        $selected_name = $select_author;
        $member_data = null;

        foreach ($teamMembersOption as $team_member_row) {
            if ($team_member_row['name'] === $selected_name) {
                $member_data = $team_member_row;
                break;
            }
        }

        if ($member_data) {
            $profile_image = $member_data['profile_image'];
            $name = $member_data['name'];
            $designation = $member_data['designation'];

            $printable .= '<div class="teamMember" role="list" aria-label="Team Members">';
            if ($profile_image) {
                $printable .= '<div class="teamImg">';
                $printable .= wp_get_attachment_image(
                    $profile_image,
                    'full',
                    false,
                    [
                        'loading' => 'eager',
                    ]
                );
                $printable .= '</div>'; #teamImg
            }
            if ($name || $designation || $date) {
                $printable .= '<div class="teamDetails">';
                if ($name) {
                    $printable .= '<span>' . esc_html($name) . '</span>';
                }
                if ($designation) {
                    $printable .= '<p class="designation">' . esc_html($designation) . '</p>';
                }
                if ($date) {
                    $printable .= '<p class="postDate">' . esc_html($date) . '</p>';
                }
                $printable .= '</div>'; #teamDetails
            }
            $printable .= '</div>'; #teamMember

        }
    }
    return $printable;
}

/**
 * Shortcode: [kv_posts_filter]
 */
function kv_posts_filter_shortcode($atts)
{

    $atts = shortcode_atts([
        'cat'       => '',
        'per_page' => 6,
    ], $atts);

    $fixed_cat = sanitize_text_field($atts['cat']);
    $per_page  = (int) $atts['per_page'];

    $categories = empty($fixed_cat)
        ? get_categories(['hide_empty' => true])
        : [];

    ob_start();
?>

    <div class="kv-posts-wrapper"
        data-fixed-cat="<?php echo esc_attr($fixed_cat); ?>"
        data-per-page="<?php echo esc_attr($per_page); ?>">

        <!-- FILTER BAR -->
        <form class="kv-filter-bar js-kv-filter">

            <select name="category" class="js-kv-category">

                <?php if (empty($fixed_cat)) : ?>

                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php echo esc_attr($cat->slug); ?>">
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>

                <?php else : ?>

                    <?php
                    $fixed_term = get_category_by_slug($fixed_cat);
                    if ($fixed_term) :
                    ?>
                        <option value="<?php echo esc_attr($fixed_term->slug); ?>" selected>
                            <?php echo esc_html($fixed_term->name); ?>
                        </option>
                    <?php endif; ?>

                <?php endif; ?>

            </select>


            <select name="sort" class="js-kv-sort">
                <option value="recent">Most Recent</option>
                <option value="oldest">Oldest</option>
                <option value="az">A – Z</option>
                <option value="za">Z – A</option>
            </select>

        </form>

        <!-- POSTS GRID -->
        <div class="kv-post-grid js-kv-posts"></div>

        <div class="loadmore js-kv-loadmore-wrap">
            <a href="javascript:;" class="btn js-kv-loadmore">Load More</a>
        </div>

    </div>

<?php
    return ob_get_clean();
}
add_shortcode('kv_posts_filter', 'kv_posts_filter_shortcode');


add_action('wp_ajax_kv_filter_posts', 'kv_filter_posts');
add_action('wp_ajax_nopriv_kv_filter_posts', 'kv_filter_posts');

function kv_filter_posts()
{

    $page     = (int) ($_POST['page'] ?? 1);
    $per_page = (int) ($_POST['per_page'] ?? 6);
    $cat      = sanitize_text_field($_POST['category'] ?? '');
    $sort     = sanitize_text_field($_POST['sort'] ?? 'recent');

    $orderby = 'date';
    $order   = 'DESC';

    if ($sort === 'oldest') {
        $order = 'ASC';
    } elseif ($sort === 'az') {
        $orderby = 'title';
        $order   = 'ASC';
    } elseif ($sort === 'za') {
        $orderby = 'title';
        $order   = 'DESC';
    }

    $args = [
        'post_type'      => 'post',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => $orderby,
        'order'          => $order,
    ];

    if (!empty($cat)) {
        $args['category_name'] = $cat;
    }

    $query = new WP_Query($args);

    ob_start();

    if ($query->have_posts()) :
        while ($query->have_posts()) : $query->the_post();
            get_template_part('post-card');
        endwhile;
    endif;

    $html = ob_get_clean();

    wp_reset_postdata();

    wp_send_json([
        'html'     => $html,
        'has_more' => ($query->max_num_pages > $page),
    ]);
}


add_filter( 'acf/field_group/disable_field_settings_tabs', '__return_true' );