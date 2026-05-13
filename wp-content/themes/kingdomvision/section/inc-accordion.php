<?php
$accordion = $section['accordion'] ?? [];
$small_cta = $section['small_cta'] ?? [];
$cta_button = $section['cta_button'] ?? [];

echo '<section ' . SectionAttributes($section, 'full-section accordion') . ' ' . BackgroundFromSection($section) . '>';
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
    if($small_cta){
    	echo '<div class="acco cta" role="CTA">';
    		echo $small_cta;
    		if($cta_button):
    		    $link_url = $cta_button['url'];
    		    $link_title = $cta_button['title'];
    		    $link_target = $cta_button['target'] ? $cta_button['target'] : '_self';
    		    echo '<a class="btn" href="'.$link_url.'" target="'.$link_title.'">'.$link_title.'</a>';
    		endif;
    	echo '</div>';
    }

echo '</div>'; // container
echo '</section>';
?>
