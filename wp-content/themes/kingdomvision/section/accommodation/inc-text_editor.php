<?php
$editor = $section['editor'];

echo '<section ' . SectionAttributes($section, 'full-section text-editor') . ' ' . BackgroundFromSection($section) . '>';
    echo '<div class="container">';
        echo TitleFromSection($section);
        if(!empty($editor)){
            echo WysiwygReadMoreLess($editor,  'editor');
        }
        echo '</div>';
    echo '</div>'; #container
echo '</section>'; #text-editor
?>