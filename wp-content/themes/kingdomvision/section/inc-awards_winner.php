<?php
$founder   = $section['founder'];
$content   = $section['content'];
$list_area = $section['list_area'];

$awards     = $section['awards'];

$awards_con = $section['awards_content'];

$rating_number = $awards_con['rating_number'];
$rating_star = $awards_con['rating_star'];
$rating_text = $awards_con['rating_text'];

$verified_number = $awards_con['verified_number'];
$verified_text = $awards_con['verified_text'];

$guests_number = $awards_con['guests_number'];
$guests_text = $awards_con['guests_text'];

echo '<section ' . SectionAttributes($section, 'full-section hero') . ' ' . BackgroundFromSection($section) . '>'; // hero start

  echo '<div class="container">'; // container start
    echo '<div class="hero-inner">'; // hero-inner start
    
      // LEFT CONTENT
      echo '<div>'; // left column start

        if($founder){printf('<span class="eyebrow">%s</span>', $founder);}
        echo TitleFromSection($section);

        if($content){ printf('<div class="hero-lead">%s</div>', $content); }

        if($list_area):
          echo '<div class="hero-perks">'; // perks start
            foreach ($list_area as $value) {
            $list = $value['list'];
              if($list){ printf('<div class="perk"><i class="fa-solid fa-circle-check"></i> %s</div>', $list); }
            }
          echo '</div>'; // perks end
        endif;

      echo '</div>'; // left column end


      // RIGHT CONTENT
      echo '<div class="hero-aside">'; // aside start

        if($awards):
            foreach ($awards as $v) {
            $a_title = $v['a_title'];
            $a_tagline = $v['a_tagline'];
                if($a_title){
                    // Award 1
                    echo '<div class="award-badge">'; // badge start
                      echo '<div class="award-badge-icon">🏆</div>';
                      echo '<div class="award-badge-text">';
                        echo '<strong>'.$a_title.'</strong>';
                        echo ''.$a_tagline.'';
                      echo '</div>'; // badge text end
                    echo '</div>'; // badge end
                }
            }
        endif;

        // Rating Card
        echo '<div class="rating-card">'; // rating card start

          echo '<div class="rating-row">'; // rating row start
          if($rating_number){ printf('<div class="rating-score">%s</div>', $rating_number); }

            echo '<div>'; // rating text wrapper start
              echo '<div class="rating-stars '. $rating_star. '">★★★★★</div>';
              if($rating_text){ printf('<div class="rating-label">%s</div>', $rating_text); }
            echo '</div>'; // rating text wrapper end

          echo '</div>'; // rating row end

          echo '<div class="rating-meta">'; // meta start
            if($verified_text){ printf('<div class="rating-meta-item"><strong>%s</strong> %s</div>', $verified_number, $verified_text); }
            if($guests_text){ printf('<div class="rating-meta-item"><strong>%s</strong> %s</div>', $guests_number, $guests_text); }
          echo '</div>'; // meta end

        echo '</div>'; // rating card end

      echo '</div>'; // aside end

    echo '</div>'; // hero-inner end

  echo '</div>'; // container end

echo '</section>'; // hero end
?>