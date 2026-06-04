/* WC REST Migrator – Admin JS */
/* global wcRm, jQuery */
(function ($) {
	'use strict';

	var batchRunning = false;
	var batchJobId   = null;

	// ---- Source form ----
	$('#wc-rm-source-form').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $btn  = $form.find('button[type="submit"]');
		var $spin = $form.find('.spinner');
		var $msg  = $('#wc-rm-source-msg');

		$btn.prop('disabled', true);
		$spin.addClass('is-active');
		$msg.text('').removeClass('success error');

		$.post(wcRm.ajaxUrl, $form.serialize() + '&action=wc_rm_save_source&nonce=' + encodeURIComponent(wcRm.nonce))
		.done(function (res) {
			if (res && res.success) {
				$msg.addClass('success').text('Kaydedildi.');
				setTimeout(function () { window.location.href = window.location.pathname + '?page=wc-rest-migrator&tab=sources'; }, 800);
			} else {
				$msg.addClass('error').text((res && res.data && res.data.message) || 'Kayıt başarısız.');
				$btn.prop('disabled', false);
				$spin.removeClass('is-active');
			}
		})
		.fail(function () {
			$msg.addClass('error').text('Sunucu bağlantı hatası.');
			$btn.prop('disabled', false);
			$spin.removeClass('is-active');
		});
	});

	// ---- Delete source ----
	$(document).on('click', '.js-rm-del-source', function () {
		if (!confirm(wcRm.confirmDelete)) return;
		var $btn = $(this);
		$btn.prop('disabled', true);
		$.post(wcRm.ajaxUrl, { action: 'wc_rm_delete_source', id: $btn.data('id'), nonce: wcRm.nonce })
		.done(function (res) {
			if (res && res.success) window.location.reload();
			else { alert((res && res.data && res.data.message) || 'Silinemedi.'); $btn.prop('disabled', false); }
		});
	});

	// ---- Test connection ----
	$(document).on('click', '.js-rm-test', function () {
		var $btn = $(this);
		$btn.prop('disabled', true).text('Test ediliyor…');
		$('#wc-rm-test-result').hide().empty();

		$.post(wcRm.ajaxUrl, { action: 'wc_rm_test_connection', source_id: $btn.data('id'), nonce: wcRm.nonce })
		.done(function (res) {
			$btn.prop('disabled', false).text('🔍 Test');
			var ok  = res && res.success;
			var msg = (res && res.data && res.data.message) || (ok ? 'Başarılı.' : 'Hata.');
			var bg  = ok ? '#d4edda' : '#f8d7da';
			var bdr = ok ? '#c3e6cb' : '#f5c6cb';
			var col = ok ? '#155724' : '#721c24';
			$('#wc-rm-test-result').show().html(
				'<div style="background:' + bg + ';border:1px solid ' + bdr + ';border-radius:4px;padding:12px 16px;margin-top:10px;color:' + col + '">' +
				(ok ? '✓ ' : '✗ ') + escHtml(msg) + '</div>'
			);
		})
		.fail(function () {
			$btn.prop('disabled', false).text('🔍 Test');
			$('#wc-rm-test-result').show().html('<div style="color:#dc3232;margin-top:10px">Sunucu bağlantı hatası.</div>');
		});
	});

	// ---- Import form ----
	$('#wc-rm-import-form').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $btn  = $('#btn-import');
		var $spin = $form.find('.spinner');

		var sourceId = $form.find('[name="source_id"]').val();
		if (!sourceId) { alert('Lütfen bir kaynak seçin.'); return; }

		$btn.prop('disabled', true);
		$spin.addClass('is-active');

		$.post(wcRm.ajaxUrl, {
			action:              'wc_rm_start_import',
			nonce:               wcRm.nonce,
			source_id:           sourceId,
			duplicate_strategy:  $form.find('[name="duplicate_strategy"]').val(),
			status:              $form.find('[name="status"]').val(),
			download_images:     $form.find('[name="download_images"]').is(':checked') ? '1' : '',
		})
		.done(function (res) {
			if (res && res.success && res.data && res.data.job_id) {
				$('#wc-rm-progress').show();
				startBatchLoop(res.data.job_id);
			} else {
				alert('Hata: ' + ((res && res.data && res.data.message) || 'Bilinmeyen hata.'));
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

	// ---- AJAX batch loop ----
	// Each call processes one page of products server-side and returns updated status.
	// JS loops immediately until completed/failed/cancelled.

	function startBatchLoop(jobId) {
		if (batchRunning && batchJobId === jobId) return;
		batchRunning = false;
		batchJobId   = jobId;
		runBatch(jobId);
	}

	function runBatch(jobId) {
		if (batchRunning || batchJobId !== jobId) return;
		batchRunning = true;

		$.ajax({
			url:     wcRm.ajaxUrl,
			method:  'GET',
			data:    { action: 'wc_rm_import_batch', job_id: jobId, nonce: wcRm.nonce },
			timeout: 150000, // 2.5 min — long enough for one page including image downloads
		})
		.done(function (res) {
			batchRunning = false;
			if (!res || !res.success) {
				// Unexpected error — retry after short delay
				setTimeout(function () { runBatch(jobId); }, 4000);
				return;
			}
			var d = res.data;
			updateProgress(d);
			updateLiveItems(d);

			if (d.status === 'completed' || d.status === 'failed' || d.status === 'cancelled') {
				onImportDone(d);
			} else {
				// Continue immediately with next batch
				runBatch(jobId);
			}
		})
		.fail(function (jqXHR) {
			batchRunning = false;
			if (jqXHR.statusText === 'timeout') {
				// Server-side processing still ran; just retry
				setTimeout(function () { runBatch(jobId); }, 2000);
			} else {
				// Network error — retry with longer back-off
				setTimeout(function () { runBatch(jobId); }, 6000);
			}
		});
	}

	function onImportDone(d) {
		batchJobId = null;
		$('#btn-import').prop('disabled', false);
		$('#wc-rm-import-form .spinner').removeClass('is-active');

		if (d.status === 'completed') {
			showResult(d);
		} else if (d.status === 'failed' && d.errors && d.errors.length) {
			var errHtml = '<div class="wc-rm-errors" style="margin-top:10px"><strong>Hata:</strong><ul>';
			for (var i = 0; i < d.errors.length; i++) errHtml += '<li>' + escHtml(d.errors[i]) + '</li>';
			errHtml += '</ul></div>';
			$('#wc-rm-progress').append(errHtml);
		}
	}

	// ---- Resume on page load (from ?resume=JOB_ID) ----
	if (wcRm.resumeJobId && wcRm.resumeJobId > 0) {
		startBatchLoop(wcRm.resumeJobId);
	}

	function updateProgress(d) {
		var labels = {
			discovering: 'Kaynak siteden ürün sayısı alınıyor…',
			processing:  'Ürünler içe aktarılıyor…',
			completed:   'Tamamlandı!',
			failed:      'Hata oluştu.',
		};
		var pct = d.percent || 0;
		if (d.status === 'discovering') {
			$('#wc-rm-progress .wc-rm-progress-inner').css('width', '5%');
			$('#wc-rm-progress .wc-rm-progress-text').text('Bağlanıyor…');
		} else {
			$('#wc-rm-progress .wc-rm-progress-inner').css('width', pct + '%');
			var pageInfo = d.current_page ? ' — sayfa ' + d.current_page : '';
			$('#wc-rm-progress .wc-rm-progress-text').text(d.processed + ' / ' + d.total + ' (' + pct + '%)' + pageInfo);
		}
		var $st = $('#wc-rm-progress .wc-rm-progress-status');
		$st.removeClass('status-completed status-failed').text(labels[d.status] || d.status);
		if (d.status === 'completed') $st.addClass('status-completed');
		if (d.status === 'failed')    $st.addClass('status-failed');
	}

	function updateLiveItems(d) {
		var items = d.imported_items || [];
		if (!items.length) return;
		var html = '<div style="margin-top:12px">'
			+ '<strong style="color:#1e6f3e">Son aktarılan ürünler (en yeni ' + items.length + ' kayıt)</strong>'
			+ '<div style="max-height:320px;overflow-y:auto;margin-top:6px;border:1px solid #c8e6c9;border-radius:4px">'
			+ '<table style="width:100%;border-collapse:collapse;font-size:12px">'
			+ '<thead><tr style="background:#e8f5e9">'
			+ '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #c8e6c9;position:sticky;top:0;background:#e8f5e9">SKU</th>'
			+ '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #c8e6c9;position:sticky;top:0;background:#e8f5e9">Ürün Adı</th>'
			+ '<th style="padding:4px 8px;text-align:left;border-bottom:1px solid #c8e6c9;position:sticky;top:0;background:#e8f5e9">Durum</th>'
			+ '</tr></thead><tbody>';
		for (var i = items.length - 1; i >= 0; i--) {
			var item   = items[i];
			var color  = item.status === 'created' ? '#1e6f3e' : '#856404';
			var label  = item.status === 'created' ? 'Oluşturuldu' : 'Güncellendi';
			html += '<tr style="border-bottom:1px solid #f0f0f0">'
				+ '<td style="padding:3px 8px;font-family:monospace">' + escHtml(item.sku  || '—') + '</td>'
				+ '<td style="padding:3px 8px">'                        + escHtml(item.name || '—') + '</td>'
				+ '<td style="padding:3px 8px;color:' + color + ';font-weight:600">' + label + '</td>'
				+ '</tr>';
		}
		html += '</tbody></table></div></div>';
		$('#wc-rm-live-items').html(html);
	}

	function showResult(d) {
		var errCnt = d.errors ? d.errors.length : 0;
		var html = '<div class="wc-rm-result"><strong>Sonuç:</strong>'
			+ '<div class="wc-rm-result-grid">'
			+ resultItem(d.created,      'Oluşturuldu', 'created')
			+ resultItem(d.updated,      'Güncellendi', 'updated')
			+ resultItem(d.skipped,      'Atlandı',     'skipped')
			+ resultItem(d.errors_count, 'Hata',        'errors')
			+ '</div></div>';
		if (errCnt) {
			html += '<div class="wc-rm-errors"><strong>Hatalar:</strong><ul>';
			for (var i = 0; i < d.errors.length; i++) html += '<li>' + escHtml(d.errors[i]) + '</li>';
			html += '</ul></div>';
		}
		$('#wc-rm-live-items').before(html);
	}

	function resultItem(val, lbl, cls) {
		return '<div class="wc-rm-result-item ' + cls + '">'
			+ '<span class="wc-rm-result-val">' + val + '</span>'
			+ '<span class="wc-rm-result-lbl">' + lbl + '</span>'
			+ '</div>';
	}

	// ---- Delete job ----
	$(document).on('click', '.js-rm-delete-job', function () {
		if (!confirm(wcRm.confirmDelete)) return;
		var $btn = $(this);
		$btn.prop('disabled', true);
		$.post(wcRm.ajaxUrl, { action: 'wc_rm_delete_job', job_id: $btn.data('job-id'), nonce: wcRm.nonce })
		.done(function (res) {
			if (res && res.success) window.location.reload();
			else { alert('Silinemedi.'); $btn.prop('disabled', false); }
		});
	});

	function escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

}(jQuery));
