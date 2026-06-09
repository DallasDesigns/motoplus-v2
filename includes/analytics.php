<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Motoplus Analytics
 * Tracks: vehicle page views, enquiry form clicks, WhatsApp clicks, phone clicks
 * Stores in a custom DB table for fast querying
 */

// ── Create analytics table on activation ─────────────────────────────────────
register_activation_hook( MOTOPLUS_DIR . 'motoplus.php', 'motoplus_create_analytics_table' );
add_action( 'init', 'motoplus_maybe_create_analytics_table' );

function motoplus_maybe_create_analytics_table() {
    if ( get_option('motoplus_analytics_db_version') !== '1.1' ) {
        motoplus_create_analytics_table();
    }
}

function motoplus_create_analytics_table() {
    global $wpdb;
    $table   = $wpdb->prefix . 'motoplus_analytics';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS {$table} (
        id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        vehicle_id  BIGINT UNSIGNED NOT NULL,
        event_type  VARCHAR(40)     NOT NULL,
        event_date  DATE            NOT NULL,
        event_hour  TINYINT         NOT NULL DEFAULT 0,
        ip_hash     VARCHAR(64)     NOT NULL DEFAULT '',
        PRIMARY KEY (id),
        KEY vehicle_event (vehicle_id, event_type),
        KEY event_date (event_date)
    ) {$charset};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
    update_option( 'motoplus_analytics_db_version', '1.1' );
}

// ── Record an event ───────────────────────────────────────────────────────────
function motoplus_record_event( $vehicle_id, $event_type ) {
    global $wpdb;

    // Deduplicate by IP+vehicle+event within same hour to prevent refresh spam
    $ip_hash = hash( 'sha256', $_SERVER['REMOTE_ADDR'] ?? '' );
    $hour    = (int) current_time('G');
    $date    = current_time('Y-m-d');

    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}motoplus_analytics
         WHERE vehicle_id=%d AND event_type=%s AND event_date=%s AND event_hour=%d AND ip_hash=%s
         LIMIT 1",
        $vehicle_id, $event_type, $date, $hour, $ip_hash
    ));

    if ( $exists ) return;

    $wpdb->insert(
        $wpdb->prefix . 'motoplus_analytics',
        [ 'vehicle_id'=>$vehicle_id, 'event_type'=>$event_type, 'event_date'=>$date, 'event_hour'=>$hour, 'ip_hash'=>$ip_hash ],
        [ '%d','%s','%s','%d','%s' ]
    );
}

// ── AJAX: track event (called from frontend JS) ───────────────────────────────
add_action( 'wp_ajax_motoplus_track',        'motoplus_ajax_track' );
add_action( 'wp_ajax_nopriv_motoplus_track', 'motoplus_ajax_track' );
function motoplus_ajax_track() {
    // No nonce check intentional — read-only pixel-style tracking
    $vehicle_id = absint( $_POST['vehicle_id'] ?? 0 );
    $event_type = sanitize_key( $_POST['event_type'] ?? '' );
    $allowed    = ['view','enquiry_click','whatsapp_click','phone_click','enquiry_sent'];

    if ( $vehicle_id && in_array( $event_type, $allowed ) ) {
        motoplus_record_event( $vehicle_id, $event_type );
    }
    wp_die(); // 200 with no body
}

// ── Auto-track vehicle page views ────────────────────────────────────────────
add_action( 'wp_head', 'motoplus_track_page_view' );
function motoplus_track_page_view() {
    if ( is_singular( MOTOPLUS_CPT ) && ! is_admin() ) {
        $id = get_the_ID();
        // Inline JS so it fires even if main script hasn't loaded yet
        echo '<script>
(function(){
  var d=new FormData();
  d.append("action","motoplus_track");
  d.append("vehicle_id","' . esc_js($id) . '");
  d.append("event_type","view");
  fetch("' . esc_url(admin_url("admin-ajax.php")) . '",{method:"POST",body:d,credentials:"same-origin"});
})();
</script>' . "\n";
    }
}

// ── Analytics query helpers ───────────────────────────────────────────────────
function motoplus_get_totals( $days = 30 ) {
    global $wpdb;
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT event_type, COUNT(*) as total
         FROM {$wpdb->prefix}motoplus_analytics
         WHERE event_date >= %s
         GROUP BY event_type",
        $since
    ), OBJECT_K );
}

function motoplus_get_top_vehicles( $days = 30, $event = 'view', $limit = 10 ) {
    global $wpdb;
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT a.vehicle_id, COUNT(*) as total, p.post_title
         FROM {$wpdb->prefix}motoplus_analytics a
         LEFT JOIN {$wpdb->posts} p ON p.ID = a.vehicle_id
         WHERE a.event_date >= %s AND a.event_type = %s AND p.post_status='publish'
         GROUP BY a.vehicle_id
         ORDER BY total DESC
         LIMIT %d",
        $since, $event, $limit
    ));
}

function motoplus_get_daily_views( $days = 30 ) {
    global $wpdb;
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT event_date, COUNT(*) as total
         FROM {$wpdb->prefix}motoplus_analytics
         WHERE event_date >= %s AND event_type = 'view'
         GROUP BY event_date
         ORDER BY event_date ASC",
        $since
    ));
}

function motoplus_get_vehicle_stats( $vehicle_id ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT event_type, COUNT(*) as total
         FROM {$wpdb->prefix}motoplus_analytics
         WHERE vehicle_id = %d
         GROUP BY event_type",
        $vehicle_id
    ), OBJECT_K );
    return [
        'views'           => (int)($rows['view']->total ?? 0),
        'enquiry_clicks'  => (int)($rows['enquiry_click']->total ?? 0),
        'whatsapp_clicks' => (int)($rows['whatsapp_click']->total ?? 0),
        'phone_clicks'    => (int)($rows['phone_click']->total ?? 0),
        'enquiries_sent'  => (int)($rows['enquiry_sent']->total ?? 0),
    ];
}

function motoplus_get_period_views( $days = 30 ) {
    global $wpdb;
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}motoplus_analytics WHERE event_date >= %s AND event_type='view'", $since
    ));
}

function motoplus_get_period_enquiries( $days = 30 ) {
    global $wpdb;
    $since = date( 'Y-m-d', strtotime( "-{$days} days" ) );
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}motoplus_analytics WHERE event_date >= %s AND event_type='enquiry_sent'", $since
    ));
}
