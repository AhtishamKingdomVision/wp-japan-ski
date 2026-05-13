<?php

$sub_title = $section['sub_title'];
$contact_details = $section['contact_details'];

echo '<section '.SectionAttributes($section, 'full-section our-offices').' '.BackgroundFromSection($section).' aria-label="Webcam Videos">';
	echo '<div class="container">';
			if($sub_title){
				echo '<span class="hero-eyebrow">'.$sub_title.'</span>';
			}
			echo TitleFromSection($section);
			if($contact_details){
				echo '<div class="contact-grid">';
					foreach ($contact_details as $key => $details) {
						$country_name = $details['country_name'];
						$title = $details['title'];
						$content = $details['content'];
						$icon_lists = $details['icon_lists'];

						echo '<div class="contact-card">';
							if($country_name){
								echo '<div class="contact-card-flag">'.$country_name.'</div>';
							}
					        if($title){
					        	echo '<h3>'.$title.'</h3>';
					        }
					        if($content){
					        	echo WysiwygReadMoreLess($content);
					        }
					        if($icon_lists){
					        	foreach($icon_lists as $key => $lists){
					        		$icon = $lists['icon'];
					        		$text = $lists['text'];

					        		if($icon == 'fa-solid fa-envelope'){
					        			echo '<div class="contact-detail"><i class="fa-solid fa-envelope"></i><a href="mailto:'.$text.'">'.$text.'</a></div>';
					        		}elseif ($icon == 'fa-solid fa-phone') {
					        			echo '<div class="contact-detail"><i class="fa-solid fa-phone"></i><a href="tel:'.$text.'">'.$text.'</a></div>';
					        		}else{
					        			echo '<div class="contact-detail"><i class="fa-solid fa-location-dot"></i><span>'.$text.'</span></div>';
					        		}

					        	}
					        }
					    echo '</div>';

					}
					
				echo '</div>'; #contact-grid
			}

	echo '</div>'; #container
echo '</section>'; #our-offices

?>
