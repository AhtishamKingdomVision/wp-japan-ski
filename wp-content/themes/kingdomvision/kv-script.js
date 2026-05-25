function setCookie(e, t, n) {
    var o = new Date;
    o.setTime(o.getTime() + 24 * n * 60 * 60 * 1e3);
    var i = "expires=" + o.toUTCString();
    document.cookie = e + "=" + t + ";" + i + ";path=/"
}

function getCookie(e) {
    for (var t = e + "=", n = decodeURIComponent(document.cookie).split(";"), o = 0; o < n.length; o++) {
        for (var i = n[o];
            " " == i.charAt(0);) i = i.substring(1);
        if (0 == i.indexOf(t)) return i.substring(t.length, i.length)
    }
    return ""
}

function capitalizeFirstLetter([first = '', ...rest]) {
    return [first.toUpperCase(), ...rest].join('');
}

function isMobile(){

    const isMobile = /Mobi|Android|iPhone/i.test(navigator.userAgent);
    const isMobileSize = window.matchMedia("(max-width: 768px)").matches;

    if (isMobile && isMobileSize) {
        return true;
    }
    else{
        return false;
    }
}

jQuery(function ($) {
    console.log('script loaded');
    // Get Theme Path From Function
    var themeUrl = kingdomVision.themeUrl;

    function closestParent(child, className) {
        if (!child || child == document) {
            return null;
        }
        if (child.classList.contains(className)) {
            return child;
        } else {
            return closestParent(child.parentNode, className);
        }
    }

    // Child Age Work
    $(document).on('change', '#input_1_10, #input_4_10 ', function (e) {
        console.log('change child');
        let noOfChilds = $(this).val();
        if (noOfChilds == '')
            return;

        if (noOfChilds == '0') {
            $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {
                let val = $(value).val();
                let child = $(value).data('child');
                $('#gform_1 .' + child + ' input').val(val);
            });
            return;
        }

        $('section.child_age').addClass('active');
        $('section.child_age input').val('');
        $('section.child_age .ch_inn ul li').show();
        // DOM ELEMENTS
        $.each($('section.child_age .ch_inn ul li'), function (index, value) {
            if ((index + 1) > noOfChilds) {
                $(value).hide();
            }
        });
    });

    $(document).on('click', 'section.child_age .age_confirm , section.child_age .child_close', function (e) {
        e.preventDefault();
        let condition = true;
        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {
            if ($(value).val() == '' || $(value).val() == '0') {
                condition = false;
                return;
            }
        });

        if (!condition) {
            $('section.child_age .age-error-box').show()
            return;
        }

        $.each($('section.child_age .ch_inn ul li:visible select'), function (index, value) {
            let val = $(value).val();
            let child = $(value).data('child');
            $('#gform_1 .' + child + ' input').val(val);
            $('#gform_4 .' + child + ' input').val(val);
        });
        $('section.child_age').removeClass('active');
    });

    // Add Class on scroll
    $(window).scroll(function () {
        if ($(document).scrollTop() > 100) {
            $('.main-header , header.newHeader').addClass('stickyHeader');
        } else {
            $('.main-header , header.newHeader').removeClass('stickyHeader');
        }
    });

    if ($('.gallery_carousel').length >= 1) {
        $('.gallery_carousel').slick({
            infinite: false,
            slidesToShow: 1,
            slidesToScroll: 1,
            draggable: true,
            autoplay: true,
            autoplaySpeed: 2000,
            dots: true,
            arrows: true,
            adaptiveHeight: true,
            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>'
        });
    }

    if ($('.fb_carousel').length >= 1) {
        $('.fb_carousel').slick({
            infinite: false,
            slidesToShow: 4,
            slidesToScroll: 1,
            draggable: true,
            // autoplay: true,
            // autoplaySpeed: 2000,
            dots: true,
            arrows: false,
            adaptiveHeight: true,
            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>'
        });
    }

    // reviews-carousel
    if ($('.reviews-carousel').length) {
        $('.reviews-carousel').slick({
            infinite: true,
            slidesToShow: 1,
            slidesToScroll: 1,
            draggable: true,
            adaptiveHeight: true,

            // DESKTOP DEFAULT
            arrows: true,
            dots: false,

            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',

            responsive: [
                {
                    breakpoint: 480,
                    settings: {
                        arrows: false,
                        dots: true,
                        slidesToShow: 1,
                        slidesToScroll: 1
                    }
                }
            ]
        });
    }

    // ---- DEFAULT: FIRST 2 OPEN ----
    const $triggers = $(".accor_trigger");

    // ---- DEFAULT: FIRST 2 OPEN ----
    $triggers.each(function (index) {
        const $btn = $(this);
        const panelID = $btn.attr("aria-controls");
        const $content = $("#" + panelID);

        if (index < 0) {
            $btn.attr("aria-expanded", "true")
                .addClass("active");

            $content.prop("hidden", false);
        } else {
            $btn.attr("aria-expanded", "false");
            $content.prop("hidden", true);
        }
    });

    // ---- CLICK TOGGLE ----
    $triggers.on("click", function () {

        const $btn = $(this);
        const panelID = $btn.attr("aria-controls");
        const $content = $("#" + panelID);

        const isOpen = $btn.attr("aria-expanded") === "true";

        // Toggle aria + hidden
        $btn.attr("aria-expanded", !isOpen);
        $content.prop("hidden", isOpen);

        // Toggle active class
        if (!isOpen) {
            $btn.addClass("active");
        } else {
            $btn.removeClass("active");
        }

    });

    // OFFERS SLIDER: 
    if ($('section.unbeatable_offers .offers').length) {
        $('section.unbeatable_offers .offers').slick({
            slidesToShow: 4.5,
            slidesToScroll: 1,
            infinite: false,
            arrows: true,
            dots: false,
            speed: 500,
            swipeToSlide: true,
            cssEase: 'ease',
            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',
            responsive: [
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3.5
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2.2
                    }
                },
                {
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1.2
                    }
                }
            ]
        });
    }

    if ($('.activeSlider').length) {
        $('.activeSlider').slick({
            slidesToShow: 3.5,
            slidesToScroll: 1,
            infinite: false,
            arrows: true,
            dots: false,
            speed: 500,
            swipeToSlide: true,
            cssEase: 'ease',
            prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
            nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',
            responsive: [
                {
                    breakpoint: 1200,
                    settings: {
                        slidesToShow: 3.5
                    }
                },
                {
                    breakpoint: 992,
                    settings: {
                        slidesToShow: 2.2
                    }
                },
                {
                    breakpoint: 767,
                    settings: {
                        slidesToShow: 1.2
                    }
                }
            ]
        });
    }

    // $('.activeSlider').slick({
    //     slidesToShow: 3.4,
    //     slidesToScroll: 1,
    //     infinite: false,
    //     arrows: true,
    //     dots: false,
    //     speed: 400,
    //     swipeToSlide: true,
    //     cssEase: 'ease',
    //     prevArrow: '<button type="button" class="slick-prev"><img src="' + themeUrl + '/images/left_arrow.svg" alt="Previous"></button>',
    //     nextArrow: '<button type="button" class="slick-next"><img src="' + themeUrl + '/images/right_arrow.svg" alt="Next"></button>',
    //     responsive: [
    //         {
    //             breakpoint: 1200,
    //             settings: {
    //                 slidesToShow: 3.5
    //             }
    //         },
    //         {
    //             breakpoint: 800,
    //             settings: {
    //                 slidesToShow: 2.5
    //             }
    //         },
    //         {
    //             breakpoint: 480,
    //             settings: {
    //                 slidesToShow: 1.5
    //             }
    //         }
    //     ]
    // });

    // Wysiwyg Read More Read Less
    $(".contentWrapper").each(function () {
        let $section = $(this);
        let $readMore = $section.find(".wysiwygReadMore");
        let $readLess = $section.find(".wysiwygReadLess");
        let $fullContent = $section.find(".wysiwygFullContent");
        let $shortContent = $section.find(".wysiwygShortContent");

        // Initially hide the "Read Less" button
        $readLess.hide();

        // READ MORE
        $readMore.on("click", function (e) {
            e.preventDefault();
            $fullContent.stop(true, true).slideDown(300); // show hidden content
            $shortContent.addClass('expend');
            $readMore.hide();
            $readLess.show();
        });

        // READ LESS
        $readLess.on("click", function (e) {
            e.preventDefault();
            $fullContent.stop(true, true).slideUp(300); // hide again
            $shortContent.removeClass('expend');
            $readMore.show();
            $readLess.hide();
        });
    });


    // $('.about-accomodation').each(function () {
    //     const $wrapper = $(this);
    //     const $list = $wrapper.find('ul.ammenites');
    //     const $items = $list.find('li');

    //     // Get limit from data attribute "data-item" (better than "item")
    //     const limit = parseInt($wrapper.attr('data-ammenites')) || 5; // default 10 if not set

    //     // Only apply if there are more than limit items
    //     if ($items.length > limit) {
    //         // Hide items after limit
    //         $items.slice(limit).hide();

    //         // Create and insert Read More button
    //         const $toggleBtn = $('<div class="rlmore"><a href="#" class="read-toggle">View All </a> </div>');
    //         $list.after($toggleBtn);

    //         // Attach click handler
    //         $('.rlmore a.read-toggle').on('click', function (e) {
    //             e.preventDefault(); // prevent page jump

    //             const $hiddenItems = $items.slice(limit);

    //             if ($hiddenItems.is(':visible')) {
    //                 $hiddenItems.slideUp();
    //                 $(this).closest('.rlmore').removeClass('active');
    //                 $(this).text('Read More');
    //             } else {
    //                 $hiddenItems.slideDown();
    //                 $(this).closest('.rlmore').addClass('active');
    //                 $(this).text('Read Less');
    //             }
    //         });
    //     }
    // });

    $(document).ready(function ($) {
        $('.kv-copy-link').on('click', function (e) {
            e.preventDefault();

            var link = $(this).data('link');
            var $btn = $(this);

            // Create temp input
            var $temp = $('<input>');
            $('body').append($temp);
            $temp.val(link).select();
            document.execCommand('copy');
            $temp.remove();

            // Visual feedback
            $btn.html('<i class="fa-solid fa-check"></i>').addClass('copied');

            setTimeout(function () {
                $btn.html('<i class="fa-solid fa-link"></i>').removeClass('copied');
            }, 1500);
        });

    });

    $(document).ready(function ($) {
        $('a[href^="#"]').on('click', function (e) {
            var id = $(this).attr('href');

            if (id === '#' || id === '') return;
            var target = $(id);
            if (target.length) {
                e.preventDefault();
                var offset = target.offset().top - 40; // sirf scroll offset
                $('html, body').stop().animate(
                    { scrollTop: offset },
                    500
                );
            }
        });

    });

    /** Blog Shortcode Script */
    if ($('.kv-posts-wrapper').length) {
        $('.kv-posts-wrapper').each(function () {

            const $wrap = $(this);
            const perPage = $wrap.data('per-page');
            const fixedCat = $wrap.data('fixed-cat');

            let page = 1;
            let busy = false;

            function loadPosts(reset = false) {

                if (busy) return;
                busy = true;

                if (reset) {
                    page = 1;
                    $wrap.find('.js-kv-posts').empty();
                }

                $.post(kv_object.ajaxurl, {
                    action: 'kv_filter_posts',
                    page: page,
                    per_page: perPage,
                    category: fixedCat || $wrap.find('.js-kv-category').val(),
                    sort: $wrap.find('.js-kv-sort').val()
                }, function (res) {

                    if (reset) {
                        $wrap.find('.js-kv-posts').html(res.html);
                    } else {
                        $wrap.find('.js-kv-posts').append(res.html);
                    }

                    if (!res.has_more) {
                        $wrap.find('.js-kv-loadmore-wrap').hide();
                    } else {
                        $wrap.find('.js-kv-loadmore-wrap').show();
                    }

                    busy = false;
                });
            }

            /* Initial load */
            loadPosts(true);

            /* Filter change */
            $wrap.on('change', '.js-kv-category, .js-kv-sort', function () {
                loadPosts(true);
            });

            /* Load more */
            $wrap.on('click', '.js-kv-loadmore', function (e) {
                e.preventDefault();
                page++;
                loadPosts();
            });

        });
    }


    /** Blog Shortcode Script */

    $(document).on('click', '.quote_toggle', function (e) {
        e.preventDefault();
        $('.mob_quote_form').addClass('active');
        $('body').addClass('quote-open');
    });

    $(document).on('click', '.close_mob_quote_form', function (e) {
        e.preventDefault();
        $('.mob_quote_form').removeClass('active');
        $('body').removeClass('quote-open');
    });

    if ($(window).width() <= 767) {
        $('.mob_quote_form').appendTo('.content-wrapper');
    }


    // OPEN NAV
    $('.menu_toggle, .search_toggle').on('click', function (e) {
        e.preventDefault();
        $('.nav_area').addClass('is-open');
        $('body').addClass('is-open');
    });

    // CLOSE NAV
    $('.close_toggle').on('click', function (e) {
        e.preventDefault();
        $('.nav_area').removeClass('is-open');
        $('body').removeClass('is-open');
    });

    // Add arrow to parent menu items
    $('.mobile_menu li.menu-item-has-children > a').after(
        '<div class="trig"><svg width="13" height="8" viewBox="0 0 13 8" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12.6929 1.44357L7.06787 7.06857C6.98948 7.14723 6.89634 7.20964 6.79378 7.25223C6.69122 7.29482 6.58126 7.31674 6.47021 7.31674C6.35916 7.31674 6.24921 7.29482 6.14665 7.25223C6.04409 7.20964 5.95094 7.14723 5.87256 7.06857L0.247557 1.44357C0.0890491 1.28506 -2.36196e-09 1.07008 0 0.845917C2.36196e-09 0.621752 0.0890491 0.406769 0.247557 0.24826C0.406066 0.0897521 0.621049 0.000703278 0.845214 0.000703275C1.06938 0.000703273 1.28436 0.0897521 1.44287 0.24826L6.47092 5.27631L11.499 0.247558C11.6575 0.0890494 11.8725 0 12.0966 0C12.3208 0 12.5358 0.0890494 12.6943 0.247558C12.8528 0.406066 12.9418 0.621049 12.9418 0.845214C12.9418 1.06938 12.8528 1.28436 12.6943 1.44287L12.6929 1.44357Z" fill="white"/></svg></div>');
    $('.mobile_menu').on('click', '.trig', function (e) {
        e.preventDefault();

        const $parentLi = $(this).closest('li');
        const $submenu = $parentLi.children('.sub-menu');

        // Close other open submenus (accordion behaviour)
        $parentLi
            .siblings('.menu-item-has-children')
            .removeClass('open')
            .children('.sub-menu')
            .slideUp(300);

        // Toggle current submenu
        $parentLi.toggleClass('open');
        $submenu.slideToggle(300);
    });


    /** Check in Checkout script */

    const savedResort = localStorage.getItem('sb_resort');
    const savedCheckin = localStorage.getItem('niseko_checkin');
    const savedCheckout = localStorage.getItem('niseko_checkout');
    const savedAdults = parseInt(localStorage.getItem('sb_adults'), 10);
    const savedChildren = parseInt(localStorage.getItem('sb_children'), 10);
    const savedInfants = parseInt(localStorage.getItem('sb_infants'), 10);

    if (savedResort) {
        $('.js-sb-resort').each(function () {
            if (!$(this).val()) {
                $(this).val(savedResort);
            }
        });
    }

    /*new resort function*/
    
    if ($('.js-sb-checkin').length > 0) {
        var chk_in = $('.js-sb-checkin');
        var mindate = localStorage.getItem('mindate') ? localStorage.getItem('mindate') : kv_object.check_start_date;
        chk_in.each(function () {
            const $curr_item = $(this);

            $curr_item
                .addClass('dateDropper')
                .dateDropper({
                    large: 1,
                    largeDefault: 1,
                    preset: false,
                    minDate: mindate,
                    maxDate: kv_object.check_end_date,
                    format: 'd/m/Y',
                    eventSelector: 'click',

                    onChange: function (res) {
                        const dateStr = $curr_item.val();
                        /* Inject helper text once */
                        if (kv_object.date_dropper_content) {
                            const $picker = $('.datedropper .picker .pick-lg');
                            if ($picker.length && !$picker.find('.kv-text').length) {
                                $picker.prepend(
                                    `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                                );
                            }
                        }

                        // Sync all instances
                        $('.js-sb-checkin').val(dateStr);
                        localStorage.setItem('niseko_checkin', dateStr);
                        
                        let minDate =
                        res.date.m + '/' +
                        res.date.d + '/' +
                        res.date.Y;
                        localStorage.setItem('mindate', minDate);
                        /* Enforce minimum nights */
                        if (
                            kv_object.check_min_days_option === '1' &&
                            kv_object.check_min_days !== ''
                        ) {
                            let dt = new Date(minDate);
                            dt.setDate(dt.getDate() + parseInt(kv_object.check_min_days, 10));
                            minDate =
                                (dt.getMonth() + 1) + '/' +
                                dt.getDate() + '/' +
                                dt.getFullYear();
                        }

                        /* Re-init CHECK-OUT */
                        const $checkouts = $('.js-sb-checkout');
                        $checkouts.prop('disabled', false);

                        // We must re-init each checkout instance with the new minDate
                        $checkouts.each(function() {
                            const chk_out = $(this);
                            console.log( chk_out );
                            chk_out
                                .prop('disabled', false)
                                .dateDropper('destroy')
                                .dateDropper({
                                    large: 1,
                                    largeDefault: 1,
                                    minDate: minDate,
                                    maxDate: kv_object.check_end_date,
                                    format: 'd/m/Y',
                                    eventSelector: 'click',
                                    onChange: function () {

                                        console.log( 'w45rtgbvcwe' );
                                        if (kv_object.date_dropper_content) {
                                            const $picker = $('.datedropper .picker .pick-lg');
                                            if ($picker.length && !$picker.find('.kv-text').length) {
                                                $picker.prepend(
                                                    `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                                                );
                                            }
                                        }

                                        const dateStrOut = chk_out.val();
                                        // Sync all instances
                                        $('.js-sb-checkout').each(function() {
                                            const $el = $(this);
                                            if ($el.val() !== dateStrOut) {
                                                if ($el.hasClass('dateDropper')) {
                                                    $el.dateDropper('set', { value: dateStrOut });
                                                }
                                                $el.val(dateStrOut).trigger('change');
                                            }
                                        });
                                        localStorage.setItem('niseko_checkout', dateStrOut);
                                    }
                                });
                        });
                        
                    }
                });

            if (savedCheckin && savedCheckin.length > 0 ) {
                $('.js-sb-checkin').val(savedCheckin).trigger('change');
            }
        });
            
    }

    if ($('.js-sb-checkout').length > 0 ) {

        var $checkout = $('.js-sb-checkout');
        $checkout.each(function () {
            var $ckout_item = $(this);

            $ckout_item
                .prop('disabled', true)
                .addClass('dateDropper')
                .dateDropper({
                    large: 1,
                    largeDefault: 1,
                    minDate: kv_object.check_start_date,
                    maxDate: kv_object.check_end_date,
                    format: 'd/m/Y',
                    eventSelector: 'click',
                    onChange: function () {

                        if (kv_object.date_dropper_content) {
                            const $picker = $('.datedropper .picker .pick-lg');
                            if ($picker.length && !$picker.find('.kv-text').length) {
                                $picker.prepend(
                                    `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                                );
                            }
                        }

                        const dateStrOut = $ckout_item.val();
                        $('.js-sb-checkout').each(function() {
                            const $el = $(this);
                            if ($el.val() !== dateStrOut) {
                                if ($el.hasClass('dateDropper')) {
                                    $el.dateDropper('set', { value: dateStrOut });
                                }
                                $el.val(dateStrOut).trigger('change');
                            }
                        });
                        localStorage.setItem('niseko_checkout', dateStrOut);
                    }
                });
            if (savedCheckout && $('.js-sb-checkout').length) {
                $('.js-sb-checkout').val(savedCheckout).trigger('change');
            }
        });
    }
    /*new resort function*/ 
    
    if ($('#sc-check-in').length === 1) {
        $('#sc-check-in')
            .addClass('dateDropper')
            .dateDropper({
                large: 1,
                largeDefault: 1,
                preset: false,
                minDate: kv_object.check_start_date,
                maxDate: kv_object.check_end_date,
                format: 'd/m/Y',
                eventSelector: 'click',

                onChange: function (res) {
                    const dateStr = $(this).val();
                    /* Inject helper text once */
                    if (kv_object.date_dropper_content) {
                        const $picker = $('.datedropper .picker .pick-lg');
                        if ($picker.length && !$picker.find('.kv-text').length) {
                            $picker.prepend(
                                `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                            );
                        }
                    }

                    let minDate =
                        res.date.m + '/' +
                        res.date.d + '/' +
                        res.date.Y;

                    /* Enforce minimum nights */
                    if (
                        kv_object.check_min_days_option === '1' &&
                        kv_object.check_min_days !== ''
                    ) {
                        let dt = new Date(minDate);
                        dt.setDate(dt.getDate() + parseInt(kv_object.check_min_days, 10));
                        minDate =
                            (dt.getMonth() + 1) + '/' +
                            dt.getDate() + '/' +
                            dt.getFullYear();
                    }

                    /* Re-init CHECK-OUT */
                    $('#sc-check-out')
                        .prop('disabled', false)
                        .dateDropper('destroy')
                        .dateDropper({
                            large: 1,
                            largeDefault: 1,
                            minDate: minDate,
                            maxDate: kv_object.check_end_date,
                            format: 'd/m/Y',
                            eventSelector: 'click',
                            onchange: function () {

                                if (kv_object.date_dropper_content) {
                                    const $picker = $('.datedropper .picker .pick-lg');
                                    if ($picker.length && !$picker.find('.kv-text').length) {
                                        $picker.prepend(
                                            `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                                        );
                                    }
                                }
                            }
                        });
                }
            });

        if (savedCheckin && $('#sc-check-in').length) {
            $('#sc-check-in').val(savedCheckin).trigger('change');
        }
    }

    if ($('#sc-check-out').length === 1) {

        $('#sc-check-out')
            .prop('disabled', true)
            .addClass('dateDropper')
            .dateDropper({
                large: 1,
                largeDefault: 1,
                minDate: kv_object.check_start_date,
                maxDate: kv_object.check_end_date,
                format: 'd/m/Y',
                eventSelector: 'click',
                onchange: function () {

                    if (kv_object.date_dropper_content) {
                        const $picker = $('.datedropper .picker .pick-lg');
                        if ($picker.length && !$picker.find('.kv-text').length) {
                            $picker.prepend(
                                `<div class="kv-text">${kv_object.date_dropper_content}</div>`
                            );
                        }
                    }
                }
            });
        if (savedCheckout && jQuery('#sc-check-out').length) {
            jQuery('#sc-check-out').val(savedCheckout).trigger('change');
        }
    }

    $(document).on('mousedown', '.pick-lg li.pick-v', function () {
        $('#sc-check-in').dateDropper('hide');
        $('#sc-check-out').dateDropper('hide');
    
        $('.js-sb-checkin').dateDropper('hide');
        $('.js-sb-checkout').dateDropper('hide');
    });

    $(document).on('click', '#rbCartFooter .rb-proceed-btn', function (e) {
        e.preventDefault();
        var url = base_url + '/confirm-booking/'
        window.open(url, '_blank');
    });
    /** Check in Checkout script */

    $(document).on('click', '.rb-toggle-long-desc', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const $box = jQuery(this).closest('.rb-rateplan-box');
        const $longDesc = $box.find('.rb-long-desc');

        $longDesc.stop(true, true).slideToggle(200);
    });

    async function initFlywireCheckout(sessionId) {

      const sdk = await window.FlywireSDK("fk_VlhaNHRZdnpqRkowOFh2TDloc1pQZz09");
      const elements = await sdk.elements();

      const checkout = await elements.create("payment", {
        sessionId: sessionId,
        displayMode: "container"
      });

      checkout.onEvent("success", (event) => {
        console.log("✅ Payment success", event);
        // DO NOT trust frontend — wait for webhook

        let form = $('#gform_3'),
            payment_id = $('#input_3_17').val(),
            hotel_data = localStorage.getItem('rb_cart');
        $.ajax({
          url: kv_object.ajaxurl,
          method: "POST",
          dataType: "json",
          data: {
            action: "add_other_data_in_fw",
            payload: {
                payment_id: payment_id,
                hotel_data: hotel_data,
            }
          },
          success: function (res) {
            console.log( 'success', res );
          },
            error: function (xhr, exception) {
                var msg = "";
                if (xhr.status === 0) {
                    msg = "Not connect.\n Verify Network." + xhr.responseText;
                } else if (xhr.status == 404) {
                    msg = "Requested page not found. [404]" + xhr.responseText;
                } else if (xhr.status == 500) {
                    msg = "Internal Server Error [500]." +  xhr.responseText;
                } else if (exception === "") {
                    msg = "Requested JSON parse failed.";
                } else if (exception === "timeout") {
                    msg = "Time out error." + xhr.responseText;
                } else if (exception === "abort") {
                    msg = "Ajax request aborted.";
                } else {
                    msg = "Error:" + xhr.status + " " + xhr.responseText;
                }

                console.error( 'error' );
                console.error( msg );
            }
        });

        /*hide flywire and show confirmation message*/
        $('#flywire_box').hide();
        $('.quote_form').trigger('submit');
        $('.booking-confirmation-form').show();
      });

      checkout.onEvent("error", (event) => {
        console.error("❌ Payment error", event);
        console.error(JSON.stringify(event, null, 2));

        $.ajax({
          url: kv_object.ajaxurl,
          method: "POST",
          dataType: "json",
          data: {
            action: "add_other_data_in_fw",
            payload: {
                payment_id: payment_id,
                hotel_data: hotel_data,
            }
          },
          success: function (res) {
            console.log( 'success', res );
          },
            error: function (xhr, exception) {
                var msg = "";
                if (xhr.status === 0) {
                    msg = "Not connect.\n Verify Network." + xhr.responseText;
                } else if (xhr.status == 404) {
                    msg = "Requested page not found. [404]" + xhr.responseText;
                } else if (xhr.status == 500) {
                    msg = "Internal Server Error [500]." +  xhr.responseText;
                } else if (exception === "") {
                    msg = "Requested JSON parse failed.";
                } else if (exception === "timeout") {
                    msg = "Time out error." + xhr.responseText;
                } else if (exception === "abort") {
                    msg = "Ajax request aborted.";
                } else {
                    msg = "Error:" + xhr.status + " " + xhr.responseText;
                }

                console.error( 'error' );
                console.error( msg );
            }
        });
      });

      checkout.mount("flywire_box");
    }

    function getValidFlywireSession() {
        const stored = localStorage.getItem('flywire_session');
        if (!stored) return null;

        const session = JSON.parse(stored);
        
        // Convert the UTC string from Flywire into a JS Date Object
        // JS handles the "+00:00" offset automatically
        const expiryTime = new Date(session.expires_at);
        
        // Get the current time as a Date Object
        const currentTime = new Date();

        // Compare them. If current time is greater than expiry, it's dead.
        if (currentTime >= expiryTime || currentTime.getTime() > (expiryTime.getTime() - 60000)) {
            console.log("Session expired. Clearing storage...");
            localStorage.removeItem('flywire_session');
            return null;
        }

        return session;
    }

    $(document).on('gform_post_render', function(event, formId) {
        if (formId !== 3) return;

        const $form = $(`#gform_${formId}`);
        const $trigger = $('#flywire-trigger');
        // const room_type = kv_roomtype_get();

        // console.log( 'roomtype' );
        // console.log( roomtype );


        // if( room_type !== 'bedbank' ){

            // If the trigger exists, validation "failed" on purpose for payment
            if ($trigger.length > 0) {

                let fw_session_obj = getValidFlywireSession(),
                    fw_session_id = '';
                
                // 1. Hide the generic GF error message so it looks professional
                $('.validation_error').hide(); 

                let firstName = $form.find("input[name='input_11']").val() || "Unknown";
                let lastName = $form.find("input[name='input_12']").val() || "Unknown";
                let email = $form.find("input[name='input_3']").val() || "unknown@example.com";
                let phone = $form.find("input[name='input_13']").val() || "090078601";
                let country = $form.find("select[name='input_5']").val() || "Japan";
                let city = $form.find("input[name='input_8']").val() || "Tokyo Metropolis";
                let postcode = $form.find("input[name='input_9']").val() || "09876";
                let lang = $form.find("select[name='input_6']").val() || "en";
                let address = $form.find("textarea[name='input_10']").val() || "Default Address";
                let deposit = $('#deposit-amount').attr('data-price') || "0";

                $('.fw_total input').attr('value', deposit);

                // 2. Run your existing AJAX to get the session
                if( fw_session_obj === null ){

                    $.ajax({
                      url: kv_object.ajaxurl,
                      method: "POST",
                      dataType: "json",
                      data: {
                        action: "create_flywire_session",
                        payload: {
                          firstName: firstName,
                          lastName: lastName,
                          email: email,
                          phone: phone,
                          address: address,
                          city: city,
                          country: country,
                          postcode: postcode,
                          amount: deposit,
                          lang: lang,
                        }
                      },
                      success: function (res) {
                        if (res.success && res.data.id) {
                            console.log('New session created:', res.data);
                            localStorage.setItem( 'flywire_session', JSON.stringify( res.data ) );
                            fw_session_id = res.data.id;

                            $form.find('input[name="input_17"]').val(fw_session_id);
                            $('.booking-confirmation-form').hide();
                            initFlywireCheckout(fw_session_id);
                        }
                      },
                        error: function (xhr, exception) {
                            var msg = "";
                            if (xhr.status === 0) {
                                msg = "Not connect.\n Verify Network." + xhr.responseText;
                            } else if (xhr.status == 404) {
                                msg = "Requested page not found. [404]" + xhr.responseText;
                            } else if (xhr.status == 500) {
                                msg = "Internal Server Error [500]." +  xhr.responseText;
                            } else if (exception === "") {
                                msg = "Requested JSON parse failed.";
                            } else if (exception === "timeout") {
                                msg = "Time out error." + xhr.responseText;
                            } else if (exception === "abort") {
                                msg = "Ajax request aborted.";
                            } else {
                                msg = "Error:" + xhr.status + " " + xhr.responseText;
                            }

                            console.error( msg );
                        }

                    });
                }
                else{
                    fw_session_id = fw_session_obj.id;

                    $form.find('input[name="input_17"]').val(fw_session_id);
                    $('.booking-confirmation-form').hide();
                    initFlywireCheckout(fw_session_id);
                }
            }
        // }

     /* API work will be here*/   
    });

    $(document).on('click', '.accom-content .enquire_btn, .accom-content .book_btn', function (e) {
        let value = $(this).data('room_id'),
            a = $(this).parents('a'),
            url = a.attr('href');
        localStorage.setItem('go_to_form', value);
        window.location.href = url;
    });

    function hz_ajax_error (xhr, exception) {
        var msg = "";
        if (xhr.status === 0) {
            alert( "Internet not connected.\n Verify Network." );
        } else if (exception === "timeout") {
            alert( "Request time out. \n Please try again");
        } else if (exception === "abort") {
            alert("Ajax request aborted.");
        } else if (xhr.status == 404) {
            msg = "Requested page not found. [404]" + xhr.responseText;
        } else if (xhr.status == 500) {
            msg = "Internal Server Error [500]." +  xhr.responseText;
        } else if (exception === "") {
            msg = "Requested JSON parse failed.";
        } else {
            msg = "Error:" + xhr.status + " " + xhr.responseText;
        }

        console.error( msg );
    }

    function kv_booking_cart_get() {
        return JSON.parse(localStorage.getItem('rb_cart') || '{"items":[]}');
    }

    function kv_booking_cart_set(cart) {
        localStorage.setItem('rb_cart', JSON.stringify(cart));
    }

    function kv_roomtype_get(){
        var cart = kv_booking_cart_get();
        if(cart?.items?.length > 0){
            return cart.items[0].room_type;
        }
    }

    /*slide up dropdown results when clicked outside*/
    $(document).on("click", function(event) {
        var $container = $(".dropdown_results"); // The div you want to slide up

        // If the click is NOT on the container and NOT on a child of the container
        if (!$container.is(event.target) && $container.has(event.target).length === 0) {
            $container.slideUp("fast");
        }
    });

    document.querySelectorAll('.faq-q').forEach(function(btn) {
        btn.addEventListener('click', function() {
          var isOpen = btn.classList.contains('open');
          document.querySelectorAll('.faq-q').forEach(function(b) {
            b.classList.remove('open');
            var a = b.nextElementSibling;
            if (a) a.classList.remove('visible');
          });
          if (!isOpen) {
            btn.classList.add('open');
            var answer = btn.nextElementSibling;
            if (answer) answer.classList.add('visible');
          }
        });
    });

    let heroCard = null;
    jQuery(window).on('scroll', function($) {
        if (!heroCard) {
            heroCard = jQuery('#search-card')[1];
        }
        let threshold = heroCard 
            ? (jQuery(heroCard).offset().top + jQuery(heroCard).outerHeight() - 80) 
            : 300;

        jQuery('header , .mobPopWrapper').toggleClass('showHeadarFilter', jQuery(window).scrollTop() > threshold);
    });


    /* ── Mobile search modal ── */
    $('.mobPopWrapper .openPop').on('click' , function(){
        $('.mobFilterModal').addClass('open');
    })
    $('.closeMobSearch').on('click' , function(){
        $(this).parents('.mobFilterModal').removeClass('open');
    })
    $('.mobFilterModal').on('click', function (e) {
      if ($(e.target).is('.mobFilterModal')) {
        $(this).removeClass('open');
      }
    });

});

document.addEventListener('DOMContentLoaded', function () {

    const initialAdults = parseInt(localStorage.getItem('sb_adults'), 10);
    const initialChildren = parseInt(localStorage.getItem('sb_children'), 10);
    const initialInfants = parseInt(localStorage.getItem('sb_infants'), 10);
 
  document.querySelectorAll('.search-card').forEach(function(card) {
 
    let g = {

                        adults: Number.isFinite(initialAdults) && initialAdults > 0 ? initialAdults : 2,

                        children: Number.isFinite(initialChildren) && initialChildren >= 0 ? initialChildren : 0,

                        infants: Number.isFinite(initialInfants) && initialInfants >= 0 ? initialInfants : 0

    };
 
    let guestPopOpen = false;
 
    const el = {

      display: card.querySelector('.js-sb-guests-display'),
      field: card.querySelector('.sb-guests-desktop'),
      pop: card.querySelector('.guests-popover'),
      adultsVal: card.querySelector('.js-v-adults'),
      childrenVal: card.querySelector('.js-v-children'),
      infantsVal: card.querySelector('.js-v-infants'),
      mAdults: card.querySelector('.js-m-adults'),
      mChildren: card.querySelector('.js-m-children'),
      mInfants: card.querySelector('.js-m-infants'),
      btnAM: card.querySelector('.js-btn-adults-minus'),
      btnCM: card.querySelector('.js-btn-children-minus'),
      btnIM: card.querySelector('.js-btn-infants-minus'),

    };
 
    if (!el.display || !el.pop) return;
 
    function renderGuests() {

            localStorage.setItem('sb_adults', String(g.adults));

            localStorage.setItem('sb_children', String(g.children));

            localStorage.setItem('sb_infants', String(g.infants));
 
            const totalGuests = g.adults + g.children;
            let label = `${totalGuests} Guest${totalGuests !== 1 ? 's' : ''}`;

            if (g.infants > 0) {
                label += `, ${g.infants} Infant${g.infants !== 1 ? 's' : ''}`;
            }

            el.display.textContent = label;

            el.display.classList.toggle('empty', totalGuests <= 0 && g.infants <= 0);

      if (el.adultsVal) el.adultsVal.textContent = g.adults;

      if (el.childrenVal) el.childrenVal.textContent = g.children;

      if (el.infantsVal) el.infantsVal.textContent = g.infants;

      if (el.btnAM) el.btnAM.disabled = g.adults <= 1;

      if (el.btnCM) el.btnCM.disabled = g.children <= 0;

      if (el.btnIM) el.btnIM.disabled = g.infants <= 0;

      if (el.mAdults) el.mAdults.value = g.adults;

      if (el.mChildren) el.mChildren.value = g.children;

      if (el.mInfants) el.mInfants.value = g.infants;

    }
 
    el.field.addEventListener('click', function(e){

      e.stopPropagation();

      guestPopOpen = !guestPopOpen;

      el.pop.classList.toggle('open', guestPopOpen);

    });
 
    document.addEventListener('click', function (e) {

      if (!guestPopOpen) return;

      if (!card.contains(e.target)) {

        guestPopOpen = false;

        el.pop.classList.remove('open');

      }

    });
 
    card.querySelectorAll('.g-btn').forEach(btn => {

      btn.addEventListener('click', function () {
 
        const row = this.closest('.g-row');

                const applyLocalAdjust = function(type, delta) {
                    if (type === 'adults') {
                        g.adults = Math.max(1, g.adults + delta);
                    } else if (type === 'children') {
                        g.children = Math.max(0, g.children + delta);
                    } else if (type === 'infants') {
                        g.infants = Math.max(0, g.infants + delta);
                    }
                    renderGuests();
                };

                const runAdjust = function(type, delta) {
                    if (typeof window.adj === 'function') {
                        window.adj(type, delta);
                        g.adults = Math.max(1, parseInt(el.adultsVal?.textContent || g.adults, 10) || g.adults);
                        g.children = Math.max(0, parseInt(el.childrenVal?.textContent || g.children, 10) || g.children);
                        g.infants = Math.max(0, parseInt(el.infantsVal?.textContent || g.infants, 10) || g.infants);
                        renderGuests();
                        return;
                    }
                    applyLocalAdjust(type, delta);
                };
 
                if (this.classList.contains('js-btn-adults-minus')) runAdjust('adults', -1);

                else if (this.classList.contains('js-btn-children-minus')) runAdjust('children', -1);

                else if (this.classList.contains('js-btn-infants-minus')) runAdjust('infants', -1);

                else if (row.querySelector('.js-v-adults')) runAdjust('adults', 1);

                else if (row.querySelector('.js-v-children')) runAdjust('children', 1);

                else if (row.querySelector('.js-v-infants')) runAdjust('infants', 1);
 
      });

    });
 
    function syncMobile() {

      g.adults = Math.max(1, parseInt(el.mAdults?.value || 2));

      g.children = Math.max(0, parseInt(el.mChildren?.value || 0));

      g.infants = Math.max(0, parseInt(el.mInfants?.value || 0));

      renderGuests();

    }
 
    if (el.mAdults) el.mAdults.addEventListener('change', syncMobile);

    if (el.mChildren) el.mChildren.addEventListener('change', syncMobile);

    if (el.mInfants) el.mInfants.addEventListener('change', syncMobile);
 
    renderGuests();
 
  });
 
});