<?php
/**
 * Plugin Name: Motoplus
 * Description: Professional dealer vehicle stock system with live filtering, gallery, lead capture, importer, and AI descriptions.
 * Version:     2.0.0
 * Author:      Motoplus
 * Text Domain: motoplus
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'MOTOPLUS_VERSION',    '2.0.0' );
define( 'MOTOPLUS_DIR',        plugin_dir_path( __FILE__ ) );
define( 'MOTOPLUS_URL',        plugin_dir_url( __FILE__ ) );
define( 'MOTOPLUS_CPT',        'motoplus_vehicle' );
define( 'MOTOPLUS_LEAD_CPT',   'motoplus_lead' );
define( 'MOTOPLUS_META',       '_motoplus_' );
define( 'MOTOPLUS_OPTION_KEY', 'motoplus_settings' );

require_once MOTOPLUS_DIR . 'includes/helpers.php';
require_once MOTOPLUS_DIR . 'includes/post-type.php';
require_once MOTOPLUS_DIR . 'includes/meta-boxes.php';
require_once MOTOPLUS_DIR . 'includes/shortcodes.php';
require_once MOTOPLUS_DIR . 'includes/ajax.php';
require_once MOTOPLUS_DIR . 'includes/single-template.php';
require_once MOTOPLUS_DIR . 'admin/settings.php';
require_once MOTOPLUS_DIR . 'admin/dashboard.php';
require_once MOTOPLUS_DIR . 'admin/import.php';

register_activation_hook( __FILE__, 'motoplus_activate' );
function motoplus_activate() {
    motoplus_register_post_types();
    flush_rewrite_rules();
}

register_deactivation_hook( __FILE__, function() { flush_rewrite_rules(); } );

// Enqueue frontend assets
add_action( 'wp_enqueue_scripts', 'motoplus_enqueue_frontend' );
function motoplus_enqueue_frontend() {
    wp_enqueue_style(  'motoplus-front', MOTOPLUS_URL . 'public/css/front.css', [], MOTOPLUS_VERSION );
    wp_enqueue_script( 'motoplus-front', MOTOPLUS_URL . 'public/js/front.js',   ['jquery'], MOTOPLUS_VERSION, true );
    wp_localize_script( 'motoplus-front', 'motoplusFront', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'motoplus_front_nonce' ),
    ]);

    // Inject CSS variables from settings
    $s = motoplus_settings();
    $css = ':root{';
    $css .= '--mp-accent:'    . esc_attr($s['accent_colour'])       . ';';
    $css .= '--mp-btn:'       . esc_attr($s['button_colour'])       . ';';
    $css .= '--mp-btn-text:'  . esc_attr($s['button_text_colour'])  . ';';
    $css .= '--mp-page-bg:'   . esc_attr($s['page_background'])     . ';';
    $css .= '--mp-card-bg:'   . esc_attr($s['card_background'])     . ';';
    $css .= '--mp-border:'    . esc_attr($s['border_colour'])       . ';';
    $css .= '--mp-text:'      . esc_attr($s['text_colour'])         . ';';
    $css .= '--mp-muted:'     . esc_attr($s['muted_text_colour'])   . ';';
    $css .= '--mp-btn-radius:'. absint($s['button_radius'])         . 'px;';
    $css .= '--mp-card-radius:'. absint($s['card_radius'])          . 'px;';
    $css .= '}';
    wp_add_inline_style( 'motoplus-front', $css );
}

// Enqueue admin assets
add_action( 'admin_enqueue_scripts', 'motoplus_enqueue_admin' );
function motoplus_enqueue_admin( $hook ) {
    $screen = get_current_screen();
    if ( ! $screen ) return;
    $on_vehicle = in_array( $screen->post_type, [ MOTOPLUS_CPT, MOTOPLUS_LEAD_CPT ] );
    $on_page    = strpos( $hook, 'motoplus' ) !== false;
    if ( ! $on_vehicle && ! $on_page ) return;

    wp_enqueue_media();
    wp_enqueue_style(  'motoplus-admin', MOTOPLUS_URL . 'admin/admin.css', [], MOTOPLUS_VERSION );
    wp_enqueue_script( 'motoplus-admin', MOTOPLUS_URL . 'admin/admin.js',  ['jquery'], MOTOPLUS_VERSION, true );
    wp_localize_script( 'motoplus-admin', 'motoplusAdmin', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'motoplus_admin_nonce' ),
    ]);
}
