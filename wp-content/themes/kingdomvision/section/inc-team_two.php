<?php
$sub_title    = $section['sub_title'];
$content_area = $section['content_area'];
$team_members = $section['team_members'];
$teamMembersOption = get_field('team_members' , 'option');

// TEAM
echo '<section '.SectionAttributes($section, 'full-section team-section').' '.BackgroundFromSection($section).'>'; // section start

  echo '<div class="container">'; // wrap start
    if($sub_title){ printf('<span class="eyebrow">%s</span>', $sub_title); }
    echo TitleFromSection($section);

    if($content_area){ printf('<div class="team-intro">%s</div>', $content_area); }

    echo '<div class="team-grid">'; // grid start
        if($team_members && $teamMembersOption){
            $memberCounts = count($team_members);
            foreach($team_members as $key => $mem){
                $selected_name = $mem['select_member'];
                $member_data = null;

                // Find matching repeater row by name
                foreach ($teamMembersOption as $team_member_row) {
                    if ($team_member_row['name'] === $selected_name) {
                        $member_data = $team_member_row;
                        break;
                    }
                }
                if ($member_data) {
                    $profile_image = $member_data['profile_image'];
                    $name = $member_data['name'];
                    $designation = $member_data['designation'];
                    $profile_description = $member_data['profile_description'];
                    $page_link = $member_data['page_link'];
                    $linkedin_url = $member_data['linkedin_url'];

                      // TEAM CARD START
                        echo '<div class="team-card" role="list" aria-label="Team Members">';
                            if($page_link){
                                echo '<a href="'.esc_url($page_link).'" class="fullAnchor"></a>';
                            }
                            if($profile_image){
                                echo '<div class="team-card-img">';
                                    echo wp_get_attachment_image( $profile_image,'full', false,);
                                echo '</div>';
                            }else{
                                echo '<div class="team-card-img-placeholder"><i class="fa-solid fa-user"></i></div>';
                            }
                            echo '<div class="team-card-body">'; // body start
                              echo '<p class="team-card-name">'.esc_html($name).'</p>';
                              echo '<p class="team-card-role">'.esc_html($designation).'</p>';
                              echo '<div class="team-card-bio">'.mb_strimwidth($profile_description, 0, 200, '...').'</div>';
                              // echo '<div class="team-card-bio">'.wp_trim_words($profile_description, 30, '...').'</div>';
                              if($linkedin_url){
                                  echo '<div class="team-card-footer">'; // footer start
                                    echo '<a href="'.$linkedin_url.'" target="_blank" class="team-card-linkedin"><i class="fa-brands fa-linkedin"></i> LinkedIn</a>';
                                  echo '</div>'; // footer end
                              }
                            echo '</div>'; // body end
                        echo '</div>'; // team card end
                }

            }

        }
    echo '</div>'; // grid end
  echo '</div>'; // container end
echo '</section>'; // section end
?>
