<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class OneWebP_List_Table extends WP_List_Table {

    public function __construct() {
        parent::__construct( array( 
            'singular' => 'log', 
            'plural'   => 'logs', 
            'ajax'     => false 
        ) );
    }

    /**
     * Get the table columns.
     *
     * @return array
     */
    public function get_columns() {
        return array(
            'cb'            => '<input type="checkbox" />',
            'preview'       => __( 'Preview', 'onewebp' ),
            'size_name'     => __( 'Size', 'onewebp' ),
            'original_size' => __( 'Original', 'onewebp' ),
            'webp_size'     => __( 'WebP', 'onewebp' ),
            'actions'       => __( 'Actions', 'onewebp' )
        );
    }

    /**
     * Get the bulk actions dropdown.
     *
     * @return array
     */
    public function get_bulk_actions() {
        return array(
            'delete'      => __( 'Delete', 'onewebp' ),
            'reoptimize'  => __( 'Reoptimize', 'onewebp' )
        );
    }

    /**
     * Prepare the items for the table.
     */
    public function prepare_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';
        $per_page = 30;
        $current_page = $this->get_pagenum();

        // Handle bulk actions.
        $this->process_bulk_action();

        // Search.
        $search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : '';
        $where = '';
        if ( ! empty( $search ) ) {
            $where = $wpdb->prepare( " WHERE original_url LIKE %s", '%' . $wpdb->esc_like( $search ) . '%' );
        }

        $total_items = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table} {$where}" );

        // Sorting.
        $orderby = isset( $_GET['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_GET['orderby'] ) ) : 'id';
        $order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'DESC';
        $order = in_array( $order, array( 'ASC', 'DESC' ), true ) ? $order : 'DESC';

        // Fetch data.
        $this->items = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
            $per_page,
            ( $current_page - 1 ) * $per_page
        ), ARRAY_A );

        $this->set_pagination_args( array(
            'total_items' => $total_items,
            'per_page'    => $per_page
        ) );
        
        $this->_column_headers = array( 
            $this->get_columns(), 
            array(), 
            array(),
            $this->get_primary_column_name()
        );
    }

    /**
     * Render the checkbox for each row.
     *
     * @param array $item The current item.
     * @return string
     */
    public function column_cb( $item ) {
        return sprintf(
            '<input type="checkbox" name="log_ids[]" value="%s" />',
            esc_attr( $item['id'] )
        );
    }

    /**
     * Handle the bulk actions.
     */
    public function process_bulk_action() {
        global $wpdb;
        $table = $wpdb->prefix . 'onewebp_logs';

        if ( empty( $_POST['log_ids'] ) || empty( $_POST['action'] ) || $_POST['action'] === '-1' ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have permission to perform this action.', 'onewebp' ) );
        }

        $log_ids = array_map( 'absint', (array) $_POST['log_ids'] );
        $action  = sanitize_text_field( wp_unslash( $_POST['action'] ) );
        $ids_placeholder = implode( ',', array_fill( 0, count( $log_ids ), '%d' ) );

        if ( $action === 'delete' ) {
            // Delete files.
            $records = $wpdb->get_results( $wpdb->prepare( "SELECT webp_url FROM {$table} WHERE id IN ($ids_placeholder)", $log_ids ) );
            foreach ( $records as $record ) {
                if ( ! empty( $record->webp_url ) && file_exists( $record->webp_url ) ) {
                    @unlink( $record->webp_url );
                }
            }
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ($ids_placeholder)", $log_ids ) );
            
        } elseif ( $action === 'reoptimize' ) {
            // Handle batch reoptimization.
            if ( ! class_exists( 'OneWebP_Converter' ) ) {
                require_once ONEWEBP_DIR . 'includes/class-onewebp-converter.php';
            }
            $converter = new OneWebP_Converter();
            $records = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE id IN ($ids_placeholder)", $log_ids ) );

            foreach ( $records as $record ) {
                if ( file_exists( $record->webp_url ) ) {
                    @unlink( $record->webp_url );
                }
                $result = $converter->execute_conversion( $record->original_url, $record->webp_url );
                if ( $result['success'] ) {
                    $webp_size = file_exists( $record->webp_url ) ? filesize( $record->webp_url ) : 0;
                    $wpdb->update( $table, array( 
                        'status' => 'success', 
                        'webp_size' => $webp_size,
                        'is_downscaled' => $result['is_downscaled'] ? 1 : 0 
                    ), array( 'id' => $record->id ) );
                } else {
                    $wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $record->id ) );
                }
            }
        }

        // Redirect to avoid resubmission on refresh.
        wp_safe_redirect( add_query_arg( 'msg', 'bulk_done', admin_url( 'admin.php?page=onewebp&tab=manager' ) ) );
        exit;
    }

    /**
     * Handle the default column rendering.
     *
     * @param array  $item        The current item.
     * @param string $column_name The column name.
     * @return string
     */
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
                
                if ( $ext === 'webp' ) {
                    $size_text = $item['original_size'] > 0 ? size_format( $item['original_size'], 2 ) : '-';
                    return $size_text . ' <span style="color:#2271b1; font-weight:bold; font-size:11px;">(' . esc_html__( 'Already WebP', 'onewebp' ) . ')</span>';
                }

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
                        return '<span style="color:#999; font-style:italic;">' . esc_html__( 'Unsupported Format', 'onewebp' ) . '</span>';
                    } else {
                        $base_url = admin_url( 'admin.php?page=onewebp&tab=manager' );
                        $nonce = wp_create_nonce( 'onewebp_action_' . $item['id'] );
                        $optimize_url = esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
                        return '<a href="' . $optimize_url . '" class="button button-small">' . esc_html__( 'Retry', 'onewebp' ) . '</a>';
                    }
                }
                
            case 'actions':
                $ext = strtolower( pathinfo( $item['original_url'], PATHINFO_EXTENSION ) );
                $base_url = admin_url( 'admin.php?page=onewebp&tab=manager' );
                $nonce = wp_create_nonce( 'onewebp_action_' . $item['id'] );
                
                if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp' ) ) ) {
                    $remove_url = esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce );
                    return '<a href="' . $remove_url . '" style="color:#d63638;" onclick="return confirm(\'' . esc_js( __( 'Delete record?', 'onewebp' ) ) . '\');">' . esc_html__( 'Remove', 'onewebp' ) . '</a>';
                }

                if ( $ext === 'webp' || $item['status'] === 'success' ) {
                    $webp_url = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], $item['webp_url'] );
                    
                    $actions = array();
                    $actions['edit'] = '<a href="' . esc_url( $base_url . '&action=edit&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '">' . esc_html__( 'Edit', 'onewebp' ) . '</a>';
                    $actions['reoptimize'] = '<a href="' . esc_url( $base_url . '&action=reoptimize&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '">' . esc_html__( 'Reoptimize', 'onewebp' ) . '</a>';
                    $actions['remove'] = '<a href="' . esc_url( $base_url . '&action=remove&log_id=' . $item['id'] . '&_wpnonce=' . $nonce ) . '" style="color:#d63638;" onclick="return confirm(\'' . esc_js( __( 'Delete this WebP file?', 'onewebp' ) ) . '\');">' . esc_html__( 'Remove', 'onewebp' ) . '</a>';
                    $actions['copy_url'] = '<a href="#" class="onewebp-copy-url" data-url="' . esc_attr( $webp_url ) . '">' . esc_html__( 'Copy URL', 'onewebp' ) . '</a>';
                    
                    return implode( ' | ', $actions );
                }
                
                return '';
                
            default: 
                return esc_html( isset( $item[ $column_name ] ) ? $item[ $column_name ] : '' );
        }
    }
    
    private function get_image_dimensions( $item ) {
        if ( ! empty( $item['attachment_id'] ) ) {
            $meta = wp_get_attachment_metadata( $item['attachment_id'] );
            if ( $meta && isset( $meta['width'] ) && isset( $meta['height'] ) ) {
                return $meta['width'] . 'x' . $meta['height'];
            }
        }
        
        // Try to get dimensions from filename.
        $file_name = basename( $item['original_url'] );
        if ( preg_match( '/-(\d+)x(\d+)\./', $file_name, $matches ) ) {
            return $matches[1] . 'x' . $matches[2];
        }
        
        return false;
    }
}
