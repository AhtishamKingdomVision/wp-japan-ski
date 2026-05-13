<?php
/**
 * Bulk Sync Admin Page
 *
 * Registers a WordPress admin page that lets administrators sync all
 * accommodation properties in configurable chunks so that server load
 * is kept low.  Progress is reported back to the browser via AJAX so
 * the page never times out.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ──────────────────────────────────────────────
 * 1.  Register admin menu page
 * ────────────────────────────────────────────── */

add_action( 'admin_menu', 'kv_sync_register_admin_page' );
function kv_sync_register_admin_page() {
    add_submenu_page(
        'edit.php?post_type=accommodation',                        // Parent: Tools menu
        'Bulk Property Sync',               // Page title
        'Bulk Property Sync',               // Menu label
        'manage_options',                   // Capability required
        'kv-bulk-sync',                     // Menu slug
        'kv_sync_render_admin_page'         // Render callback
    );
}

/* ──────────────────────────────────────────────
 * 2.  Enqueue admin scripts / styles
 * ────────────────────────────────────────────── */

add_action( 'admin_enqueue_scripts', 'kv_sync_enqueue_assets' );
function kv_sync_enqueue_assets( $hook ) {
    if ( $hook !== 'accommodation_page_kv-bulk-sync' ) {
        return;
    }

    wp_enqueue_style(
        'kv-sync-admin',
        get_template_directory_uri() . '/css/sync-admin.css',
        [],
        filemtime( get_theme_file_path( '/css/sync-admin.css' ) )
    );

    wp_enqueue_script(
        'kv-sync-admin',
        get_template_directory_uri() . '/js/sync-admin.js',
        [ 'jquery' ],
        filemtime( get_theme_file_path( '/js/sync-admin.js' ) ),
        true
    );

    wp_localize_script( 'kv-sync-admin', 'kvSync', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'kv_sync_nonce' ),
        'chunkSize' => intval( get_option( 'kv_sync_chunk_size', 3 ) ),
        'i18n'      => [
            'syncing'    => __( 'Syncing…', 'kv' ),
            'done'       => __( 'Sync complete!', 'kv' ),
            'error'      => __( 'An error occurred. Please try again.', 'kv' ),
            'stopping'   => __( 'Stopping after current chunk…', 'kv' ),
            'stopped'    => __( 'Sync stopped.', 'kv' ),
        ],
    ] );
}

/* ──────────────────────────────────────────────
 * 3.  Render the admin page HTML
 * ────────────────────────────────────────────── */

function kv_sync_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.' ) );
    }

    /* Save chunk size setting */
    if (
        isset( $_POST['kv_sync_save_settings'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'kv_sync_settings' )
    ) {
        $chunk_size = max( 1, min( 20, intval( $_POST['kv_sync_chunk_size'] ?? 3 ) ) );
        update_option( 'kv_sync_chunk_size', $chunk_size, false );
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.' ) . '</p></div>';
    }

    /* Fetch stored stats */
    $last_sync        = get_option( 'kv_sync_last_run', '' );
    $total_properties = intval( get_option( 'kv_sync_total_properties', 0 ) );
    $last_added       = intval( get_option( 'kv_sync_last_added', 0 ) );
    $last_updated     = intval( get_option( 'kv_sync_last_updated', 0 ) );
    $chunk_size       = intval( get_option( 'kv_sync_chunk_size', 3 ) );

    $last_sync_display = $last_sync
        ? esc_html( $last_sync )
        : esc_html__( 'Never' );
    ?>
    <div class="wrap kv-sync-wrap">
        <h1 class="wp-heading-inline"><?php esc_html_e( 'Bulk Property Sync' ); ?></h1>
        <hr class="wp-header-end">

        <!-- ── Stat cards ── -->
        <div class="kv-sync-cards">
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Last Sync' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-last-sync">
                    <?php echo $last_sync_display; ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Total Properties' ); ?></span>
                <span class="kv-sync-card__value kv-color-blue" id="kv-stat-total">
                    <?php echo esc_html( $total_properties ); ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Added (Last Sync)' ); ?></span>
                <span class="kv-sync-card__value kv-color-green" id="kv-stat-added">
                    <?php echo esc_html( $last_added ); ?>
                </span>
            </div>
            <div class="kv-sync-card">
                <span class="kv-sync-card__label"><?php esc_html_e( 'Updated (Last Sync)' ); ?></span>
                <span class="kv-sync-card__value kv-color-yellow" id="kv-stat-updated">
                    <?php echo esc_html( $last_updated ); ?>
                </span>
            </div>
        </div>

        <!-- ── Progress bar ── -->
        <div class="kv-sync-progress-wrap" id="kv-progress-wrap" style="display:none;">
            <div class="kv-sync-progress-bar">
                <div class="kv-sync-progress-fill" id="kv-progress-fill"></div>
            </div>
            <p class="kv-sync-progress-label" id="kv-progress-label"></p>
        </div>

        <!-- ── Status message ── -->
        <p class="kv-sync-status" id="kv-sync-status"></p>

        <!-- ── Action buttons ── -->
        <div class="kv-sync-actions">
            <button type="button" class="button button-primary kv-sync-btn" id="kv-run-sync">
                <?php esc_html_e( 'Run Sync Now' ); ?>
            </button>
            <button type="button" class="button kv-sync-btn" id="kv-stop-sync" style="display:none;">
                <?php esc_html_e( 'Stop Sync' ); ?>
            </button>
        </div>

        <!-- ── Settings ── -->
        <div class="kv-sync-settings-wrap">
            <h2><?php esc_html_e( 'Sync Settings' ); ?></h2>
            <form method="post">
                <?php wp_nonce_field( 'kv_sync_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row">
                            <label for="kv_sync_chunk_size">
                                <?php esc_html_e( 'Properties per Chunk' ); ?>
                            </label>
                        </th>
                        <td>
                            <input
                                type="number"
                                id="kv_sync_chunk_size"
                                name="kv_sync_chunk_size"
                                value="<?php echo esc_attr( $chunk_size ); ?>"
                                min="1"
                                max="20"
                                class="small-text"
                            >
                            <p class="description">
                                <?php esc_html_e( 'Number of properties to sync per request. Lower values reduce server load. Recommended: 3–5.' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input
                        type="submit"
                        name="kv_sync_save_settings"
                        class="button button-secondary"
                        value="<?php esc_attr_e( 'Save Settings' ); ?>"
                    >
                </p>
            </form>
        </div>
    </div>
    <?php
}

/* ──────────────────────────────────────────────
 * 4.  AJAX: start a fresh sync (resets counters)
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_start', 'kv_ajax_sync_start' );
function kv_ajax_sync_start() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    check_ajax_referer( 'kv_sync_nonce', 'nonce' );

    /* Set all accommodation and japan_rooms posts to pending */
    global $wpdb;
    foreach ( [ 'accommodation', 'japan_rooms' ] as $post_type ) {
        $wpdb->update(
            $wpdb->posts,
            [ 'post_status' => 'pending' ],
            [ 'post_type' => $post_type, 'post_status' => 'publish' ],
            [ '%s' ],
            [ '%s', '%s' ]
        );
    }
    clean_post_cache( 0 );

    /* Reset all pagination and session counters */
    update_option( 'hz_page', 1, false );
    update_option( 'hz_total_pages', 1, false );
    update_option( 'kv_sync_session_added', 0, false );
    update_option( 'kv_sync_session_updated', 0, false );

    wp_send_json_success( [ 'message' => 'Sync session started' ] );
}

/* ──────────────────────────────────────────────
 * 5.  AJAX: process one chunk of properties
 * ────────────────────────────────────────────── */

add_action( 'wp_ajax_kv_sync_chunk', 'kv_ajax_sync_chunk' );
function kv_ajax_sync_chunk() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
    }

    check_ajax_referer( 'kv_sync_nonce', 'nonce' );

    $chunk_size  = max( 1, min( 20, intval( get_option( 'kv_sync_chunk_size', 3 ) ) ) );
    $page_num    = intval( get_option( 'hz_page', 1 ) );
    $total_pages = intval( get_option( 'hz_total_pages', 1 ) );

    /* All pages processed */
    if ( $page_num > $total_pages && $total_pages > 1 ) {
        kv_sync_finalize();
        wp_send_json_success( [
            'done'        => true,
            'page'        => $page_num,
            'total_pages' => $total_pages,
            'added'       => intval( get_option( 'kv_sync_last_added', 0 ) ),
            'updated'     => intval( get_option( 'kv_sync_last_updated', 0 ) ),
            'total'       => intval( get_option( 'kv_sync_total_properties', 0 ) ),
            'last_sync'   => get_option( 'kv_sync_last_run', '' ),
        ] );
    }

    /* Fetch properties for this page */
    $result = hz_get_limited_properties( $page_num, $chunk_size );

    if ( $result === false || ! is_array( $result ) ) {
        wp_send_json_error( [ 'message' => 'Failed to fetch properties from API for page ' . $page_num ] );
    }

    $properties  = $result['properties'] ?? [];
    $api_total   = intval( $result['total_pages'] ?? 1 );

    /* Update total pages from API on first page */
    if ( $api_total > 0 ) {
        update_option( 'hz_total_pages', $api_total, false );
        $total_pages = $api_total;
    }

    /* Track adds vs updates by comparing existing post IDs */
    $existing_ids = kv_sync_get_existing_property_ids( $properties );

    /* Run the existing mapping logic */
    if ( ! empty( $properties ) ) {
        sq_mapping_properties( $properties );
    }

    /* Count adds vs updates */
    $added   = 0;
    $updated = 0;
    foreach ( $properties as $property ) {
        $pid = trim( (string) ( $property['id'] ?? '' ) );
        if ( $pid === '' ) {
            continue;
        }
        if ( isset( $existing_ids[ $pid ] ) ) {
            $updated++;
        } else {
            $added++;
        }
    }

    /* Accumulate session totals */
    $session_added   = intval( get_option( 'kv_sync_session_added', 0 ) ) + $added;
    $session_updated = intval( get_option( 'kv_sync_session_updated', 0 ) ) + $updated;
    update_option( 'kv_sync_session_added', $session_added, false );
    update_option( 'kv_sync_session_updated', $session_updated, false );

    /* Advance page */
    $next_page = $page_num + 1;
    update_option( 'hz_page', $next_page, false );

    /* If this was the last page, finalize now */
    $done = ( $next_page > $total_pages );
    if ( $done ) {
        kv_sync_finalize();
    }

    wp_send_json_success( [
        'done'        => $done,
        'page'        => $page_num,
        'total_pages' => $total_pages,
        'added'       => $session_added,
        'updated'     => $session_updated,
        'total'       => intval( get_option( 'kv_sync_total_properties', 0 ) ),
        'last_sync'   => get_option( 'kv_sync_last_run', '' ),
    ] );
}

/* ──────────────────────────────────────────────
 * 6.  Helper: collect property_id → post_id map
 *     for properties about to be processed so we
 *     can distinguish adds from updates.
 * ────────────────────────────────────────────── */

function kv_sync_get_existing_property_ids( array $properties ) {
    $ids = [];
    foreach ( $properties as $property ) {
        $pid = trim( (string) ( $property['id'] ?? '' ) );
        if ( $pid === '' ) {
            continue;
        }
        $existing = get_post_id_by_typeId( $pid, 'accommodation' );
        if ( $existing ) {
            $ids[ $pid ] = intval( $existing );
        }
    }
    return $ids;
}

/* ──────────────────────────────────────────────
 * 7.  Helper: finalize sync – persist stats
 * ────────────────────────────────────────────── */

function kv_sync_finalize() {
    $total_posts = wp_count_posts( 'accommodation' );
    $total       = isset( $total_posts->publish ) ? intval( $total_posts->publish ) : 0;

    $last_sync = current_time( 'Y-m-d H:i:s' );

    update_option( 'kv_sync_last_run', $last_sync, false );
    update_option( 'kv_sync_total_properties', $total, false );
    update_option( 'kv_sync_last_added', intval( get_option( 'kv_sync_session_added', 0 ) ), false );
    update_option( 'kv_sync_last_updated', intval( get_option( 'kv_sync_session_updated', 0 ) ), false );

    /* Reset session accumulators */
    update_option( 'kv_sync_session_added', 0, false );
    update_option( 'kv_sync_session_updated', 0, false );

    /* Reset pagination for next cron cycle */
    update_option( 'hz_page', 1, false );
}
