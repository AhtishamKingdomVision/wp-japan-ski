<?php
$title         = $section['title'];
$section_id    = $section['section_id'];
$content       = $section['content'];
$title_tag     = $section['title_tag'];
$content_style = $section['content_style'];
$name 		   = $section['name'];
$destination   = $section['destination'];

echo '<div class="simple_content full-section '.$content_style.'"' 
    . (!empty($section_id) ? ' id="' . esc_attr($section_id) . '"' : '') 
    . '>';

    if (!empty($title)) {
        echo '<'. $title_tag .'>' . esc_html($title) . '</' .$title_tag .'>';
    }

    if (!empty($content)) {
        echo wp_kses_post($content);
    }

    if (!empty($name && $destination)) {
        echo '<strong class="name">'. $name .'</strong>';
        echo '<span class="destination">'. $destination .'</span>';
    }

echo '</div>';
?>
