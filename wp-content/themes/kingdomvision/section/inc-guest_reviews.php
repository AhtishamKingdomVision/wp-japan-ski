<?php

$sub_title    = $section['sub_title'];
$verification_lists = $section['verification_lists'];
$reviews_content = $section['reviews_content'];
$reviews_lisitng = $section['reviews_lisitng'];

echo '<section '.SectionAttributes($section, 'full-section reviews-section').'  '.BackgroundFromSection($section).' >';
	echo '<div class="container">'; 
		if($sub_title){
			echo '<span class="eyebrow">'.$sub_title.'</span>';
		}
	    echo TitleFromSection($section);

    	echo '<div class="reviews-grid">';
	    	if($verification_lists){
		      	echo '<div class="reviews-stats">';
			        foreach ($verification_lists as $key => $verification) {
			        	$number = $verification['number'];
			        	$label = $verification['label'];
				        echo '<div class="stat-row">';
				          echo '<div class="stat-number">'.$number.'</div>';
				          echo '<div class="stat-label">'.$label.'</div>';
				        echo '</div>'; #stat-row
			        }
		       	echo '</div>'; #stat-row
	    	}

	      	echo '<div class="unknow">';
	      		if($reviews_content){
		        	echo '<p class="reviews-copy">'.$reviews_content.'</p>';
	      		}
	      		if($reviews_lisitng){
			        echo '<div class="review-cards">';
				        foreach ($reviews_lisitng as $key => $reviews) {
				        	$review_text = $reviews['review_text'];
				        	$namedate = $reviews['namedate'];
				          	echo '<div class="review-card">';
				            	if($review_text){
				            		echo '<div class="review-card-stars">★★★★★</div>';
				            		echo '<p class="review-card-text">'.$review_text.'</p>';
				            	}
				            	if($namedate){
				            		echo '<div class="review-card-author">'.$namedate.'</div>';
				            	}
				          	echo '</div>';
			          	}
			     	echo '</div>';
		      	}
		    echo '</div>'; #unknow
		echo '</div>'; #reviews-grid
	echo '</div>'; #container
echo '</section>'; #reviews-section


?>
