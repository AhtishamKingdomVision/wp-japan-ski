<?php

$sub_title    = $section['sub_title'];
$content = $section['content'];
$button = $section['button'];
$bullet_points = $section['bullet_points'];

echo '<section '.SectionAttributes($section, 'full-section how-section').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
    if($sub_title){
			echo '<span class="eyebrow">'.$sub_title.'</span>';
		}
    echo TitleFromSection($section);

    echo '<div class="how-inner">';

      echo '<div class="how-copy">';
        if($content){
      		echo wpautop($content);
      	} 
        if($button){
      		$url = $button['url'];
      		$title = $button['title'];
      		$target = $button['target'] ? $button['target'] : '_self';
      		echo '<a href="'.esc_url($url).'" target="'.esc_attr($target).'" class="btn btn-primary" style="margin-top:8px;">'.esc_html($title).'</a>'; 
      	}
      echo '</div>'; #how-copy

      if($bullet_points){
	      echo '<div class="how-steps">';
	      	$i = 1;
	      	foreach ($bullet_points as $key => $value) {
	      		$title = $value['title'];
	      		$text = $value['text'];
		        
		        echo '<div class="how-step">';
		          echo '<div class="how-step-num">'.$i.'</div>';
		          echo '<div class="how-step-content">';
		          	if($title){
		          		echo '<h3>'.$title.'</h3>';
		          	}
		          	if($text){
		          		echo '<p>'.$text.'</p>';
		          	}
		          echo '</div>'; #how-step-content
		        echo '</div>'; #how-step
	      		$i++;
	      	}
	      echo '</div>'; #how-steps
      }
    echo '</div>'; #how-inner
  echo '</div>'; #container
echo '</section>'; #how-section

?>
