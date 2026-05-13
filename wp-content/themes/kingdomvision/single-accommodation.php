<?php get_header(); 
$header_option = get_field('header_option');
?>

<div class="content-wrapper full-section accommodation <?php echo $header_option; ?>">
<?php
	get_template_part('singleFromAreaUpdated');

	/** Rooms section */
	// get_template_part( 'partials/rooms-section' );

	/** Rooms section */
	if( get_field('accommodation_builder') ){
		$accommodation_builder = get_field('accommodation_builder');
		foreach ($accommodation_builder as $key => $section) {
			include('section/accommodation/inc-'.$section['acf_fc_layout'].'.php');
		}
	} else {
		echo '<div class="container-acc" style="text-align:center;">';
			echo '<h2 style="margin:20px 0 20px;display:inline-block;">';
				// echo 'Fields Not Founds';
			echo '</h2>';
		echo '</div>';	
	} 
	?>
</div>
<?php get_footer(); ?>