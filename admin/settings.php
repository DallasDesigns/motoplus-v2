<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'motoplus_admin_menu' );
function motoplus_admin_menu() {
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Dashboard',  'Dashboard',  'edit_posts',    'motoplus-dashboard',  'motoplus_dashboard_page');
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Analytics',  'Analytics',  'edit_posts',    'motoplus-analytics',  'motoplus_analytics_page');
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Import',     'Import',     'edit_posts',    'motoplus-import',     'motoplus_import_page');
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Settings',   'Settings',   'manage_options','motoplus-settings',   'motoplus_settings_page');
}

add_action( 'admin_init', function() {
    register_setting('motoplus_settings_group', MOTOPLUS_OPTION_KEY, 'motoplus_sanitize_settings');
});

function motoplus_sanitize_settings($input) {
    $b = function($k) use($input) { return isset($input[$k]) ? '1' : '0'; };
    $s = function($k,$d='') use($input) { return sanitize_text_field($input[$k] ?? $d); };
    return [
        // Branding
        'accent_colour'      => sanitize_hex_color($input['accent_colour']      ?? '#e63946'),
        'button_colour'      => sanitize_hex_color($input['button_colour']      ?? '#e63946'),
        'button_text_colour' => sanitize_hex_color($input['button_text_colour'] ?? '#ffffff'),
        'page_background'    => sanitize_hex_color($input['page_background']    ?? '#f9fafb'),
        'card_background'    => sanitize_hex_color($input['card_background']    ?? '#ffffff'),
        'border_colour'      => sanitize_hex_color($input['border_colour']      ?? '#e5e7eb'),
        'text_colour'        => sanitize_hex_color($input['text_colour']        ?? '#111827'),
        'muted_text_colour'  => sanitize_hex_color($input['muted_text_colour']  ?? '#6b7280'),
        'button_radius'      => absint($input['button_radius']  ?? 8),
        'card_radius'        => absint($input['card_radius']    ?? 12),
        // Dealer
        'lead_email'         => sanitize_email($input['lead_email']     ?? get_option('admin_email')),
        'dealer_phone'       => $s('dealer_phone'),
        'dealer_name'        => $s('dealer_name', get_bloginfo('name')),
        'stock_page_url'     => esc_url_raw($input['stock_page_url'] ?? ''),
        // Integrations
        'lookup_provider'    => $s('lookup_provider','manual'),
        'lookup_api_key'     => $s('lookup_api_key'),
        'openai_api_key'     => $s('openai_api_key'),
        'whatsapp_number'    => $s('whatsapp_number'),
        'whatsapp_message'   => sanitize_textarea_field($input['whatsapp_message'] ?? 'Hi, I am interested in the {title}. {url}'),
        // Listing page
        'listing_show_price'        => $b('listing_show_price'),
        'listing_show_mileage'      => $b('listing_show_mileage'),
        'listing_show_fuel'         => $b('listing_show_fuel'),
        'listing_show_gearbox'      => $b('listing_show_gearbox'),
        'listing_show_year'         => $b('listing_show_year'),
        'listing_show_body'         => $b('listing_show_body'),
        'listing_show_engine'       => $b('listing_show_engine'),
        'listing_show_colour'       => $b('listing_show_colour'),
        'listing_btn_primary_text'  => $s('listing_btn_primary_text','View Vehicle'),
        'listing_btn_enquire_text'  => $s('listing_btn_enquire_text','Enquire'),
        'listing_default_view'      => in_array($input['listing_default_view']??'grid',['grid','list']) ? $input['listing_default_view'] : 'grid',
        'listing_cards_per_row'     => in_array($input['listing_cards_per_row']??'3',['2','3','4']) ? $input['listing_cards_per_row'] : '3',
        'listing_show_badges'       => $b('listing_show_badges'),
        'listing_show_search'       => $b('listing_show_search'),
        'listing_show_filters'      => $b('listing_show_filters'),
        'listing_show_sort'         => $b('listing_show_sort'),
        'listing_show_view_toggle'  => $b('listing_show_view_toggle'),
        // Product page
        'product_show_whatsapp'      => $b('product_show_whatsapp'),
        'product_show_phone'         => $b('product_show_phone'),
        'product_show_enquiry_form'  => $b('product_show_enquiry_form'),
        'product_show_highlights'    => $b('product_show_highlights'),
        'product_show_keyspecs'      => $b('product_show_keyspecs'),
        'product_show_fullspec'      => $b('product_show_fullspec'),
        'product_show_similar'       => $b('product_show_similar'),
        'product_show_description'   => $b('product_show_description'),
        'product_show_breadcrumb'    => $b('product_show_breadcrumb'),
        'product_enquiry_title'      => $s('product_enquiry_title','Enquire About This Vehicle'),
        'product_enquiry_subtitle'   => $s('product_enquiry_subtitle','Complete the form below and we\'ll get back to you as soon as possible.'),
        'product_similar_title'      => $s('product_similar_title','Similar Vehicles'),
        'product_cta_call_text'      => $s('product_cta_call_text','Call Us'),
        'product_cta_enquire_text'   => $s('product_cta_enquire_text','Enquire Now'),
        'product_cta_whatsapp_text'  => $s('product_cta_whatsapp_text','WhatsApp Us'),
        'product_spec_groups'        => $b('product_spec_groups'),
    ];
}

function motoplus_settings_page() {
    $s = motoplus_settings();
    $o = MOTOPLUS_OPTION_KEY;

    function mp_cb( $key, $s ) {
        return $s[$key] === '1' ? 'checked' : '';
    }
    function mp_field( $o, $key, $val, $type='text', $placeholder='' ) {
        return '<input class="regular-text" type="'.esc_attr($type).'" name="'.esc_attr($o).'['.esc_attr($key).']" value="'.esc_attr($val).'" placeholder="'.esc_attr($placeholder).'" />';
    }
    ?>
    <div class="wrap mp-settings-wrap">
        <h1>⚙️ Motoplus Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('motoplus_settings_group'); ?>

            <!-- ── ROW 1: Branding / Dealer / Integrations ── -->
            <div class="mp-settings-grid mp-settings-grid--3">

                <div class="mp-settings-card">
                    <h2>🎨 Branding & Design</h2>
                    <table class="form-table mp-compact-table">
                        <?php foreach([
                            'accent_colour'      =>'Accent / Badge Colour',
                            'button_colour'      =>'Button Colour',
                            'button_text_colour' =>'Button Text Colour',
                            'page_background'    =>'Page Background',
                            'card_background'    =>'Card Background',
                            'border_colour'      =>'Border Colour',
                            'text_colour'        =>'Text Colour',
                            'muted_text_colour'  =>'Muted Text',
                        ] as $key=>$label): ?>
                        <tr><th><?php echo esc_html($label); ?></th><td><input type="color" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="<?php echo esc_attr($s[$key]); ?>" /></td></tr>
                        <?php endforeach; ?>
                        <tr><th>Button Radius</th><td><input type="number" min="0" max="40" name="<?php echo $o; ?>[button_radius]" value="<?php echo esc_attr($s['button_radius']); ?>" style="width:60px"> px</td></tr>
                        <tr><th>Card Radius</th><td><input type="number" min="0" max="40" name="<?php echo $o; ?>[card_radius]" value="<?php echo esc_attr($s['card_radius']); ?>" style="width:60px"> px</td></tr>
                    </table>
                </div>

                <div class="mp-settings-card">
                    <h2>🏢 Dealer Details</h2>
                    <table class="form-table mp-compact-table">
                        <tr><th>Dealer Name</th><td><?php echo mp_field($o,'dealer_name',$s['dealer_name']); ?></td></tr>
                        <tr><th>Phone Number</th><td><?php echo mp_field($o,'dealer_phone',$s['dealer_phone'],'text','028 0000 0000'); ?><p class="description">Shown as Call button on vehicle pages.</p></td></tr>
                        <tr><th>Lead Email</th><td><?php echo mp_field($o,'lead_email',$s['lead_email'],'email'); ?></td></tr>
                        <tr><th>Stock Page URL</th><td><?php echo mp_field($o,'stock_page_url',$s['stock_page_url'],'url','https://example.com/cars-for-sale/'); ?><p class="description">Used by homepage search bar.</p></td></tr>
                    </table>
                </div>

                <div class="mp-settings-card">
                    <h2>🔗 Integrations</h2>
                    <table class="form-table mp-compact-table">
                        <tr>
                            <th>Lookup Provider</th>
                            <td>
                                <select name="<?php echo $o; ?>[lookup_provider]">
                                    <option value="manual" <?php selected($s['lookup_provider'],'manual'); ?>>Manual Only</option>
                                    <option value="dvla"   <?php selected($s['lookup_provider'],'dvla');   ?>>DVLA VES API</option>
                                </select>
                            </td>
                        </tr>
                        <tr><th>Lookup API Key</th><td><input class="regular-text" type="password" name="<?php echo $o; ?>[lookup_api_key]" value="<?php echo esc_attr($s['lookup_api_key']); ?>" /></td></tr>
                        <tr><th>OpenAI API Key</th><td><input class="regular-text" type="password" name="<?php echo $o; ?>[openai_api_key]" value="<?php echo esc_attr($s['openai_api_key']); ?>" /><p class="description">Optional — enables AI descriptions.</p></td></tr>
                        <tr><th>WhatsApp Number</th><td><?php echo mp_field($o,'whatsapp_number',$s['whatsapp_number'],'text','07700 900000'); ?><p class="description">UK format. Falls back to dealer phone if blank.</p></td></tr>
                        <tr>
                            <th>WhatsApp Message</th>
                            <td>
                                <textarea name="<?php echo $o; ?>[whatsapp_message]" rows="3" class="large-text"><?php echo esc_textarea($s['whatsapp_message']); ?></textarea>
                                <p class="description">Available tags: <code>{title}</code> = car name, <code>{price}</code> = price, <code>{url}</code> = listing URL</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- ── ROW 2: Listing page + Product page ── -->
            <div class="mp-settings-grid mp-settings-grid--2" style="margin-top:20px">

                <div class="mp-settings-card">
                    <h2>🚗 Listing Page (Stock Grid)</h2>

                    <h4 class="mp-settings-subheading">Spec Icons Shown on Cards</h4>
                    <div class="mp-toggle-grid">
                        <?php foreach([
                            'listing_show_price'   =>'Price',
                            'listing_show_year'    =>'Year',
                            'listing_show_mileage' =>'Mileage',
                            'listing_show_fuel'    =>'Fuel Type',
                            'listing_show_gearbox' =>'Transmission',
                            'listing_show_body'    =>'Body Type',
                            'listing_show_engine'  =>'Engine Size',
                            'listing_show_colour'  =>'Colour',
                        ] as $key=>$label): ?>
                        <label class="mp-toggle-label">
                            <input type="checkbox" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="1" <?php echo mp_cb($key,$s); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="mp-settings-subheading">UI Controls</h4>
                    <div class="mp-toggle-grid">
                        <?php foreach([
                            'listing_show_badges'       =>'Status Badges',
                            'listing_show_search'       =>'Search Bar',
                            'listing_show_filters'      =>'Filter Panel',
                            'listing_show_sort'         =>'Sort Dropdown',
                            'listing_show_view_toggle'  =>'Grid/List Toggle',
                        ] as $key=>$label): ?>
                        <label class="mp-toggle-label">
                            <input type="checkbox" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="1" <?php echo mp_cb($key,$s); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="mp-settings-subheading">Button Text</h4>
                    <table class="form-table mp-compact-table">
                        <tr><th>Primary Button</th><td><?php echo mp_field($o,'listing_btn_primary_text',$s['listing_btn_primary_text'],'text','View Vehicle'); ?></td></tr>
                        <tr><th>Enquire Button</th><td><?php echo mp_field($o,'listing_btn_enquire_text',$s['listing_btn_enquire_text'],'text','Enquire'); ?></td></tr>
                    </table>

                    <h4 class="mp-settings-subheading">Layout</h4>
                    <table class="form-table mp-compact-table">
                        <tr>
                            <th>Default View</th>
                            <td>
                                <select name="<?php echo $o; ?>[listing_default_view]">
                                    <option value="grid" <?php selected($s['listing_default_view'],'grid'); ?>>Grid</option>
                                    <option value="list" <?php selected($s['listing_default_view'],'list'); ?>>List</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Cards Per Row</th>
                            <td>
                                <select name="<?php echo $o; ?>[listing_cards_per_row]">
                                    <option value="2" <?php selected($s['listing_cards_per_row'],'2'); ?>>2</option>
                                    <option value="3" <?php selected($s['listing_cards_per_row'],'3'); ?>>3 (default)</option>
                                    <option value="4" <?php selected($s['listing_cards_per_row'],'4'); ?>>4</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="mp-settings-card">
                    <h2>📋 Product Page (Single Vehicle)</h2>

                    <h4 class="mp-settings-subheading">Sections to Show</h4>
                    <div class="mp-toggle-grid">
                        <?php foreach([
                            'product_show_breadcrumb'    =>'Back to Stock Link',
                            'product_show_keyspecs'      =>'Key Specs Strip',
                            'product_show_highlights'    =>'Vehicle Highlights',
                            'product_show_description'   =>'Description',
                            'product_show_fullspec'      =>'Full Specification Table',
                            'product_spec_groups'        =>'Group Specs by Category',
                            'product_show_similar'       =>'Similar Vehicles',
                            'product_show_enquiry_form'  =>'Enquiry Form',
                        ] as $key=>$label): ?>
                        <label class="mp-toggle-label">
                            <input type="checkbox" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="1" <?php echo mp_cb($key,$s); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="mp-settings-subheading">CTA Buttons</h4>
                    <div class="mp-toggle-grid">
                        <?php foreach([
                            'product_show_phone'    =>'Call Button',
                            'product_show_whatsapp' =>'WhatsApp Button',
                        ] as $key=>$label): ?>
                        <label class="mp-toggle-label">
                            <input type="checkbox" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="1" <?php echo mp_cb($key,$s); ?> />
                            <?php echo esc_html($label); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>

                    <h4 class="mp-settings-subheading">Button Text</h4>
                    <table class="form-table mp-compact-table">
                        <tr><th>Call Button</th><td><?php echo mp_field($o,'product_cta_call_text',$s['product_cta_call_text'],'text','Call Us'); ?></td></tr>
                        <tr><th>WhatsApp Button</th><td><?php echo mp_field($o,'product_cta_whatsapp_text',$s['product_cta_whatsapp_text'],'text','WhatsApp Us'); ?></td></tr>
                        <tr><th>Enquire Button</th><td><?php echo mp_field($o,'product_cta_enquire_text',$s['product_cta_enquire_text'],'text','Enquire Now'); ?></td></tr>
                    </table>

                    <h4 class="mp-settings-subheading">Enquiry Form Text</h4>
                    <table class="form-table mp-compact-table">
                        <tr><th>Form Heading</th><td><?php echo mp_field($o,'product_enquiry_title',$s['product_enquiry_title'],'text','Enquire About This Vehicle'); ?></td></tr>
                        <tr><th>Form Subtext</th><td><?php echo mp_field($o,'product_enquiry_subtitle',$s['product_enquiry_subtitle'],'text','Complete the form and we\'ll be in touch.'); ?></td></tr>
                        <tr><th>Similar Title</th><td><?php echo mp_field($o,'product_similar_title',$s['product_similar_title'],'text','Similar Vehicles'); ?></td></tr>
                    </table>
                </div>
            </div>

            <!-- ── Shortcodes reference ── -->
            <div class="mp-settings-card mp-settings-card--full" style="margin-top:20px">
                <h2>📋 Shortcodes</h2>
                <table class="mp-shortcode-table">
                    <tr><td><code>[motoplus_stock]</code></td><td>Full stock listing page with search &amp; filters</td></tr>
                    <tr><td><code>[motoplus_featured]</code></td><td>Featured vehicles only (default 3)</td></tr>
                    <tr><td><code>[motoplus_latest limit="6"]</code></td><td>Latest arrivals</td></tr>
                    <tr><td><code>[motoplus_search]</code></td><td>Standalone search bar (e.g. homepage)</td></tr>
                </table>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}
