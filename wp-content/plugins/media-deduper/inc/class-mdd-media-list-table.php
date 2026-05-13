<?php
/**
 * Media Library List Table class.
 */
require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
require_once( ABSPATH . 'wp-admin/includes/class-wp-media-list-table.php' );

/**
 * Based on WP_Media_List_Table
 */
class MDD_Media_List_Table extends WP_Media_List_Table {

	// Dummy property to prevent deprecation warnings from line 23.
	public $is_trash;

	/**
	 * @global WP_Query $wp_query
	 */
	public function prepare_items() {
		global $wp_query;

		// Eliminate some E_NOTICES from class-wp-media-list-table.
		$this->is_trash = false;

		$this->set_pagination_args( array(
			'total_items' => $wp_query->found_posts,
			'total_pages' => $wp_query->max_num_pages,
			'per_page'    => $wp_query->query_vars['posts_per_page'],
		) );
	}

	/**
	 * @access public
	 */
	public function no_items() {
		_e( 'Great news, no duplicates were found!', 'media-deduper' );
	}

	/**
	 * @return array
	 */
	protected function get_bulk_actions() {
		$actions = array();
		$actions['smartdelete'] = __( 'Smart Delete', 'media-deduper' );
		$actions['delete']      = __( 'Delete Permanently', 'media-deduper' );
		return $actions;
	}

	/**
	 * Handles the parent column output, including attach/detach links. The stock WP_Media_List_Table
	 * class hard-codes the 'detach' link back to upload.php, but not only would it be awkward to send
	 * our users back to the upload.php list table, the generated link doesn't even work to detach the
	 * attachment.
	 *
	 * There's no filter for this URL, so we re-generate it here, look for it, and replace it with an
	 * altered version that *does* point back to our custom list table screen.
	 *
	 * @param WP_Post $post The current WP_Post object.
	 */
	public function column_parent( $post ) {

		// Get the markup that the parent class would normally output for this column.
		ob_start();
		parent::column_parent( $post );
		$markup = ob_get_clean();

		// Get the URL that the parent class would normally use as the 'detach' URL for this post.
		$stock_detach_url = add_query_arg( array(
			'parent_post_id' => $post->post_parent,
			'media[]' => $post->ID,
			'_wpnonce' => wp_create_nonce( 'bulk-' . $this->_args['plural'] ),
		), 'upload.php' );

		// Add our page slug to it so that it points to this list table, instead of to the normal
		// upload.php one.
		$custom_detach_url = add_query_arg( array(
			'page' => 'media-deduper',
		), $stock_detach_url );

		// Replace the normal URL with our altered version.
		$markup = str_replace( $stock_detach_url, $custom_detach_url, $markup );

		return $markup;
	}

	/**
	 * Output markup to display a row of items.
	 *
	 * This is identical to the method of the parent class, but this class allows the display of
	 * trashed and non-trashed attachments together.
	 *
	 * @global WP_Post $post
	 */
	public function display_rows() {
		global $post, $wp_query;

		$post_ids = wp_list_pluck( $wp_query->posts, 'ID' );
		reset( $wp_query->posts );

		$this->comment_pending_count = get_pending_comments_num( $post_ids );

		add_filter( 'the_title','esc_html' );

		while ( have_posts() ) : the_post();
			$post_owner = ( get_current_user_id() == $post->post_author ) ? 'self' : 'other';
		?>
			<tr id="post-<?php echo $post->ID; ?>" class="<?php echo trim( ' author-' . $post_owner . ' status-' . $post->post_status ); ?>">
				<?php $this->single_row_columns( $post ); ?>
			</tr>
		<?php
		endwhile;
	}

	/**
	 * Add 'In Trash' below the filename for trashed attachments, to differentiate them from others.
	 *
	 * @param WP_Post $post The post being displayed.
	 */
	public function column_title( $post ) {
		parent::column_title( $post );
		global $post;
		if ( 'trash' === $post->post_status ) {
			echo '<p><em style="color: #a00;">In Trash</em></p>';
		}
	}
}
