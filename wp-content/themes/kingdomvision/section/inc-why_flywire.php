<?php

$sub_title    = $section['sub_title'];
$pay_listings = $section['pay_listings'];

echo '<section '.SectionAttributes($section, 'full-section benefits-section').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
   	if($sub_title){
			echo '<span class="eyebrow">'.$sub_title.'</span>';
		}
    echo TitleFromSection($section);
    if($pay_listings){
	    echo '<div class="benefits-grid">';
	    	foreach ($pay_listings as $key => $listings) {
	    		$icon = $listings['icon'];
	    		$title = $listings['title'];
	    		$content = $listings['content'];
		      echo '<div class="benefit-card">';
		        echo '<div class="benefit-icon"><i class="'.$icon.'"></i></div>';
		        if($title){
		        	echo '<h3>'.$title.'</h3>';
		        }
		        if($content){
		      		echo wpautop($content);
		      	} 
		      echo '</div>';
	    	}
	    echo '</div>'; #benefits-grid
  	}
  echo '</div>'; #container
echo '</section>'; #benefits-section

?>
