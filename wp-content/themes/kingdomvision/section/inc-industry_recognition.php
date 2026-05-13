<?php

$sub_title    = $section['sub_title'];
$content = $section['content'];
$awards = $section['awards'];
$memberships = $section['memberships'];

echo '<section '.SectionAttributes($section, 'full-section awards-section').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
  	if($sub_title){
			echo '<span class="eyebrow">'.$sub_title.'</span>';
		}
    echo TitleFromSection($section);
    if($content){
    	echo '<p class="awards-intro">'.$content.'</p>';
    }

    if($awards){
	    echo '<div class="awards-list">';
	    	foreach ($awards as $key => $award) {
	    		$title = $award['title'];
	    		$years = $award['years'];
	    		$description = $award['description'];
		      
		      echo '<div class="award-item">';
		        echo '<div class="award-item-icon">🏆</div>';
		        echo '<div class="award-item-content">';
		          echo '<div class="award-item-meta">';
		          	if($title){
			            echo '<h3 class="award-item-title">'.$title.'</h3>';
		          	}
		          	if($years){
			            echo '<span class="award-item-year">'.$years.'</span>';
		          	}
		          echo '</div>'; #award-item-meta\
		         	if($description){
		          	echo '<p class="award-item-body">'.$description.'</p>';
		          }
		        echo '</div>'; #award-item-content
		      echo '</div>'; #award-item
	    	
	    	}

	    echo '</div>'; #awards-list
    }

    if($memberships){
    	$title = $memberships['title'];
    	$content = $memberships['content'];
	    echo '<div class="memberships-item">';
	      echo '<i class="fa-solid fa-handshake"></i>';
	      echo '<div class="data">';
	      	if($title){
	      		echo '<h3>'.$title.'</h3>';
	      	}
	      	if($content){
	        	echo '<p>'.$content.'</p>';
	        }
	      echo '</div>'; #data
	    echo '</div>'; #memberships-item
    }
  echo '</div>'; #container
echo '</section>'; #awards-section


?>
