<?php
$prd_sku = get_field('prd_sku');
echo '<section  ' . SectionAttributes($section, 'full-section prop-reviews-section') . ' aria-labelledby="product-review-title" ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo '<div class="prop-reviews-head">';
          echo TitleFromSection($section);  
          echo '<span class="prop-reviews-meta"><strong>★ 4.9</strong> from <span class="reviewCounts">87</span> verified guest reviews</span>';
        echo '</div>';
          
        echo do_shortcode('[kv_product_reviews sku="'. $prd_sku .'"]');
        
    echo '</div>'; // .container
echo '</section>';
?>