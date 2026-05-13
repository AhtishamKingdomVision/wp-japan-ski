<?php
$content_area = $section['content'] ?? '';
$bplayout     = $section['bullet_point_layout'] ?? '';
$bullet       = $section['bullet_points'] ?? [];
$bg_icon      = $section['bg_icon'] ?? '';
$button       = $section['button'] ?? [];
$ovcolor      = $section['overlay_color'];

/* === Section ID & Extra Class === */
$section_id  = !empty($section['section_id']) 
    ? ' id="' . esc_attr($section['section_id']) . '"' 
    : '';

$extra_class = !empty($section['section_class']) 
    ? ' ' . esc_attr($section['section_class']) 
    : '';

/* === Start Section === */
echo '<section class="full-section cta_with_bg_image ' . esc_attr($bg_icon) . $extra_class . '" ' . $section_id . ' ' . BackgroundFromSection($section) . ' role="region" aria-labelledby="cta-title">';
        echo '<div class="section-overlay" style="background: linear-gradient(0deg, rgba(0, 17, 31, 0.'. $ovcolor .') 0%, rgba(0, 17, 31, 0.'. $ovcolor .') 100%);"></div>';
echo '<div class="container">';
    echo '<div class="cwbg_cover">';

        // Accessible Title
        echo TitleFromSection($section);

        // Content
        if (!empty($content_area)) {
            printf('<div class="content_area">%s</div>', wp_kses_post($content_area));
        }

        // Bullet List
        if (!empty($bullet) && is_array($bullet)) {
            echo '<ul class="cta-list ' . esc_attr($bplayout) . '" role="list">';
            foreach ($bullet as $value) {
                $points = $value['points'] ?? '';
                if (!empty($points)) {
                    echo '<li>' . esc_html($points) . '</li>';
                }
            }
            echo '</ul>';
        }

        // Button
        if (!empty($button)) :
            $link_url    = $button['url'] ?? '';
            $link_title  = $button['title'] ?? '';
            $link_target = !empty($button['target']) ? $button['target'] : '_self';

            if ($link_url && $link_title) {
                echo '<a class="btn" href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '">'
                    . esc_html($link_title) .
                '</a>';
            }
        endif;

    echo '</div>'; // .cwbg_cover
echo '</div>'; // .container
echo '</section>';
?>