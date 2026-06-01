/* global twsData, jQuery */
(function ($) {
    'use strict';

    // --- Bağlantı Testi ---
    $('#tws-test-btn').on('click', function () {
        var $btn    = $(this);
        var $result = $('#tws-test-result');

        $btn.prop('disabled', true).text('Test ediliyor…');
        $result.hide().removeClass('is-error is-success');

        $.post(twsData.ajaxUrl, {
            action: 'tws_test_connection',
            nonce:  twsData.nonce
        })
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

    // --- Manuel Senkronizasyon ---
    $('#tws-sync-btn').on('click', function () {
        var $btn      = $(this);
        var $progress = $('#tws-sync-progress');
        var $result   = $('#tws-sync-result');
        var $message  = $('#tws-sync-message');

        $btn.prop('disabled', true).text('Senkronize ediliyor…');
        $result.hide().removeClass('is-error is-success');
        $progress.show();
        $message.text('Trendyol\'dan ürünler alınıyor…');

        $.post(twsData.ajaxUrl, {
            action: 'tws_manual_sync',
            nonce:  twsData.nonce
        })
        .done(function (res) {
            $progress.hide();

            if (res.success) {
                var d = res.data;
                var msg = d.message || 'Senkronizasyon tamamlandı.';
                $result.addClass('is-success').text('✓ ' + msg).show();
                $('#tws-last-sync').text(new Date().toLocaleString('tr-TR'));
            } else {
                var errMsg = (res.data && res.data.message) ? res.data.message : 'Senkronizasyon başarısız.';
                $result.addClass('is-error').text('✗ ' + errMsg).show();
            }

            // Log tablosunu yenile
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
