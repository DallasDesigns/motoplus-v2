<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_filter( 'the_content', 'motoplus_single_content' );
function motoplus_single_content( $content ) {
    if ( ! is_singular(MOTOPLUS_CPT) || ! in_the_loop() || ! is_main_query() ) return $content;

    $id       = get_the_ID();
    $settings = motoplus_settings();
    $phone    = trim($settings['dealer_phone']);
    $tel      = preg_replace('/[^0-9+]/','', $phone);

    $status   = motoplus_meta($id,'status') ?: 'In Stock';
    $price    = motoplus_meta($id,'price');
    $prev     = motoplus_meta($id,'previous_price');
    $reduced  = ($prev && $price && $prev > $price);
    $is_new   = motoplus_is_new_arrival($id);

    $gallery_raw = motoplus_meta($id,'gallery');
    $gallery_ids = array_filter(array_map('absint', explode(',', $gallery_raw ?: '')));
    if (!$gallery_ids && has_post_thumbnail($id)) $gallery_ids = [get_post_thumbnail_id($id)];

    ob_start();
    ?>
    <div class="mp-single">

        <!-- Header -->
        <div class="mp-single-header">
            <div class="mp-single-header-text">
                <div class="mp-single-status mp-status--<?php echo sanitize_html_class(strtolower($status)); ?>"><?php echo esc_html($status); ?></div>
                <h1 class="mp-single-title"><?php the_title(); ?></h1>
            </div>
            <div class="mp-single-price-wrap">
                <?php if ($reduced): ?><div class="mp-single-prev-price"><?php echo esc_html(motoplus_money($prev)); ?></div><?php endif; ?>
                <div class="mp-single-price"><?php echo esc_html(motoplus_money($price)); ?></div>
            </div>
        </div>

        <!-- Main layout -->
        <div class="mp-single-layout">

            <!-- Gallery -->
            <div class="mp-single-gallery">
                <div class="mp-gallery-main" id="mp-gallery-main">
                    <?php if ($gallery_ids) : ?>
                    <img id="mp-main-img" src="<?php echo esc_url(wp_get_attachment_image_url($gallery_ids[0],'large')); ?>" alt="<?php the_title_attribute(); ?>" />
                    <?php if ($status !== 'In Stock') : ?>
                        <span class="mp-badge mp-badge--<?php echo sanitize_html_class(strtolower($status)); ?> mp-badge--overlay"><?php echo esc_html($status); ?></span>
                    <?php elseif ($reduced) : ?>
                        <span class="mp-badge mp-badge--reduced mp-badge--overlay">Price Drop</span>
                    <?php elseif ($is_new) : ?>
                        <span class="mp-badge mp-badge--new mp-badge--overlay">New In</span>
                    <?php endif; ?>
                    <?php else : ?>
                    <?php echo motoplus_vehicle_image($id,'large'); ?>
                    <?php endif; ?>
                </div>
                <?php if (count($gallery_ids) > 1) : ?>
                <div class="mp-gallery-thumbs">
                    <?php foreach ($gallery_ids as $i => $img_id) : ?>
                    <img class="mp-thumb <?php echo $i===0?'active':''; ?>"
                         src="<?php echo esc_url(wp_get_attachment_image_url($img_id,'thumbnail')); ?>"
                         data-full="<?php echo esc_url(wp_get_attachment_image_url($img_id,'large')); ?>"
                         alt="Photo <?php echo $i+1; ?>" />
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <aside class="mp-single-sidebar">
                <!-- Key specs grid -->
                <div class="mp-key-specs">
                    <?php
                    $key_specs = ['year'=>'Year','mileage'=>'Mileage','fuel'=>'Fuel','gearbox'=>'Transmission','engine'=>'Engine','body'=>'Body','colour'=>'Colour','doors'=>'Doors'];
                    foreach ($key_specs as $key => $label) :
                        $val = $key==='mileage' ? motoplus_miles(motoplus_meta($id,$key)) : motoplus_meta($id,$key);
                        if (!$val) continue;
                    ?>
                    <div class="mp-key-spec">
                        <span class="mp-spec-icon"><?php echo esc_html(motoplus_spec_icon($key)); ?></span>
                        <strong><?php echo esc_html($val); ?></strong>
                        <span><?php echo esc_html($label); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- CTA buttons -->
                <div class="mp-single-cta">
                    <?php if ($tel) : ?>
                    <a class="mp-btn mp-btn--ghost mp-btn--wide" href="tel:<?php echo esc_attr($tel); ?>">
                        ☎ <?php echo $phone ? esc_html($phone) : 'Call Us'; ?>
                    </a>
                    <?php endif; ?>
                    <a class="mp-btn mp-btn--primary mp-btn--wide" href="#mp-enquire">✉ Enquire Now</a>
                </div>

                <!-- Highlights -->
                <?php
                $highlights = [];
                if ($is_new) $highlights[] = 'New arrival';
                if ($reduced) $highlights[] = 'Recently reduced';
                if (stripos(motoplus_meta($id,'service_history'),'Full')!==false) $highlights[]='Full service history';
                if ((int)motoplus_meta($id,'owners')===1) $highlights[]='1 previous owner';
                $m = (int)motoplus_meta($id,'mileage');
                if ($m>0 && $m<40000) $highlights[]='Low mileage';
                if ($highlights) :
                ?>
                <div class="mp-highlights">
                    <?php foreach ($highlights as $h) : ?>
                    <span class="mp-highlight">✓ <?php echo esc_html($h); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </aside>
        </div>

        <!-- Full spec table -->
        <section class="mp-spec-section">
            <h2>Full Specification</h2>
            <div class="mp-spec-table">
                <?php foreach (motoplus_vehicle_fields() as $key => $field) :
                    if (in_array($key,['gallery','featured','previous_price'])) continue;
                    $val = $key==='mileage' ? motoplus_miles(motoplus_meta($id,$key)) : motoplus_meta($id,$key);
                    if (!$val) continue;
                ?>
                <div class="mp-spec-row">
                    <span><?php echo esc_html($field['label']); ?></span>
                    <strong><?php echo esc_html($val); ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- Description -->
        <?php if ($content) : ?>
        <section class="mp-description">
            <h2>Description</h2>
            <div class="mp-description-body"><?php echo $content; ?></div>
        </section>
        <?php endif; ?>

        <!-- Enquiry form -->
        <section id="mp-enquire" class="mp-enquiry">
            <h2>Enquire About This Vehicle</h2>
            <form class="mp-lead-form" id="mp-lead-form">
                <input type="hidden" name="vehicle_id"    value="<?php echo esc_attr($id); ?>" />
                <input type="hidden" name="vehicle_title" value="<?php echo esc_attr(get_the_title($id)); ?>" />
                <div class="mp-form-grid">
                    <div class="mp-form-field">
                        <label>Your Name *</label>
                        <input type="text" name="name" required placeholder="John Smith" />
                    </div>
                    <div class="mp-form-field">
                        <label>Phone Number *</label>
                        <input type="tel" name="phone" required placeholder="07700 900000" />
                    </div>
                    <div class="mp-form-field">
                        <label>Email Address</label>
                        <input type="email" name="email" placeholder="john@example.com" />
                    </div>
                </div>
                <div class="mp-form-field">
                    <label>Message</label>
                    <textarea name="message" rows="4" placeholder="Is this vehicle still available?">Is this vehicle still available?</textarea>
                </div>
                <button class="mp-btn mp-btn--primary" type="submit">✉ Send Enquiry</button>
                <div class="mp-lead-result" id="mp-lead-result"></div>
            </form>
        </section>

        <!-- Similar vehicles -->
        <?php
        $make_val = motoplus_meta($id,'make');
        if ($make_val) :
            $similar = new WP_Query(['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>3,'post__not_in'=>[$id],'meta_query'=>[['key'=>MOTOPLUS_META.'status','value'=>'Sold','compare'=>'!='],['key'=>MOTOPLUS_META.'make','value'=>$make_val]]]);
            if ($similar->have_posts()) :
        ?>
        <section class="mp-similar">
            <h2>Similar Vehicles</h2>
            <div class="mp-grid mp-grid--compact">
                <?php while($similar->have_posts()) { $similar->the_post(); motoplus_vehicle_card(get_the_ID()); } wp_reset_postdata(); ?>
            </div>
        </section>
        <?php endif; endif; ?>

    </div>

    <?php if ($tel) : ?>
    <div class="mp-mobile-sticky">
        <a href="tel:<?php echo esc_attr($tel); ?>">☎ Call</a>
        <a href="#mp-enquire">✉ Enquire</a>
    </div>
    <?php endif; ?>
    <?php

    return ob_get_clean();
}
