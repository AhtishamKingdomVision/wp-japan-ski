<?php
$sub_title         = $section['sub_title'] ?? '';
$link              = $section['link'] ?? '';
$image_with_title  = $section['image_with_title'] ?? [];

echo '<section ' . SectionAttributes($section, 'full-section things-td-grid') . ' ' . BackgroundFromSection($section) . '>';
echo '<div class="container">';
echo '<div class="ttd-wrap">';

/* HEADER = 1st NORMAL */
echo '<div class="ttd-card ttd-header normal">';
if ($sub_title) {
    echo '<p class="section-subtitle">' . esc_html($sub_title) . '</p>';
}
echo TitleFromSection($section);
echo '</div>';

if (!empty($image_with_title)) {

    $i = 0;

    foreach ($image_with_title as $item) {

        $title      = $item['title'] ?? '';
        $image      = $item['image'] ?? '';
        $item_link  = $item['link'] ?? $link;

        $url    = '';
        $target = '';

        if (is_array($item_link)) {
            $url    = $item_link['url'] ?? '';
            $target = $item_link['target'] ?? '';
        } else {
            $url = $item_link;
        }

        $class = '';

        if ($i < 2) {
            $class = 'normal';
        } else {
            $patternIndex = ($i - 2) % 4;

            if ($patternIndex == 0) {
                $class = 'big-left';
            } elseif ($patternIndex == 3) {
                $class = 'big-right';
            } else {
                $class = 'normal';
            }
        }

        echo '<div class="ttd-card ' . $class . '">';

        if ($url) {
            echo '<a href="' . esc_url($url) . '" ' . ($target ? 'target="'.esc_attr($target).'"' : '') . '>';
        }

        if (!empty($image) && is_array($image)) {
            echo '<div class="ttd-image">';
            echo wp_get_attachment_image($image['ID'], 'large');
            echo '</div>';
        }

        if ($title) {
            echo '<h3 class="ttd-title">' . esc_html($title) . '</h3>';
        }

        if ($url) {
            echo '</a>';
        }

        echo '</div>';

        $i++;
    }
}

echo '</div>';
echo '</div>';
echo '</section>';
?>
