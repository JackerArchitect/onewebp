<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OneWebP_Frontend {
    public function __construct() {
        add_action( 'template_redirect', array( $this, 'start_buffer' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 10, 3 );
    }

    public function start_buffer() {
        if ( is_admin() || is_feed() || wp_doing_ajax() || wp_doing_cron() ) {
            return;
        }
        ob_start( array( $this, 'process_html' ) );
    }

    public function process_html( $html ) {
        // Check if we should process
        if ( strlen( $html ) < 100 || ! get_option( 'onewebp_enable_lazyload', 1 ) ) {
            return $html;
        }

        $upload_info = wp_upload_dir();
        $base_url = $upload_info['baseurl'];
        $base_dir = $upload_info['basedir'];
        $first_n = intval( get_option( 'onewebp_first_n_direct', 3 ) );
        $enable_lazy = get_option( 'onewebp_enable_lazyload', 1 );
        $counter = 0;

        // Use DOMDocument for reliable parsing
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        libxml_use_internal_errors( true );
        $dom->loadHTML( mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' ) );
        libxml_clear_errors();

        $xpath = new DOMXPath( $dom );
        $images = $xpath->query( '//img' );
        
        if ( $images->length === 0 ) {
            return $html;
        }

        foreach ( $images as $img ) {
            $counter++;
            $src = $img->getAttribute( 'src' );
            
            if ( empty( $src ) ) {
                continue;
            }

            // Check if this is a local image
            $is_local = strpos( $src, $base_url ) !== false;
            if ( ! $is_local ) {
                continue;
            }

            $webp_url = $src . '.webp';
            $webp_path = str_replace( $base_url, $base_dir, $webp_url );
            
            // Check if WebP exists
            if ( ! file_exists( $webp_path ) ) {
                continue;
            }

            $is_critical = ( $counter <= $first_n );

            // Create picture element
            $picture = $dom->createElement( 'picture' );
            
            // Create source element for WebP
            $source = $dom->createElement( 'source' );
            $source->setAttribute( 'srcset', $webp_url );
            $source->setAttribute( 'type', 'image/webp' );
            
            // Handle srcset if present
            $srcset = $img->getAttribute( 'srcset' );
            if ( ! empty( $srcset ) ) {
                $srcset_webp = $this->convert_srcset_to_webp( $srcset, $base_url, $base_dir );
                if ( $srcset_webp ) {
                    $source->setAttribute( 'srcset', $srcset_webp );
                }
            }
            
            $picture->appendChild( $source );

            // Clone the img element
            $new_img = $img->cloneNode( true );
            
            // Clean up attributes for lazy loading
            if ( ! $is_critical && $enable_lazy ) {
                $placeholder = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
                $new_img->setAttribute( 'src', $placeholder );
                $new_img->setAttribute( 'data-src', $src );
                
                // Add lazy class
                $class = $new_img->getAttribute( 'class' );
                $class .= ' onewebp-lazy-img';
                $new_img->setAttribute( 'class', trim( $class ) );
                
                // Handle srcset for lazy
                if ( $srcset ) {
                    $new_img->setAttribute( 'data-srcset', $srcset );
                    $new_img->removeAttribute( 'srcset' );
                }
            } else {
                // Critical image - set fetchpriority
                if ( $counter === 1 ) {
                    $new_img->setAttribute( 'fetchpriority', 'high' );
                }
            }

            $picture->appendChild( $new_img );
            
            // Replace img with picture
            if ( $img->parentNode ) {
                $img->parentNode->replaceChild( $picture, $img );
            }
        }

        // Extract body content only
        $body = $dom->getElementsByTagName( 'body' )->item( 0 );
        if ( $body ) {
            $html = '';
            foreach ( $body->childNodes as $node ) {
                $html .= $dom->saveHTML( $node );
            }
            return $html;
        }

        return $dom->saveHTML();
    }

    private function convert_srcset_to_webp( $srcset, $base_url, $base_dir ) {
        $srcset_parts = explode( ',', $srcset );
        $webp_srcset = array();
        
        foreach ( $srcset_parts as $part ) {
            $part = trim( $part );
            if ( empty( $part ) ) {
                continue;
            }
            
            preg_match( '/^([^\s]+)\s*(.*)$/', $part, $matches );
            if ( empty( $matches[1] ) ) {
                continue;
            }
            
            $url = $matches[1];
            $descriptor = isset( $matches[2] ) ? ' ' . $matches[2] : '';
            
            // Check if this is a local URL
            if ( strpos( $url, $base_url ) === 0 ) {
                $path = str_replace( $base_url, $base_dir, $url );
                $webp_path = $path . '.webp';
                if ( file_exists( $webp_path ) ) {
                    $webp_url = str_replace( $base_dir, $base_url, $webp_path );
                    $webp_srcset[] = $webp_url . $descriptor;
                    continue;
                }
            }
            
            // Keep original if WebP doesn't exist
            $webp_srcset[] = $part;
        }
        
        return implode( ', ', $webp_srcset );
    }

    public function modify_image_attributes( $attr, $attachment, $size ) {
        // This is an alternative method for themes that use wp_get_attachment_image
        // Can be used alongside the buffer method
        return $attr;
    }

    public function enqueue_assets() {
        if ( get_option( 'onewebp_enable_lazyload', 1 ) ) {
            wp_enqueue_script( 
                'onewebp-frontend-js', 
                ONEWEBP_URL . 'assets/js/frontend-lazy.js', 
                array(), 
                ONEWEBP_VERSION, 
                true 
            );
        }
    }
}
