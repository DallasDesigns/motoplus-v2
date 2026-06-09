<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function motoplus_analytics_page() {
    $days    = absint( $_GET['days'] ?? 30 );
    $allowed = [7, 14, 30, 90];
    if ( ! in_array($days, $allowed) ) $days = 30;

    $totals  = motoplus_get_totals( $days );
    $daily   = motoplus_get_daily_views( $days );
    $top_views    = motoplus_get_top_vehicles( $days, 'view', 10 );
    $top_enquiries= motoplus_get_top_vehicles( $days, 'enquiry_sent', 10 );
    $top_whatsapp = motoplus_get_top_vehicles( $days, 'whatsapp_click', 10 );

    $total_views     = (int)($totals['view']->total ?? 0);
    $total_enquiries = (int)($totals['enquiry_sent']->total ?? 0);
    $total_whatsapp  = (int)($totals['whatsapp_click']->total ?? 0);
    $total_phone     = (int)($totals['phone_click']->total ?? 0);
    $conv_rate       = $total_views > 0 ? round( ($total_enquiries / $total_views) * 100, 1 ) : 0;

    // Build chart data
    $chart_labels = [];
    $chart_data   = [];
    $date_map     = [];
    foreach ( $daily as $row ) $date_map[$row->event_date] = (int)$row->total;
    for ( $i = $days - 1; $i >= 0; $i-- ) {
        $d = date('Y-m-d', strtotime("-{$i} days"));
        $chart_labels[] = date('d M', strtotime($d));
        $chart_data[]   = $date_map[$d] ?? 0;
    }
    ?>
    <div class="wrap mp-analytics-wrap">
        <div class="mp-analytics-header">
            <h1>📊 Analytics</h1>
            <div class="mp-period-tabs">
                <?php foreach([7=>'7 days',14=>'14 days',30=>'30 days',90=>'90 days'] as $d=>$label): ?>
                <a href="<?php echo esc_url(add_query_arg(['page'=>'motoplus-analytics','days'=>$d],admin_url('edit.php?post_type='.MOTOPLUS_CPT))); ?>"
                   class="mp-period-tab <?php echo $days===$d?'active':''; ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="mp-analytics-stats">
            <div class="mp-analytics-stat">
                <div class="mp-analytics-stat__icon" style="background:#e8f1fc;color:#1565c0">👁</div>
                <div class="mp-analytics-stat__body">
                    <div class="mp-analytics-stat__num"><?php echo number_format($total_views); ?></div>
                    <div class="mp-analytics-stat__label">Listing Views</div>
                    <div class="mp-analytics-stat__sub">Last <?php echo $days; ?> days</div>
                </div>
            </div>
            <div class="mp-analytics-stat">
                <div class="mp-analytics-stat__icon" style="background:#fef3c7;color:#d97706">✉️</div>
                <div class="mp-analytics-stat__body">
                    <div class="mp-analytics-stat__num"><?php echo number_format($total_enquiries); ?></div>
                    <div class="mp-analytics-stat__label">Enquiries Sent</div>
                    <div class="mp-analytics-stat__sub">Last <?php echo $days; ?> days</div>
                </div>
            </div>
            <div class="mp-analytics-stat">
                <div class="mp-analytics-stat__icon" style="background:#dcfce7;color:#16a34a">💬</div>
                <div class="mp-analytics-stat__body">
                    <div class="mp-analytics-stat__num"><?php echo number_format($total_whatsapp); ?></div>
                    <div class="mp-analytics-stat__label">WhatsApp Clicks</div>
                    <div class="mp-analytics-stat__sub">Last <?php echo $days; ?> days</div>
                </div>
            </div>
            <div class="mp-analytics-stat">
                <div class="mp-analytics-stat__icon" style="background:#f0e6ff;color:#7c3aed">📞</div>
                <div class="mp-analytics-stat__body">
                    <div class="mp-analytics-stat__num"><?php echo number_format($total_phone); ?></div>
                    <div class="mp-analytics-stat__label">Phone Clicks</div>
                    <div class="mp-analytics-stat__sub">Last <?php echo $days; ?> days</div>
                </div>
            </div>
            <div class="mp-analytics-stat mp-analytics-stat--accent">
                <div class="mp-analytics-stat__icon" style="background:rgba(255,255,255,.15);color:#fff">🎯</div>
                <div class="mp-analytics-stat__body">
                    <div class="mp-analytics-stat__num" style="color:#fff"><?php echo $conv_rate; ?>%</div>
                    <div class="mp-analytics-stat__label" style="color:rgba(255,255,255,.8)">Conversion Rate</div>
                    <div class="mp-analytics-stat__sub" style="color:rgba(255,255,255,.55)">Views → Enquiries</div>
                </div>
            </div>
        </div>

        <!-- Chart -->
        <div class="mp-analytics-card mp-analytics-chart-card">
            <div class="mp-analytics-card__header">
                <h3>Daily Listing Views</h3>
                <span class="mp-analytics-card__sub">Last <?php echo $days; ?> days</span>
            </div>
            <div class="mp-chart-wrap">
                <canvas id="mp-views-chart" height="90"></canvas>
            </div>
        </div>

        <!-- Tables row -->
        <div class="mp-analytics-grid">

            <!-- Top viewed vehicles -->
            <div class="mp-analytics-card">
                <div class="mp-analytics-card__header">
                    <h3>👁 Most Viewed Vehicles</h3>
                    <span class="mp-analytics-card__sub">Last <?php echo $days; ?> days</span>
                </div>
                <?php if ( $top_views ) : ?>
                <table class="mp-analytics-table">
                    <thead><tr><th>#</th><th>Vehicle</th><th>Views</th><th>Trend</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_views as $i => $row ) :
                        $stats  = motoplus_get_vehicle_stats( $row->vehicle_id );
                        $cr     = $stats['views'] > 0 ? round(($stats['enquiries_sent']/$stats['views'])*100,1) : 0;
                        $edit   = get_edit_post_link( $row->vehicle_id );
                        $link   = get_permalink( $row->vehicle_id );
                        $price  = motoplus_money( motoplus_meta($row->vehicle_id,'price') );
                    ?>
                    <tr>
                        <td class="mp-rank"><?php echo $i+1; ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit); ?>" class="mp-vehicle-link"><?php echo esc_html($row->post_title); ?></a>
                            <?php if($price): ?><span class="mp-vehicle-price"><?php echo esc_html($price); ?></span><?php endif; ?>
                        </td>
                        <td><strong><?php echo number_format($row->total); ?></strong></td>
                        <td>
                            <div class="mp-mini-bar-wrap" title="<?php echo $cr; ?>% enquiry rate">
                                <div class="mp-mini-bar" style="width:<?php echo min(100,($row->total/max(1,$top_views[0]->total))*100); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="mp-analytics-empty">No view data yet. Views are tracked automatically when buyers visit listings.</div>
                <?php endif; ?>
            </div>

            <!-- Top enquiry vehicles -->
            <div class="mp-analytics-card">
                <div class="mp-analytics-card__header">
                    <h3>✉️ Most Enquired Vehicles</h3>
                    <span class="mp-analytics-card__sub">Last <?php echo $days; ?> days</span>
                </div>
                <?php if ( $top_enquiries ) : ?>
                <table class="mp-analytics-table">
                    <thead><tr><th>#</th><th>Vehicle</th><th>Enquiries</th><th>Bar</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_enquiries as $i => $row ) :
                        $edit  = get_edit_post_link( $row->vehicle_id );
                        $price = motoplus_money( motoplus_meta($row->vehicle_id,'price') );
                    ?>
                    <tr>
                        <td class="mp-rank"><?php echo $i+1; ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit); ?>" class="mp-vehicle-link"><?php echo esc_html($row->post_title); ?></a>
                            <?php if($price): ?><span class="mp-vehicle-price"><?php echo esc_html($price); ?></span><?php endif; ?>
                        </td>
                        <td><strong><?php echo number_format($row->total); ?></strong></td>
                        <td>
                            <div class="mp-mini-bar-wrap">
                                <div class="mp-mini-bar mp-mini-bar--green" style="width:<?php echo min(100,($row->total/max(1,$top_enquiries[0]->total))*100); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="mp-analytics-empty">No enquiry data yet. Enquiries are tracked when buyers submit the form.</div>
                <?php endif; ?>
            </div>

            <!-- WhatsApp -->
            <div class="mp-analytics-card">
                <div class="mp-analytics-card__header">
                    <h3>💬 Top WhatsApp Vehicles</h3>
                    <span class="mp-analytics-card__sub">Last <?php echo $days; ?> days</span>
                </div>
                <?php if ( $top_whatsapp ) : ?>
                <table class="mp-analytics-table">
                    <thead><tr><th>#</th><th>Vehicle</th><th>Clicks</th><th>Bar</th></tr></thead>
                    <tbody>
                    <?php foreach ( $top_whatsapp as $i => $row ) :
                        $edit  = get_edit_post_link( $row->vehicle_id );
                        $price = motoplus_money( motoplus_meta($row->vehicle_id,'price') );
                    ?>
                    <tr>
                        <td class="mp-rank"><?php echo $i+1; ?></td>
                        <td>
                            <a href="<?php echo esc_url($edit); ?>" class="mp-vehicle-link"><?php echo esc_html($row->post_title); ?></a>
                            <?php if($price): ?><span class="mp-vehicle-price"><?php echo esc_html($price); ?></span><?php endif; ?>
                        </td>
                        <td><strong><?php echo number_format($row->total); ?></strong></td>
                        <td>
                            <div class="mp-mini-bar-wrap">
                                <div class="mp-mini-bar mp-mini-bar--whatsapp" style="width:<?php echo min(100,($row->total/max(1,$top_whatsapp[0]->total))*100); ?>%"></div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="mp-analytics-empty">No WhatsApp data yet. Clicks are tracked automatically.</div>
                <?php endif; ?>
            </div>

            <!-- Per-vehicle breakdown -->
            <div class="mp-analytics-card mp-analytics-card--full">
                <div class="mp-analytics-card__header">
                    <h3>📋 Full Stock Performance</h3>
                    <span class="mp-analytics-card__sub">All published vehicles — all time</span>
                </div>
                <?php
                $vehicles = get_posts(['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>-1,'orderby'=>'date','order'=>'DESC','post_status'=>'publish']);
                if ( $vehicles ) :
                ?>
                <table class="mp-analytics-table mp-analytics-table--full">
                    <thead>
                        <tr>
                            <th>Vehicle</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Views</th>
                            <th>Enquiries</th>
                            <th>WhatsApp</th>
                            <th>Phone</th>
                            <th>Conv.</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $vehicles as $v ) :
                        $stats  = motoplus_get_vehicle_stats( $v->ID );
                        $price  = motoplus_money( motoplus_meta($v->ID,'price') );
                        $status = motoplus_meta( $v->ID, 'status' ) ?: 'In Stock';
                        $cr     = $stats['views'] > 0 ? round(($stats['enquiries_sent']/$stats['views'])*100,1) : 0;
                        $edit   = get_edit_post_link($v->ID);
                        $view   = get_permalink($v->ID);
                        $status_class = sanitize_html_class(strtolower(str_replace(' ','-',$status)));
                    ?>
                    <tr>
                        <td><a href="<?php echo esc_url($edit); ?>" class="mp-vehicle-link"><?php echo esc_html($v->post_title); ?></a></td>
                        <td><?php echo esc_html($price); ?></td>
                        <td><span class="mp-status-dot mp-status-dot--<?php echo $status_class; ?>"><?php echo esc_html($status); ?></span></td>
                        <td><?php echo number_format($stats['views']); ?></td>
                        <td><?php echo number_format($stats['enquiries_sent']); ?></td>
                        <td><?php echo number_format($stats['whatsapp_clicks']); ?></td>
                        <td><?php echo number_format($stats['phone_clicks']); ?></td>
                        <td>
                            <?php if($cr > 0): ?>
                            <span class="mp-cr-badge mp-cr-badge--<?php echo $cr >= 5 ? 'good' : ($cr >= 2 ? 'ok' : 'low'); ?>"><?php echo $cr; ?>%</span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td><a href="<?php echo esc_url($view); ?>" target="_blank" class="mp-view-link" title="View listing">↗</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else : ?>
                <div class="mp-analytics-empty">No vehicles published yet.</div>
                <?php endif; ?>
            </div>

        </div><!-- /.mp-analytics-grid -->
    </div>

    <script>
    (function(){
        var labels = <?php echo json_encode($chart_labels); ?>;
        var data   = <?php echo json_encode($chart_data); ?>;
        var max    = Math.max.apply(null, data) || 1;

        var canvas = document.getElementById('mp-views-chart');
        if (!canvas) return;
        var ctx = canvas.getContext('2d');
        var W = canvas.offsetWidth; var H = canvas.offsetHeight || 120;
        canvas.width = W * (window.devicePixelRatio||1);
        canvas.height = H * (window.devicePixelRatio||1);
        ctx.scale(window.devicePixelRatio||1, window.devicePixelRatio||1);

        var padL=32, padR=16, padT=16, padB=36;
        var chartW = W - padL - padR;
        var chartH = H - padT - padB;
        var n = data.length;
        var barW = Math.max(2, (chartW / n) - 2);

        // Grid lines
        ctx.strokeStyle='#f1f5f9'; ctx.lineWidth=1;
        for(var g=0;g<=4;g++){
            var y = padT + chartH - (g/4)*chartH;
            ctx.beginPath(); ctx.moveTo(padL,y); ctx.lineTo(W-padR,y); ctx.stroke();
            ctx.fillStyle='#94a3b8'; ctx.font='10px Inter,sans-serif'; ctx.textAlign='right';
            ctx.fillText(Math.round((g/4)*max), padL-4, y+3);
        }

        // Gradient fill
        var grad = ctx.createLinearGradient(0,padT,0,padT+chartH);
        grad.addColorStop(0,'rgba(21,101,192,.3)');
        grad.addColorStop(1,'rgba(21,101,192,.02)');

        // Draw area
        ctx.beginPath();
        ctx.moveTo(padL, padT+chartH);
        for(var i=0;i<n;i++){
            var x = padL + (i/(n-1||1))*chartW;
            var y2 = padT + chartH - (data[i]/max)*chartH;
            if(i===0) ctx.lineTo(x,y2); else ctx.lineTo(x,y2);
        }
        ctx.lineTo(padL+(n-1)/(n-1||1)*chartW, padT+chartH);
        ctx.closePath();
        ctx.fillStyle=grad; ctx.fill();

        // Line
        ctx.beginPath(); ctx.strokeStyle='#1565c0'; ctx.lineWidth=2; ctx.lineJoin='round';
        for(var i=0;i<n;i++){
            var x = padL + (i/(n-1||1))*chartW;
            var y2 = padT + chartH - (data[i]/max)*chartH;
            if(i===0) ctx.moveTo(x,y2); else ctx.lineTo(x,y2);
        }
        ctx.stroke();

        // Dots on non-zero
        for(var i=0;i<n;i++){
            if(data[i]===0) continue;
            var x = padL + (i/(n-1||1))*chartW;
            var y2 = padT + chartH - (data[i]/max)*chartH;
            ctx.beginPath(); ctx.arc(x,y2,3.5,0,Math.PI*2);
            ctx.fillStyle='#1565c0'; ctx.fill();
        }

        // X labels — only every Nth to avoid overlap
        var skip = Math.ceil(n/8);
        ctx.fillStyle='#94a3b8'; ctx.font='10px Inter,sans-serif'; ctx.textAlign='center';
        for(var i=0;i<n;i++){
            if(i%skip===0){
                var x = padL + (i/(n-1||1))*chartW;
                ctx.fillText(labels[i], x, H-8);
            }
        }
    })();
    </script>
    <?php
}
