<?php
// Get repeater safely
$titleicon = $section['title_with_icon'] ?? [];

// Start section
echo '<section ' . SectionAttributes($section, 'full-section titles_with_icons') . '  role="region" aria-label="Titles with Icons Section" ' . BackgroundFromSection($section) . '>';

    echo '<div class="container">';

        if (!empty($titleicon)) :
            echo '<ul role="list" class="title-icon-list">';

            foreach ($titleicon as $twi) {

                $icons = $twi['icons'] ?? '';
                $title = $twi['title'] ?? '';

                echo '<li role="listitem" class="title-icon-item">';

                    // ICON
                    if ($icons) {
                        // Fetch alt text from media library
                        $alt = get_post_meta($icons, '_wp_attachment_image_alt', true);
                        $alt = $alt ? esc_attr($alt) : 'Section Icon';

                        echo wp_get_attachment_image($icons, 'full', false, [
                            'alt' => $alt,
                            'class' => 'title-icon-image'
                        ]);
                    }

                    // TITLE
                    if ($title) {
                        echo '<span class="title-icon-heading" tabindex="0" aria-label="' . esc_attr($title) . '">' . esc_html($title) . '</span>';
                    }

                echo '</li>';
            }

            echo '</ul>';
        endif;

    echo '</div>';

echo '</section>';
?>
