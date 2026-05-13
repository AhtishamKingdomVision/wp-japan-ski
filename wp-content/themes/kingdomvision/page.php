<?php get_header();
$header_option = get_field('header_option');
 ?>
<div class="content-wrapper full-section <?php echo $header_option; ?>">
<?php
	get_template_part('formarea');
?>	
<?php

	if( get_field('page_builder') ){
		$page_builder = get_field('page_builder');
		foreach ($page_builder as $key => $section) {
			include('section/inc-'.$section['acf_fc_layout'].'.php');
		}
	} else {
		echo '<div class="container" style="text-align:center;">';
			echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
				// echo 'Fields Not Founds';
			echo '</h2>';
		echo '</div>';	
	} 
	?>
</div>
<?php get_footer(); ?>