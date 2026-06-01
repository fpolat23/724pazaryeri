/* global twsData, jQuery */
(function ($) {
    'use strict';

    var wcCategories = twsData.wcCategories || [];
    var categoryMap  = twsData.categoryMap  || {};

    // ----------------------------------------------------------------
    // Yardımcı: WC kategori <select> HTML'i
    // ----------------------------------------------------------------
    function buildCatSelect(selectedId) {
        var html = '<select class="tws-wc-cat-select"><option value="0">— Seçin —</option>';
        wcCategories.forEach(function (cat) {
            var sel = (parseInt(selectedId, 10) === cat.id) ? ' selected' : '';
            html += '<option value="' + cat.id + '"' + sel + '>' + escHtml(cat.name) + '</option>';
        });
        html += '</select>';
        return html;
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ----------------------------------------------------------------
    // Kategori eşleştirme tablosunu oluştur
    // ----------------------------------------------------------------
    function renderMapTable() {
        var $tbody = $('#tws-cat-map-body');
        $tbody.empty();

        Object.keys(categoryMap).forEach(function (trendyolCat) {
            addMapRow(trendyolCat, categoryMap[trendyolCat]);
        });

        if ($tbody.children().length === 0) {
            addMapRow('', 0);
        }
    }

    function addMapRow(trendyolCat, wcId) {
        var row = '<tr>' +
            '<td><input type="text" class="tws-trendyol-cat regular-text" value="' + escHtml(trendyolCat) + '" placeholder="Trendyol kategori adı" /></td>' +
            '<td>' + buildCatSelect(wcId) + '</td>' +
            '<td><button type="button" class="button tws-remove-row">✕</button></td>' +
            '</tr>';
        $('#tws-cat-map-body').append(row);
    }

    renderMapTable();

    // Keşfedilen kategori etiketine tıklayınca ekle
    $(document).on('click', '.tws-tag', function () {
        var cat = $(this).data('cat');
        // Zaten tabloda var mı?
        var exists = false;
        $('#tws-cat-map-body .tws-trendyol-cat').each(function () {
            if ($(this).val() === cat) { exists = true; }
        });
        if (!exists) {
            addMapRow(cat, 0);
        }
        $(this).addClass('tws-tag-added');
    });

    // Satır ekle
    $('#tws-add-map-row').on('click', function () {
        addMapRow('', 0);
    });

    // Satır sil
    $(document).on('click', '.tws-remove-row', function () {
        $(this).closest('tr').remove();
    });

    // Eşleştirmeyi kaydet
    $('#tws-save-map-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#tws-map-result');
        var map     = {};

        $('#tws-cat-map-body tr').each(function () {
            var trendyolCat = $(this).find('.tws-trendyol-cat').val().trim();
            var wcId        = parseInt($(this).find('.tws-wc-cat-select').val(), 10);
            if (trendyolCat && wcId > 0) {
                map[trendyolCat] = wcId;
            }
        });

        $btn.prop('disabled', true).text('Kaydediliyor…');
        $result.hide().removeClass('is-error is-success');

        $.post(twsData.ajaxUrl, {
            action: 'tws_save_category_map',
            nonce:  twsData.nonce,
            map:    JSON.stringify(map)
        })
        .done(function (res) {
            if (res.success) {
                categoryMap = map;
                $result.addClass('is-success').text('✓ ' + res.data.message).show();
            } else {
                $result.addClass('is-error').text('✗ ' + (res.data.message || 'Kayıt başarısız.')).show();
            }
        })
        .fail(function () {
            $result.addClass('is-error').text('Sunucu hatası oluştu.').show();
        })
        .always(function () {
            $btn.prop('disabled', false).text('Eşleştirmeyi Kaydet');
        });
    });

    // ----------------------------------------------------------------
    // Bağlantı Testi
    // ----------------------------------------------------------------
    $('#tws-test-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#tws-test-result');

        $btn.prop('disabled', true).text('Test ediliyor…');
        $result.hide().removeClass('is-error is-success');

        $.post(twsData.ajaxUrl, { action: 'tws_test_connection', nonce: twsData.nonce })
        .done(function (res) {
            if (res.success) {
                $result.addClass('is-success').text('✓ ' + res.data.message).show();
            } else {
                $result.addClass('is-error').text('✗ ' + (res.data.message || 'Bağlantı başarısız.')).show();
            }
        })
        .fail(function () {
            $result.addClass('is-error').text('Sunucu hatası oluştu.').show();
        })
        .always(function () {
            $btn.prop('disabled', false).text('Bağlantıyı Test Et');
        });
    });

    // ----------------------------------------------------------------
    // Manuel Senkronizasyon
    // ----------------------------------------------------------------
    $('#tws-sync-btn').on('click', function () {
        var $btn      = $(this);
        var $progress = $('#tws-sync-progress');
        var $result   = $('#tws-sync-result');

        $btn.prop('disabled', true).text('Senkronize ediliyor…');
        $result.hide().removeClass('is-error is-success');
        $progress.show();
        $('#tws-sync-message').text('Trendyol\'dan ürünler alınıyor…');

        $.post(twsData.ajaxUrl, { action: 'tws_manual_sync', nonce: twsData.nonce })
        .done(function (res) {
            $progress.hide();
            if (res.success) {
                var d = res.data;
                $result.addClass('is-success').text('✓ ' + (d.message || 'Tamamlandı.')).show();
                $('#tws-last-sync').text(new Date().toLocaleString('tr-TR'));
            } else {
                var msg = (res.data && res.data.message) ? res.data.message : 'Senkronizasyon başarısız.';
                $result.addClass('is-error').text('✗ ' + msg).show();
            }
            location.reload();
        })
        .fail(function () {
            $progress.hide();
            $result.addClass('is-error').text('Sunucu hatası oluştu. PHP error log\'unu kontrol edin.').show();
        })
        .always(function () {
            $btn.prop('disabled', false).text('Manuel Senkronize Et');
        });
    });

}(jQuery));
