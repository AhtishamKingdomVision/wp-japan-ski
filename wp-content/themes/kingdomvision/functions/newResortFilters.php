<?php

add_shortcode('newResortFilters' , 'newResortFiltersCode');
function newResortFiltersCode(){
	ob_start();

    $terms = get_terms([
        'taxonomy'   => 'accommodation-cat',
        'parent'     => 0,
        'hide_empty' => false,
        'exclude'    => [13, 512],

        // Order by numeric term meta
        'meta_key'   => 'bs_resort_id',
        'orderby'    => 'meta_value_num',
        'order'      => 'ASC',
    ]);

    // Determine current resort based on URL segments
    $current_resort_slug = '';
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    
    if (!is_wp_error($terms) && !empty($terms)) {
        $base_slugs = array_map(function($t) { return str_replace('-accommodation', '', $t->slug); }, $terms);
        foreach ($parts as $part) {
            if (in_array($part, $base_slugs)) {
                $current_resort_slug = $part . '-accommodation';
                break;
            }
        }
    }

	  echo '<div class="search-card js-search-card">';
      echo '<div class="search-row">';

        echo '<div class="sb-field location">';
            echo '<img src="' . get_template_directory_uri() . '/images/location-icon.png" alt="Location">';
            echo '<select class="sb-select js-sb-resort" id="sb-resort" aria-label="Select resort">';
            echo '<option value="" ' . (empty($current_resort_slug) ? 'selected' : '') . '>Resort</option>';
            foreach ($terms as $term) {
                $selected = ($current_resort_slug === $term->slug) ? 'selected' : '';
                $display_name = str_ireplace(' Accommodation', '', $term->name);
                echo '<option value="' . esc_attr($term->slug) . '" ' . $selected . '>' . esc_html($display_name) . '</option>';
            }
          echo '</select>';
        echo '</div>';

        echo '<div class="date-pair">';
          echo '<div class="sb-field" >';
              echo '<img src="' . get_template_directory_uri() . '/images/calender-icon.png" alt="Check In Date">';
              echo '<input class="sb-input js-sb-checkin" type="text" placeholder="Check In" aria-label="Check-in date" autocomplete="off" />';
          echo '</div>';
          echo '<div class="sb-field" >';
              echo '<img src="' . get_template_directory_uri() . '/images/calender-icon-1.png" alt="Check Out Date">';
              echo '<input class="sb-input js-sb-checkout" type="text" placeholder="Check Out" aria-label="Check-out date" autocomplete="off" />';
          echo '</div>';
        echo '</div>';

        # Guests field with popover 
        echo '<div class="sb-field sb-guests-desktop" onclick="toggleGuests(event, this)">';
            echo '<img src="' . get_template_directory_uri() . '/images/user-icon.png" alt="people">';
            echo '<span class="sb-guests-display empty js-sb-guests-display">Guests</span>';
        echo '</div>';

        # Mobile: inline guests
        echo '<div class="guests-mobile-row">';
          echo '<div class="sb-field">';
            echo '<span class="sb-label">Adults <span class="age-hint">16+</span></span>';
              echo '<input type="number" class="sb-input js-m-adults" min="1" max="20" value="2" onchange="onMobileGuestChange(event, this)" />';
          echo '</div>';
          echo '<div class="sb-field">';
            echo '<span class="sb-label">Children <span class="age-hint">0–15</span></span>';
              echo '<input type="number" class="sb-input js-m-children" min="0" max="15" value="0" onchange="onMobileGuestChange(event, this)" />';
          echo '</div>';
          echo '<div class="sb-field">';
            echo '<span class="sb-label">Infants <span class="age-hint">0–2</span></span>';
              echo '<input type="number" class="sb-input js-m-infants" min="0" max="10" value="0" onchange="onMobileGuestChange(event, this)" />';
          echo '</div>';
        echo '</div>';

        // echo '<button type="button" class="sb-reset" onclick="resetFilters(this)">';
        //   echo 'Reset';
        // echo '</button>';

        echo '<button class="sb-submit" onclick="doSearch(this)">';
          echo '<img src="' . get_template_directory_uri() . '/images/search-icon.png" alt="Search">
          Browse Accommodation';
        echo '</button>';

      echo '</div>'; # search-row

      echo '<div class="guests-popover" id="guests-popover" role="dialog" onclick="event.stopPropagation()">';
        echo '<div class="g-row">';
          echo '<div><span class="g-label">Adults</span><span class="g-sub">Age 16+</span></div>';
          echo '<div class="g-counter">';
              echo '<button class="g-btn js-btn-adults-minus" disabled>−</button>';
              echo '<span class="g-val js-v-adults">2</span>';
              echo '<button class="g-btn js-btn-adults-plus">+</button>';
          echo '</div>';
        echo '</div>';
        echo '<div class="g-row">';
          echo '<div><span class="g-label">Children</span><span class="g-sub">Ages 3–15</span></div>';
          echo '<div class="g-counter">';
              echo '<button class="g-btn js-btn-children-minus" disabled>−</button>';
              echo '<span class="g-val js-v-children">0</span>';
              echo '<button class="g-btn js-btn-children-plus">+</button>';
          echo '</div>';
        echo '</div>';
        echo '<div class="g-row">';
          echo '<div><span class="g-label">Infants</span><span class="g-sub">Ages 0–2</span></div>';
          echo '<div class="g-counter">';
              echo '<button class="g-btn js-btn-infants-minus" disabled>−</button>';
              echo '<span class="g-val js-v-infants">0</span>';
              echo '<button class="g-btn js-btn-infants-plus">+</button>';
          echo '</div>';
        echo '</div>';
      echo '</div>';

    echo '</div>'; # /search-card

	return''.ob_get_clean();

}