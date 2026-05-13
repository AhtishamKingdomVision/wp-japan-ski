<?php
	$description        = $section['description'];
	$bullet_points      = $section['bullet_points'];
	$buttons            = $section['buttons'];
	$ovcolor            = $section['overlay_color'];

	echo '<section ' . SectionAttributes($section, 'full-section simple-middle-content') . ' ' . BackgroundFromSection($section) . '>';
        // if (!empty($ovcolor)) {
            // $opacity = $ovcolor / 100;

            echo '<div class="section-overlay" style="background: linear-gradient(0deg, rgba(0, 17, 31, 0.'. $ovcolor .') 0%, rgba(0, 17, 31, 0.'. $ovcolor .') 100%);"></div>';
//        }
		echo '<div class="container">';
			echo '<div class="smc-wrap">';
			    echo do_shortcode('[company_rating]');
				echo TitleFromSection($section);
				if (!empty($description)) {
				    echo '<div class="desc">' . $description . '</div>';
				}
				if($bullet_points){
				    echo '<ul class="bullet-points">';
				    foreach($bullet_points as $row){
				        echo '<li>'. esc_html($row['point']) .'</li>';
				    }
				    echo '</ul>';
				}

				if ($buttons) {
				    echo '<div class="buttons">';

				    if (!empty($buttons['button'])) {
				        $btn = $buttons['button'];
				        echo '<a href="' . esc_url($btn['url']) . '" class="btn" target="' . esc_attr($btn['target']) . '">'
				                . esc_html($btn['title']) .
				             '</a>';
				    }

				    if (!empty($buttons['button_2'])) {
				        $btn2 = $buttons['button_2'];
				        echo '<a href="' . esc_url($btn2['url']) . '" class="btn btn2" target="' . esc_attr($btn2['target']) . '">'
				                . esc_html($btn2['title']) .
				             '</a>';
				    }

				    echo '</div>';
				}
			echo '</div>'; #smc-wrap
		echo '</div>'; #container
	echo '</section>';
?>