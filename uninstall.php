<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_external_cache" );

// Delete options
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'onewebp_%'" );

// Delete transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_onewebp_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_onewebp_%'" );

// Delete WebP files
$upload_dir = wp_upload_dir();
$base_dir = str_replace( '\\', '/', $upload_dir['basedir'] );

// Delete all WebP files in uploads
$files = glob( $base_dir . '/**/*.webp', GLOB_NOSORT );
if ( $files ) {
    foreach ( $files as $file ) {
        if ( is_file( $file ) ) {
            @unlink( $file );
        }
    }
}

// Delete external cache directory
$ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
if ( is_dir( $ext_cache_dir ) ) {
    $ext_files = glob( $ext_cache_dir . '*' );
    foreach ( $ext_files as $file ) {
        if ( is_file( $file ) ) {
            @unlink( $file );
        }
    }
    @rmdir( $ext_cache_dir );
}
