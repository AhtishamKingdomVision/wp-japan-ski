<?php

/**
 * Company-level stats (saved via cron)
 */
$company_title   = get_option('kv_company_review_title', 'Rated excellent');
$company_total   = (int) get_option('kv_company_total_reviews', 0);
$company_average = (float) get_option('kv_company_average_rating', 0);

$schemaReviews = [];

$tags = explode( ',', trim($section['review_tags']) );
if( empty($tags) ){
    $tags = ['accommodation-best'];
}
/**
 * Fetch reviews from CPT instead of Reviews.io API
 */
$args = get_reviews_args( $tags, [], -1 );

$reviews_query = new WP_Query($args);

if ( $reviews_query->have_posts() ) {

    while ( $reviews_query->have_posts() ) {
        $reviews_query->the_post();

        $rating     = get_post_meta(get_the_ID(), 'review_rating', true);
        $reviewDate = get_post_meta(get_the_ID(), 'review_date', true);

        $schemaReviews[] = [
            "@type" => "Review",
            "author" => [
                "@type" => "Person",
                "name"  => get_the_title(),
            ],
            "datePublished" => date("Y-m-d", strtotime($reviewDate)),
            "reviewRating" => [
                "@type"       => "Rating",
                "bestRating"  => "5",
                "ratingValue" => (string) $rating,
                "worstRating" => "1",
            ],
            "reviewBody" => wp_strip_all_tags(get_the_content()),
        ];
    }

    wp_reset_postdata();
}

/**
 * Frontend display
 */
if ( $reviews_query->have_posts() ) :

    $average = number_format($company_average, 2);
    $count   = "{$average} Average {$company_total} Reviews";

    $link_url = 'https://www.reviews.io/company-reviews/store/japanskiexperience-com1';
    // $link_title = "View All " . get_the_title() . " Reviews";
    $link_title = "View All Our Reviews";
    $link_target = "blank";

    $average = $rating  = get_option( 'kv_company_average_rating', 4.8 );
    $total = (int) ( get_option( 'kv_company_total_reviews', 0 ) );
    $count = $average . ' Average ' . $total . ' Reviews';
    $title = get_option('kv_company_review_title') ?: 'Rated excellent';

    echo '<section id="section_review" class="full-section client_reviews" aria-labelledby="client-reviews-title" '.BackgroundFromSection($section).'>';
        echo '<div class="container">';
            echo '<div class="cr_cover">';
                echo '<div class="cr_left">';
                    if ($title) {
                        printf('<h2>%s</h2>', $title);
                    }
                        echo '<span class="five-star star-count-' . (int)$average . '"></span>';
                    if ($count) {
                        printf('<h3>%s</h3>', $count);
                    }
                    // if (!empty($sku)):
                        printf('<a class="btn light_green" href="%s" target="%s">%s</a>', $link_url, $link_target, $link_title);
                    // endif;
                echo '</div>'; //cr_left

                echo '<div class="cr_right">';
                    echo '<div class="reviews-carousel">';

                    while ( $reviews_query->have_posts() ) :
                        $reviews_query->the_post();

                        $reviewDate = get_post_meta(get_the_ID(), 'review_date', true);
                        $postedDate = 'Posted ' . date("F Y", strtotime($reviewDate));
                        ?>
                        <div class="item">
                            <div class="title">
                                <i class="fa fa-star"></i>
                                <i class="fa fa-star"></i>
                                <i class="fa fa-star"></i>
                                <i class="fa fa-star"></i>
                                <i class="fa fa-star"></i>
                            </div>
                            <div class="excerpt more"><?php the_content(); ?></div>
                            <h3><?php the_title(); ?></h3>
                            <span class="date"><?php echo esc_html($postedDate); ?></span>
                        </div>
                        <?php
                    endwhile;

                    wp_reset_postdata();

                    echo '</div>';
                echo '</div>';
            echo '</div>';
        echo '</div>';
    echo '</section>';

endif;

?>