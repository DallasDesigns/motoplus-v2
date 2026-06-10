<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// ── Register the Add Vehicle wizard page ─────────────────────────────────────
add_action( 'admin_menu', 'motoplus_add_vehicle_menu', 20 );
function motoplus_add_vehicle_menu() {
    add_submenu_page(
        'edit.php?post_type=' . MOTOPLUS_CPT,
        'Add New Vehicle',
        '+ Add Vehicle',
        'edit_posts',
        'motoplus-add-vehicle',
        'motoplus_add_vehicle_page'
    );
}

// ── Handle form submission ────────────────────────────────────────────────────
add_action( 'admin_post_motoplus_save_vehicle_wizard', 'motoplus_handle_wizard_save' );
function motoplus_handle_wizard_save() {
    if ( ! wp_verify_nonce( $_POST['motoplus_wizard_nonce'] ?? '', 'motoplus_wizard_save' ) ) {
        wp_die( 'Security check failed.' );
    }
    if ( ! current_user_can( 'edit_posts' ) ) wp_die( 'Permission denied.' );

    $make    = sanitize_text_field( $_POST['make']    ?? '' );
    $model   = sanitize_text_field( $_POST['model']   ?? '' );
    $variant = sanitize_text_field( $_POST['variant'] ?? '' );
    $year    = absint( $_POST['year']   ?? 0 );
    $status  = sanitize_text_field( $_POST['status']  ?? 'In Stock' );

    // Auto-generate post title
    $title_parts = array_filter( [ $year ?: '', $make, $model, $variant ] );
    $post_title  = implode( ' ', $title_parts ) ?: 'New Vehicle';

    $post_status = 'publish';
    if ( ! empty( $_POST['save_draft'] ) ) $post_status = 'draft';

    $post_id = wp_insert_post( [
        'post_type'    => MOTOPLUS_CPT,
        'post_status'  => $post_status,
        'post_title'   => $post_title,
        'post_content' => sanitize_textarea_field( $_POST['description'] ?? '' ),
    ], true );

    if ( is_wp_error( $post_id ) ) {
        wp_redirect( add_query_arg( ['page'=>'motoplus-add-vehicle','error'=>'1'], admin_url('edit.php?post_type='.MOTOPLUS_CPT) ) );
        exit;
    }

    // Save all meta fields
    $fields = motoplus_vehicle_fields();
    foreach ( $fields as $key => $field ) {
        if ( $field['type'] === 'hidden' || ! isset( $_POST[$key] ) ) continue;
        if ( $field['type'] === 'checkbox' ) {
            update_post_meta( $post_id, MOTOPLUS_META . $key, isset($_POST[$key]) ? '1' : '0' );
        } elseif ( $field['type'] === 'number' ) {
            $raw = preg_replace( '/[^0-9]/', '', $_POST[$key] ?? '' );
            if ( $raw !== '' ) update_post_meta( $post_id, MOTOPLUS_META . $key, (string)(int)$raw );
        } else {
            update_post_meta( $post_id, MOTOPLUS_META . $key, sanitize_text_field( $_POST[$key] ) );
        }
    }

    // Gallery
    if ( ! empty( $_POST['gallery_ids'] ) ) {
        $gallery = sanitize_text_field( $_POST['gallery_ids'] );
        update_post_meta( $post_id, MOTOPLUS_META . 'gallery', $gallery );
        // Set first image as featured
        $ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
        if ( $ids ) set_post_thumbnail( $post_id, $ids[0] );
    }

    // Track price reduction date if previous price set
    $price = absint( $_POST['price'] ?? 0 );
    $prev  = absint( $_POST['previous_price'] ?? 0 );
    if ( $prev && $price && $price < $prev ) {
        update_post_meta( $post_id, MOTOPLUS_META . 'price_reduced_date', time() );
    }

    $redirect_url = add_query_arg(
        ['saved' => '1', 'post_id' => $post_id],
        admin_url( 'edit.php?post_type=' . MOTOPLUS_CPT . '&page=motoplus-add-vehicle' )
    );
    wp_redirect( $redirect_url );
    exit;
}

// ── Render the wizard page ────────────────────────────────────────────────────
function motoplus_add_vehicle_page() {
    $saved   = ! empty( $_GET['saved'] );
    $post_id = absint( $_GET['post_id'] ?? 0 );
    $error   = ! empty( $_GET['error'] );
    $s       = motoplus_settings();
    ?>
    <div class="wrap mpw-wrap">

        <?php if ( $saved && $post_id ) : ?>
        <div class="mpw-success-banner">
            <span>✅ Vehicle added successfully!</span>
            <div class="mpw-success-actions">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank">View Listing →</a>
                <a href="<?php echo esc_url( get_edit_post_link( $post_id ) ); ?>">Edit Details</a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=motoplus-add-vehicle' ) ); ?>">Add Another</a>
            </div>
        </div>
        <?php endif; ?>

        <?php if ( $error ) : ?>
        <div class="mpw-error-banner">⚠️ Something went wrong saving the vehicle. Please try again.</div>
        <?php endif; ?>

        <div class="mpw-header">
            <div class="mpw-header-left">
                <h1>Add New Vehicle</h1>
                <p>Fill in the details below — required fields are marked with a <span class="mpw-req">*</span></p>
            </div>
            <div class="mpw-header-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=motoplus-import' ) ); ?>" class="mpw-btn mpw-btn--ghost">⬇ Import Instead</a>
            </div>
        </div>

        <!-- Progress bar -->
        <div class="mpw-progress" id="mpw-progress">
            <?php foreach( [
                1 => ['icon'=>'🔍','label'=>'Lookup'],
                2 => ['icon'=>'🚗','label'=>'Details'],
                3 => ['icon'=>'💰','label'=>'Pricing'],
                4 => ['icon'=>'📸','label'=>'Photos'],
                5 => ['icon'=>'✅','label'=>'Publish'],
            ] as $num => $step ) : ?>
            <div class="mpw-progress-step <?php echo $num === 1 ? 'is-active' : ''; ?>" data-step="<?php echo $num; ?>">
                <div class="mpw-progress-bubble"><?php echo $step['icon']; ?></div>
                <span><?php echo esc_html( $step['label'] ); ?></span>
            </div>
            <?php if ( $num < 5 ) : ?><div class="mpw-progress-line"></div><?php endif; ?>
            <?php endforeach; ?>
        </div>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="mpw-form">
            <?php wp_nonce_field( 'motoplus_wizard_save', 'motoplus_wizard_nonce' ); ?>
            <input type="hidden" name="action" value="motoplus_save_vehicle_wizard" />

            <!-- ── STEP 1: Reg Lookup ── -->
            <div class="mpw-step mpw-step--active" id="mpw-step-1">
                <div class="mpw-step-card">
                    <div class="mpw-step-header">
                        <div class="mpw-step-num">1</div>
                        <div>
                            <h2>Registration Lookup</h2>
                            <p>Enter the registration to auto-fill vehicle details, or skip to enter manually.</p>
                        </div>
                    </div>

                    <div class="mpw-reg-hero">
                        <div class="mpw-reg-plate-input">
                            <div class="mpw-plate-wrap">
                                <div class="mpw-plate-dot"></div>
                                <input type="text" id="mpw-reg-input" placeholder="AB12 CDE"
                                       style="font-family:'Roboto Condensed','Arial Narrow',Arial,sans-serif;font-size:28px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;text-align:center;width:260px;padding:16px 20px;background:#f5c400;border:4px solid #333;border-radius:8px;color:#111;outline:none" />
                            </div>
                            <button type="button" id="mpw-reg-lookup-btn" class="mpw-btn mpw-btn--primary mpw-btn--lg">
                                🔍 Look Up Vehicle
                            </button>
                        </div>
                        <div id="mpw-lookup-result" class="mpw-lookup-result"></div>
                        <div id="mpw-lookup-preview" class="mpw-lookup-preview" style="display:none">
                            <div class="mpw-lookup-found">
                                <span class="mpw-lookup-found-icon">✅</span>
                                <div class="mpw-lookup-found-details" id="mpw-lookup-found-details"></div>
                            </div>
                        </div>
                    </div>

                    <?php if ( ($s['lookup_provider'] ?? 'manual') === 'manual' ) : ?>
                    <div class="mpw-lookup-notice">
                        <span>💡</span>
                        <span>Automatic lookup is set to <strong>Manual Only</strong>. To enable DVLA lookup, go to <a href="<?php echo esc_url(admin_url('edit.php?post_type='.MOTOPLUS_CPT.'&page=motoplus-settings')); ?>">Settings → Integrations</a>.</span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="mpw-step-nav">
                    <span></span>
                    <button type="button" class="mpw-btn mpw-btn--primary" onclick="mpwGoStep(2)">Continue to Details →</button>
                </div>
            </div>

            <!-- ── STEP 2: Vehicle Details ── -->
            <div class="mpw-step" id="mpw-step-2">
                <div class="mpw-step-card">
                    <div class="mpw-step-header">
                        <div class="mpw-step-num">2</div>
                        <div>
                            <h2>Vehicle Details</h2>
                            <p>The key information buyers look for first.</p>
                        </div>
                    </div>

                    <!-- Required fields - big and prominent -->
                    <div class="mpw-required-banner">
                        <span class="mpw-required-banner-label">Essential Info</span>
                        <div class="mpw-req-grid">
                            <div class="mpw-req-field">
                                <label>Make <span class="mpw-req">*</span></label>
                                <input type="text" name="make" id="mpw-make" placeholder="e.g. BMW" required />
                            </div>
                            <div class="mpw-req-field">
                                <label>Model <span class="mpw-req">*</span></label>
                                <input type="text" name="model" id="mpw-model" placeholder="e.g. 3 Series" required />
                            </div>
                            <div class="mpw-req-field">
                                <label>Variant / Trim</label>
                                <input type="text" name="variant" id="mpw-variant" placeholder="e.g. M Sport" />
                            </div>
                            <div class="mpw-req-field">
                                <label>Year <span class="mpw-req">*</span></label>
                                <input type="number" name="year" id="mpw-year" placeholder="2021" min="1990" max="<?php echo date('Y')+1; ?>" required />
                            </div>
                            <div class="mpw-req-field">
                                <label>Registration</label>
                                <input type="text" name="registration" id="mpw-reg-field" placeholder="AB12 CDE" style="text-transform:uppercase" />
                            </div>
                            <div class="mpw-req-field">
                                <label>Mileage <span class="mpw-req">*</span></label>
                                <input type="number" name="mileage" id="mpw-mileage" placeholder="42000" required />
                            </div>
                        </div>
                    </div>

                    <!-- Secondary fields -->
                    <div class="mpw-field-section">
                        <h3>Technical Specs</h3>
                        <div class="mpw-field-grid-4">
                            <div class="mpw-field">
                                <label>Fuel Type</label>
                                <select name="fuel" id="mpw-fuel">
                                    <option value="">Select…</option>
                                    <option>Petrol</option><option>Diesel</option>
                                    <option>Hybrid</option><option>Plug-in Hybrid</option><option>Electric</option>
                                </select>
                            </div>
                            <div class="mpw-field">
                                <label>Transmission</label>
                                <select name="gearbox" id="mpw-gearbox">
                                    <option value="">Select…</option>
                                    <option>Manual</option><option>Automatic</option><option>Semi-Automatic</option>
                                </select>
                            </div>
                            <div class="mpw-field">
                                <label>Engine Size</label>
                                <input type="text" name="engine" id="mpw-engine" placeholder="2.0L" />
                            </div>
                            <div class="mpw-field">
                                <label>Body Type</label>
                                <input type="text" name="body" id="mpw-body" placeholder="Hatchback" />
                            </div>
                            <div class="mpw-field">
                                <label>Colour</label>
                                <input type="text" name="colour" id="mpw-colour" placeholder="Grey" />
                            </div>
                            <div class="mpw-field">
                                <label>Doors</label>
                                <select name="doors">
                                    <option value="">Select…</option>
                                    <option>2</option><option>3</option><option>4</option><option>5</option>
                                </select>
                            </div>
                            <div class="mpw-field">
                                <label>Seats</label>
                                <input type="number" name="seats" placeholder="5" min="1" max="20" />
                            </div>
                            <div class="mpw-field">
                                <label>Location</label>
                                <input type="text" name="location" placeholder="Belfast" />
                            </div>
                        </div>
                    </div>

                    <div class="mpw-field-section">
                        <h3>History & Condition</h3>
                        <div class="mpw-field-grid-4">
                            <div class="mpw-field">
                                <label>Previous Owners</label>
                                <input type="number" name="owners" placeholder="1" min="1" />
                            </div>
                            <div class="mpw-field">
                                <label>Service History</label>
                                <select name="service_history">
                                    <option value="">Select…</option>
                                    <option>Full Service History</option>
                                    <option>Part Service History</option>
                                    <option>No Service History</option>
                                </select>
                            </div>
                            <div class="mpw-field">
                                <label>MOT Expiry</label>
                                <input type="text" name="mot_expiry" placeholder="March 2027" />
                            </div>
                            <div class="mpw-field">
                                <label>Road Tax</label>
                                <input type="text" name="road_tax" placeholder="£190/year" />
                            </div>
                            <div class="mpw-field">
                                <label>Tax Band</label>
                                <input type="text" name="tax_band" placeholder="E" />
                            </div>
                            <div class="mpw-field">
                                <label>CO2 Emissions</label>
                                <input type="text" name="co2" placeholder="135 g/km" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mpw-step-nav">
                    <button type="button" class="mpw-btn mpw-btn--ghost" onclick="mpwGoStep(1)">← Back</button>
                    <button type="button" class="mpw-btn mpw-btn--primary" onclick="mpwGoStep(3)">Continue to Pricing →</button>
                </div>
            </div>

            <!-- ── STEP 3: Pricing ── -->
            <div class="mpw-step" id="mpw-step-3">
                <div class="mpw-step-card">
                    <div class="mpw-step-header">
                        <div class="mpw-step-num">3</div>
                        <div>
                            <h2>Pricing & Status</h2>
                            <p>Set the sale price and how you want this vehicle to appear.</p>
                        </div>
                    </div>

                    <div class="mpw-price-hero">
                        <div class="mpw-price-main-field">
                            <label>Sale Price <span class="mpw-req">*</span></label>
                            <div class="mpw-price-input-wrap">
                                <span class="mpw-price-symbol">£</span>
                                <input type="number" name="price" id="mpw-price" placeholder="18995" required class="mpw-price-input" />
                            </div>
                            <div class="mpw-finance-preview" id="mpw-finance-preview"></div>
                        </div>
                        <div class="mpw-price-secondary-field">
                            <label>Previous Price <span class="mpw-hint">(shows "Price Drop" badge)</span></label>
                            <div class="mpw-price-input-wrap">
                                <span class="mpw-price-symbol">£</span>
                                <input type="number" name="previous_price" placeholder="19995" class="mpw-price-input mpw-price-input--secondary" />
                            </div>
                        </div>
                    </div>

                    <div class="mpw-field-section">
                        <h3>Finance & Status</h3>
                        <div class="mpw-field-grid-3">
                            <div class="mpw-field mpw-field--full-label">
                                <label>Finance Text <span class="mpw-hint">(shown on listing — leave blank to auto-calculate)</span></label>
                                <input type="text" name="finance_text" placeholder="From £189/month — 9.9% APR" />
                            </div>
                            <div class="mpw-field">
                                <label>Status</label>
                                <select name="status" class="mpw-status-select">
                                    <option value="In Stock" selected>✅ In Stock</option>
                                    <option value="Reserved">🟡 Reserved</option>
                                    <option value="Coming Soon">🔵 Coming Soon</option>
                                    <option value="Sold">⬛ Sold</option>
                                </select>
                            </div>
                            <div class="mpw-field">
                                <label>Featured Vehicle</label>
                                <label class="mpw-toggle-switch">
                                    <input type="checkbox" name="featured" value="1" />
                                    <span class="mpw-toggle-track"></span>
                                    <span class="mpw-toggle-label-text">Show in featured section on homepage</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mpw-field-section">
                        <h3>Description <span class="mpw-hint">(optional — use AI to generate a draft)</span></h3>
                        <textarea name="description" id="mpw-description" rows="6"
                                  placeholder="Describe the vehicle's condition, features, and anything a buyer should know…"
                                  class="mpw-textarea"></textarea>
                        <button type="button" id="mpw-gen-desc" class="mpw-btn mpw-btn--ai">
                            ✨ Generate Description with AI
                        </button>
                        <span id="mpw-ai-result" class="mpw-ai-result"></span>
                    </div>
                </div>

                <div class="mpw-step-nav">
                    <button type="button" class="mpw-btn mpw-btn--ghost" onclick="mpwGoStep(2)">← Back</button>
                    <button type="button" class="mpw-btn mpw-btn--primary" onclick="mpwGoStep(4)">Continue to Photos →</button>
                </div>
            </div>

            <!-- ── STEP 4: Photos ── -->
            <div class="mpw-step" id="mpw-step-4">
                <div class="mpw-step-card">
                    <div class="mpw-step-header">
                        <div class="mpw-step-num">4</div>
                        <div>
                            <h2>Vehicle Photos</h2>
                            <p>Add as many photos as possible — listings with 10+ photos get significantly more enquiries.</p>
                        </div>
                    </div>

                    <!-- Photo quality bar -->
                    <div class="mpw-photo-quality" id="mpw-photo-quality">
                        <div class="mpw-photo-quality-label">
                            <span>Photo Quality</span>
                            <span id="mpw-photo-count-text">0 photos added</span>
                        </div>
                        <div class="mpw-photo-quality-bar">
                            <div class="mpw-photo-quality-fill" id="mpw-photo-quality-fill"></div>
                        </div>
                        <div class="mpw-photo-quality-tips" id="mpw-photo-quality-tips">
                            Add at least 1 photo to publish this listing.
                        </div>
                    </div>

                    <!-- Drop zone -->
                    <div class="mpw-dropzone" id="mpw-dropzone">
                        <div class="mpw-dropzone-inner">
                            <span class="mpw-dropzone-icon">📸</span>
                            <h3>Click to add photos</h3>
                            <p>Select multiple photos at once for fastest upload</p>
                            <button type="button" class="mpw-btn mpw-btn--primary" id="mpw-add-photos-btn">
                                Choose Photos
                            </button>
                        </div>
                    </div>

                    <!-- Photo grid preview -->
                    <div class="mpw-photo-grid" id="mpw-photo-grid"></div>
                    <input type="hidden" name="gallery_ids" id="mpw-gallery-ids" value="" />

                    <div class="mpw-photo-tips">
                        <strong>📷 Photo tips for more enquiries:</strong>
                        <ul>
                            <li>Front 3/4 angle first — this is the listing thumbnail</li>
                            <li>Both sides, rear, interior front & rear</li>
                            <li>Dashboard, boot, any extras (sunroof, alloys, sat-nav)</li>
                            <li>Any minor marks honestly shown — builds buyer trust</li>
                        </ul>
                    </div>
                </div>

                <div class="mpw-step-nav">
                    <button type="button" class="mpw-btn mpw-btn--ghost" onclick="mpwGoStep(3)">← Back</button>
                    <button type="button" class="mpw-btn mpw-btn--primary" onclick="mpwGoStep(5)">Review & Publish →</button>
                </div>
            </div>

            <!-- ── STEP 5: Review & Publish ── -->
            <div class="mpw-step" id="mpw-step-5">
                <div class="mpw-step-card">
                    <div class="mpw-step-header">
                        <div class="mpw-step-num">5</div>
                        <div>
                            <h2>Review & Publish</h2>
                            <p>Check everything looks right before your listing goes live.</p>
                        </div>
                    </div>

                    <!-- Summary preview -->
                    <div class="mpw-review-grid">
                        <div class="mpw-review-preview">
                            <div class="mpw-review-photo" id="mpw-review-photo">
                                <span>📸</span><p>No photos yet</p>
                            </div>
                            <div class="mpw-review-title" id="mpw-review-title">—</div>
                            <div class="mpw-review-price" id="mpw-review-price">—</div>
                            <div class="mpw-review-specs" id="mpw-review-specs"></div>
                        </div>

                        <div class="mpw-review-checklist" id="mpw-review-checklist">
                            <!-- Populated by JS -->
                        </div>
                    </div>
                </div>

                <div class="mpw-step-nav mpw-step-nav--publish">
                    <button type="button" class="mpw-btn mpw-btn--ghost" onclick="mpwGoStep(4)">← Back</button>
                    <div class="mpw-publish-actions">
                        <button type="submit" name="save_draft" value="1" class="mpw-btn mpw-btn--ghost mpw-btn--lg">
                            💾 Save as Draft
                        </button>
                        <button type="submit" class="mpw-btn mpw-btn--publish mpw-btn--lg">
                            🚀 Publish Listing
                        </button>
                    </div>
                </div>
            </div>

        </form>
    </div>

    <script>
    (function($){
        var currentStep = 1;
        var gallery = [];
        var mediaFrame;

        // ── Step navigation ────────────────────────────────────────────────
        window.mpwGoStep = function(step) {
            if ( step > currentStep && ! mpwValidateStep(currentStep) ) return;
            $('.mpw-step').removeClass('mpw-step--active');
            $('#mpw-step-' + step).addClass('mpw-step--active');
            $('.mpw-progress-step').removeClass('is-active is-done');
            for (var i = 1; i <= 5; i++) {
                var $s = $('.mpw-progress-step[data-step="'+i+'"]');
                if (i < step) $s.addClass('is-done');
                else if (i === step) $s.addClass('is-active');
            }
            currentStep = step;
            $('html,body').animate({scrollTop: $('.mpw-progress').offset().top - 30}, 200);
            if (step === 5) mpwBuildReview();
        };

        // ── Validation ─────────────────────────────────────────────────────
        function mpwValidateStep(step) {
            if (step === 2) {
                var make = $('[name="make"]').val().trim();
                var model = $('[name="model"]').val().trim();
                var year = $('[name="year"]').val().trim();
                var mileage = $('[name="mileage"]').val().trim();
                if (!make || !model || !year || !mileage) {
                    mpwShakeRequired();
                    mpwToast('Please fill in Make, Model, Year and Mileage before continuing.', 'error');
                    return false;
                }
            }
            if (step === 3) {
                var price = $('[name="price"]').val().trim();
                if (!price) {
                    mpwToast('Please enter a sale price before continuing.', 'error');
                    return false;
                }
            }
            return true;
        }

        function mpwShakeRequired() {
            var fields = ['make','model','year','mileage'];
            fields.forEach(function(f) {
                var $i = $('[name="'+f+'"]');
                if (!$i.val().trim()) {
                    $i.addClass('mpw-field-error');
                    $i.closest('.mpw-req-field').addClass('mpw-shake');
                    setTimeout(function(){ $i.closest('.mpw-req-field').removeClass('mpw-shake'); }, 600);
                } else {
                    $i.removeClass('mpw-field-error');
                }
            });
        }

        $('input,select').on('input change', function(){
            $(this).removeClass('mpw-field-error');
        });

        // ── Toast notifications ────────────────────────────────────────────
        function mpwToast(msg, type) {
            var $t = $('<div class="mpw-toast mpw-toast--'+type+'">'+msg+'</div>');
            $('body').append($t);
            setTimeout(function(){ $t.addClass('mpw-toast--show'); }, 10);
            setTimeout(function(){ $t.removeClass('mpw-toast--show'); setTimeout(function(){ $t.remove(); }, 300); }, 3500);
        }

        // ── Reg lookup ─────────────────────────────────────────────────────
        $('#mpw-reg-lookup-btn').on('click', function(){
            var reg = $('#mpw-reg-input').val().trim().replace(/\s/g,'').toUpperCase();
            if (!reg) { mpwToast('Enter a registration first.', 'error'); return; }
            var $btn = $(this).prop('disabled',true).text('Looking up…');
            var $res = $('#mpw-lookup-result').removeClass('success error').text('');
            $('#mpw-lookup-preview').hide();

            $.post(ajaxurl, {
                action: 'motoplus_lookup_vehicle',
                nonce: motoplusAdmin.nonce,
                registration: reg
            }, function(res){
                $btn.prop('disabled',false).html('🔍 Look Up Vehicle');
                if (res.success && res.data.fields) {
                    var f = res.data.fields;
                    var details = [];
                    if(f.make) { $('[name="make"]').val(f.make); details.push('<strong>'+f.make+'</strong>'); }
                    if(f.model) { $('[name="model"]').val(f.model); details.push(f.model); }
                    if(f.year) { $('[name="year"]').val(f.year); details.push(f.year); }
                    if(f.fuel) { $('[name="fuel"]').val(f.fuel); details.push(f.fuel); }
                    if(f.colour) { $('[name="colour"]').val(f.colour); details.push(f.colour); }
                    if(f.registration) { $('[name="registration"]').val(f.registration); $('#mpw-reg-field').val(f.registration); }
                    $('#mpw-lookup-found-details').html(details.join(' · '));
                    $('#mpw-lookup-preview').slideDown(200);
                    $res.addClass('success').text('✅ Vehicle found — details populated below.');
                    // Auto-advance after 1.5s
                    setTimeout(function(){ mpwGoStep(2); }, 1500);
                } else {
                    var msg = (res.data && res.data.message) ? res.data.message : 'Could not look up that registration. You can enter details manually.';
                    $res.addClass('error').text('⚠️ ' + msg);
                    // Still copy the reg into the field
                    $('[name="registration"]').val(reg);
                    $('#mpw-reg-field').val(reg);
                }
            });
        });

        // Enter key on reg input
        $('#mpw-reg-input').on('keydown', function(e){
            if (e.key === 'Enter') { e.preventDefault(); $('#mpw-reg-lookup-btn').trigger('click'); }
        }).on('input', function(){
            this.value = this.value.toUpperCase();
        });

        // ── Finance preview ────────────────────────────────────────────────
        $('[name="price"]').on('input', function(){
            var price = parseInt($(this).val()) || 0;
            if (price > 1000) {
                var mo = Math.ceil((price * 1.08) / 48);
                $('#mpw-finance-preview').html('Estimated finance: <strong>From £'+mo.toLocaleString()+'/month</strong> (48 months, 8% APR)').show();
            } else {
                $('#mpw-finance-preview').hide();
            }
        });

        // ── AI description ─────────────────────────────────────────────────
        $('#mpw-gen-desc').on('click', function(){
            var $btn = $(this).prop('disabled',true).text('Generating…');
            var $res = $('#mpw-ai-result').removeClass('success error').text('');
            var vehicle = {
                make: $('[name="make"]').val(), model: $('[name="model"]').val(),
                variant: $('[name="variant"]').val(), year: $('[name="year"]').val(),
                mileage: $('[name="mileage"]').val(), fuel: $('[name="fuel"]').val(),
                gearbox: $('[name="gearbox"]').val(), engine: $('[name="engine"]').val(),
                service_history: $('[name="service_history"]').val(), colour: $('[name="colour"]').val(),
            };
            $.post(ajaxurl, {action:'motoplus_generate_description', nonce:motoplusAdmin.nonce, vehicle:vehicle}, function(res){
                $btn.prop('disabled',false).html('✨ Generate Description with AI');
                if (res.success) {
                    $('#mpw-description').val(res.data.description);
                    $res.addClass('success').text('✅ Description generated — review and edit as needed.');
                } else {
                    $res.addClass('error').text('Could not generate description. Fill in Make, Model and Year first.');
                }
            });
        });

        // ── Gallery ────────────────────────────────────────────────────────
        function renderGallery() {
            var $grid = $('#mpw-photo-grid').empty();
            gallery.forEach(function(img, idx){
                var $item = $('<div class="mpw-photo-item" data-id="'+img.id+'">'+
                    '<img src="'+img.thumb+'" alt="Photo '+(idx+1)+'" />'+
                    (idx===0 ? '<span class="mpw-photo-main-badge">Main</span>' : '')+
                    '<button type="button" class="mpw-photo-remove" title="Remove">✕</button>'+
                '</div>');
                $grid.append($item);
            });
            $('#mpw-gallery-ids').val(gallery.map(function(i){return i.id;}).join(','));
            updatePhotoQuality();
        }

        function updatePhotoQuality() {
            var n = gallery.length;
            var pct = Math.min(100, Math.round((n / 12) * 100));
            $('#mpw-photo-quality-fill').css('width', pct + '%').css('background',
                n === 0 ? '#ef4444' : n < 5 ? '#f59e0b' : '#22c55e');
            var msg = n === 0 ? '0 photos — add at least 1 to publish.' :
                      n < 5  ? n+' photo'+(n>1?'s':'')+' — add more for better results (aim for 10+).' :
                      n < 10 ? n+' photos — good! Add a few more to maximise enquiries.' :
                                n+' photos — excellent! Buyers love a well-photographed listing. 👍';
            $('#mpw-photo-count-text').text(n + ' photo' + (n !== 1 ? 's' : '') + ' added');
            $('#mpw-photo-quality-tips').text(msg);
        }

        $('#mpw-add-photos-btn, #mpw-dropzone').on('click', function(e){
            if ($(e.target).is('#mpw-dropzone') && !$(e.target).is('.mpw-dropzone-inner, .mpw-dropzone-inner *')) return;
            if (mediaFrame) { mediaFrame.open(); return; }
            mediaFrame = wp.media({ title:'Select Vehicle Photos', button:{text:'Add Photos'}, multiple:true });
            mediaFrame.on('select', function(){
                mediaFrame.state().get('selection').each(function(att){
                    if (!gallery.find(function(i){return i.id===att.id;})) {
                        var thumb = att.attributes.sizes && att.attributes.sizes.thumbnail ?
                            att.attributes.sizes.thumbnail.url : att.attributes.url;
                        gallery.push({id: att.id, url: att.attributes.url, thumb: thumb});
                    }
                });
                renderGallery();
            });
            mediaFrame.open();
        });

        $(document).on('click', '.mpw-photo-remove', function(){
            var id = $(this).closest('.mpw-photo-item').data('id');
            gallery = gallery.filter(function(i){return i.id !== id;});
            renderGallery();
        });

        // Drag to reorder photos
        $(document).on('mousedown', '.mpw-photo-item', function(e){
            if ($(e.target).is('.mpw-photo-remove')) return;
        });

        // ── Review step ────────────────────────────────────────────────────
        function mpwBuildReview() {
            var make    = $('[name="make"]').val();
            var model   = $('[name="model"]').val();
            var variant = $('[name="variant"]').val();
            var year    = $('[name="year"]').val();
            var price   = $('[name="price"]').val();
            var mileage = $('[name="mileage"]').val();
            var fuel    = $('[name="fuel"]').val();
            var gearbox = $('[name="gearbox"]').val();

            var title = [year, make, model, variant].filter(Boolean).join(' ');
            $('#mpw-review-title').text(title || '—');
            $('#mpw-review-price').text(price ? '£' + parseInt(price).toLocaleString() : '—');

            var specs = [];
            if(mileage) specs.push(parseInt(mileage).toLocaleString() + ' miles');
            if(fuel) specs.push(fuel);
            if(gearbox) specs.push(gearbox);
            $('#mpw-review-specs').text(specs.join(' · '));

            if (gallery.length > 0) {
                $('#mpw-review-photo').html('<img src="'+gallery[0].thumb+'" alt="Main photo" />');
            }

            // Build checklist
            var checks = [
                { label:'Make & Model',    ok: !!(make && model) },
                { label:'Year',            ok: !!year },
                { label:'Mileage',         ok: !!mileage },
                { label:'Price',           ok: !!price },
                { label:'Fuel Type',       ok: !!fuel },
                { label:'Transmission',    ok: !!gearbox },
                { label:'Photos (10+)',    ok: gallery.length >= 10, warn: gallery.length > 0 && gallery.length < 10 },
                { label:'Description',     ok: !!$('[name="description"]').val().trim(), warn: true },
                { label:'Service History', ok: !!$('[name="service_history"]').val() },
            ];

            var html = '<h3>Listing Checklist</h3>';
            checks.forEach(function(c){
                var icon = c.ok ? '✅' : (c.warn ? '⚠️' : '❌');
                var cls  = c.ok ? 'ok' : (c.warn ? 'warn' : 'missing');
                html += '<div class="mpw-check-item mpw-check-item--'+cls+'">'+icon+' '+c.label+'</div>';
            });

            var missing = checks.filter(function(c){return !c.ok && !c.warn;}).length;
            if (missing > 0) {
                html += '<div class="mpw-check-warning">⚠️ '+missing+' required field'+(missing>1?'s are':' is')+' empty. You can still publish but these will show as missing on the listing.</div>';
            } else {
                html += '<div class="mpw-check-all-good">✅ Looking good — ready to publish!</div>';
            }

            $('#mpw-review-checklist').html(html);
        }

        // Live title preview in step 5
        $('[name="make"],[name="model"],[name="variant"],[name="year"]').on('input', function(){
            if (currentStep === 5) mpwBuildReview();
        });

        updatePhotoQuality();

    })(jQuery);
    </script>
    <?php
}
