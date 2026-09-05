<?php
/**
 * Plugin Name:       OneWebP
 * Plugin URI:        https://jackerteo.com/plugin/onewebp
 * Description:       100% free, unlimited local WebP converter with smart lazy loading. Zero API, your images never leave your server.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Jacker Architect
 * Author URI:        https://github.com/JackerArchitect
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       onewebp
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ONEWEBP_VERSION', '1.0.0' );
define( 'ONEWEBP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ONEWEBP_URL', plugin_dir_url( __FILE__ ) );

require_once ONEWEBP_DIR . 'includes/class-onewebp-core.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-converter.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-smart-batch.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-frontend.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-external.php';
require_once ONEWEBP_DIR . 'includes/class-onewebp-list-table.php';

register_activation_hook( __FILE__, array( 'OneWebP_Core', 'activate' ) );

add_action( 'plugins_loaded', 'onewebp_init' );
function onewebp_init() {
    OneWebP_Core::get_instance()->init();
}

add_action( 'admin_menu', 'onewebp_admin_menu' );
function onewebp_admin_menu() {
    add_menu_page( 
        __( 'OneWebP', 'onewebp' ), 
        __( 'OneWebP', 'onewebp' ), 
        'manage_options', 
        'onewebp', 
        'onewebp_render_page', 
        ONEWEBP_URL . 'assets/images/favicon_s.png?v=2', 
        59 
    );
}

add_action( 'admin_init', 'onewebp_register_settings' );
function onewebp_register_settings() {
    register_setting( 'onewebp_settings', 'onewebp_quality', array( 'default' => 82, 'sanitize_callback' => 'onewebp_sanitize_quality' ));
    register_setting( 'onewebp_settings', 'onewebp_max_resolution', array( 'default' => 3000 ) );
    register_setting( 'onewebp_settings', 'onewebp_allow_oversized', array( 'default' => 0 ) );
    register_setting( 'onewebp_settings', 'onewebp_first_n_direct', array( 'default' => 3 ) );
    register_setting( 'onewebp_settings', 'onewebp_conversion_scope', array( 'default' => 'all' ) );
    register_setting( 'onewebp_settings', 'onewebp_enable_external', array( 'default' => 0 ) );
    register_setting( 'onewebp_settings', 'onewebp_enable_lazyload', array( 'default' => 1 ) );
    register_setting( 'onewebp_settings', 'onewebp_allowed_types', array( 'default' => array( 'jpeg', 'png' ) ) );
}

function onewebp_sanitize_quality( $input ) {
    $input = intval( $input );
    if ( $input < 50 ) return 50;
    if ( $input > 100 ) return 100;
    return $input;
}

add_action( 'admin_init', 'onewebp_handle_manager_actions' );
function onewebp_handle_manager_actions() {
    if ( ! isset( $_GET['page'] ) || $_GET['page'] !== 'onewebp' ) return;
    if ( ! isset( $_GET['tab'] ) || $_GET['tab'] !== 'manager' ) return;
    if ( ! isset( $_GET['action'] ) || ! isset( $_GET['log_id'] ) ) return;

    $action = sanitize_text_field( $_GET['action'] );
    $log_id = intval( $_GET['log_id'] );
    $nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';

    if ( ! wp_verify_nonce( $nonce, 'onewebp_action_' . $log_id ) ) {
        wp_die( __( 'Security check failed.', 'onewebp' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'onewebp_logs';
    $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $log_id ) );

    if ( ! $log ) wp_die( __( 'Record not found.', 'onewebp' ) );

    $base_redirect = admin_url( 'admin.php?page=onewebp&tab=manager' );

    if ( $action === 'remove' ) {
        if ( file_exists( $log->webp_url ) ) @unlink( $log->webp_url );
        $wpdb->delete( $table, array( 'id' => $log_id ) );
        wp_redirect( $base_redirect . '&msg=removed' );
        exit;
    }

    if ( $action === 'reoptimize' ) {
        if ( file_exists( $log->webp_url ) ) @unlink( $log->webp_url );
        $converter = new OneWebP_Converter();
        $result = $converter->execute_conversion( $log->original_url, $log->webp_url );
        if ( $result['success'] ) {
            $wpdb->update( $table, array( 'webp_size' => filesize( $log->webp_url ), 'status' => 'success', 'is_downscaled' => $result['is_downscaled'] ? 1 : 0 ), array( 'id' => $log_id ) );
            wp_redirect( $base_redirect . '&msg=reoptimized' );
        } else {
            $wpdb->update( $table, array( 'status' => 'failed' ), array( 'id' => $log_id ) );
            wp_redirect( $base_redirect . '&msg=failed' );
        }
        exit;
    }

    if ( $action === 'edit' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['onewebp_edit_nonce'] ) ) {
        if ( ! wp_verify_nonce( $_POST['onewebp_edit_nonce'], 'onewebp_edit_' . $log_id ) ) wp_die( __( 'Security check failed.', 'onewebp' ) );
        $custom_quality = intval( $_POST['custom_quality'] );
        $custom_max_res = intval( $_POST['custom_max_res'] );
        if ( $custom_quality < 50 ) $custom_quality = 50;
        if ( $custom_quality > 100 ) $custom_quality = 100;
        if ( $custom_max_res < 100 ) $custom_max_res = 100;

        if ( file_exists( $log->webp_url ) ) @unlink( $log->webp_url );
        $converter = new OneWebP_Converter();
        $result = $converter->execute_conversion( $log->original_url, $log->webp_url, $custom_quality, $custom_max_res, 0 );
        if ( $result['success'] ) {
            $wpdb->update( $table, array( 'webp_size' => filesize( $log->webp_url ), 'status' => 'success', 'is_downscaled' => $result['is_downscaled'] ? 1 : 0 ), array( 'id' => $log_id ) );
            wp_redirect( $base_redirect . '&msg=edited' );
        } else {
            wp_redirect( $base_redirect . '&msg=failed' );
        }
        exit;
    }
}

function onewebp_render_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'dashboard';
    
    if ( isset( $_GET['msg'] ) ) {
        $msg = sanitize_text_field( $_GET['msg'] );
        $messages = array(
            'removed' => __( 'WebP file removed successfully.', 'onewebp' ),
            'reoptimized' => __( 'Image re-optimized with global settings.', 'onewebp' ),
            'edited' => __( 'Image re-optimized with custom settings.', 'onewebp' ),
            'failed' => __( 'Conversion failed.', 'onewebp' )
        );
        if ( isset( $messages[ $msg ] ) ) echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $msg ] ) . '</p></div>';
    }
    ?>
    <style>
        /* Dashboard Report Grid */
        .onewebp-report-grid { display: flex; gap: 15px; margin: 20px 0; flex-wrap: wrap; }
        .onewebp-report-box { flex: 1; min-width: 140px; background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px 10px; text-align: center; box-shadow: 0 1px 1px rgba(0,0,0,.04); }
        .onewebp-report-num { display: block; font-size: 32px; font-weight: bold; color: #1d2327; line-height: 1.2; margin-bottom: 5px; }
        .onewebp-report-label { display: block; font-size: 12px; color: #646970; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
        
        /* Progress Bar - 100% turns teal with white text */
        .onewebp-progress-container { background: #f0f0f1; border-radius: 4px; height: 30px; margin: 20px 0; overflow: hidden; position: relative; border: 1px solid #c3c4c7; }
        .onewebp-progress-fill { background: #0073aa; height: 100%; width: 0%; transition: width 0.3s ease, background-color 0.3s ease; }
        .onewebp-progress-fill.completed { background: #008080 !important; }
        .onewebp-progress-text { position: absolute; width: 100%; text-align: center; top: 0; line-height: 30px; font-size: 13px; font-weight: bold; color: #1d2327; transition: color 0.3s ease; }
        .onewebp-progress-text.completed { color: #ffffff !important; }
        
        /* Footer */
        .onewebp-footer { margin-top: 40px; padding: 20px; background: #fff; border: 1px solid #c3c4c7; border-left: 4px solid #0073aa; text-align: center; border-radius: 4px; }
        .onewebp-coffee-btn { display: inline-block; margin-left: 10px; padding: 6px 12px; background: #ff8c00; color: #fff !important; border-radius: 3px; text-decoration: none !important; font-weight: bold; font-size: 13px; }
        .onewebp-coffee-btn:hover { background: #e67e00; color: #fff !important; }

        /* Manager Table Fixes */
        .wp-list-table .column-preview, .wp-list-table .column-size_name, .wp-list-table .column-original_size, .wp-list-table .column-webp_size, .wp-list-table .column-actions { float: none !important; display: table-cell !important; vertical-align: middle !important; }
        .wp-list-table .column-preview { width: 80px !important; text-align: center; }
        .wp-list-table .column-size_name { width: 180px !important; }
        .wp-list-table .column-original_size { width: 120px !important; }
        .wp-list-table .column-webp_size { width: 180px !important; }
        .wp-list-table .column-actions { width: 180px !important; }
        .wp-list-table td.column-preview img { float: none !important; display: inline-block !important; vertical-align: middle !important; margin: 0 auto !important; }
        .wp-list-table td { vertical-align: middle !important; padding: 12px 8px !important; }
    </style>

    <div class="wrap">
        <h1><?php esc_html_e( 'OneWebP Optimizer', 'onewebp' ); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="?page=onewebp&tab=dashboard" class="nav-tab <?php echo $tab === 'dashboard' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Dashboard', 'onewebp' ); ?></a>
            <a href="?page=onewebp&tab=settings" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'onewebp' ); ?></a>
            <a href="?page=onewebp&tab=manager" class="nav-tab <?php echo $tab === 'manager' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Image Manager', 'onewebp' ); ?></a>
        </h2>

        <div class="onewebp-content">
            <?php if ( $tab === 'dashboard' ) : ?>
                
                <!-- Dashboard Report Grid -->
                <div class="onewebp-report-grid">
                    <div class="onewebp-report-box">
                        <span class="onewebp-report-num" id="stat-total">0</span>
                        <span class="onewebp-report-label"><?php esc_html_e( 'Total Images', 'onewebp' ); ?></span>
                    </div>
                    <div class="onewebp-report-box">
                        <span class="onewebp-report-num" id="stat-converted" style="color:#008080;">0</span>
                        <span class="onewebp-report-label"><?php esc_html_e( 'Converted', 'onewebp' ); ?></span>
                    </div>
                    <div class="onewebp-report-box">
                        <span class="onewebp-report-num" id="stat-pending" style="color:#dba617;">0</span>
                        <span class="onewebp-report-label"><?php esc_html_e( 'Pending', 'onewebp' ); ?></span>
                    </div>
                    <div class="onewebp-report-box">
                        <span class="onewebp-report-num" id="stat-failed" style="color:#d63638;">0</span>
                        <span class="onewebp-report-label"><?php esc_html_e( 'Failed / Non-convertible', 'onewebp' ); ?></span>
                    </div>
                </div>

                <!-- Always Visible Progress Bar -->
                <div class="onewebp-progress-container">
                    <div class="onewebp-progress-fill" id="main-progress-bar"></div>
                    <div class="onewebp-progress-text" id="main-progress-text">0%</div>
                </div>

                <!-- Stats & Controls -->
                <div class="card" style="border:none; box-shadow:none; padding:0; background:transparent;">
                    <div style="background:#f6f7f7; padding:15px; border-radius:4px; margin-bottom:20px; border:1px solid #c3c4c7;">
                        <p style="margin:0; font-size:15px;">
                            <?php esc_html_e( 'Total Space Saved:', 'onewebp' ); ?> 
                            <strong id="saved-space" style="color:#008080; font-size:18px; margin-left:5px;">0 Bytes</strong>
                        </p>
                    </div>
                    <p>
                        <button id="start-optimize-btn" class="button button-primary button-hero" disabled style="opacity: 0.6;"><?php esc_html_e( 'Scanning...', 'onewebp' ); ?></button>
                        <button id="stop-optimize-btn" class="button button-link-delete" style="display:none; margin-left:10px;"><?php esc_html_e( 'Pause', 'onewebp' ); ?></button>
                        <button id="rescan-btn" class="button button-secondary" style="margin-left:10px;"><?php esc_html_e( 'Rescan Library', 'onewebp' ); ?></button>
                    </p>
                </div>

                <!-- Footer Support -->
                <div class="onewebp-footer">
                    <p style="margin:0 0 10px 0; font-size:14px; color:#50575e;">
                        <?php esc_html_e( 'Support open source original creation. Create sustainable, high-tech plugins.', 'onewebp' ); ?>
                        <br>
                        <?php esc_html_e( 'Buy me a coffee to keep this project alive!', 'onewebp' ); ?>
                        <a href="https://jackerteo.com/plugin/onewebp" target="_blank" class="onewebp-coffee-btn">☕ <?php esc_html_e( 'Buy me a coffee', 'onewebp' ); ?></a>
                    </p>
                </div>

            <?php elseif ( $tab === 'settings' ) : ?>
                <form method="post" action="options.php">
                    <?php settings_fields( 'onewebp_settings' ); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php esc_html_e( 'WebP Quality', 'onewebp' ); ?></th>
                            <td><input type="number" name="onewebp_quality" value="<?php echo esc_attr( get_option( 'onewebp_quality', 82 ) ); ?>" class="small-text" min="50" max="100"><p class="description"><?php esc_html_e( '(Recommended: 82). Range: 50-100.', 'onewebp' ); ?></p></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Max Resolution (px)', 'onewebp' ); ?></th>
                            <td><input type="number" name="onewebp_max_resolution" value="<?php echo esc_attr( get_option( 'onewebp_max_resolution', 3000 ) ); ?>" class="small-text" min="800"><p class="description"><?php esc_html_e( '(Recommended: 3000).', 'onewebp' ); ?></p></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Allow Oversized Images', 'onewebp' ); ?></th>
                            <td><label><input type="checkbox" name="onewebp_allow_oversized" value="1" <?php checked( get_option( 'onewebp_allow_oversized' ), 1 ); ?>> <?php esc_html_e( 'Disable automatic downscaling.', 'onewebp' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'First N Direct Load', 'onewebp' ); ?></th>
                            <td><input type="number" name="onewebp_first_n_direct" value="<?php echo esc_attr( get_option( 'onewebp_first_n_direct', 3 ) ); ?>" class="small-text" min="0" max="10"><p class="description"><?php esc_html_e( '(Recommended: 3).', 'onewebp' ); ?></p></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Conversion Scope', 'onewebp' ); ?></th>
                            <td>
                                <label><input type="radio" name="onewebp_conversion_scope" value="all" <?php checked( get_option( 'onewebp_conversion_scope', 'all' ), 'all' ); ?>> <?php esc_html_e( 'Original + All Thumbnails (Recommended)', 'onewebp' ); ?></label><br>
                                <label><input type="radio" name="onewebp_conversion_scope" value="original" <?php checked( get_option( 'onewebp_conversion_scope' ), 'original' ); ?>> <?php esc_html_e( 'Original Image Only', 'onewebp' ); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Image Types to Convert', 'onewebp' ); ?></th>
                            <td>
                                <?php $allowed = get_option( 'onewebp_allowed_types', array( 'jpeg', 'png' ) ); ?>
                                <label><input type="checkbox" name="onewebp_allowed_types[]" value="jpeg" <?php checked( in_array( 'jpeg', $allowed ) ); ?>> JPEG / JPG</label><br>
                                <label><input type="checkbox" name="onewebp_allowed_types[]" value="png" <?php checked( in_array( 'png', $allowed ) ); ?>> PNG</label><br>
                                <label><input type="checkbox" name="onewebp_allowed_types[]" value="gif" <?php checked( in_array( 'gif', $allowed ) ); ?>> GIF</label>
                                <div style="margin-top: 10px; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638;">
                                    <strong><?php esc_html_e( 'Format Support Notes:', 'onewebp' ); ?></strong><br>
                                    ✅ <strong>JPEG/JPG → WebP:</strong> <?php esc_html_e( 'Fully supported. Best compression results.', 'onewebp' ); ?><br>
                                    ✅ <strong>PNG → WebP:</strong> <?php esc_html_e( 'Fully supported. Transparent background is preserved.', 'onewebp' ); ?><br>
                                    ⚠️ <strong>GIF → WebP:</strong> <?php esc_html_e( 'Static GIF only. Animated GIFs will become static (first frame only). GD library does not support GIF animation.', 'onewebp' ); ?><br>
                                    ❌ <strong>SVG:</strong> <?php esc_html_e( 'Not supported. SVG is a vector format and cannot be converted by GD library.', 'onewebp' ); ?><br>
                                    ❌ <strong>BMP/TIFF:</strong> <?php esc_html_e( 'Not recommended. Very large file sizes may cause memory issues.', 'onewebp' ); ?><br>
                                    ❌ <strong>WebP:</strong> <?php esc_html_e( 'Already WebP. No conversion needed.', 'onewebp' ); ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Smart Lazy Load', 'onewebp' ); ?></th>
                            <td><label><input type="checkbox" name="onewebp_enable_lazyload" value="1" <?php checked( get_option( 'onewebp_enable_lazyload', 1 ), 1 ); ?>> <?php esc_html_e( 'Enable queue-based lazy loading.', 'onewebp' ); ?></label></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'External Images', 'onewebp' ); ?></th>
                            <td><label><input type="checkbox" name="onewebp_enable_external" value="1" <?php checked( get_option( 'onewebp_enable_external' ), 0 ); ?>> <?php esc_html_e( 'Download and convert external images.', 'onewebp' ); ?></label></td>
                        </tr>
                    </table>
                    <?php submit_button( __( 'Save Settings', 'onewebp' ) ); ?>
                </form>
                <hr>
                <h2><?php esc_html_e( 'Reset Options', 'onewebp' ); ?></h2>
                <form method="post" action="" style="display: inline-block; margin-right: 10px;">
                    <?php wp_nonce_field( 'onewebp_reset_settings' ); ?>
                    <input type="hidden" name="onewebp_reset_action" value="reset_settings">
                    <input type="submit" class="button" value="<?php esc_attr_e( 'Reset to Default Settings', 'onewebp' ); ?>" onclick="return confirm('Are you sure?');">
                </form>
                <form method="post" action="" style="display: inline-block;">
                    <?php wp_nonce_field( 'onewebp_reset_data' ); ?>
                    <input type="hidden" name="onewebp_reset_action" value="reset_data">
                    <input type="submit" class="button button-link-delete" value="<?php esc_attr_e( 'Clear All Data & WebP Files', 'onewebp' ); ?>" onclick="return confirm('Are you sure?');">
                </form>

            <?php elseif ( $tab === 'manager' ) : ?>
                <?php 
                if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['log_id'] ) ) {
                    global $wpdb;
                    $log_id = intval( $_GET['log_id'] );
                    $log = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}onewebp_logs WHERE id = %d", $log_id ) );
                    if ( $log ) :
                        $img_url = str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $log->original_url );
                        ?>
                        <div class="card" style="max-width: 600px; padding: 20px; margin-top: 20px;">
                            <h2><?php esc_html_e( 'Edit Conversion Settings (Custom)', 'onewebp' ); ?></h2>
                            <img src="<?php echo esc_url( $img_url ); ?>" style="max-width: 100%; height: auto; margin-bottom: 20px; border: 1px solid #ddd;">
                            <form method="post">
                                <?php wp_nonce_field( 'onewebp_edit_' . $log_id, 'onewebp_edit_nonce' ); ?>
                                <table class="form-table">
                                    <tr><th><?php esc_html_e( 'Custom Quality (50-100)', 'onewebp' ); ?></th><td><input type="number" name="custom_quality" value="82" min="50" max="100" class="small-text" required></td></tr>
                                    <tr><th><?php esc_html_e( 'Custom Max Resolution (px)', 'onewebp' ); ?></th><td><input type="number" name="custom_max_res" value="3000" min="100" class="small-text" required></td></tr>
                                </table>
                                <div class="notice notice-info" style="margin: 15px 0; padding: 10px;"><p style="margin:0;"><strong>Note:</strong> These settings apply ONLY to this image.</p></div>
                                <input type="submit" class="button button-primary" value="<?php esc_attr_e( 'Apply & Re-convert', 'onewebp' ); ?>">
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=onewebp&tab=manager' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'onewebp' ); ?></a>
                            </form>
                        </div>
                        <?php
                    endif;
                } else {
                    $list_table = new OneWebP_List_Table(); 
                    $list_table->prepare_items(); 
                    echo '<form method="post">';
                    $list_table->display(); 
                    echo '</form>';
                }
                ?>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

add_action( 'admin_enqueue_scripts', 'onewebp_admin_assets' );
function onewebp_admin_assets( $hook ) {
    if ( 'toplevel_page_onewebp' !== $hook ) return;
    wp_enqueue_style( 'onewebp-admin-css', ONEWEBP_URL . 'assets/css/admin-style.css', array(), ONEWEBP_VERSION );
    
    $js_version = filemtime( ONEWEBP_DIR . 'assets/js/admin-dashboard.js' );
    wp_enqueue_script( 'onewebp-admin-js', ONEWEBP_URL . 'assets/js/admin-dashboard.js', array( 'jquery' ), $js_version, true );
    
    wp_localize_script( 'onewebp-admin-js', 'onewebp_vars', array(
        'nonce' => wp_create_nonce( 'onewebp_nonce' ),
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'text_completed' => __( 'Optimization Complete!', 'onewebp' ),
        'text_paused' => __( 'Paused', 'onewebp' )
    ));
}

// Disk Space Monitor
add_action( 'admin_notices', 'onewebp_check_disk_space_notice' );
function onewebp_check_disk_space_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $upload_dir = wp_upload_dir();
    if ( ! function_exists( 'disk_free_space' ) ) return;
    $free_space = @disk_free_space( $upload_dir['basedir'] );
    if ( $free_space === false ) return;
    $free_space_mb = $free_space / ( 1024 * 1024 );
    if ( $free_space_mb < 500 ) {
        echo '<div class="notice notice-warning is-dismissible"><p><strong>OneWebP Disk Space Warning:</strong> Your server disk space is running low. Currently remaining: <strong>' . round( $free_space_mb, 1 ) . ' MB</strong>.</p></div>';
    }
}

// RAM Monitor
add_action( 'admin_notices', 'onewebp_check_ram_notice' );
function onewebp_check_ram_notice() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $limit_str = ini_get( 'memory_limit' );
    if ( $limit_str === '-1' ) return;
    $limit_str = trim( $limit_str );
    $last = strtolower( substr( $limit_str, -1 ) );
    $val = (int) $limit_str;
    switch ( $last ) { case 'g': $val *= 1024*1024*1024; break; case 'm': $val *= 1024*1024; break; case 'k': $val *= 1024; break; }
    $available_bytes = $val - memory_get_usage( true );
    if ( $available_bytes < 64 * 1024 * 1024 ) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>OneWebP Critical Memory Warning:</strong> Available RAM is too low (' . round( $available_bytes / 1024 / 1024, 1 ) . ' MB). Please increase memory_limit.</p></div>';
    }
}