<?php
$cta_content = get_field('cta_content','option');
$button 	 = get_field('cta_button','option');
$options 	 = $section['options'];
$content 	 = $section['content'];

if($options == true){
	$custom_cta = 'custom_cta';
} else {
	$custom_cta = '';	
}
echo '<section ' . SectionAttributes($section, 'full-section newslatter') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo '<div class="newslatter_inn '. $custom_cta .'">';
			if($options == true){
				echo TitleFromSection($section);
				echo $content;
			} else {
				echo $cta_content;
			}
			echo do_shortcode('[gravityform id="2" title="false" ajax="true"]');
		echo '</div>'; //newslatter_inn
	echo '</div>';
echo '</section>';
?>