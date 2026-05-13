<?php

$sub_title    = $section['sub_title'];
$content = $section['content'];
$button = $section['button'];
$bullet_points = $section['bullet_points'];

echo '<section '.SectionAttributes($section, 'full-section why-section').' '.BackgroundFromSection($section).'>'; 
  echo '<div class="container">'; 
	if($sub_title){
		echo '<span class="eyebrow">'.$sub_title.'</span>';
	}
	echo TitleFromSection($section);
    echo '<div class="why-grid">'; 
      echo '<div class="why-copy">';
      	if($content){
      		echo wpautop($content);
      	} 
      	if($button){
      		$url = $button['url'];
      		$title = $button['title'];
      		$target = $button['target'] ? $button['target'] : '_self';
      		echo '<a href="'.esc_url($url).'" target="'.esc_attr($target).'" class="btn btn-primary" style="margin-top:8px;">'.esc_html($title).'</a>'; 
      	}
      echo '</div>'; #why-copy
      if($bullet_points){
		echo '<div class="why-points">'; 
			foreach($bullet_points as $key => $points){
				$text = $points['text'];
				echo '<div class="why-point"><i class="fa-solid fa-circle-check"></i><p>'.$text.'</p></div>'; 
			}
		echo '</div>'; #why-points
      }
    echo '</div>'; #why-grid
  echo '</div>';  #container
echo '</section>'; #why-section
?>
