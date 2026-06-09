<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'add_meta_boxes', 'motoplus_add_meta_boxes' );
function motoplus_add_meta_boxes() {
    add_meta_box( 'motoplus_vehicle_details', 'Vehicle Details',  'motoplus_vehicle_meta_box', MOTOPLUS_CPT,      'normal', 'high'    );
    add_meta_box( 'motoplus_vehicle_gallery', 'Vehicle Images',   'motoplus_gallery_meta_box', MOTOPLUS_CPT,      'side',   'default' );
    add_meta_box( 'motoplus_lead_details',    'Enquiry Details',  'motoplus_lead_meta_box',    MOTOPLUS_LEAD_CPT, 'normal', 'high'    );
}

function motoplus_vehicle_meta_box( $post ) {
    wp_nonce_field( 'motoplus_save_vehicle', 'motoplus_vehicle_nonce' );
    $fields = motoplus_vehicle_fields();
    $groups = [];
    foreach ( $fields as $key => $field ) {
        if ( $key === 'gallery' ) continue;
        $groups[ $field['group'] ][] = [$key, $field];
    }
    ?>
    <div class="mp-meta-wrap">
        <!-- Registration lookup -->
        <div class="mp-lookup-row">
            <div class="mp-lookup-input">
                <label>🔍 Registration Lookup</label>
                <input type="text" id="mp_reg_lookup" value="<?php echo esc_attr( motoplus_meta($post->ID,'registration') ); ?>" placeholder="AB12 CDE" style="text-transform:uppercase;" />
            </div>
            <button type="button" class="button button-primary" id="mp_lookup_btn">Lookup Vehicle</button>
            <span id="mp_lookup_result" class="mp-lookup-result"></span>
        </div>

        <?php foreach ( $groups as $group => $items ) : ?>
        <div class="mp-group">
            <h3 class="mp-group-title"><?php echo esc_html($group); ?></h3>
            <div class="mp-field-grid">
                <?php foreach ( $items as [$key, $field] ) : motoplus_render_field($post->ID, $key, $field); endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- AI description -->
        <div class="mp-ai-box">
            <h3>✨ AI Description Generator</h3>
            <p>Generate a starter description from the fields above, then edit it in the main content editor.</p>
            <button type="button" class="button button-secondary" id="mp_gen_desc_btn">Generate Draft Description</button>
            <span id="mp_ai_result" class="mp-ai-result"></span>
        </div>
    </div>
    <?php
}

function motoplus_render_field( $post_id, $key, $field ) {
    $value = motoplus_meta( $post_id, $key );
    $name  = 'motoplus_vehicle[' . esc_attr($key) . ']';
    echo '<div class="mp-field">';
    echo '<label for="mp_field_' . esc_attr($key) . '">' . esc_html($field['label']) . '</label>';

    if ( $field['type'] === 'select' ) {
        echo '<select id="mp_field_' . esc_attr($key) . '" name="' . $name . '">';
        foreach ( $field['options'] as $opt_val => $opt_label ) {
            echo '<option value="' . esc_attr($opt_val) . '" ' . selected($value, $opt_val, false) . '>' . esc_html($opt_label) . '</option>';
        }
        echo '</select>';
    } elseif ( $field['type'] === 'checkbox' ) {
        echo '<label class="mp-checkbox"><input type="checkbox" id="mp_field_' . esc_attr($key) . '" name="' . $name . '" value="1" ' . checked($value,'1',false) . '> Yes</label>';
    } else {
        echo '<input type="' . esc_attr($field['type']) . '" id="mp_field_' . esc_attr($key) . '" name="' . $name . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($field['placeholder'] ?? '') . '">';
    }
    echo '</div>';
}

function motoplus_gallery_meta_box( $post ) {
    $gallery = motoplus_meta( $post->ID, 'gallery' );
    $ids     = array_filter( array_map('absint', explode(',', $gallery ?? '')) );
    ?>
    <div class="mp-gallery-wrap">
        <div id="mp-gallery-preview" class="mp-gallery-preview">
            <?php foreach ( $ids as $img_id ) : ?>
            <div class="mp-gallery-item" data-id="<?php echo esc_attr($img_id); ?>">
                <?php echo wp_get_attachment_image( $img_id, 'thumbnail' ); ?>
                <button type="button" class="mp-remove-img" title="Remove">✕</button>
            </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" id="mp_gallery_input" name="motoplus_vehicle[gallery]" value="<?php echo esc_attr($gallery); ?>" />
        <div class="mp-gallery-actions">
            <button type="button" class="button button-primary" id="mp_add_gallery">+ Add Photos</button>
            <button type="button" class="button" id="mp_clear_gallery">Clear All</button>
        </div>
        <p class="description">First image is used as the main listing photo.</p>
    </div>
    <?php
}

function motoplus_lead_fields() {
    return [
        'vehicle_title' => ['label'=>'Vehicle',      'type'=>'text'],
        'name'          => ['label'=>'Name',          'type'=>'text'],
        'phone'         => ['label'=>'Phone',         'type'=>'text'],
        'email'         => ['label'=>'Email',         'type'=>'email'],
        'message'       => ['label'=>'Message',       'type'=>'textarea'],
        'status'        => ['label'=>'Lead Status',   'type'=>'select', 'options'=>['New'=>'New','Contacted'=>'Contacted','Appointment'=>'Appointment','Sold'=>'Sold','Lost'=>'Lost']],
    ];
}

function motoplus_lead_meta_box( $post ) {
    wp_nonce_field( 'motoplus_save_lead', 'motoplus_lead_nonce' );
    $prefix = MOTOPLUS_META . 'lead_';
    echo '<div class="mp-field-grid">';
    foreach ( motoplus_lead_fields() as $key => $field ) {
        $value = get_post_meta( $post->ID, $prefix . $key, true );
        $name  = 'motoplus_lead[' . esc_attr($key) . ']';
        echo '<div class="mp-field ' . ($field['type']==='textarea' ? 'mp-field--full' : '') . '">';
        echo '<label>' . esc_html($field['label']) . '</label>';
        if ( $field['type'] === 'textarea' ) {
            echo '<textarea name="' . $name . '" rows="4">' . esc_textarea($value) . '</textarea>';
        } elseif ( $field['type'] === 'select' ) {
            echo '<select name="' . $name . '">';
            foreach ( $field['options'] as $k => $v ) echo '<option value="' . esc_attr($k) . '" ' . selected($value,$k,false) . '>' . esc_html($v) . '</option>';
            echo '</select>';
        } else {
            echo '<input type="' . esc_attr($field['type']) . '" name="' . $name . '" value="' . esc_attr($value) . '">';
        }
        echo '</div>';
    }
    echo '</div>';
}

// Save vehicle
add_action( 'save_post_' . MOTOPLUS_CPT, 'motoplus_save_vehicle', 10, 2 );
function motoplus_save_vehicle( $post_id ) {
    if ( ! isset($_POST['motoplus_vehicle_nonce']) || ! wp_verify_nonce($_POST['motoplus_vehicle_nonce'], 'motoplus_save_vehicle') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $data   = $_POST['motoplus_vehicle'] ?? [];
    $fields = motoplus_vehicle_fields();

    foreach ( $fields as $key => $field ) {
        if ( $field['type'] === 'checkbox' ) {
            $value = isset($data[$key]) ? '1' : '0';
        } elseif ( $field['type'] === 'number' ) {
            $value = isset($data[$key]) && $data[$key] !== '' ? (string) floatval($data[$key]) : '';
        } elseif ( $key === 'gallery' ) {
            $value = sanitize_text_field( $data[$key] ?? '' );
        } else {
            $value = sanitize_text_field( $data[$key] ?? '' );
        }
        update_post_meta( $post_id, MOTOPLUS_META . $key, $value );
    }
}

// Save lead
add_action( 'save_post_' . MOTOPLUS_LEAD_CPT, 'motoplus_save_lead', 10, 2 );
function motoplus_save_lead( $post_id ) {
    if ( ! isset($_POST['motoplus_lead_nonce']) || ! wp_verify_nonce($_POST['motoplus_lead_nonce'], 'motoplus_save_lead') ) return;
    if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) return;
    if ( ! current_user_can('edit_post', $post_id) ) return;

    $data = $_POST['motoplus_lead'] ?? [];
    foreach ( motoplus_lead_fields() as $key => $field ) {
        $value = $field['type'] === 'textarea'
            ? sanitize_textarea_field( $data[$key] ?? '' )
            : sanitize_text_field( $data[$key] ?? '' );
        update_post_meta( $post_id, MOTOPLUS_META . 'lead_' . $key, $value );
    }
}
