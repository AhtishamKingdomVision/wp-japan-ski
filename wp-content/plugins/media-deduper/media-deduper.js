/**
 * Initialize on load
 */
jQuery(document).ready(function() {

	MDD_Help( jQuery );
	MDD_Sharing( jQuery );
	MDD_SmartDeleteWarning( jQuery );

	if ( typeof mdd_indexer_status === 'object' ) {
		MDD_Indexer( jQuery );
	}
	if ( typeof mdd_async_test_key === 'string' ) {
		MDD_Async_Test( jQuery );
	}
});


/**
 * Help screens
 */
function MDD_Help( $ ) {
	$('#shared-help').on('click', function() {
		// toggle help
		if ( $('#contextual-help-link').hasClass('screen-meta-active') ) {
			if ( $('#tab-link-shared').hasClass('active') ) {
				$('#contextual-help-link').trigger('click');
			} else {
				$('#tab-link-shared a').trigger('click');
			}
		} else {
			$('#contextual-help-link').trigger('click');
			$('#tab-link-shared a').trigger('click');
		}
	});
}


/**
 * Sharing tools
 */
function MDD_Sharing( $ ) {

	var sharer = {
		// Initialize the singleton
		init: function() {
			this.buttons = $('.share a');
			if ( this.buttons.length === 0 ) {
				// Abort if no buttons
				return;
			}

			this.buttons.on( 'click', $.proxy( this, 'onClick' ) );
		},

		// Get the url, title, and description of the page
		// Cache the data after the first get
		getPageData: function( e ) {
			if ( !this._data ) {
				this._data = {};
				this._data.title       = 'I\'ve found Media Deduper to be a useful plugin for managing my #WordPress Media Library -- check it out!';
				this._data.url         = 'https://www.mediadeduper.com/';
				this._data.description = 'Media Deduper is a great WordPress plugin to help you find and eliminate duplicate images and attachments from your media library.';
				this._data.target = e;
			}
			return this._data;
		},

		// Event handler for the share buttons
		onClick: function( event ) {
			var service = $(event.target).data('service');
			if ( this[ 'do_' + service ] ) {
				this[ 'do_' + service ]( this.getPageData( event.target ) );
			}
			return false;
		},

		// Handle the Twitter service
		do_twitter: function( data ) {
			var url = 'https://twitter.com/intent/tweet?' + $.param({
				original_referer: document.title,
				text: $(data.target).data('tweet') || data.title,
				url: data.url
			});
			if ( $('.en_social_buttons .en_twitter a').length ) {
				url = $.trim( $('.en_social_buttons .en_twitter a').attr('href') );
			}
			this.popup({
				url: url,
				name: 'twitter_share'
			});
		},

		// Handle the Facebook service
		do_facebook: function( data ) {
			var url = 'https://www.facebook.com/sharer/sharer.php?' + $.param({
				u: data.url
			});
			if ( $('.en_social_buttons .en_facebook a').length ) {
				url = $.trim( $('.en_social_buttons .en_facebook a').attr('href') );
			}
			this.popup({
				url: url,
				name: 'facebook_share'
			});
		},

		// Handle the email service
		do_email: function( data ) {
			var url = 'mailto:?subject=' + data.title + '&body=' + data.description + ': \n' + data.url;
			window.location.href = url.replace('/\+/g',' ');
		},

		// Handle Tumblr
		do_tumblr: function ( data ) {
			var url = 'https://www.tumblr.com/widgets/share/tool?' + $.param({
				canonicalUrl: data.url,
				title: data.title,
				caption: data.caption,
				posttype: 'link'
			});
			this.popup({
				url: url,
				name: 'tumblr_share'
			});
		},

		// Handle the Google+ service
		do_googleplus: function( data ) {
			var url = 'https://plus.google.com/share?' + $.param({
				url: data.url
			});
			this.popup({
				url: url,
				name: 'googleplus_share'
			});
		},

		do_gplus: function ( data ) {
			this.do_googleplus( data );
		},

		// Handle the LinkedIn service
		do_linkedin: function( data ) {
			var url = 'http://www.linkedin.com/shareArticle?' + $.param({
				mini: 'true',
				url: data.url,
				title: data.title,
				summary: data.description
				// source: data.siteName
			});
			this.popup({
				url: url,
				name: 'linkedin_share'
			});
		},

		// Create and open a popup
		popup: function( data ) {
			if ( !data.url ) {
				return;
			}

			$.extend( data, {
				name: '_blank',
				height: 600,
				width: 845,
				menubar: 'no',
				status: 'no',
				toolbar: 'no',
				resizable: 'yes',
				left: Math.floor(screen.width/2 - 845/2),
				top: Math.floor(screen.height/2 - 600/2)
			});

			var i,
				specNames = 'height width menubar status toolbar resizable left top'.split( ' ' ),
				specs = [];

			for( i = 0; i < specNames.length; ++i ) {
				specs.push( specNames[i] + '=' + data[specNames[i]] );
			}
			return window.open( data.url, data.name, specs.join(',') );
		}
	};

	sharer.init();
}


/**
 * Show a warning when the user attempts to smartdelete attachment(s).
 */
function MDD_SmartDeleteWarning( $ ) {
	// Check local storage. We only want to show the smart delete warning once per browser session.
	var warning_shown = window.sessionStorage.getItem( 'mdd_smart_delete_warning_shown' );
	// Analogous to wp-admin/js/media.js, line 184 as of WP 6.5.3.
	$( '#doaction' ).on( 'click', function( event ) {
		$( 'select[name="action"]' ).each( function() {
			var optionValue = $( this ).val();

			if ( 'smartdelete' === optionValue ) {
				// When smart deleting, warn first -- if we haven't already warned before.
				if ( ! warning_shown && ! window.confirm( mdd_l10n.warning_delete ) ) {
					if ( 'sessionStorage' in window ) {
						window.sessionStorage.setItem( 'mdd_smart_delete_warning_shown', '1' );
					}
					warning_shown = '1';
					event.preventDefault();
				}
			}
		});
	});
}


/**
 * Indexer handler
 */
function MDD_Indexer( $ ) {
	var heartbeat, // Timeout ID for a repeated AJAX call that retrieves indexer status data.
		heartbeat_request, // jqXHR for the above AJAX call.
		status = mdd_indexer_status, // Status data as returned by the above AJAX call.

		// Update debug information and the progress bar.
		update_progress = function() {

			// If the indexing task is no longer running...
			if ( 'processing' !== status.state ) {
				// Stop any future heartbeat requests.
				clearInterval( heartbeat );
				// If a heartbeat XHR is currently running, cancel it.
				if ( heartbeat_request ) {
					heartbeat_request.abort();
				}
				// Show results.
				display_results();
			}

			// Calculate user-relevant things based on latest status data.
			var percent = Math.max( 0, Math.min( 1, status.processed / status.total ) ),
				errors = _.where( status.messages, {
					success: false
				});

			// Update the progress bar.
			$('#mdd-meter').css( 'width', (percent * 100) + '%' );
			if ( 'stopped' === status.state ) {
				$('#mdd-bar-percent').html( mdd_l10n.stopped );
			} else {
				$('#mdd-bar-percent').html( (percent * 100).toFixed(1) + '%' );
			}

			// Display error messages, if any.
			if ( errors.length ) {
				$('.error-files').html( '<h4>' + mdd_l10n.index_errors + '</h4><ul></ul>' );
				_.each( errors, function( error ) {
					$('.error-files ul').append( '<li>' + error + '</li>' );
				});
			}
		},

		// Show the results and clean up.
		display_results = function() {

			$('#mdd-stop').hide();
			$('#mdd-manage').css( 'display', 'inline-block' );

			if ( 'stopped' === status.state ) {
				$('#mdd-message').html( mdd_l10n.index_complete.aborted );
			} else if ( status.failed > 0 ) {
				$('#mdd-message').html( mdd_l10n.index_complete.issues.replace('{NUM}', status.failed ) );
			} else {
				$('#mdd-message').html( mdd_l10n.index_complete.perfect );
			}

			$('#mdd-message').show();
		};

	// Initialize progressbar.
	$('#mdd-bar-percent').html( '0%' );
	$('#mdd-bar').css( 'visibility', 'visible' );

	// Update progress bar with initial indexer status (as passed in by wp_add_inline_script).
	update_progress();

	// Check for indexer status updates every second.
	heartbeat = setInterval( function() {

		heartbeat_request = $.get( ajaxurl, {
			action: 'mdd_index_status'
		}, function( response ) {
			status = response;
			update_progress();
		});

	}, 1000 );

	// Add click handler for Stop button.
	$('#mdd-stop').on( 'click', function() {

		// Disable the Stop button and indicate that we're in the process of stopping.
		$('#mdd-stop').prop( 'disabled', true ).html( mdd_l10n.stopping );

		$.post( ajaxurl, {
			action: 'mdd_index_stop',
			nonce: mdd_indexer_stop_nonce
		}, function( response ) {
			status = response;
			update_progress();
		});
	});
}


function MDD_Async_Test( $ ) {

	var $message = $( '#mdd-async-test-message' ).html( mdd_l10n.async_test_running ).show(),
		test_count = 0, // The number of times we've run test().
		test = function() {

			test_count++;

			// Add a '.' to the 'testing' message.
			$message.removeClass( 'notice-error notice-success' ).addClass( 'notice-info' )
				.find( '> p:first-child' ).append( '.' );

			var test_request = $.get( ajaxurl, {
				action: 'mdd_async_test',
				key: mdd_async_test_key
			}, function( data ) {

				// If the mdd_async_test AJAX request is successful, then async processing is working.
				if ( data.success ) {

					// Show a success message.
					$message.removeClass( 'notice-info notice-error' ).addClass( 'notice-success' )
						.html( mdd_l10n.async_test_successful );

					// Hide the message in 10 seconds.
					setTimeout( function() {
						$message.fadeOut();
					}, 10000 );

					return;
				}

				// If test() has run 10 times and the async task still isn't complete, something's up. Show
				// an error message.
				if ( test_count > 10 ) {

					// Show an error message.
					$( '#mdd-async-test-message' )
						.removeClass( 'notice-info notice-success' ).addClass( 'notice-error' )
						.html( mdd_l10n.async_test_failed ).show();

					return;
				}

				// Run test() again.
				setTimeout( test, 200 );
			});
		};

	test();
}
