<?php
$calendar       = $section['calendar'] ?? [];
$ski_season_names_and_colors = $section['ski_season_names_and_colors'] ?? [];
$left_content   = $section['left_content'] ?? '';
$right_content  = $section['right_content'] ?? '';
$button         = $section['button'] ?? '';

$months = [
    'jan' => 'Jan','feb' => 'Feb','mar' => 'Mar','apr' => 'Apr',
    'may' => 'May','jun' => 'Jun','jul' => 'Jul','aug' => 'Aug',
    'sep' => 'Sep','oct' => 'Oct','nov' => 'Nov','dec' => 'Dec',
];

/* ================= ROW KEYS ================= */
$rows = [
    'color-1',
    'color-2',
    'color-3',
    'color-4',
];

/* ================= DYNAMIC COLORS FROM REPEATER ================= */
$colors = [];

if (!empty($ski_season_names_and_colors)) {
    $index = 1;
    foreach ($ski_season_names_and_colors as $item) {
        $color_value = $item['ski_season_color'] ?? '';
        if (!empty($color_value)) {
            $colors['color-' . $index] = $color_value;
        }
        $index++;
    }
}

echo '<section ' . SectionAttributes($section, 'full-section ski-season-timeline') . ' ' . BackgroundFromSection($section) . '>';
echo '<div class="container">';
echo TitleFromSection($section);

echo '<div class="sst-wrap">';

/* ================= CALENDAR ================= */
if (!empty($calendar)) {

    echo '<div class="sst-calendar">';

    /* Header */
    echo '<div class="sst-header">';
    foreach ($months as $month_label) {
        echo '<div class="month" data-short="' . esc_attr(substr($month_label, 0, 1)) . '">' . esc_html($month_label) . '</div>';
    }
    echo '</div>';

    /* Rows */
    foreach ($rows as $row_key) {

        echo '<div class="sst-row">';

        foreach ($months as $month_key => $label) {

            $data   = $calendar[$month_key] ?? [];
            $events = $data['select_event'] ?? [];

            $has_none = is_array($events) && in_array('none', $events, true);
            $active   = is_array($events) && in_array($row_key, $events, true) && !$has_none;

            // convert color-1 → color_1 (radio field name)
            $toggle_key = str_replace('-', '_', $row_key);

            $col1 = false;
            $col2 = false;

            if ($active) {

                // Radio field value (early-month / full-month / end-month)
                $radio_value = $data[$toggle_key] ?? '';

                if ($radio_value === 'early-month') {
                    $col1 = true;
                }

                if ($radio_value === 'full-month') {
                    $col1 = true;
                    $col2 = true;
                }

                if ($radio_value === 'end-month') {
                    $col2 = true;
                }
            }

            echo '<div class="month-cell">';
                echo '<span class="cell" style="' . ($col1 && !empty($colors[$row_key]) ? 'background:' . esc_attr($colors[$row_key]) : '') . '"></span>';
                echo '<span class="cell" style="' . ($col2 && !empty($colors[$row_key]) ? 'background:' . esc_attr($colors[$row_key]) : '') . '"></span>';
            echo '</div>';

        }

        echo '</div>';
    }

    echo '</div>'; // sst-calendar
}

/* ================= LEGEND ================= */
if (!empty($ski_season_names_and_colors)) {
    echo '<div class="sst-legend">';
        foreach ($ski_season_names_and_colors as $item) {
            $name  = $item['ski_season_name'] ?? '';
            $color = $item['ski_season_color'] ?? '';
            if (empty($name) || empty($color)) {
                continue;
            }
            echo '<div class="legend-item">';
                echo '<span class="legend-color" style="background-color:' . esc_attr($color) . '"></span>';
                echo '<span class="legend-text">' . esc_html($name) . '</span>';
            echo '</div>';
        }
    echo '</div>';
}

/* ================= CONTENT ================= */
echo '<div class="sst-content">';
    echo '<div class="sst-left">' . $left_content . '</div>';
    echo '<div class="sst-right">' . $right_content . '</div>';
echo '</div>';

/* ================= BUTTON ================= */
if (!empty($button)) {
    echo '<div class="sst-button">';
        echo '<a href="' . esc_url($button['url']) . '" class="btn btn-primary" target="' . esc_attr($button['target'] ?: '_self') . '">';
            echo esc_html($button['title']);
        echo '</a>';
    echo '</div>';
}

echo '</div>'; // sst-wrap
echo '</div>'; // container
echo '</section>';
?>
