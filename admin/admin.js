jQuery(function ($) {
    var ajaxUrl = motoplusAdmin.ajaxUrl;
    var nonce   = motoplusAdmin.nonce;

    // ── Gallery picker ──────────────────────────────────────────────────────
    var frame;
    var gallery = [];

    function parseGallery() {
        var raw = $('#mp_gallery_input').val();
        if (!raw) return [];
        return raw.split(',').map(Number).filter(Boolean);
    }

    function renderGallery() {
        var $preview = $('#mp-gallery-preview');
        $preview.empty();
        gallery.forEach(function (id) {
            var $item = $('<div class="mp-gallery-item" data-id="' + id + '"></div>');
            // Get thumbnail from WP (best we can do without REST)
            $item.append('<img src="" data-id="' + id + '" style="background:#f3f4f6" />');
            $item.append('<button type="button" class="mp-remove-img">✕</button>');
            $preview.append($item);
        });
        $('#mp_gallery_input').val(gallery.join(','));

        // Load thumbnails via built-in media data
        gallery.forEach(function(id) {
            wp.media.attachment(id).fetch().then(function () {
                var att = wp.media.attachment(id);
                var url = att.get('sizes') && att.get('sizes').thumbnail ? att.get('sizes').thumbnail.url : att.get('url');
                $('[data-id="' + id + '"] img').attr('src', url);
            });
        });
    }

    $('#mp_add_gallery').on('click', function () {
        if (frame) { frame.open(); return; }
        frame = wp.media({ title: 'Select Vehicle Photos', button: { text: 'Add to Gallery' }, multiple: true });
        frame.on('select', function () {
            frame.state().get('selection').each(function (att) {
                if (gallery.indexOf(att.id) === -1) gallery.push(att.id);
            });
            renderGallery();
        });
        frame.open();
    });

    $('#mp_clear_gallery').on('click', function () {
        if (confirm('Clear all gallery images?')) { gallery = []; renderGallery(); }
    });

    $(document).on('click', '.mp-remove-img', function () {
        var id = parseInt($(this).closest('.mp-gallery-item').data('id'));
        gallery = gallery.filter(function (i) { return i !== id; });
        renderGallery();
    });

    // Init from saved value
    gallery = parseGallery();
    if (gallery.length) renderGallery();

    // ── Registration lookup ─────────────────────────────────────────────────
    $('#mp_lookup_btn').on('click', function () {
        var reg = $('#mp_reg_lookup').val().trim().replace(/\s+/g, '').toUpperCase();
        if (!reg) return;
        var $btn = $(this).prop('disabled', true).text('Looking up…');
        var $result = $('#mp_lookup_result').removeClass('success error').text('');

        $.post(ajaxUrl, { action: 'motoplus_lookup_vehicle', nonce: nonce, registration: reg }, function (res) {
            $btn.prop('disabled', false).text('Lookup Vehicle');
            if (res.success && res.data.fields) {
                var fields = res.data.fields;
                var populated = [];

                // Populate all returned fields
                Object.keys(fields).forEach(function (key) {
                    var $el = $('#mp_field_' + key);
                    if ($el.length && fields[key]) {
                        $el.val(fields[key]);
                        populated.push(fields[key]);
                        // Flash green to show populated
                        $el.css('border-color','#22c55e').css('background','#f0fdf4');
                        setTimeout(function(){ $el.css('border-color','').css('background',''); }, 2000);
                    }
                });

                var count = Object.keys(fields).length;
                $result.addClass('success').html(
                    '✅ <strong>' + count + ' fields populated</strong> from UK Vehicle Data. Review and adjust if needed.'
                );
            } else {
                $result.addClass('error').html('⚠️ ' + (res.data && res.data.message ? res.data.message : 'Lookup failed. Enter details manually.'));
            }
        }).fail(function(){
            $btn.prop('disabled', false).text('Lookup Vehicle');
            $result.addClass('error').text('Connection error. Please try again.');
        });
    });

    // Allow Enter key on reg field
    $('#mp_reg_lookup').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); $('#mp_lookup_btn').trigger('click'); }
    });
    $('#mp_reg_lookup').on('input', function(){
        this.value = this.value.toUpperCase();
    });

    // ── AI description generator ────────────────────────────────────────────
    $('#mp_gen_desc_btn').on('click', function () {
        var $btn    = $(this).prop('disabled', true).text('Generating…');
        var $result = $('#mp_ai_result').removeClass('success error').text('');

        var vehicle = {};
        $('#mp-vehicle-details-fields, .mp-field-grid').find('[id^="mp_field_"]').each(function () {
            var key = this.id.replace('mp_field_', '');
            vehicle[key] = $(this).val();
        });

        $.post(ajaxUrl, { action: 'motoplus_generate_description', nonce: nonce, vehicle: vehicle }, function (res) {
            $btn.prop('disabled', false).text('Generate Draft Description');
            if (res.success) {
                // Set in WP editor if available, else fallback
                if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor) {
                    tinyMCE.activeEditor.setContent('<p>' + res.data.description.replace(/\n\n/g, '</p><p>').replace(/\n/g, '<br>') + '</p>');
                } else {
                    var $ta = $('#content');
                    if ($ta.length) $ta.val(res.data.description);
                }
                $result.addClass('success').text('✓ Description added to editor above — review and edit before saving.');
            } else {
                $result.addClass('error').text(res.data.message || 'Generation failed.');
            }
        });
    });

    // ── Import: HTML paste ──────────────────────────────────────────────────
    $('#mp-import-html-btn').on('click', function () {
        var html       = $('#mp-import-html').val().trim();
        var source_url = $('#mp-import-source-url').val().trim();
        var $btn       = $(this).prop('disabled', true).text('Importing…');
        var $result    = $('#mp-import-html-result').removeClass('success error').text('Importing, please wait…');
        var $preview   = $('#mp-import-preview').empty();

        $.post(ajaxUrl, { action: 'motoplus_import_html', nonce: nonce, html: html, source_url: source_url }, function (res) {
            $btn.prop('disabled', false).text('Extract from HTML');
            if (res.success) {
                $result.addClass('success').text('✓ ' + res.data.message);
                $preview.html('<div class="mp-import-success-box">✓ Imported: <strong>' + res.data.title + '</strong> (' + res.data.image_count + ' photos) — <a href="' + res.data.edit_url + '">Review &amp; Publish →</a></div>');
            } else {
                $result.addClass('error').text('✗ ' + (res.data.message || 'Import failed.'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Extract from HTML');
            $result.addClass('error').text('✗ Connection error. Please try again.');
        });
    });

    // ── Import: URL ─────────────────────────────────────────────────────────
    $('#mp-import-url-btn').on('click', function () {
        var url     = $('#mp-import-url').val().trim();
        var $btn    = $(this).prop('disabled', true).text('Importing…');
        var $result = $('#mp-import-url-result').removeClass('success error').text('Fetching listing…');
        var $preview= $('#mp-import-preview').empty();

        $.post(ajaxUrl, { action: 'motoplus_import_usedcarsni', nonce: nonce, url: url }, function (res) {
            $btn.prop('disabled', false).text('Import from URL');
            if (res.success) {
                $result.addClass('success').text('✓ ' + res.data.message);
                $preview.html('<div class="mp-import-success-box">✓ Imported: <strong>' + res.data.title + '</strong> (' + res.data.image_count + ' photos) — <a href="' + res.data.edit_url + '">Review &amp; Publish →</a></div>');
            } else {
                $result.addClass('error').text('✗ ' + (res.data.message || 'Import failed.'));
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Import from URL');
            $result.addClass('error').text('✗ Connection error. Please try again.');
        });
    });
});
