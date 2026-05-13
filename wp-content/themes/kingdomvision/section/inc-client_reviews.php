<?php
// Safely get values (PHP 8 compatible)
$link        = (isset($section['link']) && is_array($section['link'])) ? $section['link'] : null;
$link_url    = $link['url']    ?? '';
$link_title  = $link['title']  ?? '';
$link_target = $link['target'] ?? '_self';

$manual    = $section['reviews_info']      ?? '';
$count     = $section['rating_count']      ?? '';
$title     = $section['title']             ?? '';
$tags      = $section['reviews_tags']       ?? '';
$category  = $section['reviews_category']  ?? '';
$perpage   = $section['reviews_per_page']  ?? '';

// If not manual — fetch from global options
if (!$manual) {
    $review_data = get_review_data();
    $average     = $review_data['average']; // kv_company_average_rating
    $total       = $review_data['total'];   // kv_company_total_reviews
    $total   = (int) $total;
    $count = round($average, 1) . ' Average ' . number_format($total) . ' Reviews';
    // $count       = round($average, 1) . ' Average ' . $total . ' Reviews';
    // $title       = $review_data['title'];
}

/* === Section ID & Extra Class === */
$section_id  = !empty($section['section_id']) ? ' id="' . esc_attr($section['section_id']) . '"' : '';
$extra_class = !empty($section['section_class']) ? ' ' . esc_attr($section['section_class']) : '';

@$_GET['show_tags'] == 'yes' ? var_dump( $section ) : '';
if( !empty( $tags ) && strpos( $atts['tags'] , ',' ) ){
    $tags = explode(',', $atts['tags'] );
}

?>

<section class="full-section client_reviews <?php echo $extra_class; ?>" <?php echo $section_id; ?> role="region" aria-labelledby="client-reviews-title" <?php echo BackgroundFromSection($section); ?>>
    <div class="container">
        <div class="cr_cover">

            <!-- LEFT CONTENT -->
            <div class="cr_left">

                <?php if (!empty($title)): ?>
                    <h2 id="client-reviews-title"><?= esc_html($title); ?></h2>
                <?php endif; ?>

                <span class="five-star" aria-hidden="true"></span>

                <?php if (!empty($count)): ?>
                    <p><?= esc_html($count); ?></p>
                <?php endif; ?>

                <?php if (!empty($link_url)): ?>
                    <a 
                        class="btn light_green" 
                        href="<?= esc_url($link_url); ?>" 
                        target="<?= esc_attr($link_target); ?>"
                        aria-label="<?= esc_attr($link_title); ?>"
                    >
                        <?= esc_html($link_title); ?>
                    </a>
                <?php endif; ?>

            </div>
            <!-- cr_left -->

            <!-- RIGHT CONTENT -->
            <div class="cr_right" role="region" aria-label="Client review listing">
                <?= do_shortcode('[reviews tags="'.$tags.'" category="' . esc_attr($category) . '" perpage="' . esc_attr($perpage) . '"]'); ?>
            </div>

        </div>
    </div>
</section>