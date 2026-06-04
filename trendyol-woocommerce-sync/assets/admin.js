/* global twsData, jQuery */
(function ($) {
    'use strict';

    var wcCategories = twsData.wcCategories || [];
    var categoryMap  = twsData.categoryMap  || {};

    // ================================================================
    // Yardımcı fonksiyonlar
    // ================================================================

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function showNotice($el, type, msg) {
        $el.removeClass('is-error is-success is-warning')
           .addClass('is-' + type)
           .text(msg)
           .show();
    }

    function post(action, data) {
        data.action = action;
        data.nonce  = twsData.nonce;
        return $.post(twsData.ajaxUrl, data);
    }

    // ================================================================
    // API Ayarları Formu (#tws-my-config-form) — vendor ve admin
    // ================================================================

    $('#tws-my-config-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn  = $form.find('[type=submit]');
        var $res  = $('#tws-save-result');

        var data = {
            vendor_id:     $form.find('[name=vendor_id]').val(),
            supplier_id:   $form.find('[name=supplier_id]').val(),
            api_key:       $form.find('[name=api_key]').val(),
            api_secret:    $form.find('[name=api_secret]').val(),
            price_markup:  $form.find('[name=price_markup]').val(),
            sync_interval: $form.find('[name=sync_interval]').val(),
            auto_sync:     $form.find('[name=auto_sync]').is(':checked') ? 1 : '',
        };

        $btn.prop('disabled', true).text('Kaydediliyor…');
        $res.hide().removeClass('is-error is-success');

        post('tws_save_vendor_config', data)
        .done(function (r) {
            if (r.success) { showNotice($res, 'success', '✓ ' + r.data.message); }
            else { showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata')); }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Kaydet'); });
    });

    // ================================================================
    // Bağlantı testi (kendi API'si)
    // ================================================================

    $('#tws-test-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-test-result');
        $btn.prop('disabled', true).text('Test ediliyor…');
        $res.hide().removeClass('is-error is-success');
        post('tws_test_connection', { vendor_id: twsData.activeVendorId })
        .done(function (r) {
            if (r.success) {
                showNotice($res, 'success', '✓ ' + r.data.message);
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Hata';
                var hint = (r.data && r.data.hint) ? '\n\nİpucu: ' + r.data.hint : '';
                $res.removeClass('is-error is-success is-warning')
                    .addClass('is-error')
                    .html('<strong>✗ ' + escHtml(msg) + '</strong>' + (hint ? '<br><small style="display:block;margin-top:6px;">' + escHtml(r.data.hint) + '</small>' : ''))
                    .show();
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Bağlantıyı Test Et'); });
    });

    // ================================================================
    // Canlı senkronizasyon paneli (polling — kendi entegrasyonu)
    // ================================================================

    var syncPollTimer = null;

    function updateEngineBadge(hasAS) {
        var $b = $('#tws-engine-badge');
        if (hasAS === undefined) { return; }
        if (hasAS) {
            $b.removeClass('tws-engine-checking tws-engine-wp')
              .addClass('tws-engine-as')
              .html('✓ <strong>Action Scheduler</strong> aktif — tarayıcı kapalıyken de çalışır');
        } else {
            $b.removeClass('tws-engine-checking tws-engine-as')
              .addClass('tws-engine-wp')
              .html('⚠ <strong>WP-Cron</strong> kullanılıyor — sistem cron kurmanız önerilir');
        }
    }

    function pollSyncStatus() {
        post('tws_sync_status', { vendor_id: twsData.activeVendorId })
        .done(function (r) {
            if (!r.success) { return; }
            var d = r.data;

            updateEngineBadge(d.has_as);
            $('#tws-last-sync').text(d.last_sync || '—');
            $('#tws-next-sync').text(d.next_sync || '—');

            if (d.in_progress && d.stats && d.stats.total) {
                $('#tws-live-progress').show();
                $('#tws-progress-fill').css('width', (d.progress_pct || 0) + '%');
                $('#tws-progress-text').text(
                    'İşleniyor: ' + (d.stats.processed || 0) + ' / ' + d.stats.total +
                    '  •  Yeni: ' + (d.stats.imported || 0) +
                    '  Güncellenen: ' + (d.stats.updated || 0) +
                    '  Hatlı: ' + (d.stats.failed || 0)
                );
                syncPollTimer = setTimeout(pollSyncStatus, 3000);
            } else {
                $('#tws-live-progress').hide();
                if (syncPollTimer) {
                    clearTimeout(syncPollTimer);
                    syncPollTimer = null;
                    location.reload();
                }
            }
        });
    }

    if ($('#tws-engine-badge').length) {
        pollSyncStatus();
    }

    $('#tws-sync-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-sync-result'), $sp = $('#tws-sync-spinner');
        $btn.prop('disabled', true);
        $sp.show();
        $res.hide().removeClass('is-error is-success');

        post('tws_manual_sync', { vendor_id: twsData.activeVendorId })
        .done(function (r) {
            if (r.success) {
                if (r.data.background) {
                    showNotice($res, 'success', '✓ ' + r.data.message + ' İlerleme aşağıda gösterilecek.');
                    if (syncPollTimer) { clearTimeout(syncPollTimer); }
                    syncPollTimer = setTimeout(pollSyncStatus, 2000);
                } else {
                    showNotice($res, 'success', '✓ ' + r.data.message);
                    setTimeout(function () { location.reload(); }, 1500);
                }
            } else {
                showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata'));
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false); $sp.hide(); });
    });

    // ================================================================
    // Sistem Cron UI
    // ================================================================

    $(document).on('click', '.tws-cron-tab-btn', function () {
        var show = $(this).data('show');
        $('.tws-cron-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('#tws-cron-curl, #tws-cron-wget, #tws-cron-wpcli').hide();
        $('#tws-cron-' + show).show();
    });

    $(document).on('click', '.tws-copy-btn', function () {
        var $target = $('#' + $(this).data('target'));
        var text = $target.find('code').text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            var $tmp = $('<textarea>').val(text).appendTo('body').select();
            document.execCommand('copy');
            $tmp.remove();
        }
        var $btn = $(this);
        $btn.text('Kopyalandı!');
        setTimeout(function () { $btn.text('Kopyala'); }, 2000);
    });

    // ================================================================
    // Kategori eşleştirme
    // ================================================================

    function buildCatSelect(selId) {
        var html = '<select class="tws-wc-cat-select"><option value="0">— Seçin —</option>';
        wcCategories.forEach(function (c) {
            html += '<option value="' + c.id + '"' + (parseInt(selId) === c.id ? ' selected' : '') + '>' + escHtml(c.name) + '</option>';
        });
        return html + '</select>';
    }

    function addMapRow(trCat, wcId) {
        $('#tws-cat-map-body').append(
            '<tr>' +
            '<td><input type="text" class="tws-trendyol-cat regular-text" value="' + escHtml(trCat) + '" placeholder="Trendyol kategori" /></td>' +
            '<td>' + buildCatSelect(wcId) + '</td>' +
            '<td><button type="button" class="button tws-remove-row">✕</button></td>' +
            '</tr>'
        );
    }

    function renderMapTable() {
        $('#tws-cat-map-body').empty();
        var keys = Object.keys(categoryMap);
        if (keys.length === 0) { addMapRow('', 0); return; }
        keys.forEach(function (k) { addMapRow(k, categoryMap[k]); });
    }
    renderMapTable();

    $(document).on('click', '.tws-tag', function () {
        var cat = $(this).data('cat'), exists = false;
        $('#tws-cat-map-body .tws-trendyol-cat').each(function () { if ($(this).val() === cat) { exists = true; } });
        if (!exists) { addMapRow(cat, 0); }
        $(this).addClass('tws-tag-added');
    });

    $('#tws-add-map-row').on('click', function () { addMapRow('', 0); });
    $(document).on('click', '.tws-remove-row', function () { $(this).closest('tr').remove(); });

    $('#tws-save-map-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-map-result'), map = {};
        $('#tws-cat-map-body tr').each(function () {
            var k = $(this).find('.tws-trendyol-cat').val().trim();
            var v = parseInt($(this).find('.tws-wc-cat-select').val(), 10);
            if (k && v > 0) { map[k] = v; }
        });
        $btn.prop('disabled', true).text('Kaydediliyor…');
        $res.hide().removeClass('is-error is-success');
        post('tws_save_category_map', { map: JSON.stringify(map) })
        .done(function (r) {
            if (r.success) { categoryMap = map; showNotice($res, 'success', '✓ ' + r.data.message); }
            else { showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata')); }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Eşleştirmeyi Kaydet'); });
    });

    // ================================================================
    // Vendor Yönetimi (admin)
    // ================================================================

    var allVendors          = [];
    var vendorPollTimer     = null;
    var activeVendorModalId = 0;

    function vendorConfigBadge(configured) {
        return configured
            ? '<span class="tws-badge tws-badge-success">✓ Yapılandırılmış</span>'
            : '<span class="tws-badge tws-badge-error">✗ Yapılandırılmamış</span>';
    }

    function vendorStatusBadge(status) {
        var map = {
            idle:    ['tws-badge-info',    'Hazır'],
            running: ['tws-badge-warning', 'Çalışıyor'],
            error:   ['tws-badge-error',   'Hata'],
        };
        var s = map[status] || map['idle'];
        return '<span class="tws-badge ' + s[0] + '">' + s[1] + '</span>';
    }

    function renderVendorRow(v) {
        var roles = (v.roles || []).join(', ');
        $('#tws-vendor-tbody').append(
            '<tr data-vendor-id="' + v.id + '">' +
            '<td>' + v.id + '</td>' +
            '<td><strong>' + escHtml(v.name) + '</strong><br><small>' + escHtml(v.email) + '</small></td>' +
            '<td><code>' + escHtml(roles) + '</code></td>' +
            '<td>' + vendorConfigBadge(v.configured) + '</td>' +
            '<td>' + escHtml(v.stats ? (v.stats.last_sync || '—') : '—') + '</td>' +
            '<td>' + (v.stats ? v.stats.product_count : 0) + '</td>' +
            '<td>' + vendorStatusBadge(v.stats ? v.stats.status : 'idle') + '</td>' +
            '<td><button class="button tws-vendor-edit-btn" data-vendor-id="' + v.id + '">Äzarla</button></td>' +
            '</tr>'
        );
    }

    function loadVendors() {
        if (!$('#tws-vendor-tbody').length) { return; }
        post('tws_get_vendors', {})
        .done(function (r) {
            if (!r.success) { return; }
            allVendors = r.data;
            filterVendors($('#tws-vendor-search').val() || '');
        })
        .fail(function () {
            $('#tws-vendor-tbody').html('<tr><td colspan="8">Yükleme hatası.</td></tr>');
        });
    }

    function filterVendors(query) {
        var $tbody = $('#tws-vendor-tbody');
        $tbody.empty();
        var q = query.toLowerCase();
        var filtered = allVendors.filter(function (v) {
            return !q || v.name.toLowerCase().indexOf(q) >= 0 || v.email.toLowerCase().indexOf(q) >= 0;
        });
        if (!filtered.length) {
            $tbody.html('<tr><td colspan="8">Vendor bulunamadı.</td></tr>');
            return;
        }
        filtered.forEach(renderVendorRow);
    }

    if ($('#tws-vendor-table').length) {
        loadVendors();
    }

    $('#tws-vendor-search').on('input', function () {
        filterVendors($(this).val());
    });

    // Vendor modal aç
    $(document).on('click', '.tws-vendor-edit-btn', function () {
        var vid    = parseInt($(this).data('vendor-id'), 10);
        var vendor = null;
        allVendors.forEach(function (v) { if (v.id === vid) { vendor = v; } });
        if (!vendor) { return; }

        activeVendorModalId = vid;
        $('#tws-vm-title').text(vendor.name + ' — Ayarlar');
        $('#vm_vendor_id').val(vid);

        var c = vendor.config || {};
        $('#vm_supplier_id').val(c.supplier_id || '');
        $('#vm_api_key').val(c.api_key || '');
        $('#vm_api_secret').val(c.api_secret || '');
        $('#vm_price_markup').val(typeof c.price_markup !== 'undefined' ? c.price_markup : 0);
        $('#vm_sync_interval option').prop('selected', false);
        $('#vm_sync_interval option[value="' + (c.sync_interval || 3600) + '"]').prop('selected', true);
        $('#vm_auto_sync').prop('checked', !!c.auto_sync);

        $('#tws-vm-result').hide().removeClass('is-error is-success');
        $('#tws-vm-progress').hide();
        $('#tws-vm-log-section').hide();

        $('#tws-vendor-modal').show();
    });

    function closeVendorModal() {
        $('#tws-vendor-modal').hide();
        activeVendorModalId = 0;
        if (vendorPollTimer) { clearTimeout(vendorPollTimer); vendorPollTimer = null; }
    }

    $('#tws-vendor-modal').on('click', function (e) {
        if ($(e.target).is('#tws-vendor-modal')) { closeVendorModal(); }
    });

    // Vendor kaydet
    $('#tws-vm-save-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-vm-result');
        var vid  = parseInt($('#vm_vendor_id').val(), 10);
        if (!vid) { return; }

        var data = {
            vendor_id:     vid,
            supplier_id:   $('#vm_supplier_id').val(),
            api_key:       $('#vm_api_key').val(),
            api_secret:    $('#vm_api_secret').val(),
            price_markup:  $('#vm_price_markup').val(),
            sync_interval: $('#vm_sync_interval').val(),
            auto_sync:     $('#vm_auto_sync').is(':checked') ? 1 : '',
        };

        $btn.prop('disabled', true).text('Kaydediliyor…');
        $res.hide().removeClass('is-error is-success');

        post('tws_save_vendor_config', data)
        .done(function (r) {
            if (r.success) {
                showNotice($res, 'success', '✓ ' + r.data.message);
                allVendors.forEach(function (v) {
                    if (v.id === vid) {
                        v.config.supplier_id   = data.supplier_id;
                        v.config.api_key       = data.api_key;
                        v.config.api_secret    = data.api_secret;
                        v.config.price_markup  = parseFloat(data.price_markup);
                        v.config.sync_interval = parseInt(data.sync_interval, 10);
                        v.config.auto_sync     = !!data.auto_sync;
                        v.configured           = !!(data.api_key && data.supplier_id);
                    }
                });
                filterVendors($('#tws-vendor-search').val() || '');
            } else {
                showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata'));
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Kaydet'); });
    });

    // Vendor bağlantı testi
    $('#tws-vm-test-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-vm-result');
        var vid  = parseInt($('#vm_vendor_id').val(), 10);
        if (!vid) { return; }

        $btn.prop('disabled', true).text('Test ediliyor…');
        $res.hide().removeClass('is-error is-success');

        post('tws_test_vendor_conn', { vendor_id: vid })
        .done(function (r) {
            if (r.success) {
                showNotice($res, 'success', '✓ ' + r.data.message);
            } else {
                var msg = (r.data && r.data.message) ? r.data.message : 'Hata';
                $res.removeClass('is-error is-success is-warning')
                    .addClass('is-error')
                    .html('<strong>✗ ' + escHtml(msg) + '</strong>' + (r.data && r.data.hint ? '<br><small style="display:block;margin-top:6px;">' + escHtml(r.data.hint) + '</small>' : ''))
                    .show();
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Bağlantı Test Et'); });
    });

    // Vendor sync başlat
    $('#tws-vm-sync-btn').on('click', function () {
        var $btn = $(this), $res = $('#tws-vm-result');
        var vid  = parseInt($('#vm_vendor_id').val(), 10);
        if (!vid) { return; }

        $btn.prop('disabled', true).text('Başlatılıyor…');
        $res.hide().removeClass('is-error is-success');

        post('tws_sync_vendor', { vendor_id: vid })
        .done(function (r) {
            if (r.success) {
                showNotice($res, 'success', '✓ ' + r.data.message);
                if (r.data.background) {
                    if (vendorPollTimer) { clearTimeout(vendorPollTimer); }
                    vendorPollTimer = setTimeout(function () { pollVendorStatus(vid); }, 2000);
                }
            } else {
                showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata'));
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text('Şimdi Sync Et'); });
    });

    function pollVendorStatus(vid) {
        if (activeVendorModalId !== vid) { return; }
        post('tws_vendor_sync_status', { vendor_id: vid })
        .done(function (r) {
            if (!r.success || activeVendorModalId !== vid) { return; }
            var d = r.data;

            if (d.in_progress && d.stats && d.stats.total) {
                $('#tws-vm-progress').show();
                $('#tws-vm-progress-fill').css('width', (d.progress_pct || 0) + '%');
                $('#tws-vm-progress-text').text(
                    'İşleniyor: ' + (d.stats.processed || 0) + ' / ' + d.stats.total +
                    '  •  Yeni: ' + (d.stats.imported || 0) +
                    '  Güncellenen: ' + (d.stats.updated || 0) +
                    '  Hatlı: ' + (d.stats.failed || 0)
                );
                vendorPollTimer = setTimeout(function () { pollVendorStatus(vid); }, 3000);
            } else {
                $('#tws-vm-progress').hide();
                if (vendorPollTimer) { clearTimeout(vendorPollTimer); vendorPollTimer = null; }
                loadVendors();
            }
        });
    }

    // ================================================================
    // Modal kapat — hem export hem vendor
    // ================================================================

    $(document).on('click', '.tws-modal-close, .tws-modal-close-btn', function () {
        if ($(this).closest('#tws-vendor-modal').length) {
            closeVendorModal();
        } else {
            closeModal();
        }
    });

    // ================================================================
    // Export sekmesi – Ürün listesi
    // ================================================================

    var exportPage       = 1;
    var currentProductId = 0;
    var cargoLoaded      = false;

    function statusBadge(status) {
        var map = {
            none:    ['tws-badge-info',    '—'],
            pending: ['tws-badge-warning', 'Bekliyor'],
            success: ['tws-badge-success', 'Aktarıldı'],
            error:   ['tws-badge-error',   'Hatlı'],
        };
        var s = map[status] || map['none'];
        return '<span class="tws-badge ' + s[0] + '">' + s[1] + '</span>';
    }

    function loadProducts(page) {
        exportPage = page || 1;
        var search = $('#tws-product-search').val() || '';
        var $tbody = $('#tws-export-tbody');
        $tbody.html('<tr><td colspan="7" class="tws-loading">Yükleniyor…</td></tr>');

        post('tws_get_wc_products', { page: exportPage, search: search })
        .done(function (r) {
            $tbody.empty();
            if (!r.success || !r.data.products.length) {
                $tbody.html('<tr><td colspan="7">Ürün bulunamadı.</td></tr>');
                $('#tws-export-pagination').empty();
                return;
            }
            r.data.products.forEach(function (p) {
                var thumb = p.thumb
                    ? '<img src="' + escHtml(p.thumb) + '" width="40" height="40" style="object-fit:cover;border-radius:3px;">'
                    : '<span class="tws-no-thumb">?</span>';

                var checkBtn = (p.status === 'pending')
                    ? '<button class="button tws-check-status-btn" data-id="' + p.id + '">Durum?</button> '
                    : '';

                $tbody.append(
                    '<tr data-id="' + p.id + '">' +
                    '<td>' + thumb + '</td>' +
                    '<td><strong>' + escHtml(p.name) + '</strong><br><small>' + escHtml(p.type) + '</small></td>' +
                    '<td><code>' + escHtml(p.sku || '—') + '</code></td>' +
                    '<td>' + p.price + '</td>' +
                    '<td>' + (p.stock !== null ? p.stock : '∞') + '</td>' +
                    '<td class="tws-status-cell">' + statusBadge(p.status) + (p.export_date ? '<br><small>' + escHtml(p.export_date) + '</small>' : '') + '</td>' +
                    '<td>' + checkBtn + '<button class="button button-primary tws-open-export" data-id="' + p.id + '" data-name="' + escHtml(p.name) + '" data-config="' + escHtml(JSON.stringify(p.config)) + '">Aktar</button></td>' +
                    '</tr>'
                );
            });
            renderPagination(r.data.page, r.data.totalPages);
        })
        .fail(function () {
            $tbody.html('<tr><td colspan="7">Yükleme hatası.</td></tr>');
        });
    }

    function renderPagination(current, total) {
        var $pg = $('#tws-export-pagination');
        $pg.empty();
        if (total <= 1) { return; }
        for (var i = 1; i <= total; i++) {
            $pg.append('<button class="button tws-page-btn' + (i === current ? ' active' : '') + '" data-page="' + i + '">' + i + '</button> ');
        }
    }

    $(document).on('click', '.tws-page-btn', function () { loadProducts(parseInt($(this).data('page'), 10)); });
    $('#tws-product-search-btn').on('click', function () { loadProducts(1); });
    $('#tws-product-search').on('keypress', function (e) { if (e.which === 13) { loadProducts(1); } });

    if ($('#tws-export-tbody').length) {
        loadProducts(1);
        loadCargoCompanies();
    }

    // ================================================================
    // Export Modal
    // ================================================================

    function openModal(productId, productName, savedConfig) {
        currentProductId = productId;
        $('#tws-modal-title').text('Aktar: ' + productName);
        $('#tws-cat-id').val('');
        $('#tws-cat-search').val('');
        $('#tws-cat-label').text('');
        $('#tws-cat-dropdown').hide();
        $('#tws-brand-id').val('');
        $('#tws-brand-search').val('');
        $('#tws-brand-label').text('');
        $('#tws-brand-dropdown').hide();
        $('#tws-desi').val(1);
        $('#tws-attributes-section').hide();
        $('#tws-attributes-table').empty();
        $('#tws-export-modal-result').hide();

        if (savedConfig && Object.keys(savedConfig).length) {
            if (savedConfig.category_id)        { $('#tws-cat-id').val(savedConfig.category_id); $('#tws-cat-label').text('ID: ' + savedConfig.category_id); }
            if (savedConfig.brand_id)           { $('#tws-brand-id').val(savedConfig.brand_id); $('#tws-brand-label').text('ID: ' + savedConfig.brand_id); }
            if (savedConfig.cargo_company_id)   { setTimeout(function () { $('#tws-cargo-id').val(savedConfig.cargo_company_id); }, 500); }
            if (savedConfig.dimensional_weight) { $('#tws-desi').val(savedConfig.dimensional_weight); }
            if (savedConfig.category_id)        { loadCategoryAttributes(savedConfig.category_id, savedConfig.attributes || []); }
        }

        $('#tws-export-modal').show();
    }

    function closeModal() {
        $('#tws-export-modal').hide();
        currentProductId = 0;
    }

    $(document).on('click', '.tws-open-export', function () {
        var id     = $(this).data('id');
        var name   = $(this).data('name');
        var config = {};
        try { config = JSON.parse($(this).data('config') || '{}'); } catch (e) { config = {}; }
        openModal(id, name, config);
    });

    $('#tws-export-modal').on('click', function (e) { if ($(e.target).is('#tws-export-modal')) { closeModal(); } });

    // ================================================================
    // Kategori Autocomplete
    // ================================================================

    var catTimer;
    $('#tws-cat-search').on('input', function () {
        clearTimeout(catTimer);
        var val = $(this).val().trim();
        if (val.length < 2) { $('#tws-cat-dropdown').hide(); return; }
        catTimer = setTimeout(function () { searchCategories(val); }, 350);
    });

    function searchCategories(name) {
        var $dd = $('#tws-cat-dropdown').html('<div class="tws-ac-item tws-ac-loading">Aranıyor…</div>').show();
        post('tws_search_trendyol_categories', { name: name })
        .done(function (r) {
            $dd.empty();
            if (!r.success || !r.data.length) { $dd.html('<div class="tws-ac-item tws-ac-empty">Sonuç bulunamadı.</div>'); return; }
            r.data.slice(0, 20).forEach(function (cat) {
                $dd.append('<div class="tws-ac-item" data-id="' + cat.id + '" data-name="' + escHtml(cat.name) + '">' + escHtml(cat.name) + '</div>');
            });
        })
        .fail(function () { $dd.html('<div class="tws-ac-item tws-ac-empty">Hata oluştu.</div>'); });
    }

    $(document).on('click', '#tws-cat-dropdown .tws-ac-item[data-id]', function () {
        var id   = $(this).data('id');
        var name = $(this).data('name');
        $('#tws-cat-id').val(id);
        $('#tws-cat-search').val(name);
        $('#tws-cat-label').text('');
        $('#tws-cat-dropdown').hide();
        loadCategoryAttributes(id, []);
    });

    function loadCategoryAttributes(categoryId, savedAttrs) {
        var $sec   = $('#tws-attributes-section');
        var $table = $('#tws-attributes-table');
        $sec.show();
        $table.html('<tr><td>Özellikler yükleniyor…</td></tr>');

        post('tws_get_category_attributes', { category_id: categoryId })
        .done(function (r) {
            $table.empty();
            if (!r.success || !r.data.length) { $sec.hide(); return; }

            r.data.forEach(function (attr) {
                var attrId   = attr.attribute.id;
                var attrName = attr.attribute.name;
                var required = attr.required;
                var vals     = attr.attributeValues || [];
                var savedVal = '';
                savedAttrs.forEach(function (s) {
                    if (s.attributeId === attrId) { savedVal = s.attributeValueId || s.customAttributeValue || ''; }
                });

                var inputHtml;
                if (vals.length > 0) {
                    inputHtml = '<select class="tws-attr-input regular-text" data-attr-id="' + attrId + '" data-allow-custom="' + (attr.allowCustom ? '1' : '0') + '">';
                    inputHtml += '<option value="">— Seçin —</option>';
                    vals.forEach(function (v) {
                        inputHtml += '<option value="' + v.id + '"' + (savedVal == v.id ? ' selected' : '') + '>' + escHtml(v.name) + '</option>';
                    });
                    inputHtml += '</select>';
                } else {
                    inputHtml = '<input type="text" class="tws-attr-input regular-text" data-attr-id="' + attrId + '" data-custom="1" value="' + escHtml(savedVal) + '" />';
                }

                $table.append(
                    '<tr>' +
                    '<th><label>' + escHtml(attrName) + (required ? ' <span class="tws-req">*</span>' : '') + '</label></th>' +
                    '<td>' + inputHtml + '</td>' +
                    '</tr>'
                );
            });
        })
        .fail(function () { $sec.hide(); });
    }

    // ================================================================
    // Marka Autocomplete
    // ================================================================

    var brandTimer;
    $('#tws-brand-search').on('input', function () {
        clearTimeout(brandTimer);
        var val = $(this).val().trim();
        if (val.length < 2) { $('#tws-brand-dropdown').hide(); return; }
        brandTimer = setTimeout(function () { searchBrands(val); }, 350);
    });

    function searchBrands(name) {
        var $dd = $('#tws-brand-dropdown').html('<div class="tws-ac-item tws-ac-loading">Aranıyor…</div>').show();
        post('tws_search_brands', { name: name })
        .done(function (r) {
            $dd.empty();
            if (!r.success || !r.data.length) { $dd.html('<div class="tws-ac-item tws-ac-empty">Sonuç bulunamadı.</div>'); return; }
            r.data.slice(0, 15).forEach(function (brand) {
                $dd.append('<div class="tws-ac-item" data-id="' + brand.id + '" data-name="' + escHtml(brand.name) + '">' + escHtml(brand.name) + '</div>');
            });
        })
        .fail(function () { $dd.html('<div class="tws-ac-item tws-ac-empty">Hata oluştu.</div>'); });
    }

    $(document).on('click', '#tws-brand-dropdown .tws-ac-item[data-id]', function () {
        $('#tws-brand-id').val($(this).data('id'));
        $('#tws-brand-search').val($(this).data('name'));
        $('#tws-brand-label').text('');
        $('#tws-brand-dropdown').hide();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.tws-ac-wrap').length) {
            $('.tws-ac-dropdown').hide();
        }
    });

    // ================================================================
    // Kargo şirketleri
    // ================================================================

    function loadCargoCompanies() {
        if (cargoLoaded) { return; }
        post('tws_get_cargo_companies', {})
        .done(function (r) {
            var $sel = $('#tws-cargo-id');
            $sel.empty().append('<option value="">— Seçin —</option>');
            if (r.success && r.data.length) {
                cargoLoaded = true;
                r.data.forEach(function (c) {
                    $sel.append('<option value="' + c.id + '">' + escHtml(c.name) + '</option>');
                });
            }
        });
    }

    // ================================================================
    // Aktarım butonu
    // ================================================================

    $('#tws-do-export').on('click', function () {
        var $btn = $(this);
        var $res = $('#tws-export-modal-result');

        var catId   = parseInt($('#tws-cat-id').val(), 10);
        var brandId = parseInt($('#tws-brand-id').val(), 10);
        var cargoId = parseInt($('#tws-cargo-id').val(), 10);
        var desi    = parseFloat($('#tws-desi').val()) || 1;

        if (!catId || !brandId || !cargoId) {
            showNotice($res, 'error', 'Kategori, marka ve kargo şirketi zorunludur.');
            return;
        }

        var attributes = [];
        $('.tws-attr-input').each(function () {
            var attrId   = parseInt($(this).data('attr-id'), 10);
            var val      = $(this).val();
            var isCustom = $(this).data('custom') === 1 || $(this).data('allow-custom') === '1';
            if (!val) { return; }

            var item = { attributeId: attrId };
            if (isCustom || isNaN(parseInt(val, 10))) {
                item.customAttributeValue = val;
            } else {
                item.attributeValueId = parseInt(val, 10);
            }
            attributes.push(item);
        });

        var config = {
            category_id:        catId,
            brand_id:           brandId,
            cargo_company_id:   cargoId,
            dimensional_weight: desi,
            attributes:         attributes,
        };

        $btn.prop('disabled', true).text('Aktarılıyor…');
        $res.hide().removeClass('is-error is-success');

        post('tws_export_product', { product_id: currentProductId, config: JSON.stringify(config) })
        .done(function (r) {
            if (r.success) {
                showNotice($res, 'success', "✓ Trendyol'a gönderildi. Batch ID: " + (r.data.batchRequestId || '—'));
                $('tr[data-id="' + currentProductId + '"] .tws-status-cell').html(statusBadge('pending'));
                setTimeout(function () { closeModal(); }, 2000);
            } else {
                showNotice($res, 'error', '✗ ' + (r.data && r.data.message ? r.data.message : 'Hata'));
            }
        })
        .fail(function () { showNotice($res, 'error', 'Sunucu hatası.'); })
        .always(function () { $btn.prop('disabled', false).text("Trendyol'a Aktar"); });
    });

    // ================================================================
    // Durum kontrolü
    // ================================================================

    $(document).on('click', '.tws-check-status-btn', function () {
        var $btn = $(this);
        var pid  = $(this).data('id');
        $btn.prop('disabled', true).text('Kontrol…');

        post('tws_check_export_status', { product_id: pid })
        .done(function (r) {
            if (r.success) {
                var d = r.data;
                $btn.closest('tr').find('.tws-status-cell').html(statusBadge(d.status));
                if (d.status !== 'pending') { $btn.remove(); }
                else { $btn.prop('disabled', false).text('Durum?'); }
            } else {
                $btn.prop('disabled', false).text('Durum?');
            }
        })
        .fail(function () { $btn.prop('disabled', false).text('Durum?'); });
    });

}(jQuery));
