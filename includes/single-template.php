<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'the_content', 'motoplus_single_content' );
function motoplus_single_content( $content ) {
    if ( ! is_singular(MOTOPLUS_CPT) || ! in_the_loop() || ! is_main_query() ) return $content;

    $id       = get_the_ID();
    $settings = motoplus_settings();
    $phone    = trim($settings['dealer_phone']);
    $tel      = preg_replace('/[^0-9+]/', '', $phone);
    $dealer   = $settings['dealer_name'] ?: get_bloginfo('name');

    $status   = motoplus_meta($id,'status') ?: 'In Stock';
    $price    = motoplus_meta($id,'price');
    $prev     = motoplus_meta($id,'previous_price');
    $reduced  = ($prev && $price && (int)$prev > (int)$price);
    $is_new   = motoplus_is_new_arrival($id);

    // Gallery
    $gallery_raw = motoplus_meta($id,'gallery');
    $gallery_ids = array_filter(array_map('absint', explode(',', $gallery_raw ?: '')));
    if (!$gallery_ids && has_post_thumbnail($id)) $gallery_ids = [get_post_thumbnail_id($id)];
    $gallery_ids = array_values($gallery_ids);

    // All spec fields grouped for the table
    $spec_groups = [
        'Overview'           => ['make','model','variant','year','registration'],
        'Performance'        => ['engine','fuel','gearbox','body'],
        'Details'            => ['colour','doors','seats','mileage'],
        'History & Condition'=> ['owners','service_history','mot_expiry','road_tax','tax_band','co2'],
        'Other'              => ['location','payload'],
    ];

    // Highlights
    $highlights = [];
    if ($is_new) $highlights[]  = ['icon'=>'🆕','text'=>'New Arrival'];
    if ($reduced) $highlights[] = ['icon'=>'💰','text'=>'Price Reduced'];
    if (stripos(motoplus_meta($id,'service_history'),'Full')!==false) $highlights[]=['icon'=>'📋','text'=>'Full Service History'];
    if ((int)motoplus_meta($id,'owners')===1) $highlights[]=['icon'=>'👤','text'=>'1 Previous Owner'];
    $mil = (int)motoplus_meta($id,'mileage');
    if ($mil>0 && $mil<40000) $highlights[]=['icon'=>'⬇️','text'=>'Low Mileage'];

    ob_start();
    ?>
    <div class="mp-single">

        <!-- Breadcrumb -->
        <nav class="mp-breadcrumb">
            <a href="<?php echo esc_url(get_post_type_archive_link(MOTOPLUS_CPT)); ?>">← Back to Stock</a>
        </nav>

        <!-- Vehicle title bar -->
        <div class="mp-single-titlebar">
            <div class="mp-single-titlebar__left">
                <h1 class="mp-single-h1"><?php the_title(); ?></h1>
                <div class="mp-single-meta">
                    <?php $reg = motoplus_meta($id,'registration'); if($reg): ?>
                    <span class="mp-reg-plate"><?php echo esc_html(strtoupper($reg)); ?></span>
                    <?php endif; ?>
                    <span class="mp-status-pill mp-status-pill--<?php echo sanitize_html_class(strtolower(str_replace(' ','-',$status))); ?>"><?php echo esc_html($status); ?></span>
                </div>
            </div>
            <div class="mp-single-titlebar__price">
                <?php if ($reduced): ?>
                <div class="mp-was-price">Was <?php echo esc_html(motoplus_money($prev)); ?></div>
                <?php endif; ?>
                <div class="mp-sale-price"><?php echo esc_html(motoplus_money($price)); ?></div>
                <?php if ($reduced): ?>
                <div class="mp-saving">Save <?php echo esc_html(motoplus_money((int)$prev-(int)$price)); ?></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main two-column layout -->
        <div class="mp-single-body">

            <!-- LEFT: Gallery -->
            <div class="mp-single-left">
                <div class="mp-gallery-wrap">
                    <div class="mp-gallery-stage">
                        <?php if ($gallery_ids) : ?>
                        <img id="mp-main-img"
                             src="<?php echo esc_url(wp_get_attachment_image_url($gallery_ids[0],'large')); ?>"
                             alt="<?php the_title_attribute(); ?>" />
                        <?php if ($status !== 'In Stock') : ?>
                        <span class="mp-img-badge mp-img-badge--<?php echo sanitize_html_class(strtolower(str_replace(' ','-',$status))); ?>"><?php echo esc_html($status); ?></span>
                        <?php elseif ($reduced) : ?>
                        <span class="mp-img-badge mp-img-badge--reduced">Price Drop</span>
                        <?php elseif ($is_new) : ?>
                        <span class="mp-img-badge mp-img-badge--new">New In</span>
                        <?php endif; ?>
                        <?php if (count($gallery_ids) > 1): ?>
                        <div class="mp-img-counter"><span id="mp-img-num">1</span> / <?php echo count($gallery_ids); ?></div>
                        <?php endif; ?>
                        <?php else : ?>
                        <?php echo motoplus_vehicle_image($id,'large'); ?>
                        <?php endif; ?>
                    </div>

                    <?php if (count($gallery_ids) > 1) : ?>
                    <div class="mp-thumb-strip">
                        <?php foreach ($gallery_ids as $i => $img_id) : ?>
                        <button class="mp-thumb-btn <?php echo $i===0?'is-active':''; ?>"
                                data-full="<?php echo esc_url(wp_get_attachment_image_url($img_id,'large')); ?>"
                                data-index="<?php echo $i+1; ?>"
                                type="button">
                            <img src="<?php echo esc_url(wp_get_attachment_image_url($img_id,'thumbnail')); ?>" alt="Photo <?php echo $i+1; ?>" />
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Key specs strip under gallery -->
                <div class="mp-keyspec-strip">
                    <?php
                    $strip = ['year'=>'Year','mileage'=>'Mileage','fuel'=>'Fuel','gearbox'=>'Gearbox','engine'=>'Engine','colour'=>'Colour','doors'=>'Doors','body'=>'Body'];
                    foreach ($strip as $k => $label) :
                        $v = $k==='mileage' ? motoplus_miles(motoplus_meta($id,$k)) : motoplus_meta($id,$k);
                        if (!$v) continue;
                    ?>
                    <div class="mp-keyspec-item">
                        <span class="mp-keyspec-icon"><?php echo esc_html(motoplus_spec_icon($k)); ?></span>
                        <span class="mp-keyspec-val"><?php echo esc_html($v); ?></span>
                        <span class="mp-keyspec-label"><?php echo esc_html($label); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Description -->
                <?php if (trim($content)) : ?>
                <div class="mp-description-block">
                    <h2>About This Vehicle</h2>
                    <div class="mp-description-body"><?php echo $content; ?></div>
                </div>
                <?php endif; ?>

                <!-- Full spec table -->
                <div class="mp-fullspec-block">
                    <h2>Full Specification</h2>
                    <?php foreach ($spec_groups as $group_name => $keys) :
                        $rows = [];
                        foreach ($keys as $k) {
                            $field = motoplus_vehicle_fields()[$k] ?? null;
                            if (!$field) continue;
                            $v = $k==='mileage' ? motoplus_miles(motoplus_meta($id,$k)) : motoplus_meta($id,$k);
                            if ($v) $rows[] = ['label'=>$field['label'],'val'=>$v];
                        }
                        if (!$rows) continue;
                    ?>
                    <div class="mp-spec-group">
                        <h3><?php echo esc_html($group_name); ?></h3>
                        <table class="mp-spec-tbl">
                            <?php foreach ($rows as $row) : ?>
                            <tr>
                                <td><?php echo esc_html($row['label']); ?></td>
                                <td><?php echo esc_html($row['val']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    </div>
                    <?php endforeach; ?>
                </div>

            </div><!-- /.mp-single-left -->

            <!-- RIGHT: Sidebar -->
            <aside class="mp-single-right">

                <!-- Price card -->
                <div class="mp-price-card">
                    <div class="mp-price-card__price">
                        <?php if ($reduced): ?>
                        <span class="mp-price-was"><?php echo esc_html(motoplus_money($prev)); ?></span>
                        <?php endif; ?>
                        <span class="mp-price-main"><?php echo esc_html(motoplus_money($price)); ?></span>
                    </div>

                    <?php if ($tel) : ?>
                    <a class="mp-cta-call mp-track-click"
                       href="tel:<?php echo esc_attr($tel); ?>"
                       data-vehicle-id="<?php echo esc_attr($id); ?>"
                       data-event="phone_click">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                        <?php echo esc_html($phone ?: 'Call Us'); ?>
                    </a>
                    <?php endif; ?>

                    <?php
                    // WhatsApp button — shown if WhatsApp number is set in settings
                    $wa_raw    = trim( $settings['whatsapp_number'] ?? $settings['dealer_phone'] ?? '' );
                    $wa_number = preg_replace('/[^0-9+]/', '', $wa_raw );
                    // Convert UK local numbers to international format
                    if ( $wa_number && $wa_number[0] === '0' ) {
                        $wa_number = '44' . ltrim( $wa_number, '0' );
                    }
                    $wa_title   = urlencode( get_the_title($id) );
                    $wa_price   = motoplus_money( $price );
                    $wa_message = urlencode( "Hi, I'm interested in the {$wa_title}" . ($wa_price ? " ({$wa_price})" : '') . " — is it still available?" );
                    $wa_url     = "https://wa.me/{$wa_number}?text={$wa_message}";
                    if ( $wa_number ) :
                    ?>
                    <a class="mp-cta-whatsapp mp-track-click"
                       href="<?php echo esc_url($wa_url); ?>"
                       target="_blank"
                       rel="noopener"
                       data-vehicle-id="<?php echo esc_attr($id); ?>"
                       data-event="whatsapp_click">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                        WhatsApp Us
                    </a>
                    <?php endif; ?>

                    <a class="mp-cta-enquire mp-track-click"
                       href="#mp-enquire"
                       data-vehicle-id="<?php echo esc_attr($id); ?>"
                       data-event="enquiry_click">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        Enquire About This Vehicle
                    </a>
                </div>

                <!-- Highlights -->
                <?php if ($highlights) : ?>
                <div class="mp-highlights-card">
                    <h3>Vehicle Highlights</h3>
                    <ul>
                        <?php foreach ($highlights as $h) : ?>
                        <li><span><?php echo esc_html($h['icon']); ?></span><?php echo esc_html($h['text']); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <!-- Quick spec summary -->
                <div class="mp-quickspec-card">
                    <h3>Key Details</h3>
                    <?php
                    $qs = ['make'=>'Make','model'=>'Model','year'=>'Year','mileage'=>'Mileage','fuel'=>'Fuel Type','gearbox'=>'Transmission','mot_expiry'=>'MOT Expiry','service_history'=>'Service History','owners'=>'Owners'];
                    foreach ($qs as $k => $label) :
                        $v = $k==='mileage' ? motoplus_miles(motoplus_meta($id,$k)) : motoplus_meta($id,$k);
                        if (!$v) continue;
                    ?>
                    <div class="mp-qs-row">
                        <span><?php echo esc_html($label); ?></span>
                        <strong><?php echo esc_html($v); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Dealer info -->
                <?php if ($dealer || $phone) : ?>
                <div class="mp-dealer-card">
                    <h3>Contact the Dealer</h3>
                    <?php if ($dealer) : ?><p class="mp-dealer-name"><?php echo esc_html($dealer); ?></p><?php endif; ?>
                    <?php if ($tel) : ?><a class="mp-dealer-phone" href="tel:<?php echo esc_attr($tel); ?>"><?php echo esc_html($phone); ?></a><?php endif; ?>
                </div>
                <?php endif; ?>

            </aside><!-- /.mp-single-right -->
        </div><!-- /.mp-single-body -->

        <!-- Enquiry form (full width) -->
        <section id="mp-enquire" class="mp-enquiry-section">
            <div class="mp-enquiry-inner">
                <div class="mp-enquiry-header">
                    <h2>Enquire About This Vehicle</h2>
                    <p>Complete the form below and we'll get back to you as soon as possible.</p>
                </div>
                <form class="mp-lead-form" id="mp-lead-form">
                    <input type="hidden" name="vehicle_id"    value="<?php echo esc_attr($id); ?>" />
                    <input type="hidden" name="vehicle_title" value="<?php echo esc_attr(get_the_title($id)); ?>" />
                    <div class="mp-form-row">
                        <div class="mp-form-field">
                            <label for="mp-f-name">Full Name <span>*</span></label>
                            <input id="mp-f-name" type="text" name="name" required placeholder="John Smith" />
                        </div>
                        <div class="mp-form-field">
                            <label for="mp-f-phone">Phone Number <span>*</span></label>
                            <input id="mp-f-phone" type="tel" name="phone" required placeholder="07700 900000" />
                        </div>
                        <div class="mp-form-field">
                            <label for="mp-f-email">Email Address</label>
                            <input id="mp-f-email" type="email" name="email" placeholder="john@example.com" />
                        </div>
                    </div>
                    <div class="mp-form-field">
                        <label for="mp-f-msg">Message</label>
                        <textarea id="mp-f-msg" name="message" rows="4" placeholder="Is this vehicle still available?">Is this vehicle still available?</textarea>
                    </div>
                    <div class="mp-form-submit">
                        <button class="mp-submit-btn" type="submit">Send Enquiry</button>
                        <p class="mp-form-note">We typically respond within a few hours during business hours.</p>
                    </div>
                    <div class="mp-lead-result" id="mp-lead-result"></div>
                </form>
            </div>
        </section>

        <!-- Similar vehicles -->
        <?php
        $make_val = motoplus_meta($id,'make');
        if ($make_val) :
            $similar = new WP_Query(['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>3,'post__not_in'=>[$id],'meta_query'=>[['key'=>MOTOPLUS_META.'status','value'=>'Sold','compare'=>'!='],['key'=>MOTOPLUS_META.'make','value'=>$make_val]]]);
            if ($similar->have_posts()) :
        ?>
        <section class="mp-similar-section">
            <h2>More <?php echo esc_html($make_val); ?> Vehicles</h2>
            <div class="mp-grid">
                <?php while($similar->have_posts()) { $similar->the_post(); motoplus_vehicle_card(get_the_ID()); } wp_reset_postdata(); ?>
            </div>
        </section>
        <?php endif; endif; ?>

    </div><!-- /.mp-single -->

    <?php if ($tel || $wa_number) : ?>
    <div class="mp-mobile-sticky">
        <?php if ($tel) : ?>
        <a class="mp-mobile-sticky__call mp-track-click"
           href="tel:<?php echo esc_attr($tel); ?>"
           data-vehicle-id="<?php echo esc_attr($id); ?>"
           data-event="phone_click">📞 Call</a>
        <?php endif; ?>
        <?php if (!empty($wa_number)) : ?>
        <a class="mp-mobile-sticky__whatsapp mp-track-click"
           href="<?php echo esc_url($wa_url); ?>"
           target="_blank" rel="noopener"
           data-vehicle-id="<?php echo esc_attr($id); ?>"
           data-event="whatsapp_click">💬 WhatsApp</a>
        <?php endif; ?>
        <a class="mp-mobile-sticky__enquire mp-track-click"
           href="#mp-enquire"
           data-vehicle-id="<?php echo esc_attr($id); ?>"
           data-event="enquiry_click">✉ Enquire</a>
    </div>
    <?php endif; ?>
    <?php

    return ob_get_clean();
}
