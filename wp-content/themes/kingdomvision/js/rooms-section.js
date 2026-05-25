    jQuery(function($) {

        // ✅ HELPER: Common AJAX error handler to reduce code duplication
        function handleAjaxError(xhr, exception) {
            let msg = '';
            if (xhr.status === 0) {
                msg = 'Internet not connected. Verify Network.';
                alert(msg);
            } else if (exception === 'timeout') {
                msg = 'Request timed out. Please try again.';
                alert(msg);
            } else if (exception === 'abort') {
                msg = 'Ajax request aborted.';
            } else if (xhr.status === 404) {
                msg = 'Requested page not found. [404]';
            } else if (xhr.status === 500) {
                msg = 'Internal Server Error [500].';
            } else if (exception === 'parsererror') {
                msg = 'Requested JSON parse failed.';
            } else {
                msg = 'Error: ' + xhr.status + ' ' + xhr.responseText;
            }
            console.error('AJAX Error:', msg);
            return msg;
        }

        // ✅ HELPER: Validate form input existence and get value safely
        function getFieldValue(selector) {
            const $field = $(selector);
            return $field.length > 0 ? ($field.val() || '') : '';
        }

        // ✅ HELPER: Validate and get numeric value from field
        function getNumericValue(value, defaultVal = 0) {
            const num = parseInt(value);
            return isNaN(num) ? defaultVal : num;
        }

        // ✅ HELPER: Consolidate localStorage operations
        const rb_storage = {
            get: function(key) {
                try { return localStorage.getItem(key); } catch (e) { return null; }
            },
            set: function(key, value) {
                try { localStorage.setItem(key, value); } catch (e) {}
            },
            remove: function(key) {
                try { localStorage.removeItem(key); } catch (e) {}
            },
            getJSON: function(key) {
                try {
                    const val = localStorage.getItem(key);
                    return val ? JSON.parse(val) : null;
                } catch (e) { return null; }
            },
            setJSON: function(key, obj) {
                try { localStorage.setItem(key, JSON.stringify(obj)); } catch (e) {}
            }
        };

        // ✅ HELPER: Check if mobile device
        function isMobile() {
            return window.matchMedia("(max-width: 767px)").matches;
        }

        const $filterForm = $('#room-filter-form');
        const acc_id = $filterForm.attr('acc-id');
        const room_id = $filterForm.attr('room-id');
        const header_height = $('header').outerHeight() || 0;
        const to_form = rb_storage.get('go_to_form');

        if (to_form === room_id) {
            $('html, body').animate({
                scrollTop: ($filterForm.offset().top - header_height)
            }, 400);
            rb_storage.remove('go_to_form');
        }

        // Initial UI state check
        const uiState = rb_storage.get('rb_ui_state');
        if (uiState === 'booking') {
            rb_render_booking_from_storage();
        } else {
            // If no dates are pre-filled and not in booking state, show the room list.
            // This handles the case where rooms-section.php used to call show_booking_ui() unconditionally.
            show_booking_ui();
        }

        // Ensure layout is handled on load (handles mobile cart positioning etc)
        handle_layout();

        jQuery(document).on('click', '.room_details', function() {
           let parent = jQuery( this ).parents('.room-card'),
                rc_cover = parent.find('.rc_cover'),
                room_btns = rc_cover.find( '.room-btns' ),
                btn = room_btns.find('button.btn');

            btn.trigger('click');
        });
        
        // jQuery(document).on('click', '.detail_btn, .detail_btn img', function() {
        //    let parent = jQuery( this ).parents('.room-card'),
        //         rc_cover = parent.find('.rc_cover'),
        //         room_btns = rc_cover.find( '.room-btns' ),
        //         btn = room_btns.find('a.btn');

        //     btn.trigger('click');
        // });

        jQuery(document).on('click', '.book-btn', function(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();

            const ratesChecked = rb_storage.get(acc_id + '_rates_checked') === 'true';
            const bookingContext = rb_storage.getJSON('rb_booking_context');

            if ( (!ratesChecked && !bookingContext ) || ( bookingContext.checkin === '' || bookingContext.checkout === '' ) ) {
                alert('Please enter dates.');
                return;
            }

            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).addClass('loading').text('Loading…');

            const payload = {
                property_id: bookingContext?.property_id || $filterForm.attr('property-id'),
                start_date: bookingContext?.checkin || getFieldValue('#sc-check-in'),
                end_date: bookingContext?.checkout || getFieldValue('#sc-check-out'),
                adults: $('#adults').val() || 0,
                children: $('#children').val() || 0,
                infants: $('#infants').val() || 0
            };

            fetchBookingUI(payload, function() {
                $btn.prop('disabled', false).removeClass('loading').text(originalText);
            });
        });

        function show_booking_ui() {
            const payload = {
                property_id: $filterForm.attr('property-id'),
                start_date: $filterForm.find('#sc-check-in').val(),
                end_date: $filterForm.find('#sc-check-out').val(),
            };

            if (!payload.start_date || !payload.end_date) {
                $('.room-list').show();
                return;
            }

            fetchBookingUI(payload);
        }

        /**
         * Consolidated AJAX handler for loading the booking flow
         */
        function fetchBookingUI(params, callback) {
            $('.booking-wrap')
                .html('<div class="rb-loading">Loading booking…</div>')
                .show();
            $('.room-list').hide();

            $.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                type: 'POST',
                data: {
                    action: 'load_roomboss_booking',
                    property_id: params.property_id,
                    start_date: params.checkin || params.start_date,
                    end_date: params.checkout || params.end_date,
                    adults: params.adults || 0,
                    children: params.children || 0,
                    infants: params.infants || 0
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.success) {
                        rb_storage.set('rb_ui_state', 'booking');
                        
                        // ✅ Sync context to storage so state persists correctly on page refresh
                        rb_storage.setJSON('rb_booking_context', {
                            property_id: params.property_id,
                            checkin: params.checkin || params.start_date,
                            checkout: params.checkout || params.end_date,
                            adults: params.adults || 0,
                            children: params.children || 0,
                            infants: params.infants || 0
                        });

                        $('.room-list').fadeOut(200, function() {
                            $('#room-modal').fadeOut(200);
                            $('.booking-wrap').hide().html(resp.data.html).fadeIn(200);
                        });

                        if (typeof kv_booking_init === 'function') {
                            kv_booking_init();
                        }
                        $('.units_avl').text($('.rb-room-card').length);
                    } else {
                        $('.booking-wrap').html('<p class="rb-error">' + (resp?.data?.message || 'No data returned') + '</p>');
                    }
                },
                error: function(xhr) {
                    $('.booking-wrap').html('<p class="rb-error">Unable to load booking data.</p>');
                    console.error('Booking AJAX error:', xhr.responseText);
                },
                complete: function() {
                    if (typeof callback === 'function') callback();
                }
            });
        }

        function handle_layout(){
            var rbcart = $('aside.rb-cart');
            var body = $('body');
            if(isMobile()){
                body.addClass('mobile');

                if( rbcart.length > 0 && body.hasClass( 'mobile' ) ){

                    // Move rbcart to be the first child of the body
                    rbcart.prependTo(body);
                }
            }
            else{
                var rbcart = $('aside.rb-cart');
                var parent = $('.rb-booking-layout');

                if( rbcart.length > 0 && body.hasClass( 'mobile' ) ){

                    body.removeClass('mobile');
                    // Move rbcart to be the first child of the body
                    rbcart.appendTo(parent);
                }
            }
        }

        $(document).on('change', '#room-filter-form input, #room-filter-form select', function() {
            rb_storage.set(acc_id + '_rates_checked', 'false');
        });

        $(document).on('click', '.back-to-rooms', function() {

            // 🔁 Reset persisted UI state
            localStorage.removeItem('rb_ui_state');

            $('.booking-wrap').fadeOut(200, function() {
                jQuery(this).empty();

                $('.room-list').fadeIn(200);
            });
        });

        // Open modal
        $(document).on('click', '.details-btn', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const originalText = $btn.text();

            const roomId = $(this).attr('room-id');
            const propertyId = $(this).attr('property-id');

            $btn.prop('disabled', true).addClass('loading').text('Loading…');

            $.ajax({
                url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                method: 'POST',
                data: {
                    action: 'niseko_load_room_details',
                    room_id: roomId,
                    property_id: propertyId
                },
                success: function(res) {
                    if (res.success) {
                        $('#room-modal-body').html(res.data.html);
                        $('#room-modal').fadeIn(200, function() {
                            const $gallery = $('.js-room-gallery');
                            const $images = $gallery.find('img');
                            let imagesLoaded = 0;
                            $images.each(function() {
                                if (this.complete) {
                                    imagesLoaded++;
                                } else {
                                    $(this).on('load', () => {
                                        imagesLoaded++;
                                        if (imagesLoaded === $images.length) initRoomGallery();
                                    });
                                }
                            });
                            if (imagesLoaded === $images.length) initRoomGallery();
                        });
                        $('.book-btn').prop('disabled', false).removeClass('disabled');
                    } else {
                        $('#room-modal-body').html('<p>Error loading room details.</p>');
                        $('.book-btn').prop('disabled', false).removeClass('disabled');
                    }
                },
                error: function(xhr, exception) {
                    handleAjaxError(xhr, exception);
                    $('#room-modal-body').html('<p class="rb-error">Error loading room details.</p>');
                },
                complete: function() {
                    $btn.prop('disabled', false).removeClass('loading').text(originalText);
                }
            });
        });

        $(document).on('click', '#room-modal', function(e) {
            if ($(e.target).is('#room-modal')) $(this).fadeOut(200);
        });
        $(document).on('click', '.room-modal-close, .room-modal-backdrop', function() {
            $('#room-modal').fadeOut(200);
        });

        $('#room-filter-form').on('submit', room_filter_submit_func);

        function room_filter_submit_func(e) {
            if (e && typeof e.preventDefault === 'function') e.preventDefault();

            const uiState = rb_storage.get('rb_ui_state') || 'list';
            const form = $( '#room-filter-form' );
            const $btn = form.find( '.rooms-sc-btn' );
            const originalText = $btn.text();

            const property_id = form.attr( 'property-id' );
            const rate_checked = rb_storage.get( property_id + '_rates_checked' );

            var checkin = getFieldValue('#sc-check-in');
            var checkout = getFieldValue('#sc-check-out');

            if( rate_checked === 'true' ){
                if ( !checkin || !checkout ) {
                    alert('Please select check-in and check-out dates');
                    return;
                }
            }

            if (checkin && checkout ) {
                rb_storage.set('niseko_checkin', checkin);
                rb_storage.set('niseko_checkout', checkout);
            }

            rb_storage.setJSON('rb_booking_context', {
                checkin: checkin,
                checkout: checkout,
                property_id: property_id,
                adults: $('#adults').val() || 0,
                children: $('#children').val() || 0,
                infants: $('#infants').val() || 0
            });

            $btn.prop('disabled', true).addClass('loading').text('Loading…');

            if (uiState === 'list') {
                $.ajax({
                    url: kv_object.ajaxurl + '?v=' + new Date().getTime(),
                    method: 'POST',
                    data: {
                        action: 'niseko_search_roomboss_single',
                        checkin: checkin,
                        checkout: checkout,
                        property_id: property_id,
                    },
                    success: function(res) {
                        if (res.success) {
                            rb_storage.set( acc_id+'_rates_checked', 'true');
                            $('#room-results').html(res.data.html);
                            updateBedroomTabs(res.data.available_bedroom_types);
                            $('.book-btn').prop('disabled', false).removeClass('disabled');
                            $('.units_avl').text( res.data.count );
                        } else {
                            $('.book-btn').prop('disabled', false).removeClass('disabled');
                            $('#room-results').html('<p>No rooms available for selected dates.</p>');
                        }
                    },
                    error: function(xhr, exception) {
                        handleAjaxError(xhr, exception);
                        $('#room-results').html('<p class="rb-error">Error fetching room availability.</p>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).removeClass('loading').text(originalText);
                    }
                });
                return;
            }
            rb_render_booking_from_storage();
        }

        // Handle Bedroom Tab Switching
        $(document).on('click', '.bedroom-tab', function() {
            $('.bedroom-tab').removeClass('active');
            $(this).addClass('active');
            var bedroomFilter = $(this).data('bedroom');
            if (bedroomFilter === 'all') {
                $('.room-card').show();
            } else {
                $('.room-card').each(function() {
                    var roomBedroomCount = $(this).data('bedroom');
                    if (roomBedroomCount === parseInt(bedroomFilter)) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            }
        });

        // Trigger a click on the "All Bedrooms" tab by default
        $('.bedroom-tab[data-bedroom="all"]').click();

        $(document).on('click', '.close_cart', function (e) {
            $(this).parents('aside.rb-cart').removeClass('active');
            $( 'body' ).removeClass('cart-active');
        });
        
        $('.filter-tab.reset').on('click', function() {
            localStorage.setItem( 'apply-filters', false );
            $('.filter-tabs .close_filter').each( function ( i,e ){
                $(this).trigger('click');
            } );
            var parent = $( this ).parent('.filter');
            parent.hide();
            localStorage.setItem( 'apply-filters', true );
            localStorage.removeItem( 'niseko_checkin' );
            localStorage.removeItem( 'niseko_checkout' );
            $('#sc-check-in, #sc-check-out').val('');
            $('#apply-filters').trigger('click');
        });

        function updateBedroomTabs(availableBedroomTypes) {
            var avl_units = $( '.available_units' )[0].outerHTML;
            $('.bedroom-tabs').empty();
            $('.bedroom-tabs').append('<button type="button" class="bedroom-tab active" data-bedroom="all">All Bedrooms</button>');
            $.each(availableBedroomTypes, function(index, type) {
                $('.bedroom-tabs').append('<button type="button" class="bedroom-tab" data-bedroom="' + type + '">' + type + ' Bedroom' + (type > 1 ? 's' : '') + '</button>');
            });
            $('.bedroom-tabs').prepend( avl_units );
            var room_count = $( '#room-results .room-card' ).length;
            $( '.units_avl' ).text( room_count );
        }


        function kv_booking_cart_get() {
            try {
                const val = sessionStorage.getItem('rb_cart');
                return val ? JSON.parse(val) : { items: [] };
            } catch(e) { return { items: [] }; }
        }

        function kv_booking_cart_set(cart) {
            try { sessionStorage.setItem('rb_cart', JSON.stringify(cart)); } catch(e) {}
        }

        function kv_booking_cart_render() {

            const cart = kv_booking_cart_get();
            const $body  = jQuery('#rbCartBody');
            const $footer = jQuery('#rbCartFooter');

            if (!cart.items || !cart.items.length) {
                $body.html('<p class="rb-cart-empty">No rooms selected yet.</p>');
                $footer.hide();
                return;
            }

            // ------ Property header (from first cart item) ------
            const first = cart.items[0];
            let propertyHtml = '';
            if (first.property_name) {
                const pImg = first.property_image
                    ? `<img src="${first.property_image}" alt="${first.property_name}" class="rb-cart-property-img">`
                    : '';
                propertyHtml = `<div class="rb-cart-property">
                    ${pImg}
                    <div class="rb-cart-property-info">
                        <div class="rb-cart-property-name">${first.property_name}</div>
                        ${first.resort_name ? `<div class="rb-cart-resort-name">${first.resort_name}</div>` : ''}
                    </div>
                </div>`;
            }

            let html = propertyHtml;
            let subtotal     = 0;
            let totalDeposit = 0;
            let totalBalance = 0;

            cart.items.forEach((it, idx) => {
                const price   = Number(it.price || 0);
                subtotal     += price;
                const deposit = Number(it.payment?.depositAmount    || 0);
                const balance = Number(it.payment?.balanceDueAmount || (price - deposit));
                totalDeposit += deposit;
                totalBalance += balance;

                const totalPax      = (it.guests?.adults || 0) + (it.guests?.children || 0) + (it.guests?.infants || 0);
                const formattedPrice = '¥' + price.toLocaleString('ja-JP');

                const roomImgHtml = it.room_image
                    ? `<div class="rb-summary-room-img-wrap"><img src="${it.room_image}" alt="${it.room_name || ''}" class="rb-summary-room-img"></div>`
                    : '';

                let guestRows = '';
                if (it.guests?.adults)   guestRows += `<div class="rb-guest-row"><span>Adults</span><strong>${it.guests.adults}</strong></div>`;
                if (it.guests?.children) guestRows += `<div class="rb-guest-row"><span>Children</span><strong>${it.guests.children}</strong></div>`;
                if (it.guests?.infants)  guestRows += `<div class="rb-guest-row"><span>Infants</span><strong>${it.guests.infants}</strong></div>`;

                let paymentHtml = '';
                if (deposit > 0) {
                    paymentHtml += `<div class="rb-payment-row rb-payment-deposit">
                        <span>Deposit on booking</span>
                        <strong>¥${deposit.toLocaleString('ja-JP')}</strong>
                    </div>`;
                    if (balance > 0) {
                        const balanceDateFmt = it.payment?.balanceDueDate
                            ? new Date(it.payment.balanceDueDate).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})
                            : '';
                        paymentHtml += `<div class="rb-payment-row rb-payment-balance">
                            <span>Balance due${balanceDateFmt ? ' (' + balanceDateFmt + ')' : ''}</span>
                            <strong>¥${balance.toLocaleString('ja-JP')}</strong>
                        </div>`;
                    }
                } else {
                    const dueDateFmt = it.payment?.balanceDueDate
                        ? new Date(it.payment.balanceDueDate).toLocaleDateString('en-GB', {day:'numeric',month:'short',year:'numeric'})
                        : '';
                    paymentHtml += `<div class="rb-payment-row">
                        <span>Full amount${dueDateFmt ? ' due ' + dueDateFmt : ' due on booking'}</span>
                        <strong>${formattedPrice}</strong>
                    </div>`;
                }

                html += `<div class="rb-summary-card" data-idx="${idx}">
                    ${roomImgHtml}
                    <div class="rb-summary-card-body">
                        <div class="rb-summary-head">
                            <div class="rb-summary-room-info">
                                <div class="rb-summary-room">${it.room_name || ''}</div>
                                <div class="rb-summary-rate">${it.rateplan_name || ''}</div>
                            </div>
                            <div class="rb-summary-price-wrap">
                                <div class="rb-summary-price">${formattedPrice}</div>
                                <button type="button" class="rb-remove" title="Remove"></button>
                            </div>
                        </div>
                        <div class="rb-summary-guests-detail">
                            <div class="rb-summary-pax">Total guests: ${totalPax}</div>
                            ${guestRows}
                        </div>
                        <div class="rb-summary-dates">
                            <div class="rb-date"><span>Check in</span>${it.dates?.checkinDisplay || ''}</div>
                            <div class="rb-date-separator"></div>
                            <div class="rb-date"><span>Check out</span>${it.dates?.checkoutDisplay || ''}</div>
                        </div>
                        <div class="rb-summary-payment">${paymentHtml}</div>
                    </div>
                </div>`;
            });

            $body.html(html);

            // ------ Footer: subtotal + deposit totals + grand total ------
            let footerHtml = '';
            if (cart.items.length > 1) {
                footerHtml += `<div class="rb-total-row rb-subtotal-row">
                    <span>Subtotal</span><span>¥${subtotal.toLocaleString('ja-JP')}</span>
                </div>`;
            }
            if (totalDeposit > 0) {
                footerHtml += `<div class="rb-total-row rb-deposit-sum-row">
                    <span>Total deposit</span><span>¥${totalDeposit.toLocaleString('ja-JP')}</span>
                </div>
                <div class="rb-total-row rb-balance-sum-row">
                    <span>Total balance</span><span>¥${totalBalance.toLocaleString('ja-JP')}</span>
                </div>`;
            }
            footerHtml += `<div class="rb-total-row rb-grand-total-row">
                <strong>Total</strong>
                <strong><span id="rbCartTotal">¥${subtotal.toLocaleString('ja-JP')}</span></strong>
            </div>
            <button type="button" class="rb-proceed-btn">Proceed</button>`;

            $footer.html(footerHtml).show();

            $('.rb-select-btn').removeClass('is-selected').text('Select');
            cart.items.forEach(it => {
                $(`.rb-rateplan-box[data-room-type-id="${it.room_type_id}"][data-rateplan-id="${it.rateplan_id}"]`)
                    .find('.rb-select-btn').addClass('is-selected').text('Selected');
            });
        }

        function scrollToRoom($room) {
            if($room.length) {
                $('html, body').animate({ scrollTop: $room.offset().top - 140 }, 600);
            }
        }

        function kv_booking_init() {
            kv_booking_cart_render();
            handle_layout();

            // Add mobile cart logic from rooms-section.php
            const wp_body = $('body');
            const hz_cart = $('aside.rb-cart');
            $(document)
                .off('click.rbSelect')
                .on('click.rbSelect', '.rb-select-btn', function() {
                    const wp_body = $('body');
                    const hz_cart = $( 'aside.rb-cart' );
                    const $btn = $(this);
                    const $box = $btn.closest('.rb-rateplan-box');
                    const $room = $btn.closest('.rb-room-card');
                    // const $wrap = $('.rb-booking-layout');
                    // const form = $('#room-filter-form');

                    /* --------------------------------------------------
                       1️⃣ READ ROOM LIMITS & GUESTS
                    -------------------------------------------------- */
                    const maxGuests = getNumericValue($room.find('.rb-icon.guest').text());
                    const $guestForm = $room.find('.rb-guests-form');
                    const adults = getNumericValue($guestForm.find('.rb-adults').val());
                    const children = getNumericValue($guestForm.find('.rb-children').val());
                    const infants = getNumericValue($guestForm.find('.rb-infants').val());

                    // reset UI
                    $room.find('.rb-guest-input').css('border', '');

                    /* --------------------------------------------------
                       2️⃣ VALIDATION (EXACT OLD BEHAVIOUR)
                    -------------------------------------------------- */

                    if (!adults || adults < 1) {
                        console.warn('❌ validation failed: adults');
                        $room.find('.rb-adults').css('border', '2px solid red');
                        alert('Please select number of adults staying in this room.');
                        return;
                    }

                    if ((adults + children) > maxGuests) {
                        console.warn('❌ validation failed: guests exceed max');
                        $room.find('.rb-adults, .rb-children').css('border', '2px solid red');
                        alert(`Total guests cannot exceed ${maxGuests}.`);
                        return;
                    }

                    /* --------------------------------------------------
                       4️⃣ BUILD GUESTS STRING (OLD STYLE)
                    -------------------------------------------------- */
                    let parts = [];

                    if (adults > 0) {
                        parts.push(`${adults} Adult${adults > 1 ? 's' : ''}`);
                    }

                    if (children > 0) {
                        parts.push(`${children} Child${children > 1 ? 'ren' : ''}`);
                    }

                    if (infants > 0) {
                        parts.push(`${infants} Infant${infants > 1 ? 's' : ''}`);
                    }

                    let guests_staying = parts.join(', ');

                    /* --------------------------------------------------
                       5️⃣ OPTIONAL DISCOUNT SUPPORT
                    -------------------------------------------------- */

                    // const discountPrice = Number($box.data('discount-price') || 0);
                    // console.log('💸 discountPrice:', discountPrice);

                    /* --------------------------------------------------
                       5.5️⃣ READ .rb-room-data HIDDEN FIELD & PROPERTY CONTEXT
                    -------------------------------------------------- */

                    let roomData = {};
                    try {
                        const rawRoomData = $box.find('.rb-room-data').val();
                        if (rawRoomData) roomData = JSON.parse(rawRoomData);
                    } catch(e) { console.warn('Could not parse .rb-room-data', e); }

                    /* --------------------------------------------------
                       3️⃣ READ DATES & NIGHTS (SINGLE SOURCE)
                    -------------------------------------------------- */

                    const check_in = roomData.checkIn;
                    const check_out = roomData.checkOut;
                    const nights = roomData.nights;

                    const payTerm      = roomData.roomPayTerm || {};

                    /* --------------------------------------------------
                       6️⃣ BUILD CART ITEM
                    -------------------------------------------------- */

                    const item = {
                        hotel_type_id:   roomData.propertyId,
                        is_bedbank:      roomData.isBedbank,
                        room_type_id:    roomData.roomTypeId,
                        property_name:   roomData.propertyName,
                        property_image:  roomData.propertyImage,
                        resort_name:     roomData.resortName,
                        room_name:       roomData.roomName,
                        room_image:      roomData.roomImage,
                        room_desc:       roomData.roomDescription,
                        rateplan_id:     roomData.ratePlanId,
                        rateplan_name:   roomData.ratePlanName,
                        price:           roomData.priceRetail,
                        // price_label:     $box.find('.rb-rateplan-price').text().trim(),
                        // discount_price:  discountPrice,
                        duration:        roomData.nights,
                        guests: {
                            adults,
                            children,
                            infants,
                            label: guests_staying
                        },
                        dates: {
                            check_in,
                            check_out,
                            nights,
                            checkinDisplay: roomData.checkinDisplay,
                            checkoutDisplay: roomData.checkoutDisplay
                        },
                        payment: {
                            isDeposit:        !!payTerm.isDeposit,
                            depositAmount:    Number(payTerm.depositAmount    || 0),
                            balanceDueAmount: Number(payTerm.balanceDueAmount || 0),
                            balanceDueDate:   payTerm.balanceDueDate || '',
                            totalAmount:      Number(payTerm.totalAmount || roomData.priceRetail || 0),
                        }
                    };

                    /* --------------------------------------------------
                       7️⃣ UPDATE CART
                    -------------------------------------------------- */

                    let cart = kv_booking_cart_get();

                    cart.items = cart.items.filter(
                        x => x.room_type_id !== item.room_type_id
                    );

                    cart.items.push(item);

                    kv_booking_cart_set(cart);
                    kv_booking_cart_render();

                    /* --------------------------------------------------
                       8️⃣ UPDATE BUTTON STATES
                    -------------------------------------------------- */

                    $room.find('.rb-select-btn')
                        .removeClass('is-selected')
                        .text('Select');

                    $btn.addClass('is-selected').text('Selected');

                    // Add mobile cart logic from rooms-section.php
                    if (isMobile()) {
                        wp_body.addClass('cart-active');
                        hz_cart.addClass('active');
                    }
                });

            jQuery(document).on('change', '.rb-guest-input', function() {

                const $room = jQuery(this).closest('.rb-room-card');
                const $form = $room.find('.rb-guests-form');
                const $btn = $room.find('.rb-select-btn');

                const adultsField = $form.find('.rb-adults');
                const childrenField = $form.find('.rb-children');
                const infantsField = $form.find('.rb-infants');

                const adults = parseInt(adultsField.val()) || 0;
                const children = parseInt(childrenField.val()) || 0;
                const infants = parseInt(infantsField.val()) || 0;

                const maxGuests = parseInt($room.find('.rb-icon.guest').text()) || 0;
                const maxInfants = parseInt($room.find('.rb-icon.infant').text()) || 0;

                /* ---------------------------
                   ERROR CONTAINERS
                --------------------------- */
                let $guestErr = $room.find('.rb-error-guests');
                let $infantErr = $room.find('.rb-error-infants');

                if (!$guestErr.length) {
                    $guestErr = jQuery('<div class="rb-error rb-error-guests"></div>').insertAfter($form);
                }
                if (!$infantErr.length) {
                    $infantErr = jQuery('<div class="rb-error rb-error-infants"></div>').insertAfter($form);
                }

                /* ---------------------------
                   RESET
                --------------------------- */
                $guestErr.hide().text('');
                $infantErr.hide().text('');
                $form.find('.rb-guest-input').css('border', '');
                $btn.prop('disabled', false);

                let hasError = false;

                /* ---------------------------
                   1️⃣ ADULTS REQUIRED (ONLY HARD RETURN)
                --------------------------- */
                if (!adults || adults < 1) {
                    adultsField.css('border', '2px solid red');
                    $guestErr.text('Please select number of adults staying in this room.').show();
                    // scrollToRoom($room);
                    $btn.prop('disabled', true);
                    return; // ✅ staging also returns here
                }

                /* ---------------------------
                   2️⃣ NON-INFANT GUEST VALIDATION
                --------------------------- */
                const guestTotal = adults + children;

                if (guestTotal > maxGuests) {
                    adultsField.add(childrenField).css('border', '2px solid red');
                    $guestErr
                        .text(`Total guests (${guestTotal}) cannot exceed ${maxGuests}.`)
                        .show();
                    hasError = true;
                }

                /* ---------------------------
                   3️⃣ INFANT VALIDATION (RUNS REGARDLESS)
                --------------------------- */
                if (infantsField.length && infants > 0) {
                    if ((guestTotal + infants) > (maxGuests + maxInfants)) {
                        infantsField.css('border', '2px solid red');
                        $infantErr
                            .text(
                                `Total guests + infants cannot exceed ${maxGuests + maxInfants}.`
                            )
                            .show();
                        hasError = true;
                    }
                }

                /* ---------------------------
                   4️⃣ FINAL STATE
                --------------------------- */
                if (hasError) {
                    scrollToRoom($room);
                    $btn.prop('disabled', true);
                } else {
                    $guestErr.hide();
                    $infantErr.hide();
                    $form.find('.rb-guest-input').css('border', '');
                    $btn.prop('disabled', false);
                }
            });

            $(document)
                .off('click.rbRemove')
                .on('click.rbRemove', '.rb-remove', function() {
                    const $item = $(this).closest('.rb-summary-card');
                    const idx = Number($item.data('idx'));
                    let cart = kv_booking_cart_get();
                    if (!cart.items || typeof cart.items[idx] === 'undefined') return;

                    const removedItem = cart.items[idx];
                    cart.items.splice(idx, 1);
                    kv_booking_cart_set(cart);
                    kv_booking_cart_render();

                    $(`.rb-rateplan-box[data-room-type-id="${removedItem.room_type_id}"][data-rateplan-id="${removedItem.rateplan_id}"]`)
                        .find('.rb-select-btn').removeClass('is-selected').text('Select');
                });
        }

        // proceed
        jQuery(document).off('click.rbProceed').on('click.rbProceed', '.rb-proceed-btn', function() {
            // redirect or open next step
            window.location.href = '/confirm-booking/';
        });

        function initRoomGallery() {
            const $gallery = $('.js-room-gallery');
            const $slides = $gallery.find('.room-slide');
            if ($gallery.hasClass('slick-initialized')) {
                $gallery.slick('unslick');
            }

            if ($slides.length >= 3) {
                $gallery.on('init', function() {
                    setTimeout(function() { $gallery.slick('setPosition'); }, 0);
                });
                $gallery.slick({
                    infinite: true,
                    slidesToShow: 3,
                    slidesToScroll: 1,
                    arrows: true,
                    dots: false,
                    adaptiveHeight: true,
                    prevArrow: '<button type="button" class="slick-prev"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/left_arrow.svg" alt="Previous"></button>',
                    nextArrow: '<button type="button" class="slick-next"><img src="' + base_url + '/wp-content/themes/kingdomvision/images/right_arrow.svg" alt="Next"></button>',
                    responsive: [{
                            breakpoint: 1024,
                            settings: { slidesToShow: 2 }
                        },
                        {
                            breakpoint: 768,
                            settings: { slidesToShow: 1 }
                        }
                    ]
                });
            }
        }

        function rb_render_booking_from_storage() {
            const bookingContext = rb_storage.getJSON('rb_booking_context');
            const currentPropertyId = $('#room-filter-form').attr('property-id');

            // 🛡️ Safety Check: If context is missing OR belongs to another property, reset UI state
            if (!bookingContext || !bookingContext.checkin || !bookingContext.checkout || 
                !bookingContext.property_id || bookingContext.property_id !== currentPropertyId) {
                
                rb_storage.remove('rb_ui_state');
                show_booking_ui(); 
                return;
            }

            const payload = {
                property_id: bookingContext.property_id,
                checkin: bookingContext.checkin,
                checkout: bookingContext.checkout,
                adults: getNumericValue(bookingContext.adults, 1),
                children: getNumericValue(bookingContext.children),
                infants: getNumericValue(bookingContext.infants)
            };

            fetchBookingUI(payload, function() {
                $('.rooms-sc-btn').prop('disabled', false).removeClass('loading').text('Check Rates');
                // The original rooms-section.php had a .book-btn click here, but fetchBookingUI already loads the booking UI.
                // This is removed to prevent redundant calls.
                scrollToRoom($('.booking-wrap')); // Scroll to the booking wrap
            });
        }
    });