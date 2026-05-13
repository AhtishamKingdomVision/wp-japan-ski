<?php

$sub_title = $section['sub_title'];
$content = $section['content'];
$icon_lists = $section['icon_lists'];
$form_shortcode = $section['form_shortcode'];

echo '<section '.SectionAttributes($section, 'full-section contact-hero').' '.BackgroundFromSection($section).' aria-label="Webcam Videos">';
	echo '<div class="container">';
				
			echo '<div class="hero-grid">';
				echo '<div class="hero-content">';
					if($sub_title){
						echo '<span class="hero-eyebrow">'.$sub_title.'</span>';
					}
			        echo TitleFromSection($section);
			        if($content){
			        	echo WysiwygReadMoreLess($content, 'hero-lead');
			        }
			        if($icon_lists){
			        	echo '<div class="hero-meta">';
			        		foreach($icon_lists as $key => $lists){
			        			$icon = $lists['icon'];
			        			$text = $lists['text'];
			        			echo '<div class="hero-meta-item"><i class="'.$icon.'"></i> '.$text.'</div>';
			        		}
			        	echo '</div>';
			        }
			    echo '</div>'; #hero-content

				echo '<div class="form-card">';
					echo '<p class="form-card-title">Send us a message</p>';
					echo do_shortcode($form_shortcode);
				echo '</div>'; #form-card
			echo '</div>'; #hero-grid

	echo '</div>'; #container
echo '</section>'; #webcam-videos

?>
