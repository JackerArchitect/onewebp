<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class OneWebP_Converter {
    public function __construct() {
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_upload' ), 10, 2 );
    }

    public function process_upload( $metadata, $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path ) {
            return $metadata;
        }
        
        $file_dir  = dirname( $file_path );
        $scope = get_option( 'onewebp_conversion_scope', 'all' );
        $allowed_types = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) );

        $mime = get_post_mime_type( $attachment_id );
        $type_key = str_replace( 'image/', '', $mime );
        if ( $type_key === 'jpg' ) {
            $type_key = 'jpeg';
        }
        
        if ( ! in_array( $type_key, $allowed_types ) ) {
            return $metadata;
        }

        if ( $scope === 'original' || $scope === 'all' ) {
            $this->convert_and_log( $file_path, $file_path . '.webp', $attachment_id, 'original' );
        }

        if ( $scope === 'thumbnails' || $scope === 'all' ) {
            if ( ! empty( $metadata['sizes'] ) ) {
                foreach ( $metadata['sizes'] as $size_name => $size_data ) {
                    $size_path = $file_dir . '/' . $size_data['file'];
                    if ( file_exists( $size_path ) ) {
                        $this->convert_and_log( $size_path, $size_path . '.webp', $attachment_id, $size_name );
                    }
                }
            }
        }
        return $metadata;
    }

    private function convert_and_log( $source_path, $dest_path, $attachment_id, $size_name ) {
        global $wpdb;
        
        $original_size = file_exists( $source_path ) ? filesize( $source_path ) : 0;
        $result = $this->execute_conversion( $source_path, $dest_path );
        $status = $result['success'] ? 'success' : 'failed';
        $webp_size = $result['success'] && file_exists( $dest_path ) ? filesize( $dest_path ) : 0;

        $wpdb->insert( $wpdb->prefix . 'onewebp_logs', array(
            'attachment_id' => $attachment_id,
            'image_type'    => 'local',
            'size_name'     => $size_name,
            'original_url'  => $source_path,
            'webp_url'      => $dest_path,
            'original_size' => $original_size,
            'webp_size'     => $webp_size,
            'is_downscaled' => ! empty( $result['is_downscaled'] ) ? 1 : 0,
            'status'        => $status
        ));
    }

    public function execute_conversion( $source_path, $dest_path, $custom_quality = null, $custom_max_res = null, $custom_allow_oversized = null ) {
        if ( ! file_exists( $source_path ) ) {
            return array( 'success' => false, 'error' => 'Source file not found' );
        }

        // Check if destination is writable
        $dest_dir = dirname( $dest_path );
        if ( ! is_writable( $dest_dir ) ) {
            return array( 'success' => false, 'error' => 'Destination directory not writable' );
        }

        $quality = $this->sanitize_quality( 
            $custom_quality !== null ? intval( $custom_quality ) : intval( get_option( 'onewebp_quality', 82 ) ) 
        );
        
        $max_res = $custom_max_res !== null ? intval( $custom_max_res ) : intval( get_option( 'onewebp_max_resolution', 3000 ) );
        $allow_big = $custom_allow_oversized !== null ? intval( $custom_allow_oversized ) : get_option( 'onewebp_allow_oversized', 0 );

        // Check and increase memory if needed
        $this->ensure_memory_for_image( $source_path );

        list( $orig_w, $orig_h, $orig_type ) = @getimagesize( $source_path );
        if ( ! $orig_w ) {
            return array( 'success' => false, 'error' => 'Failed to get image dimensions' );
        }

        $target_w = $orig_w;
        $target_h = $orig_h;
        $is_downscaled = false;

        // Downscale if needed
        if ( ! $allow_big && ( $orig_w > $max_res || $orig_h > $max_res ) ) {
            $is_downscaled = true;
            if ( $orig_w > $orig_h ) {
                $target_w = $max_res;
                $target_h = round( $orig_h * ( $max_res / $orig_w ) );
            } else {
                $target_h = $max_res;
                $target_w = round( $orig_w * ( $max_res / $orig_h ) );
            }
        }

        // Check for animated GIF
        if ( $orig_type == IMAGETYPE_GIF && $this->is_animated_gif( $source_path ) ) {
            return array( 'success' => false, 'error' => 'Animated GIF not supported' );
        }

        // Load source image
        $source_image = $this->load_image( $source_path, $orig_type );
        if ( ! $source_image ) {
            return array( 'success' => false, 'error' => 'Failed to load image' );
        }

        // Create new image
        $new_image = imagecreatetruecolor( $target_w, $target_h );
        if ( ! $new_image ) {
            imagedestroy( $source_image );
            return array( 'success' => false, 'error' => 'Failed to create image canvas' );
        }

        // Handle transparency
        $this->handle_transparency( $new_image, $orig_type );

        // Resample
        imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h );
        
        // Save as WebP
        $success = imagewebp( $new_image, $dest_path, $quality );
        
        // Clean up
        imagedestroy( $source_image );
        imagedestroy( $new_image );

        return array( 
            'success' => $success, 
            'is_downscaled' => $is_downscaled 
        );
    }

    private function sanitize_quality( $quality ) {
        $quality = intval( $quality );
        if ( $quality < 50 ) return 50;
        if ( $quality > 100 ) return 100;
        return $quality;
    }

    private function load_image( $source_path, $type ) {
        switch ( $type ) {
            case IMAGETYPE_JPEG:
                return imagecreatefromjpeg( $source_path );
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng( $source_path );
                if ( $image ) {
                    imagepalettetotruecolor( $image );
                    imagealphablending( $image, true );
                    imagesavealpha( $image, true );
                }
                return $image;
            case IMAGETYPE_GIF:
                return imagecreatefromgif( $source_path );
            case IMAGETYPE_WEBP:
                return imagecreatefromwebp( $source_path );
            default:
                return false;
        }
    }

    private function handle_transparency( $image, $type ) {
        if ( $type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF || $type == IMAGETYPE_WEBP ) {
            imagecolortransparent( $image, imagecolorallocatealpha( $image, 0, 0, 0, 127 ) );
            imagealphablending( $image, false );
            imagesavealpha( $image, true );
        }
    }

    private function is_animated_gif( $filename ) {
        if ( ! function_exists( 'file_get_contents' ) ) {
            return false;
        }
        
        $contents = file_get_contents( $filename );
        if ( ! $contents ) {
            return false;
        }
        
        // Check for GIF animation
        $frames = preg_match_all( '/\x00\x21\xF9\x04/', $contents );
        return $frames > 1;
    }

    private function ensure_memory_for_image( $source_path ) {
        $image_size = file_exists( $source_path ) ? filesize( $source_path ) : 0;
        $required_memory = $image_size * 6; // 6x for safety
        
        $memory_limit = ini_get( 'memory_limit' );
        if ( $memory_limit === '-1' ) {
            return;
        }
        
        $memory_bytes = wp_convert_hr_to_bytes( $memory_limit );
        if ( $memory_bytes > 0 && $required_memory > $memory_bytes * 0.7 ) {
            $new_limit = ceil( $required_memory / 1024 / 1024 ) + 64;
            @ini_set( 'memory_limit', $new_limit . 'M' );
        }
    }
}
