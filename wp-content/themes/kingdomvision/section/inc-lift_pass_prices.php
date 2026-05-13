<?php
$description        = $section['description'] ?? '';
$pass_prices_table  = $section['pass_prices_table'] ?? [];
$content_with_image = $section['content_with_image'] ?? [];

echo '<section ' . SectionAttributes($section, 'full-section lift-pass-prices') . ' ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo '<div class="lpp-wrap">';

            echo TitleFromSection($section);

            if (!empty($description)) {
                echo '<div class="desc">' . $description . '</div>';
            }

            /* =========================
               PASS PRICES TABLE
            ========================== */
            if (!empty($pass_prices_table)) {

			    $columns = [
			        'column_1' => 'content_column_1',
			        'column_2' => 'content_column_2',
			        'column_3' => 'content_column_3',
			        'column_4' => 'content_column_4',
			    ];

			    // Find max rows (safe)
			    $max_rows = 0;
			    foreach ($columns as $col => $repeater) {
			        if (!empty($pass_prices_table[$col][$repeater])) {
			            $max_rows = max($max_rows, count($pass_prices_table[$col][$repeater]));
			        }
			    }

			    if ($max_rows > 0) {

			        echo '<div class="pass-prices-table-wrap">';
			        echo '<table class="pass-prices-table">';

			        /* ---------- TABLE HEAD ---------- */
			        echo '<thead><tr>';
			        foreach ($columns as $col => $repeater) {
			            echo '<th>';
			                echo esc_html($pass_prices_table[$col]['title'] ?? '');
			            echo '</th>';
			        }
			        echo '</tr></thead>';

			        /* ---------- TABLE BODY ---------- */
			        echo '<tbody>';

			        for ($i = 0; $i < $max_rows; $i++) {

			            echo '<tr>';

			            foreach ($columns as $col => $repeater) {
			                $cell = $pass_prices_table[$col][$repeater][$i]['text'] ?? '';
			                echo '<td>' . $cell . '</td>';
			            }

			            echo '</tr>';
			        }

			        echo '</tbody>';
			        echo '</table>';
			        echo '</div>';
			    }
			}

            /* =========================
               CONTENT WITH IMAGE
            ========================== */
            if (!empty($content_with_image)) {

                echo '<div class="content-with-image">';

                // Content
                if (!empty($content_with_image['content_box'])) {
                    echo '<div class="cwi-content">';
                        echo $content_with_image['content_box'];
                    echo '</div>';
                }

                // Image (IMG TAG – SAFE)
                if (!empty($content_with_image['image_box'])) {

                    $image = $content_with_image['image_box'];
                    $img_url = '';
                    $img_alt = '';

                    if (is_array($image)) {
                        $img_url = $image['url'] ?? '';
                        $img_alt = $image['alt'] ?? '';
                    } elseif (is_numeric($image)) {
                        $img_url = wp_get_attachment_url($image);
                        $img_alt = get_post_meta($image, '_wp_attachment_image_alt', true);
                    } else {
                        $img_url = $image;
                    }

                    if (!empty($img_url)) {
                        echo '<div class="cwi-image">';
                            echo '<img src="' . esc_url($img_url) . '" alt="' . esc_attr($img_alt) . '" loading="lazy">';
                        echo '</div>';
                    }
                }

                echo '</div>';
            }

        echo '</div>';
    echo '</div>';
echo '</section>';
?>
