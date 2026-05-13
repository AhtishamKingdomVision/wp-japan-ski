function loc_get_selected_rooms( key ){
    localStorage.getItem( key );
}

function loc_add_selected_rooms( key, value ){
    localStorage.setItem( key, value );
}

jQuery(function ($) {    
    function hz_get_rooms(formData, ajax_url, userTriggered = true) {

        $.ajax({
            url: ajax_url,
            method: "POST",
            data: {
                action: "roomboss_search",
                data: formData
            },
            beforeSend: function() {
                if (userTriggered) {
                    // Only change button if this was a *user* search
                    $('.search_roomboss.form_submit').val('Searching...');
                }
                $('.acc_loader').show();
            },
            success: function(response) {
                let res = response.data,
                    data = res.data,
                    total_pages = res.total,
                    img_array = res.images,
                    offset = $( '.roomboss_form #offset' ).val(),
                    rb_loadmore = $( '.rb_loadmore' ),
                    params = new URLSearchParams(formData);

                $('.acc_loader').remove();

                Cookies.set( 'total_acc_pages', total_pages, { expires: 1 } );

                var total_acc_pages = Cookies.get('total_acc_pages') ?? 5;
                var loader = '<svg width="100%" height="80" viewBox="0 0 900 80" xmlns="http://www.w3.org/2000/svg"><span class="acc_loader"></span>';

                if (params.get("type_acc") === "hotel") {
                    let res = $(response.data.data),
                        acc_buttons = res.find('.acc_buttons'),
                        url = acc_buttons.attr('data-redirect'),
                        params_raw = acc_buttons.attr('data-params'),
                        post_id = acc_buttons.data('post_id');

                    // console.log( res );
                    Cookies.set('hz_acc_params_'+post_id , params_raw, { expires: 1 });

                    if (userTriggered) {
                        $('.search_roomboss.form_submit').val('Search');
                    }

                    window.open(url, '_blank');
                    // return;
                }
                else{
                    if( $(".pl_list").hasClass( 'roomboss_list' ) ){
                        $(".pl_list").append(data);
                    }
                    else{
                        $(".pl_list").html(data);
                        $(".pl_list").addClass('roomboss_list');
                    }

                    $('.pl_listing .pl_list').css("-webkit-filter", "blur(0px)");

                    var list_items = $('.pl_list .listing_box');

                    list_items.each(function(index, element) {
                        var listing_id = $(element).data('hotel-id');
                        if(img_array[listing_id]){
                            var img_url = img_array[listing_id];
                            $(element).find('.listing_box_img .acc_img').attr('src', img_url);
                        }
                    });

                    if( offset < total_acc_pages ){
                        rb_loadmore.show();
                    }
                    else{
                        rb_loadmore.hide();
                    }

                    var button = $('.rb_listing_btn');

                    button.each(function(index, element) {
                        $(this).is('[type="button"]') && $(this).text('Load More');
                        $(this).is('[type="submit"]') && $(this).val('Search');
                    });
                    button.attr( 'disabled', false );

                    rb_loadmore.attr( 'data-max_page' , total_acc_pages );
                }

                if ( parseInt(offset) < parseInt(total_acc_pages) ) {
                    rb_get_rooms(false); // pass false = not user triggered
                }
            },
            error: function (jqXHR, exception) {
                var msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\n Verify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }
                console.log( msg );
            },
        });
    }

    $("form#roomboss_form").on("submit", function(e) {
        e.preventDefault();
        var cat = $(this).attr('data-cat');
        var acc_search = $('.filter').find( '.accomodation_search' );
        var search_acc = acc_search.find( '#search_acc' );
        var type_acc_val = acc_search.find( '#type_acc' );

        // search_acc.attr( 'data-val', cat );
        if( search_acc.val().length > 1 ){
            type_acc_val.attr( 'value', 'hotel' );
        }
        else {
            type_acc_val.attr( 'value', 'destination' );
        }
            
        var formData = $(this).serialize();
        var ajax_url = kv_object.ajaxurl;
        var type_val = search_acc.attr('data-val');
        var type_acc = type_acc_val.val();
        var price_range_value = $('#price_range_value').val();

        if( $('input[name="ski"]').is(':checked') ){
            formData += '&ski=yes';
        }

        formData += '&price_range_value=' + price_range_value;
        formData += '&type_val=' + type_val;
        formData += '&get_all=no';
        formData += '&type_acc='+type_acc;

        let selectedBedrooms = $('input[name="bedrooms[]"]:checked')
            .map(function() {
                return $(this).val();
            })
        .get()
        .join(',');

        if ( selectedBedrooms ) {
            formData += '&selcted_room='+selectedBedrooms;
        }

        hz_get_rooms(formData, ajax_url);
        Cookies.set('formData', formData );
        // $('.pl_listing .pl_list').css("-webkit-filter", "blur(10px)");

    });

    function rb_get_rooms(userTriggered = true) {

        let form = $("form#roomboss_form"),
            offset = form.find('#offset');

        offset_val = parseInt( offset.val() );
        offset_val = offset_val + 1;

        offset.attr( 'value' , offset_val );
        
        if( $('#search_acc').val().length > 0 && userTriggered === true){
            form.trigger( 'submit' );
        }
        else{

            let form = $('#roomboss_form'),
                acc_search = $('.filter').find( '.accomodation_search' ),
                search_acc = acc_search.find( '#search_acc' ).val().length > 1 
                ? acc_search.find( '#search_acc' ).val() 
                : form.find('#search_location').val(),
                
                checkIn = form.find('#check-in').val().length > 0 
                ? form.find('#check-in').val() 
                : form.find('#check-in').data('default'),

                checkOut = form.find('#check-out').val().length > 0
                ? form.find('#check-out').val() 
                : '',
                
                guests = form.find('#guests').val().length > 0 
                ? form.find('#guests').val() 
                : form.find('#guests').data('default'),

                price_range_value = $( '.filter' ).find('#price_range_value').val(),
                
                offset = form.find('#offset').attr('value'),
                type_acc = acc_search.find('#type_acc').val(),
                type_val = form.find('#search_location').attr('data-val'),
                ajax_url = kv_object.ajaxurl;
                
                
                // if( $('#search_acc').val().length > 0 && form.find('#check-in').val().length > 0 && form.find('#duration').val().length && form.find('#adults').val().length > 0 && form.find('#children').val().length > 0 && form.find('#infants').val() ){
                    var formData = 'search_location='+search_acc+'&type_acc='+type_acc+'&checkIn='+checkIn+'&checkOut='+checkOut+'&guests='+guests+'&offset='+offset+'&limit=9&price_range_value='+price_range_value+'&type_val='+type_val+'&type_acc='+type_acc+'&get_all=no';
                                    // search_location=Niseko&        type_acc=destination&checkIn=20-Mar-2026&checkOut=28-Mar-2026&guests=1&offset=1&limit=9&price_range_value=150000-5850000&type_val=Niseko Accommodation&get_all=no&type_acc=destination
                // }
                // else{
                //     var formData = 'search_acc='+search_acc+'&checkIn='+checkIn+'&duration='+duration+'&adults='+adults+'&children='+children+'&infants='+infants+'&offset='+offset+'&limit=9&get_all=yes ';
                // }

            var total_acc_pages = Cookies.get( 'total_acc_pages' ) ? Cookies.get( 'total_acc_pages' ) : 5;

            if( parseInt( total_acc_pages ) > parseInt( offset_val ) ){
                hz_get_rooms( formData, ajax_url, userTriggered );
            }
        }
    };

    $(document).on('click', '.search_roomboss.form_submit', function(){
        var form = $(this).parents('.roomboss_form');

        $('.pl_list').removeClass( 'roomboss_list' );
        form.find( '#offset' ).val( 1 );
        if( $('.kv_loadmore').length > 0 )
            $('.kv_loadmore').remove();
    });

    if( pathArray[1] == 'room-boss-hotel-listing' || pathArray[2] == 'accommodation' ){

        $('.gallery-box ul li').each( function(i, e){
            if( i == 2 ){
                $( this ).find('a span').text('View More');
            }
        } );

        let form = $('#roomboss_form'),
            search_acc = form.find('#search_acc').data('default'),
            checkIn = form.find('#check-in').data('default'),
            duration = form.find('#duration').data('default'),
            adults = form.find('#adults').data('default'),
            children = form.find('#children').data('default'),
            infants = form.find('#infants').data('default'),
            formData = 'search_acc='+search_acc+'&checkIn='+checkIn+'&duration='+duration+'&offset=1&limit=9&get_all=yes',
            ajax_url = kv_object.ajaxurl;

        // hz_get_rooms( formData, ajax_url, false );

        const airPickerLocale = {
            days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            months: ['January','February','March','April','May','June', 'July','August','September','October','November','December'],
            monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            today: 'Today',
            clear: 'Clear',
            dateFormat: 'mm/dd/yyyy',
            timeFormat: 'hh:ii aa',
            firstDay: 0
        }

        if ($('input#check-in').length == 1) {
            // console.log( 'check-in' );
            const datepicker1 = new AirDatepicker('#check-in', {
                minDate: new Date(kv_object.check_start_date),
                maxDate: new Date(kv_object.check_end_date),
                dateFormat: 'dd-MMM-yyyy',
                autoClose: true,
                locale: airPickerLocale,
                onSelect({date}) {

                    if (kv_object.date_dropper_content) {
                        $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                    }

                    if (kv_object.check_min_days_option == '1' && kv_object.check_min_days != "") {
                        const minDate = new Date(date);
                        minDate.setDate(minDate.getDate() + parseInt(kv_object.check_min_days));

                        $('#check-out').prop('readonly', false);
                        $('#check-out').prop('disabled', false);

                        if (window.datepicker2) {
                            window.datepicker2.destroy();
                        }

                        window.datepicker2 = new AirDatepicker('#check-out', {
                            minDate: minDate,
                            maxDate: new Date(kv_object.check_end_date),
                            dateFormat: 'dd-MMM-yyyy',
                            autoClose: true,
                            locale: airPickerLocale,
                            onSelect({date}) {
                                if (kv_object.date_dropper_content) {
                                    $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                                }
                            }
                        });
                    }
                }
            });
        }

        if ($('input#check-out').length == 1) {
            window.datepicker2 = new AirDatepicker('#check-out', {
                minDate: new Date(kv_object.check_start_date),
                maxDate: new Date(kv_object.check_end_date),
                dateFormat: 'dd-MMM-yyyy',
                autoClose: true,
                locale: airPickerLocale,
                onSelect({date}) {
                    if (kv_object.date_dropper_content) {
                        $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                    }
                }
            });
        }

        if ($('input#selected_check_in').length == 1) {
            // console.log( 'selected_check_in' );
            var min_date = $('input#selected_check_in').data( 'min_date' );
            const datepicker1 = new AirDatepicker('#selected_check_in', {
                minDate: new Date(min_date),
                maxDate: new Date(kv_object.check_end_date),
                dateFormat: 'dd-MMM-yyyy',
                autoClose: true,
                locale: airPickerLocale,
                onSelect({date}) {

                    if (kv_object.date_dropper_content) {
                        $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                    }

                    if (kv_object.check_min_days_option == '1' && kv_object.check_min_days != "") {
                        const minDate = new Date(date);
                        minDate.setDate(minDate.getDate() + parseInt(kv_object.check_min_days));

                        $('#selected_check_out').prop('readonly', false);
                        $('#selected_check_out').prop('disabled', false);
                        $('[name="search_roomboss_acc"]').prop('disabled', false);

                        if (window.datepicker2) {
                            window.datepicker2.destroy();
                        }

                        window.datepicker2 = new AirDatepicker('#selected_check_out', {
                            minDate: minDate,
                            maxDate: new Date(kv_object.check_end_date),
                            dateFormat: 'dd-MMM-yyyy',
                            autoClose: true,
                            locale: airPickerLocale,
                            onSelect({date}) {
                                if (kv_object.date_dropper_content) {
                                    $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                                }
                            }
                        });
                    }
                }
            });
        }

        if ($('input#selected_check_out').length == 1) {
            window.datepicker2 = new AirDatepicker('#selected_check_out', {
                minDate: new Date(kv_object.check_start_date),
                maxDate: new Date(kv_object.check_end_date),
                dateFormat: 'dd-MMM-yyyy',
                autoClose: true,
                locale: airPickerLocale,
                onSelect({date}) {
                    if (kv_object.date_dropper_content) {
                        $('.air-datepicker').find('.air-datepicker-body').prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                    }
                }
            });
        }

    }

    let range_values = kv_object.range_values,
        min_price = hz_make_whole( range_values.min_price ),
        max_price = hz_make_whole( range_values.max_price ),
        from = hz_make_whole( range_values.min_price ),
        to = hz_make_whole( range_values.max_price );

    console.log( min_price );
    console.log( max_price );
    console.log( from );
    console.log( to );
    $("#price_range").ionRangeSlider({
        type: "double",
        grid: false,
        min: min_price,
        max: max_price,
        from: from,
        prefix: "¥",
        to: to,
        prettify_separator: ',',
        skin: "round",
        step: 5000,
        onFinish: function (data) {
            
            var price_range_val = '';

            price_range_val = data.from;
            price_range_val += '-'+data.to;
            $( '#price_range_value' ).attr( 'value', price_range_val );
        },
    });

    $(document).on('click', '.bedroom-btn', function (e) {
        let bedroom = $( this ).data( 'value' ),
            container = $('.room-container');
        
        if( bedroom != '*' ){            
            container.isotope({ filter: '[data-bedroom="'+bedroom+'"]', stagger: 30 });
        }
        else{
            container.isotope({ filter: '*', stagger: 30 });
        }
    });


    $(document).on('click', '.select-room .room_select', function (e) {

        let cart_body = $('.cart_body'),
            cart_footer = $('.cart_footer'),
            total = cart_footer.find( '.total' ),

            hotel_rooms = $( this ).parents( '.hotel_rooms' ),
            post_id = hotel_rooms.data( 'post_id' ),

            room = $( this ).parents('.room'),
            room_header = room.find( '.room_header' ),
            room_main = room.find('.room-main-1'),
            room_tid = room.data('tid'),
            room_title = room_main.find('.room_title p').text(),
            room_name = $('.details .room_name'),

            rate_plan_box = $( this ).parents('.rate_plan_box'),
            rate_plan_id = rate_plan_box.data( 'tid' ),
            rate_plan_title = rate_plan_box.find('.rate_plan_title p').text(),
            rate_plan_field = $('.selected_dates .rate_plan'),

            start_date = room_header.find('.start-date').text(),
            
            end_date = room_header.find('.end-date').text(),

            room_prices = rate_plan_box.find('.room_prices'),
            discount_price = rate_plan_box.find('.discount_price'),
            raw_discount_price = discount_price.data('price'),

            price = room_prices.find('.final_price').text(),
            raw_price = room_prices.find('.final_price').data('price'),

            guests_form = room_main.find('.guests_filter .guets_form'),
            duration = guests_form.attr('data-nights'),

            adults_staying = guests_form.find('#num_adults').val() ?? 1,
            children_staying = guests_form.find('#num_children').val() ?? 0,
            infants_staying = guests_form.find('#num_infants').val() ?? 0,

            guests_staying = [
                adults_staying  > 0 ? '<b>adult'  + (adults_staying > 1 ? 's</b>' : '</b>') + ' : ' + adults_staying : '',
                children_staying > 0 ? '<b>child' + (children_staying > 1 ? 'ren</b>' : '</b>') + ' : ' + children_staying : '',
                infants_staying  > 0 ? '<b>infant' + (infants_staying > 1 ? 's</b>' : '</b>')  + ' : ' + infants_staying : '',
            ].filter(Boolean).join(', ') 
            + (duration > 0 ? ', <b>night' + (duration > 1 ? 's</b>' : '</b>') + ' : ' + duration : '');

            let discountHTML = '';
            if(
              typeof raw_discount_price !== 'undefined' &&
              !isNaN(parseFloat(raw_discount_price)) &&
              parseFloat(raw_discount_price) > 0
            ){
                discountHTML = `<div class="discount_price" data-price="${raw_discount_price}">${discount_price.text()}</div>`;
            }

            if( adults_staying == 'Adults' || adults_staying < 1 ){

                $( guests_form.find('#num_adults') ).css( "border", "solid red" );

                /*scroll to form*/
                alert( 'Please select number of adults staying in this room.' );
                return $('html, body').animate({
                    scrollTop: $( room ).offset().top - 140
                }, 600);
            }

            let cart_item = '<div class="selected_room"><div class="close"><button><i class="fa-solid fa-trash"></i></button></div><div class="details" data-tid="'+room_tid+'"><div class="room_name">'+room_title+'</div><div class="room_desc">'+rate_plan_title+'</div><div class="guests_staying">'+guests_staying+'</div><div class="selected_dates"><div class="start_date_container"><b>Start Date:</b><div class="start_date">'+start_date+'</div></div><br><div class="end_date_container"><b>End Date</b><div class="end_date">'+end_date+'</div></div></div>'+discountHTML+'<div class="price" data-price="'+raw_price+'" data-tid="'+rate_plan_id+'">'+price+'</div></div></div>',

            total_val = 0;

            cart_body.append( cart_item );

            $( this ).text( 'Selected' ); 
            $( this ).attr( 'disabled', true ); 
            $( this ).addClass( 'selected' ); 
            $('.cart_body .selected_room .price').each(function(i, e) {
                var current_val = $(this).data( 'price' );
                total_val = total_val + parseInt( current_val );
            } );

            var total_field = $('.total input.total_price');
            total_field.attr( 'data-price', parseInt( total_val ) );
            total_field.val( "¥" + total_val.toLocaleString('ja-JP') );
            $('.cart_footer').show();
            // console.log( total );
    });

    // keep your existing focusin so data-val is stored
    $(document).on('focusin', '.guests_form_select', function (e) {
        $(this).attr('data-val', $(this).val());
    });

    // unified handler: triggers for any guest-related field
    $(document).on("change", ".guest_fields", function () {

        let room           = $(this).closest(".room"),
            room_main      = room.find(".room-main-1"),
            room_options   = room_main.find(".room_options"),
            guests_form    = room_main.find("#guets_form"),
            adults_field   = guests_form.find("#num_adults"),
            guests_fields  = guests_form.find(".guests_form_select"),
            infants_field  = guests_form.find(".net_total_guests"),

            // error containers
            guest_err      = room_main.find(".err_guests"),
            infant_err     = room_main.find(".err_infants"),

            select_btn     = room.find(".bottom-container .select-room .room_select"),

            maxGuests      = parseInt(room_options.find('li[data-option="guests"] .value').text()) || 0,
            maxInfants     = parseInt(room_options.find('li[data-option="infants"] .value').text()) || 0;

        //-------------------------
        // 1. CLEAR ALL ERRORS FIRST
        //-------------------------
        guest_err.hide().text("");
        infant_err.hide().text("");

        guests_fields.css("border", "none");
        infants_field.css("border", "none");

        select_btn.attr("disabled", false);

        //-------------------------
        // 2. VALIDATE ADULT FIELD
        //-------------------------
        let adults_val = parseInt(adults_field.val()) || 0;

        if (adults_val < 1 || adults_field.val() == "Adults") {

            guests_fields.css("border", "2px solid red");

            guest_err
                .text("Please select number of adults staying in this room.")
                .show();

            $("html, body").animate({
                scrollTop: room.offset().top - 140
            }, 600);

            select_btn.attr("disabled", true);
            return;
        }
        var guests_label = '';
        //------------------------------
        // 3. CALCULATE TOTAL GUESTS NON-INFANTS (Adults + Children)
        //------------------------------
        let guest_total = 0;
        guests_fields.each(function () {
            
            if( guests_label.length > 0 ){
                guests_label += ' + '+$(this).find('option:first').val();
            }
            else{
                guests_label += $(this).find('option:first').val();
            }
            guest_total += parseInt($(this).val()) || 0;
        });

        //---------------------------------------
        // 4. VALIDATE NON-INFANT GUEST LIMIT
        //---------------------------------------
        if (guest_total > maxGuests) {

            guests_fields.css("border", "2px solid red");

            guest_err
                .text("Total guests ("+guests_label+") cannot exceed "
                      + maxGuests + " (maximum allowed guests).")
                .show();

            select_btn.attr("disabled", true);
        }

        //---------------------------
        // 5. CALCULATE INFANTS TOTAL
        //---------------------------

        //--------------------------------------
        // 6. VALIDATE INFANTS LIMIT
        //--------------------------------------
        if( infants_field.length > 0 && ( infants_field.val() != 'Infants' && infants_field.val() > 0 ) ){

            let infants_total = parseInt(infants_field.val()) || 0;
            if ((guest_total + infants_total) > (maxGuests + maxInfants)) {

                infants_field.css("border", "2px solid red");

                infant_err
                    .text(
                        "Total guests + infants cannot exceed " +
                        (maxGuests + maxInfants) +
                        " (maximum allowed guests including infants)."
                    )
                    .show();

                select_btn.attr("disabled", true);
            }

           if ((guest_total + infants_total) <= (maxGuests + maxInfants)) {
                infant_err.hide();
                infants_field.css("border", "none");
            }
        }

        //----------------------------------------
        // 7. IF GUESTS VALID → HIDE ONLY GUEST ERROR
        //----------------------------------------
        console.log( guest_total );
        console.log( maxGuests );     
        if (guest_total <= maxGuests) {
            guest_err.hide();
            guests_fields.css("border", "none");
        }

    });

    $(document).on('click', '.close button', function (e) {
        
        let parent = $(this).parents( '.selected_room' ),
            cart_body = $('.cart_body'),
            details = parent.find('.details'),
            tid = details.data('tid'),

            room = $('.room[data-tid="'+tid+'"]'),
            bottom_container = room.find( '.bottom-container' ),
            room_select = bottom_container.find( '.select-room .room_select.selected' ),

            price = details.find('.price').data('price'),
            price_text = parseInt( price ),
            cart_footer = $('.cart_footer'),
            total = cart_footer.find( '.total .total_price' ).data('price'),
            total_val = 0;
            console.log( 'here' );
            console.log( $('.selected_room .details').find('.price') );

        $('.selected_room .details').find('.price').each(function(i, e) {
            
            var current_val = $(this).data( 'price' );
            console.log( 'current_val' );
            console.log( current_val );
            total_val = parseInt( total_val ) + parseInt( current_val );
            console.log( 'total_val' );
            console.log( total_val );
        } );

        var deducted_total = parseInt(total_val - price_text);
        var formated_new_total = "¥" + deducted_total.toLocaleString('ja-JP');

        cart_footer.find( '.total .total_price' ).attr( 'data-price', parseInt( deducted_total ) );
        cart_footer.find( '.total .total_price' ).val( formated_new_total );

        room_select.text('Select');
        room_select.removeClass('selected');
        room_select.attr('disabled', false);
        parent.remove();

        if( $( '.cart_body .selected_room' ).length < 1 ){
            $( '.cart_footer' ).hide();
        }
    });

    $(document).on('input', 'input#duration, input#selected_duration', function (e) {
        let $this = $(this),
            val = parseInt($this.val()) || 0,
            parent = $this.closest('.accomodation_search'),
            err_msg = parent.find('.err_msg');

        if (val < 5) {
            $this.val(5);
            err_msg.text('Duration cannot be less than 5 night').show();
            err_msg.delay(2000).hide('slow');
        } else {
            err_msg.hide();
        }
    });

    $(document).on('keyup', '.filter #search_acc', function (e) {
        
        let form = $('.roomboss_form'),
            cat_slug = form.attr('data-cat'),
            accomodation_search = $(this).parent( '.accomodation_search' ),
            dropdown_results = accomodation_search.find( '.dropdown_results' ),

            ajax_url = kv_object.ajaxurl;

        $.ajax({
            url: ajax_url,
            method: "POST",
            data: {
                action: "hz_get_results_by_name",
                name: $( this ).val(),
                cat_slug: cat_slug,
            },
            success: function(response) {
                let res = response.data,
                    data = res.data,
                    res_properties = data.properties,
                    res_destinations = data.destinations;

                if( res_properties.length > 0 ){
                    dropdown_results.show();
                    dropdown_results.find('ul').html( res_properties );
                }
                else{
                    dropdown_results.hide();
                }

            },
            error: function (jqXHR, exception) {
                var msg = '';
                if (jqXHR.status === 0) {
                    msg = 'Not connect.\n Verify Network.';
                } else if (jqXHR.status == 404) {
                    msg = 'Requested page not found. [404]';
                } else if (jqXHR.status == 500) {
                    msg = 'Internal Server Error [500].';
                } else if (exception === 'parsererror') {
                    msg = 'Requested JSON parse failed.';
                } else if (exception === 'timeout') {
                    msg = 'Time out error.';
                } else if (exception === 'abort') {
                    msg = 'Ajax request aborted.';
                } else {
                    msg = 'Uncaught Error.\n' + jqXHR.responseText;
                }
                console.log( msg );
            },
        });
    });

    $(document).on('click', '.properties .property', function (e) {
        
        let tid = $( this ).data( 'hotel-id' ) ?? 0,
            dropdown_results = $(this).parents( '.dropdown_results' ),
            loc_name_txt = $( this ).text(),
            search_acc = $('#search_acc');

        search_acc.val( loc_name_txt );
        search_acc.attr( 'data-val', tid );
        $('#type_acc').attr( 'value', 'hotel' );
        dropdown_results.fadeOut(200);
    });

    $(document).on('click', '.destination-grid .destination-item', function (e) {
        
        let des_title = $( this ).find( '.destination-title' ) ?? '',
            discovery_box = $( '.discovery-box' ),
            des_title_text = des_title.text() ?? '',
            search_acc = $('#search_acc');

        search_acc.val( des_title_text );
        search_acc.attr( 'data-val', des_title_text );
        $('#type_acc').attr( 'value', 'destination' );
        $(".overlay, .discovery-box").fadeOut(200);
    });

    $(document).on('click', '.area-grid .area-item', function (e) {
        
        let area_title = $( this ).find( '.area-title' ) ?? '',
            discovery_box = $( '.discovery-box' ),
            area_title_text = area_title.text() ?? '',
            search_acc = $('#search_acc');

        search_acc.val( area_title_text );
        search_acc.attr( 'data-val', area_title_text );
        $('#type_acc').attr( 'value', 'area' );
        $(".overlay, .discovery-box").fadeOut(200);
    });

    var parent = $('.dropdown_results').parents();

    parent.on("click", function() {
        
        if( $('.dropdown_results').hide() === false ){
            $('.dropdown_results').hide()
        }
    });

    // Optional: close when ESC key pressed
    $(document).on("keydown", function(e) {
        if (e.key === "Escape") {
          $(".overlay, .discovery-box").fadeOut(200);
        }
    });

    // Close when overlay is clicked
    $(document).on('click', '.view_details .view_details_btn', function (e) {
        let listing_box = $(this).parents('.listing_box'),
            acc_btns = $(this).parents('.acc_buttons'),
            url = acc_btns.attr('data-redirect'),
            params_raw = acc_btns.attr('data-params'),
            post_id = listing_box.data('acc-id');
            console.log( post_id );


        if( Cookies.get( 'hz_acc_params_'+post_id ) ){
            Cookies.remove( 'hz_acc_params_'+post_id, { path: '/' });
            $( this ).trigger( 'click' );
        }
        else{
            window.open(url, '_blank');
        }

    });

    function highlight_invalid_field( field, color = 'red', isEmpty = true ){

        if( isEmpty === true ){
            if( $( field ).val().length < 1 ){
                $( field ).css( "border", "solid " + color );
            }
        }
        else{
            $( field ).css( "border", "solid " + color );
        }

        /*scroll to form*/
        $('html, body').animate({
            scrollTop: $( field ).offset().top - 140
        }, 600);
    }

    $(document).on('click', '.view_book .view_book_btn', function (e){
        let listing_box = $(this).parents('.listing_box'),
            acc_btns = $(this).parents('.acc_buttons'),
            url = acc_btns.attr('data-redirect'),
            params_raw = acc_btns.attr('data-params'),
            post_id = listing_box.data('acc-id'),

            form = $('#roomboss_form'),
            checkIn = form.find('#check-in'),
            checkout = form.find('#check-out'),
            guests = form.find('#guests').val();

        if( checkIn.val().length > 0 && checkout.val().length > 0 ){
            if (params_raw) {
                let paramsObj = new URLSearchParams(params_raw);

                // Update values
                paramsObj.set('guests', guests);
                paramsObj.set('check_rates', 'yes');

                // Convert back to string
                let updatedParams = paramsObj.toString();
                // Save back into cookie
                Cookies.set('hz_acc_params_' + post_id, updatedParams, { expires: 1 });
                // console.log( Cookies.get('hz_acc_params_' + post_id ) );
            }
        }    
        // window.location.href = url+'?check_rates=yes';
        window.open(url, '_blank');
    });

    $(document).on('click', 'i.toggle_long_desc', function (e) {
        let rate_plan_box = $(this).parents('.rate_plan_box'),
            long_desc = rate_plan_box.find('.long_desc');

        long_desc.toggle();
    });

    function getDurationInDays(startDate, endDate) {
      // Month short names
      const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun",
                          "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];

      // Parse DD-MMM-YYYY for start date
      const [startDay, startMon, startYear] = startDate.split("-");
      const startMonth = monthNames.indexOf(startMon);
      const start = new Date(parseInt(startYear, 10), startMonth, parseInt(startDay, 10));

      // Parse DD-MMM-YYYY for end date
      const [endDay, endMon, endYear] = endDate.split("-");
      const endMonth = monthNames.indexOf(endMon);
      const end = new Date(parseInt(endYear, 10), endMonth, parseInt(endDay, 10));

      // Calculate difference in milliseconds
      const diffTime = end - start;

      // Convert to days (1 day = 24 * 60 * 60 * 1000)
      const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));

      return diffDays;
    }

    $(document).on("submit", "form#checkrates_form", function (e) {
        e.preventDefault();
        

        $(this).find("[required]").each(function () {
            if ( $(this).val().length < 1 || $(this).val() < 1 ) {
                    $(this).css("border", "2px solid red"); // mark invalid
            }
            else{
                $(this).css("border", "1px solid #545454"); // mark invalid
            }
        });

        // ========== FIRST FUNCTION LOGIC ==========
        var cat = $(this).attr("data-cat");
        var search_acc = $(this).find("#selected_acc");

        if (search_acc.val().length < 1) {
            $("#type_acc").val("destination");
            
            var formData = $(this).serialize();
            var ajax_url = kv_object.ajaxurl;
            var type_val = search_acc.attr("data-val");

            formData += "&type_acc=hotel";
            formData += "&type_val=" + type_val;
            formData += "&&get_all=no";

            console.log( 'data-val' );
            console.log( search_acc.attr("data-val") );

            // Call your ajax function
            hz_get_rooms(formData, ajax_url);
        }

        // ========== SECOND FUNCTION LOGIC ==========
        let url       = $(this).data("url"),
            post_id   = $(this).data("post_id"),
            selected_guests = $(this).find("#selected_guests").val(),
            start_date = $(this).find("#selected_check_in").val(),
            end_date   = $(this).find("#selected_check_out").val(),
            duration   = getDurationInDays(start_date, end_date);

        if( start_date.length > 1 && end_date.length > 1 ){
            // Get cookie params
            let params_raw = Cookies.get("hz_acc_params_" + post_id);
            let paramsObj = params_raw ? new URLSearchParams(params_raw) : new URLSearchParams();

            // Update values
            paramsObj.set("start_date", start_date);
            paramsObj.set("end_date", end_date);
            paramsObj.set("guests", selected_guests);
            paramsObj.set("check_rates", "yes");

            // Convert back to string
            let updatedParams = paramsObj.toString();

            // Save back into cookie
            Cookies.set("hz_acc_params_" + post_id, updatedParams, { expires: 1 });

            // Reload page (if still needed)
            location.reload();
        }
    });

    gform.utils.addAsyncFilter('gform/submission/pre_submission', async (data) => {
        // Get the form element being submitted
        let $form = $(data.form),
            form_id = $form.attr( 'id' );
            console.log( form_id );
        if( form_id != 'gform_6' ){
            return data;
        }
        var form_abort = false;
        // Now you can pull values like you were doing before
        let firstName = $form.find("input[name='input_11']").val() || "Unknown";
        let lastName  = $form.find("input[name='input_12']").val() || "Unknown";
        let email     = $form.find("input[name='input_3']").val()   || "unknown@example.com";
        let phone     = $form.find("input[name='input_4']").val()   || "090078601";
        let country   = $form.find("select[name='input_5']").val()  || "Japan";
        let city      = $form.find("input[name='input_8']").val()   || "Tokyo Metropolis";
        let postcode  = $form.find("input[name='input_9']").val()   || "09876";
        let lang      = $form.find("select[name='input_6']").val()  || "en";
        let address   = $form.find("textarea[name='input_10']").val()|| "Default Address";
        let total     = $('.booking_total').attr( 'data-total' )   || "0";
     
        $('.fw_total input').attr( 'value', total );
        let config = {
            env: 'demo', // switch to "production" when live
            recipientCode: 'JSE',
            amount: String( total )+'.00', // ensures correct format
            email: email,
            firstName: firstName,
            lastName: lastName,
            address: address,
            city: city,
            state: '',
            country: country,
            phone: phone,
            zip: postcode,
            currency: 'JPY',
            returnUrl: window.location.href + '?payment=success',
            requestPayerInfo: true,
            requestRecipientInfo: true,
            paymentOptionsConfig: {
                filters: {
                    type: [],
                    currency: ['nonFX', 'fx', 'local', 'foreign'],
                }
            },
            recipientFields: {
                booking_reference: "GF-" + Date.now(),
            },
            callbackUrl: 'https://jsestaging.japanskiexperience.com/api/payment-notification',
            callbackVersion: "2",
        };

        // console.log( data );
        let modal = window.FlywirePayment.initiate({
            ...config, // keep all your existing config
            onComplete: function (payment) {
                console.log("✅ Payment successful:", payment);

                $.ajax({
                    url: kv_object.ajaxurl,   // <-- your backend REST endpoint
                    method: "POST",
                    dataType: "json",
                    data: {
                        action: 'flywire_payment',
                        payment: payment,
                    },
                    success: function (response) {
                        if (response.success) {
                            alert("Booking created successfully!");
                            window.location.href = base_url + "/booking-confirmation/?id=" + response.booking_id;
                        } else {
                            alert("Payment succeeded but booking failed. Please contact support.");
                            console.error(response.error);
                        }
                    },
                    error: function (xhr, status, error) {
                        console.error("❌ AJAX error:", error);
                        alert("Something went wrong while creating your booking.");
                    }
                });
            },
            onError: function (error) {
                console.error("❌ Flywire error:", error);
                form_abort = true;   // 🚫 form_abort submission cleanly
                return data;
            },
            onCancel: function () {
                console.log("⚠️ Payment cancelled by user.");
                form_abort = true;   // 🚫 abort submission cleanly
                return data;
            }
        });
        modal.render();
    });

    $('.close-modal').on('click', function(e){    
        $('#checkin-modal').fadeOut();
    });
    
    $('.vote_close').on('click', function(e){    
        $('.vote_for_us').fadeOut();
    });


    $('#checkin-form').on('submit', function(e){
        
    });

    $(document).on('click', '.detail_buttons .get_quote', function(e) {
        e.preventDefault();
        $('.enquire_form').show();
        if( !$(".custom_quote_form").is(":visible") ){
            $( '.custom_quote_form, .filter-area' ).toggle();
        }
        $('html, body').animate({
            scrollTop: $('.enquire_form').parent('.container').offset().top
        }, 800);
    });

    $(document).on('click', '.detail_buttons .instant_quote', function(e) {
        e.preventDefault();
        if( !$(".filter-area").is(":visible") ){
            $( '.custom_quote_form, .filter-area' ).toggle();
        }
        $('html, body').animate({
            scrollTop: $('.filter-area').parent('.container').offset().top
        }, 800);
        $('.enquire_form').show();
    });

    $(document).on('click', '.form_tabs .quote_tab', function(e) {
        e.preventDefault();
        $('.enquire_form').show();
        if( !$(".quote_form").is(":visible") ){
            $( '.rb_form, .quote_form' ).toggle();
            $( '.quote_tab, .rb_tab' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( 'section.tabs, section.team_carousel' ).show();
        }
    });

    $(document).on('click', '.form_tabs .rb_tab', function(e) {
        e.preventDefault();
        $('.enquire_form').show();
        if( !$(".rb_form").is(":visible") ){
            $( '.rb_form, .quote_form' ).toggle();
            $( '.quote_tab, .rb_tab' ).removeClass( 'active' );
            $( this ).addClass( 'active' );
            $( 'section.tabs, section.team_carousel' ).hide();
        }
    });

    $(document).on('input', '#duration, #selected_duration', function() {
        let val = parseInt($(this).val(), 10);

        if (val > 90) {
            $(this).val(90);
        } else if (val < 1 || isNaN(val)) {
            $(this).val( '' );
        }
    });

    $(document).on('click', '.filter_slider .filter_slider_btn', function(e) {
        e.preventDefault();
        $('.filter_container .filter').slideToggle();
        $( this ).find( 'i' ).toggleClass("fa-sliders fa-times")
    });

    $(document).on('click', '.booking-right .proceed-btn', function(e) {
        e.preventDefault();
        var url = base_url+'/jsnew/confirm-booking/'
        window.open(url, '_blank');
    });
});