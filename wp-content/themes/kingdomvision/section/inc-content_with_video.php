<?php
$description = $section['description'] ?? '';
$points      = $section['bullet_points'] ?? [];
$buttons     = $section['buttons'] ?? [];
$videoimage  = $section['video_or_image'] ?? '';
$video       = $section['video'] ?? [];
$cwv_image   = $section['cwv_image'] ?? '';

echo '<section ' . SectionAttributes($section, 'full-section content-with-video') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo '<div class="cwv-wrap">';

			// LEFT SIDE
			echo '<div class="cwv-left">';
				echo do_shortcode('[company_rating]');
				echo TitleFromSection($section);

				if(!empty($description)){
				    echo '<div class="desc">'. $description .'</div>';
				}

				if(!empty($points) && is_array($points)){
				    echo '<ul class="points">';
				    foreach($points as $row){
				        if(!empty($row['point'])){
				            echo '<li>'. esc_html($row['point']) .'</li>';
				        }
				    }
				    echo '</ul>';
				}

				if(!empty($buttons) && is_array($buttons)){
				    echo '<div class="buttons">';
				    foreach($buttons as $row){
				        $btn = $row['button'] ?? [];
				        if(!empty($btn['url']) && !empty($btn['title'])){
				            echo '<a href="'. esc_url($btn['url']) .'" class="btn" target="'. esc_attr($btn['target'] ?? '_self') .'">'
				                    . esc_html($btn['title']) .
				                 '</a>';
				        }
				    }
				    echo '</div>';
				}
			echo '</div>'; // cwv-left end


			// RIGHT SIDE
			if($videoimage === 'video1' && !empty($video) && is_array($video)){

			    $thumb = $video['video_thumbnail'] ?? '';
			    $type  = $video['select_video'] ?? '';
			    $html5 = $video['html5_video'] ?? '';
			    $yt    = $video['youtube_video_url'] ?? '';
			    $vimeo = $video['vimeo_video_url'] ?? '';
			    $iframeCode = $video['iframe_code'] ?? '';

			    $video_url = '';

			    if($type === 'html5' && !empty($html5['url'])){
			        $video_url = $html5['url'];
			    } elseif($type === 'youtube' && !empty($yt)){
			        $video_url = $yt;
			    } elseif($type === 'vimeo' && !empty($vimeo)){
			        $video_url = $vimeo;
			    }

			    if(!empty($thumb['url']) && !empty($video_url)){
			        echo '<div class="cwv-right">';
			            echo '<a data-fancybox href="'. esc_url($video_url) .'">';
			                echo '<img src="'. esc_url($thumb['url']) .'" alt="">';
			                echo '<span class="play-btn"><i class="fa-regular fa-circle-play"></i></span>';
			            echo '</a>';
			        echo '</div>';
			    }else{
			    	echo '<div class="cwv-right">';
			            echo $iframeCode;
			        echo '</div>';
			    }

			} else {

			    if(!empty($cwv_image)){
			        echo '<div class="cwv-right">';
			            echo wp_get_attachment_image($cwv_image, 'full');
			        echo '</div>';
			    }

			}

		echo '</div>'; // cwv-wrap end
	echo '</div>'; // container end
echo '</section>';
?>