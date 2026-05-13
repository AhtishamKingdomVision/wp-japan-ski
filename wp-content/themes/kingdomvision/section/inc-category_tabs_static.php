<?php

$show_all_tab = $section['show_all_tab'] ?: false;
$tabs = $section['tabs'] ?? [];
$column = '';

if ( is_array($tabs) ) {
    $count = count($tabs);

    if ( $count > 3 ) {
        $column = ' column4';
    } elseif ( $count > 2 ) {
        $column = ' column3';
    }
}

echo '<section ' . SectionAttributes($section, 'full-section category-tabs') . ' ' . BackgroundFromSection($section) . ' role="region" aria-labelledby="category-tabs" >';
	echo '<div class="container">';
		echo TitleFromSection($section);
		echo '<div class="tabsWrapper '.$column.'">';
			if($tabs){
				echo '<div class="tabs-menu">';
					if($show_all_tab == true){
						echo '<div class="tabName " data-hash="tabAll" >';
					        echo '<span>All</span>';
					    echo '</div>';
				    }
					foreach ($tabs as $key => $tab) {
						$tab_title = $tab['tab_title'];
					    echo '<div class="tabName " data-hash="tab-'.$key.'">';
					        echo '<span>'.$tab_title.'</span>';
					    echo '</div>';
					}
	        	echo '</div>'; #tabs-menu
				
				echo '<div class="tabs-content">';
					if($show_all_tab == true){
						echo '<div class="tab-pane all" data-hash="tabAll" >';
							echo '<div class="allTabData">';
							foreach ($tabs as $key => $allTab) {
								$allTabContent  = $allTab['tab_content'] ?: [];
						    	// echo '<div class="tab-acc-lists">';
						            foreach ($allTabContent as $inner_key => $allTabCont) {
						                $title_inner   = $allTabCont['title_inner'] ?? '';
						                $content_inner = $allTabCont['content_inner'] ?? '';
						                $image_inner   = $allTabCont['image_inner'] ?? '';
						                $link_inner    = $allTabCont['link_inner'] ?? [];

						                $link_url    = $link_inner['url'] ?? '';
						                $link_title  = $link_inner['title'] ?? '';
						                $link_target = !empty($link_inner['target']) ? $link_inner['target'] : '_self';

						                echo '<div class="acc-box">';
						                    if ($link_url) {
						                        echo '<a href="'.esc_url($link_url).'" class="fulAnchor"></a>';
						                    }
						                    echo '<div class="post-cont">';
						                        if ($title_inner) {
						                            echo '<h3>'.esc_html($title_inner).'</h3>';
						                        }
						                        if ($content_inner) {
						                            echo $content_inner;
						                        }
						                        if ($link_url && $link_title) {
						                            echo '<a href="'.esc_url($link_url).'" target="'.esc_attr($link_target).'" class="btn">';
						                                echo esc_html($link_title);
						                            echo '</a>';
						                        }
						                    echo '</div>'; # post-cont
					                        echo '<div class="post-img">';
					                        	if($image_inner){
						                            echo wp_get_attachment_image($image_inner, 'full');
					                        	} else {
					                        		echo '<img src="'.esc_url(wp_get_attachment_url(1183)).'" alt="'.get_the_title().'" class="default"/>';
					                        	}
					                        echo '</div>';
						                echo '</div>'; # acc-box
						            }
						        // echo '</div>'; // tab-acc-lists
							}
				    		echo '</div>'; # allTabData
				    		echo '<div class="mobLoadMore">';
				    			echo '<a href="javascript:;" class="btn">Load More</a>';
				    		echo '</div>'; #mobLoadMore
				    	echo '</div>'; # tab-pane
				    }
					
					foreach ($tabs as $tab_key => $tab) {
					    $tab_content  = $tab['tab_content'] ?? [];
					    $tab_desc     = $tab['tab_description'] ?? '';
					    $active_class = ($tab_key === 0) ? 'active' : '';

					    echo '<div class="tab-pane '.$active_class.'" data-hash="tab-'.$tab_key.'">';
					        if ($tab_desc) {
					            echo '<div class="cat-description">';
					                echo wp_kses_post($tab_desc);
					            echo '</div>';
					        }
					        echo '<div class="tab-acc-lists">';
					            foreach ($tab_content as $inner_key => $tabinn) {
					                $title_inner   = $tabinn['title_inner'] ?? '';
					                $content_inner = $tabinn['content_inner'] ?? '';
					                $image_inner   = $tabinn['image_inner'] ?? '';
					                $link_inner    = $tabinn['link_inner'] ?? [];

					                $link_url    = $link_inner['url'] ?? '';
					                $link_title  = $link_inner['title'] ?? '';
					                $link_target = !empty($link_inner['target']) ? $link_inner['target'] : '_self';

					                echo '<div class="acc-box">';
					                    if ($link_url) {
					                        echo '<a href="'.esc_url($link_url).'" class="fulAnchor"></a>';
					                    }
					                    echo '<div class="post-cont">';
					                        if ($title_inner) {
					                            echo '<h3>'.esc_html($title_inner).'</h3>';
					                        }
					                        if ($content_inner) {
					                            echo $content_inner;
					                        }
					                        if ($link_url && $link_title) {
					                            echo '<a href="'.esc_url($link_url).'" target="'.esc_attr($link_target).'" class="btn">';
					                                echo esc_html($link_title);
					                            echo '</a>';
					                        }
					                    echo '</div>'; // post-cont
				                        echo '<div class="post-img">';
				                        	if($image_inner){
					                            echo wp_get_attachment_image($image_inner, 'full');
				                        	} else {
				                        		echo '<img src="'.esc_url(wp_get_attachment_url(1183)).'" alt="'.get_the_title().'" class="default"/>';
				                        	}
				                        echo '</div>';
					                echo '</div>'; // acc-box
					            }

					        echo '</div>'; // tab-acc-lists
					    echo '</div>'; // tab-pane
					}
				echo '</div>'; // tabs-content
			}
		echo '</div>'; #tabsWrapper
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

	    // mobile script
	    let itemsToShow = 15;
		let boxes = $('.tab-pane.all .acc-box');
		// Initially show first 6
		boxes.hide().slice(0, itemsToShow).show();
		$('.mobLoadMore a').click(function (e) {
		    e.preventDefault();
		    // Show next 6 hidden items
		    boxes.filter(':hidden').slice(0, itemsToShow).slideDown();
		    // If all items are visible → hide button
		    if (boxes.filter(':hidden').length === 0) {
		        $(this).text('No more items!')
		    }
		});

	});


</script>