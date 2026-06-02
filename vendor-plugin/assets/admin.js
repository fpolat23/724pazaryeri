(function(){
  'use strict';
  console.log('[PZV] Admin JS yüklendi, sürüm 1.0.2');
  // Komisyon override kaydet
  document.querySelectorAll('.pzv-commission-input').forEach(function(input){
    input.addEventListener('change', function(){
      var vendor = input.getAttribute('data-vendor');
      var rate = input.value.trim();
      var fd = new FormData();
      fd.append('action', 'pzv_save_commission');
      fd.append('nonce', pzv.nonce);
      fd.append('vendor_id', vendor);
      fd.append('rate', rate);
      input.style.background = '#fff7ed';
      fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          input.style.background = res.success ? '#dcfce7' : '#fee2e2';
          setTimeout(function(){ input.style.background = ''; }, 1200);
        });
    });
  });

  // Ödendi işaretle
  document.querySelectorAll('.pzv-mark-paid').forEach(function(btn){
    btn.addEventListener('click', function(){
      var vendor = btn.getAttribute('data-vendor');
      var note = prompt('Ödeme notu (opsiyonel):', 'Banka havalesi - ' + new Date().toLocaleDateString('tr-TR'));
      if (note === null) return;
      if (!confirm('Bu satıcı için "Ödendi" işaretlensin mi? Bekleyen tüm komisyon kayıtları kapatılacak.')) return;
      var fd = new FormData();
      fd.append('action', 'pzv_mark_paid');
      fd.append('nonce', pzv.nonce);
      fd.append('vendor_id', vendor);
      fd.append('note', note);
      btn.disabled = true;
      btn.textContent = 'Kaydediliyor...';
      fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res && res.success) {
            btn.textContent = '✓ Ödendi';
            btn.style.background = '#1a7a4a';
            setTimeout(function(){ btn.closest('tr').remove(); }, 800);
          } else {
            btn.disabled = false;
            btn.textContent = '✓ Ödendi İşaretle';
            alert('Hata oluştu.');
          }
        });
    });
  });

  // Başvuru → satıcı yap (CPT listesinde)
  document.addEventListener('click', function(e){
    var link = e.target.closest('.pzv-approve-vendor');
    if (!link) return;
    console.log('[PZV] Satıcı Yap tıklandı, apply ID:', link.getAttribute('data-apply'));
    e.preventDefault();
    var applyId = link.getAttribute('data-apply');
    if (!confirm('Bu başvuruyu onaylayıp satıcı hesabı oluşturulsun mu? Kullanıcıya e-posta ile giriş bilgileri gönderilecek.')) return;
    link.textContent = 'İşleniyor...';
    var fd = new FormData();
    fd.append('action', 'pzv_approve_vendor');
    fd.append('nonce', pzv.nonce);
    fd.append('apply_id', applyId);
    fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res.success) {
          alert('✓ Satıcı oluşturuldu (ID: ' + res.data.vendor_id + ')');
          window.location.reload();
        } else {
          alert('Hata: ' + ((res && res.data && res.data.message) || 'bilinmeyen'));
          link.textContent = '🚀 Satıcı Yap';
        }
      });
  });

  // Satıcı aktif ↔ pasif toggle
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.pzv-toggle-vendor-status');
    if (!btn) return;
    e.preventDefault();
    var vid    = btn.getAttribute('data-vendor');
    var active = btn.getAttribute('data-active') === '1';
    var label  = btn.closest('tr') ? btn.closest('tr').querySelector('td:nth-child(2) strong') : null;
    var name   = label ? label.textContent : 'bu satıcı';
    var msg    = active
      ? ('"' + name + '" pasife alınmak üzere. Mağazası ve ürünleri sitede gizlenecek. Devam?')
      : ('"' + name + '" aktif edilmek üzere. Mağazası ve ürünleri tekrar görünür olacak. Devam?');
    if (!confirm(msg)) return;
    btn.disabled = true;
    btn.textContent = '⏳';
    var fd = new FormData();
    fd.append('action',    'pzv_toggle_vendor_status');
    fd.append('nonce',     pzv.nonce);
    fd.append('vendor_id', vid);
    fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res.success) {
          var nowActive = res.data.active;
          btn.setAttribute('data-active', nowActive ? '1' : '0');
          btn.textContent = nowActive ? '● Aktif' : '○ Pasif';
          btn.style.color       = nowActive ? '#1a7a4a' : '#dc2626';
          btn.style.borderColor = nowActive ? '#a7f3d0' : '#fca5a5';
          btn.disabled = false;
          var row = btn.closest('tr');
          if (row) row.style.opacity = nowActive ? '' : '0.6';
        } else {
          btn.disabled = false;
          btn.textContent = active ? '● Aktif' : '○ Pasif';
          alert('Hata oluştu.');
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = active ? '● Aktif' : '○ Pasif';
        alert('Bağlantı hatası.');
      });
  });

  // Satıcı sil
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.pzv-delete-vendor');
    if (!btn) return;
    e.preventDefault();
    var vid  = btn.getAttribute('data-vendor');
    var name = btn.getAttribute('data-name') || 'bu satıcı';
    if (!confirm('"' + name + '" satıcısını kaldırmak istediğinizden emin misiniz?\n\n• Satıcı rolü kaldırılır (kullanıcı hesabı korunur)\n• Tüm mağaza bilgileri silinir\n• Ürünler taslağa alınır')) return;
    if (!confirm('Son onay: "' + name + '" satıcı listesinden kaldırılsın mı?')) return;
    btn.disabled = true;
    btn.textContent = '⏳';
    var fd = new FormData();
    fd.append('action',    'pzv_delete_vendor');
    fd.append('nonce',     pzv.nonce);
    fd.append('vendor_id', vid);
    fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res.success) {
          var row = document.getElementById('pzv-vendor-row-' + vid);
          if (row) {
            row.style.background  = '#fee2e2';
            row.style.transition  = 'background .3s';
            setTimeout(function(){ row.remove(); }, 700);
          }
          alert('✓ ' + res.data.message);
        } else {
          btn.disabled = false;
          btn.textContent = 'Sil';
          alert('Hata: ' + ((res && res.data && res.data.message) || 'bilinmeyen'));
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = 'Sil';
        alert('Bağlantı hatası.');
      });
  });

  // Bekleyen ürün onayla / reddet
  document.addEventListener('click', function(e){
    var btn = e.target.closest('.pzv-approve-product');
    if (!btn) return;
    e.preventDefault();
    var pid    = btn.getAttribute('data-product');
    var action = btn.getAttribute('data-action');
    var note   = '';
    if (action === 'approve') {
      if (!confirm('Bu ürün onaylanıp yayınlansın mı? Satıcıya bilgi e-postası gönderilecek.')) return;
    } else {
      note = prompt('Reddetme nedeni (satıcıya e-posta ile iletilecek, boş bırakılabilir):', '') || '';
      if (note === null) return;
      if (!confirm('Bu ürün reddedilip taslağa alınsın mı?')) return;
    }
    btn.disabled = true;
    btn.textContent = 'İşleniyor...';
    var fd = new FormData();
    fd.append('action',         'pzv_approve_product');
    fd.append('nonce',          pzv.nonce);
    fd.append('product_id',     pid);
    fd.append('approve_action', action);
    fd.append('note',           note);
    fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){ return r.json(); })
      .then(function(res){
        if (res && res.success) {
          var row = document.getElementById('pzv-pending-row-' + pid);
          if (row) {
            row.style.background = action === 'approve' ? '#dcfce7' : '#fee2e2';
            row.style.transition = 'background .3s';
            setTimeout(function(){ row.remove(); }, 800);
          }
        } else {
          btn.disabled = false;
          btn.textContent = action === 'approve' ? '✓ Onayla' : '✗ Reddet';
          alert('Hata: ' + ((res && res.data && res.data.message) || 'bilinmeyen'));
        }
      })
      .catch(function(){
        btn.disabled = false;
        btn.textContent = action === 'approve' ? '✓ Onayla' : '✗ Reddet';
        alert('Bağlantı hatası');
      });
  });
})();
