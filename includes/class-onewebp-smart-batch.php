<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OneWebP_Smart_Batch {
    public function __construct() {
        add_action( 'wp_ajax_onewebp_run_batch', array( $this, 'handle_ajax_batch' ) );
        add_action( 'wp_ajax_onewebp_get_stats', array( $this, 'handle_ajax_stats' ) );
        add_action( 'wp_ajax_onewebp_scan_library', array( $this, 'handle_ajax_scan_library' ) );
    }

    public function handle_ajax_stats() {
        check_ajax_referer( 'onewebp_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        $total     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
        $converted = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'success'" );
        $pending   = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
        $failed    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'failed'" );

        $total_orig = $wpdb->get_var( "SELECT SUM(original_size) FROM {$table} WHERE status = 'success'" );
        $total_webp = $wpdb->get_var( "SELECT SUM(webp_size) FROM {$table} WHERE status = 'success'" );

        $saved_bytes = max( 0, intval( $total_orig ) - intval( $total_webp ) );
        $progress    = ( $total > 0 ) ? round( ( $converted / $total ) * 100, 1 ) : 0;

        wp_send_json_success( array(
            'total'       => $total,
            'converted'   => $converted,
            'pending'     => $pending,
            'failed'      => $failed,
            'saved_bytes' => $saved_bytes,
            'progress'    => $progress
        ));
    }

    public function handle_ajax_scan_library() {
        check_ajax_referer( 'onewebp_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $limit  = 50;

        $allowed_types = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) );
        $exts = array();
        if ( in_array( 'jpeg', $allowed_types, true ) ) { $exts[] = 'jpg'; $exts[] = 'jpeg'; }
        if ( in_array( 'png', $allowed_types, true ) )  { $exts[] = 'png'; }
        if ( in_array( 'gif', $allowed_types, true ) )  { $exts[] = 'gif'; }

        if ( empty( $exts ) ) {
            wp_send_json_success( array( 'done' => true, 'scanned' => 0, 'next_offset' => 0 ) );
        }

        $upload_dir = wp_upload_dir();
        $base_dir   = str_replace( '\\', '/', $upload_dir['basedir'] );
        
        // Simple recursive scan
        $files = $this->collect_upload_images( $base_dir, $exts );
        $total = count( $files );
        $chunk = array_slice( $files, $offset, $limit );

        if ( empty( $chunk ) ) {
            wp_send_json_success( array( 'done' => true, 'scanned' => 0, 'next_offset' => $offset ) );
        }

        $scanned_count = 0;
        foreach ( $chunk as $file_path ) {
            $exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE original_url = %s", $file_path ) );
            if ( $exists ) continue;

            $file_url = str_replace( $base_dir, $upload_dir['baseurl'], $file_path );
            $att_id   = attachment_url_to_postid( $file_url );
            
            // Determine size name
            $size_name = 'original';
            if ( $att_id ) {
                $orig_file = get_attached_file( $att_id );
                if ( $orig_file && str_replace( '\\', '/', $orig_file ) !== $file_path ) {
                    $meta = wp_get_attachment_metadata( $att_id );
                    $base = basename( $file_path );
                    if ( ! empty( $meta['sizes'] ) ) {
                        foreach ( $meta['sizes'] as $s_name => $s_data ) {
                            if ( isset( $s_data['file'] ) && $s_data['file'] === $base ) {
                                $size_name = $s_name;
                                break;
                            }
                        }
                    }
                }
            }

            $wpdb->insert( $table, array(
                'attachment_id' => $att_id,
                'image_type'    => 'local',
                'size_name'     => $size_name,
                'original_url'  => $file_path,
                'webp_url'      => $file_path . '.webp',
                'original_size' => filesize( $file_path ),
                'webp_size'     => 0,
                'status'        => 'pending'
            ) );
            $scanned_count++;
        }

        wp_send_json_success( array(
            'done'        => ( $offset + $limit ) >= $total,
            'scanned'     => $scanned_count,
            'next_offset' => $offset + $limit
        ) );
    }

    private function collect_upload_images( $dir, $exts ) {
        $files = array();
        if ( ! is_dir( $dir ) ) return $files;
        try {
            $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
            foreach ( $iterator as $file ) {
                if ( ! $file->isFile() ) continue;
                $ext = strtolower( $file->getExtension() );
                if ( in_array( $ext, $exts, true ) ) {
                    $files[] = str_replace( '\\', '/', $file->getPathname() );
                }
            }
        } catch ( Exception $e ) {}
        sort( $files );
        return $files;
    }

    public function handle_ajax_batch() {
        check_ajax_referer( 'onewebp_nonce', 'security' );
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        $total_pending = $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
        if ( $total_pending == 0 ) {
            wp_send_json_success( array( 'done' => true, 'progress' => 100, 'message' => 'Done!', 'total_pending' => 0 ) );
        }

        $pending_images = $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'pending' LIMIT 10", ARRAY_A );
        $processed = 0;
        $converter = new OneWebP_Converter();

        foreach ( $pending_images as $img ) {
            $result = $converter->execute_conversion( $img['original_url'], $img['webp_url'] );
            if ( $result['success'] ) {
                $webp_size = filesize( $img['webp_url'] );
                $orig_size = $img['original_size'] ?: filesize( $img['original_url'] );
                $wpdb->update( $table, array( 'status' => 'success', 'webp_size' => $webp_size, 'original_size' => $orig_size ), array( 'id' => $img['id'] ) );
            } else {
                $wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $img['id'] ) );
            }
            $processed++;
        }

        $total_processed = $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status IN ('success', 'failed')" );
        $total_all = $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
        $progress = ( $total_all > 0 ) ? round( ( $total_processed / $total_all ) * 100, 1 ) : 100;

        wp_send_json_success( array(
            'done' => ( $total_pending - $processed <= 0 ),
            'progress' => $progress,
            'total_pending' => max( 0, $total_pending - $processed ),
            'message' => "Processing... {$progress}%"
        ));
    }
}