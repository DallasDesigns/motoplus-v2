<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'init', 'motoplus_register_post_types' );
function motoplus_register_post_types() {

    register_post_type( MOTOPLUS_CPT, [
        'labels'        => [
            'name'          => 'Vehicles', 'singular_name' => 'Vehicle', 'menu_name' => 'Motoplus',
            'add_new_item'  => 'Add New Vehicle', 'edit_item' => 'Edit Vehicle',
            'view_item'     => 'View Vehicle',    'not_found'  => 'No vehicles found',
        ],
        'public'        => true,
        'has_archive'   => true,
        'rewrite'       => ['slug' => 'cars-for-sale', 'with_front' => false],
        'menu_icon'     => 'dashicons-car',
        'supports'      => ['title', 'editor', 'thumbnail'],
        'show_in_rest'  => true,
    ]);

    register_post_type( MOTOPLUS_LEAD_CPT, [
        'labels'        => [
            'name'          => 'Enquiries', 'singular_name' => 'Enquiry', 'menu_name' => 'Enquiries',
            'add_new_item'  => 'Add Enquiry', 'edit_item' => 'Edit Enquiry',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => 'edit.php?post_type=' . MOTOPLUS_CPT,
        'supports'      => ['title'],
    ]);
}

// Custom admin columns – vehicles
add_filter( 'manage_' . MOTOPLUS_CPT . '_posts_columns', function( $cols ) {
    return ['cb'=>$cols['cb'],'image'=>'Photo','title'=>'Vehicle','price'=>'Price','status'=>'Status','featured'=>'★','date'=>$cols['date']];
});
add_action( 'manage_' . MOTOPLUS_CPT . '_posts_custom_column', function( $col, $id ) {
    if ( $col === 'image'    ) echo motoplus_vehicle_image( $id, 'thumbnail' );
    if ( $col === 'price'    ) echo esc_html( motoplus_money( motoplus_meta($id,'price') ) );
    if ( $col === 'status'   ) { $s = motoplus_meta($id,'status') ?: 'In Stock'; echo '<span class="mp-col-status mp-col-status--'.sanitize_html_class(strtolower($s)).'">'.esc_html($s).'</span>'; }
    if ( $col === 'featured' ) echo motoplus_meta($id,'featured')==='1' ? '<span title="Featured">⭐</span>' : '—';
}, 10, 2 );

// Custom admin columns – leads
add_filter( 'manage_' . MOTOPLUS_LEAD_CPT . '_posts_columns', function( $cols ) {
    return ['cb'=>$cols['cb'],'title'=>'Lead','vehicle'=>'Vehicle','phone'=>'Phone','email'=>'Email','lead_status'=>'Status','date'=>$cols['date']];
});
add_action( 'manage_' . MOTOPLUS_LEAD_CPT . '_posts_custom_column', function( $col, $id ) {
    $p = MOTOPLUS_META . 'lead_';
    if ( $col === 'vehicle'     ) echo esc_html( get_post_meta($id,$p.'vehicle_title',true) );
    if ( $col === 'phone'       ) echo esc_html( get_post_meta($id,$p.'phone',true) );
    if ( $col === 'email'       ) echo esc_html( get_post_meta($id,$p.'email',true) );
    if ( $col === 'lead_status' ) { $s = get_post_meta($id,$p.'status',true) ?: 'New'; echo '<span class="mp-col-status">'.esc_html($s).'</span>'; }
}, 10, 2 );
