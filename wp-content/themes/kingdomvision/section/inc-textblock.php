<?php
$content = $section['content'];
echo '<section ' . SectionAttributes($section, 'full-section textblock') . ' ' . BackgroundFromSection($section) . '>';
	echo '<div class="container">';
		echo TitleFromSection($section);
		echo $content;
	echo '</div>';
echo '</section>';
?>