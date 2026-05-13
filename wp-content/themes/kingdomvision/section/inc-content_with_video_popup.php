<?php
$title 			= $section['title'];
$options  		= $section['button_action'];
$link 			= $section['button'];
$btn_label		= $section['btn_label'];
$more_con		= $section['more_content'];
$content 		= $section['content'];
$option 		= $section['video_image_option'];
$video_thumbnail = $section['video_thumbnail'];
$upload_opt		= $section['upload_or_url'];
$video_url 		= $section['video_url'];
$video_upload	= $section['video_upload'];
$party_video	= $section['party_video'];
$cp_position	= $section['content_position'];
$available_tag	= $section['available_tag'];

$image = $section['image'];
echo '<section ' . SectionAttributes($section, 'full-section content_with_video_popup') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo '<div class="cwvp_inner '.$cp_position.'">';
			echo '<div class="cwvp_content">';

				if($available_tag){
					echo '<div class="cwvp-avail-tag">';
						echo $available_tag;
					echo '</div>'; #cwvp-avail-tag
				}
				
				echo TitleFromSection($section);

				echo '<div class="cwvp-content">';
					echo $content;
				echo '</div>'; #cwvp-content

				if($options == 'popup'){
					echo '<div class="more_text" style="display: none;" id="hidden-content">';
						//echo $content;
						echo $more_con;
					echo '</div>';
				}
				if($options == 'link'){
					if($link):
						$link_url = $link['url'];
						$link_title = $link['title'];
						$link_target = $link['target'] ? $link['target'] : '_self';
						echo sprintf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title);
					endif;
				} elseif ($options == 'popup') {
						echo sprintf('<a class="btn light_green" href="javascript:;" data-fancybox data-src="#hidden-content">%s</a>', $btn_label);
				}
			echo '</div>'; //cwvp_content

			if($option == 'image'){
				echo '<div class="cwvp_video" style="border-color: #5a5a5a;">';
					echo '<img src="'.$image.'" />';
				echo '</div>';
			} else{
				echo '<div class="cwvp_video" >';
					if($upload_opt == 'upload'){
						echo '<a class="cwvp_fancybox" data-fancybox href="'.$video_upload.'">';
							echo '<img src="'.esc_url($video_thumbnail['url']).'" alt="'.esc_attr($video_thumbnail['alt']).'"/>';
						echo '</a>';
					} elseif ($upload_opt == 'url') {
						echo '<a class="cwvp_fancybox" data-fancybox href="'.$video_url.'">';
							echo '<img src="'.esc_url($video_thumbnail['url']).'" alt="'.esc_attr($video_thumbnail['alt']).'"/>';
						echo '</a>';
					} elseif ($upload_opt == 'ex_video') {
						echo $party_video;
					}
				echo '</div>'; //cwvp_video
			}
			
		echo '</div>'; //cwvp_inner
	echo '</div>';
echo '</section>';
?>