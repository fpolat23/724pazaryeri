/* PZV Vendor Migrator – Admin JS */
/* global pzvMig, jQuery */
(function ($) {
	'use strict';

	// ── Helpers ─────────────────────────────────────────────────────────────

	function post(action, data, done, fail) {
		$.post(pzvMig.ajaxUrl, $.extend({ action: action, nonce: pzvMig.nonce }, data))
			.done(done)
			.fail(fail || function () { alert('Sunucu bağlantı hatası.'); });
	}

	function escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	// ── Settings ─────────────────────────────────────────────────────────────

	$('#pzv-save-settings').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		var $msg  = $('#pzv-settings-msg');
		$btn.prop('disabled', true); $spin.addClass('is-active');
		$msg.text('').removeClass('success error');

		post('pzv_mig_save_settings', {
			source_url:   $('#pzv-source-url').val(),
			username:     $('#pzv-username').val(),
			app_password: $('#pzv-app-password').val(),
		}, function (res) {
			$btn.prop('disabled', false); $spin.removeClass('is-active');
			if (res && res.success) { $msg.addClass('success').text('Kaydedildi.'); }
			else { $msg.addClass('error').text((res && res.data && res.data.message) || 'Kayıt başarısız.'); }
		});
	});

	$('#pzv-test-connection').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		var $out  = $('#pzv-test-result');
		$btn.prop('disabled', true).text('Test ediliyor…'); $spin.addClass('is-active');
		$out.hide().empty();

		// Save first then test
		$.post(pzvMig.ajaxUrl, {
			action: 'pzv_mig_save_settings', nonce: pzvMig.nonce,
			source_url:   $('#pzv-source-url').val(),
			username:     $('#pzv-username').val(),
			app_password: $('#pzv-app-password').val(),
		}).done(function () {
			post('pzv_mig_test_connection', {}, function (res) {
				$btn.prop('disabled', false).text('🔍 Bağlantıyı Test Et'); $spin.removeClass('is-active');
				var ok  = res && res.success;
				var msg = (res && res.data && res.data.message) || (ok ? 'Başarılı.' : 'Hata.');
				var bg  = ok ? '#d4edda' : '#f8d7da';
				var bdr = ok ? '#c3e6cb' : '#f5c6cb';
				var col = ok ? '#155724' : '#721c24';
				$out.show().html(
					'<div style="background:' + bg + ';border:1px solid ' + bdr + ';border-radius:4px;padding:12px 16px;margin-top:10px;color:' + col + '">' +
					(ok ? '✓ ' : '✗ ') + escHtml(msg) + '</div>'
				);
			});
		});
	});

	// ── Reset ────────────────────────────────────────────────────────────────

	$('#pzv-reset-all').on('click', function () {
		if (!confirm(pzvMig.confirmReset)) return;
		post('pzv_mig_reset', {}, function (res) {
			if (res && res.success) window.location.reload();
		});
	});

	// ── Member import ────────────────────────────────────────────────────────

	var memberRunning = false;

	$('#pzv-start-members').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		$btn.prop('disabled', true); $spin.addClass('is-active');
		$('#pzv-member-progress').show();

		post('pzv_mig_start_member_import', {}, function (res) {
			if (res && res.success) {
				updateMemberProgress({ status: 'running', total: res.data.total, processed: 0, percent: 0, created: 0, updated: 0, errors: [] });
				memberRunning = false;
				runMemberBatch();
			} else {
				alert('Hata: ' + ((res && res.data && res.data.message) || '?'));
				$btn.prop('disabled', false); $spin.removeClass('is-active');
			}
		});
	});

	function runMemberBatch() {
		if (memberRunning) return;
		memberRunning = true;
		$.ajax({
			url: pzvMig.ajaxUrl,
			method: 'POST',
			data: { action: 'pzv_mig_member_batch', nonce: pzvMig.nonce },
			timeout: 150000,
		})
		.done(function (res) {
			memberRunning = false;
			if (!res || !res.success) { setTimeout(runMemberBatch, 4000); return; }
			var d = res.data;
			updateMemberProgress(d);
			if (d.status === 'running') {
				runMemberBatch();
			} else {
				finishMemberImport(d);
			}
		})
		.fail(function () { memberRunning = false; setTimeout(runMemberBatch, 5000); });
	}

	function updateMemberProgress(d) {
		$('#pzv-member-progress .pzv-progress-inner').css('width', (d.percent || 0) + '%');
		$('#pzv-member-progress .pzv-progress-text').text(d.processed + ' / ' + d.total + ' (' + (d.percent || 0) + '%)');
		$('#pzv-member-progress .pzv-progress-status')
			.removeClass('status-done')
			.text(d.status === 'completed' ? 'Tamamlandı!' : (d.status === 'running' ? 'Aktarılıyor…' : ''));
		if (d.status === 'completed') $('#pzv-member-progress .pzv-progress-status').addClass('status-done');

		$('#pzv-member-results').html(
			resultItem(d.created, 'Oluşturuldu', 'created') +
			resultItem(d.updated, 'Güncellendi', 'updated') +
			resultItem((d.errors || []).length, 'Hata', 'errors')
		);
	}

	function finishMemberImport(d) {
		$('#pzv-start-members').prop('disabled', false).text('↺ Yeniden Aktar');
		$('.pzv-mig-card').find('.spinner').removeClass('is-active');
		if (d.errors && d.errors.length) {
			var html = '<div class="pzv-errors"><strong>Hatalar:</strong><ul>';
			for (var i = 0; i < Math.min(d.errors.length, 20); i++) html += '<li>' + escHtml(d.errors[i]) + '</li>';
			html += '</ul></div>';
			$('#pzv-member-progress').append(html);
		}
	}

	// ── Vendor import ────────────────────────────────────────────────────────

	var vendorRunning = false;

	$('#pzv-start-vendors').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		$btn.prop('disabled', true); $spin.addClass('is-active');
		$('#pzv-vendor-progress').show();

		post('pzv_mig_start_vendor_import', {}, function (res) {
			if (res && res.success) {
				updateVendorProgress({ status: 'running', total: res.data.total, processed: 0, percent: 0, created: 0, updated: 0, errors: [] });
				vendorRunning = false;
				runVendorBatch();
			} else {
				alert('Hata: ' + ((res && res.data && res.data.message) || '?'));
				$btn.prop('disabled', false); $spin.removeClass('is-active');
			}
		});
	});

	function runVendorBatch() {
		if (vendorRunning) return;
		vendorRunning = true;
		$.ajax({
			url: pzvMig.ajaxUrl,
			method: 'POST',
			data: { action: 'pzv_mig_vendor_batch', nonce: pzvMig.nonce },
			timeout: 150000,
		})
		.done(function (res) {
			vendorRunning = false;
			if (!res || !res.success) { setTimeout(runVendorBatch, 4000); return; }
			var d = res.data;
			updateVendorProgress(d);
			if (d.status === 'running') {
				runVendorBatch();
			} else {
				finishVendorImport(d);
			}
		})
		.fail(function () { vendorRunning = false; setTimeout(runVendorBatch, 5000); });
	}

	function updateVendorProgress(d) {
		$('#pzv-vendor-progress .pzv-progress-inner').css('width', (d.percent || 0) + '%');
		$('#pzv-vendor-progress .pzv-progress-text').text(d.processed + ' / ' + d.total + ' (' + (d.percent || 0) + '%)');
		$('#pzv-vendor-progress .pzv-progress-status')
			.removeClass('status-done')
			.text(d.status === 'completed' ? 'Tamamlandı!' : (d.status === 'running' ? 'Aktarılıyor…' : ''));
		if (d.status === 'completed') $('#pzv-vendor-progress .pzv-progress-status').addClass('status-done');

		$('#pzv-vendor-results').html(
			resultItem(d.created, 'Oluşturuldu', 'created') +
			resultItem(d.updated, 'Güncellendi', 'updated') +
			resultItem((d.errors || []).length, 'Hata', 'errors')
		);
	}

	function finishVendorImport(d) {
		$('#pzv-start-vendors').prop('disabled', false).text('↺ Yeniden Aktar');
		$('.pzv-mig-card').find('.spinner').removeClass('is-active');
		if (d.errors && d.errors.length) {
			var html = '<div class="pzv-errors"><strong>Hatalar:</strong><ul>';
			for (var i = 0; i < Math.min(d.errors.length, 20); i++) html += '<li>' + escHtml(d.errors[i]) + '</li>';
			html += '</ul></div>';
			$('#pzv-vendor-progress').append(html);
		}
	}

	// ── Product link ─────────────────────────────────────────────────────────

	var linkRunning = false;

	$('#pzv-start-link').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		$btn.prop('disabled', true); $spin.addClass('is-active');
		$('#pzv-link-progress').show();

		post('pzv_mig_start_link', {}, function (res) {
			if (res && res.success) {
				updateLinkProgress({ status: 'running', vendor_index: 0, vendor_total: res.data.vendor_count, percent: 0, linked: 0, missing: 0, errors: [] });
				linkRunning = false;
				runLinkBatch();
			} else {
				alert('Hata: ' + ((res && res.data && res.data.message) || '?'));
				$btn.prop('disabled', false); $spin.removeClass('is-active');
			}
		});
	});

	function runLinkBatch() {
		if (linkRunning) return;
		linkRunning = true;
		$.ajax({
			url: pzvMig.ajaxUrl,
			method: 'POST',
			data: { action: 'pzv_mig_link_batch', nonce: pzvMig.nonce },
			timeout: 150000,
		})
		.done(function (res) {
			linkRunning = false;
			if (!res || !res.success) { setTimeout(runLinkBatch, 4000); return; }
			var d = res.data;
			updateLinkProgress(d);
			if (d.status === 'running') runLinkBatch();
			else finishLink(d);
		})
		.fail(function () { linkRunning = false; setTimeout(runLinkBatch, 5000); });
	}

	function updateLinkProgress(d) {
		$('#pzv-link-progress .pzv-progress-inner').css('width', (d.percent || 0) + '%');
		$('#pzv-link-progress .pzv-progress-text').text('Satıcı ' + d.vendor_index + ' / ' + d.vendor_total);
		$('#pzv-link-progress .pzv-progress-status')
			.removeClass('status-done')
			.text(d.status === 'completed' ? 'Tamamlandı!' : (d.status === 'running' ? 'Bağlanıyor…' : ''));
		if (d.status === 'completed') $('#pzv-link-progress .pzv-progress-status').addClass('status-done');

		$('#pzv-link-results').html(
			resultItem(d.linked,  'Bağlandı',    'created') +
			resultItem(d.missing, 'Bulunamadı',  'skipped') +
			resultItem((d.errors || []).length, 'Hata', 'errors')
		);
	}

	function finishLink(d) {
		$('#pzv-start-link').prop('disabled', false).text('↺ Yeniden Bağla');
		$('.pzv-mig-card').find('.spinner').removeClass('is-active');
	}

	// ── Commission import ────────────────────────────────────────────────────

	var commRunning = false;

	$('#pzv-start-comm').on('click', function () {
		var $btn  = $(this);
		var $spin = $btn.closest('.pzv-mig-card').find('.spinner');
		$btn.prop('disabled', true); $spin.addClass('is-active');
		$('#pzv-comm-progress').show();

		post('pzv_mig_start_comm_import', {}, function (res) {
			if (res && res.success) {
				commRunning = false;
				runCommBatch();
			} else {
				alert('Hata: ' + ((res && res.data && res.data.message) || '?'));
				$btn.prop('disabled', false); $spin.removeClass('is-active');
			}
		});
	});

	function runCommBatch() {
		if (commRunning) return;
		commRunning = true;
		$.ajax({
			url: pzvMig.ajaxUrl,
			method: 'POST',
			data: { action: 'pzv_mig_comm_batch', nonce: pzvMig.nonce },
			timeout: 150000,
		})
		.done(function (res) {
			commRunning = false;
			if (!res || !res.success) { setTimeout(runCommBatch, 4000); return; }
			var d = res.data;
			$('#pzv-comm-progress .pzv-progress-inner').css('width', (d.percent || 0) + '%');
			$('#pzv-comm-progress .pzv-progress-text').text(d.processed + ' / ' + d.total + ' (' + (d.percent || 0) + '%)');
			if (d.status === 'running') runCommBatch();
			else {
				$('#pzv-start-comm').prop('disabled', false).text('↺ Yeniden Aktar');
				$('.pzv-mig-card').find('.spinner').removeClass('is-active');
				$('#pzv-comm-progress .pzv-progress-status').addClass('status-done').text('Tamamlandı!');
			}
		})
		.fail(function () { commRunning = false; setTimeout(runCommBatch, 5000); });
	}

	// ── Shared helpers ───────────────────────────────────────────────────────

	function resultItem(val, lbl, cls) {
		return '<div class="pzv-result-item ' + cls + '">'
			+ '<span class="pzv-result-val">' + val + '</span>'
			+ '<span class="pzv-result-lbl">' + lbl + '</span>'
			+ '</div>';
	}

}(jQuery));
