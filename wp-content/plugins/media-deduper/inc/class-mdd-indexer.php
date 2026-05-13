<?php
/**
 * Media Deduper: async indexer class.
 *
 * @package Media_Deduper
 */

// Load parent classes.
require_once( MDD_INCLUDES_DIR . 'vendor/wp-async-request.php' );
require_once( MDD_INCLUDES_DIR . 'vendor/wp-background-process.php' );

/**
 * Background process class for executing indexing tasks in the background.
 */
class MDD_Indexer extends WP_Background_Process {

	/**
	 * The namespace for options, etc.
	 *
	 * @var string
	 */
	protected $prefix = 'mdd';

	/**
	 * A name for the specific action performed by this class.
	 *
	 * @var string
	 */
	protected $action = 'index';

	/**
	 * The name of the WP option where we'll store indexer status data.
	 *
	 * @var string
	 */
	protected $status_option;

	/**
	 * The name of the WP option whose value will be set to 1 if the user stops the indexer.
	 *
	 * @var string
	 */
	protected $stop_option;

	/**
	 * Is the process stopped? If we check the 'stop' option and its value is 1, this will be set to
	 * TRUE so that we don't have to keep checking the stop option in the database.
	 *
	 * @var bool
	 */
	protected $is_stopped = false;

	/**
	 * Constructor. Adds hooks that watch for changes/access to the indexer status option.
	 */
	public function __construct() {

		parent::__construct();

		// Set option names based on $this->identifier.
		$this->status_option = $this->identifier . '_status';
		$this->stop_option = $this->identifier . '_stop';
	}

	/**
	 * Save queue, initialize stored indexer status data and begin processing.
	 */
	public function index() {

		// Save queue.
		$this->save();

		// Clear old indexer status data, if any.
		delete_option( $this->status_option );

		// Set & store total count.
		$status = $this->get_status();
		$status['total'] = count( $this->data );
		$this->update_status( $status );

		// Initialize the 'stop' option. Note: we set the value to 0 instead of deleting the option
		// because WP caches unset options differently from options that are explicitly set, and
		// depending on what type of cache the site is using, we may need to clear the cache for this
		// option repeatedly.
		$this->update_option( $this->stop_option, 0 );

		return $this->dispatch();
	}

	/**
	 * Check whether the indexer is running.
	 */
	public function is_indexing() {
		return ( ! $this->is_queue_empty() || $this->is_process_running() );
	}

	/**
	 * Get status data.
	 */
	public function get_status() {

		// Get raw data from option.
		$status = $this->get_option( $this->status_option );

		// If option was empty or something other than an array, initialize it.
		if ( ! $status || ! is_array( $status ) ) {
			$status = array();
		}

		// Make sure 'processed', 'failed', and 'total' are integers.
		$int_keys = array( 'processed', 'failed', 'total' );
		foreach ( $int_keys as $key ) {
			if ( ! isset( $status[ $key ] ) ) {
				$status[ $key ] = 0;
			} else {
				$status[ $key ] = (int) $status[ $key ];
			}
		}

		// Make sure 'error_messages' is an array.
		if ( ! isset( $status['error_messages'] ) || ! is_array( $status['error_messages'] ) ) {
			$status['error_messages'] = array();
		}

		// Make sure 'state' is a string.
		if ( ! isset( $status['state'] ) || empty( $status['state'] ) ) {
			$status['state'] = 'processing';
		}

		return $status;
	}

	/**
	 * Save updated status data.
	 *
	 * @param array $status The status data to save.
	 */
	protected function update_status( $status ) {
		return $this->update_option( $this->status_option, $status );
	}

	/**
	 * Stop indexing now.
	 */
	public function stop() {

		// Signal the currently running background process to stop doing stuff.
		$this->update_option( $this->stop_option, 1 );

		// If the background process is working, it'll call unlock_process() soon on its own; but if
		// it's broken somehow, then it won't. There's no real harm in calling it multiple times, so
		// let's call it now just to be safe.
		$this->unlock_process();

		return $this;
	}

	/**
	 * Check whether the user has stopped the currently running index process.
	 *
	 * @return boolean TRUE if stopped, FALSE otherwise.
	 */
	private function is_stopped() {

		// Only check database if the `is_stopped` property is set to FALSE. If it's TRUE, then we don't
		// need to waste another DB query.
		if ( ! $this->is_stopped ) {

			if ( $this->get_option( $this->stop_option ) ) {
				$this->is_stopped = true;
			}
		}

		return $this->is_stopped;
	}

	/**
	 * Get the value of a WP option, making sure that the value is as 'fresh' as possible, i.e. not
	 * cached from an earlier call to get_option() if the value may have changed.
	 *
	 * @param string $option_name The name of the option to get.
	 */
	private function get_option( $option_name ) {

		$this->maybe_clear_option_cache( $option_name );

		return get_option( $option_name );
	}

	/**
	 * If the site is not using an object cache that's shared across requests, then clear the cache
	 * for the given option.
	 *
	 * @param string $option_name The name of the option for which to (maybe) clear the cache.
	 */
	private function maybe_clear_option_cache( $option_name ) {

		// Are we using an object cache that's shared across requests?
		global $_wp_using_ext_object_cache;

		// If not, then we'll need to clear the cache for the 'stopped' option, because another request
		// may have changed it and we need to be sure we're getting an up-to-date value.
		if ( ! $_wp_using_ext_object_cache ) {
			wp_cache_delete( $option_name, 'options' );
		}
	}

	/**
	 * Update a WP option. Disable autoloading so that options are cached individually.
	 *
	 * @param string $option_name  The name of the option to update.
	 * @param mixed  $option_value The new value for the option.
	 */
	private function update_option( $option_name, $option_value ) {
		return update_option( $option_name, $option_value, false ); // Disable autoloading.
	}

	/**
	 * Process an item in the queue.
	 *
	 * @param int $post_id The ID of the post to index.
	 */
	protected function task( $post_id ) {

		// If the user has asked the indexer to stop, skip this item (and all other items that would
		// otherwise have been processed during this request). Once task() has run for all items,
		// handle() will call unlock_process(), which will clear out any other stored data and *really*
		// stop the indexer.
		if ( $this->is_stopped() ) {
			return false;
		}

		// Sanitize $post_id.
		$post_id = absint( $post_id );
		// Get post data.
		$post = get_post( $post_id );

		// Get the stored status data.
		$status = $this->get_status();

		if ( ! $post ) {

			// If this isn't really a post, skip it.
			$status['failed'] += 1;
			$status['error_messages'][] = sprintf(
				// translators: %d: The ID of the missing post.
				__( 'No post found with ID %d', 'media-deduper' ),
				$post_id
			);

		} else {

			// If the post was found, process it.
			// Get the global MDD plugin object.
			global $MediaDeduper;

			if ( 'attachment' === $post->post_type ) {
				// If this is an attachment, calculate its hash.
				$result = $MediaDeduper->calc_media_meta( $post_id );
			}

			if ( is_wp_error( $result ) ) {
				$status['failed'] += 1;
				// Add the latest message.
				$status['error_messages'][] = $result->get_error_message();
			}
		}

		// Bump the processed count.
		$status['processed'] += 1;

		// Store the updated status data.
		$this->update_status( $status );

		// Remove this item from the queue.
		return false;
	}

	/**
	 * Delete process lock and, if indexer is stopped, clear all batches. Called at the end of
	 * WP_Background_Process->handle().
	 */
	protected function unlock_process() {
		parent::unlock_process();

		// If the 'stop' option is set, clear all batches.
		if ( $this->is_stopped() ) {

			while ( ! $this->is_queue_empty() ) {
				$this->cancel_process();
			}

			$status = $this->get_status();
			$status['state'] = 'stopped';
			$this->update_status( $status );

			// Clear the 'stop' option.
			$this->update_option( $this->stop_option, 0 );
		}

		return $this;
	}

	/**
	 * Fires once all items in the queue have been processed.
	 */
	protected function complete() {

		// Call parent class's complete handler to unschedule this process's cron task.
		parent::complete();

		// Update status with 'complete' state, unless current state is 'stopped'.
		$status = $this->get_status();
		if ( 'stopped' !== $status['state'] ) {
			// Clear any flags that indicate a need to reindex.
			update_option( 'mdd_reindex_updated', 0 );
			update_option( 'mdd_reindex_option_changed', 0 );
			$status['state'] = 'complete';
			$this->update_status( $status );
		}
	}
}
