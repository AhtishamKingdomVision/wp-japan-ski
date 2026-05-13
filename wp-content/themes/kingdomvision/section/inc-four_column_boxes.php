<?php
$data_type    = $section['data_type'] ?? '';
$static_data  = $section['static_data'] ?? [];
$dynamic      = $section['dynamic'] ?? [];
$section_desc = $section['section_desc'] ?? [];
echo '<section ' . SectionAttributes($section, 'full-section four_column_boxes') . ' ' . BackgroundFromSection($section) . ' role="region">';
echo '<div class="container">';
echo TitleFromSection($section);
if(!empty($section_desc)){
    echo '<div class="desc">'. $section_desc .'</div>';
}
echo '<div class="fcb_cover">';


/* --------------------------------------------------
 * STATIC DATA
 * -------------------------------------------------- */
if ($data_type === 'static') {

    foreach ($static_data as $v) {

        $image   = $v['image'] ?? '';
        $title   = $v['title'] ?? '';
        $content = $v['content'] ?? '';
        $link    = $v['link']['url'] ?? '';
        $link_title  = $v['link']['title'] ?? '';

        $link_target = !empty($v['link']['target']) ? esc_attr($v['link']['target']) : '_self';
        // $link_text = !empty($v['title']);


        echo '<div class="fcb_inner '.esc_attr($link_title ? 'haveBtn' : '').'">';

            if (!empty($link)) {
                echo '<a href="' . esc_url($link) . '" target="' . $link_target . '" aria-label="' . esc_attr($title ?: 'Box Item') . '">';
            }

            if (!empty($image)) {
                echo wp_get_attachment_image($image, 'full', false, ['alt' => esc_attr($title ?: 'Image')]);
            }

            echo '<div class="fcb_content ">';
                if (!empty($title)) {
                    echo '<h3 class="'.esc_attr(!$link ?: 'haveLink').'">' . esc_html($title) . '</h3>';
                }

                if (!empty($content)) {
                    echo $content;
                }
                if (!empty($link_title)) {
                    echo '<span class="btn">'. $link_title.'</span>';
                }
            echo '</div>';

            if (!empty($link)) {
                echo '</a>';
            }

        echo '</div>';
    }


/* --------------------------------------------------
 * DYNAMIC DATA (select_page field)
 * -------------------------------------------------- */
} else {

    foreach ($dynamic as $item) {

        $page_field = $item['select_page'] ?? '';

        if (empty($page_field)) {
            continue;
        }

        /* --- ACF Page Link field return handling --- */

        if (is_numeric($page_field)) {  
            // Returns ID
            $page_id = $page_field;
            $page_link = get_permalink($page_id);
            $page_title = get_the_title($page_id);

        } elseif (is_array($page_field)) {
            // Returns array: url, title, target
            $page_link  = $page_field['url'] ?? '';
            $page_title = $page_field['title'] ?? '';
            $page_id    = url_to_postid($page_link); // try extracting ID

        } else {
            // Returns URL
            $page_link = $page_field;
            $page_id   = url_to_postid($page_field);
            $page_title = get_the_title($page_id);
        }

        $image_id = get_post_thumbnail_id($page_id);

        echo '<div class="fcb_inner">';

            if (!empty($page_link)) {
                echo '<a href="' . esc_url($page_link) . '" target="_self" aria-label="' . esc_attr($page_title ?: 'Box Item') . '">';
            }

            if (!empty($image_id)) {
                echo wp_get_attachment_image(
                    $image_id,
                    'full',
                    false,
                    ['alt' => esc_attr($page_title ?: 'Image')]
                );
            }

            if (!empty($page_title)) {
                echo '<h3>' . esc_html($page_title) . '</h3>';
            }

            if (!empty($page_link)) {
                echo '</a>';
            }

        echo '</div>';
    }
}

echo '</div>'; // fcb_cover
echo '</div>'; // container
echo '</section>';
?>
