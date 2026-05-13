<?php
	$description        = $section['description'];
	$image              = $section['image'];
	$video_thumbnail    = $section['video_thumbnail'];
	$youtube_url        = $section['youtube_url'];
	$vimeo_url          = $section['vimeo_url'];
	$html_5_video       = $section['html_5_video'];
	$select_option      = $section['select_option'];

	echo '<section ' . SectionAttributes($section, 'full-section simple-content') . ' ' . BackgroundFromSection($section) . '>';
		echo '<div class="container">';
			echo '<div class="simple-content-wrap">';

				echo TitleFromSection($section);

				if (!empty($description)) {
				    echo '<div class="desc">' . $description . '</div>';
				}

				/* ================= MEDIA SECTION ================= */

				echo '<div class="media-wrap">';

					/* IMAGE FIRST */
					if ($select_option === 'image' && !empty($image)) {

					    echo '<div class="image-wrap">';
					        echo '<img src="' . esc_url($image['url']) . '" alt="">';
					    echo '</div>';

					}

					/* YOUTUBE */
					elseif ($select_option === 'youtube' && !empty($youtube_url)) {

					    $thumb = !empty($video_thumbnail['url']) ? $video_thumbnail['url'] : $image['url'];

					    echo '<a href="' . esc_url($youtube_url) . '" data-fancybox class="video-thumb">';
					        echo '<img src="' . esc_url($thumb) . '" alt="">';
					        echo '<i class="fa-solid fa-circle-play"></i>';
					    echo '</a>';

					}

					/* VIMEO */
					elseif ($select_option === 'vimeo' && !empty($vimeo_url)) {

					    $thumb = !empty($video_thumbnail['url']) ? $video_thumbnail['url'] : $image['url'];

					    echo '<a href="' . esc_url($vimeo_url) . '" data-fancybox class="video-thumb">';
					        echo '<img src="' . esc_url($thumb) . '" alt="">';
					        echo '<i class="fa-solid fa-circle-play"></i>';
					    echo '</a>';

					}

					/* HTML5 */
					elseif ($select_option === 'html5' && !empty($html_5_video['url'])) {

					    $thumb = !empty($video_thumbnail['url']) ? $video_thumbnail['url'] : $image['url'];

					    echo '<a href="' . esc_url($html_5_video['url']) . '" 
					             data-fancybox 
					             data-type="video"
					             class="video-thumb">';
					        echo '<img src="' . esc_url($thumb) . '" alt="">';
					        echo '<i class="fa-solid fa-circle-play"></i>';
					    echo '</a>';

					}

				echo '</div>'; // media-wrap

			echo '</div>';
		echo '</div>';
	echo '</section>';
?>