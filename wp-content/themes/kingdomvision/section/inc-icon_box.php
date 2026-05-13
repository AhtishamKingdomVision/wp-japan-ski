<?php
$boxes = $section['icon_box_data'];
echo '<section ' . SectionAttributes($section, 'full-section icon-box') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		if($boxes){
		    echo '<div class="icon-box-wrap">';
		    foreach($boxes as $box){
		        $icon  = $box['icon_image'];
		        $title = $box['title'];
		        $desc  = $box['description'];
		        $btn   = $box['button'];
		        echo '<div class="icon-box">';
		            if($icon){
		                echo '<div class="icon"><img src="'. esc_url($icon['url']) .'" alt=""></div>';
		            }
		            if($title){
		                echo '<h3>'. esc_html($title) .'</h3>';
		            }
		            if($desc){
		                echo '<p>'. esc_html($desc) .'</p>';
		            }
		            if($btn){
		                echo '<a href="'. esc_url($btn['url']) .'" class="btn" target="'. $btn['target'] .'">'
		                        . esc_html($btn['title']) .
		                     '</a>';
		            }
		        echo '</div>';
		    }
		    echo '</div>';
		}

	echo '</div>';
echo '</section>';
?>