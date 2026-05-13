<?php
/**
 * Helper functions for accommodation filters configuration
 * Location: wp-content/themes/jse-kingdomvision/section/accommodation-config.php
 */

// ============================================================================
// ACCOMMODATION CONFIGURATION
// ============================================================================

/**
 * Get bedroom options
 * @return array
 */
function get_accommodation_bedroom_options() {
    return [
        'studio' => 'Studio',
        '1'      => '1',
        '2'      => '2',
        '3'      => '3',
        '4'      => '4',
        '5'      => '5',
        '6'      => '6',
        '7'      => '7',
        '8'      => '8',
        '9'      => '9',
        '10'     => '10+',
    ];
}

/**
 * Get accommodation type options
 * @return array
 */
function get_accommodation_type_options() {
    
    /* return all temrs for "property_types" in slug => label */
    $terms = get_terms([
        'taxonomy'   => 'property_types',
        'hide_empty' => true,
    ]);

    $options = [];
    foreach ($terms as $term) {
        $options[$term->slug] = $term->name;   
    }

    return $options;
}

/**
 * Get area options
 * @return array
 */
function get_accommodation_area_options() {
    return [
        'hirafu'         => 'Hirafu Village',
        'izumikyo'       => 'Izumikyo',
        'kabayama'       => 'Kabayama',
        'niseko-village' => 'Niseko Village / Higashiyama',
        'hanazono'       => 'Hanazono',
        'annupuri'       => 'Annupuri',
        'happo-village'  => 'Happo One Village',
        'wadano'         => 'Happo One Wadano no Mori',
        'echoland'       => 'Echoland',
        'misorano'       => 'Misorano',
        'hakuba47'       => 'Hakuba 47',
        'hakuba-station' => 'Hakuba Station',
        'goryu'          => 'Goryu',
        'iwatake'        => 'Iwatake',
        'tsugaike'       => 'Tsugaike',
        'norikura'       => 'Norikura',
        'cortina'        => 'Cortina',
        'ochikura'       => 'Ochikura',
        'kitanomine'     => 'Kitanomine Zone',
        'furano'         => 'Furano Zone',
        'ski-in-out'     => 'Ski In Ski Out',
        'off-beaten'     => 'Off the Beaten Track',
    ];
}

/**
 * Parse category from request
 * @param mixed $category Category object or ID
 * @return array
 */
function parse_accommodation_category($category) {
    $result = [
        'id'   => '',
        'slug' => '',
        'name' => '',
    ];

    if (!$category) {
        return $result;
    }

    if (is_object($category)) {
        $result['id']   = (int) $category->term_id;
        $result['slug'] = $category->slug;
        $result['name'] = $category->name;
    } else {
        $term = get_term((int) $category);
        if ($term && !is_wp_error($term)) {
            $result['id']   = (int) $term->term_id;
            $result['slug'] = $term->slug;
            $result['name'] = $term->name;
        }
    }

    return $result;
}

/**
 * Get URL path segments
 * @return array
 */
function get_url_path_segments() {
    global $wp;
    $absolute_url = home_url(add_query_arg([], $wp->request));
    $relative_url = wp_make_link_relative($absolute_url);
    $segments     = array_filter(explode('/', $relative_url));
    return array_slice($segments, -3);
}

/**
 * Get filter input values from POST/GET
 * @return array
 */
function get_filter_input_values() {
    $segments = get_url_path_segments();
    [$resort, $acc, $area] = array_pad($segments, 3, null);

    return [
        'bedrooms'    => $_POST['bedrooms'] ?? [],
        'areas'       => $_POST['area'] ?? [],
        'resort'      => isset($_POST['resort']) ? str_replace('-accommodation', '', $_POST['resort']) : $resort,
        'types'       => $_POST['type'] ?? [],
        'price_min'   => isset($_POST['price_min']) && $_POST['price_min'] !== '' ? (int) $_POST['price_min'] : 0,
        'price_max'   => isset($_POST['price_max']) && $_POST['price_max'] !== '' ? (int) $_POST['price_max'] : 1000000,
    ];
}

/**
 * Get resort terms (for filter dropdown)
 * @return array
 */
function get_resort_terms() {
    return get_terms([
        'taxonomy'   => 'accommodation-cat',
        'hide_empty' => true,
        'parent'     => 0,
    ]);
}

/**
 * Should display resort filter?
 * @param string $resort
 * @return boolean
 */
function should_display_resort_filter($resort) {
    return $resort && in_array($resort, ['accommodation', 'offers'], true);
}

/**
 * Should display base area filter?
 * @param string $resort
 * @return boolean
 */
function should_display_base_area_filter($resort) {
    return $resort && ($resort !== 'accommodation' && strpos($resort, 'accommodation') === false);
}
