<?php

$filter = $section['filter'];
$intro_text = $section['intro_text'];
$resort_names = $section['resort_names'];
$trust_bullets = $section['trust_bullets'];
$trust_bar = $section['trust_bar'];

echo '<section '.SectionAttributes($section, 'full-section hero-banner-with-filter').' '.BackgroundFromSection($section).' aria-label="Japan ski accommodation search">';
  // echo '<div class="hero-bg" role="presentation"></div>';

  echo '<div class="hero-inner">';

    echo TitleFromSection($section);

    if($filter == true){
	  	echo '<div class="resortFilterWrap">';
	      echo do_shortcode('[newResortFilters]');
	    echo '</div>'; # /resortFilterWrap
    }

    # Reviews row
    echo '<div class="reviews-row">';
      echo do_shortcode('[company_rating]');
    echo '</div>';

    if($intro_text){
	    # Intro text
	    echo '<div class="hero-intro">';
	      echo '<p class="hero-intro-line1">'.$intro_text.'</p>';
	    echo '</div>';
    }

    if($resort_names){
	    # Resort names
	    echo '<p class="hero-resorts">'.$resort_names.'</p>';
    }

    if($trust_bullets){
    	# Trust bullets 
	    echo '<div class="hero-trust" role="list">';
	    	foreach ($trust_bullets as $key => $bullets) {
	    		$item = $bullets['item'];
	      	echo '<span class="ht-item" role="listitem">'.$item.'</span>';
	    	}
	    echo '</div>';
    }

    echo '</div>';
echo '</section>';

if($trust_bar){
	# Trust bar 
	echo '<section class="full-section trust-bar">';
	  echo '<div class="container">';
		  echo '<div class="trust-bar-inner">';
		  	foreach ($trust_bar as $key => $bar) {
		  		$icon = $bar['icon'];
		  		$text = $bar['text'];
			    echo '<div class="tb-item">';
			      echo $icon . $text;
			    echo '</div>';
		  	}
		  echo '</div>'; #trust-bar-inner
	  echo '</div>'; #container
	echo '</section>'; #trust-bar

}

// echo ' <section class="full-section hero-banner-filter-result">';
//   echo '<div class="container">';
//   	echo '<p class="results-count">';
// 	    echo "<strong>1,500+ properties</strong> across Japan's top ski resorts";
// 	  echo '</p>'; #results-count

// 	  echo '<div class="property-grid">';
// 	    echo '<div class="card">';
// 	      echo '<div class="card-image">🏔</div>'; #card-image
// 	      echo '<div class="card-body">';
// 	        echo '<p class="tag">Ski-in Ski-out · Niseko</p>'; #tag
// 	        echo '<p class="title">Powder Ridge Chalet</p>'; #title
// 	        echo '<p class="price">¥52,000 <span>/ night</span></p>'; #price
// 	      echo '</div>'; #card-body
// 	    echo '</div>'; #card

// 	    echo '<div class="card">';
// 	      echo '<div class="card-image">🏨</div>'; #card-image
// 	      echo '<div class="card-body">';
// 	        echo '<p class="tag">Hotel · Hakuba</p>'; #tag
// 	        echo '<p class="title">Happo Village Hotel</p>'; #title
// 	        echo '<p class="price">¥24,000 <span>/ night</span></p>'; #price
// 	      echo '</div>'; #card-body
// 	    echo '</div>'; #card

// 	    echo '<div class="card">';
// 	      echo '<div class="card-image">🏠</div>'; #card-image
// 	      echo '<div class="card-body">';
// 	        echo '<p class="tag">Apartment · Rusutsu</p>'; #tag
// 	        echo '<p class="title">Forest View Apartment</p>'; #title
// 	        echo '<p class="price">¥18,000 <span>/ night</span></p>'; #price
// 	      echo '</div>'; #card-body
// 	    echo '</div>'; #card
// 	  echo '</div>'; #property-grid

// 	  echo '<div class="load-more-wrapper">';
// 	    echo '<button class="load-more-btn">Load more properties</button>';
// 	  echo '</div>'; #load-more-wrapper
//   echo '</div>'; #container
// echo '</section>'; #hero-banner-filter-result

?>

