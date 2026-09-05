<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OneWebP_Core {
    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    public function init() {
        new OneWebP_Converter();
        new OneWebP_Frontend();
        new OneWebP_Smart_Batch();
        new OneWebP_External();
        add_action( 'admin_init', array( $this, 'handle_reset_actions' ) );
        add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
        add_action( 'wp_loaded', array( $this, 'handle_cleanup_orphaned_files' ) );
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
            PRIMARY KEY (id),
            KEY attachment_id (attachment_id),
            KEY status (status)
        ) $charset_collate;";
        
        $sql_ext = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}onewebp_external_cache (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            original_url varchar(2000) NOT NULL,
            local_webp_url varchar(2000) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY original_url (original_url(191))
        ) $charset_collate;";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_logs );
        dbDelta( $sql_ext );
        
        set_transient( 'onewebp_activation_redirect', true, 30 );
    }

    public static function deactivate() {
        // Clean up any scheduled events or transients
        delete_transient( 'onewebp_activation_redirect' );
        delete_transient( 'onewebp_cleanup_run' );
    }

    public function maybe_redirect_after_activation() {
        if ( get_transient( 'onewebp_activation_redirect' ) ) {
            delete_transient( 'onewebp_activation_redirect' );
            if ( ! isset( $_GET['activate-multi'] ) && ! wp_doing_ajax() ) {
                wp_redirect( admin_url( 'admin.php?page=onewebp&tab=dashboard' ) );
                exit;
            }
        }
    }

    public function handle_reset_actions() {
        if ( ! isset( $_POST['onewebp_reset_action'] ) ) {
            return;
        }
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'onewebp' ) );
        }
        
        $action = sanitize_text_field( $_POST['onewebp_reset_action'] );
        
        if ( $action === 'reset_settings' && check_admin_referer( 'onewebp_reset_settings' ) ) {
            $options = array( 
                'onewebp_quality', 
                'onewebp_max_resolution', 
                'onewebp_allow_oversized', 
                'onewebp_first_n_direct', 
                'onewebp_conversion_scope', 
                'onewebp_enable_external', 
                'onewebp_enable_lazyload', 
                'onewebp_allowed_types',
                'onewebp_image_deletion_mode'
            );
            foreach ( $options as $opt ) {
                delete_option( $opt );
            }
            wp_redirect( admin_url( 'admin.php?page=onewebp&tab=settings&msg=reset_settings' ) );
            exit;
        }
        
        if ( $action === 'reset_data' && check_admin_referer( 'onewebp_reset_data' ) ) {
            global $wpdb;
            $table = $wpdb->prefix . 'onewebp_logs';
            $ext_table = $wpdb->prefix . 'onewebp_external_cache';
            
            // Get all WebP file paths from logs
            $webp_files = $wpdb->get_col( "SELECT webp_url FROM {$table} WHERE webp_url LIKE '%.webp'" );
            foreach ( $webp_files as $file ) {
                if ( file_exists( $file ) ) {
                    @unlink( $file );
                }
            }
            
            // Clear external cache directory
            $upload_dir = wp_upload_dir();
            $ext_cache_dir = $upload_dir['basedir'] . '/onewebp-external-cache/';
            if ( is_dir( $ext_cache_dir ) ) {
                $ext_files = glob( $ext_cache_dir . '*' );
                foreach ( $ext_files as $file ) {
                    if ( is_file( $file ) ) {
                        @unlink( $file );
                    }
                }
            }
            
            // Truncate tables
            $wpdb->query( "TRUNCATE TABLE {$table}" );
            $wpdb->query( "TRUNCATE TABLE {$ext_table}" );
            
            wp_redirect( admin_url( 'admin.php?page=onewebp&tab=settings&msg=reset_data' ) );
            exit;
        }
    }

    public function handle_cleanup_orphaned_files() {
        // Only run once per week
        if ( get_transient( 'onewebp_cleanup_run' ) ) {
            return;
        }
        set_transient( 'onewebp_cleanup_run', true, WEEK_IN_SECONDS );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';
        
        // Get all WebP files from uploads directory
        $upload_dir = wp_upload_dir();
        $base_dir = str_replace( '\\', '/', $upload_dir['basedir'] );
        
        $files = glob( $base_dir . '/**/*.webp', GLOB_NOSORT );
        if ( empty( $files ) ) {
            return;
        }
        
        $webp_paths = array_map( function( $file ) {
            return str_replace( '\\', '/', $file );
        }, $files );
        
        // Process in batches
        $batch_size = 100;
        for ( $i = 0; $i < count( $webp_paths ); $i += $batch_size ) {
            $batch = array_slice( $webp_paths, $i, $batch_size );
            if ( empty( $batch ) ) {
                continue;
            }
            
            $placeholders = implode( ', ', array_fill( 0, count( $batch ), '%s' ) );
            $query = $wpdb->prepare( 
                "SELECT webp_url FROM {$table} WHERE webp_url IN ($placeholders)",
                $batch 
            );
            $existing = $wpdb->get_col( $query );
            $existing_set = array_flip( $existing );
            
            foreach ( $batch as $file ) {
                if ( ! isset( $existing_set[ $file ] ) ) {
                    // Check if this is an external cache file
                    if ( strpos( $file, '/onewebp-external-cache/' ) === false ) {
                        @unlink( $file );
                    }
                }
            }
        }
    }
}
