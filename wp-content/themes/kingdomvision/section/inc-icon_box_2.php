<?php
$boxes = $section['icon_box_data'];
echo '<section ' . SectionAttributes($section, 'full-section icon-box-2') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		if($boxes){
		    echo '<div class="icon-box-wrap-2">';
		    foreach($boxes as $box){
		        $icon  = $box['icon_image'];
		        $title = $box['title'];
		        $desc  = $box['description'];
		        $bp   = $box['bullet_points'];
		        echo '<div class="icon-box-2">';
		            if($icon){
		                echo '<div class="icon-2"><img src="'. esc_url($icon['url']) .'" alt=""></div>';
		            }
		            if($title){
		                echo '<h3>'. esc_html($title) .'</h3>';
		            }
		            if($desc){
		                echo '<p>'. esc_html($desc) .'</p>';
		            }
		            if($bp){
		                echo '<ul class="bullet-points">';
						    foreach($bp as $row){
						        echo '<li><i class="fa-regular fa-circle-check"></i>'. esc_html($row['point']) .'</li>';
						    }
					    echo '</ul>';
			        }
		        echo '</div>';
		    }
		    echo '</div>';
		}

	echo '</div>';
echo '</section>';
?>