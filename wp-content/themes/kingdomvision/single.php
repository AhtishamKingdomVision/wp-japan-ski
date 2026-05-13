<?php
get_header();
$header_option = get_field('header_option');
$bg_image = get_the_post_thumbnail_url();
$placeholder = get_template_directory_uri() . '/images/placeholder-single.jpg';
$bg_image = !empty($bg_image) ? $bg_image : $placeholder;
$tag_line = get_field('tag_line');
$title = get_the_title();
$teamMembersOption = get_field('team_members' , 'option') ?? [];
$post_id    = get_the_ID();
$post_url   = urlencode(get_permalink($post_id));
$post_title = urlencode(get_the_title($post_id));
$post_image = has_post_thumbnail($post_id)
    ? urlencode(get_the_post_thumbnail_url($post_id, 'full'))
    : '';
$overlay      = get_field('overlay');
$blog_builder = get_field('post_builder');
$ff_image     = get_field('form_footer_image', 'option');
$ff_content   = get_field('form_footer_content', 'option');

// Pull author info from WordPress Author and the Team Members repeater
$wp_author_id   = get_the_author_meta('ID');
$wp_author_name = get_the_author_meta('display_name');
$author_url     = get_author_posts_url($wp_author_id);
$author_photo    = '';
$author_jobtitle = '';
$author_bio      = '';

if ($teamMembersOption) {
    foreach ($teamMembersOption as $member) {
        if (isset($member['name']) && trim(strtolower($member['name'])) === trim(strtolower($wp_author_name))) {
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
            break;
        }
    }
}

// Show "Published" by default, switch to "Updated" if modified more than 7 days after publish
$published_timestamp = get_the_time('U');
$modified_timestamp  = get_the_modified_time('U');
$update_threshold    = 7 * DAY_IN_SECONDS;

if (($modified_timestamp - $published_timestamp) > $update_threshold) {
    $post_date_display = 'Updated ' . get_the_modified_time('j F Y');
} else {
    $post_date_display = 'Published ' . get_the_time('j F Y');
}

echo '<div class="content-wrapper full-section accommodation '.esc_attr($header_option).'">';
    echo '<section class="full-section topsinglebanner" style="background: url('.$bg_image.') no-repeat center/cover;">';
        echo '<div class="container">';
            if($tag_line){ printf('<span class="tagline">%s</span>', $tag_line); }
            if($title){ printf('<h1>%s</h1>', $title); }

            // Author and date byline
            echo '<div class="postAuthorDate">';
                echo '<span class="postAuthorName">By <a href="' . esc_url($author_url) . '">' . esc_html($wp_author_name) . '</a></span>';
                echo '<span class="postAuthorDateSeparator">|</span>';
                echo '<span class="postAuthorDateText">' . esc_html($post_date_display) . '</span>';
            echo '</div>';

            echo '<div class="kv-social-share">';
            echo '<a href="javascript:;" class="kv-share-btn kv-copy-link"
                    data-link="' . esc_url(get_permalink($post_id)) . '"
                    aria-label="Copy link">
                    <i class="fa-solid fa-link"></i>
                  </a>';
            echo '<a class="kv-share-btn"
                    href="https://twitter.com/intent/tweet?url=' . $post_url . '&text=' . $post_title . '"
                    target="_blank" rel="noopener noreferrer"
                    aria-label="Share on X">
                    <i class="fa-brands fa-x-twitter"></i>
                  </a>';
            echo '<a class="kv-share-btn"
                    href="https://www.facebook.com/sharer/sharer.php?u=' . $post_url . '"
                    target="_blank" rel="noopener noreferrer"
                    aria-label="Share on Facebook">
                    <i class="fa-brands fa-facebook-f"></i>
                  </a>';
            echo '<a class="kv-share-btn"
                    href="https://www.linkedin.com/sharing/share-offsite/?url=' . $post_url . '"
                    target="_blank" rel="noopener noreferrer"
                    aria-label="Share on LinkedIn">
                    <i class="fa-brands fa-linkedin-in"></i>
                  </a>';
            if ($post_image) {
                echo '<a class="kv-share-btn"
                        href="https://pinterest.com/pin/create/button/?url=' . $post_url . '&media=' . $post_image . '&description=' . $post_title . '"
                        target="_blank" rel="noopener noreferrer"
                        aria-label="Share on Pinterest">
                        <i class="fa-brands fa-pinterest-p"></i>
                      </a>';
            }
            echo '</div>';
        echo '</div>';
    echo '</section>';
    echo '<section class="full-section single_main">';
        echo '<div class="container">';
            echo '<div class="sm_inner">';
                echo '<div class="sm_left">';
                    the_content();
                     if($blog_builder){
                       if (!empty($blog_builder) && is_array($blog_builder)) {
                          $sectionIndex = 0;
                          foreach ($blog_builder as $section){
                             $sectionIndex++;
                             $layout = $section['acf_fc_layout'];
                             $template = locate_template("section/blogs/{$layout}.php");
                             if ($template) {
                                   $currenSectionIndex = $sectionIndex;
                                   include $template;
                             }
                          }
                       } else {
                       }
                     }

                    // Author bio block at end of post
                    if ($author_photo || $author_bio) :
                        ?>
                        <div class="post-author-bio">
                            <div class="post-author-bio-inner">
                                <?php if ($author_photo) : ?>
                                    <div class="post-author-bio-photo">
                                        <span class="post-author-eyebrow">Written by</span>
                                        <a href="<?php echo esc_url($author_url); ?>">
                                            <img src="<?php echo esc_url($author_photo); ?>" alt="<?php echo esc_attr($wp_author_name); ?>" />
                                        </a>
                                    </div>
                                <?php endif; ?>
                                <div class="post-author-bio-content">
                                    <h3><a href="<?php echo esc_url($author_url); ?>"><?php echo esc_html($wp_author_name); ?></a></h3>
                                    <?php if ($author_jobtitle) : ?>
                                        <p class="post-author-bio-title"><?php echo esc_html($author_jobtitle); ?></p>
                                    <?php endif; ?>
                                    <?php if ($author_bio) : ?>
                                        <div class="post-author-bio-text"><?php echo $author_bio; ?></div>
                                    <?php endif; ?>
                                    <div class="post-author-bio-links">
                                        <a href="<?php echo esc_url($author_url); ?>" class="post-author-bio-readmore">Read more by <?php echo esc_html($wp_author_name); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
                    endif;

                        echo '<div class="more_posts full-section">';
                         $prev_post = get_previous_post();
                         $next_post = get_next_post();
                            if($prev_post) {
                              echo '<div class="prev_posts">';
                                 $prev_title = strip_tags(str_replace('"', '', $prev_post->post_title));
                                 echo "\t" . '<a class="btn" rel="prev" href="' . get_permalink($prev_post->ID) . '" title="' . $prev_title. '" class=" "><img src="'.home_url().'/wp-content/themes/kingdomvision/images/left_arrow.svg"></i>&nbsp;&nbsp;Previous Post</a>' . "\n";
                              echo '</div>';
                            }
                            if($next_post) {
                              echo '<div class="next_posts">';
                                 $next_title = strip_tags(str_replace('"', '', $next_post->post_title));
                                 echo "\t" . '<a class="btn" rel="next" href="' . get_permalink($next_post->ID) . '" title="' . $next_title. '" class=" ">Next Post&nbsp;&nbsp;<img src="'.home_url().'/wp-content/themes/kingdomvision/images/right_arrow.svg"></a>' . "\n";
                              echo '</div>';
                            }
                            echo '</div>';
                echo '</div>';
                echo '<div class="sm_right">';
                    echo '<div class="sm_sticky">';
                        $jumplinks = get_field('jumplinks');
                        if (!empty($jumplinks) && is_array($jumplinks)) {
                            echo '<ul>';
                            foreach ($jumplinks as $value) {
                                if (empty($value['link']) || empty($value['link']['url'])) {
                                    continue;
                                }
                                $link        = $value['link'];
                                $link_url    = $link['url'];
                                $link_title  = !empty($link['title']) ? $link['title'] : '';
                                $link_target = !empty($link['target']) ? $link['target'] : '_self';
                                if (empty($link_url)) {
                                    continue;
                                }
                                echo '<li>';
                                    echo '<a href="' . esc_url($link_url) . '" target="' . esc_attr($link_target) . '">';
                                        echo esc_html($link_title);
                                    echo '</a>';
                                echo '</li>';
                            }
                            echo '</ul>';
                        }
                        echo do_shortcode('[gravityform id="1" title="true"]');
                        if ($ff_image && $ff_content) {
                            echo '<div class="ff_content_image">';
                                echo '<div class="ff_image">';
                                    echo wp_get_attachment_image($ff_image, 'full');
                                echo '</div>';
                                echo '<div class="ff_content">';
                                    echo wp_kses_post($ff_content);
                                echo '</div>';
                            echo '</div>';
                        }
                    echo '</div>';
                echo '</div>';
            echo '</div>';
        echo '</div>';
    echo '</section>';
    $title_n = get_field('title_n');
    $related_posts = get_field('post', get_the_ID());
    $teamMembersOption  = get_field('team_members', 'option') ?? [];
    if ($related_posts) {
        echo '<section class="full-section kv-related-posts">';
            echo '<div class="container">';
                echo '<h2>' . ($title_n == '0' || empty($title_n) ? 'Related Posts' : esc_html($title_n)) . '</h2>';
                echo '<div class="kv-post-grid">';
                foreach ($related_posts as $post) {
                    setup_postdata($post);
                    set_query_var('teamMembersOption', $teamMembersOption);
                    ob_start();
                    get_template_part('post-card');
                    echo ob_get_clean();
                }
                echo '</div>';
            echo '</div>';
        echo '</section>';
        wp_reset_postdata();
    }
    echo '</div>';
?>
<?php if($overlay == true): ?>
    <style type="text/css">
    section.topsinglebanner:after { content: ""; position: absolute; width: 100%; height: 100%; top: 0; background: rgba(0, 17, 31, 0.80); z-index: -1; }
</style>
<?php endif; ?>
<?php get_footer(); ?>