<?php
$left  = $section['left_column'];
$right = $section['right_column'];
echo '<section ' . SectionAttributes($section, 'full-section get-to-niseko') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo '<div class="gtn-wrap">';
		/* LEFT COLUMN */
		if($left){
		    $ltitle = $left['title'];
		    $ldesc  = $left['description'];
		    $lbtn   = $left['button'];
		    $ldesc2 = $left['description_2'];
		    echo '<div class="gtn-left">';
		        if($ltitle){
		            echo '<h2>'. esc_html($ltitle) .'</h2>';
		        }
		        if($ldesc){
		            echo '<div class="desc">'. wp_kses_post($ldesc) .'</div>';
		        }
		        if($lbtn){
		            echo '<a href="'. esc_url($lbtn['url']) .'" class="btn" target="'. $lbtn['target'] .'">'
		                    . esc_html($lbtn['title']) .
		                 '</a>';
		        }
		        if($ldesc2){
		            echo '<div class="desc-2">'. wp_kses_post($ldesc2) .'</div>';
		        }
		    echo '</div>';
		}

		/* RIGHT COLUMN */
		if($right){
		    echo '<div class="gtn-right">';
		    foreach($right as $row){
		        $icon  = $row['icon_image'];
		        $title = $row['title'];
		        $desc  = $row['description'];
		        echo '<div class="gtn-info-box">';
		            if($icon){
		                echo '<div class="icon">
		                        <img src="'. esc_url($icon['url']) .'" alt="">
		                      </div>';
		            }
		            if($title){
		                echo '<h3>'. esc_html($title) .'</h3>';
		            }
		            if($desc){
		                echo '<p>'. esc_html($desc) .'</p>';
		            }
		        echo '</div>';
		    }
		    echo '</div>';
		}
		echo '</div>';
	echo '</div>';
echo '</section>';
?>