<?php
/**
 * The main Media Deduper plugin class.
 *
 * @package Media_Deduper
 */

register_activation_hook( MDD_FILE, array( 'Media_Deduper', 'activate' ) );
register_uninstall_hook( MDD_FILE, array( 'Media_Deduper', 'uninstall' ) );

/**
 * The main Media Deduper plugin class.
 */
class Media_Deduper {

	/**
	 * Plugin version.
	 */
	const VERSION = '1.5.9';

	/**
	 * Special hash value used to mark an attachment if its file can't be found.
	 */
	const NOT_FOUND_HASH = 'not-found';

	/**
	 * Default size value used if an attachment post's file can't be found.
	 */
	const NOT_FOUND_SIZE = 0;

	/**
	 * The ID of the admin screen for this plugin.
	 */
	const ADMIN_SCREEN = 'media_page_media-deduper';

	/**
	 * The number of attachments deleted during a 'smart delete' operation.
	 *
	 * @var int Set/incremented by Media_Deduper::smart_delete_media().
	 */
	protected $smart_deleted_count = 0;

	/**
	 * The number of attachments skipped during a 'smart delete' operation.
	 *
	 * @var int Set/incremented in Media_Deduper::smart_delete_media().
	 */
	protected $smart_skipped_count = 0;

	/**
	 * Compatibility manager instance. Set in constructor.
	 *
	 * @var MDD_Compat_Manager
	 */
	public $compat_manager;

	/**
	 * Async tester instance. Set in constructor.
	 *
	 * @var MDD_Async_Test
	 */
	public $async_test;

	/**
	 * Indexer instance. Set in constructor.
	 *
	 * @var MDD_Indexer
	 */
	public $indexer;

	/**
	 * Capability required for browsing duplicates, Smart Deleting, etc. Set in constructor.
	 *
	 * @var string
	 */
	public $capability;

	/**
	 * Hook suffix for the Manage Duplicates screen. Set in `add_admin_menu()`.
	 *
	 * @var string
	 */
	public $hook;

	/**
	 * When the plugin is activated after being inactive, clear any previously cached transients, and
	 * make sure the `mdd_hash_index` DB index exists.
	 */
	static function activate() {
		static::delete_transients();
		static::db_index( 'add' );
	}

	/**
	 * Main constructor, primarily used for registering hooks.
	 */
	function __construct() {

		// Require the MDD helper functions file.
		require_once MDD_INCLUDES_DIR . 'helper-functions.php';

		// Class for maintaining compatibility with earlier Media Deduper versions' data.
		require_once MDD_INCLUDES_DIR . 'class-mdd-compat-manager.php' ;
		$this->compat_manager = new MDD_Compat_Manager();

		// When the plugin is deactivated, remove the db index.
		register_deactivation_hook( MDD_FILE,     array( $this, 'deactivate' ) );

		// Class for testing async functionality (as used by the indexer).
		require_once MDD_INCLUDES_DIR . 'class-mdd-async-test.php' ;
		$this->async_test = new MDD_Async_Test();

		// Class for processing the index task in the background.
		require_once MDD_INCLUDES_DIR . 'class-mdd-indexer.php' ;
		$this->indexer = new MDD_Indexer();

		// Class for handling outputting the duplicates.
		require_once MDD_INCLUDES_DIR . 'class-mdd-media-list-table.php' ;

		// Use an existing capabilty to check for privileges. manage_options may not be ideal, but gotta use something...
		$this->capability = apply_filters( 'media_deduper_cap', 'manage_options' );

		$this->load_dependencies();

		add_action( 'cshp_settings_page_after_section_mdd', array( $this, 'debug_info_section' ) );

		add_action( 'wp_ajax_mdd_index_status',   array( $this, 'ajax_index_status' ) );
		add_action( 'wp_ajax_mdd_index_stop',     array( $this, 'ajax_index_stop' ) );

		add_action( 'wp_ajax_mdd_async_test',     array( $this, 'ajax_async_test' ) );

		add_action( 'admin_menu',                 array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts',      array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_init',                 array( $this, 'admin_init' ) );
		add_action( 'admin_notices',              array( $this, 'admin_notices' ), 11 );

		// When add_metadata() or update_metadata() is called to set a new or
		// existing attachment's _wp_attached_file value, (re)calculate the
		// attachment's file hash.
		add_action( 'added_post_meta',            array( $this, 'after_add_file_meta' ), 10, 3 );
		add_action( 'update_post_metadata',       array( $this, 'before_update_file_meta' ), 10, 5 );

		// When an attachment is deleted, invalidate the cached list of duplicate
		// IDs, because there may be another attachment that would previously have
		// been considered a duplicate, but is now unique.
		add_action( 'delete_attachment',          array( 'Media_Deduper', 'delete_transients' ) );

		add_filter( 'set-screen-option',          array( $this, 'save_screen_options' ), 10, 3 );

		// Set removable query args (used for displaying messages to the user).
		add_filter( 'removable_query_args',       array( $this, 'removable_query_args' ) );

		// Column handlers.
		add_filter( 'manage_upload_columns',          array( $this, 'media_columns' ) );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'media_sortable_columns' ) );
		add_filter( 'manage_media_custom_column',     array( $this, 'media_custom_column' ), 10, 2 );

		// Row actions (view/edit/delete, etc.) for the duplicates list table.
		add_filter( 'media_row_actions',          array( $this, 'media_row_actions' ), 10, 2 );

		// Allow admin notices to be hidden for the current user via AJAX.
		add_action( 'wp_ajax_mdd_dismiss_notice', array( $this, 'ajax_dismiss_notice' ) );

		// Query filters (for adding sorting options in wp-admin).
		if ( is_admin() ) {
			add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		}
	}

	/**
	 * Load Dependencies Step.
	 */
	private function load_dependencies() {
		require_once( MDD_INCLUDES_DIR . 'class-mdd-admin-notice.php' );
		$this->require_all( MDD_INCLUDES_DIR . 'settings' );

		$this->build_menu_settings();
	}

	/**
	 * Build the admin page.
	 */
	private function build_menu_settings() {
		require_once( MDD_PATH . 'admin/menu-settings.php' );
	}

	/**
	 * Add Debug info Section to the admin page.
	 */
	public function debug_info_section() {
		require_once( MDD_PATH . 'admin/debug-info.php' );
	}

	/**
	 * Enqueue the media js file from core. Also enqueue our own assets.
	 */
	public function enqueue_scripts() {

		$screen = get_current_screen();

		// only enqueue this style/scripts on mdd admin screens.
		if ( is_mdd_admin() ) {
			wp_enqueue_media();
			wp_enqueue_script( 'media-grid' );
			wp_enqueue_script( 'media' );
			wp_enqueue_script( 'media-deduper-js', plugins_url( 'media-deduper.js', MDD_FILE ), array( 'underscore' ), static::VERSION, true );

			// Add localization strings. If this is the indexer tab, additional data
			// will be added later.
			wp_localize_script(
				'media-deduper-js', 'mdd_l10n', array(
					'warning_delete' => __( "Warning: This will modify your files and content!!!!!!! (Lots of exclamation points because it’s seriously that big of a deal.)\n\nWe strongly recommend that you BACK UP YOUR UPLOADS AND DATABASE before performing this operation.\n\nClick 'Cancel' to stop, 'OK' to delete.", 'media-deduper' ),
					'stopping'       => esc_html__( 'Stopping...', 'media-deduper' ),
					'stopped'        => esc_html__( 'Stopped', 'media-deduper' ),
					'index_errors'   => __( 'Errors:', 'media-deduper' ),
					'index_complete' => array(
						'issues' => '<p>'
							. esc_html__( 'Indexing complete;', 'media-deduper' )
							. ' <strong>'
							// translators: %s: The number of files that we failed to index.
							. esc_html( sprintf( __( '%s files could not be indexed.', 'media-deduper' ), '{NUM}' ) )
							. ' <a href=\'' . esc_url( admin_url( 'upload.php?page=media-deduper' ) ) . '\'>'
							. esc_html__( 'Manage duplicates now.', 'media-deduper' )
							. '</a></strong></p>',
						'perfect' => '<p>' . esc_html__( 'Indexing complete;', 'media-deduper' ) . ' <strong>' . esc_html__( 'All media successfully indexed.', 'media-deduper' ) . '</strong></p>',
						'aborted' => '<p>' . esc_html__( 'Indexing aborted; only some items indexed.', 'media-deduper' ) . '</p>',
					),
					'async_test_running' => '<p>' . __( 'Testing asynchronous task execution...', 'media-deduper' ) . '</p>',
					'async_test_successful' => '<p>' . __( 'Asynchronous task execution is working.', 'media-deduper' ) . '</p>',
					'async_test_failed' => '<p>' . __( 'Asynchronous task execution is not functioning correctly, which may cause problems with the Media Deduper indexing process.', 'media-deduper' ) . '</p>',
				)
			);
		}//end if

		// Always enqueue the global admin JS and CSS.
		wp_enqueue_script( 'media-deduper-admin-global-js', plugins_url( 'media-deduper-admin-global.js', MDD_FILE ), array(), static::VERSION, true );
		wp_enqueue_style( 'media-deduper', plugins_url( 'media-deduper.css', MDD_FILE ), array(), static::VERSION );
	}

	/**
	 * Admin init callback.
	 */
	function admin_init() {

		if ( isset( $_GET['mdd_cbur_nonce'] ) && wp_verify_nonce( $_GET['mdd_cbur_nonce'], 'mdd_clear_basic_auth_request' ) ) {
			delete_transient( 'mdd_wp_cron_request' );
		}

		if ( (bool) mdd_basic_auth_check() ) {
			new MDD_Admin_Notice(
				sprintf(
					// translators: %s: Link URL.
					__( 'It looks like your site is password-protected, which may interfere with Media Deduper’s ability to index your media and content. <strong><a href="%1$s">Check our documentation</a></strong> for more info and suggested troubleshooting. <a href="%2$s" class="button button-primary">Check again</a>', 'media-deduper' ),
					'https://support.cornershopcreative.com/support/solutions/articles/43000655565-media-deduper-pro-is-stuck-on-indexing-at-0-',
					wp_nonce_url( admin_url( 'options-general.php?page=mdd' ), 'mdd_clear_basic_auth_request', 'mdd_cbur_nonce' )
				), 'notice notice-warning is-dismissible'
			);
		}

	}

	/**
	 * Remind people they need to do things.
	 */
	public function admin_notices() {

		// If the current user isn't allowed to view the MDD admin screen, bail. None of the messages
		// we'd show here are relevant to users who can't rebuild the index, etc.
		if ( ! current_user_can( $this->capability ) ) {
			return;
		}

		$screen = get_current_screen();
		$html = '';

		// Show a message about MDD Pro, but only on the Dashboard, Media Library, Upload New Media,
		// and Edit Attachment screens, and only to users who could actually install it and who haven't
		// already seen and dismissed it.
		if (
			( current_user_can( 'upload_plugins' ) || current_user_can( 'install_plugins' ) )
			&& in_array( get_current_screen()->id, array( 'dashboard', 'upload', 'media', 'attachment' ), true )
			&& ! $this->is_notice_dismissed( 'pro-10-upsell' )
		) {
			$nonce = wp_create_nonce( 'mdd_dismiss_pro-10-upsell' );
			$html .= '<div class="notice mdd-notice notice-info is-dismissible" data-mdd-notice-slug="pro-10-upsell" data-mdd-notice-nonce="' . esc_attr( $nonce ) . '"><p>';
			$html .= __( 'Thanks for using Media Deduper! Upgrade to Media Deduper Pro for improved indexing, Smart Delete integration with popular plugins like WooCommerce, and more.', 'media-deduper' );
			$html .= '</p><p><a class="button button-primary button-cshp" href="https://www.mediadeduper.com/" target="_blank" rel="noopener noreferrer">' . _x( 'More info', 'upsell button text', 'media-deduper' ) . '</a> <a href="#" class="button mdd-notice-dismiss">' . __( 'No thanks', 'media-deduper' ) . '</a></p></div>';
		}

		if ( ! get_option( 'mdd-activated', false ) && $this->get_count( 'indexed' ) < $this->get_count() ) {

			// On initial plugin activation, point to the indexing page.
			add_option( 'mdd-activated', true, '', 'no' );
			$html .= '<div class="error notice is-dismissible"><p>';
			$html .= sprintf(
				// translators: %s: Link URL.
				__( 'In order to manage duplicate media you must first <strong><a href="%s">generate the media index</a></strong>.', 'media-deduper' ),
				admin_url( 'upload.php?page=media-deduper&tab=index' )
			);
			$html .= '</p></div>';

		} elseif ( 'upload' === $screen->base && $this->get_count( 'indexed' ) < $this->get_count() ) {

			// Otherwise, complain about incomplete indexing if necessary.
			$html .= '<div class="error notice is-dismissible"><p>';
			$html .= sprintf(
				// translators: %s: Link URL.
				__( 'Media duplication index is not comprehensive, please <strong><a href="%s">update the index now</a></strong>.', 'media-deduper' ),
				admin_url( 'upload.php?page=media-deduper&tab=index' )
			);
			$html .= '</p></div>';

		} elseif ( static::ADMIN_SCREEN === $screen->base ) {

			if ( isset( $_GET['smartdeleted'] ) ) {

				// The 'smartdelete' action has been performed. $_GET['smartdelete'] is
				// expected to be a comma-separated pair of values reflecting the number
				// of attachments deleted and the number of attachments that weren't
				// deleted (which happens if all other copies of an image have already
				// been deleted).
				list( $deleted, $skipped ) = array_map( 'absint', explode( ',', $_GET['smartdeleted'] ) );
				// Only output a message if at least one attachment was either deleted
				// or skipped.
				if ( $deleted || $skipped ) {
					$html .= '<div class="updated notice is-dismissible"><p>';
					// translators: %1$d: Number of items deleted. %2$d: Number of items skipped.
					$html .= sprintf( __( 'Deleted %1$d items and skipped %2$d items.', 'media-deduper' ), $deleted, $skipped );
					$html .= '</p></div>';
				}
				// Remove the 'smartdeleted' query arg from the REQUEST_URI, since it's
				// served its purpose now and we don't want it weaseling its way into
				// redirect URLs or the like.
				$_SERVER['REQUEST_URI'] = remove_query_arg( 'smartdeleted', $_SERVER['REQUEST_URI'] );

			} elseif ( isset( $_GET['deleted'] ) ) {

				// The 'delete' action has been performed. $_GET['deleted'] is expected
				// to reflect the number of attachments deleted.
				// Only output a message if at least one attachment was deleted.
				$deleted = absint( $_GET['deleted'] );
				if ( $deleted ) {
					// Show a simpler message if only one file was deleted (based on
					// wp-admin/upload.php).
					if ( 1 === $deleted ) {
						$message = __( 'Media file permanently deleted.', 'media-deduper' );
					} else {
						/* translators: %s: number of media files */
						$message = _n( '%s media file permanently deleted.', '%s media files permanently deleted.', $deleted, 'media-deduper' );
					}
					$html .= '<div class="updated notice is-dismissible"><p>';
					$html .= sprintf( $message, number_format_i18n( $deleted ) );
					$html .= '</p></div>';
				}
				// Remove the 'deleted' query arg from REQUEST_URI.
				$_SERVER['REQUEST_URI'] = remove_query_arg( 'deleted', $_SERVER['REQUEST_URI'] );

			} //end if
		} //end if

		echo $html;
		// WPCS: XSS ok.
	}

	/**
	 * Check whether the current user has seen and dismissed a given admin notice.
	 *
	 * @param string $notice A slug identifying the admin notice.
	 */
	function is_notice_dismissed( $notice ) {

		// Get array of all notices dismissed by this user.
		$dismissed_notices = get_user_meta( get_current_user_id(), 'mdd_dismissed_notices', true );

		// If $notice is in the array, then yes, it's been hidden.
		if ( is_array( $dismissed_notices ) && in_array( $notice, $dismissed_notices, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Permanently hide an admin notice for the current user.
	 *
	 * @param string $notice A slug identifying the admin notice.
	 */
	function dismiss_notice( $notice ) {

		// Get all currently hidden notices.
		$dismissed_notices = get_user_meta( get_current_user_id(), 'mdd_dismissed_notices', true );

		if ( ! is_array( $dismissed_notices ) ) {
			// If user meta value wasn't an array, initialize it to an array containing only $notice.
			$dismissed_notices = array( $notice );
		} elseif ( ! in_array( $notice, $dismissed_notices, true ) ) {
			// If user meta value didn't contain $notice, add it.
			$dismissed_notices[] = $notice;
		} else {
			// If user meta value was already an array containing $notice, then we don't need to do
			// anything. Return true to indicate that the notice is hidden, though.
			return true;
		}

		// Update user meta.
		return update_user_meta( get_current_user_id(), 'mdd_dismissed_notices', $dismissed_notices );
	}

	/**
	 * Permanently hide an admin notice for the current user.
	 */
	function ajax_dismiss_notice() {

		// Get notice slug from request, sanitize, and check nonce.
		$notice_slug = sanitize_title( $_REQUEST['notice'] );
		check_ajax_referer( 'mdd_dismiss_' . $notice_slug, 'nonce' );

		// Update dismissed notices user meta value, and return a JSON status message.
		if ( $this->dismiss_notice( $notice_slug ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'Notice dismissed.', 'media-deduper' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Error dismissing notice.', 'media-deduper' ),
				)
			);
		}
	}

	/**
	 * Adds/removes DB index on meta_value to facilitate performance in finding dupes.
	 *
	 * @param string $task 'add' to add the index, any other value to remove it.
	 */
	static function db_index( $task = 'add' ) {

		global $wpdb;
		if ( 'add' === $task ) {
			$sql = "CREATE INDEX `mdd_hash_index` ON $wpdb->postmeta ( meta_value(32) );";
		} else {
			$sql = "DROP INDEX `mdd_hash_index` ON $wpdb->postmeta;";
		}

		$wpdb->query( $sql );

	}

	/**
	 * On deactivation, get rid of our index.
	 */
	public function deactivate() {

		global $wpdb;

		// Kill our index.
		static::db_index( 'remove' );
	}

	/**
	 * On uninstall, get rid of ALL junk.
	 */
	static function uninstall() {
		global $wpdb;

		// Kill our mdd_hashes and mdd_sizes. It's annoying to re-generate the
		// index but we don't want to pollute the DB.
		$wpdb->delete(
			$wpdb->postmeta, array(
				'meta_key' => 'mdd_hash',
			)
		);
		$wpdb->delete(
			$wpdb->postmeta, array(
				'meta_key' => 'mdd_size',
			)
		);

		// Kill our mysql table index.
		static::db_index( 'remove' );

		// Remove the option indicating activation.
		delete_option( 'mdd-activated' );
	}

	/**
	 * When add_post_meta() is called to set an attachment post's initial
	 * _wp_attached_file meta value, calculate the attachment's hash.
	 *
	 * @param int    $meta_id    The ID of the meta value in the postmeta table.
	 *                           Passed in by update_post_meta(), ignored here.
	 * @param int    $post_id    The ID of the post whose meta value has changed.
	 * @param string $meta_key   The meta key whose value has changed.
	 */
	function after_add_file_meta( $meta_id, $post_id, $meta_key ) {

		// If the meta key that was updated isn't _wp_attached_file, bail.
		if ( '_wp_attached_file' !== $meta_key ) {
			return;
		}

		// If this isn't an attachment post, bail.
		if ( 'attachment' !== get_post_field( 'post_type', $post_id, 'raw' ) ) {
			return;
		}

		// Calculate and save the file hash.
		$this->calc_media_meta( $post_id );
	}

	/**
	 * When update_post_meta() is called to set an attachment post's
	 * _wp_attached_file meta value, recalculate the attachment's hash.
	 *
	 * Note: the Enable Media Replace plugin uses a direct db query to set
	 * _wp_attached_file before calling update_attached_file(), so when a file is
	 * changed using EMR, the "new" meta value passed here may be the same as the
	 * old one, and updated_post_meta won't fire because the values are the same.
	 * That's why this function hooks into update_post_metadata, which _always_
	 * fires, instead of updated_post_meta.
	 *
	 * If the new value for the meta key is the same as the old value, this
	 * function will recalculate the attachment hash immediately; if the new value
	 * is different from the old one, this function will attach another hook that
	 * will recalculate the hash _after_ the new meta value has been saved.
	 *
	 * @uses Media_Deduper::after_update_file_meta()
	 *
	 * @param null|bool $check      Whether to allow updating metadata. Passed in
	 *                              by the update_post_metadata hook, but ignored
	 *                              here -- we don't want to change whether meta
	 *                              is saved, we just want to know if it changes.
	 * @param int       $post_id    Object ID.
	 * @param string    $meta_key   Meta key.
	 * @param mixed     $meta_value Meta value. Must be serializable if non-scalar.
	 * @param mixed     $prev_value Optional. If specified, only update existing
	 *                              metadata entries with the specified value.
	 *                              Otherwise, update all entries.
	 */
	function before_update_file_meta( $check, $post_id, $meta_key, $meta_value, $prev_value ) {

		// If the meta key that was updated isn't _wp_attached_file, bail.
		if ( '_wp_attached_file' !== $meta_key ) {
			return $check;
		}

		// If this isn't an attachment post, bail.
		if ( 'attachment' !== get_post_field( 'post_type', $post_id, 'raw' ) ) {
			return $check;
		}

		// Compare existing value to new value. See update_metadata() in
		// wp-includes/meta.php. If the old value and the new value are the same,
		// then the updated_post_meta action won't fire. The Enable Media Replace
		// plugin might have changed the actual contents of the file, though, even
		// if the filename/path hasn't changed, so now is our chance to update the
		// image hash and size.
		if ( empty( $prev_value ) ) {
			$old_value = get_post_meta( $post_id, $meta_key );
			if ( 1 === count( $old_value ) ) {
				if ( $old_value[0] === $meta_value ) {
					// Recalculate and save the file hash.
					$this->calc_media_meta( $post_id );
					// Leave $check as is to avoid affecting whether or not meta is saved.
					return $check;
				}
			}
		}

		// If the old and new meta values are NOT identical, wait until the metadata
		// is actually saved, and _then_ recalculate the hash.
		add_action( 'updated_post_meta', array( $this, 'after_update_file_meta' ), 10, 3 );

		// Leave $check as is to avoid affecting whether or not meta is saved.
		return $check;
	}

	/**
	 * Calculate the hash for a new attachment post or one whose attached file has
	 * changed.
	 *
	 * @param int    $meta_id    The ID of the meta value in the postmeta table.
	 *                           Passed in by update_post_meta(), ignored here.
	 * @param int    $post_id    The ID of the post whose meta value has changed.
	 * @param string $meta_key   The meta key whose value has changed.
	 */
	function after_update_file_meta( $meta_id, $post_id, $meta_key ) {

		// If the meta key that was updated isn't _wp_attached_file, bail.
		if ( '_wp_attached_file' !== $meta_key ) {
			return;
		}

		// If this isn't an attachment post, bail.
		if ( 'attachment' !== get_post_field( 'post_type', $post_id, 'raw' ) ) {
			return;
		}

		// Calculate the hash for this attachment.
		$this->calc_media_meta( $post_id );

		// Unhook this function from update_post_meta, so it doesn't keep firing for
		// future metadata changes. $this->before_update_meta() will add this
		// function back as needed.
		remove_action( 'updated_post_meta', array( $this, 'after_update_file_meta' ), 10 );
	}

	/**
	 * Calculate the hash for a just-uploaded file.
	 *
	 * @param int $post_id The ID of the attachment post to calculate meta for.
	 * @return array {
	 *     @type bool   $success Whether the hash & size could be calculated correctly.
	 *     @type string $message Human-readable info about what happened.
	 * }
	 */
	function calc_media_meta( $post_id ) {
		$mediafile = get_attached_file( $post_id );

		// If the file doesn't exist, save special "not found" hash + size.
		if ( false === $mediafile || ! file_exists( $mediafile ) ) {
			$this->save_media_meta( $post_id, self::NOT_FOUND_HASH );
			$this->save_media_meta( $post_id, self::NOT_FOUND_SIZE, 'mdd_size' );

			// Delete cached counts.
			static::delete_transients();

			// Return an error message for logging.
			return new WP_Error(
				'mdd_file_not_found',
				sprintf(
					// translators: %s: Attachment title (links to the Edit Attachment screen).
					__( 'Attachment file for %s could not be found.', 'media-deduper' ),
					'<a href="' . esc_url( get_edit_post_link( $post_id ) ) . '">' . esc_html( get_the_title( $post_id ) ) . '</a>'
				)
			);
		}

		// Calculate and save hash and size.
		$hash = $this->calculate_hash( $mediafile );
		$size = $this->calculate_size( $mediafile );
		$this->save_media_meta( $post_id, $hash );
		$this->save_media_meta( $post_id, $size, 'mdd_size' );

		// Delete transients, most importantly the attachment count (but duplicate
		// IDs and shared file IDs may have been affected too, if this post was
		// copied meta-value-for-meta-value from another post).
		static::delete_transients();

		// If hash and size were saved, return TRUE to indicate success.
		return true;
	}

	/**
	 * Get indexer status data.
	 */
	function ajax_index_status() {
		$status = $this->indexer->get_status();
		wp_send_json( $status );
	}

	/**
	 * Stop the indexer and return the indexer status.
	 */
	public function ajax_index_stop() {
		check_admin_referer( 'mdd_index_stop', 'nonce' );
		$status = $this->indexer->stop()->get_status();
		wp_send_json( $status );
	}

	/**
	 * Check whether an async test task has been executed.
	 */
	public function ajax_async_test() {
		if ( $this->async_test->check( sanitize_key( $_GET['key'] ) ) ) {
			wp_send_json_success();
		} else {
			wp_send_json_error();
		}
	}

	/**
	 * Calculate the size for a given file.
	 *
	 * @param string $file The path to the file for which to calculate size.
	 */
	private function calculate_size( $file ) {
		return filesize( $file );
	}

	/**
	 * Calculate the MD5 hash for a given file.
	 *
	 * @param string $file The path to the file for which to to calculate a hash.
	 */
	private function calculate_hash( $file ) {
		return md5_file( $file );
	}

	/**
	 * Save metadata for an attachment.
	 *
	 * @param int    $post_id  The ID of the post for which to save metadata.
	 * @param any    $value    The meta value to save.
	 * @param string $meta_key The meta key under which to save the value.
	 */
	private function save_media_meta( $post_id, $value, $meta_key = 'mdd_hash' ) {
		return update_post_meta( $post_id, $meta_key, $value );
	}

	/**
	 * Return either the total # of attachments, or the # of indexed attachments.
	 *
	 * @param string $type The type of count to return. Use 'all' to count all
	 *                     attachments, or 'indexed' to count only attachments
	 *                     whose hash and size have already been calculated.
	 *                     Default 'all'.
	 */
	private function get_count( $type = 'all' ) {

		global $wpdb;

		switch ( $type ) {
			case 'all':
				$sql = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment';";
				break;

			case 'indexed':
			default:
				$sql = "SELECT COUNT(*) FROM $wpdb->posts p
					LEFT JOIN $wpdb->postmeta ph
						ON p.ID = ph.post_id AND ph.meta_key = 'mdd_hash'
					LEFT JOIN $wpdb->postmeta ps
						ON p.ID = ps.post_id AND ps.meta_key = 'mdd_size'
					WHERE (
						p.post_type = 'attachment'
						AND ph.meta_id IS NOT NULL
						AND ps.meta_id IS NOT NULL
					)
					";
		}

		$result = get_transient( 'mdd_count_' . $type );

		if ( false === $result ) {
			$result = $wpdb->get_var( $sql );
			set_transient( 'mdd_count_' . $type, $result, HOUR_IN_SECONDS );
		}
		return $result;

	}

	/**
	 * Add to admin menu.
	 */
	function add_admin_menu() {
		$this->hook = add_media_page( __( 'Manage Duplicates', 'media-deduper' ), __( 'Manage Duplicates', 'media-deduper' ), $this->capability, 'media-deduper', array( $this, 'admin_screen' ) );

		add_action( 'load-' . $this->hook, array( $this, 'screen_tabs' ) );
	}

	/**
	 * Implements screen options.
	 */
	function screen_tabs() {

		$option = 'per_page';
		$args = array(
			'label'   => 'Items',
			'default' => get_option( 'posts_per_page', 20 ),
			'option'  => 'mdd_per_page',
		);
		add_screen_option( $option, $args );

		$screen = get_current_screen();

		$screen->add_help_tab(
			array(
				'id'      => 'overview',
				'title'   => __( 'Overview' ),
				'content' =>
				'<p>' . __( 'Media Deduper was built to help you find and eliminate duplicate images and attachments from your WordPress media library.' )
					. '</p><p>' . __( 'Before Media Deduper can identify duplicate assets, it first must build an index of all the files in your media library.' )
					. '</p><p>' . __( 'Once its index is complete, Media Deduper will also prevent users from uploading duplicates of files already present in your media library.' )
					. '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'indexing',
				'title'   => __( 'Indexing' ),
				'content' =>
				'<p>' . __( 'Media Deduper needs to generate an index of your media files in order to determine which files match. It only looks at the files themselves, not any data in WordPress (such as title, caption or comments). Once that’s done, however, Media Deduper automatically adds new uploads to its index, so you shouldn’t have to generate the index again.' )
					. '</p><p>' . __( 'As a part of the indexing process, Media Deduper also stores information about each file’s size so duplicates can be sorted by disk space used, allow you to most efficiently perform cleanup.' )
					. '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'deletion',
				'title'   => __( 'Deletion' ),
				'content' =>
				'<p>' . __( 'Once Media Deduper has indexed your files and found duplicates, you can easily delete them in one of two ways:' )
					. '</p><p>' . __( 'Option 1: Smart Delete. This option preserves images that are assigned as Featured Images on posts. Smart Delete reassigns a single instance of the image to the post, and only deletes orphaned copies of that image. Smart Delete will refuse to delete the last remaining copy of an item: even if you select all copies of an image, and none of them are used anywhere on the site, Smart Delete will leave one copy of the image in your library. In this sense, Smart Delete is safer than Delete Permanently. <em><strong>Please note:</strong></em> Although this option preserves Featured Images, it does <em>not</em> preserve media used in galleries, other shortcodes, custom fields, the bodies of posts, or other meta data, and it is not reversible. Please be careful.' )
					. '</p><p>' . __( 'Option 2: Delete Permanently. This option <em>permanently</em> deletes whichever files you select. This can be <em>very dangerous</em> as it cannot be undone, and you may inadvertently delete all versions of a file, regardless of how they are being used on the site.' )
					. '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'shared',
				'title'   => __( 'Shared Files' ),
				'content' =>
				'<p>' . __( 'In a typical WordPress installation, each different Media "post" relates to a separate file uploaded to the filesystem. However, some plugins facilitate copying media posts in a way that produces multiple posts all referencing a single file.' )
					. '</p><p>' . __( 'Media Deduper considers such posts to be "duplicates" because they share the same image data. However, in most cases you would not want to actually delete any of these posts because deleting any one of them would remove the media file they all share.' )
					. '</p><p>' . __( 'Because this can lead to unintentional data loss, Media Deduper prefers to suppress showing duplicates that share a file. However, it is possible to show these media items if you wish to review or delete them. <strong>Be extremely cautious</strong> when working with duplicates that share files as unintentional data loss can easily occur.' )
					. '</p>',
			)
		);

		$screen->add_help_tab(
			array(
				'id'      => 'about',
				'title'   => __( 'About' ),
				'content' =>
				'<p>' . __( 'Media Deduper was built by Cornershop Creative, on the web at <a href="https://cornershopcreative.com">https://cornershopcreative.com</a>' )
					. '</p><p>' . __( 'Need support? Got a feature idea? <a href="https://wordpress.org/support/plugin/media-deduper">Contact us on the wordpress.org plugin support page</a>. Thanks!' )
					. '</p>',
			)
		);

		$this->get_duplicate_ids();

		// We use $wp_query (the main query) since Media_List_Table does and we extend that.
		global $wp_query;
		$query_parameters = array_merge(
			// Defaults that $_GET can override.
			array(
				'orderby'        => array(
					'mdd_size'  => 'desc',
					'post_date' => 'desc',
				),
			),
			// Query args (most of the time these will only affect sort order).
			$_GET,
			// Hard settings that should override anything in $_GET.
			array(
				'post__in'       => $this->duplicate_ids,
				'post_type'      => 'attachment',
				'post_status'    => get_post_stati(),
				'posts_per_page' => get_user_option( 'mdd_per_page' ),
			)
		);

		// If suppressing shared files (the default), do that.
		if ( ! isset( $_GET['show_shared'] ) || 1 !== absint( $_GET['show_shared'] ) ) {
			$this->get_shared_filename_ids();
			$query_parameters['post__in'] = array_diff( $this->duplicate_ids, $this->shared_filename_ids );
			if ( ! count( $query_parameters['post__in'] ) ) {
				// We do this otherwise WP_Query's post__in gets an empty array and
				// returns all posts.
				$query_parameters['post__in'] = array( '0' );
			}
		}

		$wp_query = new WP_Query( $query_parameters );

		$this->list_table = new MDD_Media_List_Table(
			array(
				// Even though this is really the 'media_page_media-deduper' screen,
				// we want to show the columns that would normally be shown on the
				// 'upload' screen, including taxonomy terms or any other columns
				// that other plugins might be adding.
				'screen' => 'upload',
			)
		);

		// Handle bulk actions, if any.
		$this->handle_bulk_actions();

		// If we got here via a form submission, but there was no bulk action to apply, then the user
		// probably just changed the 'Hide duplicates that share files' setting. Redirect to a slightly
		// cleaner URL: remove the _wp_http_referer and _wpnonce args. wp-admin/upload.php does this.
		if ( ! empty( $_GET['_wp_http_referer'] ) && ! empty( $_GET['filter_action'] ) ) {
			$redirect_url = add_query_arg(
				array(
					'show_shared' => absint( $_GET['show_shared'] ),
					's'           => $_GET['s'],
				), admin_url( 'upload.php?page=media-deduper' )
			);
			wp_redirect( $redirect_url );
			exit;
		}
	}

	/**
	 * Allow the `mdd_per_page` screen option to be saved.
	 *
	 * @param bool|int $status Screen option value. Default false to skip.
	 * @param string   $option The option name.
	 * @param int      $value  The number of rows to use.
	 */
	function save_screen_options( $status, $option, $value ) {
		if ( 'mdd_per_page' === $option ) {
			return $value;
		}
	}

	/**
	 * The main admin screen!
	 */
	function admin_screen() {

		// Get the currently active tab.
		$active_tab = ( isset( $_GET['tab'] ) ? $_GET['tab'] : 'duplicates' );
		if ( ! in_array( $active_tab, array( 'duplicates', 'index', 'license' ), true ) ) {
			$active_tab = 'duplicates';
		}

		?>
		<div id="mdd-async-test-message" class="notice notice-info" style="display:none"></div>
		<div id="mdd-message" class="updated fade" style="display:none"></div>
		<div class="wrap deduper">
			<h1><?php esc_html_e( 'Media Deduper', 'media-deduper' ); ?></h1>
			<aside class="mdd-column-2">
				<div class="mdd-box">
					<h2>Like Media Deduper?</h2>
					<ul>
						<li class="share"><a href="#" data-service="facebook">Share it on Facebook »</a></li>
						<li class="share"><a href="#" data-service="twitter" data-tweet="Media Deduper Pro helped me clean up my #WordPress files! Finally a plugin that will save you time and clean up your website:">Tweet it »</a></li>
						<li><a href="https://wordpress.org/support/plugin/media-deduper/reviews/#new-post" target="_blank" rel="noopener noreferrer">Review it on WordPress.org »</a></li>
					</ul>
					<p>
						<strong>Guard against broken images!</strong><br />
						<a href="https://www.mediadeduper.com/" target="_blank" rel="noopener noreferrer">Upgrade to Media Deduper Pro</a> and make sure you’re not only removing duplicates but also replacing them through even more field options. Features include integration with plugins like WooCommerce and Yoast SEO, extended Smart Delete functionality for post and page content, and more.
					</p>
					<p><a class="button button-primary button-cshp" href="https://www.mediadeduper.com/" target="blank" rel="noopener noreferrer">Check out Media Deduper Pro</a></p>
				</div>
			</aside>
			<div class="mdd-column-1">
				<div class="nav-tab-wrapper">
					<a href="<?php echo esc_url( admin_url( 'upload.php?page=media-deduper&tab=duplicates' ) ); ?>" class="nav-tab<?php echo ( 'duplicates' === $active_tab ? ' nav-tab-active' : '' ); ?>">Duplicates</a>
					<a href="<?php echo esc_url( admin_url( 'upload.php?page=media-deduper&tab=index' ) ); ?>" class="nav-tab<?php echo ( 'index' === $active_tab ? ' nav-tab-active' : '' ); ?>">Index</a>
				</div>

		<?php
		if ( 'index' === $active_tab ) :
		?>

				<h2><?php esc_html_e( 'Index of Duplicate Media', 'media-deduper' ); ?></h2>

				<?php
				// Display the index screen.
				$this->show_index_screen();
				?>

			</div><!-- .mdd-column-1 -->

		<?php elseif ( 'license' === $active_tab ) : ?>

		<?php else : ?>

				<p><?php esc_html_e( 'Use this tool to identify duplicate media files in your site. It only looks at the files themselves, not any data in WordPress (such as title, caption or comments).', 'media-deduper' ); ?></p>
				<p><?php esc_html_e( 'In order to identify duplicate files, an index of all media must first be generated.', 'media-deduper' ); ?></p>

				<?php $this->show_index_button(); ?>

			</div><!-- .mdd-column-1 -->

			<!-- the posts table -->
			<h2 style="clear:both;"><?php esc_html_e( 'Duplicate Media Files', 'media-deduper' ); ?></h2>
			<form id="posts-filter" method="get">
				<?php
				// Set the `page` query param when processing actions. This ensures that
				// $this->handle_bulk_actions() will run, which will process the bulk action and redirect
				// the user. Otherwise, it would fall to wp-admin/upload.php to process bulk actions, and
				// upload.php doesn't know how to smartdelete.
				?>
				<input type="hidden" name="page" value="media-deduper">
				<div class="wp-filter mdd-filter">
					<div class="view-switch">
						<select name="show_shared">
							<option value="0" <?php selected( ! isset( $_GET['show_shared'] ) || ( '0' === $_GET['show_shared'] ) ); ?>><?php esc_html_e( 'Hide duplicates that share files', 'media-deduper' ); ?></option>
							<option value="1" <?php selected( isset( $_GET['show_shared'] ) && ( '1' === $_GET['show_shared'] ) ); ?>><?php esc_html_e( 'Show duplicates that share files', 'media-deduper' ); ?></option>
						</select>
						<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e( 'Apply', 'media-deduper' ); ?>">
					</div>
					<a href="javascript:void(0);" id="shared-help"><?php esc_html_e( 'What\'s this?', 'media-deduper' ); ?></a>
					<div class="search-form">
						<label for="media-search-input" class="screen-reader-text"><?php esc_html_e( 'Search Duplicate Media' ); ?></label>
						<input type="search" placeholder="<?php esc_attr_e( 'Search duplicate items...' ); ?>" id="media-search-input" class="search" name="s" value="<?php _admin_search_query(); ?>">
					</div>
				</div>
				<?php

				$this->list_table->prepare_items();
				$this->list_table->display();

				// This stuff makes the 'Attach' dialog work.
				wp_nonce_field( 'find-posts', '_ajax_nonce', false );
				?>
				<input type="hidden" id="find-posts-input" name="ps" value="" /><div id="ajax-response"></div>
				<?php find_posts_div(); ?>
				</form>
		<?php endif; ?>

		</div><!-- .wrap -->
		<?php
	}


	/**
	 * Output the indexing progress page.
	 */
	private function show_index_screen() {

		if ( isset( $_GET['async_test'] ) ) {
			// Spawn an async task to check whether async processing is working.
			$async_test_key = uniqid();
			$this->async_test->run( $async_test_key );
			wp_add_inline_script( 'media-deduper-js', 'window.mdd_async_test_key = ' . json_encode( $async_test_key ) . ';', 'before' );
		}

		// If the indexer isn't running...
		if ( ! $this->indexer->is_indexing() ) {

			// If the user didn't get here by clicking the Index Media button, then show the button,
			// explanatory text, and any errors from the last time the index was run.
			if ( empty( $_POST['mdd-build-index'] ) && empty( $_POST['mdd-build-index-clean'] ) ) {
				$this->show_index_button( true );
				return;
			}

			// Form nonce check.
			check_admin_referer( 'media-deduper-index' );

			// Check whether _all_ attachments should be indexed ($clean === true), or only un-indexed ones.
			$clean = ( ! empty( $_POST['mdd-build-index-clean'] ) );

			// Get unhashed attachment IDs.
			$attachments = $this->get_attachment_ids( ! $clean );
			// Get total number of items to process (note: in the Pro version, this includes the count of
			// unindexed posts).
			$total_count = count( $attachments );

			if ( $total_count < 1 ) {

				if ( $clean ) {
					echo '<p>' . esc_html__( 'There are no indexable attachments on this site! As you begin adding content, Media Deduper will index new attachments in the background.', 'media-deduper' ) . '</p>';
				} else {
					echo '<p>' . esc_html__( 'There are no unindexed items. Would you like to completely rebuild the index?', 'media-deduper' ) . '</p>';
					$this->show_index_button( true, false );
				}

				return;
			}

			// Add unhashed attachment IDs.
			foreach ( $attachments as $attachment_id ) {
				$this->indexer->push_to_queue( $attachment_id );
			}

			// Kick off the indexer process.
			$this->indexer->index();
		}//end if

		?>
		<p><?php esc_html_e( 'Please be patient while the index is generated. This can take a while if your server is slow or if you have many large media files. Once the indexing process is underway, it will continue on its own – feel free to close this window or navigate away from this page. You can return later to check on the indexing process.', 'media-deduper' ); ?></p>

		<noscript><p><em><?php esc_html_e( 'You must enable Javascript in order to proceed!', 'media-deduper' ); ?></em></p></noscript>

		<div id="mdd-bar" style="visibility: hidden;">
			<div id="mdd-meter"></div>
			<div id="mdd-bar-percent"></div>
		</div>

		<p>
			<button class="button hide-if-no-js" id="mdd-stop">
				<?php esc_attr_e( 'Stop', 'media-deduper' ); ?>
			</button>
			<a class="button" id="mdd-manage" href="<?php echo esc_url( admin_url( 'upload.php?page=media-deduper' ) ); ?>"><?php esc_attr_e( 'Manage Duplicates Now', 'media-deduper' ); ?></a>
		</p>

		<div class="error-files">
			<ul></ul>
		</div>

		<?php

		wp_add_inline_script( 'media-deduper-js', 'window.mdd_indexer_status = ' . json_encode( $this->indexer->get_status() ) . ';', 'before' );
		wp_add_inline_script( 'media-deduper-js', 'window.mdd_indexer_stop_nonce = ' . json_encode( wp_create_nonce( 'mdd_index_stop' ) ) . ';', 'before' );
	}

	/**
	 * Output the (Re-)Index Media button and preceding text.
	 *
	 * @param bool $always    Set to TRUE if the button should be displayed even if there are no
	 *                        un-indexed attachments. Default FALSE.
	 * @param bool $show_info Set to FALSE if only the button should be shown. Otherwise, contextual
	 *                        information will be added before and/or after the button, detailing the
	 *                        number of unindexed items, etc.
	 */
	private function show_index_button( $always = false, $show_info = true ) {

		$index_incomplete = ( $this->get_count( 'indexed' ) < $this->get_count() );

		if ( $index_incomplete ) {

			if ( $show_info ) {
				// If the index isn't comprehensive, show how many attachments have been indexed.
				?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							// translators: %1$d: Number of attachment posts indexed. %2$d: Total number of attachment posts.
							__( 'Looks like %1$d of %2$d media items have been indexed.', 'media-deduper' ),
							$this->get_count( 'indexed' ),
							$this->get_count()
						)
					);
					?>
					<strong><?php esc_html_e( 'Please index all media now.', 'media-deduper' ); ?></strong>
				</p>
				<?php
			}

			$button_text = __( 'Index Media', 'media-deduper' );
			$button_name = 'mdd-build-index';

		} else {

			if ( $show_info ) {
				// If the index IS comprehensive, say so.
				?>
				<p><?php esc_html_e( 'All media have been indexed.', 'media-deduper' ); ?></p>
				<?php
			}

			$button_text = __( 'Re-Index Media', 'media-deduper' );
			$button_name = 'mdd-build-index-clean';

		}//end if

		// Show the button, if ether the index isn't comprehensive or we were asked to always show it.
		if ( $always || $index_incomplete ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'upload.php?page=media-deduper&tab=index' ) ); ?>">
				<?php wp_nonce_field( 'media-deduper-index' ); ?>
				<p><input type="submit" class="button hide-if-no-js" name="<?php echo esc_attr( $button_name ); ?>" value="<?php echo esc_attr( $button_text ); ?>" /></p>
				<noscript><p><em><?php esc_html_e( 'You must enable Javascript in order to proceed!', 'media-deduper' ); ?></em></p></noscript>
			</form><br>
			<?php
		}

		if ( $always && $show_info ) {

			// Get and display errors from the last indexer run, if any.
			$last_index_status = $this->indexer->get_status();
			$errors = $last_index_status['error_messages'];

			if ( ! empty( $errors ) ) {

				if ( 'stopped' === $last_index_status['state'] ) {
					// translators: %d: The number of errors.
					$error_heading = __( 'The last indexer process, which was stopped manually, resulted in %d errors:', 'media-deduper' );
				} else {
					// translators: %d: The number of errors.
					$error_heading = __( 'The last indexer process resulted in %d errors:', 'media-deduper' );
				}

				echo '<h4>' . esc_html(
					sprintf(
						$error_heading,
						count( $errors )
					)
				) . '</h4>';

				?>
				<div class="error-files">
					<ul>
						<?php foreach ( $errors as $error ) { ?>
							<li>
							<?php
							echo wp_kses(
								$error,
								array(
									'a' => array(
										'href' => array(),
										'title' => array(),
									),
								)
							);
							?>
							</li>
						<?php } ?>
					</ul>
				</div>
				<?php
			}//end if
		}//end if
	}

	/**
	 * Retrieves a list of attachment posts that haven't yet had their file md5 hashes computed.
	 *
	 * @param bool $unhashed_only TRUE to return the IDs of attachments whose hash has not been
	 *                            calculated. FALSE to return all attachment IDs.
	 */
	private function get_attachment_ids( $unhashed_only = true ) {

		global $wpdb;

		$sql = "
			SELECT ID FROM $wpdb->posts p
			WHERE p.post_type = 'attachment'
			";

		if ( $unhashed_only ) {
			$sql .= "
				AND ( NOT EXISTS (
					SELECT * FROM $wpdb->postmeta pm
					WHERE pm.meta_key = 'mdd_hash'
					AND pm.post_id = p.ID
				) OR NOT EXISTS (
					SELECT * FROM $wpdb->postmeta pm2
					WHERE pm2.meta_key = 'mdd_size'
					AND pm2.post_id = p.ID
				) )
				";
		}

		$sql .= ';';

		return $wpdb->get_col( $sql );

	}

	/**
	 * Retrieves an array of post ids that have duplicate hashes.
	 */
	private function get_duplicate_ids() {

		global $wpdb;

		$duplicate_ids = get_transient( 'mdd_duplicate_ids' );

		if ( false === $duplicate_ids ) {
			$sql = "SELECT DISTINCT p.post_id
				FROM $wpdb->postmeta AS p
				JOIN (
					SELECT count(*) AS dupe_count, meta_value
					FROM $wpdb->postmeta
					WHERE meta_key = 'mdd_hash'
					AND meta_value != '" . self::NOT_FOUND_HASH . "'
					GROUP BY meta_value
					HAVING dupe_count > 1
				) AS p2
				ON p.meta_value = p2.meta_value;";

			$duplicate_ids = $wpdb->get_col( $sql );
			// If we don't do this, WP_Query's post__in gets an empty array and
			// returns all posts.
			if ( ! count( $duplicate_ids ) ) {
				$duplicate_ids = array( '0' );
			}
			set_transient( 'mdd_duplicate_ids', $duplicate_ids, HOUR_IN_SECONDS );
		}//end if

		$this->duplicate_ids = $duplicate_ids;
		return $this->duplicate_ids;

	}

	/**
	 * Retrieves an array of post ids that have duplicate filenames/paths.
	 */
	private function get_shared_filename_ids() {

		global $wpdb;

		$sharedfile_ids = get_transient( 'mdd_sharedfile_ids' );

		if ( false === $sharedfile_ids ) {
			$sql = "SELECT DISTINCT p.post_id
				FROM $wpdb->postmeta AS p
				JOIN (
					SELECT count(*) AS sharedfile_count, meta_value
					FROM $wpdb->postmeta
					WHERE meta_key = '_wp_attached_file'
					GROUP BY meta_value
					HAVING sharedfile_count > 1
				) AS p2
				ON p.meta_value = p2.meta_value;";

			$sharedfile_ids = $wpdb->get_col( $sql );
			// If we don't do this, WP_Query's post__in gets an empty array and
			// returns all posts.
			if ( ! count( $sharedfile_ids ) ) {
				$sharedfile_ids = array( '0' );
			}
			set_transient( 'mdd_sharedfile_ids', $sharedfile_ids, HOUR_IN_SECONDS );
		}

		$this->shared_filename_ids = $sharedfile_ids;
		return $this->shared_filename_ids;

	}

	/**
	 * Clears out cached IDs and counts.
	 */
	static function delete_transients() {
		delete_transient( 'mdd_duplicate_ids' ); // Attachments that share hashes.
		delete_transient( 'mdd_sharedfile_ids' ); // Attachments that share files.
		delete_transient( 'mdd_count_all' ); // All attachments, period.
		delete_transient( 'mdd_count_indexed' ); // All attachments with known hashes and sizes.
	}

	/**
	 * Process a bulk action performed on the media table.
	 */
	public function handle_bulk_actions() {

		// Get the current action.
		$doaction = $this->list_table->current_action();

		// Handle stock WP bulk actions (attach/detach).
		if ( 'detach' === $doaction ) {
			wp_media_attach_action( $_REQUEST['parent_post_id'], 'detach' );
		} elseif ( 'attach' === $doaction ) {
			wp_media_attach_action( $_REQUEST['found_post_id'] );
		} elseif ( 'smartdelete' !== $doaction && 'delete' !== $doaction ) {
			// Ignore any bulk actions other than attach, detach, delete, or smartdelete.
			return;
		}

		// Check nonce field. The type of request will determine which nonce field needs to be checked.
		if ( isset( $_REQUEST['post'] ) ) {

			// If the 'post' request variable is present, then this is a request to delete a single item.
			// Sanitize the post ID to operate on.
			$post_id = intval( $_REQUEST['post'] );

			// Check nonce field. This field is automatically generated for each "Delete Permanently" link
			// by WP_Media_List_Table.
			check_admin_referer( 'delete-post_' . $post_id );

			// Store the post ID in an array, so we can use the same foreach() loop we'd use if we were
			// performing a bulk action.
			$post_ids = array( $post_id );

		} else {

			// If the 'post' query var is absent, then this must be a bulk action request.
			// Check nonce field. This field is automatically generated for the Bulk Actions menu by
			// WP_Media_List_Table.
			check_admin_referer( 'bulk-media' );

			// Sanitize the list of post IDs to operate on.
			$post_ids = array();
			if ( isset( $_REQUEST['media'] ) ) {
				$post_ids = $_REQUEST['media'];
			}
			$post_ids = array_map( 'intval', (array) $post_ids );
		}//end if

		// Redirect to the Media Deduper page by default.
		$redirect_url = add_query_arg(
			array(
				'page' => 'media-deduper',
			), 'upload.php'
		);

		switch ( $doaction ) {
			case 'smartdelete':
				// Loop over the array of record IDs and delete them.
				foreach ( $post_ids as $id ) {
					self::smart_delete_media( $id );
				}

				// Add query args that will cause Media_Deduper::admin_notices() to
				// show messages.
				$redirect_url = add_query_arg(
					array(
						'page' => 'media-deduper',
						'smartdeleted' => $this->smart_deleted_count . ',' . $this->smart_skipped_count,
					), $redirect_url
				);

				break;

			case 'delete':
				$deleted_count = 0;

				// Handle normal delete action.
				foreach ( $post_ids as $id ) {
					// Delete the attachment. See the note in smart_delete_media() about the reasons behind
					// the second argument ($force_delete).
					if ( wp_delete_post( $id, true ) ) {
						$deleted_count++;
					}
				}

				// Add query args that will cause Media_Deduper::admin_notices() to
				// show messages.
				$redirect_url = add_query_arg(
					array(
						'page' => 'media-deduper',
						'deleted' => $deleted_count,
					), $redirect_url
				);

				break;

			default:
				// Ignore any other actions.
				break;
		} //end switch

		// Redirect to the redirect URL set above.
		wp_redirect( $redirect_url );
		exit;
	}

	/**
	 * Declare the 'smartdeleted' query arg to be 'removable'.
	 *
	 * This causes users who visit upload.php?page=media-deduper&smartdeleted=1,0
	 * (which is where you're sent after 'smart-deleting' images) to only see
	 * upload.phpp?page=media-deduper in their URL bar.
	 *
	 * @param array $args An array of removable query args.
	 */
	public function removable_query_args( $args ) {
		$args[] = 'smartdeleted';
		return $args;
	}

	/**
	 * 'Smart-delete' an attachment post: delete only duplicate attachments, and replace references to
	 * deleted attachments.
	 *
	 * If there are no duplicates of the given attachment, this function will do nothing. If there
	 * are duplicates, then this function will check for references to the attachment and replace them
	 * with references to an older duplicate, and then delete the attachment.
	 *
	 * @param int $id The ID of the post to (maybe) delete.
	 */
	protected function smart_delete_media( $id ) {

		// Check whether there are other copies of this image.
		$this_post_hash = get_post_meta( $id, 'mdd_hash', true );
		if ( ! $this_post_hash ) {
			die( 'Something has gone horribly awry' );
		}
		$duplicate_media = new WP_Query(
			array(
				'ignore_sticky_posts' => true,
				'post__not_in'        => array( $id ),
				'post_type'           => 'attachment',
				'post_status'         => 'any',
				'orderby'             => 'ID',
				'order'               => 'ASC',
				'meta_key'            => 'mdd_hash',
				'meta_value'          => $this_post_hash,
			)
		);

		// If no other media with this hash was found, don't delete this media item. This way, even if
		// the user selects both images in a pair of duplicates, one will always be preserved.
		if ( ! $duplicate_media->have_posts() ) {
			$this->smart_skipped_count++;
			return;
		}

		// If this attachment is used as the featured image for any other posts, update those posts to
		// instead use the duplicate with the lowest post ID.
		$featured_on_posts = new WP_Query( array(
			'posts_per_page'      => 99999, // Because truly killing pagination isn't allowed on VIP.
			'ignore_sticky_posts' => true,
			'post_type'           => 'any',
			'meta_key'            => '_thumbnail_id',
			'meta_value'          => $id,
		));
		if ( $featured_on_posts->have_posts() ) {
			$preserved_id = $duplicate_media->posts[0]->ID;
			// Update each of the posts our current media image is featured on to use the $preserved_id.
			foreach ( $featured_on_posts->posts as $post ) {
				update_post_meta( $post->ID, '_thumbnail_id', $preserved_id, $id );
			}
		}

		// Finally, delete this attachment. The second argument here ($force_delete) causes the
		// attachment to be deleted immediately, regardless of the user's MEDIA_TRASH setting. The
		// duplicates list table displays trashed and non-trashed attachments together, and allowing the
		// normal MEDIA_TRASH behavior would make bulk actions affect trashed and non-trashed
		// attachments differently: non-trashed attachments would be trashed rather than deleted, and
		// would continue to appear in the list table after being "deleted." in order to fully remove
		// them, the user would have to "delete" them a second time.
		if ( wp_delete_attachment( $id, true ) ) {
			$this->smart_deleted_count++;
		}
	}

	/**
	 * Filters the media columns to add another one for filesize.
	 *
	 * @param array $posts_columns An array of column machine-readable names =>
	 *                             human-readable titles.
	 */
	public function media_columns( $posts_columns ) {
		$posts_columns['mdd_size'] = _x( 'Size', 'column name', 'media-deduper' );
		return $posts_columns;
	}

	/**
	 * Filters the media columns to make the Size column sortable.
	 *
	 * @param array $sortable_columns An array of sortable column machine readable
	 *                                names => human-readable titles.
	 */
	public function media_sortable_columns( $sortable_columns ) {
		$sortable_columns['mdd_size'] = array( 'mdd_size', true );
		return $sortable_columns;
	}

	/**
	 * Handles the file size column output.
	 *
	 * @param string $column_name The machine-readable name of the column to
	 *                            display content for.
	 * @param int    $post_id     The ID of the post to display content for.
	 */
	public function media_custom_column( $column_name, $post_id ) {
		if ( 'mdd_size' === $column_name ) {
			$filesize = get_post_meta( $post_id, 'mdd_size', true );
			if ( ! $filesize ) {
				echo esc_html__( 'Unknown', 'media-deduper' );
			} else {
				echo esc_html( size_format( $filesize ) );
			}
		}
	}

	/**
	 * Change row action links in the Media Deduper list table. This is necessary because the normal
	 * row actions for a WP_Media_List_Table are different depending on the $is_trash property of the
	 * list table, which MDD_Media_List_Table ignores. These action links and their URLs are based on
	 * a selection of those in WP_Media_List_Table::_get_row_actions().
	 *
	 * @param array   $actions Row action links.
	 * @param WP_Post $post    The post being displayed.
	 */
	public function media_row_actions( $actions, $post ) {

		// Don't alter actions if we're not on the Media Deduper page.
		$screen = get_current_screen();
		if ( static::ADMIN_SCREEN !== $screen->base ) {
			return $actions;
		}

		// Initialize actions array (it's a little easier to reconstruct it entirely than to selectively
		// remove and insert actions).
		$actions = array();

		// Get the title or 'Auto Draft' text as used in the default row actions by
		// WP_Media_List_Table::handle_row_actions().
		$att_title = _draft_or_post_title();

		// If the user can edit this post, and it's not in the trash, show an edit link.
		if ( current_user_can( 'edit_post', $post->ID ) && 'trash' !== $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: attachment title */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $att_title ) ),
				__( 'Edit' )
			);
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {

			// If this post is trashed, show a Restore from Trash link.
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" class="submitdelete aria-button-if-js" aria-label="%s">%s</a>',
					wp_nonce_url( "post.php?action=untrash&amp;post=$post->ID", 'untrash-post_' . $post->ID ),
					/* translators: %s: attachment title */
					esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $att_title ) ),
					__( 'Restore' )
				);
			}

			// If the user can delete this post, show a delete link.
			$actions['delete'] = sprintf(
				'<a href="%s" class="submitdelete aria-button-if-js" onclick="return showNotice.warn();" aria-label="%s">%s</a>',
				// Note: instead of linking to post.php here, like the standard WP media list table does, we
				// link to the MDD page so we can use our custom delete action handler, which ignores the
				// MEDIA_TRASH constant and always deletes the attachment in question.
				wp_nonce_url( "upload.php?page=media-deduper&amp;action=delete&amp;post=$post->ID", 'delete-post_' . $post->ID ),
				/* translators: %s: attachment title */
				esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $att_title ) ),
				__( 'Delete Permanently' )
			);
		}//end if

		// If this post isn't trashed, link to the single attachment template.
		if ( 'trash' !== $post->post_status ) {
			$actions['view'] = sprintf(
				'<a href="%s" aria-label="%s" rel="bookmark">%s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: attachment title */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $att_title ) ),
				__( 'View' )
			);
		}

		return $actions;
	}

	/**
	 * Add meta query clauses corresponding to custom 'orderby' values.
	 *
	 * @param WP_Query $query A WP_Query object for which to alter query vars.
	 */
	public function pre_get_posts( $query ) {

		// Get the orderby query var.
		$orderby = $query->get( 'orderby' );

		// If there's only one orderby option, cast it as an array.
		if ( ! is_array( $orderby ) ) {
			$orderby = array(
				$orderby => $query->get( 'order' ),
			);
		}

		if ( in_array( 'mdd_size', array_keys( $orderby ), true ) ) {

			// Get the current meta query.
			$meta_query = $query->get( 'meta_query' );
			if ( ! $meta_query ) {
				$meta_query = array();
			}

			// Add a clause to sort by.
			$meta_query['mdd_size'] = array(
				'key'     => 'mdd_size',
				'type'    => 'NUMERIC',
				'compare' => 'EXISTS',
			);

			// Set the new meta query.
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Include other functions
	 */
	protected function require_all( $dir, $depth = 0 ) {
		// strip slashes from end of string
		$dir = rtrim( $dir, '/\\' );
		// require all php files
		$scan = glob( $dir . DIRECTORY_SEPARATOR . '*' );
		foreach ( $scan as $path ) {
			if ( preg_match( '/\.php$|\.inc$/', $path ) ) {
				require_once $path;
			} elseif ( is_dir( $path ) ) {
				$this->require_all( $path, $depth + 1 );
			}
		}
	}
}


/**
 * Start up this plugin.
 */
function media_deduper_init() {
	global $MediaDeduper;
	$MediaDeduper = new Media_Deduper();
}
add_action( 'init', 'media_deduper_init' );
