// Media Deduper JS for all admin pages.
/* global jQuery, ajaxurl */

jQuery( function( $ ) {

	// Find all dismissible notices created by the Media Deduper plugin.
	$( '.mdd-notice.is-dismissible' ).each( function() {

		// Get slug and nonce from data attributes.
		var noticeSlug = $( this ).attr( 'data-mdd-notice-slug' ),
			noticeNonce = $( this ).attr( 'data-mdd-notice-nonce' ),
			dismissNotice = function() {
				$.post( ajaxurl, {
					action: 'mdd_dismiss_notice',
					nonce: noticeNonce,
					notice: noticeSlug
				}, function() {
					// Noop. It doesn't matter _that_ much whether this request went through properly.
				});
			};

		// Bail if no slug or nonce attribute(s) found.
		if ( ! noticeSlug || ! noticeNonce ) {
			return;
		}

		// When the user clicks the 'X' icon to dismiss this notice, send a silent AJAX request to
		// remember that this user dismissed this notice.
		$( this ).on( 'click', '.notice-dismiss', dismissNotice );

		// When the user clicks a 'cancel' button, behave as though the user clicked the 'X' button.
		$( this ).on( 'click', '.button.mdd-notice-dismiss', function( e ) {
			e.preventDefault();
			$( this ).closest( '.mdd-notice' ).find( '.notice-dismiss' ).click();
		});

	});
});
