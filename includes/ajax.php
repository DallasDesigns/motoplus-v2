<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Frontend: AJAX live filter ────────────────────────────────────────────────
add_action( 'wp_ajax_motoplus_filter',        'motoplus_ajax_filter' );
add_action( 'wp_ajax_nopriv_motoplus_filter', 'motoplus_ajax_filter' );
function motoplus_ajax_filter() {
    check_ajax_referer( 'motoplus_front_nonce', 'nonce' );

    $search = sanitize_text_field( $_POST['search']   ?? '' );
    $make   = sanitize_text_field( $_POST['make']     ?? '' );
    $fuel   = sanitize_text_field( $_POST['fuel']     ?? '' );
    $gear   = sanitize_text_field( $_POST['gearbox']  ?? '' );
    $body   = sanitize_text_field( $_POST['body']     ?? '' );
    $price  = absint( $_POST['max_price'] ?? 0 );
    $sort   = sanitize_text_field( $_POST['sort']     ?? '' );
    $limit  = absint( $_POST['limit']    ?? 24 );
    $feat   = $_POST['featured_only']    ?? '0';

    $meta_query = [['key'=>MOTOPLUS_META.'status','value'=>'Sold','compare'=>'!=']];
    if ($feat === '1') $meta_query[] = ['key'=>MOTOPLUS_META.'featured','value'=>'1'];
    if ($make) $meta_query[] = ['key'=>MOTOPLUS_META.'make',    'value'=>$make];
    if ($fuel) $meta_query[] = ['key'=>MOTOPLUS_META.'fuel',    'value'=>$fuel];
    if ($gear) $meta_query[] = ['key'=>MOTOPLUS_META.'gearbox', 'value'=>$gear];
    if ($body) $meta_query[] = ['key'=>MOTOPLUS_META.'body',    'value'=>$body];
    if ($price) $meta_query[] = ['key'=>MOTOPLUS_META.'price',  'value'=>$price,'compare'=>'<=','type'=>'NUMERIC'];

    $args = ['post_type'=>MOTOPLUS_CPT,'posts_per_page'=>$limit,'meta_query'=>$meta_query,'orderby'=>'date','order'=>'DESC'];

    if ($search) {
        $ids = motoplus_keyword_ids($search);
        $args['post__in'] = $ids ?: [0];
    }
    if ($sort==='price-asc')   { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'price';   $args['order']='ASC';  }
    if ($sort==='price-desc')  { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'price';   $args['order']='DESC'; }
    if ($sort==='mileage-asc') { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'mileage'; $args['order']='ASC';  }
    if ($sort==='year-desc')   { $args['orderby']='meta_value_num'; $args['meta_key']=MOTOPLUS_META.'year';    $args['order']='DESC'; }

    $q = new WP_Query($args);
    ob_start();
    if ($q->have_posts()) {
        while ($q->have_posts()) { $q->the_post(); motoplus_vehicle_card(get_the_ID()); }
        wp_reset_postdata();
    } else {
        echo '<div class="mp-empty"><span>🚗</span><p>No vehicles found. Try adjusting the filters.</p><button class="mp-btn mp-btn--primary" id="mp-reset">Show All Vehicles</button></div>';
    }
    $html = ob_get_clean();

    wp_send_json_success(['html'=>$html,'count'=>$q->found_posts]);
}

// ── Frontend: Submit enquiry lead ─────────────────────────────────────────────
add_action( 'wp_ajax_motoplus_submit_lead',        'motoplus_ajax_submit_lead' );
add_action( 'wp_ajax_nopriv_motoplus_submit_lead', 'motoplus_ajax_submit_lead' );
function motoplus_ajax_submit_lead() {
    check_ajax_referer( 'motoplus_front_nonce', 'nonce' );

    $vehicle_id    = absint( $_POST['vehicle_id'] ?? 0 );
    $vehicle_title = sanitize_text_field( $_POST['vehicle_title'] ?? 'Vehicle Enquiry' );
    $name          = sanitize_text_field( $_POST['name'] ?? '' );
    $phone         = sanitize_text_field( $_POST['phone'] ?? '' );
    $email         = sanitize_email( $_POST['email'] ?? '' );
    $message       = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( ! $name || ! $phone ) {
        wp_send_json_error(['message'=>'Please enter your name and phone number.']);
    }
    if ( $email && ! is_email($email) ) {
        wp_send_json_error(['message'=>'Please enter a valid email address.']);
    }

    $lead_id = wp_insert_post([
        'post_type'   => MOTOPLUS_LEAD_CPT,
        'post_status' => 'publish',
        'post_title'  => $name . ' — ' . $vehicle_title,
    ]);

    if ( is_wp_error($lead_id) ) {
        wp_send_json_error(['message'=>'There was an error saving your enquiry.']);
    }

    $prefix = MOTOPLUS_META . 'lead_';
    update_post_meta( $lead_id, $prefix.'vehicle_id',    $vehicle_id );
    update_post_meta( $lead_id, $prefix.'vehicle_title', $vehicle_title );
    update_post_meta( $lead_id, $prefix.'name',          $name );
    update_post_meta( $lead_id, $prefix.'phone',         $phone );
    update_post_meta( $lead_id, $prefix.'email',         $email );
    update_post_meta( $lead_id, $prefix.'message',       $message );
    update_post_meta( $lead_id, $prefix.'status',        'New' );

    $s = motoplus_settings();
    $subject = 'New Enquiry: ' . $vehicle_title;
    $body    = "Vehicle: {$vehicle_title}\nName: {$name}\nPhone: {$phone}\nEmail: {$email}\n\nMessage:\n{$message}";
    wp_mail( $s['lead_email'], $subject, $body );

    wp_send_json_success(['message'=>"Thanks {$name}! We'll be in touch shortly."]);
}

// ── Admin: Generate AI description ────────────────────────────────────────────
add_action( 'wp_ajax_motoplus_generate_description', 'motoplus_ajax_generate_description' );
function motoplus_ajax_generate_description() {
    check_ajax_referer( 'motoplus_admin_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error(['message'=>'Permission denied.']);

    $d     = array_map('sanitize_text_field', $_POST['vehicle'] ?? []);
    $title = trim( ($d['year']??'') . ' ' . ($d['make']??'') . ' ' . ($d['model']??'') . ' ' . ($d['variant']??'') );
    $bits  = array_filter([
        $d['mileage'] ? number_format((float)$d['mileage']).' miles' : '',
        $d['fuel'] ?? '', $d['gearbox'] ?? '', $d['engine'] ?? '',
        $d['service_history'] ?? '', $d['colour'] ?? '',
    ]);

    $s = motoplus_settings();

    // Use OpenAI if key is configured
    if ( ! empty($s['openai_api_key']) ) {
        $prompt = "Write a short, professional and engaging vehicle description for a UK car dealer listing. Vehicle: {$title}. Key details: ".implode(', ',$bits).". Keep it under 100 words, friendly and accurate. Do not invent features.";
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => 20,
            'headers' => ['Authorization'=>'Bearer '.$s['openai_api_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['model'=>'gpt-4o-mini','messages'=>[['role'=>'user','content'=>$prompt]],'max_tokens'=>200]),
        ]);
        if ( ! is_wp_error($response) ) {
            $data = json_decode( wp_remote_retrieve_body($response), true );
            $text = $data['choices'][0]['message']['content'] ?? '';
            if ($text) wp_send_json_success(['description'=>trim($text)]);
        }
    }

    // Fallback local template
    $desc = "This {$title} is a well-presented example, offering a great blend of comfort, style and value.";
    if ($bits) $desc .= " Key details include: " . implode(', ', $bits) . ".";
    $desc .= "\n\nAvailable to view now — contact us today to arrange a test drive or ask any questions.";

    wp_send_json_success(['description'=>$desc]);
}

// ── Admin: Registration lookup ────────────────────────────────────────────────
add_action( 'wp_ajax_motoplus_lookup_vehicle', 'motoplus_ajax_lookup_vehicle' );
function motoplus_ajax_lookup_vehicle() {
    check_ajax_referer( 'motoplus_admin_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error(['message'=>'Permission denied.']);

    $s   = motoplus_settings();
    $reg = strtoupper( preg_replace('/\s+/','',$_POST['registration']??'') );
    if ( ! $reg ) wp_send_json_error(['message'=>'Enter a registration first.']);
    if ( $s['lookup_provider'] === 'manual' ) wp_send_json_error(['message'=>'Vehicle lookup is set to Manual Only in Motoplus Settings.']);
    if ( empty($s['lookup_api_key']) ) wp_send_json_error(['message'=>'Add your Lookup API key in Motoplus → Settings.']);

    // DVLA VES endpoint
    if ( $s['lookup_provider'] === 'dvla' ) {
        $resp = wp_remote_post('https://driver-vehicle-licensing.api.gov.uk/vehicle-enquiry/v1/vehicles',[
            'timeout' => 10,
            'headers' => ['x-api-key'=>$s['lookup_api_key'],'Content-Type'=>'application/json'],
            'body'    => wp_json_encode(['registrationNumber'=>$reg]),
        ]);
        if ( is_wp_error($resp) ) wp_send_json_error(['message'=>$resp->get_error_message()]);
        $code = wp_remote_retrieve_response_code($resp);
        if ($code !== 200) wp_send_json_error(['message'=>'DVLA returned HTTP '.$code.'. Check your API key.']);
        $v = json_decode(wp_remote_retrieve_body($resp),true);
        wp_send_json_success(['fields'=>[
            'registration' => $reg,
            'make'         => ucfirst(strtolower($v['make'] ?? '')),
            'fuel'         => ucfirst(strtolower($v['fuelType'] ?? '')),
            'colour'       => ucfirst(strtolower($v['colour'] ?? '')),
            'year'         => substr($v['monthOfFirstRegistration'] ?? '',0,4),
        ]]);
    }

    wp_send_json_error(['message'=>'Lookup provider framework ready. Connect the selected provider once your API access is active.']);
}

// ── Admin: Import from UsedCarsNI URL ─────────────────────────────────────────
add_action( 'wp_ajax_motoplus_import_usedcarsni', 'motoplus_ajax_import_url' );
function motoplus_ajax_import_url() {
    check_ajax_referer( 'motoplus_admin_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error(['message'=>'Permission denied.']);

    $url  = esc_url_raw( trim($_POST['url'] ?? '') );
    $host = wp_parse_url($url, PHP_URL_HOST);
    if ( ! $url || ! $host ) wp_send_json_error(['message'=>'Enter a valid URL.']);
    if ( stripos($host,'usedcarsni.com') === false ) wp_send_json_error(['message'=>'Only UsedCarsNI URLs are supported at the moment.']);

    $resp = wp_remote_get($url,['timeout'=>20,'headers'=>['User-Agent'=>'Motoplus/2.0; '.home_url('/')]]);
    if ( is_wp_error($resp) ) wp_send_json_error(['message'=>$resp->get_error_message()]);
    $code = wp_remote_retrieve_response_code($resp);
    if ($code < 200 || $code >= 300) wp_send_json_error(['message'=>'Could not fetch listing. HTTP '.$code.'. Try the HTML paste option instead.']);

    $html = wp_remote_retrieve_body($resp);
    if (!$html) wp_send_json_error(['message'=>'The listing returned no content.']);

    motoplus_process_import($html, $url);
}

// ── Admin: Import from pasted HTML ────────────────────────────────────────────
add_action( 'wp_ajax_motoplus_import_html', 'motoplus_ajax_import_html' );
function motoplus_ajax_import_html() {
    check_ajax_referer( 'motoplus_admin_nonce', 'nonce' );
    if ( ! current_user_can('edit_posts') ) wp_send_json_error(['message'=>'Permission denied.']);

    $html       = wp_unslash($_POST['html'] ?? '');
    $source_url = esc_url_raw(trim($_POST['source_url'] ?? 'https://www.usedcarsni.com/'));
    if (!$html || strlen(trim($html)) < 500) wp_send_json_error(['message'=>'Please paste the full page source HTML.']);

    motoplus_process_import($html, $source_url);
}

function motoplus_process_import($html, $source_url) {
    $data = motoplus_parse_usedcarsni($html, $source_url);

    if (empty($data['title']) && empty($data['fields']['make'])) {
        wp_send_json_error(['message'=>'Could not find enough vehicle data in that HTML.']);
    }

    $title = $data['title'] ?: trim(($data['fields']['make']??'').' '.($data['fields']['model']??''));
    $post_id = wp_insert_post([
        'post_type'    => MOTOPLUS_CPT,
        'post_status'  => 'draft',
        'post_title'   => $title,
        'post_content' => $data['description'] ?? '',
    ], true);

    if (is_wp_error($post_id)) wp_send_json_error(['message'=>$post_id->get_error_message()]);

    $allowed = array_keys(motoplus_vehicle_fields());
    foreach (($data['fields']??[]) as $key=>$value) {
        if (in_array($key,$allowed)) update_post_meta($post_id, MOTOPLUS_META.$key, sanitize_text_field($value));
    }
    update_post_meta($post_id, MOTOPLUS_META.'status', 'In Stock');
    if ($source_url) update_post_meta($post_id, MOTOPLUS_META.'import_source', esc_url_raw($source_url));

    $image_ids = motoplus_import_images($data['images']??[], $post_id, 20);
    if ($image_ids) {
        set_post_thumbnail($post_id, $image_ids[0]);
        update_post_meta($post_id, MOTOPLUS_META.'gallery', implode(',',$image_ids));
    }

    wp_send_json_success([
        'message'     => 'Imported as draft. Review before publishing.',
        'edit_url'    => get_edit_post_link($post_id,'raw'),
        'title'       => get_the_title($post_id),
        'image_count' => count($image_ids),
    ]);
}

function motoplus_parse_usedcarsni($html, $url) {
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    $xp = new DOMXPath($dom);

    $text = fn($q) => ($n=$xp->query($q)) && $n->length ? trim(preg_replace('/\s+/',' ',$n->item(0)->textContent)) : '';
    $attr = fn($q,$a) => ($n=$xp->query($q)) && $n->length ? trim($n->item(0)->getAttribute($a)) : '';

    // Extract JSON-LD
    $jsonld = [];
    foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
        $decoded = json_decode(html_entity_decode(trim($script->textContent),ENT_QUOTES|ENT_HTML5),true);
        if (!$decoded) continue;
        $items = isset($decoded['@graph']) ? $decoded['@graph'] : [$decoded];
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $type = is_array($item['@type']??'') ? implode(' ',$item['@type']) : ($item['@type']??'');
            if (stripos($type,'Car')!==false || isset($item['mileageFromOdometer'])) { $jsonld=$item; break 2; }
        }
    }

    // Title
    $title = html_entity_decode($jsonld['name']??'',ENT_QUOTES|ENT_HTML5);
    if (!$title) $title = $text('//h1') ?: $attr('//meta[@property="og:title"]','content');
    $title = preg_replace('/\s*For Sale\s*\|.*/i','',$title);
    $title = preg_replace('/^Used\s+/i','',$title);
    $title = trim(html_entity_decode($title,ENT_QUOTES|ENT_HTML5));

    // Description
    $desc = $jsonld['description'] ?? $attr('//meta[@property="og:description"]','content') ?: $attr('//meta[@name="description"]','content');
    $desc = html_entity_decode($desc,ENT_QUOTES|ENT_HTML5);
    $desc = preg_replace('/\s*Visit UsedCarsNI\.com.*/i','',$desc);
    $desc = trim($desc);

    // Fields from JSON-LD
    $fields = [];
    if ($jsonld) {
        if (!empty($jsonld['brand']['name']))               $fields['make']    = $jsonld['brand']['name'];
        if (!empty($jsonld['model']))                       $fields['model']   = is_array($jsonld['model']) ? ($jsonld['model']['name']??'') : $jsonld['model'];
        if (!empty($jsonld['vehicleModelDate']))            $fields['year']    = $jsonld['vehicleModelDate'];
        if (!empty($jsonld['color']))                       $fields['colour']  = $jsonld['color'];
        if (!empty($jsonld['bodyType']))                    $fields['body']    = $jsonld['bodyType'];
        if (!empty($jsonld['fuelType']))                    $fields['fuel']    = $jsonld['fuelType'];
        if (!empty($jsonld['vehicleTransmission']))         $fields['gearbox'] = $jsonld['vehicleTransmission'];
        if (!empty($jsonld['numberOfDoors']))               $fields['doors']   = $jsonld['numberOfDoors'];
        if (!empty($jsonld['offers']['price']))             $fields['price']   = $jsonld['offers']['price'];
        if (!empty($jsonld['mileageFromOdometer']['value']))$fields['mileage'] = $jsonld['mileageFromOdometer']['value'];
    }

    // Page text scraping
    $page = trim(preg_replace('/\s+/',' ',$dom->textContent));
    $labels = ['Mileage','Location','Payload','Colour','Color','Engine Size','Fuel Type','Transmission','Doors','Seats','Body Style','Owners','MOT Expiry','Standard Tax','Tax Band','CO2 Emission'];
    $others = implode('|',array_map(fn($l)=>preg_quote($l,'/'),$labels));
    foreach ([
        'Mileage'=>'mileage','Location'=>'location','Payload'=>'payload','Colour'=>'colour','Color'=>'colour',
        'Engine Size'=>'engine','Fuel Type'=>'fuel','Transmission'=>'gearbox','Doors'=>'doors','Seats'=>'seats',
        'Body Style'=>'body','Owners'=>'owners','MOT Expiry'=>'mot_expiry','Standard Tax'=>'road_tax','Tax Band'=>'tax_band','CO2 Emission'=>'co2'
    ] as $label=>$key) {
        if (!empty($fields[$key])) continue;
        if (preg_match('/'.preg_quote($label,'/').'\\s+(.{1,120}?)(?=\\s+(?:'.$others.')\\b|\\s+Seller\\b|$)/i',$page,$m)) {
            $fields[$key] = trim($m[1]);
        }
    }

    // Price from page
    if (empty($fields['price'])) {
        foreach ($xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," car-detail-price__price ")]') as $n) {
            if (preg_match('/([0-9]{1,3}(?:,[0-9]{3})+|[0-9]{3,})/',$n->textContent,$m)) { $fields['price']=preg_replace('/[^0-9.]/','',$m[1]); break; }
        }
    }
    if (empty($fields['price']) && preg_match('/£\s*([0-9][0-9,]*)/i',$page,$m)) $fields['price']=preg_replace('/[^0-9.]/','',$m[1]);

    // Infer from title
    $clean = preg_replace('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)\s+/i','',$title);
    if (preg_match('/\b(19[8-9]\d|20[0-3]\d)\b/',$clean,$m)) $fields['year'] = $fields['year'] ?? $m[1];
    $parts = preg_split('/\s+/',trim(preg_replace('/\b(19[8-9]\d|20[0-3]\d)\b/','',$clean,1)));
    if (count($parts)>=2) { $fields['make']=$fields['make']??$parts[0]; $fields['model']=$fields['model']??$parts[1]; if (empty($fields['variant'])&&count($parts)>2) $fields['variant']=implode(' ',array_slice($parts,2)); }

    // Clean numerics
    foreach (['mileage','doors','owners','seats'] as $k) {
        if (!empty($fields[$k]) && preg_match('/([0-9]{1,3}(?:,[0-9]{3})*|[0-9]+)/',$fields[$k],$m)) $fields[$k]=preg_replace('/[^0-9]/','',$m[1]);
    }
    if (!empty($fields['price']) && preg_match('/([0-9]{1,3}(?:,[0-9]{3})+|[0-9]{3,})(?:\.[0-9]{2})?/',html_entity_decode($fields['price'],ENT_QUOTES|ENT_HTML5),$m)) $fields['price']=preg_replace('/[^0-9.]/','',$m[1]);
    if (!empty($fields['gearbox'])) $fields['gearbox']=ucfirst(strtolower($fields['gearbox']));
    if (!empty($fields['fuel']))    $fields['fuel']   =ucfirst(strtolower($fields['fuel']));

    // Images
    $listing_id = '';
    if (preg_match('/"car_id"\s*:\s*"?(\d{6,})"?/i',$html,$m)) $listing_id=$m[1];
    if (!$listing_id) { $canonical=$attr('//link[@rel="canonical"]','href')?:$url; if(preg_match('/-(\d{6,})(?:\?|$)/',$canonical,$m)) $listing_id=$m[1]; }
    $listing_path = ($listing_id && strlen($listing_id)>=9) ? substr($listing_id,0,3).'/'.substr($listing_id,3,3).'/'.substr($listing_id,6,3) : '';

    $images = [];
    if (!empty($jsonld['image'])) $images = is_array($jsonld['image']) ? $jsonld['image'] : [$jsonld['image']];
    foreach ($xp->query('//meta[@property="og:image"]') as $n) $images[]=$n->getAttribute('content');
    foreach ($xp->query('//img') as $n) { $src=$n->getAttribute('data-src')?:$n->getAttribute('data-lazy')?:$n->getAttribute('src'); if($src) $images[]=$src; }
    if (preg_match_all('/https?:\/\/image\.usedcarsni\.com\/photos\/[^"\'\s<>,]+/i',$html,$m)) $images=array_merge($images,$m[0]);

    $base = wp_parse_url($url);
    $base_url = (!empty($base['scheme'])&&!empty($base['host'])) ? $base['scheme'].'://'.$base['host'] : 'https://www.usedcarsni.com';

    $images = array_values(array_unique(array_filter(array_map(function($src) use($base_url,$listing_path) {
        $src = html_entity_decode(trim($src)); $src=preg_replace('/\s+.*$/','',$src);
        $src = preg_replace('/(\.(?:jpg|jpeg|png|webp))(?:\/.*)?$/i','$1',$src);
        if (strpos($src,'//')===0) $src='https:'.$src;
        elseif (strpos($src,'/')===0) $src=$base_url.$src;
        if (!preg_match('/^https?:\/\//i',$src)) return '';
        if (stripos($src,'image.usedcarsni.com/photos/')===false) return '';
        if ($listing_path && strpos($src,'/'.$listing_path.'/')===false) return '';
        return $src;
    },$images))));

    return ['title'=>$title,'description'=>$desc,'fields'=>$fields,'images'=>$images];
}

function motoplus_import_images($urls, $post_id, $limit=20) {
    if (!$urls) return [];
    require_once ABSPATH.'wp-admin/includes/file.php';
    require_once ABSPATH.'wp-admin/includes/media.php';
    require_once ABSPATH.'wp-admin/includes/image.php';
    $ids=[];
    foreach (array_slice(array_unique($urls),0,$limit) as $url) {
        $tmp = download_url($url,20);
        if (is_wp_error($tmp)) continue;
        $name = basename(parse_url($url,PHP_URL_PATH));
        if (!$name||strpos($name,'.')===false) $name='motoplus-'.time().'-'.count($ids).'.jpg';
        $file=['name'=>sanitize_file_name($name),'tmp_name'=>$tmp];
        $id=media_handle_sideload($file,$post_id);
        if (is_wp_error($id)) { @unlink($tmp); continue; }
        $ids[]=$id;
    }
    return $ids;
}
