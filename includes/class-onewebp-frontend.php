<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OneWebP_Frontend {
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'start_buffer' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function start_buffer() {
        if ( is_admin() || is_feed() || wp_doing_ajax() ) return;
        ob_start( array( $this, 'process_html' ) );
    }

    public function process_html( $html ) {
        $upload_info = wp_upload_dir();
        $base_url = $upload_info['baseurl'];
        $base_dir = $upload_info['basedir'];
        $first_n = intval( get_option( 'onewebp_first_n_direct', 3 ) );
        $enable_lazy = get_option( 'onewebp_enable_lazyload', 1 );
        $counter = 0;

        $allowed_types = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) );
        $ext_pattern = array();
        if ( in_array( 'jpeg', $allowed_types ) ) { $ext_pattern[] = 'jpg'; $ext_pattern[] = 'jpeg'; }
        if ( in_array( 'png', $allowed_types ) ) $ext_pattern[] = 'png';
        if ( in_array( 'gif', $allowed_types ) ) $ext_pattern[] = 'gif';
        if ( empty( $ext_pattern ) ) return $html;

        $regex = '/<img([^>]+)src=["\']([^"\']+\.(' . implode( '|', $ext_pattern ) . '))["\']([^>]*)\/?>/i';

        return preg_replace_callback(
            $regex,
            function ( $matches ) use ( $base_url, $base_dir, $first_n, $enable_lazy, &$counter ) {
                $counter++;
                $attributes = $matches[1] . $matches[4];
                $original_url = $matches[2];

                if ( strpos( $original_url, $base_url ) === false ) return $matches[0];

                $webp_url = $original_url . '.webp';
                $webp_path = str_replace( $base_url, $base_dir, $webp_url );
                if ( ! file_exists( $webp_path ) ) return $matches[0];

                $is_critical = ( $counter <= $first_n );
                $priority = ( $counter === 1 ) ? ' fetchpriority="high"' : '';

                if ( $is_critical || ! $enable_lazy ) {
                    return '<picture><source srcset="' . esc_url( $webp_url ) . '" type="image/webp"><img' . $attributes . ' src="' . esc_url( $original_url ) . '"' . $priority . '></picture>';
                } else {
                    $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                    $clean_attr = preg_replace( '/\s*src=["\'][^"\']+["\']/', '', $attributes );
                    return '<picture><source data-srcset="' . esc_url( $webp_url ) . '" type="image/webp"><img' . $clean_attr . ' src="' . $placeholder . '" data-src="' . esc_url( $original_url ) . '" class="onewebp-lazy-img"></picture>';
                }
            },
            $html
        );
    }

    public function enqueue_assets() {
        if ( get_option( 'onewebp_enable_lazyload', 1 ) ) {
            wp_enqueue_script( 'onewebp-frontend-js', ONEWEBP_URL . 'assets/js/frontend-lazy.js', array(), ONEWEBP_VERSION, true );
        }
    }
}