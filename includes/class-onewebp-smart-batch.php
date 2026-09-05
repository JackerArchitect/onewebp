<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OneWebP_Smart_Batch {
    public function __construct() {
        add_action( 'wp_ajax_onewebp_run_batch', array( $this, 'handle_ajax_batch' ) );
        add_action( 'wp_ajax_onewebp_get_stats', array( $this, 'handle_ajax_stats' ) );
        add_action( 'wp_ajax_onewebp_scan_library', array( $this, 'handle_ajax_scan_library' ) );
    }

    private function verify_nonce() {
        if ( ! isset( $_POST['nonce'] ) ) {
            wp_send_json_error( __( 'Missing nonce parameter.', 'onewebp' ) );
        }
        
        if ( ! wp_verify_nonce( $_POST['nonce'], 'onewebp_nonce' ) ) {
            wp_send_json_error( __( 'Security check failed.', 'onewebp' ) );
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'onewebp' ) );
        }
    }

    public function handle_ajax_stats() {
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        $total     = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
        $converted = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'success'" );
        $pending   = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
        $failed    = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'failed'" );

        $total_orig = (int) $wpdb->get_var( "SELECT COALESCE(SUM(original_size), 0) FROM {$table} WHERE status = 'success'" );
        $total_webp = (int) $wpdb->get_var( "SELECT COALESCE(SUM(webp_size), 0) FROM {$table} WHERE status = 'success'" );

        $saved_bytes = max( 0, $total_orig - $total_webp );
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
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        $offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
        $limit  = 20;

        // Query WordPress media library
        $attachments = get_posts( array(
            'post_type' => 'attachment',
            'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ),
            'posts_per_page' => $limit,
            'offset' => $offset,
            'fields' => 'ids',
            'no_found_rows' => true
        ) );

        if ( empty( $attachments ) ) {
            wp_send_json_success( array( 
                'done' => true, 
                'scanned' => 0, 
                'next_offset' => 0 
            ) );
        }

        $upload_dir = wp_upload_dir();
        $base_dir = str_replace( '\\', '/', $upload_dir['basedir'] );
        $scanned_count = 0;

        foreach ( $attachments as $attachment_id ) {
            $file_path = get_attached_file( $attachment_id );
            if ( ! $file_path ) {
                continue;
            }
            
            $file_path = str_replace( '\\', '/', $file_path );
            
            // Check if already in log
            $exists = $wpdb->get_var( $wpdb->prepare( 
                "SELECT id FROM {$table} WHERE original_url = %s AND attachment_id = %d",
                $file_path,
                $attachment_id 
            ) );
            
            if ( $exists ) {
                continue;
            }

            $ext = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
            $status = 'pending';
            $webp_url = $file_path . '.webp';
            $webp_size = 0;

            if ( $ext === 'webp' ) {
                $status = 'success';
                $webp_url = $file_path;
                $webp_size = file_exists( $file_path ) ? filesize( $file_path ) : 0;
            } elseif ( file_exists( $webp_url ) ) {
                $webp_size = filesize( $webp_url );
                $status = 'success';
            }

            $wpdb->insert( $table, array(
                'attachment_id' => $attachment_id,
                'image_type'    => 'local',
                'size_name'     => 'original',
                'original_url'  => $file_path,
                'webp_url'      => $webp_url,
                'original_size' => file_exists( $file_path ) ? filesize( $file_path ) : 0,
                'webp_size'     => $webp_size,
                'status'        => $status
            ) );
            $scanned_count++;
        }

        // Check if we're done
        $total_attachments = wp_count_posts( 'attachment' );
        $total = isset( $total_attachments->inherit ) ? (int) $total_attachments->inherit : 0;

        wp_send_json_success( array(
            'done'        => ( $offset + $limit ) >= $total,
            'scanned'     => $scanned_count,
            'next_offset' => $offset + $limit
        ));
    }

    public function handle_ajax_batch() {
        $this->verify_nonce();
        
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        // Get pending count
        $total_pending = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status = 'pending'" );
        
        if ( $total_pending === 0 ) {
            wp_send_json_success( array( 
                'done' => true, 
                'progress' => 100, 
                'message' => __( 'Optimization Complete!', 'onewebp' ), 
                'total_pending' => 0 
            ) );
        }

        // Dynamic batch size
        $batch_size = min( 10, max( 3, ceil( $total_pending / 100 ) ) );
        
        $pending_images = $wpdb->get_results( 
            $wpdb->prepare( 
                "SELECT * FROM {$table} WHERE status = 'pending' LIMIT %d", 
                $batch_size 
            ), 
            ARRAY_A 
        );
        
        if ( empty( $pending_images ) ) {
            wp_send_json_success( array( 
                'done' => true, 
                'progress' => 100, 
                'message' => __( 'Optimization Complete!', 'onewebp' ) 
            ) );
        }
        
        if ( ! class_exists( 'OneWebP_Converter' ) ) {
            require_once ONEWEBP_DIR . 'includes/class-onewebp-converter.php';
        }
        
        $converter = new OneWebP_Converter();
        $processed = 0;

        foreach ( $pending_images as $img ) {
            $result = $converter->execute_conversion( $img['original_url'], $img['webp_url'] );
            
            if ( $result['success'] ) {
                $webp_size = file_exists( $img['webp_url'] ) ? filesize( $img['webp_url'] ) : 0;
                $orig_size = $img['original_size'] ?: ( file_exists( $img['original_url'] ) ? filesize( $img['original_url'] ) : 0 );
                
                $wpdb->update( $table, array( 
                    'status' => 'success', 
                    'webp_size' => $webp_size, 
                    'original_size' => $orig_size,
                    'is_downscaled' => $result['is_downscaled'] ? 1 : 0
                ), array( 'id' => $img['id'] ) );
            } else {
                $wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $img['id'] ) );
            }
            $processed++;
        }

        $total_processed = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} WHERE status IN ('success', 'failed')" );
        $total_all = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );
        $progress = ( $total_all > 0 ) ? round( ( $total_processed / $total_all ) * 100, 1 ) : 100;
        $remaining = max( 0, $total_pending - $processed );

        wp_send_json_success( array(
            'done' => $remaining === 0,
            'progress' => $progress,
            'total_pending' => $remaining,
            'message' => sprintf( __( 'Processing... %s%%', 'onewebp' ), $progress )
        ));
    }
}
