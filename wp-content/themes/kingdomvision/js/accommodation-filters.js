/**
 * Accommodation Filters Manager
 * Modular system for managing accommodation search, filtering, sorting, and pagination
 * Features: Error handling, AJAX loading states, filter management, date picking
 */

const AccommodationFilters = (function() {
    'use strict';

    // ============================================================================
    // CONFIGURATION
    // ============================================================================
    const CONFIG = {
        ajax: {
            // url: '<?php echo admin_url('admin-ajax.php'); ?>',
            url: kv_object.ajaxurl,
            action: 'niseko_search',
            timeout: 30000
        },
        selectors: {
            searchForm: '.search-card.js-search-card',
            searchResults: '#accom-search-results',
            filterPanel: '#filter-panel',
            loadMore: '#load-more',
            appliedFilters: '.filter-tabs',
            priceMin: '#price_min',
            priceMax: '#price_max',
            roomsCount: '#rooms-count',
            propertiesCount: '#properties-count',
            sortBy: '#sort-by',
            checkin: '.js-sb-checkin, #sc-check-in, #input_1_5',
            checkout: '.js-sb-checkout, #sc-check-out, #input_1_6',
            resort: '.js-sb-resort',
            guestsDisplay: '.js-sb-guests-display'
        },
        defaults: {
            perPage: 6,
            priceMin: 0,
            priceMax: 1000000,
            priceGap: 1000,
            loadingText: 'Loading...',
            loadMoreText: 'Load More',
            loadingMoreText: 'Loading…'
        },
        storage: {
            hotelSearch: 'niseko_hotel_search',
            checkin: 'niseko_checkin',
            checkout: 'niseko_checkout',
            resort: 'sb_resort',
            adults: 'sb_adults',
            children: 'sb_children',
            infants: 'sb_infants'
        },
    };

    // ============================================================================
    // STATE & CACHE
    // ============================================================================
    const State = {
        isSearching: false,
        priceChanged: false,
        currentPage: 1,

        cache: {
            $form: null,
            $results: null,
            $loadMore: null,
            $priceMin: null,
            $priceMax: null,
            $roomsCount: null,
            $propertiesCount: null,
        },

        init: function() {
            this.cache.$form = jQuery(CONFIG.selectors.searchForm);
            this.cache.$results = jQuery(CONFIG.selectors.searchResults);
            this.cache.$loadMore = jQuery(CONFIG.selectors.loadMore);
            this.cache.$priceMin = jQuery(CONFIG.selectors.priceMin);
            this.cache.$priceMax = jQuery(CONFIG.selectors.priceMax);
            this.cache.$roomsCount = jQuery(CONFIG.selectors.roomsCount);
            this.cache.$propertiesCount = jQuery(CONFIG.selectors.propertiesCount);

            return Object.values(this.cache).every(el => el && el.length > 0);
        }
    };

    // ============================================================================
    // UI UTILITIES - Loading states and error messages
    // ============================================================================
    const UI = {
        showLoader: function(target = State.cache.$results) {
            if (!target || !target.length) return;
            target.html(`
                <div class="accom-loader">
                    <div class="spinner"></div>
                    <p>${CONFIG.defaults.loadingText}</p>
                </div>
            `);
        },

        hideLoader: function() {
            jQuery('.accom-loader').remove();
        },

        showError: function(message = 'An error occurred while loading accommodation') {
            State.cache.$results.html(`
                <div class="accom-error-message">
                    <i class="fa-solid fa-exclamation-circle"></i>
                    <p>${message}</p>
                </div>
            `);
        },

        showNoResults: function() {
            State.cache.$results.html(`
                <p class="no-results">No accommodation found. Try adjusting your filters.</p>
            `);
        },

        setLoadMoreState: function(isLoading, currentPage = 1) {
            const $btn = State.cache.$loadMore;
            if (!$btn || !$btn.length) return;

            if (isLoading) {
                $btn.prop('disabled', true).addClass('loading').text(CONFIG.defaults.loadingMoreText);
            } else {
                $btn.prop('disabled', false).removeClass('loading').text(CONFIG.defaults.loadMoreText);
                $btn.data('page', currentPage);
            }
        },

        updateCounts: function(roomCount, propertyCount) {
            State.cache.$roomsCount.text(roomCount || 0);
            State.cache.$propertiesCount.text(propertyCount || 0);
        },

        resetCounts: function() {
            this.updateCounts(0, 0);
        }
    };

    // ============================================================================
    // FILTER DATA COLLECTION
    // ============================================================================
    const FilterData = {
        collect: function(context) {
            const $scope = context ? jQuery(context).closest('.search-card') : jQuery(CONFIG.selectors.searchForm).first();
            const guestState = SearchState.getGuestCounts($scope);

            return {
                checkin: $scope.find(CONFIG.selectors.checkin).val() || '',
                checkout: $scope.find(CONFIG.selectors.checkout).val() || '',
                resort: $scope.find(CONFIG.selectors.resort).val() || '',
                guests: guestState.adults + guestState.children + guestState.infants,
                adults: guestState.adults,
                children: guestState.children,
                infants: guestState.infants,
                bedrooms: this.getCheckboxValues('bedrooms[]'),
                areas: this.getCheckboxValues('area[]'),
                accommodation_type: this.getCheckboxValues('type[]'),
                ski_in_ski_out: jQuery('input[name="ski_in_ski_out"]:checked').val() || '',
                onsen: jQuery('input[name="onsen"]:checked').val() || '',
                booking: jQuery('input[name="booking"]:checked').val() || '',
                discount: jQuery('input[name="discount"]:checked').val() || '',
                sort: jQuery(CONFIG.selectors.sortBy).val() || '',
                price_min: State.priceChanged ? State.cache.$priceMin.val() : null,
                price_max: State.priceChanged ? State.cache.$priceMax.val() : null,
            };
        },

        getCheckboxValues: function(name) {
            const values = [];
            jQuery(`input[name="${name}"]:checked`).each(function() {
                values.push(jQuery(this).val());
            });
            return values;
        },

        saveDatesTolocalStorage: function(checkin, checkout) {
            if (checkin && checkout) {
                localStorage.setItem(CONFIG.storage.checkin, checkin);
                localStorage.setItem(CONFIG.storage.checkout, checkout);
            }
        }
    };

    const SearchState = {
            getGuestCounts: function(context) {
                const $scope =
                    context && context.jquery
                        ? context
                        : context
                            ? jQuery(context).closest('.search-card')
                            : jQuery(CONFIG.selectors.searchForm).first();

                const read = (selector, isInput, fallback) => {
                    const raw = isInput
                        ? $scope.find(selector).first().val()
                        : $scope.find(selector).first().text();

                    const n = parseInt(raw, 10);
                    return isNaN(n) ? fallback : n;
                };

                return {
                    adults: read('.js-v-adults', false, 2),
                    children: read('.js-v-children', false, 0),
                    infants: read('.js-v-infants', false, 0),
                };
            },

            save: function(context, values = null) {

                const $scope = context && context.jquery
                    ? context
                    : context
                        ? jQuery(context).closest('.search-card')
                        : jQuery(CONFIG.selectors.searchForm).first();

                // IMPORTANT: use passed values first, fallback to DOM only if missing
                const guestState = values || this.getGuestCounts($scope);
                const state = values || {};
                const resort = state.resort !== undefined ? state.resort : ($scope.find(CONFIG.selectors.resort).val() || '');
                const checkin = state.checkin !== undefined ? state.checkin : ($scope.find(CONFIG.selectors.checkin).val() || '');
                const checkout = state.checkout !== undefined ? state.checkout : ($scope.find(CONFIG.selectors.checkout).val() || '');

                if (resort) {
                    // localStorage.setItem(CONFIG.storage.resort, resort);
                } else {
                    localStorage.removeItem(CONFIG.storage.resort);
                }

                if (checkin) {
                    localStorage.setItem(CONFIG.storage.checkin, checkin);
                }

                if (checkout) {
                    localStorage.setItem(CONFIG.storage.checkout, checkout);
                }

                localStorage.setItem(CONFIG.storage.adults, String(parseInt(guestState.adults, 10) || 2));
                localStorage.setItem(CONFIG.storage.children, String(parseInt(guestState.children, 10) || 0));

                localStorage.setItem(CONFIG.storage.infants, String(parseInt(guestState.infants, 10) || 0));
            },

            restore: function() {
                const resort = localStorage.getItem(CONFIG.storage.resort);
                const checkin = localStorage.getItem(CONFIG.storage.checkin);
                const checkout = localStorage.getItem(CONFIG.storage.checkout);
                const adults = parseInt(localStorage.getItem(CONFIG.storage.adults), 10);
                const children = parseInt(localStorage.getItem(CONFIG.storage.children), 10);
                const infants = parseInt(localStorage.getItem(CONFIG.storage.infants), 10);

                if (resort) {
                    jQuery(CONFIG.selectors.resort).each(function() {
                        if (!jQuery(this).val()) {
                            jQuery(this).val(resort);
                        }
                    });
                }

                if (checkin) {
                    jQuery(CONFIG.selectors.checkin).each(function() {
                        if (!jQuery(this).val()) {
                            jQuery(this).val(checkin);
                        }
                    });
                }

                if (checkout) {
                    jQuery(CONFIG.selectors.checkout).each(function() {
                        if (!jQuery(this).val()) {
                            jQuery(this).val(checkout).prop('disabled', false);
                        }
                    });
                }

                if (Number.isFinite(adults)) {
                    this.applyGuests({
                        adults: adults,
                        children: Number.isFinite(children) && children >= 0 ? children : 0,
                        infants: Number.isFinite(infants) && infants >= 0 ? infants : 0,
                    });
                }
            },

            applyGuests: function(guestState) {

                const adultsRaw = parseInt(guestState.adults, 10);
                const childrenRaw = parseInt(guestState.children, 10);
                const infantsRaw = parseInt(guestState.infants, 10);

                const adults = isNaN(adultsRaw) ? 2 : Math.max(1, adultsRaw);
                const children = isNaN(childrenRaw) ? 0 : Math.max(0, childrenRaw);
                const infants = isNaN(infantsRaw) ? 0 : Math.max(0, infantsRaw);

                const totalGuests = adults + children;

                let label = `${totalGuests} Guest${totalGuests !== 1 ? 's' : ''}`;

                if (infants > 0) {
                    label += `, ${infants} Infant${infants !== 1 ? 's' : ''}`;
                }

                const $scopes = jQuery(document).find(CONFIG.selectors.searchForm);

                $scopes.each(function (i) {
                    const $scope = jQuery(this);
                    if (!$scope.length) return;

                    $scope.find('.js-v-adults').text(adults);
                    $scope.find('.js-v-children').text(children);
                    $scope.find('.js-v-infants').text(infants);

                    $scope.find('.js-m-adults').val(adults);
                    $scope.find('.js-m-children').val(children);
                    $scope.find('.js-m-infants').val(infants);

                    $scope.find('.js-btn-adults-minus').prop('disabled', adults <= 1);
                    $scope.find('.js-btn-children-minus').prop('disabled', children <= 0);
                    $scope.find('.js-btn-infants-minus').prop('disabled', infants <= 0);

                    $scope.find(CONFIG.selectors.guestsDisplay)
                        .text(label)
                        .removeClass('empty');
                });
            },

            clear: function() {
                [
                    CONFIG.storage.checkin,
                    CONFIG.storage.checkout,
                    CONFIG.storage.resort,
                    CONFIG.storage.adults,
                    CONFIG.storage.children,
                    CONFIG.storage.infants,
                ].forEach((key) => localStorage.removeItem(key));
            }
    };

    window.syncGuestUI = function (state) {

        const adults = state.adults ?? 2;
        const children = state.children ?? 0;
        const infants = state.infants ?? 0;

        const total = adults + children;

        let label = `${total} Guest${total !== 1 ? 's' : ''}`;
        if (infants > 0) {
            label += `, ${infants} Infant${infants !== 1 ? 's' : ''}`;
        }

        // ✅ sync ALL instances (this is the key fix)
        jQuery(CONFIG.selectors.searchForm).each(function () {
            const $card = jQuery(this);
            if (!$card.length) return;

            // counters
            $card.find('.js-v-adults').text(adults);
            $card.find('.js-v-children').text(children);
            $card.find('.js-v-infants').text(infants);

            // inputs
            $card.find('.js-m-adults').val(adults);
            $card.find('.js-m-children').val(children);
            $card.find('.js-m-infants').val(infants);

            // label
            $card.find('.js-sb-guests-display')
                .text(label)
                .removeClass('empty');

            // buttons
            $card.find('.js-btn-adults-minus').prop('disabled', adults <= 1);
            $card.find('.js-btn-children-minus').prop('disabled', children <= 0);
            $card.find('.js-btn-infants-minus').prop('disabled', infants <= 0);
        });
    };
    // ============================================================================
    // AJAX SEARCH - With error handling and timeout
    // ============================================================================
    const Search = {
        run: function(page = 1, append = false, context = null) {
            if (State.isSearching) {
                console.warn('Search already in progress');
                return;
            }

            State.isSearching = true;
            State.currentPage = page;

            const filterData = FilterData.collect(context);
            const perPage = State.cache.$form.attr('per_page') || CONFIG.defaults.perPage;

            SearchState.save(context, filterData);

            // Save dates
            FilterData.saveDatesTolocalStorage(filterData.checkin, filterData.checkout);
            
            // Show loader
            if (!append) {
                UI.resetCounts();
                UI.showLoader();
            }

            const ajaxData = {
                action: CONFIG.ajax.action,
                page: page,
                per_page: perPage,
                location: jQuery('#search_acc').val() || '',
                // category_slug: jQuery('input[name="category_slug"]').val() || '',
                property_id: jQuery('#search_acc').data("val") || '0',
                ...filterData,
                hotel_search: localStorage.getItem(CONFIG.storage.hotelSearch) === 'true' ? 1 : 0,
            };

            jQuery.ajax({
                url: CONFIG.ajax.url,
                type: 'POST',
                data: ajaxData,
                timeout: CONFIG.ajax.timeout,
                beforeSend: function() {
                    if (!append) {
                        UI.showLoader();
                    }
                    jQuery( '.sb-submit img' ).attr('src', base_url+'/wp-content/themes/kingdomvision/images/loader-circle.gif');
                },
                success: function(res) {
                    if (res.success && res.data) {
                        // ✅ REDIRECT: If server detects resort mismatch or incorrect URL landing
                        if (res.data.redirect) {
                            window.location.href = res.data.redirect;
                            return;
                        }
                        Search.handleSuccess(res.data, append, page);
                    } else {
                        Search.handleError('Invalid response from server');
                    }
                },
                error: function(xhr, status, error) {
                    let errorMsg = 'Failed to load accommodation';

                    if (status === 'timeout') {
                        errorMsg = 'Request timed out. Please try again.';
                    } else if (xhr.status === 0) {
                        errorMsg = 'Network error. Please check your connection.';
                    } else if (xhr.status >= 500) {
                        errorMsg = 'Server error. Please try again later.';
                    }

                    Search.handleError(errorMsg, error);
                },
                complete: function() {
                    State.isSearching = false;
                    UI.hideLoader();
                    jQuery( '.sb-submit img' ).attr('src', base_url+'/wp-content/themes/kingdomvision/images/search-icon.png');
                    
                }
            });
        },

        handleSuccess: function(data, append, page) {
            if (!data.html) {
                UI.showNoResults();
                State.cache.$loadMore.hide();
                return;
            }

            if (append) {
                State.cache.$results.append(data.html);
            } else {
                State.cache.$results.html(data.html);
            }

            UI.updateCounts(data.room_count || 0, data.count || 0);

            // Update Load More button
            if (data.has_more) {
                UI.setLoadMoreState(false, page);
                State.cache.$loadMore.show();
            } else {
                State.cache.$loadMore.hide().data('page', 1);
            }
        },

        handleError: function(message, error) {
            console.error('AJAX Search Error:', error || message);
            UI.showError(message);
            State.cache.$loadMore.hide();
        }
    };

    // ============================================================================
    // PRICE RANGE HANDLER
    // ============================================================================
    const PriceRange = {
        init: function() {
            const $priceMin = State.cache.$priceMin;
            const $priceMax = State.cache.$priceMax;

            if (!$priceMin.length || !$priceMax.length) return;

            $priceMin.on('input change', this.update.bind(this));
            $priceMax.on('input change', this.update.bind(this));

            // Trigger search only when user stops sliding (on change)
            $priceMin.on('change', () => {
                Filters.upsertSelected('price', `${$priceMin.val()}-${$priceMax.val()}`, `JPY ${parseInt($priceMin.val()).toLocaleString()} – ${parseInt($priceMax.val()).toLocaleString()}`, true);
                Search.run(1, false);
            });
            $priceMax.on('change', () => {
                Filters.upsertSelected('price', `${$priceMin.val()}-${$priceMax.val()}`, `JPY ${parseInt($priceMin.val()).toLocaleString()} – ${parseInt($priceMax.val()).toLocaleString()}`, true);
                Search.run(1, false);
            });
        },

        update: function() {
            State.priceChanged = true;

            let min = parseInt(State.cache.$priceMin.val(), 10);
            let max = parseInt(State.cache.$priceMax.val(), 10);

            // Clamp values
            if (min > max - CONFIG.defaults.priceGap) {
                min = max - CONFIG.defaults.priceGap;
                State.cache.$priceMin.val(min);
            }

            if (max < min + CONFIG.defaults.priceGap) {
                max = min + CONFIG.defaults.priceGap;
                State.cache.$priceMax.val(max);
            }

            // Update display
            jQuery('#price-min-label').text(min.toLocaleString('ja-JP'));
            jQuery('#price-max-label').text(max.toLocaleString('ja-JP'));
        }
    };

    // ============================================================================
    // FILTER MANAGEMENT
    // ============================================================================
    const Filters = {
        upsertSelected: function(type, value, label, single = false) {
            const container = jQuery(CONFIG.selectors.appliedFilters);
            const template = jQuery('#selected-filter-template .filter');

            if (!container.length || !template.length) return;

            let filterEl;

            if (single) {
                filterEl = container.find(`.filter-tab[data-type="${type}"]`).closest('.filter');
            } else {
                filterEl = container.find(`.filter-tab[data-type="${type}"][data-value="${value}"]`).closest('.filter');
            }

            if (!filterEl.length) {
                filterEl = template.clone();
                filterEl.find('.close_filter').on('click', Events.onRemoveFilterTab); // Re-bind event to clone
                
                const resetTab = container.find('.filter-tab.reset').closest('.filter');
                
                if (resetTab.length && resetTab.is(':visible')) {
                    filterEl.insertBefore(resetTab);
                } else {
                    container.append(filterEl);
                }
            }

            const tab = filterEl.find('.filter-tab');
            tab.data('type', type);
            tab.attr('data-type', type); // Ensure attribute is set for CSS selectors
            if (value !== undefined && value !== null) {
                tab.data('value', value);
                tab.attr('data-value', value);
            }
            tab.text(label);

            this.updateResetVisibility();
        },

        removeSelected: function(type, value = null) {
            const container = jQuery(CONFIG.selectors.appliedFilters);
            const selector = value !== null
                ? `.filter-tab[data-type="${type}"][data-value="${value}"]`
                : `.filter-tab[data-type="${type}"]`;

            container.find(selector).closest('.filter').remove();
            this.updateResetVisibility();
        },

        lockFilterIfScoped: function(type) {
            const container = jQuery(CONFIG.selectors.appliedFilters);
            const filterTab = container.find(`.filter-tab[data-type="${type}"]`);
            
            if (filterTab.length < 1) return;

            const filterWrapper = filterTab.closest('.filter');
            const closeBtn = filterWrapper.find('.close_filter');
            
            if (closeBtn.length) {
                closeBtn.remove();
                filterWrapper.addClass('locked');
            }
        },

        updateResetVisibility: function() {
            const container = jQuery(CONFIG.selectors.appliedFilters);
            const resetFilter = container.find('.filter-tab.reset').closest('.filter');
            const activeFilters = container.find('.filter:has(.close_filter)');

            if (resetFilter.length) {
                resetFilter.toggle(activeFilters.length > 0);
            }
        },

        updateBaseArea: function(resortKey) {
            if (!resortKey) return;

            const $container = jQuery('.filter-accordion.base_area .accordion-content');
            if (!$container.length) return;

            jQuery.ajax({
                url: CONFIG.ajax.url,
                type: 'POST',
                data: {
                    action: 'kv_get_resort_areas',
                    resort: resortKey
                },
                success: function(res) {
                    if (res.success && res.data) {
                        let html = '<div class="checkbox-list">';
                        
                        Object.entries(res.data).forEach(([slug, name]) => {
                            html += `
                                <div class="ch-item">
                                    <input type="checkbox" name="area[]" data-type="area" id="area-${slug}" value="${slug}">
                                    <label for="area-${slug}">${name}</label>
                                </div>`;
                        });
                        
                        html += '</div>';
                        $container.html(html);
                        
                        // Ensure the base area accordion is visible
                        jQuery('.filter-accordion.base_area').show();
                    } else {
                        $container.empty();
                        jQuery('.filter-accordion.base_area').hide();
                    }
                },
                error: function (xhr, exception) {
                    // $container.empty();
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
        },

        reset: function() {
            localStorage.setItem('apply-filters', 'false');

            // Clear all filters
            jQuery(`${CONFIG.selectors.appliedFilters} .close_filter`).trigger('click');

            jQuery(`${CONFIG.selectors.appliedFilters} .filter`).hide();

            localStorage.setItem('apply-filters', 'true');
            SearchState.clear();

            jQuery('input[name="checkin"], input[name="checkout"]').val('');
            jQuery('#apply-filters').trigger('click');
        }
    };

    // ============================================================================
    // EVENT HANDLERS
    // ============================================================================
    const Events = {
        init: function() {
            // Form submission
            State.cache.$form.off('submit').on('submit', this.onFormSubmit.bind(this));

            // Apply filters
            jQuery('#apply-filters').on('click', this.onApplyFilters.bind(this));

            // Clear filters
            jQuery('#clear-filters').on('click', this.onClearFilters.bind(this));

            // Close filter panel on outside click
            jQuery(document).on('click', this.onDocumentClick.bind(this));

            // Filter panel open/close
            jQuery('#open-filter').on('click', this.onOpenFilter.bind(this));
            jQuery('#close-filter').on('click', this.onCloseFilter.bind(this));

            // Accordion
            jQuery(document).on('click', '.accordion-header', this.onAccordionClick.bind(this));

            // Load more
            State.cache.$loadMore.on('click', this.onLoadMore.bind(this));

            // Filter tab close
            jQuery(document).on('click', '.close_filter', this.onRemoveFilterTab.bind(this));

            // Resort change
            jQuery(document).on('change', CONFIG.selectors.resort, this.onResortChange.bind(this));

            // Checkbox changes
            jQuery(document).on('change', '.ch-item input', this.onFilterInputChange.bind(this));

            // Sort
            jQuery('#sortTrigger').on('click', this.onSortTrigger.bind(this));
            jQuery('#sortMenu a').on('click', this.onSortOption.bind(this));

            // Accommodation name search autocomplete
            jQuery(document).on('keyup', '.property-search #search_acc:not([readonly])', function(e) {
                let form = jQuery('#accom-search-form'),
                    cat_slug = form.attr('data-cat'),
                    accomodation_search = jQuery(this).parents('.accordion-content'),
                    dropdown_results = accomodation_search.find('.dropdown_results'),
                    clear_loc = accomodation_search.find('.clear_loc'),
                    ajax_url = kv_object.ajaxurl;

                if( jQuery( this ).val() === '' ){
                    clear_loc.hide();
                }
                else{
                    clear_loc.show();
                }

                jQuery.ajax({
                    url: ajax_url,
                    method: "POST",
                    data: {
                        action: "hz_get_results_by_name",
                        name: jQuery(this).val(),
                        cat_slug: cat_slug,
                    },
                    success: function(response) {
                        let res = response.data,
                            data = res.data,
                            res_properties = data.properties,
                            res_destinations = data.destinations;

                        if (res_properties.length > 0) {
                            dropdown_results.show();
                            dropdown_results.find('ul').html(res_properties);
                        } else {
                            dropdown_results.hide();
                        }
                    },
                    error: function(jqXHR, exception) {
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
                        console.log(msg);
                    },
                });
            });

            
            jQuery('.filter-tab.reset').on('click', function() {
                localStorage.setItem( 'apply-filters', false );
                jQuery('.filter-tabs .close_filter').each( function ( i,e ){
                    jQuery(this).trigger('click');
                } );
                var parent = jQuery( this ).parent('.filter');
                parent.hide();
                localStorage.setItem( 'apply-filters', true );
                localStorage.removeItem( 'niseko_checkin' );
                localStorage.removeItem( 'niseko_checkout' );
                jQuery('#sc-check-in, #sc-check-out').val('');
                jQuery('#apply-filters').trigger('click');
            });

            // Handle selection from autocomplete results
            jQuery(document).on('click', '.properties .property', function(e) {
                let tid = jQuery(this).data('property-id') ?? 0,
                    dropdown_results = jQuery(this).parents('.dropdown_results'),
                    loc_name_txt = jQuery(this).text(),
                    search_acc = jQuery('#search_acc');

                search_acc.val(loc_name_txt);
                search_acc.attr('data-val', tid);
                search_acc.attr('readonly', true);
                dropdown_results.fadeOut(200);
                
                // Search.run(1, false);
            });

            // Handle selection from autocomplete results
            jQuery(document).on('click', '.clear_loc', function(e) {
                const $search_acc = jQuery('#search_acc');
                $search_acc.attr('readonly', false);
                $search_acc.val('');
                jQuery( this ).hide();
                
                // Search.run(1, false);
            });
        },

        updateGuestDisplay: function($card) {
            // Use global values if no specific card context is provided for calculation
            const adults = parseInt(jQuery('.js-v-adults').first().text()) || 1;
            const children = parseInt(jQuery('.js-v-children').first().text()) || 0;
            const infants = parseInt(jQuery('.js-v-infants').first().text()) || 0;
            
            const totalGuests = adults + children;
            let label = `${totalGuests} Guest${totalGuests !== 1 ? 's' : ''}`;
            
            if (infants > 0) {
                label += `, ${infants} Infant${infants !== 1 ? 's' : ''}`;
            }

            const $displays = jQuery(CONFIG.selectors.guestsDisplay);

            $displays.text(label);
            $displays.toggleClass('empty', false);
        },

        onFormSubmit: function(e) {
            console.log( 'onFormSubmit' );
            e.preventDefault();
            localStorage.setItem(CONFIG.storage.hotelSearch, 'true');
            
            Search.run(1, false);
        },

        onApplyFilters: function() {
            jQuery(CONFIG.selectors.filterPanel).removeClass('active');
            Search.run(1, false);
        },

        onClearFilters: function() {
            Filters.reset();
        },

        onDocumentClick: function(e) {
            const $panel = jQuery(CONFIG.selectors.filterPanel);
            const isClickInsidePanel = jQuery(e.target).closest($panel).length > 0;
            const isClickOnOpenBtn = jQuery(e.target).closest('#open-filter').length > 0;

            if (!isClickInsidePanel && !isClickOnOpenBtn) {
                $panel.removeClass('active');
            }
        },

        onOpenFilter: function() {
            jQuery(CONFIG.selectors.filterPanel).addClass('active');
        },

        onCloseFilter: function() {
            jQuery(CONFIG.selectors.filterPanel).removeClass('active');
        },

        onAccordionClick: function(e) {
            e.preventDefault();
            const $accordion = jQuery(e.target).closest('.filter-accordion');
            jQuery('.filter-accordion').not($accordion).removeClass('active');
            $accordion.toggleClass('active');
        },

        onLoadMore: function() {
            const nextPage = State.cache.$loadMore.data('page') + 1;
            UI.setLoadMoreState(true);
            Search.run(nextPage, true);
        },

        onRemoveFilterTab: function(e) {
            const $filter = jQuery(e.target).closest('.filter');
            const $tab = $filter.find('.filter-tab');
            const type = $tab.attr('data-type') || $tab.data('type');
            const value = $tab.attr('data-value') || $tab.data('value');
            const apply = localStorage.getItem('apply-filters') ?? localStorage.setItem('apply-filters', 'true');

            // Update associated inputs
            if (type === 'price') {
                const $priceMin = State.cache.$priceMin;
                const $priceMax = State.cache.$priceMax;
                const defMin = parseInt($priceMin.attr('min'));
                const defMax = parseInt($priceMax.attr('max'));

                $priceMin.val(defMin);
                $priceMax.val(defMax);
                jQuery('#price-min-label').text(defMin);
                jQuery('#price-max-label').text(defMax);
                State.priceChanged = false;
            } else if (type === 'bedrooms') {
                jQuery(`input[name="bedrooms[]"][value="${value}"]`).prop('checked', false);
            } else if (type === 'resort') {
                jQuery(CONFIG.selectors.resort).val('');
            } else {
                jQuery(`input[data-type="${type}"][value="${value}"]`).prop('checked', false);
            }

            $filter.remove();

            if (apply === 'true' || apply === true) {
                // Search.run(1, false);
                $('#apply-filters').trigger('click');
            }
        },

        onResortChange: function(e) {
            const $input = jQuery(e.target);
            const resortSlug = $input.val()?.toLowerCase() || '';
            
            // Sync all resort dropdowns
            jQuery(CONFIG.selectors.resort).val($input.val());
            SearchState.save($input.closest('.search-card'), {
                resort: $input.val() || ''
            });

            const resortKey = resortSlug.replace('-accommodation', '');
            Filters.updateBaseArea(resortKey);
            jQuery('.filter-accordion.base_area').show();

            // ✅ REMOVED: Search.run(1, false);
            // Changing the resort should update filters but not trigger the redirect/search AJAX.
        },

        onFilterInputChange: function(e) {
            const $input = jQuery(e.target);
            let type = $input.data('type'); // Get data-type first
            const value = $input.val();
            const label = $input.siblings('label').text().trim();
            const isRadio = $input.attr('type') === 'radio';
            
            // If it's a radio button and data-type is not explicitly set, use the name attribute as the type
            if (isRadio && !type) {
                type = $input.attr('name');
            }
            
            let finalType = type;
            let finalLabel = label;

            // Handle Bedroom Specific Labels
            if (type && type.includes('bedrooms')) {
                finalType = 'bedrooms';
                finalLabel = parseInt(value) > 1 ? `${value} bedrooms` : `${value} bedroom`;
            }

            if ($input.is(':checked')) {
                Filters.upsertSelected(finalType, value, finalLabel, isRadio);
            } else if (!isRadio) {
                Filters.removeSelected(finalType, value);
            }
            
            Filters.updateResetVisibility();
            // Search.run(1, false);
        },

        onSortTrigger: function(e) {
            e.preventDefault();
            e.stopPropagation();
            jQuery('#sortDropdown').toggleClass('open');
        },

        onSortOption: function(e) {
            e.preventDefault();
            const $option = jQuery(e.target);
            const value = $option.data('value');

            jQuery(CONFIG.selectors.sortBy).val(value);
            jQuery('#sortDropdown').removeClass('open');
            // Search.run(1, false);
        }
    };

    // ============================================================================
    // DATE PICKER - Wrapper for dateDropper plugin
    // ============================================================================
    const DatePicker = {
        localeEn: {
            days: ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
            daysShort: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            daysMin: ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'],
            months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
            monthsShort: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
            today: 'Today',
            clear: 'Clear',
            dateFormat: 'dd/MM/yyyy',
            timeFormat: 'hh:mm aa',
            firstDay: 0
        },

        init: function() {
            if (typeof AirDatepicker === 'undefined') {
                console.warn('AirDatepicker library not loaded');
                return;
            }
            if (typeof jQuery.fn.dateDropper !== 'function') return;

            if (typeof kv_object === 'undefined') {
                console.warn('kv_object not available for DatePicker');
                return;
            }

            // ✅ Load saved dates from storage if inputs are currently empty
            const $checkin = jQuery(CONFIG.selectors.checkin);
            const $checkout = jQuery(CONFIG.selectors.checkout);
            
            if ($checkin.length && !$checkin.val()) {
                const savedIn = localStorage.getItem(CONFIG.storage.checkin);
                if (savedIn) $checkin.val(savedIn);
            }
            if ($checkout.length && !$checkout.val()) {
                const savedOut = localStorage.getItem(CONFIG.storage.checkout);
                if (savedOut) {
                    $checkout.val(savedOut);
                    $checkout.prop('disabled', false); // Enable checkout input if restored
                }
            }

            this.initCheckIn();
            this.initCheckOut();
            this.initCloseOnDateClick();
        },

        formatMDY: function(dateStr) {
            if (!dateStr || !dateStr.includes('/')) return '';
            const p = dateStr.split('/');
            return p[1] + '/' + p[0] + '/' + p[2];
        },

        initCheckIn: function() {
            const $checkin = jQuery(CONFIG.selectors.checkin);
            if (!$checkin.length) return;

            const savedIn = localStorage.getItem('niseko_checkin');
            const minStart = this.parseDate(kv_object.check_start_date);
            const maxEnd = this.parseDate(kv_object.check_end_date);
            const initialDate = savedIn ? this.parseDate(savedIn) : minStart;

            $checkin.each(function() {
                new AirDatepicker(this, {
                    locale: DatePicker.localeEn,
                    selectedDates: [initialDate],
                    dateFormat: 'dd/MM/yyyy',
                    minDate: minStart,
                    maxDate: maxEnd,
                    autoClose: true,
                    onSelect: function ({date, formattedDate}) {
                        if (!date) return;
                        
                        const actualSelectedDate = formattedDate;
                        jQuery(this).dateDropper({
                            large: 1,
                            largeDefault: 1,
                            minDate: DatePicker.formatMDY(kv_object.check_start_date),
                            maxDate: DatePicker.formatMDY(kv_object.check_end_date),
                            format: 'd/m/Y',
                            onChange: function(res) {
                                const val = ('0' + res.date.d).slice(-2) + '/' + ('0' + res.date.m).slice(-2) + '/' + res.date.Y;
                                jQuery(CONFIG.selectors.checkin).val(val);
                                localStorage.setItem(CONFIG.storage.checkin, val);

                                // Sync all instances
                                jQuery('.js-sb-checkin').val(actualSelectedDate);
                                localStorage.setItem(CONFIG.storage.checkin, actualSelectedDate);
                                let dt = new Date(res.date.Y, res.date.m - 1, res.date.d);
                                dt.setDate(dt.getDate() + (parseInt(kv_object.check_min_days, 10) || 1));
                                
                                let nextMinDate = new Date(date);
                                let minNights = parseInt(kv_object.check_min_days, 10) || 1;
                                nextMinDate.setDate(nextMinDate.getDate() + minNights);
                                const outMin = (dt.getMonth() + 1) + '/' + dt.getDate() + '/' + dt.getFullYear();
                                const outVal = ('0' + dt.getDate()).slice(-2) + '/' + ('0' + (dt.getMonth() + 1)).slice(-2) + '/' + dt.getFullYear();

                                /* Re-init CHECK-OUT */
                                const $checkouts = jQuery('.js-sb-checkout');
                                $checkouts.prop('disabled', false);
                                jQuery(CONFIG.selectors.checkout).prop('disabled', false).val(outVal).dateDropper('destroy').dateDropper({
                                    large: 1,
                                    minDate: outMin,
                                    maxDate: DatePicker.formatMDY(kv_object.check_end_date),
                                    format: 'd/m/Y'
                                });
                                localStorage.setItem(CONFIG.storage.checkout, outVal);

                                $checkouts.each(function() {
                                    const instance = this._airDatepicker;
                                    if (instance) {
                                        instance.update({
                                            minDate: nextMinDate
                                        });
                                        // Automatically select checkout date, capped by maxEnd
                                        let calculatedCheckoutDate = new Date(nextMinDate);
                                        if (calculatedCheckoutDate > maxEnd) {
                                            calculatedCheckoutDate = maxEnd;
                                        }
                                        instance.clear();
                                        instance.selectDate(calculatedCheckoutDate);
                                    } else {
                                        new AirDatepicker(this, {
                                            locale: DatePicker.localeEn,
                                            dateFormat: 'dd/MM/yyyy',
                                            minDate: nextMinDate,
                                            maxDate: DatePicker.parseDate(kv_object.check_end_date),
                                            autoClose: true,
                                            onSelect: function ({formattedDate: dateStrOut}) {
                                                if (!dateStrOut) return;
                                                
                                                jQuery('.js-sb-checkout').val(dateStrOut);
                                                localStorage.setItem('niseko_checkout', dateStrOut);
                                            }
                                        });
                                    }
                                });
                            },
                            onRenderCell: function() {
                                if (kv_object.date_dropper_content) {
                                    const $picker = jQuery('.air-datepicker');
                                    if ($picker.length && !$picker.find('.kv-text').length) {
                                        $picker.prepend(
                                            `<div class="kv-text" style="padding: 10px; font-size: 12px; background: #f9f9f9; text-align: center;">${kv_object.date_dropper_content}</div>`
                                        );
                                    }
                                    if ($picker.length && !$picker.find('.kv-text').length) $picker.prepend(`<div class="kv-text">${kv_object.date_dropper_content}</div>`);
                                }
                            }
                        });
                    }.bind(this)
                } )
            } );
            
        },
        initCheckOut: function() {
            const $checkout = jQuery(CONFIG.selectors.checkout);
            if (!$checkout.length) return;
            
            const savedOut = localStorage.getItem(CONFIG.storage.checkout);
            const initialCheckout = savedOut ? this.parseDate(savedOut) : null;
            const maxEnd = this.parseDate(kv_object.check_end_date);

            // Determine initial minDate for checkout
            let initialCheckoutMinDate = this.parseDate(kv_object.check_start_date);
            const savedIn = localStorage.getItem(CONFIG.storage.checkin);
            if (savedIn) {
                let checkinDate = this.parseDate(savedIn);
                let minNights = parseInt(kv_object.check_min_days, 10) || 1;
                checkinDate.setDate(checkinDate.getDate() + minNights);
                initialCheckoutMinDate = checkinDate;
            }

            $checkout.each(function() {
                new AirDatepicker(this, {
                    locale: DatePicker.localeEn,
                    selectedDates: initialCheckout ? [initialCheckout] : [],
                    dateFormat: 'dd/MM/yyyy', // Ensure this is consistent
                    minDate: initialCheckoutMinDate, // Use the dynamically calculated minDate
                    maxDate: DatePicker.parseDate(kv_object.check_end_date),
                    autoClose: true,
                    onSelect: function ({formattedDate}) {
                        if (!formattedDate) return;
                        jQuery('.js-sb-checkout').val(formattedDate);
                        localStorage.setItem('niseko_checkout', formattedDate);
                    }
                });

                // Initialize legacy dateDropper for this element
                jQuery(this).dateDropper({
                    large: 1,
                    minDate: DatePicker.formatMDY(kv_object.check_start_date),
                    maxDate: DatePicker.formatMDY(kv_object.check_end_date),
                    format: 'd/m/Y',
                    onChange: function(res) {
                        const val = ('0' + res.date.d).slice(-2) + '/' + ('0' + res.date.m).slice(-2) + '/' + res.date.Y;
                        jQuery(CONFIG.selectors.checkout).val(val);
                        localStorage.setItem(CONFIG.storage.checkout, val);
                    }
                });

                if (!jQuery(CONFIG.selectors.checkin).first().val() && !jQuery(this).val()) {
                    jQuery(this).prop('disabled', true);
                }
            }.bind(this));
        },

        parseDate: function(dateStr) {
            if (!dateStr) return undefined;
            // Try DD/MM/YYYY
            const parts = dateStr.split('/');
            if (parts.length === 3) {
                const d = parseInt(parts[0], 10);
                const m = parseInt(parts[1], 10) - 1;
                const y = parseInt(parts[2], 10);
                return new Date(y, m, d);
            }
            return new Date(dateStr);
        },

        onCheckInChange: function(res) {
            this.injectHelperText();

            const actualSelectedDate = ('0' + res.date.d).slice(-2) + '-' + res.date.m_str + '-' + res.date.Y;

            // Sync all check-in inputs and update picker internal states
            jQuery(CONFIG.selectors.checkin).each(function() {
                const $el = jQuery(this);
                if ($el.val() !== actualSelectedDate) {
                    $el.val(actualSelectedDate).trigger('change');
                    if ($el.hasClass('dateDropper')) {
                        $el.dateDropper('set', { value: actualSelectedDate });
                    }
                }
            });

        },

        injectHelperText: function() {
            if (!kv_object.date_dropper_content) return;

            const $picker = jQuery('.air-datepicker');
            if ($picker.length && !$picker.find('.kv-text').length) {
                $picker.prepend(`<div class="kv-text" style="padding: 10px; background: #f0f0f0; text-align: center; font-size: 12px;">${kv_object.date_dropper_content}</div>`);
            }
        },

    };

    // ============================================================================
    // PUBLIC API
    // ============================================================================
    return {
        init: function() {
            // ✅ 1. EXPOSE GLOBALS IMMEDIATELY
            // These must be defined even if search results container is missing (e.g., Homepage)
            // because the header form relies on these functions via HTML attributes.
            this.exposeGlobals();

            localStorage.setItem(CONFIG.storage.hotelSearch, 'false');

            const hasRequiredElements = State.init();
            const isAccommodationPage = window.location.pathname.indexOf('/accommodation/') !== -1;

            // Initialize basic search form events if the form exists (header search)
            if (State.cache.$form.length) {
                Events.init();
            }

            jQuery(document).on('click', '.sb-submit', function() {
                var header_height = jQuery('header').outerHeight() || 0;
                jQuery( 'html, body' ).animate({
                    scrollTop: (jQuery('#accom-search-form').offset().top - header_height) 
                }, 400);
            });

            SearchState.restore();

            if (!hasRequiredElements) {
                // if (isAccommodationPage) {
                //     console.error('AccommodationFilters: Required DOM elements not found on an /accommodation/ page.');
                // }
                return false;
            }

            PriceRange.init();

            // Initialize from current filter state
            this.initializeFromInputs();

            return true;
        },

        exposeGlobals: function() {
            window.doSearch = (el) => {
                localStorage.setItem(CONFIG.storage.hotelSearch, 'true');
                Search.run(1, false, el);
            };

            window.toggleGuests = (e, el) => {
                e.stopPropagation();
                const $card = jQuery(el).closest('.search-card');
                jQuery('.datedropper').removeClass(' picker-focused');
                $card.find('.guests-popover').toggleClass('show');
                $card.find('.sb-guests-desktop').toggleClass('active');
            };

            window.adj = (type, delta, el, e) => {
                if (e) {
                    e.stopPropagation();
                }

                const $card = jQuery(el).closest('.search-card');
                const typeClass = type.trim();
                const selector = '.js-v-' + typeClass;
                
                const $counter = $card.find(selector).first();

                const currentVal = parseInt($counter.text().trim(), 10);

                let val = (isNaN(currentVal) ? 0 : currentVal) + parseInt(delta, 10);
                const minVal = (typeClass === 'adults') ? 1 : 0;
                if (val < minVal) {
                    val = minVal; 
                    
                }
                // Pass the card context so we get the counts for the correct form
                const counts = {
                    adults: parseInt($card.find('.js-v-adults').first().text(), 10) || 2,
                    children: parseInt($card.find('.js-v-children').first().text(), 10) || 0,
                    infants: parseInt($card.find('.js-v-infants').first().text(), 10) || 0
                };

                $counter.text(val);
                
                counts[typeClass] = val;

                SearchState.applyGuests(counts);
                SearchState.save();
            };

            window.onMobileGuestChange = (e, el) => {
                const $target = jQuery(e.target);
                const counts = SearchState.getGuestCounts($target);
                SearchState.applyGuests(counts);
                SearchState.save($card, counts);
            };

            window.resetFilters = (el) => {
                const $card = jQuery(el).closest('.search-card');
                // 1. Clear Local Storage keys used for persistence
                const keys = [
                    CONFIG.storage.resort, CONFIG.storage.checkin, CONFIG.storage.checkout,
                    CONFIG.storage.adults, CONFIG.storage.children, CONFIG.storage.infants,
                    CONFIG.storage.hotelSearch
                ];
                keys.forEach(k => localStorage.removeItem(k));

                // 2. Reset Search Card Inputs for this specific card
                $card.find(CONFIG.selectors.resort).val('').trigger('change');
                $card.find(CONFIG.selectors.checkin).val('');
                $card.find(CONFIG.selectors.checkout).val('').prop('disabled', true);

                // 3. Reset Guest Counters (Desktop & Mobile)
                $card.find('.js-v-adults').text('2');
                $card.find('.js-v-children').text('0');
                $card.find('.js-v-infants').text('0');
                $card.find('.js-m-adults').val(2);
                $card.find('.js-m-children').val(0);
                $card.find('.js-m-infants').val(0);
                $card.find('.js-btn-adults-minus').prop('disabled', true);
                $card.find('.js-btn-children-minus').prop('disabled', true);
                $card.find('.js-btn-infants-minus').prop('disabled', true);

                // Restore placeholder label
                const $display = $card.find(CONFIG.selectors.guestsDisplay);
                $display.text('Guests').addClass('empty');

                // 4. If on Accommodation page, perform a full reset of sidebar filters and results
                if (window.location.pathname.indexOf('/accommodation/') !== -1) {
                    Filters.reset();
                }
            };

            // Close popover on focus out
            // jQuery(".guests-popover").focusout(function() {
            //     jQuery(this).removeClass('open');
            // });

            // Close popover when clicking outside
            jQuery(document).on('click', function(e) {
                if (!jQuery(e.target).closest('.sb-guests-desktop, .guests-popover').length) {
                    jQuery('.guests-popover').removeClass('open');
                    jQuery('.sb-guests-desktop').removeClass('active');
                }
            });
        },

        getURLContext: function() {
            const segments = window.location.pathname.split('/').filter(Boolean);
            const accIndex = segments.indexOf('accommodation');
            
            if (accIndex !== -1 && segments[accIndex + 1]) {
                return segments[accIndex + 1]; // This is the area or resort slug
            }
            return null;
        },

        initializeFromInputs: function() {
            // 1. Handle Resort/Area from URL
            const urlContext = this.getURLContext();
            if (urlContext) {
                const normalizedId = urlContext.replace(/-/g, '_');
                const $targetInput = jQuery(`#${normalizedId}, #${urlContext}, input[value="${urlContext}"], input[value="${urlContext}-accommodation"], input[name="${normalizedId}"]`);
                
                if ($targetInput.length > 0) {
                    $targetInput.prop('checked', true).trigger('change');
                    $targetInput.siblings('label').css("pointer-events", "none"); // Disable the input so user cannot remove it
                    // Lock the filter tab using its actual data-type for accuracy
                    const type = $targetInput.data('type') || $targetInput.attr('name') || urlContext;
                    Filters.lockFilterIfScoped(type);
                }
            }

            // 2. Sync existing checked inputs to Tabs
            jQuery('.ch-item input:checked').each(function() {
                const $input = jQuery(this);
                const type = ($input.attr('name') || '').replace('[]', '');
                const value = $input.val();
                const label = $input.siblings('label').text().trim();
                const isRadio = $input.attr('type') === 'radio';

                let finalLabel = label;
                if (type === 'bedrooms') {
                    finalLabel = parseInt(value) > 1 ? `${value} bedrooms` : `${value} bedroom`;
                }

                Filters.upsertSelected(type, value, finalLabel, isRadio || type === 'resort');
            });

            // 3. Sync Price if changed
            const min = parseInt(State.cache.$priceMin.val());
            const max = parseInt(State.cache.$priceMax.val());
            if (min > CONFIG.defaults.priceMin || max < CONFIG.defaults.priceMax) {
                PriceRange.update();
            }

            // 4. Update Resort-based Areas
            const resortSlug = jQuery(CONFIG.selectors.resort).first().val()?.toLowerCase() || '';
            const resortKey = resortSlug.replace('-accommodation', '');
            if (resortKey) {
                Filters.updateBaseArea(resortKey);
                Filters.lockFilterIfScoped('resort');
            }

            // 5. Trigger initial search on accommodation pages or if dates are set
            const isAccommodationPage = window.location.pathname.indexOf('/accommodation/') !== -1;
            const checkin = jQuery(CONFIG.selectors.checkin).first().val();
            const checkout = jQuery(CONFIG.selectors.checkout).first().val();

            if (isAccommodationPage || (checkin && checkout)) {
                // ✅ API SEARCH: If dates are present, ensure we trigger the API/RoomBoss search automatically
                if (checkin && checkout) {
                    localStorage.setItem(CONFIG.storage.hotelSearch, 'true');
                }

                Filters.updateResetVisibility();
                Search.run(1);
            }
        },

        search: function(page = 1, append = false) {
            
            var header_height = jQuery('header').outerHeight() || 0;
            jQuery( 'html, body' ).animate({
                scrollTop: (jQuery('#accom-search-form').offset().top - header_height) 
            }, 400);
            return Search.run(page, append);
        },

        reset: function() {
            Filters.reset();
        }
    };
})();

// ============================================================================
// AUTO-INIT ON DOM READY
// ============================================================================
jQuery(document).ready(function($) {

    AccommodationFilters.init();
});