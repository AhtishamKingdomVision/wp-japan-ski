<?php

// Add scripts to wp_head()
function breadcrumbs_schema() {

    //  BreadCrumb Schema
    $id = get_the_ID();
    $breadcrumbs = get_field('custom_breadcrumb', $id);
    if (!empty($breadcrumbs)) {
        $schema = '<script type="application/ld+json" cst-crumb-schema>{"@context": "https://schema.org","@type": "BreadcrumbList","itemListElement": [';
        $i = 1;
        if (!home_url()) {
            foreach ($breadcrumbs as $track) {
                $value = $track['custom_breadcrumb_link'];
                $post_id = $value->ID;
                $breadcrumb_title = $value->post_title;
                $page_link = get_permalink($post_id);

                $schema .= '{"@type": "ListItem","position": ' . $i . ',"name": "' . $breadcrumb_title . '","item": "' . $page_link . '"},';
                $i++;
            }
        }
        $schema .= '{"@type": "ListItem","position": ' . $i . ',"name": "' . get_the_title($id) . '","item": "' . get_permalink($id) . '"}';
        $schema .= ']}</script>';
        echo $schema;
    }

}

add_action('wp_head', 'breadcrumbs_schema');

// breadcrumbs New Product page
add_shortcode('cst-breadcrumbs', 'codex_generate_custom_breadcrumbs');
function codex_generate_custom_breadcrumbs($atts) {
    ob_start();
    wp_reset_postdata();
    extract(shortcode_atts(array(
        'id' => get_the_ID(),
        'slug' => false,
    ), $atts));

    if (!empty($id)): ?>
        <div class="cst-breadcrumbs">
            <ul>
                <li class="home_bc"><a href="<?php echo home_url(); ?>"><i class="fa fa-home"></i></a></li>
                <?php if (have_rows('custom_breadcrumb', $id)) : ?>
                    <?php while (have_rows('custom_breadcrumb', $id)) : the_row(); ?>

                        <?php
                        $value = get_sub_field('custom_breadcrumb_link');
                        $post_id = $value->ID;
                        $breadcrumb_title = $value->post_title;
                        $breadcrumb_slug = ucfirst(str_replace('-', ' ', $value->post_name));
                        $page_link = get_permalink($post_id);
                        ?>
                        <?php if ($slug == false): ?>
                            <li><a href="<?php echo $page_link; ?>"><?php echo $breadcrumb_title; ?></a></li>
                        <?php else: ?>
                            <li><a href="<?php echo $page_link; ?>"><?php echo $breadcrumb_slug; ?></a></li>
                        <?php endif; ?>

                    <?php endwhile; ?>
                <?php endif; ?>
                <li><span><?php echo get_the_title($id); ?></span></li>
            </ul>
        </div>
    <?php endif;
    wp_reset_postdata();
    return '' . ob_get_clean();
}