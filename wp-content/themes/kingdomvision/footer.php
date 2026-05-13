<?php
$footer_logo         = get_field('footer_logo', 'option');
$footer_content      = get_field('footer_content', 'option');
$menu                = get_field('menu', 'option');
$mini_content        = get_field('mini_content', 'option');
$copyright           = get_field('copyright', 'option');
$footer_bottom_logo  = get_field('footer_bottom_logo', 'option');

echo '<footer class="footer_main full-section" role="contentinfo">';

    echo '<div class="container">';
        echo '<div class="footer_inner">';

            // LEFT COLUMN CONTENT
            echo '<div class="footer_content foo_col">';

                if ( $footer_logo ) {
                    echo wp_get_attachment_image($footer_logo,'full',false,array('alt' => esc_attr( get_bloginfo('name') . ' footer logo' )));
                }

                if ( ! empty($footer_content) ) {
                    echo wp_kses_post( $footer_content );
                }

            echo '</div>'; // footer_content

            // FOOTER MENU
            echo '<div class="footer_menu foo_col">';

                if ( ! empty($menu) && is_array($menu) ) {

                    foreach ( $menu as $value ) {

                        $menu_title = $value['menu_title'] ?? '';
                        $menu_links = $value['menu_link'] ?? [];

                        echo '<div class="menu_col">';

                            if ( $menu_title ) {
                                echo '<h2>' . esc_html( $menu_title ) . '</h2>';
                            }

                            echo '<ul>';

                                if ( is_array($menu_links) ) {
                                    foreach ( $menu_links as $item ) {

                                        $mlink        = $item['mlink'] ?? [];
                                        $link_url     = esc_url( $mlink['url'] ?? '#' );
                                        $link_title   = esc_html( $mlink['title'] ?? '' );
                                        $link_target  = esc_attr( $mlink['target'] ?? '_self' );

                                        echo '<li><a href="' . $link_url . '" target="' . $link_target . '">' . $link_title . '</a></li>';
                                    }
                                }

                            echo '</ul>';

                        echo '</div>';
                    }
                }

            echo '</div>'; // footer_menu

        echo '</div>'; // footer_inner
    echo '</div>';

    // COPYRIGHT AREA
    echo '<div class="copyright">';
		echo '<div class="container">';
		    echo '<div class="left_content">';

		        if ( ! empty($copyright) ) {
		            echo '<p>' . esc_html( $copyright ) . '</p>';
		        }

		    echo '</div>';

		    echo '<div class="right_content">';

		        if (!empty($footer_bottom_logo) && is_array($footer_bottom_logo)){
		        	echo '<div class="f_logo">';
			        	foreach ($footer_bottom_logo as $value) {
			        		$flogo = $value['flogo'];
			                echo wp_get_attachment_image($flogo,'full',false,array('alt' => esc_attr( get_bloginfo('name') . ' footer bottom logo' )));
			        	}
		        	echo '</div>'; //f_logo
		        }
		        if ( ! empty($mini_content) ) {
		            echo '<p>' . esc_html( $mini_content ) . '</p>';
		        }


		    echo '</div>';
		echo '</div>';
    echo '</div>'; //copyright

echo '</footer>';
// child age popup
    include('child-age.php');
// child age popup End
?>

<style>
    .sticky-cta-container {
        position: fixed;
        bottom: 30px;
        right: 30px;
        z-index: 9999;
    }
    .sticky-cta-btn {
        background-color: #001C44;
        color: #ffffff !important;
        padding: 14px 32px;
        border-radius: 50px;
        text-decoration: none !important;
        font-weight: 700;
        font-size: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        text-transform: uppercase;
        letter-spacing: 0.8px;
        transition: all 0.3s ease;
    }
    .sticky-cta-btn:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 25px rgba(0,0,0,0.3);
        background-color: #002a66;
    }
    @media (max-width: 767px) {
        .sticky-cta-container {
            bottom: 0;
            right: 0;
            left: 0;
            width: 100%;
        }
        .sticky-cta-btn {
            border-radius: 0;
            padding: 20px;
            font-size: 16px;
        }
    }
</style>

<?php if( get_post_type() == 'accommodation'){ ?>
    <!-- This is the Sticky CTA HTML -->
    <div class="sticky-cta-container">
        <a href="#room-filter-form" class="sticky-cta-btn">Sticky CTA for bottom</a>
    </div>
<?php }

echo '</div>'; //Main Wrapper


echo '<div class="mobFilterModal" id="mobFilterModal">';
  echo '<div class="mobFilterInner">';
    echo '<div class="mobFilterClose">';
      echo '<a href="javascript:;" class="closeMobSearch">✕</a>';
    echo '</div>'; #mobFilterClose
    echo '<div class="resortFilterWrap">';
        echo do_shortcode('[newResortFilters]');
    echo '</div>'; #resortFilterWrap
  echo '</div>'; #mobFilterInner
echo '</div>'; #mobFilterModal

wp_footer();
?>
</body>
</html>