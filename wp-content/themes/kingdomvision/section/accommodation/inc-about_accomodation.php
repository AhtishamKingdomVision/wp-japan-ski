<?php

$content = get_field( 'client_quote_desc' ) ? get_field( 'client_quote_desc' ) : get_field( 'quote_desc' );

$parent_cat = str_replace(' Accommodation', '', hz_get_parent_category( get_the_ID() ));
// $ammenites_title = title . post parent categoy . FACILITIES;
// Return nothing if no title
$cat_name = str_replace(' Accommodation', '', hz_get_parent_category( get_the_ID() ));
$ammenites_title = get_the_title();
    if ( ! empty( $cat_name ) && strpos( strtolower( $ammenites_title ), strtolower( $cat_name ) ) !== false ) {
    $ammenites_title .= ' Facilities';
} elseif ( ! empty( $cat_name ) ) {
    $ammenites_title .= ' ' . $cat_name . ' Facilities';
} else {
    $ammenites_title .= ' Facilities';
}

$ammenites = get_the_terms(get_the_ID(), 'property_ammenites');

echo '<section class="full-section about-accomodation" ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo '<div class="flex-wrap">';
            echo '<div class="left-side">';
                echo HZoverviewTitleFromSection($section);
                echo WysiwygReadMoreLess($content);
            echo '</div>'; #left-side
            echo '<div class="right-side">';
                if($ammenites_title){
                    echo '<h3>'.$ammenites_title.'</h3>';
                }
                if($ammenites && !is_wp_error($ammenites)){
                    $count = count($ammenites);
                    echo '<ul class="ammenites" data-ammenites="'.$count.'">';
                        foreach ($ammenites as $term) {
                            $icon = get_field('acc_facility_icon', 'property_ammenites_' . $term->term_id);
                            $label = $term->name;

                            if ($label && !empty($icon) && strtolower($icon) !== 'none') {
                                echo '<li>';
                                    echo '<span class="icon">';
                                        echo $icon;
                                    echo '</span>';
                                    echo '<span class="text">' . esc_html($label) . '</span>';
                                echo '</li>';
                            }
                        }
                    echo '</ul>';
                }
            echo '</div>'; #right-side
        echo '</div>'; #flex-wrap
    echo '</div>'; // container
echo '</section>'; #about-accomodation
?>
