<?php
    $jumplink = $section["jumplink"] ?? [];
    echo '<section class="full-section jumplinks" id="jumplink-nav">';
        echo '<div class="container">';
            if (!empty($jumplink)) {
    			echo '<a class="mob_button" href="javascript:;">Jump to:</a>';
                echo '<div class="mobJumplinkWrapper">';
	                echo '<div class="jumplink-wrapper">';
	                foreach ($jumplink as $item) {
	                    $link = $item["link"] ?? "";
	                    if (!empty($link)) {
	                        $url = $link["url"] ?? "";
	                        $title = $link["title"] ?? "";
	                        $target = !empty($link["target"]) ? $link["target"] : "_self";
	                        echo '<a class="jumplink-item"href="' .
	                            esc_url($url) .
	                            '"target="' .
	                            esc_attr($target) .
	                            '">';
	                        echo esc_html($title);
	                        echo "</a>";
	                    }
	                }
	                echo "</div>";
                echo "</div>";
            }
        echo "</div>";
    echo "</section>";
?>


<script>
jQuery(function($){

    let isScrolling = false;

    // Mobile button toggle
    $(document).on('click', 'a.mob_button', function(){
        $(this).toggleClass('active');
        $(this).parents(".jumplinks").find(".mobJumplinkWrapper").slideToggle();
    });

    // Click on jumplink
    $(document).on('click', '.jumplinks .jumplink-wrapper a', function(e){
        e.preventDefault();

        isScrolling = true;

        let $this = $(this);

        $('.jumplinks .jumplink-wrapper a').removeClass('active');
        $this.addClass('active');

        // Mobile close
        if ($(window).width() <= 767) {
            $this.parents('.jumplinks').find(".mobJumplinkWrapper").slideUp();
        }

        // Change button text
        let selectedText = $this.text();
        $this.parents('.jumplinks').find(".mob_button").text(selectedText);

        let currLink = $this.attr('href');
        let targetID = currLink.replace('#', '');
        let $section = $(`section[id="${targetID}"]`);

        if ($section.length) {
            let minusTop = $('.jumplinks').innerHeight() + $('header').innerHeight();

            $('html, body').stop().animate({
                scrollTop: $section.offset().top - minusTop
            }, 400, function(){
                setTimeout(() => { isScrolling = false; }, 150);
            });
        }
    });

    // Scroll active
    $(window).on('scroll', function () {

        if (isScrolling) return;

        let scrollPos = $(window).scrollTop();
        let headerHeight = $('.jumplinks').innerHeight() + $('header').innerHeight();

        $('.jumplinks .jumplink-wrapper a').each(function () {

            let currLink = $(this);
            let ref = currLink.attr('href');
            let targetID = ref.replace('#', '');
            let $section = $(`section[id="${targetID}"]`);

            if ($section.length) {

                let sectionTop = $section.offset().top - headerHeight;
                let sectionBottom = sectionTop + $section.outerHeight();

                if (scrollPos >= sectionTop && scrollPos < sectionBottom) {
                    $('.jumplinks .jumplink-wrapper a').removeClass('active');
                    currLink.addClass('active');
                }
            }
        });
    });

});
</script>