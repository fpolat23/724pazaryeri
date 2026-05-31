/* global wcXmlMigrator, jQuery */
(function ($) {
    'use strict';

    // ---- Yardımcı ----

    function showNotice($el, type, title, body, errors) {
        var html = '<div class="notice-title">' + escHtml(title) + '</div>';
        if (body) html += '<p>' + escHtml(body) + '</p>';
        if (errors && errors.length) {
            html += '<ul class="wc-xml-error-list">';
            errors.forEach(function (e) { html += '<li>' + escHtml(e) + '</li>'; });
            html += '</ul>';
        }
        $el
            .removeClass('is-success is-error')
            .addClass(type === 'success' ? 'is-success' : 'is-error')
            .html(html)
            .show();
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setLoading($form, $btn, $spinner, loading) {
        $btn.prop('disabled', loading);
        if (loading) {
            $spinner.addClass('is-active');
            $btn.text(loading);
        } else {
            $spinner.removeClass('is-active');
        }
    }

    // ---- Dışa Aktar ----

    $('#wc-xml-export-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $form.find('#btn-export');
        var $spinner = $form.find('.spinner');
        var $result  = $('#export-result');
        var origText = $btn.text();

        setLoading($form, $btn, $spinner, wcXmlMigrator.i18n.exporting);

        var formData = new FormData($form[0]);
        formData.append('action', 'wc_xml_export');
        formData.append('nonce', wcXmlMigrator.nonce);

        // Çoklu select için manuel ekle
        formData.delete('categories[]');
        $form.find('#export-categories option:selected').each(function () {
            formData.append('categories[]', $(this).val());
        });

        // Checkbox'ları manuel gönder
        ['include_images', 'include_variations', 'include_meta'].forEach(function (name) {
            formData.delete(name);
            if ($form.find('[name="' + name + '"]').is(':checked')) {
                formData.append(name, '1');
            }
        });

        $.ajax({
            url: wcXmlMigrator.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        })
        .done(function (res) {
            if (res.success) {
                var html =
                    '<div class="notice-title">' + escHtml(res.data.message) + '</div>' +
                    '<a href="' + escHtml(res.data.file_url) + '" download="' +
                    escHtml(res.data.file_name) + '" class="wc-xml-download-btn">' +
                    '&#x2B07; ' + escHtml(res.data.file_name) + '</a>';
                $result
                    .removeClass('is-error')
                    .addClass('is-success')
                    .html(html)
                    .show();
            } else {
                showNotice($result, 'error', 'Hata', res.data.message);
            }
        })
        .fail(function () {
            showNotice($result, 'error', 'Sunucu hatası', 'Lütfen tekrar deneyin.');
        })
        .always(function () {
            $btn.prop('disabled', false).text(origText);
            $spinner.removeClass('is-active');
        });
    });

    // ---- İçe Aktar ----

    $('#wc-xml-import-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $form.find('#btn-import');
        var $spinner = $form.find('.spinner');
        var $result  = $('#import-result');
        var origText = $btn.text();
        var fileInput = document.getElementById('xml-file');

        if (!fileInput.files || !fileInput.files.length) {
            showNotice($result, 'error', 'Hata', wcXmlMigrator.i18n.noFile);
            return;
        }

        setLoading($form, $btn, $spinner, wcXmlMigrator.i18n.importing);

        var formData = new FormData($form[0]);
        formData.append('action', 'wc_xml_import');
        formData.append('nonce', wcXmlMigrator.nonce);

        // Checkbox
        formData.delete('download_images');
        if ($form.find('[name="download_images"]').is(':checked')) {
            formData.append('download_images', '1');
        }

        $.ajax({
            url: wcXmlMigrator.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            timeout: 600000,
        })
        .done(function (res) {
            if (res.success) {
                var errors = res.data.results && res.data.results.errors ? res.data.results.errors : [];
                showNotice($result, 'success', wcXmlMigrator.i18n.success, res.data.message, errors);
            } else {
                showNotice($result, 'error', 'Hata', res.data.message);
            }
        })
        .fail(function (xhr) {
            var msg = 'Sunucu hatası.';
            if (xhr.status === 0) msg = 'Bağlantı zaman aşımına uğradı. İşlem arka planda devam ediyor olabilir.';
            showNotice($result, 'error', 'Hata', msg);
        })
        .always(function () {
            $btn.prop('disabled', false).text(origText);
            $spinner.removeClass('is-active');
        });
    });

}(jQuery));
