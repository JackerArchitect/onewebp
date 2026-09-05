<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OneWebP_External {
    public function __construct() {
        // External logic is integrated into Frontend regex if enabled, 
        // but for simplicity in this architecture, we hook into the frontend buffer.
        add_filter( 'onewebp_process_external_image', array( $this, 'handle_external' ), 10, 2 );
    }

    public function handle_external( $url, $html ) {
        if ( ! get_option( 'onewebp_enable_external', 0 ) ) return $html;
        
        global $wpdb;
        $cache_table = $wpdb->prefix . 'onewebp_external_cache';
        
        $cached = $wpdb->get_row( $wpdb->prepare( "SELECT local_webp_url FROM {$cache_table} WHERE original_url = %s", $url ) );
        if ( $cached ) {
            return str_replace( $url, esc_url( $cached->local_webp_url ), $html );
        }

        // Download and convert (Simplified for MVP)
        $response = wp_remote_get( $url, array( 'timeout' => 15 ) );
        if ( is_wp_error( $response ) ) return $html;

        $image_data = wp_remote_retrieve_body( $response );
        $upload_dir = wp_upload_dir();
        $ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
        if ( ! file_exists( $ext_cache_dir ) ) wp_mkdir_p( $ext_cache_dir );

        $filename = md5( $url ) . '.jpg';
        $temp_path = $ext_cache_dir . $filename;
        file_put_contents( $temp_path, $image_data );

        $webp_path = $temp_path . '.webp';
        $converter = new OneWebP_Converter();
        $result = $converter->execute_conversion( $temp_path, $webp_path );

        if ( $result['success'] ) {
            $local_webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $webp_path );
            $wpdb->insert( $cache_table, array( 'original_url' => $url, 'local_webp_url' => $local_webp_url ) );
            @unlink( $temp_path ); // Remove temp original
            return str_replace( $url, esc_url( $local_webp_url ), $html );
        }
        
        @unlink( $temp_path );
        return $html;
    }
}