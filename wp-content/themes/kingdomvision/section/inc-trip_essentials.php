<?php
$description 	   = $section['description'];
$button = $section['button'];
$icon_with_title   = $section['icon_with_title'];
$right_title   = $section['right_title'];

echo '<section ' . SectionAttributes($section, 'full-section trip-essentials') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo '<div class="trip-essentials-wrap">';
		   echo '<div class="te-left">';
			    echo TitleFromSection($section);
				if(!empty($description)){
				    echo '<div class="desc">'. $description .'</div>';
				}
				if($button){
			        echo '<a href="'. esc_url($button['url']) .'" class="btn" target="'. $button['target'] .'">'
			                . esc_html($button['title']) .
			             '</a>';
				}
		   echo '</div>';
		   echo '<div class="te-right">';
		       if($right_title){
		       	echo '<h3>'. $right_title .'</h3>';
		       }
			   if($icon_with_title){
				    echo '<div class="icon-with-title">';
					    foreach($icon_with_title as $row){
					        $title = $row['title'];
					        $icon_image = $row['icon_image'];
					        echo '<span>';
					           echo '<img src="'. esc_url($icon_image['url']) .'" alt="">';
					           echo '<h5>'. esc_html($row['title']) .'</h5>';
					        echo '</span>';
					    }
				    echo '</div>';
				}
		   echo '</div>';
		echo '</div>';
	echo '</div>';
echo '</section>';
?>