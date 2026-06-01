/* global wcCatMigrator, jQuery */
(function ($) {
    'use strict';

    $('#wc-cat-import-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#btn-import');
        var $spinner = $form.find('.spinner');
        var $result  = $('#import-result');
        var file     = document.getElementById('cat-xml-file');

        if (!file.files || !file.files.length) {
            alert('Lütfen bir XML dosyası seçin.');
            return;
        }

        $btn.prop('disabled', true).text('İşleniyor…');
        $spinner.addClass('is-active');
        $result.hide();

        var fd = new FormData($form[0]);
        fd.append('action', 'wc_cat_import');
        fd.append('nonce',  wcCatMigrator.nonce);

        // Radio
        fd.delete('update_existing');
        fd.append('update_existing', $form.find('[name="update_existing"]:checked').val() || '1');

        // Checkbox
        fd.delete('download_images');
        if ($form.find('[name="download_images"]').is(':checked')) fd.append('download_images', '1');

        $.ajax({
            url: wcCatMigrator.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            timeout: 300000,
        })
        .done(function (res) {
            if (res.success) {
                showResult(res.data.results, false);
            } else {
                showResult({ errors: [res.data.message || 'Bilinmeyen hata.'] }, true);
            }
        })
        .fail(function () {
            showResult({ errors: ['Sunucu bağlantı hatası.'] }, true);
        })
        .always(function () {
            $btn.prop('disabled', false).text('⬆ İçe Aktarmayı Başlat');
            $spinner.removeClass('is-active');
        });
    });

    function showResult(r, isError) {
        var $el = $('#import-result');
        var html = '<div class="wc-cat-result' + (isError ? ' is-error' : '') + '">';

        if (!isError) {
            html += '<div class="wc-cat-result-grid">';
            html += stat(r.created || 0, 'Oluşturuldu', 'is-created');
            html += stat(r.updated || 0, 'Güncellendi', 'is-updated');
            html += stat(r.skipped || 0, 'Atlandı', '');
            html += stat(r.images  || 0, 'Resim',     'is-images');
            if ((r.errors || []).length) {
                html += stat(r.errors.length, 'Hata', 'is-error');
            }
            html += '</div>';
        }

        if ((r.errors || []).length) {
            html += '<div class="wc-cat-errors"><strong>Hatalar:</strong><ul>';
            r.errors.forEach(function (e) { html += '<li>' + esc(e) + '</li>'; });
            html += '</ul></div>';
        } else if (isError) {
            html += '<p>' + esc(r.errors && r.errors[0] ? r.errors[0] : 'Hata oluştu.') + '</p>';
        }

        html += '</div>';
        $el.html(html).show();
    }

    function stat(n, label, cls) {
        return '<div class="wc-cat-result-item ' + cls + '">' +
               '<div class="num">' + n + '</div>' +
               '<div class="lbl">' + label + '</div>' +
               '</div>';
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

}(jQuery));
