<?php
$gallery = $section['gallery'];
$content_area = $section['content_area'];
$button = $section['button'];
$gp = $section['gallery_position'];
$gallery_carousel = ( !empty($gallery) && count($gallery) > 1 ) ? ' gallery_carousel' : '';

if( !empty( $gallery ) ){

	echo '<section ' . SectionAttributes($section, 'full-section image_with_gallery') . ' ' . BackgroundFromSection($section) . '>';
		echo '<div class="container">';
			echo '<div class="iwg_cover '. $gp. '">';
				echo '<div class="iwg_gallery '. $gallery_carousel.'">';
					foreach ($gallery as  $g) {
						$images = $g['images'];
						echo wp_get_attachment_image($images, 'full');
					}
				echo '</div>'; //iwg_gallery

				echo '<div class="iwg_content">';
					echo TitleFromSection($section);
					if($content_area){ printf('<div class="content_area">%s</div>', $content_area); }
					if($button):
					    $link_url = $button['url'];
					    $link_title = $button['title'];
					    $link_target = $button['target'] ? $button['target'] : '_self';
					    echo '<a class="btn" href="'.$link_url.'" target="'.$link_target.'">'.$link_title.'</a>';
					endif;
				echo '</div>'; //iwg_content
			echo '</div>'; //iwg_cover
		echo '</div>';
	echo '</section>';
}