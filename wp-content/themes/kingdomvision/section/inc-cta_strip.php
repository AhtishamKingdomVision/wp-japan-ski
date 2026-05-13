<?php

$description = $section['description'];
$buttons = $section['buttons'];

echo '<section '.SectionAttributes($section, 'full-section ctaStrip').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
    echo '<div class="cta-inner">';
      echo '<div class="cta-text">';
        echo TitleFromSection($section);
        if($description){
      		echo wpautop($description);
      	} 
      echo '</div>'; #cta-text
      if($buttons){
      	$button_one = $buttons['button_one'];
      	$button_two = $buttons['button_two'];
	      echo '<div class="cta-btns">';
	      	if($button_one){
	      		$buttonOneUrl = $button_one['url'];
	      		$buttonOneTitle = $button_one['title'];
	      		$buttonOneTarget = $button_one['target'] ? $button_one['target'] : '_self';
	        
	        	echo '<a href="'.esc_url($buttonOneUrl).'" class="btn btn-primary" target="'.esc_attr($buttonOneTarget).'">'.esc_html($buttonOneTitle).'</a>';
	      	
	      	}
	      	if($button_two){
	      		$buttonTwoUrl = $button_two['url'];
	      		$buttonTwoTitle = $button_two['title'];
	      		$buttonTwoTarget = $button_two['target'] ? $button_two['target'] : '_self';
		        
		        echo '<a href="'.esc_url($buttonTwoUrl).'" class="btn btn-ghost" target="'.esc_attr($buttonTwoTarget).'">'.esc_html($buttonTwoTitle).'</a>';

		      }
	      echo '</div>'; #cta-btns
      }
    echo '</div>'; #cta-inner
  echo '</div>'; #container
echo '</section>'; #ctaStrip

?>
