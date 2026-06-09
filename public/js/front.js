jQuery(function ($) {
    var wrap        = $('#mp-stock-wrap');
    if (!wrap.length) return;

    var grid        = $('#mp-grid');
    var ajaxUrl     = motoplusFront.ajaxUrl;
    var nonce       = motoplusFront.nonce;
    var debounce;
    var limit       = wrap.data('limit') || 24;
    var featuredOnly= wrap.data('featured') || '0';

    // ── Restore saved view ──────────────────────────────────────────────────
    var savedView = localStorage.getItem('mp_view');
    if (savedView === 'list') {
        grid.addClass('mp-list-view');
        $('[data-view="list"]').addClass('active');
        $('[data-view="grid"]').removeClass('active');
    }

    // ── View toggle ─────────────────────────────────────────────────────────
    $(document).on('click', '.mp-view-btn', function () {
        $('.mp-view-btn').removeClass('active');
        $(this).addClass('active');
        var v = $(this).data('view');
        grid.toggleClass('mp-list-view', v === 'list');
        localStorage.setItem('mp_view', v);
    });

    // ── Filter panel toggle ─────────────────────────────────────────────────
    $(document).on('click', '#mp-toggle-filters', function () {
        var $f = $('#mp-filters');
        $f.slideToggle(180);
        $('#mp-filter-arrow').text($f.is(':hidden') ? '▾' : '▴');
    });

    // ── Collect current filter values ───────────────────────────────────────
    function getFilters() {
        return {
            action:       'motoplus_filter',
            nonce:        nonce,
            search:       $('#mp-search').val() || '',
            make:         $('#mp-filter-make').val()   || '',
            fuel:         $('#mp-filter-fuel').val()   || '',
            gearbox:      $('#mp-filter-gearbox').val()|| '',
            body:         $('#mp-filter-body').val()   || '',
            max_price:    $('#mp-filter-price').val()  || '',
            sort:         $('#mp-sort').val()          || '',
            limit:        limit,
            featured_only:featuredOnly,
        };
    }

    // ── Run AJAX filter ─────────────────────────────────────────────────────
    function runFilter() {
        grid.addClass('mp-loading');
        $.post(ajaxUrl, getFilters(), function (res) {
            grid.removeClass('mp-loading');
            if (!res.success) return;
            grid.html(res.data.html);

            // Re-apply view
            if (localStorage.getItem('mp_view') === 'list') grid.addClass('mp-list-view');

            // Update count
            var c = res.data.count;
            $('#mp-count').text(c + ' vehicle' + (c !== 1 ? 's' : '') + ' found');
        });
    }

    // ── Event bindings ──────────────────────────────────────────────────────
    $('#mp-search-btn').on('click', runFilter);

    $('#mp-search').on('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); runFilter(); }
    }).on('input', function () {
        clearTimeout(debounce);
        debounce = setTimeout(runFilter, 550);
    });

    $('#mp-apply-filters').on('click', runFilter);
    $('#mp-sort').on('change', runFilter);

    $(document).on('click', '#mp-clear-filters, #mp-reset', function () {
        $('#mp-search').val('');
        $('#mp-filter-make, #mp-filter-fuel, #mp-filter-gearbox, #mp-filter-body').val('');
        $('#mp-filter-price').val('');
        $('#mp-sort').val('');
        runFilter();
    });

    // ── Enquiry form (single vehicle page) ─────────────────────────────────
    $(document).on('submit', '#mp-lead-form', function (e) {
        e.preventDefault();
        var $form   = $(this);
        var $btn    = $form.find('button[type="submit"]');
        var $result = $('#mp-lead-result');

        $btn.prop('disabled', true).text('Sending…');
        $result.removeClass('success error').text('');

        var data = {
            action:        'motoplus_submit_lead',
            nonce:         nonce,
            vehicle_id:    $form.find('[name="vehicle_id"]').val(),
            vehicle_title: $form.find('[name="vehicle_title"]').val(),
            name:          $form.find('[name="name"]').val(),
            phone:         $form.find('[name="phone"]').val(),
            email:         $form.find('[name="email"]').val(),
            message:       $form.find('[name="message"]').val(),
        };

        $.post(ajaxUrl, data, function (res) {
            $btn.prop('disabled', false).text('✉ Send Enquiry');
            if (res.success) {
                $result.addClass('success').text(res.data.message);
                $form.find('input:not([type=hidden]), textarea').val('');
            } else {
                $result.addClass('error').text(res.data.message || 'Something went wrong. Please try again.');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('✉ Send Enquiry');
            $result.addClass('error').text('Connection error. Please try again.');
        });
    });

    // ── Gallery thumbnails (single vehicle page) ────────────────────────────
    $(document).on('click', '.mp-thumb', function () {
        var $this = $(this);
        var full  = $this.data('full');
        $('#mp-main-img').attr('src', full);
        $('.mp-thumb').removeClass('active');
        $this.addClass('active');
    });
});
