<?php
$accordion = $section['accordion'] ?? [];

echo '<section class="full-section accordion" ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo TitleFromSection($section);
        // A11Y: accordion wrapper with role + label
        echo '<div class="acco" role="tablist">';
        if (!empty($accordion) && is_array($accordion)) {
            foreach ($accordion as $index => $item) {
                $title = $item['title'] ?? '';
                $description = $item['description'] ?? '';
                // Safe, unique IDs for A11Y
                $tab_id  = 'acc-tab-' . $index;
                $panel_id = 'acc-panel-' . $index;
                echo '<div class="accor_inn">';
                    // A11Y button for title
                    echo '<h3 
                            class="accor_trigger" 
                            id="' . esc_attr($tab_id) . '" 
                            aria-expanded="false" 
                            aria-controls="' . esc_attr($panel_id) . '" 
                            role="tab"
                        >
                            ' . esc_html($title) . '
                        </h3>';
                    // Accordion content panel
                    echo '<div 
                            class="accor_content" 
                            id="' . esc_attr($panel_id) . '" 
                            role="tabpanel" 
                            aria-labelledby="' . esc_attr($tab_id) . '" 
                            hidden
                        >';
                        echo wp_kses_post($description);
                    echo '</div>';
                echo '</div>';
            }
        }
        echo '</div>'; // acco
    echo '</div>'; // container
echo '</section>';
?>