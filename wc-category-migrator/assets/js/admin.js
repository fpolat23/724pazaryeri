/* global wcCatMigrator, jQuery */
(function ($) {
    'use strict';

    var pollTimer = null;
    var currentJobId = null;

    $('#wc-cat-import-form').on('submit', function (e) {
        e.preventDefault();

        var $form    = $(this);
        var $btn     = $('#btn-import');
        var $spinner = $form.find('.spinner');
        var $progress = $('#cat-import-progress');
        var file     = document.getElementById('cat-xml-file');

        if (!file.files || !file.files.length) {
            alert('Lütfen bir XML dosyası seçin.');
            return;
        }

        $btn.prop('disabled', true).text('Yükleniyor…');
        $spinner.addClass('is-active');
        $progress.hide();

        var fd = new FormData($form[0]);
        fd.append('action', 'wc_cat_start_import');
        fd.append('nonce',  wcCatMigrator.nonce);

        fd.delete('update_existing');
        fd.append('update_existing', $form.find('[name="update_existing"]:checked').val() || '1');

        fd.delete('download_images');
        if ($form.find('[name="download_images"]').is(':checked')) fd.append('download_images', '1');

        $.ajax({
            url: wcCatMigrator.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            timeout: 60000,
        })
        .done(function (res) {
            $btn.prop('disabled', false).text('⬆ İçe Aktarmayı Başlat');
            $spinner.removeClass('is-active');

            if (res.success && res.data.job_id) {
                currentJobId = res.data.job_id;
                $progress.show();
                setProgress(0, 0, 0, 'İşlem başlatıldı…');
                startPolling(currentJobId);
            } else {
                alert((res.data && res.data.message) ? res.data.message : 'İçe aktarma başlatılamadı.');
            }
        })
        .fail(function () {
            $btn.prop('disabled', false).text('⬆ İçe Aktarmayı Başlat');
            $spinner.removeClass('is-active');
            alert('Sunucu bağlantı hatası.');
        });
    });

    function startPolling(jobId) {
        stopPolling();
        poll(jobId);
        pollTimer = setInterval(function () { poll(jobId); }, 4000);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function poll(jobId) {
        $.ajax({
            url: wcCatMigrator.ajaxUrl,
            type: 'GET',
            data: {
                action:  'wc_cat_job_status',
                nonce:   wcCatMigrator.nonce,
                job_id:  jobId,
            },
            timeout: 15000,
        })
        .done(function (res) {
            if (!res.success) return;

            var d = res.data;
            setProgress(d.percent || 0, d.processed || 0, d.total || 0, statusLabel(d.status));

            if (d.errors && d.errors.length) {
                showErrors(d.errors);
            }

            if (d.status === 'completed' || d.status === 'failed') {
                stopPolling();
                setProgress(
                    d.status === 'completed' ? 100 : (d.percent || 0),
                    d.processed || 0,
                    d.total || 0,
                    d.status === 'completed' ? 'Tamamlandı ✓' : 'Hatalı ✗'
                );
            }
        });
    }

    function setProgress(pct, processed, total, statusText) {
        $('#cat-import-progress .wc-cat-progress-inner').css('width', pct + '%');
        $('#cat-import-progress .wc-cat-progress-text').text(processed + ' / ' + total);
        $('#cat-import-progress .wc-cat-progress-status').text(statusText);
    }

    function showErrors(errors) {
        var $status = $('#cat-import-progress .wc-cat-progress-status');
        var html = '<div class="wc-cat-errors"><strong>Hatalar:</strong><ul>';
        errors.forEach(function (e) { html += '<li>' + esc(e) + '</li>'; });
        html += '</ul></div>';
        if (!$('#cat-import-progress .wc-cat-errors').length) {
            $('#cat-import-progress').append(html);
        }
    }

    function statusLabel(s) {
        return { pending: 'Bekliyor…', processing: 'İşleniyor…', completed: 'Tamamlandı ✓', failed: 'Hatalı ✗' }[s] || s;
    }

    function esc(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

}(jQuery));
