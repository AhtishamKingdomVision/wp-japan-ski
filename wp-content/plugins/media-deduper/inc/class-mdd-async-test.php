<?php
/**
 * Media Deduper: async task test class.
 *
 * @package Media_Deduper
 */

// Load parent classes.
require_once( MDD_INCLUDES_DIR . 'vendor/wp-async-request.php' );

/**
 * Background process class for executing indexing tasks in the background.
 */
class MDD_Async_Test extends WP_Async_Request {

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
	protected $action = 'async_test';

	/**
	 * Make an async request.
	 *
	 * @param string $key A unique key that will identify this particular test task. When this task is
	 *                    executed, $key will be passed along to handle() as $_POST['key'].
	 *
	 * @return array|WP_Error See WP_HTTP::request().
	 */
	public function run( $key ) {

		// Add $key to POST data that will be sent by dispatch().
		$this->data = array(
			'key' => sanitize_key( $key ),
		);

		return parent::dispatch();
	}

	/**
	 * Check whether a test task has been executed for a given key.
	 *
	 * @param  string $key The key that identifies the task we're checking for.
	 *
	 * @return bool TRUE if a test task has been executed for this key, FALSE if not.
	 */
	public function check( $key ) {

		$transient_name = $this->identifier . '_' . $key;

		// If the transient for this key was set by handle()...
		if ( get_transient( $transient_name ) ) {
			// Delete the transient so it's not taking up space in memcache or the options table.
			delete_transient( $transient_name );
			return true;
		}

		return false;
	}

	/**
	 * Execute an async task.
	 */
	protected function handle() {

		$transient_name = $this->identifier . '_' . sanitize_key( $_POST['key'] );

		// Set a transient (with a short shelf life) indicating that this task was executed.
		set_transient( $transient_name, 1, 120 );
	}
}
