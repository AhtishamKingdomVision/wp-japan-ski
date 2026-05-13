<?php
$select_post = $section['select_post'];
$teamMembersOption = get_field('team_members' , 'option') ?? [];
$select_auhtor = get_field('select_member', $select_post->ID);
$date = get_field('date', $select_post->ID);
$tag_line = get_field('tag_line', $select_post->ID);
$image = get_field('listing_image', $select_post->ID);
$placeholder = get_template_directory_uri() . '/images/placeholder-featured.jpg';

$link = get_the_permalink($select_post);

echo '<section ' . SectionAttributes($section, 'full-section featured_post ') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		// echo TitleFromSection($section);
		echo '<div class="featured_cover">';

			echo '<div class="left_featured">';
				if($image){
					echo wp_get_attachment_image($image, 'full');
				} else {
					echo '<img src="'.$placeholder.'">';
				}
			echo '</div>'; //left_featured

			echo '<div class="right_featured">';
				if($tag_line){
					printf('<span class="tagline">%s</span>', $tag_line);
				}				
				if(get_the_title($select_post)){
					printf('<h2>%s</h2>', get_the_title($select_post));
				}
	            if($select_auhtor || $date){
	                echo '<div class="postAuthorDate">';
	                    echo getMemberFromSpecialists($select_auhtor , $teamMembersOption, $date);
	                echo '</div>';
	            }
	            echo '<a class="btn" href="'.$link.'">Read More</a>';
			echo '</div>'; //right_featured

		echo '</div>'; //featured_cover

	echo '</div>';
echo '</section>';
?>