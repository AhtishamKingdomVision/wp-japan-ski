<?php

$multiple_webcam = $section['multiple_webcam'];

echo '<section '.SectionAttributes($section, 'full-section webcam-videos').' '.BackgroundFromSection($section).' aria-label="Webcam Videos">';
	echo '<div class="container">';
			echo TitleFromSection($section);
			if($multiple_webcam){
				echo '<div class="webcamWrapper">';
					foreach ($multiple_webcam as $key => $value) {
						$video_title = $value['video_title'];
						$video_iframe = $value['video_iframe'];

						echo '<div class="itemWrap">';
							if($video_title){
								echo '<h3>'.$video_title.'</h3>';
							}
							if($video_iframe){
								echo '<div class="iframeWrap">';
									echo $video_iframe;
								echo '</div>'; #iframeWrap
							}
						echo '</div>'; #itemWrap

					}
				echo '</div>'; #webcamWrapper
			}
	echo '</div>'; #container
echo '</section>'; #webcam-videos

?>

