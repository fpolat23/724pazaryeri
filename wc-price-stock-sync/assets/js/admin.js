/* WC Price & Stock Sync – Admin JS v2 */
/* global wcPss, jQuery */
(function ($) {
	'use strict';

	var pollTimer    = null;
	var currentJobId = null;

	// ----------------------------------------------------------------
	// Sources: form submit (add/edit)
	// ----------------------------------------------------------------
	$('#wc-pss-source-form').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $btn  = $form.find('button[type="submit"]');
		var $spin = $form.find('.spinner');
		var $msg  = $('#pss-source-msg');

		$btn.prop('disabled', true);
		$spin.addClass('is-active');
		$msg.text('').removeClass('success error');

		// serialize() properly encodes price_rules[N][min] etc. as PHP-parseable arrays
		var data = $form.serialize() + '&action=wc_pss_save_source&nonce=' + encodeURIComponent(wcPss.nonce);
		$.post(wcPss.ajaxUrl, data)
		.done(function (res) {
			if (res && res.success) {
				$msg.addClass('success').text('Kaynak kaydedildi.');
				setTimeout(function () { window.location.href = window.location.pathname + '?page=wc-pss&tab=sources'; }, 800);
			} else {
				var m = (res && res.data && res.data.message) ? res.data.message : 'Kayıt başarısız.';
				$msg.addClass('error').text(m);
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

	// Discovery method toggle
	$('input[name="discovery"]').on('change', function () {
		$('.wc-pss-crawl-row').toggle($(this).val() === 'crawl');
	});

	// Price rules: add row
	var ruleIdx = $('#pss-rules-body tr').length;
	$('#add-price-rule').on('click', function () {
		var i = ruleIdx++;
		$('#pss-rules-body').append(
			'<tr>' +
			'<td><input type="number" name="price_rules[' + i + '][min]" min="0" step="0.01" class="small-text" placeholder="0"></td>' +
			'<td><input type="number" name="price_rules[' + i + '][max]" min="0" step="0.01" class="small-text" placeholder="∞"></td>' +
			'<td><input type="number" name="price_rules[' + i + '][pct]" min="-100" max="10000" step="0.1" class="small-text" placeholder="20"> %</td>' +
			'<td><button type="button" class="button button-small js-remove-rule">✕</button></td>' +
			'</tr>'
		);
	});

	// Price rules: remove row
	$(document).on('click', '.js-remove-rule', function () {
		$(this).closest('tr').remove();
	});

	// Delete source
	$(document).on('click', '.js-pss-del-source', function () {
		if (!confirm(wcPss.confirmDelete)) return;
		var $btn = $(this);
		$btn.prop('disabled', true);
		$.post(wcPss.ajaxUrl, { action: 'wc_pss_delete_source', id: $btn.data('id'), nonce: wcPss.nonce })
		.done(function (res) {
			if (res && res.success) { window.location.reload(); }
			else { alert((res && res.data && res.data.message) || 'Silinemedi.'); $btn.prop('disabled', false); }
		});
	});

	// ----------------------------------------------------------------
	// Sync form submit
	// ----------------------------------------------------------------
	$('#wc-pss-sync-form').on('submit', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $btn  = $('#btn-sync');
		var $spin = $form.find('.spinner');

		var sourceId = $form.find('[name="source_id"]').val();
		if (!sourceId) { alert('Lütfen bir kaynak seçin.'); return; }

		$btn.prop('disabled', true);
		$spin.addClass('is-active');

		$.post(wcPss.ajaxUrl, {
			action:        'wc_pss_start_sync',
			nonce:         wcPss.nonce,
			source_id:     sourceId,
			update_prices: $form.find('[name="update_prices"]').is(':checked') ? '1' : '',
			update_stock:  $form.find('[name="update_stock"]').is(':checked')  ? '1' : '',
			match_by_name: $form.find('[name="match_by_name"]').is(':checked') ? '1' : '',
		})
		.done(function (res) {
			if (res && res.success && res.data && res.data.job_id) {
				currentJobId = res.data.job_id;
				$('#pss-progress').show();
				startPolling(currentJobId);
			} else {
				var m = (res && res.data && res.data.message) ? res.data.message : 'Bilinmeyen hata.';
				alert('Hata: ' + m);
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
				$('#btn-sync').prop('disabled', false);
				$('#wc-pss-sync-form .spinner').removeClass('is-active');
				if (d.status === 'completed') {
					showResult(d);
				} else if (d.status === 'failed' && d.errors && d.errors.length) {
					var errHtml = '<div class="wc-pss-errors" style="margin-top:10px"><strong>Hata detayı:</strong><ul>';
					for (var ei = 0; ei < d.errors.length; ei++) {
						errHtml += '<li>' + escHtml(d.errors[ei]) + '</li>';
					}
					errHtml += '</ul></div>';
					$('#pss-progress').append(errHtml);
				}
			}
		});
	}

	function updateProgress(d) {
		var labels = {
			discovering: 'Ürün URL\'leri keşfediliyor…',
			pending:     'Sıraya alındı…',
			processing:  'Ürün sayfaları işleniyor…',
			completed:   'Tamamlandı!',
			failed:      'Hata oluştu.',
			cancelled:   'İptal edildi.'
		};
		var pct = d.percent || 0;

		if (d.status === 'discovering') {
			$('#pss-progress .wc-pss-progress-inner').css('width', '5%');
			$('#pss-progress .wc-pss-progress-text').text('URL listesi alınıyor…');
		} else {
			$('#pss-progress .wc-pss-progress-inner').css('width', pct + '%');
			$('#pss-progress .wc-pss-progress-text').text(d.processed + ' / ' + d.total + ' (' + pct + '%)');
		}

		var $st = $('#pss-progress .wc-pss-progress-status');
		$st.removeClass('status-completed status-failed status-cancelled')
		   .text(labels[d.status] || d.status);
		if (d.status === 'completed') $st.addClass('status-completed');
		if (d.status === 'failed')    $st.addClass('status-failed');
		if (d.status === 'cancelled') $st.addClass('status-cancelled');
	}

	function showResult(d) {
		var errCnt = d.errors ? d.errors.length : 0;
		var html = '<div class="wc-pss-result"><strong>Sonuç:</strong>'
			+ '<div class="wc-pss-result-grid">'
			+ resultItem(d.updated,   'Güncellendi', 'updated')
			+ resultItem(d.not_found, 'Bulunamadı',  'not-found')
			+ resultItem(d.skipped,   'Atlandı',     'skipped')
			+ resultItem(errCnt,      'Hata',        'errors')
			+ '</div></div>';

		if (errCnt) {
			html += '<div class="wc-pss-errors"><strong>Hatalar:</strong><ul>';
			for (var i = 0; i < d.errors.length; i++) {
				html += '<li>' + escHtml(d.errors[i]) + '</li>';
			}
			html += '</ul></div>';
		}
		$('#pss-progress').append(html);
	}

	function resultItem(val, lbl, cls) {
		return '<div class="wc-pss-result-item ' + cls + '">'
			+ '<span class="wc-pss-result-val">' + val + '</span>'
			+ '<span class="wc-pss-result-lbl">' + lbl + '</span>'
			+ '</div>';
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
		var $row = $btn.closest('tr');
		$row.find('button').prop('disabled', true);
		$.post(wcPss.ajaxUrl, { action: 'wc_pss_' + action + '_job', job_id: $btn.data('job-id'), nonce: wcPss.nonce })
		.done(function (res) {
			if (res && res.success) { window.location.reload(); }
			else {
				alert((res && res.data && res.data.message) || 'İşlem başarısız.');
				$row.find('button').prop('disabled', false);
			}
		})
		.fail(function () {
			alert('Sunucu bağlantı hatası.');
			$row.find('button').prop('disabled', false);
		});
	}

	// ----------------------------------------------------------------
	// Helpers
	// ----------------------------------------------------------------
	function formToObj($form) {
		var obj = {};
		$form.serializeArray().forEach(function (f) { obj[f.name] = f.value; });
		// Checkboxes not in serializeArray if unchecked – that's fine, PHP handles absent = false
		return obj;
	}

	function escHtml(s) {
		return String(s)
			.replace(/&/g, '&amp;').replace(/</g, '&lt;')
			.replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

}(jQuery));
