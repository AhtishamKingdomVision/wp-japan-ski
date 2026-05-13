<?php 
global $post;
$acc_details = get_field('accomodation_details');
$acc_form = $acc_details['accommodation_form'] ?? false;
$address = $acc_details['address'] ?? '';
// $acco_image_gallery = get_field('acco_image_gallery');
$acco_image_gallery = kv_get_meta_images_gallery($post->ID, 'acco_pending_images');
$bg_color   = $acc_details['bg_color'] ?? '#01111f';
$section_style = $bg_color ? 'style="background-color:' . esc_attr($bg_color) . ';"' : 'xyz';
$ff_image     = get_field('form_footer_image', 'option');
$ff_content   = get_field('form_footer_content', 'option');
echo '<section class="form_area acc-single-banner full-section" '. $section_style .' aria-labelledby="acc-title">';
	echo '<div class="container">';
		echo '<div class="left-side">';
			echo '<a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>';
			echo '<div class="title-wrapper">';
				echo '<h1>'. get_the_title() .'</h1>';
			echo '</div>'; #title-wrapper
			echo '<div class="single_review">';
				echo do_shortcode('[sku_reviews]');
			echo '</div>'; //single_review
			if($address){
				echo '<div class="acc-address">';
					echo '<p>'.$address.'</p>';
				echo '</div>'; #acc-address
			}
				echo '<div class="acc-gallery">';
				if($acco_image_gallery){
					$count = count($acco_image_gallery);
					// foreach( $acco_image_gallery as $key => $image_id ){
					// 	echo '<div class="galleryItem" data-fancybox="gallery" data-src="'.wp_get_attachment_image_url( $image_id, 'full').'">';
					// 		if($key == 0){
					// 			echo '<span>All Photos ('.$count.')</span>';
					// 		}
					// 		echo wp_get_attachment_image( $image_id, 'full' );
					// 	echo '</div>'; #galleryItem
					// }

					foreach( $acco_image_gallery as $key => $url ){
						$url = esc_url((string) $url);
						if (empty($url)) {
							continue;
						}
						echo '<div class="galleryItem" data-fancybox="gallery" data-src="'. $url .'">';
							if($key == 0){
								echo '<span>All Photos ('.$count.')</span>';
							}
							echo '<img src="'. $url .'" class="attachment-full size-full" loading="lazy" decoding="async" fetchpriority="high" />';
							// <img width="1280" height="852" src="https://jsnew.japanskiexperience.com/wp-content/uploads/2026/04/2c98902a50a6076e0150b6d1d6f0651d_niseko_hirafu_420317.jpg" class="attachment-full size-full" alt="" decoding="async" fetchpriority="high" />
						echo '</div>'; #galleryItem
					}
				}
				else{
					$room_placeholder_img = get_stylesheet_directory_uri() . '/images/placeholder-listing.jpg';
					echo '<div class="galleryItem" data-fancybox="gallery" data-src="'.$room_placeholder_img.'">';
					echo '<img src="'.$room_placeholder_img.'">';
					echo '</div>'; #galleryItem
				}
				echo '</div>'; #acc-gallery


		echo '</div>'; #left-side
		if($acc_form == false){
			echo '<div class="right-side">';
				echo '<a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>';
				echo '<div class="mob_quote_form">';
					echo '<div class="mob_quote_inner">';
						echo '<a class="btn desktop-none close_mob_quote_form" href="javascript:void();"><i class="fa-solid fa-x"></i></a>';
						echo do_shortcode('[gravityform id="1" title="true"]');
					echo '</div>'; //mob_quote_inner
				echo '</div>'; //mob_quote_form
                if ($ff_image && $ff_content) {
                    echo '<div class="ff_content_image">';
                        echo '<div class="ff_image">';
                            echo wp_get_attachment_image($ff_image, 'full');
                        echo '</div><!-- ff_image -->';

                        echo '<div class="ff_content">';
                            echo wp_kses_post($ff_content);
                        echo '</div><!-- ff_content -->';
                    echo '</div><!-- ff_content_image -->';
                }
			echo '</div>'; #right-side
		}
	echo '</div>'; #container
echo '</section>'; #acc-single-banner

?>