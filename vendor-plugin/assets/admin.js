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
})();
