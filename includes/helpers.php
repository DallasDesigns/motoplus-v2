<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function motoplus_settings() {
    return wp_parse_args( get_option( MOTOPLUS_OPTION_KEY, [] ), [
        'accent_colour'      => '#e63946',
        'button_colour'      => '#e63946',
        'button_text_colour' => '#ffffff',
        'page_background'    => '#f9fafb',
        'card_background'    => '#ffffff',
        'border_colour'      => '#e5e7eb',
        'text_colour'        => '#111827',
        'muted_text_colour'  => '#6b7280',
        'button_radius'      => 8,
        'card_radius'        => 12,
        'lead_email'         => get_option( 'admin_email' ),
        'dealer_phone'       => '',
        'dealer_name'        => get_bloginfo('name'),
        'stock_page_url'     => '',
        'lookup_provider'    => 'ukvd',
        'lookup_api_key'     => 'c87e9404-11ce-4432-a9e1-ccd73276cd63',
        'openai_api_key'     => '',
        'whatsapp_number'    => '',
        'whatsapp_message'   => 'Hi, I am interested in the {title}. {url}',

        // Listing page (cards) customisation
        'listing_show_price'       => '1',
        'listing_show_mileage'     => '1',
        'listing_show_fuel'        => '1',
        'listing_show_gearbox'     => '1',
        'listing_show_year'        => '1',
        'listing_show_body'        => '0',
        'listing_show_engine'      => '0',
        'listing_show_colour'      => '0',
        'listing_btn_primary_text' => 'View Vehicle',
        'listing_btn_enquire_text' => 'Enquire',
        'listing_default_view'     => 'grid',
        'listing_cards_per_row'    => '3',
        'listing_show_badges'      => '1',
        'listing_show_search'      => '1',
        'listing_show_filters'     => '1',
        'listing_show_sort'        => '1',
        'listing_show_view_toggle' => '1',

        // Product (single vehicle) page customisation
        'product_show_whatsapp'       => '1',
        'product_show_phone'          => '1',
        'product_show_enquiry_form'   => '1',
        'product_show_highlights'     => '1',
        'product_show_keyspecs'       => '1',
        'product_show_fullspec'       => '1',
        'product_show_similar'        => '1',
        'product_show_description'    => '1',
        'product_show_breadcrumb'     => '1',
        'product_enquiry_title'       => 'Enquire About This Vehicle',
        'product_enquiry_subtitle'    => 'Complete the form below and we\'ll get back to you as soon as possible.',
        'product_similar_title'       => 'Similar Vehicles',
        'product_cta_call_text'       => 'Call Us',
        'product_cta_enquire_text'    => 'Enquire Now',
        'product_cta_whatsapp_text'   => 'WhatsApp Us',
        'product_highlight_color'     => '#f0fdf4',
        'product_spec_groups'         => '1',
    ]);
}

function motoplus_meta( $post_id, $key ) {
    return get_post_meta( $post_id, MOTOPLUS_META . $key, true );
}

function motoplus_money( $value ) {
    if ( $value === '' || $value === null ) return '';
    // Strip anything non-numeric except decimal point, then cast to int
    $clean = preg_replace( '/[^0-9.]/', '', (string) $value );
    if ( $clean === '' ) return '';
    $int = (int) round( (float) $clean );
    if ( $int <= 0 || $int > 10000000 ) return ''; // sanity cap — no car costs >£10m
    return '£' . number_format( $int, 0 );
}

function motoplus_miles( $value ) {
    if ( $value === '' || $value === null ) return '';
    return number_format( (float) $value, 0 ) . ' miles';
}

function motoplus_is_new_arrival( $id ) {
    return ( time() - get_post_time( 'U', true, $id ) ) <= 14 * DAY_IN_SECONDS;
}

function motoplus_vehicle_fields() {
    return [
        'registration'    => ['label'=>'Registration',       'type'=>'text',     'placeholder'=>'AB12 CDE',   'group'=>'Lookup'],
        'price'           => ['label'=>'Price (£)',           'type'=>'number',   'placeholder'=>'18995',      'group'=>'Sale Details'],
        'previous_price'  => ['label'=>'Previous Price (£)',  'type'=>'number',   'placeholder'=>'19995',      'group'=>'Sale Details'],
        'status'          => ['label'=>'Status',              'type'=>'select',   'options'=>['In Stock'=>'In Stock','Reserved'=>'Reserved','Sold'=>'Sold','Coming Soon'=>'Coming Soon'], 'group'=>'Sale Details'],
        'featured'        => ['label'=>'Featured Vehicle',    'type'=>'checkbox', 'group'=>'Sale Details'],
        'make'            => ['label'=>'Make',                'type'=>'text',     'placeholder'=>'BMW',        'group'=>'Vehicle Details'],
        'model'           => ['label'=>'Model',               'type'=>'text',     'placeholder'=>'3 Series',   'group'=>'Vehicle Details'],
        'variant'         => ['label'=>'Variant / Trim',      'type'=>'text',     'placeholder'=>'M Sport',    'group'=>'Vehicle Details'],
        'year'            => ['label'=>'Year',                'type'=>'number',   'placeholder'=>'2021',       'group'=>'Vehicle Details'],
        'mileage'         => ['label'=>'Mileage',             'type'=>'number',   'placeholder'=>'42000',      'group'=>'Vehicle Details'],
        'fuel'            => ['label'=>'Fuel Type',           'type'=>'select',   'options'=>[''=>'Select','Petrol'=>'Petrol','Diesel'=>'Diesel','Hybrid'=>'Hybrid','Plug-in Hybrid'=>'Plug-in Hybrid','Electric'=>'Electric'], 'group'=>'Vehicle Details'],
        'gearbox'         => ['label'=>'Transmission',        'type'=>'select',   'options'=>[''=>'Select','Manual'=>'Manual','Automatic'=>'Automatic','Semi-Automatic'=>'Semi-Automatic'], 'group'=>'Vehicle Details'],
        'engine'          => ['label'=>'Engine Size',         'type'=>'text',     'placeholder'=>'2.0L',       'group'=>'Vehicle Details'],
        'body'            => ['label'=>'Body Type',           'type'=>'text',     'placeholder'=>'Hatchback',  'group'=>'Vehicle Details'],
        'colour'          => ['label'=>'Colour',              'type'=>'text',     'placeholder'=>'Grey',       'group'=>'Vehicle Details'],
        'doors'           => ['label'=>'Doors',               'type'=>'number',   'placeholder'=>'5',          'group'=>'Vehicle Details'],
        'owners'          => ['label'=>'Previous Owners',     'type'=>'number',   'placeholder'=>'1',          'group'=>'History & Condition'],
        'service_history' => ['label'=>'Service History',     'type'=>'select',   'options'=>[''=>'Select','Full Service History'=>'Full Service History','Part Service History'=>'Part Service History','No Service History'=>'No Service History'], 'group'=>'History & Condition'],
        'mot_expiry'      => ['label'=>'MOT Expiry',          'type'=>'text',     'placeholder'=>'March 2027', 'group'=>'History & Condition'],
        'road_tax'        => ['label'=>'Road Tax',            'type'=>'text',     'placeholder'=>'£190/year',  'group'=>'History & Condition'],
        'tax_band'        => ['label'=>'Tax Band',            'type'=>'text',     'placeholder'=>'E',          'group'=>'History & Condition'],
        'co2'             => ['label'=>'CO2 Emissions',       'type'=>'text',     'placeholder'=>'135 g/km',   'group'=>'History & Condition'],
        'seats'           => ['label'=>'Seats',               'type'=>'number',   'placeholder'=>'5',          'group'=>'Extra Details'],
        'location'        => ['label'=>'Location',            'type'=>'text',     'placeholder'=>'Belfast',    'group'=>'Extra Details'],
        'payload'         => ['label'=>'Payload',             'type'=>'text',     'placeholder'=>'741kg',      'group'=>'Extra Details'],
        'finance_text'    => ['label'=>'Finance Text',        'type'=>'text',     'placeholder'=>'From £189/month — 9.9% APR Representative', 'group'=>'Sale Details'],
        'video_url'       => ['label'=>'Video URL',           'type'=>'text',     'placeholder'=>'https://youtube.com/watch?v=...', 'group'=>'Images'],
        'gallery'         => ['label'=>'Gallery',             'type'=>'hidden',   'group'=>'Images'],
    ];
}

function motoplus_spec_icon( $key, $size = 16, $colour = 'currentColor' ) {
    // Clean single-colour SVG icons (Phosphor-style, Regular weight)
    $c = esc_attr($colour);
    $s = absint($size);
    $icons = [
        'year'    => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="40" y="40" width="176" height="176" rx="8" stroke="'.$c.'" stroke-width="16" fill="none"/><line x1="176" y1="24" x2="176" y2="56" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="80" y1="24" x2="80" y2="56" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="40" y1="96" x2="216" y2="96" stroke="'.$c.'" stroke-width="16"/><rect x="84" y="128" width="32" height="24" rx="4" fill="'.$c.'"/><rect x="140" y="128" width="32" height="24" rx="4" fill="'.$c.'"/><rect x="84" y="172" width="32" height="24" rx="4" fill="'.$c.'"/></svg>',
        'mileage' => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="128" cy="128" r="96" stroke="'.$c.'" stroke-width="16" fill="none"/><path d="M64 192 C64 192 80 128 128 128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><path d="M128 128 L168 72" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><circle cx="128" cy="128" r="12" fill="'.$c.'"/><path d="M48 160 L80 152 M208 160 L176 152 M128 40 L128 64" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/></svg>',
        'fuel'    => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="32" y="48" width="120" height="184" rx="8" stroke="'.$c.'" stroke-width="16" fill="none"/><path d="M152 96 L192 72 L192 176 Q192 200 168 200" stroke="'.$c.'" stroke-width="16" stroke-linecap="round" stroke-linejoin="round" fill="none"/><line x1="32" y1="120" x2="152" y2="120" stroke="'.$c.'" stroke-width="16"/></svg>',
        'gearbox' => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="80" cy="56" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="176" cy="56" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="80" cy="200" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="176" cy="200" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><line x1="80" y1="80" x2="80" y2="128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="176" y1="80" x2="176" y2="128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="80" y1="128" x2="176" y2="128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="176" y1="128" x2="176" y2="176" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="80" y1="128" x2="80" y2="176" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/></svg>',
        'engine'  => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="88" y="88" width="80" height="80" rx="8" stroke="'.$c.'" stroke-width="16" fill="none"/><line x1="128" y1="40" x2="128" y2="88" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="40" y1="128" x2="88" y2="128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="168" y1="128" x2="216" y2="128" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="128" y1="168" x2="128" y2="216" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="40" y1="96" x2="40" y2="160" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><line x1="216" y1="96" x2="216" y2="160" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/></svg>',
        'body'    => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M16 160 L48 96 Q80 56 128 56 Q176 56 208 96 L240 160 L240 192 Q240 208 224 208 L32 208 Q16 208 16 192 Z" stroke="'.$c.'" stroke-width="16" stroke-linejoin="round" fill="none"/><circle cx="72" cy="196" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="184" cy="196" r="24" stroke="'.$c.'" stroke-width="16" fill="none"/><line x1="96" y1="196" x2="160" y2="196" stroke="'.$c.'" stroke-width="16"/></svg>',
        'colour'  => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="128" cy="120" r="88" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="128" cy="120" r="32" stroke="'.$c.'" stroke-width="16" fill="none"/><line x1="128" y1="32" x2="128" y2="88" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/><line x1="128" y1="152" x2="128" y2="208" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/><line x1="40" y1="120" x2="96" y2="120" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/><line x1="160" y1="120" x2="216" y2="120" stroke="'.$c.'" stroke-width="12" stroke-linecap="round"/><path d="M128 200 Q160 220 184 208 Q208 196 200 224 Q192 240 128 240 Q80 240 56 216 Q32 192 72 200 Q100 208 128 200 Z" fill="'.$c.'" stroke="none"/></svg>',
        'doors'   => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><rect x="48" y="24" width="160" height="208" rx="8" stroke="'.$c.'" stroke-width="16" fill="none"/><circle cx="172" cy="128" r="10" fill="'.$c.'"/><line x1="48" y1="72" x2="208" y2="72" stroke="'.$c.'" stroke-width="12"/></svg>',
        'seats'   => '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M56 48 L56 160 Q56 184 80 184 L200 184" stroke="'.$c.'" stroke-width="16" stroke-linecap="round" stroke-linejoin="round" fill="none"/><path d="M96 184 L96 216" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><path d="M176 184 L176 216" stroke="'.$c.'" stroke-width="16" stroke-linecap="round"/><rect x="88" y="80" width="128" height="88" rx="16" stroke="'.$c.'" stroke-width="16" fill="none"/></svg>',
    ];
    return $icons[$key] ?? '<svg width="'.$s.'" height="'.$s.'" viewBox="0 0 256 256"><circle cx="128" cy="128" r="20" fill="'.$c.'"/></svg>';
}

function motoplus_vehicle_image( $id, $size = 'large' ) {
    if ( has_post_thumbnail( $id ) ) return get_the_post_thumbnail( $id, $size );
    $gallery = motoplus_meta( $id, 'gallery' );
    $first   = absint( explode( ',', $gallery )[0] ?? 0 );
    if ( $first ) return wp_get_attachment_image( $first, $size );
    $placeholder = MOTOPLUS_URL . 'public/img/coming-soon.svg';
    $title       = get_the_title( $id ) ?: 'Vehicle';
    return '<img src="' . esc_url( $placeholder ) . '" alt="' . esc_attr( $title ) . ' — Photos Coming Soon" class="mp-coming-soon-img" />';
}

function motoplus_get_unique_meta( $key ) {
    global $wpdb;
    return $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_value FROM {$wpdb->postmeta} pm
         INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
         WHERE pm.meta_key = %s AND pm.meta_value != ''
         AND p.post_type = %s AND p.post_status = 'publish'
         ORDER BY meta_value ASC",
        MOTOPLUS_META . $key, MOTOPLUS_CPT
    ));
}
