<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function motoplus_dashboard_page() {
    $live     = (int)(wp_count_posts(MOTOPLUS_CPT)->publish     ?? 0);
    $leads    = (int)(wp_count_posts(MOTOPLUS_LEAD_CPT)->publish ?? 0);
    $featured = motoplus_count_meta('featured','1');
    $reserved = motoplus_count_meta('status','Reserved');
    $sold     = motoplus_count_meta('status','Sold');

    // Recent leads
    $recent_leads = get_posts(['post_type'=>MOTOPLUS_LEAD_CPT,'posts_per_page'=>5,'orderby'=>'date','order'=>'DESC']);

    $p = MOTOPLUS_META.'lead_';
    ?>
    <div class="wrap mp-dashboard">
        <h1>🚗 Motoplus Dashboard</h1>

        <div class="mp-dash-stats">
            <div class="mp-stat"><span><?php echo $live; ?></span><label>Live Vehicles</label></div>
            <div class="mp-stat"><span><?php echo $featured; ?></span><label>Featured</label></div>
            <div class="mp-stat"><span><?php echo $reserved; ?></span><label>Reserved</label></div>
            <div class="mp-stat"><span><?php echo $sold; ?></span><label>Sold</label></div>
            <div class="mp-stat mp-stat--accent"><span><?php echo $leads; ?></span><label>Enquiries</label></div>
        </div>

        <div class="mp-dash-grid">
            <div class="mp-dash-card">
                <h2>Quick Actions</h2>
                <div class="mp-quick-actions">
                    <a class="mp-btn mp-btn--primary" href="<?php echo esc_url(admin_url('post-new.php?post_type='.MOTOPLUS_CPT)); ?>">+ Add Vehicle</a>
                    <a class="mp-btn mp-btn--ghost" href="<?php echo esc_url(admin_url('admin.php?page=motoplus-import')); ?>">⬇ Import Vehicle</a>
                    <a class="mp-btn mp-btn--ghost" href="<?php echo esc_url(admin_url('edit.php?post_type='.MOTOPLUS_CPT)); ?>">All Vehicles</a>
                    <a class="mp-btn mp-btn--ghost" href="<?php echo esc_url(admin_url('edit.php?post_type='.MOTOPLUS_LEAD_CPT)); ?>">All Enquiries</a>
                </div>
            </div>

            <div class="mp-dash-card">
                <h2>Recent Enquiries</h2>
                <?php if ($recent_leads) : ?>
                <table class="mp-lead-table">
                    <thead><tr><th>Name</th><th>Vehicle</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_leads as $lead) : ?>
                    <tr>
                        <td><a href="<?php echo get_edit_post_link($lead->ID); ?>"><?php echo esc_html(get_post_meta($lead->ID,$p.'name',true)); ?></a></td>
                        <td><?php echo esc_html(get_post_meta($lead->ID,$p.'vehicle_title',true)); ?></td>
                        <td><span class="mp-lead-status"><?php echo esc_html(get_post_meta($lead->ID,$p.'status',true)?:'New'); ?></span></td>
                        <td><?php echo esc_html(get_the_date('d M Y',$lead->ID)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <p style="color:#6b7280;">No enquiries yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function motoplus_count_meta($key,$value) {
    $q = new WP_Query(['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>1,'fields'=>'ids','meta_key'=>MOTOPLUS_META.$key,'meta_value'=>$value]);
    return $q->found_posts;
}
