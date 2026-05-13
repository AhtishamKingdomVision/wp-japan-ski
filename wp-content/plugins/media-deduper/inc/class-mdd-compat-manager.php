<?php
/**
 * Media Deduper: compatibility manager class.
 *
 * @package Media_Deduper
 */

/**
 * Class for handling backwards compatibility (changing option formats, etc).
 */
class MDD_Compat_Manager {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->delete_mdd_version_site_option();
		$this->check_index_version();
		add_action( 'admin_init', array( $this, 'prepare_admin_notices' ) );
	}

	/**
	 * Change 'mdd_version' from a multisite 'site option' to a regular option.
	 *
	 * Old versions of MDD stored the plugin version as a site option, which made sense in theory
	 * (there's only one version running on a multisite network at any given time), but in practice,
	 * that meant that after an update, ONE site in the network would be prompted to reindex, and
	 * then the site option would be changed. So let's get rid of the old site option. We'll use a
	 * regular option instead.
	 *
	 * Note that on non-multisite installs, get_site_option() is just a wrapper for get_option(), so
	 * this is only relevant on multisite.
	 */
	public function delete_mdd_version_site_option() {

		// Bail if not multisite, because 'site options' are the same as regular options.
		if ( ! is_multisite() ) {
			return;
		}

		if ( get_site_option( 'mdd_version' ) ) {
			delete_site_option( 'mdd_version' );
		}
	}

	/**
	 * If this plugin has been used on this site before, and the most recently active version used a
	 * different index format or indexed different data, save an option that will prompt the user to
	 * regenerate the index.
	 */
	public function check_index_version() {

		// The most recent version of the plugin that changed what gets indexed or how.
		$index_version = '1.4.2';

		// Get the last active plugin version.
		$last_active_version = get_option( 'mdd_version' );

		// If there's no last active plugin version stored, the plugin may have just been installed for
		// the first time, or it may have just been upgraded from version 1.0.5 or earlier, which stored
		// the version in a site option. Let's check for previously indexed posts/attachments.
		if ( ! $last_active_version ) {

			// Check whether there are any post meta rows with the keys that Deduper's indexer uses.
			global $wpdb;
			$has_meta = $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
					WHERE meta_key IN (
						'mdd_hash',
						'mdd_size',
						'_mdd_referenced_by',
						'_mdd_referenced_by_count',
						'_mdd_references'
					)
					LIMIT 1
					"
			);

			// If there aren't any, then there's no out-of-date indexer data. Either the plugin has just
			// been installed, or the prior version of the plugin never indexed anything, or its data was
			// deleted fully. Regardless, we don't need to *re*index.
			if ( ! $has_meta ) {

				// Save the current plugin version, so we don't have to go through this again.
				update_option( 'mdd_version', Media_Deduper::VERSION );

				// We're done.
				return;
			}
		}

		// If there's no stored version number, or if the stored version number is lower than the index
		// version, delete transients and save a temporary option that will trigger an admin message
		// indicating that the user should update the index.
		if ( version_compare( $index_version, $last_active_version ) > 0 ) {

			// Delete transients, since old duplicate counts, etc. may no longer be relevant.
			Media_Deduper::delete_transients();

			// Save an option indicating that we've just updated and we need to reindex. When the indexer
			// is run, it will reset this option.
			update_option( 'mdd_reindex_updated', 1 );

			// Save the current plugin version.
			update_option( 'mdd_version', Media_Deduper::VERSION );
		}
	}

	/**
	 * Queue up admin messages on load.
	 */
	public function prepare_admin_notices() {
		if ( get_option( 'mdd_reindex_updated' ) ) {
			new MDD_Admin_Notice(
				sprintf(
					// translators: %s: Link URL.
					__( 'Thanks for updating Media Deduper. Due to recent enhancements you’ll need to <a href="%s">regenerate the index</a>.', 'media-deduper' ),
					admin_url( 'upload.php?page=media-deduper&tab=index' )
				), 'notice notice-warning'
			);
		}
	}
}
