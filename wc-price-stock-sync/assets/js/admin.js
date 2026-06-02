/* WC Price & Stock Sync – Admin JS */
/* global wcPss, jQuery */
(function ($) {
	'use strict';

	var pollTimer = null;
	var currentJobId = null;

	// ----------------------------------------------------------------
	// Update form submit
	// ----------------------------------------------------------------
	$('#wc-pss-update-form').on('submit', function (e) {
		e.preventDefault();

		var $form   = $(this);
		var $btn    = $('#btn-update');
		var $spin   = $form.find('.spinner');
		var csvFile = document.getElementById('pss-csv-file').files[0];

		if (!csvFile) {
			alert('Lütfen bir CSV dosyası seçin.');
			return;
		}

		var fd = new FormData();
		fd.append('action',        'wc_pss_start_update');
		fd.append('nonce',         wcPss.nonce);
		fd.append('csv_file',      csvFile);
		fd.append('update_prices', $form.find('[name="update_prices"]').is(':checked') ? '1' : '');
		fd.append('update_stock',  $form.find('[name="update_stock"]').is(':checked')  ? '1' : '');

		$btn.prop('disabled', true);
		$spin.addClass('is-active');

		$.ajax({
			url:         wcPss.ajaxUrl,
			type:        'POST',
			data:        fd,
			processData: false,
			contentType: false
		})
		.done(function (res) {
			if (res && res.success && res.data && res.data.job_id) {
				currentJobId = res.data.job_id;
				$('#pss-progress').show();
				startPolling(currentJobId);
			} else {
				var msg = (res && res.data && res.data.message) ? res.data.message : 'Bilinmeyen hata.';
				alert('Hata: ' + msg);
				$btn.prop('disabled', false);
				$spin.removeClass('is-active');
			}
		})
		.fail(function () {
			alert('Sunucu bağlantı hatası.');
			$btn.prop('disabled', false);
			$spin.removeClass('is-active');
		});
	});

	// ----------------------------------------------------------------
	// Polling
	// ----------------------------------------------------------------
	function startPolling(jobId) {
		stopPolling();
		poll(jobId);
		pollTimer = setInterval(function () { poll(jobId); }, 4000);
	}

	function stopPolling() {
		if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
	}

	function poll(jobId) {
		$.get(wcPss.ajaxUrl, { action: 'wc_pss_job_status', job_id: jobId, nonce: wcPss.nonce })
		.done(function (res) {
			if (!res || !res.success) return;
			var d = res.data;
			updateProgress(d);
			if (d.status === 'completed' || d.status === 'failed' || d.status === 'cancelled') {
				stopPolling();
				$('#btn-update').prop('disabled', false);
				$('#wc-pss-update-form .spinner').removeClass('is-active');
				if (d.status === 'completed') {
					showResult(d);
				}
			}
		});
	}

	// ----------------------------------------------------------------
	// Progress UI
	// ----------------------------------------------------------------
	function updateProgress(d) {
		var pct = d.percent || 0;
		$('#pss-progress .wc-pss-progress-inner').css('width', pct + '%');
		$('#pss-progress .wc-pss-progress-text').text(d.processed + ' / ' + d.total + ' (' + pct + '%)');

		var $status = $('#pss-progress .wc-pss-progress-status');
		$status.removeClass('status-completed status-failed status-cancelled');
		var labels = {
			pending:    'Sıraya alındı…',
			processing: 'İşleniyor…',
			completed:  'Tamamlandı!',
			failed:     'Hata oluştu.',
			cancelled:  'İptal edildi.'
		};
		$status.text(labels[d.status] || d.status);
		if (d.status === 'completed')  $status.addClass('status-completed');
		if (d.status === 'failed')     $status.addClass('status-failed');
		if (d.status === 'cancelled')  $status.addClass('status-cancelled');
	}

	function showResult(d) {
		var html =
			'<div class="wc-pss-result">' +
			'<strong>Sonuç:</strong>' +
			'<div class="wc-pss-result-grid">' +
			resultItem(d.updated,   'Güncellendi', 'updated') +
			resultItem(d.not_found, 'Bulunamadı',  'not-found') +
			resultItem(d.skipped,   'Atlandı',     'skipped') +
			resultItem(d.errors ? d.errors.length : 0, 'Hata', 'errors') +
			'</div></div>';

		if (d.errors && d.errors.length) {
			html += '<div class="wc-pss-errors"><strong>Hatalar:</strong><ul>';
			for (var i = 0; i < d.errors.length; i++) {
				html += '<li>' + escHtml(d.errors[i]) + '</li>';
			}
			html += '</ul></div>';
		}

		$('#pss-progress').append(html);
	}

	function resultItem(val, lbl, cls) {
		return '<div class="wc-pss-result-item ' + cls + '">' +
			'<span class="wc-pss-result-val">' + val + '</span>' +
			'<span class="wc-pss-result-lbl">' + lbl + '</span>' +
			'</div>';
	}

	function escHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;');
	}

	// ----------------------------------------------------------------
	// History: cancel / delete
	// ----------------------------------------------------------------
	$(document).on('click', '.js-pss-cancel', function () {
		if (!confirm(wcPss.confirmCancel)) return;
		jobAction($(this), 'cancel');
	});

	$(document).on('click', '.js-pss-delete', function () {
		if (!confirm(wcPss.confirmDelete)) return;
		jobAction($(this), 'delete');
	});

	function jobAction($btn, action) {
		var jobId = $btn.data('job-id');
		var $row  = $btn.closest('tr');
		$row.find('button').prop('disabled', true);

		$.post(wcPss.ajaxUrl, {
			action:  'wc_pss_' + action + '_job',
			job_id:  jobId,
			nonce:   wcPss.nonce
		})
		.done(function (res) {
			if (res && res.success) {
				window.location.reload();
			} else {
				var msg = (res && res.data && res.data.message) ? res.data.message : 'İşlem başarısız.';
				alert(msg);
				$row.find('button').prop('disabled', false);
			}
		})
		.fail(function () {
			alert('Sunucu bağlantı hatası.');
			$row.find('button').prop('disabled', false);
		});
	}

}(jQuery));
