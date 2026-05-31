/* global wcXmlMigrator, jQuery */
(function ($) {
    'use strict';

    var POLL_INTERVAL = 4000; // ms

    // ---- Yardımcılar ----

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function startPolling(jobId, $progressWrap, onComplete) {
        var timer = setInterval(function () {
            $.get(wcXmlMigrator.ajaxUrl, {
                action:   'wc_xml_job_status',
                job_id:   jobId,
                nonce:    wcXmlMigrator.nonce,
            })
            .done(function (res) {
                if (!res.success) return;
                var d = res.data;

                updateProgress($progressWrap, d.percent, d.processed, d.total, d.status);

                if (d.status === 'completed' || d.status === 'failed') {
                    clearInterval(timer);
                    onComplete(d);
                }
            });
        }, POLL_INTERVAL);
    }

    function updateProgress($wrap, pct, processed, total, status) {
        $wrap.find('.wc-xml-progress-bar-inner').css('width', pct + '%');
        $wrap.find('.wc-xml-progress-text').text(processed + ' / ' + total + '  (' + pct + '%)');
        $wrap.find('.wc-xml-progress-status').text(
            status === 'completed' ? wcXmlMigrator.i18n.completed :
            status === 'failed'    ? wcXmlMigrator.i18n.failed    :
                                     wcXmlMigrator.i18n.processing + '…'
        );
    }

    function showErrors($wrap, errors) {
        if (!errors || !errors.length) return;
        var html = '<ul class="wc-xml-error-list">';
        errors.slice(0, 10).forEach(function (e) { html += '<li>' + escHtml(e) + '</li>'; });
        if (errors.length > 10) html += '<li>… ve ' + (errors.length - 10) + ' hata daha</li>';
        html += '</ul>';
        $wrap.find('.wc-xml-progress-status').after(html);
    }

    function setBtn($btn, $spinner, disabled) {
        $btn.prop('disabled', disabled);
        $spinner.toggleClass('is-active', disabled);
    }

    // ---- Dışa Aktar ----

    $('#wc-xml-export-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#btn-export');
        var $spinner = $form.find('.spinner');
        var $prog    = $('#export-progress');

        setBtn($btn, $spinner, true);
        $btn.text(wcXmlMigrator.i18n.starting);

        var formData = new FormData();
        formData.append('action', 'wc_xml_start_export');
        formData.append('nonce',  wcXmlMigrator.nonce);
        formData.append('status', $form.find('[name="status"]').val());
        formData.append('batch_size', $form.find('[name="batch_size"]').val());

        $form.find('#export-categories option:selected').each(function () {
            formData.append('categories[]', $(this).val());
        });
        ['include_images', 'include_variations', 'include_meta'].forEach(function (n) {
            if ($form.find('[name="' + n + '"]').is(':checked')) formData.append(n, '1');
        });

        $.ajax({ url: wcXmlMigrator.ajaxUrl, type: 'POST', data: formData, processData: false, contentType: false })
        .done(function (res) {
            if (!res.success) {
                alert('Hata: ' + res.data.message);
                setBtn($btn, $spinner, false);
                $btn.text('Dışa Aktarmayı Başlat');
                return;
            }

            setBtn($btn, $spinner, false);
            $btn.text('Dışa Aktarmayı Başlat');
            $prog.show();

            startPolling(res.data.job_id, $prog, function (d) {
                if (d.status === 'completed' && d.file_url) {
                    $prog.find('.wc-xml-progress-status').after(
                        '<a href="' + escHtml(d.file_url) + '" download class="wc-xml-download-btn">' +
                        '&#x2B07; ' + escHtml(wcXmlMigrator.i18n.download) + '</a>'
                    );
                }
                if (d.status === 'failed') showErrors($prog, d.errors);
            });
        })
        .fail(function () {
            alert('Sunucu hatası.');
            setBtn($btn, $spinner, false);
            $btn.text('Dışa Aktarmayı Başlat');
        });
    });

    // ---- İçe Aktar ----

    $('#wc-xml-import-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#btn-import');
        var $spinner = $form.find('.spinner');
        var $prog    = $('#import-progress');
        var fileInput = document.getElementById('xml-file');

        if (!fileInput.files || !fileInput.files.length) {
            alert(wcXmlMigrator.i18n.noFile);
            return;
        }

        setBtn($btn, $spinner, true);
        $btn.text(wcXmlMigrator.i18n.starting);

        var formData = new FormData($form[0]);
        formData.append('action', 'wc_xml_start_import');
        formData.append('nonce',  wcXmlMigrator.nonce);

        formData.delete('download_images');
        if ($form.find('[name="download_images"]').is(':checked')) formData.append('download_images', '1');

        $.ajax({ url: wcXmlMigrator.ajaxUrl, type: 'POST', data: formData, processData: false, contentType: false })
        .done(function (res) {
            if (!res.success) {
                alert('Hata: ' + res.data.message);
                setBtn($btn, $spinner, false);
                $btn.text('İçe Aktarmayı Başlat');
                return;
            }

            setBtn($btn, $spinner, false);
            $btn.text('İçe Aktarmayı Başlat');
            $prog.show();

            startPolling(res.data.job_id, $prog, function (d) {
                if (d.status === 'failed') showErrors($prog, d.errors);
            });
        })
        .fail(function () {
            alert('Sunucu hatası.');
            setBtn($btn, $spinner, false);
            $btn.text('İçe Aktarmayı Başlat');
        });
    });

}(jQuery));
