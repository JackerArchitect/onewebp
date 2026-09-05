<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_logs" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}onewebp_external_cache" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'onewebp_%'" );
$upload_dir = wp_upload_dir();
$files = glob( $upload_dir['basedir'] . '/**/*.webp', GLOB_BRACE );
if ( $files ) { foreach ( $files as $file ) { if ( is_file( $file ) ) @unlink( $file ); } }