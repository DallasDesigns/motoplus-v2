<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'admin_menu', 'motoplus_admin_menu' );
function motoplus_admin_menu() {
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Dashboard',        'Dashboard',  'edit_posts',    'motoplus-dashboard', 'motoplus_dashboard_page');
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Import Vehicle',   'Import',     'edit_posts',    'motoplus-import',    'motoplus_import_page');
    add_submenu_page('edit.php?post_type='.MOTOPLUS_CPT, 'Motoplus Settings','Settings',   'manage_options','motoplus-settings',  'motoplus_settings_page');
}

add_action( 'admin_init', function() {
    register_setting('motoplus_settings_group', MOTOPLUS_OPTION_KEY, 'motoplus_sanitize_settings');
});

function motoplus_sanitize_settings($input) {
    return [
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
        'lead_email'         => sanitize_email($input['lead_email'] ?? get_option('admin_email')),
        'dealer_phone'       => sanitize_text_field($input['dealer_phone'] ?? ''),
        'dealer_name'        => sanitize_text_field($input['dealer_name']  ?? ''),
        'stock_page_url'     => esc_url_raw($input['stock_page_url'] ?? ''),
        'lookup_provider'    => sanitize_text_field($input['lookup_provider'] ?? 'manual'),
        'lookup_api_key'     => sanitize_text_field($input['lookup_api_key'] ?? ''),
        'openai_api_key'     => sanitize_text_field($input['openai_api_key'] ?? ''),
    ];
}

function motoplus_settings_page() {
    $s = motoplus_settings();
    $o = MOTOPLUS_OPTION_KEY;
    ?>
    <div class="wrap mp-settings-wrap">
        <h1>⚙️ Motoplus Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('motoplus_settings_group'); ?>

            <div class="mp-settings-grid">

                <div class="mp-settings-card">
                    <h2>🎨 Branding & Design</h2>
                    <table class="form-table">
                        <?php foreach([
                            'accent_colour'=>'Accent / Badge Colour','button_colour'=>'Button Colour',
                            'button_text_colour'=>'Button Text Colour','page_background'=>'Page Background',
                            'card_background'=>'Card Background','border_colour'=>'Border Colour',
                            'text_colour'=>'Text Colour','muted_text_colour'=>'Muted Text'
                        ] as $key=>$label): ?>
                        <tr><th><?php echo esc_html($label); ?></th><td><input type="color" name="<?php echo $o; ?>[<?php echo $key; ?>]" value="<?php echo esc_attr($s[$key]); ?>" /></td></tr>
                        <?php endforeach; ?>
                        <tr><th>Button Radius</th><td><input type="number" min="0" max="40" name="<?php echo $o; ?>[button_radius]" value="<?php echo esc_attr($s['button_radius']); ?>" style="width:70px;"> px</td></tr>
                        <tr><th>Card Radius</th><td><input type="number" min="0" max="40" name="<?php echo $o; ?>[card_radius]" value="<?php echo esc_attr($s['card_radius']); ?>" style="width:70px;"> px</td></tr>
                    </table>
                </div>

                <div class="mp-settings-card">
                    <h2>🏢 Dealer Details</h2>
                    <table class="form-table">
                        <tr>
                            <th>Dealer Name</th>
                            <td><input class="regular-text" type="text" name="<?php echo $o; ?>[dealer_name]" value="<?php echo esc_attr($s['dealer_name']); ?>" /></td>
                        </tr>
                        <tr>
                            <th>Phone Number</th>
                            <td><input class="regular-text" type="text" name="<?php echo $o; ?>[dealer_phone]" value="<?php echo esc_attr($s['dealer_phone']); ?>" placeholder="028 0000 0000" />
                            <p class="description">Shown as a Call button on vehicle pages.</p></td>
                        </tr>
                        <tr>
                            <th>Lead Email</th>
                            <td><input class="regular-text" type="email" name="<?php echo $o; ?>[lead_email]" value="<?php echo esc_attr($s['lead_email']); ?>" /></td>
                        </tr>
                        <tr>
                            <th>Stock Page URL</th>
                            <td><input class="regular-text" type="url" name="<?php echo $o; ?>[stock_page_url]" value="<?php echo esc_attr($s['stock_page_url']); ?>" placeholder="https://example.com/cars-for-sale/" />
                            <p class="description">Used by standalone search bars (e.g. homepage). Leave blank to use current page.</p></td>
                        </tr>
                    </table>
                </div>

                <div class="mp-settings-card">
                    <h2>🔗 Integrations</h2>
                    <table class="form-table">
                        <tr>
                            <th>Lookup Provider</th>
                            <td>
                                <select name="<?php echo $o; ?>[lookup_provider]">
                                    <option value="manual" <?php selected($s['lookup_provider'],'manual'); ?>>Manual Only</option>
                                    <option value="dvla"   <?php selected($s['lookup_provider'],'dvla');   ?>>DVLA VES API</option>
                                    <option value="ukvd"   <?php selected($s['lookup_provider'],'ukvd');   ?>>UK Vehicle Data</option>
                                    <option value="custom" <?php selected($s['lookup_provider'],'custom'); ?>>Custom API</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Lookup API Key</th>
                            <td><input class="regular-text" type="password" name="<?php echo $o; ?>[lookup_api_key]" value="<?php echo esc_attr($s['lookup_api_key']); ?>" /></td>
                        </tr>
                        <tr>
                            <th>OpenAI API Key</th>
                            <td><input class="regular-text" type="password" name="<?php echo $o; ?>[openai_api_key]" value="<?php echo esc_attr($s['openai_api_key']); ?>" />
                            <p class="description">Optional. If set, the AI description button uses GPT-4o-mini. Otherwise a local template is used.</p></td>
                        </tr>
                    </table>
                </div>

                <div class="mp-settings-card mp-settings-card--shortcodes">
                    <h2>📋 Shortcodes</h2>
                    <table class="mp-shortcode-table">
                        <tr><td><code>[motoplus_stock]</code></td><td>Full stock listing page with search &amp; filters</td></tr>
                        <tr><td><code>[motoplus_featured]</code></td><td>Featured vehicles only (default 3)</td></tr>
                        <tr><td><code>[motoplus_latest limit="6"]</code></td><td>Latest arrivals</td></tr>
                        <tr><td><code>[motoplus_search]</code></td><td>Standalone search bar (e.g. homepage)</td></tr>
                    </table>
                </div>
            </div>

            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}
