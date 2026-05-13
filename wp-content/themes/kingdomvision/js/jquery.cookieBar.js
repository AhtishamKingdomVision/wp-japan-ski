/*!
 * Cookie Bar component (https://github.com/kovarp/jquery.cookieBar)
 * Version 1.2.0
 *
 * Copyright 2018 Pavel Kovář - Frontend developer [www.pavelkovar.cz]
 * @license: MIT (https://github.com/kovarp/jquery.cookieBar/blob/master/LICENSE)
 */

if (typeof jQuery === 'undefined') {
    throw new Error('Cookie Bar component requires jQuery')
}

/**
 * ------------------------------------------------------------------------
 * Cookie Bar component
 * ------------------------------------------------------------------------
 */

(function ($) {

    // Global variables
    var cookieBar, config;

    // Cookie Bar translations
    var translation = [];

    translation['en'] = {
        title: 'Our Cookies',
        message: 'We use cookies to enable the site to function, improve our services and provide you a better customer experience. You can accept all cookies or manage them individually. For full details please see our Privacy Policy (link to privacy policy page)',
        acceptText: 'OK',
        infoText: 'More information',
        privacyText: 'Privacy protection',
        manageText: 'Manage preferences',
    };

    translation['de'] = {
        title: 'Our Cookies',
        message: 'Zur Bereitstellung von Diensten verwenden wir Cookies. Durch die Nutzung dieser Website stimmen Sie zu.',
        acceptText: 'OK',
        infoText: 'Mehr Informationen',
        manageText: 'Manage preferences',
        privacyText: 'Datenschutz'
    };

    translation['cs'] = {
        title: 'Our Cookies',
        message: 'K poskytování služeb využíváme soubory cookie. Používáním tohoto webu s&nbsp;tím souhlasíte.',
        acceptText: 'V pořádku',
        infoText: 'Více informací',
        manageText: 'Manage preferences',
        privacyText: 'Ochrana soukromí'
    };

    translation['sk'] = {
        title: 'Our Cookies',
        message: 'Na poskytovanie služieb využívame súbory cookie. Používaním tohto webu s&nbsp;tým súhlasíte.',
        acceptText: 'V poriadku',
        infoText: 'Viac informácií',
        manageText: 'Manage preferences',
        privacyText: 'Ochrana súkromia'
    };

    translation['ru'] = {
        title: 'Our Cookies',
        message: 'Данный сайт использует для предоставления услуг, персонализации объявлений и анализа трафика печенье. Используя этот сайт, вы соглашаетесь.',
        acceptText: 'Я согласен',
        infoText: 'Больше информации',
        manageText: 'Manage preferences',
        privacyText: 'Конфиденциальность'
    };

    translation['pl'] = {
        title: 'Our Cookies',
        message: 'Używamy plików cookie w celu świadczenia naszych usług. Korzystając z tej strony, zgadzasz się na to.',
        acceptText: 'Dobrze',
        infoText: 'Więcej informacji',
        manageText: 'Manage preferences',
        privacyText: 'Ochrona prywatności'
    };

    var methods = {
        init: function (options) {
            cookieBar = '#cookie-bar';

            var defaults = {
                infoLink: 'https://www.google.com/policies/technologies/cookies/',
                infoTarget: '_blank',
                wrapper: 'body',
                expireDays: 365,
                style: 'top',
                language: $('html').attr('lang') || 'en',
                privacy: false,
                privacyTarget: '_blank',
                privacyContent: null,
                title: translation['en'].title,
                message: translation['en'].message,
                acceptText: translation['en'].acceptText,
                manageText: 'Manage preferences',
                infoText: translation['en'].infoText,
                privacyText: translation['en'].privacyText,
            };

            config = $.extend(defaults, options);


            translation[config.language] = {
                title: config.title,
                message: config.message,
                acceptText: config.acceptText,
                manageText: config.manageText,
                infoText: config.infoText,
                privacyText: config.privacyText,
            };

            if (methods.getCookie('cookies-state') !== 'accepted' && methods.getCookie('cookies-state') !== 'closed') {
                methods.displayBar();
            }

            // Accept cookies
            $(document).on('click', cookieBar + ' .cookie-bar__btn', function (e) {
                e.preventDefault();
                methods.setCookie('cookies-state', 'accepted', config.expireDays);
                methods.hideBar();
            });

            // Open privacy info popup
            $(document).on('click', '[data-toggle="cookieBarPrivacyPopup"]', function (e) {
                e.preventDefault();

                methods.showPopup();
            });

            // Close privacy info popup
            $(document).on('click', '.cookie-bar-privacy-popup, .cookie-bar-privacy-popup__dialog__close', function (e) {
                methods.hidePopup();
            });

            $(document).on('click', '.cookie-bar-privacy-popup__dialog', function (e) {
                e.stopPropagation();
            });

            $(document).on('click', '.cookie-bar-close', function (e) {
                methods.setCookie('cookies-state', 'closed', config.expireDays);
                methods.hideBar();
            });

        },
        displayBar: function () {
            // console.log( translation[config.language] );
            // Display Cookie Bar on page
            var acceptButton = '<button type="button" class="cookie-bar__btn">' + translation[config.language].acceptText + '</button>';
            var infoLink = '<a href="' + config.infoLink + '" target="' + config.infoTarget + '" class="cookie-bar__link cookie-bar__link--cookies-info">' + translation[config.language].infoText + '</a>';
            var manage_preference = '<button type="button" class="bar-manage__btn">' + translation[config.language].manageText + '</button>';

            var privacyButton = '';
            if (config.privacy) {
                if (config.privacy === 'link') {
                    privacyButton = '<a href="' + config.privacyContent + '" target="' + config.privacyTarget + '" class="cookie-bar__link cookie-bar__link--privacy-info">' + translation[config.language].privacyText + '</a>';
                } else if (config.privacy === 'bs_modal') {
                    privacyButton = '<a href="' + config.privacyContent + '" data-toggle="modal" class="cookie-bar__link cookie-bar__link--privacy-info">' + translation[config.language].privacyText + '</a>';
                } else if (config.privacy === 'popup') {
                    methods.renderPopup();
                    privacyButton = '<a href="#" data-toggle="cookieBarPrivacyPopup" class="cookie-bar__link cookie-bar__link--privacy-info">' + translation[config.language].privacyText + '</a>';
                }
            }

            console.log(translation[config.language]);


            var template = '<div id="cookie-bar" class="cookie-bar cookie-bar--' + config.style + '">' +
                '<div class="cookie-bar__inner">' +
                '<h4 class="cookie-bar-title">' + translation[config.language].title + '</h4>' +
                '<span class="cookie-bar__message">' + translation[config.language].message + '</span>' +
                '<span class="cookie-bar__buttons">' + acceptButton + infoLink + privacyButton + '</span>' +
                '<div class="cookie-bar-close">x</div>' +
                '</div>' +
                '</div>';

            var modal =
                '<div id="manage-preferences-modal" title="Basic dialog">\n' +
                '  <p>This is the default dialog which is useful for displaying information. The dialog window can be moved, resized and closed with the &apos;x&apos; icon.</p>\n' +
                '</div>';

            // template += modal;

            $(config.wrapper).prepend(template);


        },
        hideBar: function () {
            // Hide Cookie Bar
            $(cookieBar).slideUp();
        },
        renderPopup: function () {
            var popup = $('<div id="cookieBarPrivacyPopup" class="cookie-bar-privacy-popup cookie-bar-privacy-popup--hidden"><div class="cookie-bar-privacy-popup__dialog"><button type="button" class="cookie-bar-privacy-popup__dialog__close"></button></div></div>');
            $('body').append(popup);
            $('.cookie-bar-privacy-popup__dialog', popup).append(config.privacyContent);
        },
        showPopup: function () {
            $('#cookieBarPrivacyPopup').removeClass('cookie-bar-privacy-popup--hidden');
        },
        hidePopup: function () {
            $('#cookieBarPrivacyPopup').addClass('cookie-bar-privacy-popup--hidden');
        },
        addTranslation: function (lang, translate) {
            translation[lang] = translate;
        },
        setCookie: function (cname, cvalue, exdays) {
            // Helpful method for set cookies
            var d = new Date();
            d.setTime(d.getTime() + (exdays * 24 * 60 * 60 * 1000));
            var expires = "expires=" + d.toUTCString();

            document.cookie = cname + "=" + cvalue + ";" + expires + ";path=/";
        },
        getCookie: function (cname) {
            // Helpful method for get cookies
            var name = cname + "=";
            var ca = document.cookie.split(';');

            for (var i = 0; i < ca.length; i++) {
                var c = ca[i];

                while (c.charAt(0) === ' ') {
                    c = c.substring(1);
                }

                if (c.indexOf(name) === 0) {
                    return c.substring(name.length, c.length);
                }
            }

            return '';
        }
    };

    // Create jQuery cookieBar function
    $.cookieBar = function (methodOrOptions) {
        if (methods[methodOrOptions]) {
            return methods[methodOrOptions].apply(this, Array.prototype.slice.call(arguments, 1));
        } else if (typeof methodOrOptions === 'object' || !methodOrOptions) {
            return methods.init.apply(this, arguments);
        } else {
            $.error('Method ' + methodOrOptions + ' does not exist on Cookie Bar component');
        }
    };
}(jQuery));