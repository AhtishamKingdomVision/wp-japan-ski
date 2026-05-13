<?php

$sub_title    = $section['sub_title'];
$listings = $section['listings'];

echo '<section '.SectionAttributes($section, 'full-section paying-with-flywire').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
    if($sub_title){
			echo '<span class="eyebrow">'.$sub_title.'</span>';
		}
    echo TitleFromSection($section);
    if($listings){
	    echo '<div class="how-steps">';
	    	$x = 1;
	    	foreach ($listings as $key => $lists) {
	    		$title = $lists['title'];
	    		$content = $lists['content'];
		      echo '<div class="how-step">';
		        echo '<div class="how-step-num">'.$x.'</div>';
		        if($title){
		        	echo '<h3>'.$title.'</h3>';
		        }
		        if($content){
		      		echo wpautop($content);
		      	} 
		      echo '</div>'; #how-step
		      $x++;
		    }
	    echo '</div>'; #how-steps
  	}
  echo '</div>'; #container
echo '</section>'; #paying-with-flywire
?>
