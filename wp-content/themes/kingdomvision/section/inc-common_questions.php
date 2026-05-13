<?php

$sub_title    = $section['sub_title'];
$content = $section['content'];
$button = $section['button'];
$faqs = $section['faqs'];

echo '<section '.SectionAttributes($section, 'full-section faq-section').' '.BackgroundFromSection($section).'>';
  echo '<div class="container">';
    echo '<div class="faq-wrap">';

      echo '<div class="faq-sidebar">';
        if($sub_title){
					echo '<span class="eyebrow">'.$sub_title.'</span>';
				}
        echo TitleFromSection($section);
        if($content){
      		echo wpautop($content);
      	} 
        if($button){
      		$url = $button['url'];
      		$title = $button['title'];
      		$target = $button['target'] ? $button['target'] : '_self';
      		echo '<a href="'.esc_url($url).'" target="'.esc_attr($target).'" class="btn btn-primary" style="margin-top:8px;">'.esc_html($title).'</a>'; 
      	}
      echo '</div>'; #faq-sidebar

      if($faqs){
	      echo '<div class="faq-items">';
	      	foreach ($faqs as $key => $faq) {
	      		$sub_title = $faq['sub_title'];
	      		$question = $faq['question'];
	      		$answer = $faq['answer'];

	      		if($sub_title){
	      			echo '<div class="faq-group-label">'.$sub_title.'</div>';
	      		}
	      		if($question || $answer){
			        echo '<div class="faq-item">';
			        	if($question){
			        		echo '<div class="faq-q">'.$question.'</div>';
			        	}
			        	if($answer){
			        		echo '<div class="faq-a">'.$answer.'</div>';
			        	}
		        	echo '</div>';
	      		}
	      	
	      	}
	      echo '</div>'; #faq-items
      }
    echo '</div>'; #faq-wrap
  echo '</div>'; #container
echo '</section>'; #faq-section

?>
