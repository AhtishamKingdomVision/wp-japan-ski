<?php get_header();
 /*
 * The default page template
 */

$header_option = get_field('header_option');

$search = get_field('search_content', 'option');
$meta_desc = substr( get_post_meta(get_the_ID(), '_yoast_wpseo_metadesc', true), 0, 200 );

echo '<div class="content-wrapper full-section '.$header_option.'">';
    echo '<section class="full-section search_area">';
        echo '<div class="container">';

            if(isset($_GET['s'])){
                echo '<h2 class="keyword">Keywords: '.$_GET["s"].'</h2>';
            }
            if (have_posts()) { while (have_posts()) { the_post();
                echo '<div class="search-box">';
                        if(has_post_thumbnail()){
                            $img = get_the_post_thumbnail_url();
                        } else{
                            $img = get_template_directory_uri().'/images/placeholder-featured.jpg';
                        }
                    echo '<div class="sh-image" style="background: url('.$img.')no-repeat center/cover;"></div>';
                    echo '<div class="sh-cont">';
                        echo '<h3><a href="'.get_the_permalink().'">'.get_the_title().'</a></h3>';
                        $content = get_the_content();
                        if($content){
                            echo '<p>'.wp_trim_words($content , 30 , '...').'</p>';
                        } elseif ($meta_desc) {
                            echo '<p>'.$meta_desc.'</p>';              
                        } else {
                            echo '<p>'.wp_trim_words($search , 30 , '...').'</p>';
                        }
                        echo '<a href="'.get_the_permalink().'" class="btn light_green">Read More</a>';
                    echo '</div>';
                echo '</div>';
            } }
            else{
                echo '<p class="no-result">No results found matching your search criteria.</p>';
            }
        echo '</div>';
    echo '</section>';
echo '</div>'; #content-wrapper
get_footer(); ?>

<?php #get_template_part( 'blog', 'loop' ); ?>