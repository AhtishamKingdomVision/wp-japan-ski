<?php

$select_category = $section['select_category'] ?? [];
echo '<section ' . SectionAttributes($section, 'full-section category-tabs') . ' ' . BackgroundFromSection($section) . ' role="region" aria-labelledby="category-tabs" >';
	echo '<div class="container">';

		echo TitleFromSection($section);
		
		if($select_category ){
		    echo '<div class="tabsWrapper">';
		    	$count = count($select_category);
		    	echo '<div class="tabs-menu menu-'.$count.'">';
		            foreach ( $select_category as $index => $term ) {
					    $slug = $term->slug;

					    $data_hash = preg_replace('/[^A-Za-z0-9]/', '', $term->name);

					    echo '<div class="tabName '.($index == 0 ? 'active' : '').'" 
					        data-hash="'.esc_attr($data_hash).'">';
					        echo '<span>'.esc_html($term->name).'</span>';
					    echo '</div>';
					}
		        echo '</div>'; #tabs-menu

		        echo '<div class="tabs-content">';
		            foreach( $select_category as $index => $term ){ 
		                $slug = $term->slug;

		                // Query posts from this category
		                $args = array(
		                    'post_type'      => 'accommodation',
		                    'tax_query'      => array(
		                        array(
		                            'taxonomy' => 'accommodation-cat',
		                            'field'    => 'slug',
		                            'terms'    => $slug,
		                        )
		                    ),
		                    'posts_per_page' => 5,
		                );
		                $query = new WP_Query($args);


		                $data_hash = preg_replace('/[^A-Za-z0-9]/', '', $term->name);
		                echo '<div class="tab-pane '.($index == 0 ? 'active' : '').'" 
		                	data-hash="'.esc_attr($data_hash).'">';
		                	
		                	echo '<div class="cat-description">';
		                		echo '<p>'.$term->description.'</p>';
		                	echo '</div>'; #cat-description

		                    if ( $query->have_posts() ) {
		                        echo '<div class="tab-acc-lists">';
		                            while ( $query->have_posts() ) { 
		                            		$query->the_post();
		                                echo '<div class="acc-box">';
		                                	echo '<a href="'.esc_url(get_the_permalink()).'" class="fulAnchor"></a>';
		                                    	
		                                    echo '<div class="post-cont">';
		                                    	echo '<h3>'.get_the_title().'</h3>';
		                                    	echo '<p>'.wp_trim_words(strip_tags(get_the_content()), 30, '...').'</p>';
		                                    	echo '<a href="'.esc_url(get_the_permalink()).'" class="btn">Discover</a>';
		                                    echo '</div>'; #post-cont

		                                    echo '<div class="post-img">';
		                                    	if(has_post_thumbnail()){
		                                    		echo '<img src="'.esc_url(get_the_post_thumbnail_url()).'" alt="'.get_the_title().'" />';
		                                    	}else{
		                                    		echo '<img src="'.esc_url(wp_get_attachment_url(1183)).'" alt="'.get_the_title().'" class="default"/>';
		                                    	}
		                                    echo '</div>'; #post-img
		                                echo '</div>'; #tab-acc-lists
		                            } 
		                            wp_reset_postdata();
		                        echo '</div>'; #tab-acc-lists
		                    }
		                echo '</div>'; #tab-pane

		            }
		        echo '</div>'; #tabs-content

		    echo '</div>'; #tabsWrapper
		}
	echo '</div>'; #container
echo '</section>'; #category-tabs

?>

<script>

	jQuery(document).ready(function($){
	    // --- Handle tab clicks ---
	    $(".tabs-menu .tabName").click(function(){
	        var tab_hash = $(this).data("hash");

	        $(".tabs-menu .tabName").removeClass("active");
	        $(".tab-pane").removeClass("active");

	        $(this).addClass("active");
	        $('.tab-pane[data-hash="' + tab_hash + '"]').addClass("active");

	        // Update the hash in the URL
	        history.replaceState(null, null, '#' + tab_hash.replace(/\s+/g, ''));
	    });

	    // --- Activate tab based on URL hash ---
	    var hash = window.location.hash.substring(1); // remove #
	    if(hash){
	        var $tabTrigger = $('.tabs-menu .tabName').filter(function(){
	            return $(this).data("hash").replace(/\s+/g, '') === hash;
	        });

	        if($tabTrigger.length){
	            var tab_hash = $tabTrigger.data("hash");

	            $(".tabs-menu .tabName").removeClass("active");
	            $(".tab-pane").removeClass("active");

	            $tabTrigger.addClass("active");
	            $('.tab-pane[data-hash="' + tab_hash + '"]').addClass("active");
	        }
	    }
	});


</script>