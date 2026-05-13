<?php
if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_the_ID();

// Data from shortcode
$teamMembersOption = get_query_var('teamMembersOption') ?? [];

// ACF Fields
$select_author = get_field('select_member', $post_id);
$date          = get_field('date', $post_id);
$tag_line      = get_field('tag_line', $post_id);
$image         = get_field('listing_image', $post_id);
$placeholder = get_template_directory_uri() . '/images/placeholder-featured.jpg';
?>

<article class="kv-post-card">
    <a href="<?php the_permalink(); ?>">

        <div class="image">
            <?php
            if ($image) {
                echo wp_get_attachment_image($image, 'full');
            } else {
                echo '<img src="'.$placeholder.'">';
            }
            ?>
        </div>

        <div class="postcontent">

            <?php if ($tag_line) { ?>
                <span class="tagline"><?php echo esc_html($tag_line); ?></span>
            <?php } else {
                echo '<span class="tagline">&nbsp;</span>';
            } ?>

            <h3><?php the_title(); ?></h3>

            <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 18)); ?></p>

            <?php
            if (!empty($select_author) || !empty($date)) {
                echo getMemberFromSpecialists(
                    $select_author ?? null,
                    $teamMembersOption,
                    $date ?? null
                );
            }
            ?>

            <span class="btn">Read More</span>

        </div>
    </a>
</article>
