<?php
$description 	   = $section['description'];
$left  = $section['left_column'];
$right = $section['right_column'];

echo '<section ' . SectionAttributes($section, 'full-section easy-bookings') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo TitleFromSection($section);
		if(!empty($description)){
		    echo '<div class="desc">'. $description .'</div>';
		}
		echo '<div class="easy-bookings-wrap">';
		foreach (['left' => $left, 'right' => $right] as $side => $col) {
		    if(empty($col)) continue;
		    $icon        = $col['icon_image'];
		    $recommended = $col['recommended'];
		    $title       = $col['title'];
		    $points      = $col['bullet_points'];
		    $button      = $col['button'];	
		    $end_text    = $col['end_text'];

		    echo '<div class="easy-box '. $side .'">';
		        if($recommended){
		            echo '<span class="recommended">Recommended</span>';
		        }
		        if($icon){
		            echo '<div class="icon"><img src="'. esc_url($icon['url']) .'" alt=""></div>';
		        }
		        if($title){
		            echo '<h3>'. esc_html($title) .'</h3>';
		        }
		        if($points){
		            echo '<ul class="points">';
		            foreach($points as $row){
		                echo '<li>'. esc_html($row['point']) .'</li>';
		            }
		            echo '</ul>';
		        }
		        if($button){
		            echo '<a href="'. esc_url($button['url']) .'" class="btn">'. esc_html($button['title']) .'</a>';
		        }
		        if($end_text){
		            echo '<div class="end-text">'. esc_html($end_text) .'</div>';
		        }
		    echo '</div>';
		}
		echo '</div>';
	echo '</div>';
echo '</section>';
?>