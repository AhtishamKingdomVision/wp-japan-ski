/**
 * Booking Manager - Modular booking system for reusable cart and payment handling
 * Features: Cart management, DOM rendering, payment scheduling, currency formatting
 */

const BookingManager = (function() {
    'use strict';

    // ============================================================================
    // CONFIGURATION OBJECT - Centralize all settings
    // ============================================================================
    const CONFIG = {
        storage: {
            cartKey: 'rb_cart',
            checkinKey: 'niseko_checkin',
            checkoutKey: 'niseko_checkout'
        },
        payment: {
            depositPercentage: 0.25,
            defaultPaymentRequired: true,
            balanceDueDaysBefore: 30
        },
        currency: {
            locale: 'ja-JP',
            code: 'JPY',
            symbol: '¥'
        },
        selectors: {
            bookingContainer: '#booking-container',
            roomCardTemplate: '#rb-room-card-template',
            summary: {
                bookings: '#summary-bookings',
                guests: '#summary-guests',
                checkin: '#summary-checkin',
                checkout: '#summary-checkout',
                duration: '#summary-duration',
                totalPrice: '.room-total-price'
            },
            payment: {
                fullPaymentRequired: '#full-payment-required',
                fullPaymentAmount: '#full-payment-amount',
                depositSchedule: '#deposit-schedule',
                depositAmount: '#deposit-amount',
                balanceAmount: '#balance-amount',
                balanceDueDate: '#balance-due-date',
                fullPaymentDeadline: '#full-payment-deadline'
            },
            dates: {
                wrapper: '.booking_dates',
                checkinDisplay: '.check-in p',
                checkoutDisplay: '.check-out p'
            }
        },
        ajax: {
            action: 'fetch_room_details',
            paramName: 'room_tid'
        }
    };

    // ============================================================================
    // DOM CACHE - Avoid repeated DOM queries
    // ============================================================================
    const DOMCache = {
        elements: {},
        
        init: function() {
            // Cache common elements
            this.elements.bookingContainer = document.querySelector(CONFIG.selectors.bookingContainer);
            this.elements.roomTemplate = document.querySelector(CONFIG.selectors.roomCardTemplate);
            this.elements.summaryBookings = document.querySelector(CONFIG.selectors.summary.bookings);
            this.elements.summaryGuests = document.querySelector(CONFIG.selectors.summary.guests);
            this.elements.summaryCheckin = document.querySelector(CONFIG.selectors.summary.checkin);
            this.elements.summaryCheckout = document.querySelector(CONFIG.selectors.summary.checkout);
            this.elements.summaryDuration = document.querySelector(CONFIG.selectors.summary.duration);
            this.elements.totalPrice = document.querySelector(CONFIG.selectors.summary.totalPrice);
            
            return this.elements.bookingContainer && this.elements.roomTemplate;
        },
        
        get: function(selector) {
            return document.querySelector(selector);
        },
        
        getAll: function(selector) {
            return document.querySelectorAll(selector);
        }
    };

    // ============================================================================
    // STORAGE UTILITIES - Cart & LocalStorage Management
    // ============================================================================
    const Storage = {
        getCart: function() {
            try {
                const data = localStorage.getItem(CONFIG.storage.cartKey);
                return data ? JSON.parse(data) : { items: [] };
            } catch (e) {
                console.error('Failed to parse cart data:', e);
                return { items: [] };
            }
        },

        setCart: function(cart) {
            try {
                localStorage.setItem(CONFIG.storage.cartKey, JSON.stringify(cart));
                return true;
            } catch (e) {
                console.error('Failed to save cart data:', e);
                return false;
            }
        },

        getCheckin: function() {
            return localStorage.getItem(CONFIG.storage.checkinKey) || '';
        },

        getCheckout: function() {
            return localStorage.getItem(CONFIG.storage.checkoutKey) || '';
        },

        removeItemByIndex: function(index) {
            const cart = this.getCart();
            if (cart.items && typeof cart.items[index] !== 'undefined') {
                cart.items.splice(index, 1);
                return this.setCart(cart);
            }
            return false;
        }
    };

    // ============================================================================
    // FORMATTING UTILITIES
    // ============================================================================
    const Formatter = {
        toCurrency: function(amount) {
            return new Intl.NumberFormat(CONFIG.currency.locale, {
                style: 'currency',
                currency: CONFIG.currency.code,
                maximumFractionDigits: 0
            }).format(amount);
        },

        parseDate: function(dateString) {
            // Expected format: "10/04/2026" (DD/MM/YYYY)
            if (!dateString) return null;
            const parts = dateString.split('/');
            if (parts.length !== 3) return null;
            return new Date(parts[2], parts[1] - 1, parts[0]);
        },

        formatDateGB: function(date) {
            return date?.toLocaleDateString('en-GB') || '';
        },

        formatDateUS: function(date, options = {}) {
            const defaultOptions = {
                month: 'short',
                day: 'numeric',
                year: 'numeric'
            };
            return date?.toLocaleDateString('en-US', { ...defaultOptions, ...options }) || '';
        },

        calculateDurationDays: function(durationMs) {
            return Math.floor(durationMs / (1000 * 60 * 60 * 24));
        }
    };

    // ============================================================================
    // AJAX UTILITIES - Centralized AJAX calls with error handling
    // ============================================================================
    const Ajax = {
        fetchRoomDetails: function(roomTypeId) {
            return new Promise((resolve, reject) => {
                if (typeof kv_object === 'undefined' || !kv_object.ajaxurl) {
                    reject(new Error('AJAX URL not configured'));
                    return;
                }

                jQuery.ajax({
                    url: kv_object.ajaxurl,
                    method: 'POST',
                    data: {
                        action: CONFIG.ajax.action,
                        [CONFIG.ajax.paramName]: roomTypeId
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            resolve(response.data);
                        } else {
                            reject(new Error('Invalid response from server'));
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', status, error);
                        reject(new Error(`AJAX request failed: ${status}`));
                    }
                });
            });
        }
    };

    // ============================================================================
    // ROOM RENDERING - Build room cards from template
    // ============================================================================
    const RoomRenderer = {
        createBeddingBlocks: function(bedroomCount, existingBedroomOptions = []) {
            const fragment = document.createDocumentFragment();
            
            for (let i = 1; i <= bedroomCount; i++) {
                const block = document.createElement('div');
                block.className = 'rb-bedding-block';
                
                const label = document.createElement('label');
                label.textContent = `Bedroom ${i} Bedding`;
                
                const select = document.createElement('select');
                select.dataset.bedroom = i;
                
                const defaultOption = document.createElement('option');
                defaultOption.value = '';
                defaultOption.textContent = 'Please Select';
                select.appendChild(defaultOption);
                
                ['1 King', '2 Single'].forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option;
                    opt.textContent = option;
                    select.appendChild(opt);
                });
                
                block.appendChild(label);
                block.appendChild(select);
                fragment.appendChild(block);
            }
            
            return fragment;
        },

        populateRoomCard: function(card, item, roomDetails, index) {
            // Basic info
            card.dataset.roomTypeId = item.room_type_id;
            card.dataset.idx = index;

            // Hotel name
            const hotelNameEl = card.querySelector('.rb-hotel-name');
            if (hotelNameEl) hotelNameEl.textContent = item.property_name || '';

            // Room title
            const roomTitleEl = card.querySelector('.rb-room-title');
            if (roomTitleEl) roomTitleEl.textContent = item.room_name || '';

            // Room image
            const imgEl = card.querySelector('.rb-room-img img');
            if (imgEl && roomDetails?.room_img) {
                imgEl.src = roomDetails.room_img;
                imgEl.alt = item.room_name || '';
            }

            // Room description
            const descEl = card.querySelector('.rb-room-desc');
            if (descEl && roomDetails?.room_desc) {
                descEl.textContent = roomDetails.room_desc;
            }

            // Icons/amenities
            const iconsEl = card.querySelector('.rb-icons');
            if (iconsEl && roomDetails?.room_fields) {
                iconsEl.innerHTML = roomDetails.room_fields;
            }

            // Bedding options
            const beddingWrap = card.querySelector('.rb-bedding-wrap');
            if (beddingWrap) {
                const bedroomCount = item.bedrooms || 1;
                beddingWrap.appendChild(this.createBeddingBlocks(bedroomCount));
            }

            // Rateplan info
            const ratePlanBox = card.querySelector('.rb-rateplan-box');
            if (ratePlanBox) {
                ratePlanBox.dataset.rateplanId = item.rateplan_id;
                ratePlanBox.dataset.rateplanName = item.rateplan_name;
                ratePlanBox.dataset.price = item.price;
            }

            // Rateplan title
            const ratePlanTitleEl = card.querySelector('.rb-rateplan-title');
            if (ratePlanTitleEl) ratePlanTitleEl.textContent = item.rateplan_name || '';

            // Final price
            const priceEl = card.querySelector('.rb-final-price');
            if (priceEl) {
                priceEl.textContent = Formatter.toCurrency(item.price);
                priceEl.dataset.price = item.price;
            }
        },

        renderRooms: function(cart) {
            const container = DOMCache.elements.bookingContainer;
            const template = DOMCache.elements.roomTemplate;

            if (!container || !template) {
                console.error('Required elements not found in DOM');
                return;
            }

            if (!cart?.items?.length) {
                container.innerHTML = '<p>No booking data found. Please select a room first.</p>';
                return;
            }

            const roomPromises = cart.items.map((item, idx) => {
                return Ajax.fetchRoomDetails(item.room_type_id)
                    .then(roomDetails => ({
                        card: template.content.firstElementChild.cloneNode(true),
                        item,
                        roomDetails,
                        idx
                    }))
                    .catch(error => {
                        console.error(`Failed to load room ${item.room_type_id}:`, error);
                        return null;
                    });
            });

            Promise.all(roomPromises).then(results => {
                const cards = results.filter(r => r !== null).map(({ card, item, roomDetails, idx }) => {
                    this.populateRoomCard(card, item, roomDetails, idx);
                    return card;
                });

                container.replaceChildren(...cards);
                Summary.update(cart);
            });
        }
    };

    // ============================================================================
    // SUMMARY UPDATES - Booking summary calculations
    // ============================================================================
    const Summary = {
        updateDates: function() {
            const checkin = Storage.getCheckin();
            const checkout = Storage.getCheckout();

            const datesWrapper = DOMCache.get(CONFIG.selectors.dates.wrapper);
            if (datesWrapper) {
                const checkinDisplay = datesWrapper.querySelector(CONFIG.selectors.dates.checkinDisplay);
                const checkoutDisplay = datesWrapper.querySelector(CONFIG.selectors.dates.checkoutDisplay);
                
                if (checkinDisplay) checkinDisplay.textContent = checkin;
                if (checkoutDisplay) checkoutDisplay.textContent = checkout;
            }

            if (DOMCache.elements.summaryCheckin) 
                DOMCache.elements.summaryCheckin.textContent = checkin;
            if (DOMCache.elements.summaryCheckout) 
                DOMCache.elements.summaryCheckout.textContent = checkout;
        },

        updateMetadata: function(cart) {
            if (DOMCache.elements.summaryBookings) 
                DOMCache.elements.summaryBookings.textContent = cart.items?.length || 0;

            const firstItem = cart.items?.[0];
            if (firstItem) {
                if (DOMCache.elements.summaryGuests) 
                    DOMCache.elements.summaryGuests.textContent = firstItem.guests?.label || '–';
                
                if (DOMCache.elements.summaryDuration) {

                    // const days = Formatter.calculateDurationDays(firstItem.duration);
                    const days = firstItem.dates.nights || 0;
                    console.log( 'dates' );
                    console.log( firstItem.dates );

                    console.log( 'days' );
                    console.log( firstItem.dates.nights );
                    DOMCache.elements.summaryDuration.textContent = days > 0 ? days + ' day' + ( days > 1 ? 's' : '') : '–';
                }
            }
        },

        calculateTotal: function() {
            let total = 0;
            DOMCache.getAll('.rb-final-price').forEach(el => {
                const price = parseFloat(el.dataset.price) || 0;
                total += price;
            });

            if (DOMCache.elements.totalPrice) {
                DOMCache.elements.totalPrice.dataset.price = total;
                DOMCache.elements.totalPrice.textContent = Formatter.toCurrency(total);
            }

            return total;
        },

        update: function(cart) {
            this.updateDates();
            this.updateMetadata(cart);
            this.calculateTotal();
        }
    };

    // ============================================================================
    // PAYMENT SCHEDULE - Handle deposit/full payment logic
    // ============================================================================
    const PaymentSchedule = {
        update: function(cart) {
            const total = Summary.calculateTotal();
            
            if (!total || total <= 0) {
                this.hide();
                return;
            }

            const fullRequiredFlag = !!cart?.items?.[0]?.payment?.full_required;
            const depositPct = fullRequiredFlag ? 1 : CONFIG.payment.depositPercentage;
            
            const deposit = Math.round(total * depositPct);
            const balance = Math.max(0, total - deposit);
            const isFullPayment = deposit >= total || balance === 0;

            if (isFullPayment) {
                this.showFullPayment(total);
            } else {
                this.showDepositSchedule(deposit, balance);
            }

            this.setFullPaymentDeadline();
        },

        showFullPayment: function(total) {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);

            if (fullWrap) fullWrap.style.display = '';
            if (depositWrap) depositWrap.style.display = 'none';

            const amountEl = DOMCache.get(CONFIG.selectors.payment.fullPaymentAmount);
            if (amountEl) amountEl.textContent = Formatter.toCurrency(total);
        },

        showDepositSchedule: function(deposit, balance) {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);

            if (fullWrap) fullWrap.style.display = 'none';
            if (depositWrap) depositWrap.style.display = '';

            const depositEl = DOMCache.get(CONFIG.selectors.payment.depositAmount);
            const balanceEl = DOMCache.get(CONFIG.selectors.payment.balanceAmount);

            if (depositEl) {
                depositEl.textContent = Formatter.toCurrency(deposit);
                depositEl.dataset.price = deposit;
            }

            if (balanceEl) {
                balanceEl.textContent = Formatter.toCurrency(balance);
                balanceEl.dataset.price = balance;
            }

            this.updateBalanceDueDate();
        },

        updateBalanceDueDate: function() {
            const dueDateEl = DOMCache.get(CONFIG.selectors.payment.balanceDueDate);
            if (!dueDateEl) return;

            const checkin = Storage.getCheckin();
            if (!checkin) {
                dueDateEl.textContent = '';
                return;
            }

            const checkinDate = Formatter.parseDate(checkin);
            if (!checkinDate) return;

            checkinDate.setDate(checkinDate.getDate() - CONFIG.payment.balanceDueDaysBefore);
            dueDateEl.textContent = `(Due by ${Formatter.formatDateGB(checkinDate)})`;
        },

        setFullPaymentDeadline: function() {
            const el = DOMCache.get(CONFIG.selectors.payment.fullPaymentDeadline);
            if (!el) return;

            const checkin = Storage.getCheckin();
            if (!checkin) return;

            const checkinDate = Formatter.parseDate(checkin);
            if (!checkinDate) return;

            checkinDate.setDate(checkinDate.getDate() - CONFIG.payment.balanceDueDaysBefore);
            el.textContent = Formatter.formatDateUS(checkinDate);
        },

        hide: function() {
            const fullWrap = DOMCache.get(CONFIG.selectors.payment.fullPaymentRequired);
            const depositWrap = DOMCache.get(CONFIG.selectors.payment.depositSchedule);
            
            if (fullWrap) fullWrap.style.display = 'none';
            if (depositWrap) depositWrap.style.display = 'none';
        }
    };

    // ============================================================================
    // EVENT HANDLERS
    // ============================================================================
    const EventHandlers = {
        init: function() {
            jQuery(document)
                .off('click.rbRemove')
                .on('click.rbRemove', '.rb-remove', this.onRemoveItem.bind(this));
        },

        onRemoveItem: function(e) {
            const $item = jQuery(e.target).closest('.rb-room-card, .rb-summary-card');
            const idx = Number($item.data('idx'));

            if (Storage.removeItemByIndex(idx)) {
                const cart = Storage.getCart();
                RoomRenderer.renderRooms(cart);
                PaymentSchedule.update(cart);
            }
        }
    };

    // ============================================================================
    // PUBLIC API
    // ============================================================================
    return {
        init: function() {
            if (!DOMCache.init()) {
                console.warn('BookingManager: Required DOM elements not found');
                return false;
            }

            const cart = Storage.getCart();
            RoomRenderer.renderRooms(cart);
            Summary.update(cart);
            PaymentSchedule.update(cart);
            EventHandlers.init();

            return true;
        },

        refresh: function() {
            const cart = Storage.getCart();
            Summary.update(cart);
            PaymentSchedule.update(cart);
        },

        removeItem: function(index) {
            return Storage.removeItemByIndex(index);
        },

        getCart: function() {
            return Storage.getCart();
        },

        setConfig: function(overrides) {
            Object.assign(CONFIG, overrides);
        }
    };
})();

// ============================================================================
// INITIALIZATION
// ============================================================================
jQuery(document).ready(function($) {
    if ($('.booking_page').length > 0) {
        BookingManager.init();
    }
});
