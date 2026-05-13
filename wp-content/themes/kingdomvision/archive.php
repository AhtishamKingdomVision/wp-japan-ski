<?php get_header();
$term     = get_queried_object();
$tag_line = get_field('tag_line', $term);
$banner   = get_field('category_banner', $term);
$overlay  = get_field('banner_overlay', $term);

$opt_ID     = get_field('form_id_option', 'option', $term);
$cat_banner = get_field('category_banner_image', 'option', $term);
$cat_btn    = get_field('category_btn', 'option', $term);
$cat_toggle = get_field('category_toggle', 'option', $term);

if ($cat_toggle == true):
    $form_s    = 'form_toggle';
    $hide_form = 'desktop-none';
else:
    $form_s    = '';
    $hide_form = '';
endif;

// Author archive layout
if (is_author()) :

    $author      = get_queried_object();
    $author_id   = $author->ID;
    $author_name = $author->display_name;

    // Pull data from the Team Members repeater on the Team Specialists options page
    $team_members    = get_field('team_members', 'option');
    $author_photo    = '';
    $author_jobtitle = '';
    $author_bio      = '';
    $author_linkedin = '';

    if ($team_members) {
        foreach ($team_members as $member) {
            if (isset($member['name']) && trim(strtolower($member['name'])) === trim(strtolower($author_name))) {
                // Handle all three possible ACF Image return formats
                if (!empty($member['profile_image'])) {
                    if (is_array($member['profile_image'])) {
                        $author_photo = $member['profile_image']['url'];
                    } elseif (is_numeric($member['profile_image'])) {
                        $author_photo = wp_get_attachment_image_url($member['profile_image'], 'full');
                    } else {
                        $author_photo = $member['profile_image'];
                    }
                }
                $author_jobtitle = !empty($member['designation']) ? $member['designation'] : '';
                $author_bio      = !empty($member['profile_description']) ? $member['profile_description'] : '';
                $author_linkedin = !empty($member['linkedin_url']) ? $member['linkedin_url'] : '';
                break;
            }
        }
    }

    // Fallbacks if no team member match found
    if (!$author_photo) {
        $author_photo = get_avatar_url($author_id, ['size' => 600]);
    }
    if (!$author_bio) {
        $author_bio = wpautop(esc_html(get_the_author_meta('description', $author_id)));
    }
    ?>

    <section class="full-section author-archive">
        <div class="container">
            <div class="author-header">
                <div class="author-photo">
                    <img src="<?php echo esc_url($author_photo); ?>" alt="<?php echo esc_attr($author_name); ?>" />
                </div>
                <div class="author-info">
                    <h1><?php echo esc_html($author_name); ?></h1>
                    <?php if ($author_jobtitle) : ?>
                        <p class="author-job-title"><?php echo esc_html($author_jobtitle); ?></p>
                    <?php endif; ?>
                    <?php if ($author_bio) : ?>
                        <div class="author-bio"><?php echo $author_bio; ?></div>
                    <?php endif; ?>
                    <?php if ($author_linkedin) : ?>
                        <a href="<?php echo esc_url($author_linkedin); ?>" target="_blank" rel="noopener" class="author-linkedin">
                            Connect on LinkedIn
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <h2 class="author-posts-heading">Articles by <?php echo esc_html($author_name); ?></h2>

            <div class="kv-post-grid">
                <?php
                while (have_posts()) : the_post();
                    $thumbnail_id = get_post_thumbnail_id($post->ID);
                    $alt          = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                    $excerpt      = get_the_excerpt();
                    ?>
                    <article class="kv-post-card">
                        <a href="<?php the_permalink(); ?>" class="image">
                            <?php echo wp_get_attachment_image($thumbnail_id, 'blog_size', false, array('alt' => $alt)); ?>
                        </a>
                        <div class="postcontent">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <?php if ($excerpt) : ?>
                                <p><?php echo wp_trim_words($excerpt, 18, '...'); ?></p>
                            <?php endif; ?>
                            <a class="btn" href="<?php the_permalink(); ?>">Read More</a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <div class="author-pagination">
                <?php
                echo paginate_links(array(
                    'prev_text' => '← Newer posts',
                    'next_text' => 'Older posts →',
                ));
                ?>
            </div>
        </div>
    </section>

<?php
// Category, tag and other archive layout
else :

    echo '<section class="full-section top_banner" style="background: url(' . ($banner ? $banner : wp_get_attachment_url(586)) . ') no-repeat center/cover;">';
        echo '<div class="container">';
            echo '<h2>' . $term->name . '</h2>';
            if ($tag_line) {
                printf('<p class="space_bottom">%s</p>', $tag_line);
            }

            if ($cat_btn):
                echo '<div class="' . $form_s . ' mobile-none"><a href="javascript:;">' . $cat_btn . '</a></div>';
            else:
                echo '<div class="' . $form_s . ' mobile-none"><a href="javascript:;">Get a Quote</a></div>';
            endif;

            echo '<a class="quote_form desktop-none" href="' . home_url() . '/get-a-quote/">' . $cat_btn . '</a>';
            echo '<div class="enquire_form mobile-none ' . $hide_form . '">';
                echo do_shortcode('[gravityform id="' . $opt_ID . '" title="true" description="false" ajax="true"]');
            echo '</div>';
        echo '</div>';
    echo '</section>';

    echo '<section class="full-section cs_breadcrumbs">';
        echo '<div class="container">';
            echo do_shortcode('[wpseo_breadcrumb]');
        echo '</div>';
        the_archive_description('<div class="taxonomy-description">', '</div>');
    echo '</section>';
    ?>

    <section class="full-section blogs_section">
        <div class="container">
            <div class="blog-wrapper">
                <?php
                while (have_posts()) : the_post();
                    $thumbnail_id = get_post_thumbnail_id($post->ID);
                    $alt          = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
                    echo '<div class="post-box">';
                        echo '<a href="' . get_the_permalink() . '">';
                            echo '<div class="image">';
                                echo wp_get_attachment_image($thumbnail_id, 'blog_size', false, array('alt' => $alt));
                            echo '</div>';
                        echo '</a>';
                        echo '<div class="cont">';
                            $date = get_field('post_date');
                            if ($date) {
                                echo '<p class="date">' . $date . '</p>';
                            } else {
                                echo '<p class="date">' . get_the_time('d, F, Y') . '</p>';
                            }
                            $cate = get_the_category();
                            echo '<p class="cat">';
                                echo '<strong>Categories: </strong>';
                                foreach ($cate as $key => $value) {
                                    echo '<a href="' . esc_attr(esc_url(get_category_link($value->term_id))) . '" class="cat">' . $value->name . '</a>';
                                }
                            echo '<h2><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></h2>';
                            $author = get_field('post_auther');
                            if ($author) {
                                echo '<p class="author">By ' . $author . '</p>';
                            } else {
                                echo '<p class="author">By ' . get_the_author_meta('display_name', get_the_author_ID()) . '</p>';
                            }
                            echo '<a class="btn light_green" href="' . get_the_permalink() . '">Read More</a>';
                        echo '</div>';
                    echo '</div>';
                endwhile;
                ?>
            </div>
        </div>
    </section>

    <?php if ($overlay == true): ?>
        <style type="text/css">
            section.top_banner:after {
                content: "";
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                background: rgb(0 0 0 / 50%);
                z-index: -1;
            }
        </style>
    <?php endif; ?>

<?php endif; ?>
<?php get_footer(); ?>