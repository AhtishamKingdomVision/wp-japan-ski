<?php
/* Template Name: RoomBoss Hotel Listing */

get_header();

// $main_title = $section['main_title'];
$main_title = get_the_title();



// $cat = $section['select_category'];



// $product_count = $section['product_count'];



$by_default = get_field('by_default', 'option');

echo '<section class="full-section packages_listing">';

echo '<div class="container">';

echo '<div class="pl__inn s1">';

if ($main_title) {

    printf('<h2>%s</h2>', $main_title);

}

// if (!empty($cat)) :



    // print_r ($cat->term_id);



    // die;

    // echo do_shortcode('[cat_listing]');



    echo '<div class="pl_listing">';

        $field_types = get_field_object( 'field_63511e6430063' );
        ?>
        <div class="rom_types">
            <button class="type">All</button>
            <?php foreach ($field_types['choices'] as $key => $value): ?>
                <button value="<?php echo $key ?>"><?php echo $value ?></button>
            <?php endforeach ?>
        </div>
        <?php
        $loadID = 'l-' . uniqid();

        echo '<div class="pl_list ' . $loadID . '">';

            // echo '<h1> Loading Accomodation... </h1>';
            // $lodaing_acc_src = get_attachment_link( 115176 );
            // echo '<img src="'.$lodaing_acc_src.'">';

            $args = [
                'post_type'      => 'accommodation',
                'post_status'    => 'publish',
                'fields'         => 'ids', // return only post IDs
                'orderby' => 'title', // Order by post title
                'order'   => 'ASC',
                'posts_per_page' => -1,
                'tax_query'      => [
                    [
                        'taxonomy' => 'accommodation-cat',
                        'field'    => 'slug',   // you can also use 'term_id'
                        'terms'    => 'niseko-accommodation',
                    ],
                ],
            ];
            $get_acc = new WP_Query($args);
            $acc_html = '';

            if ( $get_acc->have_posts() ) :
                while ( $get_acc->have_posts() ) :


                    $get_acc->the_post();
                    
                    $hotel_tid = get_field( 'acc_hotel_id' );
                    $get_min_room_price = get_field( 'min_room_price' );
                    echo get_the_ID();
                    $thumbnail_url = has_post_thumbnail() ? get_the_post_thumbnail_url() : get_attachment_link( 43404 );
                    echo $thumbnail_url;
                    $acc_html .= '<div class="listing_box" data-hotel-id="' . esc_attr($hotel_tid) . '" data-acc-id="' . esc_attr(get_the_ID()) . '">';
                        // $acc_html .= '<a href="'.$acc_url.'?start_date='.$start_date.'&end_date='.$end_date.'&guests='.$guests.'&resortId='.$resortId.'">';
                        $acc_html .= '<div class="listing_box_img">';
                        $acc_html .= '<img class="acc_img" src="'.$thumbnail_url.'" alt="hero01"/>';
                        $acc_html .= '</div>'; // .listing_box_img

                        $acc_html .= '<div class="listing_cont">';
                            $acc_html .= '<div class="acc_title"> <h3>' . get_the_title() . '</h3> </div>';
                            $acc_html .= '<div class="acc_desc">'.$trim_cleaned_desc.'</div>';
                            pre( var_dump( $get_min_room_price ) , 1);
                            $acc_html .= '<div class="acc_min_price"><p>From: '._currency_format_new( $get_min_room_price, true ).'</p> /night</div>';
                                $acc_html .= '<div class="acc_buttons" data-post_id="'.get_the_ID().'" data-redirect="'.get_the_permalink().'" data-params="checkIn=01-Dec-2025&checkout=08-Dec-2025&guests=2&adults=2&children=0&infants=0&resort=Niseko Accommodation">
                                    <div class="check_rates">
                                        <button class="check_rates_btn">Check Rates</button>
                                    </div>
                                    <div class="view_details">
                                        <button class="view_details_btn">View Details</button>
                                    </div>
                                </div>';
                        $acc_html .= '</div>'; // .listing_cont

                            // $acc_html .= '<span>View More</span>';
                    $acc_html .= '</div>'; // .listing_box
                    ?>
                <?php endwhile; ?>

                <?php wp_reset_postdata(); ?>

            <?php else : ?>
                <p><?php $acc_html = 'Sorry, no accomodations matched your criteria.'; ?></p>
            <?php endif;
            echo $acc_html;
        echo '</div>'; //pl_list
        ?>
        <?php

    echo '</div>'; //pl_listing

// endif;



echo '<div class="loadmore-button-wrapper">'; //pl__inn


$display = 'none';

// if ($loop->max_num_pages > 1) {
//     echo '<button data-max_page="' . $loop->max_num_pages . '" type="button" class="btn light_green kv_loadmore">Load More</button>';
// }

// echo '<button data-max_page="" type="button" style="display:none" class="btn rb_listing_btn light_green rb_loadmore">Load More</button>';

echo '</div>';

echo '</div>'; //pl__inn

echo '</div>';

echo '</section>';

?>
<?php

get_footer();