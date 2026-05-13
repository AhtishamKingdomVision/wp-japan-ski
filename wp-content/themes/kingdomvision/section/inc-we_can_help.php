<?php

$sub_title = $section['sub_title'];
$help_items = $section['help_items'];
$trip_planner = $section['trip_planner'];

echo '<section '.SectionAttributes($section, 'full-section we-can-help').' '.BackgroundFromSection($section).' aria-label="Webcam Videos">';
	echo '<div class="container">';
			if($sub_title){
				echo '<span class="hero-eyebrow">'.$sub_title.'</span>';
			}
			echo TitleFromSection($section);
			echo '<div class="help-grid">';
				if($help_items){
					echo '<div class="help-items">';
						foreach ($help_items as $key => $items) {
							$icon = $items['icon'];
							$text = $items['text'];

							echo '<div class="help-item">';
								if($icon){
									echo '<i class="'.$icon.'"></i>';
								}
								if($text){
									echo '<p>'.$text.'</p>';
								}
						    echo '</div>'; #help-item

						}
					echo '</div>';
						
				}
				if($trip_planner){
					$hours_text = $trip_planner['hours_text'];
					$title = $trip_planner['title'];
					$content = $trip_planner['content'];
					$button = $trip_planner['button'];
					echo '<div class="help-aside">';
						if($hours_text){
							echo '<div class="response-badge"><i class="fa-regular fa-clock"></i> '.$hours_text.'</div>';
						}
						if($title){
							echo '<h3>'.$title.'</h3>';
						}
						if($content){
				        	echo WysiwygReadMoreLess($content);
				        }
				        if($button){
				        	$buttonUrl = $button['url'];
				        	$buttonTitle = $button['title'];
				        	$buttonTarget = $button['target'] ? $button['target'] : '_self';
				        	echo '<a href="'.esc_url($buttonUrl).'" target="'.esc_attr($buttonTarget).'" class="btn btn-primary">'.esc_html($buttonTitle).'</a>';
				        }
				    echo '</div>';
				}
			echo '</div>'; #help-grid

	echo '</div>'; #container
echo '</section>'; #we-can-help

?>
