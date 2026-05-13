<?php
$team_members = $section['team_members'];
$button = $section['button'];
$teamMembersOption = get_field('team_members' , 'option');
    echo '<section ' . SectionAttributes($section, 'full-section teams') . ' ' . BackgroundFromSection($section) . '>';
        echo '<div class="container">';
            echo TitleFromSection($section);
                if (!empty($button)) :
                    $link_url    = esc_url($button['url'] ?? '#');
                    $link_title  = esc_html($button['title'] ?? 'Learn more');
                    $link_target = esc_attr($button['target'] ?? '_self');

                    echo '<a class="btn" href="'
                        . $link_url
                        . '" target="'
                        . $link_target
                        . '" aria-label="'
                        . $link_title
                        . '">'
                        . $link_title
                        . '</a>';
            endif;
        echo '</div>'; //container

            echo '<div class="team_cover">';
                if($team_members && $teamMembersOption){
                    $memberCounts = count($team_members);
                    echo '<div class="teamWrapper '.($memberCounts > 4 ? 'activeSlider' : '').'" role="list" aria-label="Team Members">';
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

                                echo '<div class="teamItem" role="listitem" aria-labelledby="member-' . esc_attr($key) . '-name">';
                                    if($page_link){
                                        echo '<a href="'.esc_url($page_link).'" class="fullAnchor"></a>';
                                    }
                                    if($profile_image){
                                        echo '<div class="teamImg">';
                                            echo wp_get_attachment_image( $profile_image,'full', false,);
                                        echo '</div>'; #teamImg
                                    }
                                    if($name || $designation){
                                        echo '<div class="teamTitle">';
                                            if($name){
                                                echo '<h3>'.esc_html($name).'</h3>';
                                            }
                                            if($designation){
                                                echo '<p>'.esc_html($designation).'</p>';
                                            }
                                        echo '</div>'; #teamTitle
                                    }
                                    if($profile_description){
                                        echo '<div class="teamDesc">';
                                            echo mb_strimwidth($profile_description, 0, 200, '...');
                                        echo '</div>'; #teamDesc
                                    }
                                echo '</div>'; #teamItem
                            }

                        }
                    echo '</div>'; #teamWrapper
                }
            echo '</div>'; //team_cover
    echo '</section>';