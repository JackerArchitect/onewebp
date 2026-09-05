<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class OneWebP_Converter {
    public function __construct() {
        // Hook into WordPress upload process
        add_filter( 'wp_generate_attachment_metadata', array( $this, 'process_upload' ), 10, 2 );
    }

    public function process_upload( $metadata, $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        $file_dir  = dirname( $file_path );
        $scope = get_option( 'onewebp_conversion_scope', 'all' );
        $allowed_types = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) );

        // Check if this image type is allowed
        $mime = get_post_mime_type( $attachment_id );
        $type_key = str_replace( 'image/', '', $mime );
        if ( $type_key === 'jpg' ) $type_key = 'jpeg';
        if ( ! in_array( $type_key, $allowed_types ) ) {
            return $metadata; // Skip this image type
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
        $original_size = filesize( $source_path );
        
        // Use global settings (no custom params passed)
        $result = $this->execute_conversion( $source_path, $dest_path );

        $status = $result['success'] ? 'success' : 'failed';
        $webp_size = $result['success'] ? filesize( $dest_path ) : 0;

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

    /**
     * Core conversion function.
     * Accepts optional custom parameters for manual "Edit" actions.
     * If custom params are null, it falls back to global options.
     */
    public function execute_conversion( $source_path, $dest_path, $custom_quality = null, $custom_max_res = null, $custom_allow_oversized = null ) {
        if ( ! file_exists( $source_path ) ) return array( 'success' => false );

        // Determine settings: Custom params take precedence, otherwise use global options
        $quality   = $custom_quality !== null ? intval( $custom_quality ) : intval( get_option( 'onewebp_quality', 82 ) );
        $max_res   = $custom_max_res !== null ? intval( $custom_max_res ) : intval( get_option( 'onewebp_max_resolution', 3000 ) );
        $allow_big = $custom_allow_oversized !== null ? intval( $custom_allow_oversized ) : get_option( 'onewebp_allow_oversized', 0 );

        list( $orig_w, $orig_h, $orig_type ) = @getimagesize( $source_path );
        if ( ! $orig_w ) return array( 'success' => false );

        $target_w = $orig_w;
        $target_h = $orig_h;
        $is_downscaled = false;

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

        $source_image = null;
        if ( $orig_type == IMAGETYPE_JPEG ) {
            $source_image = imagecreatefromjpeg( $source_path );
        } elseif ( $orig_type == IMAGETYPE_PNG ) {
            $source_image = imagecreatefrompng( $source_path );
            imagepalettetotruecolor( $source_image );
            imagealphablending( $source_image, true );
            imagesavealpha( $source_image, true );
        } elseif ( $orig_type == IMAGETYPE_GIF ) {
            $source_image = imagecreatefromgif( $source_path );
        } else {
            return array( 'success' => false );
        }

        if ( ! $source_image ) return array( 'success' => false );

        $new_image = imagecreatetruecolor( $target_w, $target_h );
        
        if ( $orig_type == IMAGETYPE_PNG || $orig_type == IMAGETYPE_GIF ) {
            imagecolortransparent( $new_image, imagecolorallocatealpha( $new_image, 0, 0, 0, 127 ) );
            imagealphablending( $new_image, false );
            imagesavealpha( $new_image, true );
        }

        imagecopyresampled( $new_image, $source_image, 0, 0, 0, 0, $target_w, $target_h, $orig_w, $orig_h );
        $success = imagewebp( $new_image, $dest_path, $quality );

        imagedestroy( $source_image );
        imagedestroy( $new_image );

        return array( 'success' => $success, 'is_downscaled' => $is_downscaled );
    }
}
