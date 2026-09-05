<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OneWebP_List_Table extends WP_List_Table {
    public function __construct() {
        parent::__construct( array( 'singular' => 'log', 'plural' => 'logs', 'ajax' => false ) );
    }

    public function get_columns() {
        return array(
            'preview'       => __( 'Preview', 'onewebp' ),
            'size_name'     => __( 'Size', 'onewebp' ),
            'original_size' => __( 'Original', 'onewebp' ),
            'webp_size'     => __( 'WebP', 'onewebp' ),
            'actions'       => __( 'Actions', 'onewebp' )
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';
        $per_page = 30;
        $current_page = $this->get_pagenum();

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );

        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.* FROM {$table} l LEFT JOIN {$wpdb->posts} p ON l.attachment_id = p.ID ORDER BY p.post_date DESC, l.id DESC LIMIT %d OFFSET %d",
            $per_page,
            ( $current_page - 1 ) * $per_page
        ), ARRAY_A );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );
        $this->_column_headers = array( $this->get_columns(), array(), array() );
    }

    public function pagination( $which ) {
        if ( 'top' === $which ) {
            $total_items = $this->get_pagination_arg( 'total_items' );
            $per_page = $this->get_pagination_arg( 'per_page' );
            $current_page = $this->get_pagenum();
            
            $start = ( $current_page - 1 ) * $per_page + 1;
            $end = min( $current_page * $per_page, $total_items );
            
            echo '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">';
            echo '<span style="color:#646970; font-size:13px;">' . sprintf( 'Showing %d-%d (total %d)', $start, $end, $total_items ) . '</span>';
            parent::pagination( $which );
            echo '</div>';
        } else {
            parent::pagination( $which );
        }
    }

    public function column_default( $item, $column_name ) {
        $upload_dir = wp_upload_dir();
        
        switch ( $column_name ) {
            case 'preview':
                $img_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['original_url'] );
                return '<img src="' . esc_url( $img_url ) . '" style="width:40px; height:40px; object-fit:cover; border:1px solid #ddd; display:inline-block; vertical-align:middle; margin:0;">';
                
            case 'size_name':
                $file_name = basename( $item['original_url'] );
                $dimensions = $this->get_image_dimensions( $item );
                $img_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['original_url'] );
                
                $output = '<strong><a href="' . esc_url( $img_url ) . '" target="_blank" style="color:#2271b1; text-decoration:none;">' . esc_html( $file_name ) . '</a></strong>';
                
                if ( $dimensions ) {
                    $output .= '<br><span style="color:#646970; font-size:11px;">' . esc_html( $dimensions ) . '</span>';
                }
                
                return $output;
                
            case 'original_size': 
                return $item['original_size'] > 0 ? size_format( $item['original_size'], 2 ) : '-';
                
            case 'webp_size': 
                $ext = strtolower( pathinfo( $item['original_url'], PATHINFO_EXTENSION ) );
                $supported_exts = array( 'jpg', 'jpeg', 'png', 'gif' );
                
                if ( $item['status'] === 'success' ) {
                    $webp_size_text = $item['webp_size'] > 0 ? size_format( $item['webp_size'], 2 ) : '-';
                    
                    if ( $item['original_size'] > 0 && $item['webp_size'] > 0 ) {
                        if ( $item['webp_size'] < $item['original_size'] ) {
                            $p = round( ( 1 - $item['webp_size'] / $item['original_size'] ) * 100, 1 );
                            $webp_size_text .= ' <span style="color:#008080; font-weight:bold; font-size:11px;">(-' . esc_html( $p ) . '%)</span>';
                        } else {
                            $p = round( ( $item['webp_size'] / $item['original_size'] - 1 ) * 100, 1 );
                            $webp_size_text .= ' <span style="color:#d63638; font-weight:bold; font-size:11px;">(+' . esc_html( $p ) . '%)</span>';
                        }
                    }
                    
                    $webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['webp_url'] );
                    return '<a href="' . esc_url( $webp_url ) . '" target="_blank" style="text-decoration:underline; color:inherit;">' . $webp_size_text . '</a>';
                    
                } else {
                    if ( ! in_array( $ext, $supported_exts ) ) {
                        return '<span style="color:#999;">' . __( 'Non-support', 'onewebp' ) . '</span>';
                    } else {
                        $base_url = admin_url( 'admin.php?page=onewebp&tab=manager' );
                        $nonce = wp_create_nonce( 'onewebp_action_' . $item['id'] );
                        $optimize_url = esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
                        return '<a href="' . $optimize_url . '" class="button button-small">' . __( 'Optimize', 'onewebp' ) . '</a>';
                    }
                }
                
            case 'actions':
                if ( $item['status'] !== 'success' ) {
                    return '';
                }
                
                $base_url = admin_url( 'admin.php?page=onewebp&tab=manager' );
                $nonce = wp_create_nonce( 'onewebp_action_' . $item['id'] );
                $webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['webp_url'] );
                
                $actions = array();
                $actions['edit'] = '<a href="' . esc_url( $base_url . '&action=edit&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '">' . __( 'Edit', 'onewebp' ) . '</a>';
                $actions['reoptimize'] = '<a href="' . esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '" onclick="return confirm(\'' . esc_js( __( 'Re-optimize with global settings?', 'onewebp' ) ) . '\');">' . __( 'Reoptimize', 'onewebp' ) . '</a>';
                $actions['remove'] = '<a href="' . esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '" style="color:#d63638;" onclick="return confirm(\'' . esc_js( __( 'Delete this WebP file?', 'onewebp' ) ) . '\');">' . __( 'Remove', 'onewebp' ) . '</a>';
                $actions['copy_url'] = '<a href="javascript:void(0);" onclick="navigator.clipboard.writeText(\'' . esc_js( $webp_url ) . '\').then(function() { var el = this; el.innerText = \'Copied!\'; setTimeout(function(){ el.innerText = \'Copy URL\'; }, 1500); }.bind(this));">' . __( 'Copy URL', 'onewebp' ) . '</a>';
                
                return implode( ' | ', $actions );
                
            default: 
                return esc_html( $item[ $column_name ] );
        }
    }
    
    private function get_image_dimensions( $item ) {
        if ( $item['attachment_id'] > 0 ) {
            $meta = wp_get_attachment_metadata( $item['attachment_id'] );
            if ( $meta && isset( $meta['width'] ) && isset( $meta['height'] ) ) {
                return $meta['width'] . 'x' . $meta['height'];
            }
        }
        
        if ( file_exists( $item['original_url'] ) ) {
            $image_info = @getimagesize( $item['original_url'] );
            if ( $image_info && isset( $image_info[0] ) && isset( $image_info[1] ) ) {
                return $image_info[0] . 'x' . $image_info[1];
            }
        }
        
        $file_name = basename( $item['original_url'] );
        if ( preg_match( '/-(\d+)x(\d+)\./', $file_name, $matches ) ) {
            return $matches[1] . 'x' . $matches[2];
        }
        
        return false;
    }
}