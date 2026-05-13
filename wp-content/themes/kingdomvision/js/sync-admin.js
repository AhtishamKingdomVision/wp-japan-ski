/* global kvSync, jQuery */
( function ( $ ) {
    'use strict';

    var stopRequested = false;

    var $runBtn      = $( '#kv-run-sync' );
    var $stopBtn     = $( '#kv-stop-sync' );
    var $status      = $( '#kv-sync-status' );
    var $progressWrap = $( '#kv-progress-wrap' );
    var $progressFill = $( '#kv-progress-fill' );
    var $progressLabel = $( '#kv-progress-label' );

    /* ── Stat element refs ── */
    var $statLastSync = $( '#kv-stat-last-sync' );
    var $statTotal    = $( '#kv-stat-total' );
    var $statAdded    = $( '#kv-stat-added' );
    var $statUpdated  = $( '#kv-stat-updated' );

    /* ──────────────────────────────────────────
     * Start button
     * ────────────────────────────────────────── */
    $runBtn.on( 'click', function () {
        stopRequested = false;

        $runBtn.prop( 'disabled', true );
        $stopBtn.show();
        $progressWrap.show();
        $progressFill.css( 'width', '0%' );
        $progressLabel.text( '' );
        $status.text( kvSync.i18n.syncing );

        /* Reset state on server then kick off first chunk */
        $.post( kvSync.ajaxUrl, {
            action : 'kv_sync_start',
            nonce  : kvSync.nonce,
        } )
        .done( function () {
            processNextChunk();
        } )
        .fail( function () {
            handleError();
        } );
    } );

    /* ──────────────────────────────────────────
     * Stop button
     * ────────────────────────────────────────── */
    $stopBtn.on( 'click', function () {
        stopRequested = true;
        $status.text( kvSync.i18n.stopping );
        $stopBtn.prop( 'disabled', true );
    } );

    /* ──────────────────────────────────────────
     * Process one chunk, then schedule the next
     * ────────────────────────────────────────── */
    function processNextChunk() {
        if ( stopRequested ) {
            finish( false );
            return;
        }

        $.post( kvSync.ajaxUrl, {
            action : 'kv_sync_chunk',
            nonce  : kvSync.nonce,
        } )
        .done( function ( response ) {
            if ( ! response.success ) {
                handleError( response.data && response.data.message );
                return;
            }

            var data        = response.data;
            var page        = data.page        || 1;
            var totalPages  = data.total_pages || 1;
            var pct         = totalPages > 0 ? Math.min( 100, Math.round( ( page / totalPages ) * 100 ) ) : 100;

            /* Update progress bar */
            $progressFill.css( 'width', pct + '%' );
            $progressLabel.text(
                'Page ' + page + ' of ' + totalPages +
                '  |  Added: ' + ( data.added || 0 ) +
                '  |  Updated: ' + ( data.updated || 0 )
            );

            /* Update stat cards live */
            if ( data.added   !== undefined ) { $statAdded.text( data.added ); }
            if ( data.updated !== undefined ) { $statUpdated.text( data.updated ); }
            if ( data.total   !== undefined ) { $statTotal.text( data.total ); }
            if ( data.last_sync )             { $statLastSync.text( data.last_sync ); }

            if ( data.done ) {
                $progressFill.css( 'width', '100%' );
                finish( true, data );
            } else {
                /* Small delay between chunks to ease server load */
                setTimeout( processNextChunk, 500 );
            }
        } )
        .fail( function () {
            handleError();
        } );
    }

    /* ──────────────────────────────────────────
     * Finish (done or stopped)
     * ────────────────────────────────────────── */
    function finish( completed, data ) {
        $runBtn.prop( 'disabled', false );
        $stopBtn.hide().prop( 'disabled', false );

        if ( completed ) {
            $status.text( kvSync.i18n.done );
        } else {
            $status.text( kvSync.i18n.stopped );
        }
    }

    /* ──────────────────────────────────────────
     * Error handler
     * ────────────────────────────────────────── */
    function handleError( msg ) {
        $runBtn.prop( 'disabled', false );
        $stopBtn.hide().prop( 'disabled', false );
        $status.text( ( msg || kvSync.i18n.error ) );
    }

} )( jQuery );
