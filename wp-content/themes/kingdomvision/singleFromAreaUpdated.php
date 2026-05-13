<?php
global $post;

$acc_details = get_field('accomodation_details');
$acc_form = $acc_details['accommodation_form'] ?? false;
$address = $acc_details['address'] ?? '';
// $acco_image_gallery = get_field('acco_image_gallery');
$acco_image_gallery = kv_get_meta_images_gallery($post->ID, 'acco_pending_images');
$acc_ext_gallary = get_field('acc_gallary', $post->ID);
// @$_GET['gall'] == 'yes' ? pre($acco_image_gallery,1) : '';
$merged_gallary = [];

if( ( isset( $acc_ext_gallary ) && is_array( $acc_ext_gallary ) ) ){
	$merged_gallary = array_merge((array)$acco_image_gallery, (array)$acc_ext_gallary);
	}
else{
	$merged_gallary = !empty( $acco_image_gallery ) ? $acco_image_gallery : [];

}
// @$_GET['gall'] == 'yes' ? pre($merged_gallary) : '';
$bg_color   = $acc_details['bg_color'] ?? '#01111f';
$section_style = $bg_color ? 'style="background-color:' . esc_attr($bg_color) . ';"' : 'xyz';
$ff_image     = get_field('form_footer_image', 'option');
$ff_content   = get_field('form_footer_content', 'option');
echo '<section class="form_area acc-single-banner accSingleBannerUpdate full-section" '. $section_style .' aria-labelledby="acc-title">';
	echo '<div class="container">';
		echo '<div class="left-side">';
			echo '<a class="btn desktop-none quote_toggle" href="javascript:void();">Get a Quote</a>';

			if (function_exists('yoast_breadcrumb')) {
			    $links = apply_filters('wpseo_breadcrumb_links', []);
			    if (!empty($links) && count($links) > 1) {
			        $parent = $links[count($links) - 2];
			        $parentText = $links[count($links) - 3];
			        if (!empty($parent['url']) && !empty($parent['text'])) {

			            echo '<div class="backToResultsWrap">';
			                echo '<a href="' . esc_url($parent['url']) . '" class="backToResults">';
			                    echo '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
			                            <polyline points="15 18 9 12 15 6"></polyline>
			                          </svg> Back to ' . esc_html($parentText['text']) .' results';
			                echo '</a>';
			            echo '</div>'; #backToResultsWrap
			        }
			    }
			}

			echo '<div class="title-wrapper">';
				echo '<h1>'. get_the_title() .'</h1>';
			echo '</div>'; #title-wrapper
			
			if($address){
				echo '<div class="acc-address">';
					echo '<p>'.$address.'</p>';
				echo '</div>'; #acc-address
			}

			// if(is_array($acco_image_gallery)){
			// 	$galleryCounts = count($acco_image_gallery) ?? '';
			// 	echo '<div class="accGallerySlider '.($galleryCounts > 3 ? 'activeSliderX' : '').'">';
			// 		foreach($acco_image_gallery as $key => $image_id){
			// 			echo '<div class="accGalleryItem" >';
			// 				echo '<a href="javascript:;" data-fancybox="accGallery" data-src="'.wp_get_attachment_image_url( $image_id, 'full').'">';
			// 					echo wp_get_attachment_image( $image_id, 'full' );
			// 				echo '</a>';
			// 			echo '</div>'; #accGalleryItem
			// 		}
			// 	echo '</div>'; #accGallerySlider
			// }

			echo '<div class="acc-gallery t2">';
			if($merged_gallary){
				// pre($merged_gallary,1);
				$count = count($merged_gallary);

				// foreach( $merged_gallary as $key => $image_id ){
				// 	echo '<div class="galleryItem" data-fancybox="gallery" data-src="'.wp_get_attachment_image_url( $image_id, 'full').'">';
				// 		echo wp_get_attachment_image( $image_id, 'full' );
				// 	echo '</div>'; #galleryItem
				// 	if($key == 4){
				// 		echo '<span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> All Photos ('.$count.')</span>';
				// 	}
				// }

				foreach( $merged_gallary as $key => $url ){
					$url = esc_url((string) $url);
					if (empty($url)) {
						continue;
					}
					echo '<div class="galleryItem" data-fancybox="gallery" data-src="'. $url .'">';
						// <img width="1280" height="852" src="https://jsnew.japanskiexperience.com/wp-content/uploads/2026/04/2c98902a50a6076e0150b6d1d6f0651d_niseko_hirafu_420317.jpg" class="attachment-full size-full" alt="" decoding="async" fetchpriority="high" />
						echo '<img src="'. $url .'" class="t2 attachment-full size-full" loading="lazy" decoding="async" fetchpriority="high" />';
					echo '</div>'; #galleryItem
					if($key == 4){
						echo '<span><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg> All Photos ('.$count.')</span>';
					}
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
	echo '</div>'; #container
echo '</section>'; #acc-single-banner

?>

<script>
	jQuery(document).ready(function($) {
		$('.accGallerySlider.activeSliderX').slick({
		  dots: false,
		  arrows: true,
		  infinite: true,
		  speed: 300,
		  slidesToShow: 3,
		  slidesToScroll: 1,
		  prevArrow: '<button type="button" class="slick-prev"><img src="<?php echo THEME_URL ?>/images/left_arrow.svg" alt="Previous"></button>',
          nextArrow: '<button type="button" class="slick-next"><img src="<?php echo THEME_URL ?>/images/right_arrow.svg" alt="Next"></button>',
		  responsive: [
		    {
		      breakpoint: 1024,
		      settings: {
		        slidesToShow: 3,
		        slidesToScroll: 1,
		      }
		    },
		    {
		      breakpoint: 991,
		      settings: {
		        slidesToShow: 2,
		        slidesToScroll: 1,
		      }
		    },
		    {
		      breakpoint: 768,
		      settings: {
		        slidesToShow: 1,
		        slidesToScroll: 1
		      }
		    }
		  ]
		});

		// ================================ accGallery =====================
	    $('[data-fancybox="accGallery"]').fancybox({
	      selector: '.slick-slide:not(.slick-cloned) a'
	    });
	    $('.slick-slider').on('init', function(event, slick) {
	      $('.slick-cloned a').removeAttr('data-fancybox');
	    });
	    // ================================ accGallery =====================
	})
</script>