<?php

add_shortcode('roomboss_listing', 'roomboss_listing_fn');

function roomboss_listing_fn($atts)
{

    $atts = shortcode_atts(
        array(
            'cat_slug' => '',
        ),
        $atts,
        'roomboss_listing'
    );

    $url = $_SERVER['REQUEST_URI'];
    $path = parse_url( $url, PHP_URL_PATH );// "/stay/hakuba-accommodation/"
    $slug = basename( untrailingslashit( $path ) ); 
    $resort = explode( '-', $slug )[0];

    $_GET['resort'] == $resort;

    $cat_slug = $atts['cat_slug'];

    ob_start(); ?>

    <div class="filter-area">

        <form id="roomboss_form" class="roomboss_form" method="POST" data-offset="1" data-post_id="<?php echo get_the_ID() ?>" data-cat="<?php echo $cat_slug ?>">

            <?php
                $args = [
                    'post_type'      => 'accommodation',
                    'post_status'    => 'publish',
                    'orderby'        => 'title', // Order by post title
                    'fields'         => 'ids',   // Only get post IDs
                    'order'          => 'ASC',
                    'posts_per_page' => 9,
                    'tax_query'      => [
                        [
                            'taxonomy' => 'accommodation-cat',
                            'field'    => 'slug',   // you can also use 'term_id'
                            'terms'    => $cat_slug,
                        ],
                    ],
                ];
                $acc_ids = get_posts($args);
            ?>

            <div class="_search accomodation_search">
                <label>Location/Property</label>
                <input type="text" class="select_acc" name="search_location" id="search_location" placeholder="Where to?" value="<?php echo ucwords( $resort ) ?>" data-default="All Accommodation" autocomplete="off" readonly data-val="Niseko Accommodation">
                <input type="hidden" class="acc_type" name="type_acc" id="type_acc" value="destination">
                <div class="err_msg" style="display:none"></div>
                
            </div>

            <div class="check-dates accomodation_search">
                <label> Check In </label>
                <div class="checkin">
                    <input type="text" class="search_dates" name="checkIn" id="check-in" placeholder="Choose check-in date" autocomplete="off" title="CheckIn date" readonly data-default="01-Dec-2025">
                    <div class="err_msg" style="display:none"></div>
                </div>

            </div>

            <div class="check-dates accomodation_search">
                <label> Checkout Date </label>
                <div class="duration">
                    <input type="text" disabled class="duration_input" name="checkOut" id="check-out" readonly placeholder="Select checkout date" title="Checkout field">
                    <div class="err_msg" style="display:none"></div>
                </div>
            </div>

            <div class="num_guests accomodation_search" style="display:none;">
                <label> Guests </label>
                <select class="num_guests accomodation-select2" name="guests" id="guests" data-default="1" data-placeholder="Guests">
                    <option>Guests</option>
                    <?php for ($i=1; $i < 15; $i++) {
                        ?>        
                        <option value="<?= $i; ?>" <?php echo $i == 1 ? 'selected' : ''; ?>><?= $i; ?></option>
                        <?php
                    } ?>
                </select>
                <div class="err_msg" style="display:none"></div>
            </div>

            <input type="hidden" name="offset" id="offset" value="1">
            <input type="hidden" name="limit" id="limit" value="9">
            <input type="submit" id="roomboss_form_submit" name="search_roomboss_acc" class="search_roomboss rb_listing_btn form_submit" value="Search">

        </form>
    </div>
    <div class="filter_container">
        <div class="filter_slider">
            <button class="filter_slider_btn">
                <i class="fa-solid fa-sliders"></i> Filter By
            </button>
        </div>   
        <div class="filter" style="display:none;">

            <div class="_search accomodation_search">
                <label>Location/Property</label>
                <input type="text" class="select_acc" name="search_acc" id="search_acc" placeholder="Where to?" data-default="All Accommodation" autocomplete="off" data-val="Niseko Accommodation">
                <input type="hidden" class="acc_type" name="type_acc" id="type_acc" value="destination">
                <div class="err_msg" style="display:none"></div>
                
                <div class="dropdown_results" style="display: none;">
                    <ul class="properties">
                        <?php foreach ($acc_ids as $key => $value):

                            $title = get_the_title($value);
                            $area = get_field('area', $value);
                            $hotel_tid = get_field( 'acc_hotel_id', $value );
                        ?>
                            <li data-value="<?php echo $title ?>" data-id="<?php echo $hotel_tid ?>" class="property"><?php echo ucwords( $title ) ?></li>
                        <?php endforeach ?>
                   </ul>
                </div>
            </div>

            <div class="price-range accomodation_search">
                <label> Price Range </label>
                <?php 
                $max_price = get_acc_price( 'max', $slug );
                $min_price = get_acc_price( 'min', $slug );
                ?>
                <div class="price-range">
                    <input type="text" class="price-slider" name="price_range" data-slug="<?php echo $slug; ?>" data-min="<?php echo $min_price ?>" data-max="<?php echo $max_price ?>" id="price_range" value="" />
                    <input type="hidden" id="price_range_value" name="price_range_value" value="<?php echo $min_price .'-'.$max_price ?>" />

                    <div class="err_msg" style="display:none"></div>
                </div>
            </div>

            <div class="ski-in_ski-out accomodation_search">
                <label> SKI In SKI Out </label>
                <div class="price-range">
                    <input type="checkbox" class="ski-in-ski-out" name="ski" id="ski" />
                    <div class="err_msg" style="display:none"></div>
                </div>
            </div>

            <?php echo kv_dynamic_bedroom_checkboxes(); ?>
        </div>
    </div>
<?php

    wp_reset_postdata();

    return '' . ob_get_clean();

}