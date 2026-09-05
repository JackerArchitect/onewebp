<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OneWebP_Core {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) self::$instance = new self();
        return self::$instance;
    }

    public function init() {
        new OneWebP_Converter();
        new OneWebP_Frontend();
        new OneWebP_Smart_Batch();
        new OneWebP_External();
        
        add_action( 'admin_init', array( $this, 'handle_reset_actions' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
    }

    public static function activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}onewebp_logs (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            attachment_id bigint(20) DEFAULT 0,
            image_type varchar(20) NOT NULL DEFAULT 'local',
            size_name varchar(50) DEFAULT 'original',
            original_url varchar(2000) NOT NULL,
            webp_url varchar(2000) NOT NULL,
            original_size int(11) DEFAULT 0,
            webp_size int(11) DEFAULT 0,
            is_downscaled tinyint(1) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_ext = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}onewebp_external_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            original_url varchar(2000) NOT NULL,
            local_webp_url varchar(2000) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql_logs );
        dbDelta( $sql_ext );

        // Set redirect flag for activation
        set_transient( 'onewebp_activation_redirect', true, 30 );
    }

    public function maybe_redirect_after_activation() {
        if ( get_transient( 'onewebp_activation_redirect' ) ) {
            delete_transient( 'onewebp_activation_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=onewebp&tab=dashboard' ) );
                exit;
            }
        }
    }

    public function handle_reset_actions() {
        if ( ! isset( $_POST['onewebp_reset_action'] ) ) return;
        
        $action = sanitize_text_field( $_POST['onewebp_reset_action'] );

        if ( $action === 'reset_settings' && check_admin_referer( 'onewebp_reset_settings' ) ) {
            $options = array( 'onewebp_quality', 'onewebp_max_resolution', 'onewebp_allow_oversized', 'onewebp_first_n_direct', 'onewebp_conversion_scope', 'onewebp_enable_external', 'onewebp_enable_lazyload', 'onewebp_allowed_types' );
            foreach ( $options as $opt ) {
                delete_option( $opt );
            }
            wp_redirect( admin_url( 'admin.php?page=onewebp&tab=settings&msg=reset_settings' ) );
            exit;
        }

        if ( $action === 'reset_data' && check_admin_referer( 'onewebp_reset_data' ) ) {
            global $wpdb;
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}onewebp_logs" );
            $wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}onewebp_external_cache" );
            
            $upload_dir = wp_upload_dir();
            $files = glob( $upload_dir['basedir'] . '/**/*.webp', GLOB_BRACE );
            if ( $files ) {
                foreach ( $files as $file ) {
                    if ( is_file( $file ) ) @unlink( $file );
                }
            }
            
            $ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
            if ( is_dir( $ext_cache_dir ) ) {
                $ext_files = glob( $ext_cache_dir . '*' );
                foreach ( $ext_files as $file ) {
                    if ( is_file( $file ) ) @unlink( $file );
                }
            }
            
            wp_redirect( admin_url( 'admin.php?page=onewebp&tab=settings&msg=reset_data' ) );
            exit;
        }
    }
}