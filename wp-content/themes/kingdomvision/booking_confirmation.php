<?php
/**
 * Template Name: Booking Confirmation
 */
get_header();

$cart_data = isset($_COOKIE['cart_data']) ? json_decode(stripslashes($_COOKIE['cart_data']), true) : null;
$long_desc_data = isset($_COOKIE['long_desc_data']) ? json_decode(stripslashes($_COOKIE['long_desc_data']), true) : null;
?>

<section class="full-section booking_page">
    <div class="container booking_page">

        <div class="rb-booking-wrapper">

            <!-- LEFT COLUMN -->
            <div class="rb-left-column">

                <div class="booking_dates">
                    <div class="check-in">
                        <h4>CHECK IN</h4>
                        <p></p>
                    </div>
                    <div class="check-out">
                        <h4>CHECK OUT</h4>
                        <p></p>
                    </div>
                </div>

                <!-- ROOM STRUCTURE TEMPLATE -->
                <template id="rb-room-card-template">
                    <div class="rb-room-card room-card" data-room-type-id="" data-idx="">

                        <div class="rb-room-top">

                            <div class="rb-room-img">
                                <img src="" alt="">
                            </div>

                            <div class="rb-room-meta">

                                <!-- HOTEL NAME -->
                                <div class="rb-hotel-name"></div>

                                <!-- ROOM TITLE -->
                                <div class="tit_date">
                                    <h3 class="rb-room-title"></h3>
                                    <button type="button" class="rb-remove"><i class="fa-solid fa-xmark"></i></button>
                                </div>

                                <!-- ICONS -->
                                <div class="rb-icons"></div>

                                <!-- DESCRIPTION -->
                                <div class="rb-room-desc"></div>

                                <!-- BEDDING OPTIONS -->
                                <div class="rb-bedding-wrap"></div>

                            </div>
                        </div>

                        <!-- RATEPLAN -->
                        <div class="rb-rateplans">
                            <div class="rb-rateplan-box">

                                <div class="rb-rateplan-left">
                                    <div class="rb-rateplan-title"></div>

                                    <a href="javascript:void(0)" class="rb-policy-link">
                                        Show payment & cancellation policies
                                    </a>
                                </div>

                                <div class="rb-rateplan-right">
                                    <div class="rb-final-price" data-price=""></div>
                                </div>

                            </div>
                        </div>

                    </div>
                </template>

                <!-- ROOMS CONTAINER -->
                <div id="booking-container"></div>

                <!-- FORM -->
                <div class="booking-confirmation-form">
                    <h3>Enter your details</h3>
                    <?php echo do_shortcode('[gravityform id="3" title="false" description="false" ajax="true"]'); ?>
                </div>
                <div id="flywire_box"></div>

            </div>

            <!-- RIGHT COLUMN -->
            <div class="rb-right-column">

                <div class="rb-summary-box">
                    <h3>Booking Summary</h3>

                    <div class="rb-summary-line">
                        <span>Bookings</span>
                        <span id="summary-bookings">1</span>
                    </div>

                    <div class="rb-summary-line">
                        <span>Guests</span>
                        <span id="summary-guests">–</span>
                    </div>

                    <div class="rb-summary-line">
                        <span>Check in</span>
                        <span id="summary-checkin">–</span>
                    </div>

                    <div class="rb-summary-line">
                        <span>Check out</span>
                        <span id="summary-checkout">–</span>
                    </div>

                    <div class="rb-summary-line">
                        <span>Duration</span>
                        <span id="summary-duration">–</span>
                    </div>

                    <div class="rb-summary-total">
                        <span>Total</span>
                        <span class="room-total-price" data-price="0">¥0</span>
                    </div>

                    <div class="rb-payment-schedule">

                        <h4>Payment Schedule</h4>

                        <!-- Full payment required -->
                        <div id="full-payment-required" style="display:none;">
                            <div class="rb-payment-line">
                                <span>Full payment is required</span>
                                <span id="full-payment-amount">¥0</span>
                            </div>
                        </div>

                        <!-- Deposit / balance -->
                        <div id="deposit-schedule">
                            <div class="rb-payment-line">
                                <span>Deposit due now</span>
                                <span id="deposit-amount">¥0</span>
                            </div>

                            <div class="rb-payment-line">
                                <span>
                                    Remaining balance
                                    <small id="balance-due-date"></small>
                                </span>
                                <span id="balance-amount">¥0</span>
                            </div>
                        </div>

                    </div>

                    <button class="rb-pay-btn">
                        PROCEED TO PAYMENT
                    </button>
                </div>

                <div class="rb-payment-notice">
                    <div class="rb-notice-icon"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <div class="rb-notice-content">
                        <p>
                            Full payment is required by 
                            <strong id="full-payment-deadline">–</strong>
                            to secure your booking.
                        </p>

                        <p class="rb-notice-subtext">
                            Please note that we do not accept third-party credit card payments.
                            The credit card used for payment must belong to one of the staying guests
                            and must be presented at check-in. Additionally, the cardholder’s
                            identity will be verified upon arrival.
                        </p>
                    </div>
                </div>

            </div>

        </div>

    </div>
</section>

<?php get_footer(); ?>

<script src="<?php echo get_template_directory_uri(); ?>/js/booking-manager.js?v=<?php echo filemtime(get_stylesheet_directory() .'/js/booking-manager.js'); ?>"></script>