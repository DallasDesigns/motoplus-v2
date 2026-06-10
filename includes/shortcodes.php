<?php
if ( ! defined( 'ABSPATH' ) ) exit;

add_shortcode( 'motoplus_stock',    'motoplus_stock_shortcode' );
add_shortcode( 'motoplus_featured', 'motoplus_featured_shortcode' );
add_shortcode( 'motoplus_latest',   'motoplus_latest_shortcode' );
add_shortcode( 'motoplus_search',   'motoplus_search_shortcode' );

function motoplus_stock_shortcode( $atts ) {
    $atts = shortcode_atts(['limit'=>24,'show_search'=>'yes'], $atts);
    return motoplus_render_stock(['limit'=>$atts['limit'],'featured_only'=>false,'show_search'=>$atts['show_search']==='yes']);
}
function motoplus_featured_shortcode( $atts ) {
    $atts = shortcode_atts(['limit'=>3], $atts);
    return motoplus_render_stock(['limit'=>$atts['limit'],'featured_only'=>true,'show_search'=>false]);
}
function motoplus_latest_shortcode( $atts ) {
    $atts = shortcode_atts(['limit'=>6], $atts);
    return motoplus_render_stock(['limit'=>$atts['limit'],'featured_only'=>false,'show_search'=>false]);
}
function motoplus_search_shortcode( $atts ) {
    ob_start();
    echo '<div class="mp-wrap">';
    motoplus_render_filter_bar();
    echo '</div>';
    return ob_get_clean();
}

function motoplus_render_stock( $opts ) {
    $limit        = absint($opts['limit'] ?? 24);
    $featured_only= $opts['featured_only'] ?? false;
    $show_search  = $opts['show_search'] ?? true;

    $meta_query = [['key'=>MOTOPLUS_META.'status','value'=>'Sold','compare'=>'!=']];
    if ($featured_only) $meta_query[] = ['key'=>MOTOPLUS_META.'featured','value'=>'1'];

    // GET filters for initial page load
    foreach (['make','fuel','gearbox','body'] as $f) {
        if (!empty($_GET[$f])) $meta_query[] = ['key'=>MOTOPLUS_META.$f,'value'=>sanitize_text_field(wp_unslash($_GET[$f]))];
    }
    if (!empty($_GET['max_price'])) $meta_query[] = ['key'=>MOTOPLUS_META.'price','value'=>floatval($_GET['max_price']),'compare'=>'<=','type'=>'NUMERIC'];

    $args = ['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>$limit,'meta_query'=>$meta_query,'orderby'=>'date','order'=>'DESC'];

    $keyword = sanitize_text_field(wp_unslash($_GET['vehicle_search'] ?? ''));
    if ($keyword !== '') {
        $ids = motoplus_keyword_ids($keyword);
        $args['post__in'] = $ids ?: [0];
    }

    $sort = sanitize_text_field($_GET['mp_sort'] ?? '');
    if ($sort === 'price-asc')   { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'price';   $args['order']='ASC';  }
    if ($sort === 'price-desc')  { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'price';   $args['order']='DESC'; }
    if ($sort === 'mileage-asc') { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'mileage'; $args['order']='ASC';  }
    if ($sort === 'year-desc')   { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'year';    $args['order']='DESC'; }

    $q = new WP_Query($args);

    ob_start();
    $ls = motoplus_settings();
    $per_row = absint($ls['listing_cards_per_row'] ?? 3);
    $def_view = $ls['listing_default_view'] ?? 'grid';
    echo '<div class="mp-wrap" id="mp-stock-wrap" data-limit="'.esc_attr($limit).'" data-featured="'.($featured_only?'1':'0').'" data-default-view="'.esc_attr($def_view).'" style="--mp-grid-cols:'.esc_attr($per_row).'">';

    if ($show_search) motoplus_render_filter_bar();

    if ($show_search) {
        echo '<div class="mp-results-bar">';
        echo '<span class="mp-count" id="mp-count">'.intval($q->found_posts).' vehicle'.($q->found_posts!==1?'s':'').' found</span>';
        echo '<div class="mp-sort-toggle">';
        if (($ls['listing_show_sort'] ?? '1') !== '0') {
            echo '<select id="mp-sort"><option value="">Newest First</option><option value="price-asc">Price: Low to High</option><option value="price-desc">Price: High to Low</option><option value="mileage-asc">Lowest Mileage</option><option value="year-desc">Newest Year</option></select>';
        }
        if (($ls['listing_show_view_toggle'] ?? '1') !== '0') {
            $active_grid = $def_view==='grid' ? 'active' : '';
            $active_list = $def_view==='list' ? 'active' : '';
            echo '<div class="mp-view-toggle"><button class="mp-view-btn '.$active_grid.'" data-view="grid" title="Grid">⊞</button><button class="mp-view-btn '.$active_list.'" data-view="list" title="List">☰</button></div>';
        }
        echo '</div></div>';
    }

    echo '<div class="mp-grid" id="mp-grid">';
    if ($q->have_posts()) {
        while ($q->have_posts()) { $q->the_post(); motoplus_vehicle_card(get_the_ID()); }
        wp_reset_postdata();
    } else {
        echo '<div class="mp-empty"><span>🚗</span><p>No vehicles found. Try adjusting the filters.</p><button class="mp-btn mp-btn--primary" id="mp-reset">Show All Vehicles</button></div>';
    }
    echo '</div></div>';

    return ob_get_clean();
}

function motoplus_render_filter_bar() {
    $s      = motoplus_settings();
    $action = $s['stock_page_url'] ? esc_url($s['stock_page_url']) : '';
    $makes  = motoplus_get_unique_meta('make');
    $fuels  = motoplus_get_unique_meta('fuel');
    $gears  = motoplus_get_unique_meta('gearbox');
    $bodies = motoplus_get_unique_meta('body');
    ?>
    <div class="mp-filter-bar" id="mp-filter-bar">
        <div class="mp-search-row">
            <div class="mp-search-field">
                <input type="text" id="mp-search" placeholder="🔍  Search make, model, keyword…" value="<?php echo esc_attr($_GET['vehicle_search']??''); ?>" />
            </div>
            <button class="mp-btn mp-btn--primary" id="mp-search-btn">Search</button>
            <button class="mp-btn mp-btn--ghost" id="mp-toggle-filters">Filters <span id="mp-filter-arrow">▾</span></button>
        </div>
        <div class="mp-filters" id="mp-filters" style="display:none;">
            <div class="mp-filter-grid">
                <div class="mp-filter-group">
                    <label>Make</label>
                    <select id="mp-filter-make">
                        <option value="">All Makes</option>
                        <?php foreach ($makes as $m): ?><option value="<?php echo esc_attr($m); ?>" <?php selected($_GET['make']??'',$m); ?>><?php echo esc_html($m); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-filter-group">
                    <label>Fuel Type</label>
                    <select id="mp-filter-fuel">
                        <option value="">All Fuels</option>
                        <?php foreach ($fuels as $f): ?><option value="<?php echo esc_attr($f); ?>" <?php selected($_GET['fuel']??'',$f); ?>><?php echo esc_html($f); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-filter-group">
                    <label>Transmission</label>
                    <select id="mp-filter-gearbox">
                        <option value="">All</option>
                        <?php foreach ($gears as $g): ?><option value="<?php echo esc_attr($g); ?>" <?php selected($_GET['gearbox']??'',$g); ?>><?php echo esc_html($g); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-filter-group">
                    <label>Body Type</label>
                    <select id="mp-filter-body">
                        <option value="">All Types</option>
                        <?php foreach ($bodies as $b): ?><option value="<?php echo esc_attr($b); ?>" <?php selected($_GET['body']??'',$b); ?>><?php echo esc_html($b); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="mp-filter-group">
                    <label>Max Price (£)</label>
                    <input type="number" id="mp-filter-price" placeholder="Any" value="<?php echo esc_attr($_GET['max_price']??''); ?>" />
                </div>
            </div>
            <div class="mp-filter-footer">
                <button class="mp-btn mp-btn--primary" id="mp-apply-filters">Apply Filters</button>
                <button class="mp-btn mp-btn--ghost"   id="mp-clear-filters">Clear All</button>
            </div>
        </div>
    </div>
    <?php
}

function motoplus_vehicle_card( $id ) {
    $status   = motoplus_meta($id,'status') ?: 'In Stock';
    $price    = motoplus_meta($id,'price');
    $prev     = motoplus_meta($id,'previous_price');
    $mileage  = motoplus_meta($id,'mileage');
    $fuel     = motoplus_meta($id,'fuel');
    $gearbox  = motoplus_meta($id,'gearbox');
    $year     = motoplus_meta($id,'year');
    $url      = get_permalink($id);
    $is_new   = motoplus_is_new_arrival($id);
    $featured = motoplus_meta($id,'featured');
    $reduced  = ($prev && $price && $prev > $price);
    ?>
    <?php $ls_s = motoplus_settings(); ?>
    <article class="mp-card" data-id="<?php echo esc_attr($id); ?>">
        <a class="mp-card-img" href="<?php echo esc_url($url); ?>">
            <?php echo motoplus_vehicle_image($id,'large'); ?>
            <?php if ($status !== 'In Stock') : ?>
                <span class="mp-badge mp-badge--<?php echo sanitize_html_class(strtolower($status)); ?>"><?php echo esc_html($status); ?></span>
            <?php elseif ($reduced) : ?>
                <span class="mp-badge mp-badge--reduced">Price Drop</span>
            <?php elseif ($is_new) : ?>
                <span class="mp-badge mp-badge--new">New In</span>
            <?php elseif ($featured === '1') : ?>
                <span class="mp-badge mp-badge--featured">Featured</span>
            <?php endif; ?>
        </a>
        <div class="mp-card-body">
            <h3 class="mp-card-title"><a href="<?php echo esc_url($url); ?>"><?php the_title(); ?></a></h3>
            <div class="mp-card-price">
                <?php if ($reduced): ?>
                <span class="mp-prev-price"><?php echo esc_html(motoplus_money($prev)); ?></span>
                <?php endif; ?>
                <?php echo esc_html(motoplus_money($price)); ?>
            </div>
            <ul class="mp-card-specs" style="list-style:none!important;padding:0!important;margin:0 0 14px!important">
                <?php if($year    && ($ls_s['listing_show_year']    ?? '1') !== '0') : ?><li style="list-style:none!important;display:inline-flex!important;align-items:center!important;gap:5px!important;padding:0!important;margin:0!important"><?php echo motoplus_spec_icon('year',14);    ?><span><?php echo esc_html($year); ?></span></li><?php endif; ?>
                <?php if($mileage && ($ls_s['listing_show_mileage'] ?? '1') !== '0') : ?><li style="list-style:none!important;display:inline-flex!important;align-items:center!important;gap:5px!important;padding:0!important;margin:0!important"><?php echo motoplus_spec_icon('mileage',14); ?><span><?php echo esc_html(motoplus_miles($mileage)); ?></span></li><?php endif; ?>
                <?php if($fuel    && ($ls_s['listing_show_fuel']    ?? '1') !== '0') : ?><li style="list-style:none!important;display:inline-flex!important;align-items:center!important;gap:5px!important;padding:0!important;margin:0!important"><?php echo motoplus_spec_icon('fuel',14);    ?><span><?php echo esc_html($fuel); ?></span></li><?php endif; ?>
                <?php if($gearbox && ($ls_s['listing_show_gearbox'] ?? '1') !== '0') : ?><li style="list-style:none!important;display:inline-flex!important;align-items:center!important;gap:5px!important;padding:0!important;margin:0!important"><?php echo motoplus_spec_icon('gearbox',14); ?><span><?php echo esc_html($gearbox); ?></span></li><?php endif; ?>
            </ul>
            <div class="mp-card-actions">
                <a class="mp-btn mp-btn--primary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($ls_s['listing_btn_primary_text'] ?: 'View Vehicle'); ?></a>
                <a class="mp-btn mp-btn--ghost"   href="<?php echo esc_url($url); ?>#mp-enquire"><?php echo esc_html($ls_s['listing_btn_enquire_text'] ?: 'Enquire'); ?></a>
            </div>
        </div>
    </article>
    <?php
}

function motoplus_keyword_ids( $keyword ) {
    global $wpdb;
    $like = '%' . $wpdb->esc_like($keyword) . '%';
    $keys = [MOTOPLUS_META.'make',MOTOPLUS_META.'model',MOTOPLUS_META.'variant',MOTOPLUS_META.'registration',MOTOPLUS_META.'fuel',MOTOPLUS_META.'gearbox',MOTOPLUS_META.'body'];
    $ph   = implode(',', array_fill(0,count($keys),'%s'));
    $sql  = $wpdb->prepare(
        "SELECT DISTINCT p.ID FROM {$wpdb->posts} p
         LEFT JOIN {$wpdb->postmeta} pm ON p.ID=pm.post_id AND pm.meta_key IN ($ph)
         WHERE p.post_type=%s AND p.post_status='publish'
         AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)",
        array_merge($keys,[MOTOPLUS_CPT,$like,$like])
    );
    return array_map('absint',$wpdb->get_col($sql));
}
