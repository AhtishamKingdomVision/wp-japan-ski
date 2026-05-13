<?php
$boxes 	   = $section['boxes'];
$main_link = $section['main_link'];
$alignment = $section['text_alignment'];

echo '<section class="full-section three_column_boxes" ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo TitleFromSection($section);
		echo '<div class="tcb_cover '. $alignment .'">';
			foreach ($boxes	 as $tcb) {
				echo '<div class="tcb_inner">';
				$image = $tcb['image'];
				$title = $tcb['title'];
				$content = $tcb['content'];
				$button = $tcb['button'];
					echo wp_get_attachment_image($image, 'full');
					if($title):
						printf('<h3>%s</h3>', $title);
					endif;
					echo $content;
					if($button):
					    $link_url = $button['url'];
					    $link_title = $button['title'];
					    $link_target = $button['target'] ? $button['target'] : '_self';
					    echo '<a class="btn" href="'.$link_url.'" target="'.$link_title.'">'.$link_title.'</a>';
					endif;
				echo '</div>'; //tcb_inner
			}
		echo '</div>'; //tcb_cover
			if($main_link):
			    $link_url = $main_link['url'];
			    $link_title = $main_link['title'];
			    $link_target = $main_link['target'] ? $main_link['target'] : '_self';
			    echo '<a class="btn main_btn" href="'.$link_url.'" target="'.$link_title.'">'.$link_title.'</a>';
			endif;
	echo '</div>';
echo '</section>';
?>