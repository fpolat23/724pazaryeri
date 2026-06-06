(function(){
  'use strict';

  // ─── SİPARİŞ GÜNCELLE (mevcut) ───
  document.querySelectorAll('.pzv-update-order').forEach(function(btn){
    btn.addEventListener('click', function(){
      var form = btn.closest('.pzv-order-form');
      if (!form) return;
      var msg = form.querySelector('.pzv-order-msg');
      msg.className = 'pzv-order-msg';
      msg.textContent = 'Gönderiliyor...';
      var fd = new FormData();
      fd.append('action', 'pzv_update_order');
      fd.append('nonce', pzv.nonce);
      fd.append('order_id', form.getAttribute('data-order'));
      ['status','shipping_company','tracking_number','note'].forEach(function(field){
        var el = form.querySelector('[name="'+field+'"]');
        if (el) fd.append(field, el.value);
      });
      btn.disabled = true;
      fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          btn.disabled = false;
          if (res && res.success) {
            msg.className = 'pzv-order-msg success';
            msg.textContent = '✓ ' + (res.data.message || 'Güncellendi');
          } else {
            msg.className = 'pzv-order-msg error';
            msg.textContent = '✗ ' + ((res && res.data && res.data.message) || 'Hata');
          }
        })
        .catch(function(){
          btn.disabled = false;
          msg.className = 'pzv-order-msg error';
          msg.textContent = '✗ Bağlantı hatası';
        });
    });
  });

  // ─── ÜRÜN ARAMA + KLON ───
  console.log('[PZV] Dashboard JS yüklendi');
  var searchInput = document.getElementById('pzv-product-search');
  var searchBtn   = document.getElementById('pzv-search-btn');
  var resultsBox  = document.getElementById('pzv-search-results');
  var searchTimer = null;

  if (searchInput) {
    // Yazınca otomatik ara (debounce)
    searchInput.addEventListener('input', function(){
      var q = searchInput.value.trim();
      clearTimeout(searchTimer);
      if (q.length < 2) {
        if (resultsBox) resultsBox.innerHTML = '';
        setStatus('İlk 2 harften sonra otomatik arar.');
        return;
      }
      setStatus('Aranıyor...');
      searchTimer = setTimeout(function(){ doSearch(q); }, 300);
    });
    // Enter tuşunda da ara
    searchInput.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        clearTimeout(searchTimer);
        var q = searchInput.value.trim();
        if (q.length >= 1) doSearch(q);
      }
    });
  }
  // Ara butonu
  if (searchBtn) {
    searchBtn.addEventListener('click', function(){
      clearTimeout(searchTimer);
      var q = searchInput ? searchInput.value.trim() : '';
      if (q.length < 1) {
        setStatus('Lütfen aranacak kelime yazın.');
        if (searchInput) searchInput.focus();
        return;
      }
      doSearch(q);
    });
  }

  function setStatus(text){
    var s = document.querySelector('.pzv-search-status');
    if (s) s.textContent = text;
  }

  function setSpinner(on){
    var t = document.querySelector('.pzv-btn-text');
    var s = document.querySelector('.pzv-btn-spinner');
    if (t) t.style.display = on ? 'none' : '';
    if (s) s.style.display = on ? '' : 'none';
    if (searchBtn) searchBtn.disabled = !!on;
  }

  function doSearch(q){
    if (typeof pzv === 'undefined' || !pzv.ajax_url) {
      setStatus('⚠ JS yapılandırması yüklenmedi. Sayfayı yenileyin veya cache temizleyin.');
      console.error('[PZV] pzv objesi yok!');
      return;
    }
    setSpinner(true);
    setStatus('Aranıyor: "' + q + '"...');
    var fd = new FormData();
    fd.append('action', 'pzv_search_products');
    fd.append('nonce', pzv.nonce);
    fd.append('q', q);
    fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.text().then(function(txt){
          try { return JSON.parse(txt); }
          catch(e){
            console.error('Sunucu yanıtı JSON değil:', txt.substring(0, 300));
            throw new Error('Sunucu yanıtı bozuk.');
          }
        });
      })
      .then(function(res){
        setSpinner(false);
        if (!res || !res.success) {
          setStatus('✗ Hata: ' + ((res && res.data && res.data.message) || 'bilinmeyen'));
          return;
        }
        var items = res.data.results || [];
        setStatus(items.length === 0 ? 'Sonuç yok.' : '✓ ' + items.length + ' ürün bulundu');
        renderResults(items);
      })
      .catch(function(err){
        setSpinner(false);
        setStatus('✗ ' + (err.message || 'Bağlantı hatası'));
        console.error('[PZV] Arama hatası:', err);
      });
  }

  function renderResults(items){
    if (!items.length) {
      resultsBox.innerHTML = '<div class="pzv-no-result">🔍 Bu aramaya uygun ürün bulunamadı. Farklı kelimeler deneyin.</div>';
      return;
    }
    var html = '';
    items.forEach(function(item){
      var btn = item.already_added
        ? '<button class="button button-small" disabled>✓ Eklenmiş</button>'
        : '<button class="button button-primary button-small pzv-clone-btn" data-id="'+item.id+'" data-name="'+escapeAttr(item.name)+'" data-image="'+escapeAttr(item.image)+'" data-price="'+escapeAttr(item.price)+'" data-seller="'+escapeAttr(item.seller)+'">📥 Mağazama Ekle</button>';
      html += '<div class="pzv-search-item">' +
        '<img src="'+escapeAttr(item.image)+'" alt="">' +
        '<div class="pzv-search-info">' +
          '<div class="pzv-search-name">'+escapeHTML(item.name)+'</div>' +
          '<div class="pzv-search-meta">SKU: '+escapeHTML(item.sku)+' · Satıcı: <strong>'+escapeHTML(item.seller)+'</strong> · Fiyat: <strong>'+escapeHTML(item.price_html)+'</strong></div>' +
        '</div>' +
        '<div class="pzv-search-actions">'+btn+' <a class="button button-small" href="'+escapeAttr(item.permalink)+'" target="_blank">👁</a></div>' +
      '</div>';
    });
    resultsBox.innerHTML = html;
    // Klon butonu bind
    resultsBox.querySelectorAll('.pzv-clone-btn').forEach(function(b){
      b.addEventListener('click', function(){ openCloneModal(b); });
    });
  }

  function openCloneModal(btn){
    var modal = document.getElementById('pzv-clone-modal');
    if (!modal) return;
    var data = {
      id: btn.getAttribute('data-id'),
      name: btn.getAttribute('data-name'),
      image: btn.getAttribute('data-image'),
      price: btn.getAttribute('data-price'),
      seller: btn.getAttribute('data-seller')
    };
    modal.setAttribute('data-source', data.id);
    var info = modal.querySelector('.pzv-modal-product');
    if (info) info.innerHTML = '<div class="pzv-modal-product-card">' +
      '<img src="'+escapeAttr(data.image)+'" alt="">' +
      '<div><div class="pzv-mp-name">'+escapeHTML(data.name)+'</div>' +
      '<div class="pzv-mp-meta">Mevcut satıcı fiyatı: <strong>'+escapeHTML(data.price)+' ₺</strong> ('+escapeHTML(data.seller)+')</div></div>' +
      '</div>';
    // Default değerler
    document.getElementById('pzv-clone-price').value = data.price;
    document.getElementById('pzv-clone-stock').value = '';
    document.getElementById('pzv-clone-sku').value = '';
    document.getElementById('pzv-clone-status').value = 'pending';
    modal.querySelector('.pzv-modal-msg').textContent = '';
    modal.style.display = 'flex';
  }

  // Modal kapat
  document.querySelectorAll('.pzv-modal-close, .pzv-modal-cancel').forEach(function(el){
    el.addEventListener('click', function(){
      var modal = el.closest('.pzv-modal');
      if (modal) modal.style.display = 'none';
    });
  });
  // Modal dışı tıklamada kapat
  document.querySelectorAll('.pzv-modal').forEach(function(modal){
    modal.addEventListener('click', function(e){
      if (e.target === modal) modal.style.display = 'none';
    });
  });

  // Klon onayla
  var cloneBtn = document.querySelector('.pzv-modal-clone');
  if (cloneBtn) {
    cloneBtn.addEventListener('click', function(){
      var modal = document.getElementById('pzv-clone-modal');
      var sourceId = modal.getAttribute('data-source');
      var price = document.getElementById('pzv-clone-price').value;
      var stock = document.getElementById('pzv-clone-stock').value;
      var sku   = document.getElementById('pzv-clone-sku').value;
      var status= document.getElementById('pzv-clone-status').value;
      var msg = modal.querySelector('.pzv-modal-msg');
      msg.className = 'pzv-modal-msg';
      if (!price || price <= 0) { msg.className='pzv-modal-msg error'; msg.textContent = 'Fiyat girin.'; return; }
      if (stock === '' || stock < 0) { msg.className='pzv-modal-msg error'; msg.textContent = 'Stok adedi girin (0 olabilir).'; return; }

      cloneBtn.disabled = true;
      cloneBtn.textContent = '⏳ Ekleniyor...';
      msg.textContent = '';

      var fd = new FormData();
      fd.append('action', 'pzv_clone_product');
      fd.append('nonce', pzv.nonce);
      fd.append('source_id', sourceId);
      fd.append('price', price);
      fd.append('stock', stock);
      fd.append('sku', sku);
      fd.append('status', status);

      fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          cloneBtn.disabled = false;
          cloneBtn.textContent = '📥 Mağazama Ekle';
          if (res && res.success) {
            msg.className = 'pzv-modal-msg success';
            var cloneIcon = res.data.went_pending ? '⏳' : '✓';
            msg.innerHTML = cloneIcon + ' ' + res.data.message + '<br><br><a class="button button-primary" href="'+res.data.edit_url+'">Ürünlerim sayfasına git →</a>';
            // Arama sonucundaki butonu güncelle
            var oldBtn = document.querySelector('.pzv-clone-btn[data-id="'+sourceId+'"]');
            if (oldBtn) oldBtn.outerHTML = '<button class="button button-small" disabled>✓ Eklenmiş</button>';
          } else {
            msg.className = 'pzv-modal-msg error';
            msg.textContent = '✗ ' + ((res && res.data && res.data.message) || 'Bilinmeyen hata');
          }
        })
        .catch(function(){
          cloneBtn.disabled = false;
          cloneBtn.textContent = '📥 Mağazama Ekle';
          msg.className = 'pzv-modal-msg error';
          msg.textContent = '✗ Bağlantı hatası';
        });
    });
  }

  // ─── ÜRÜNLERİM HIZLI DÜZENLE ───
  document.querySelectorAll('.pzv-quick-save').forEach(function(btn){
    btn.addEventListener('click', function(){
      var row = btn.closest('tr');
      if (!row) return;
      var pid = row.getAttribute('data-product');
      var price = row.querySelector('.pzv-quick-price').value;
      var stock = row.querySelector('.pzv-quick-stock').value;
      var status = row.querySelector('.pzv-quick-status').value;
      btn.disabled = true;
      btn.textContent = '⏳';
      var fd = new FormData();
      fd.append('action', 'pzv_update_my_product');
      fd.append('nonce', pzv.nonce);
      fd.append('product_id', pid);
      fd.append('price', price);
      fd.append('stock', stock);
      fd.append('status', status);
      fetch(pzv.ajax_url, { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          btn.disabled = false;
          if (res && res.success) {
            if (res.data && res.data.went_pending) {
              btn.textContent = '⏳';
              btn.style.background = '#d97706';
              // Status select'i güncelle
              var statusSel = row.querySelector('.pzv-quick-status');
              if (statusSel) statusSel.value = 'pending';
              setTimeout(function(){ btn.textContent = '💾'; btn.style.background = ''; }, 2500);
            } else {
              btn.textContent = '✓';
              btn.style.background = '#1a7a4a';
              setTimeout(function(){ btn.textContent = '💾'; btn.style.background = ''; }, 1500);
            }
          } else {
            btn.textContent = '💾';
            alert((res && res.data && res.data.message) || 'Hata');
          }
        })
        .catch(function(){
          btn.disabled = false;
          btn.textContent = '💾';
          alert('Bağlantı hatası');
        });
    });
  });

  // Helper'lar
  function escapeHTML(s){
    return (s + '').replace(/[&<>"']/g, function(c){
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
  }
  function escapeAttr(s){ return escapeHTML(s); }
})();

/* ═══ PZV: Ürün Formu + Profil ═══ */
(function(){
  'use strict';

  // ── Medya seçici yardımcısı ──
  function pzvMedia(btnId, removeId, inputId, previewId, phId, title){
    var btn = document.getElementById(btnId);
    var rem = document.getElementById(removeId);
    var inp = document.getElementById(inputId);
    var prv = document.getElementById(previewId);
    var ph  = document.getElementById(phId);
    if(!btn) return;
    var frame;
    btn.addEventListener('click', function(){
      if(frame){frame.open();return;}
      frame = wp.media({title:title||'Görsel Seç',button:{text:'Seç'},multiple:false});
      frame.on('select',function(){
        var a = frame.state().get('selection').first().toJSON();
        if(inp) inp.value = a.id;
        if(prv){ prv.src = a.url; prv.style.display='block'; }
        if(ph)  ph.style.display='none';
        if(rem) rem.style.display='';
      });
      frame.open();
    });
    if(rem) rem.addEventListener('click',function(){
      if(inp) inp.value='0';
      if(prv){ prv.src=''; prv.style.display='none'; }
      if(ph)  ph.style.display='';
      rem.style.display='none';
    });
  }

  // ── Genel form gönderici ──
  function pzvSubmitForm(opts){
    var btn = document.getElementById(opts.submitId);
    var msg = document.getElementById(opts.msgId);
    if(!btn) return;
    btn.addEventListener('click',function(){
      var txt  = btn.querySelector('.pzv-btn-txt');
      var spin = btn.querySelector('.pzv-btn-spin');
      var fd = new FormData();
      fd.append('nonce', pzv.nonce);
      fd.append('action', opts.action);
      opts.fields.forEach(function(f){
        var el = document.getElementById(f.id);
        if(el) fd.append(f.name, el.value||'');
      });
      if(opts.extra) opts.extra(fd);
      // Validasyon
      if(opts.validate){
        var err = opts.validate();
        if(err){ msg.className='pzv-form-msg error'; msg.textContent='✗ '+err; return; }
      }
      btn.disabled=true;
      if(txt) txt.style.display='none';
      if(spin) spin.style.display='';
      msg.className='pzv-form-msg'; msg.textContent='';
      fetch(pzv.ajax_url,{method:'POST',body:fd,credentials:'same-origin'})
        .then(function(r){return r.json();})
        .then(function(res){
          btn.disabled=false;
          if(txt) txt.style.display='';
          if(spin) spin.style.display='none';
          if(res&&res.success){
            msg.className='pzv-form-msg success';
            msg.textContent='✓ '+(res.data&&res.data.message||'Kaydedildi');
            if(opts.onSuccess) opts.onSuccess(res.data);
          } else {
            msg.className='pzv-form-msg error';
            msg.textContent='✗ '+((res&&res.data&&res.data.message)||'Bir hata oluştu');
          }
        })
        .catch(function(){
          btn.disabled=false;
          if(txt) txt.style.display='';
          if(spin) spin.style.display='none';
          msg.className='pzv-form-msg error';
          msg.textContent='✗ Bağlantı hatası';
        });
    });
  }

  // ── YENİ ÜRÜN formu ──
  pzvMedia('pzv-np-img-btn','pzv-np-img-remove','pzv-np-img-id','pzv-np-img-preview','pzv-np-placeholder','Ürün Görseli Seç');
  pzvSubmitForm({
    submitId: 'pzv-np-submit',
    msgId:    'pzv-np-msg',
    action:   'pzv_new_product',
    fields: [
      {id:'pzv-np-title',      name:'title'},
      {id:'pzv-np-cat',        name:'cat_id'},
      {id:'pzv-np-price',      name:'price'},
      {id:'pzv-np-sale-price', name:'sale_price'},
      {id:'pzv-np-stock',      name:'stock'},
      {id:'pzv-np-sku',        name:'sku'},
      {id:'pzv-np-short-desc', name:'short_desc'},
      {id:'pzv-np-desc',       name:'desc'},
      {id:'pzv-np-status',     name:'status'},
      {id:'pzv-np-img-id',     name:'image_id'},
    ],
    validate: function(){
      var t = document.getElementById('pzv-np-title');
      var p = document.getElementById('pzv-np-price');
      if(!t||!t.value.trim()) return 'Ürün adı gerekli.';
      if(!p||parseFloat(p.value)<=0) return 'Geçerli bir fiyat girin.';
      return null;
    },
    onSuccess: function(data){
      if(data&&data.went_pending){
        setTimeout(function(){
          var m = document.getElementById('pzv-np-msg');
          if(m) m.innerHTML += ' &mdash; <a href="'+data.products_url+'">Ürünlerime git &rarr;</a>';
        },200);
      }
    }
  });

  // ── ÜRÜN DÜZENLE formu ──
  pzvMedia('pzv-ep-img-btn','pzv-ep-img-remove','pzv-ep-img-id','pzv-ep-img-preview','pzv-ep-placeholder','Ürün Görseli Seç');
  var epForm = document.getElementById('pzv-edit-product-form');
  if(epForm){
    var pid = epForm.getAttribute('data-product');
    pzvSubmitForm({
      submitId: 'pzv-ep-submit',
      msgId:    'pzv-ep-msg',
      action:   'pzv_save_product_data',
      fields: [
        {id:'pzv-ep-title',      name:'title'},
        {id:'pzv-ep-cat',        name:'cat_id'},
        {id:'pzv-ep-price',      name:'price'},
        {id:'pzv-ep-sale-price', name:'sale_price'},
        {id:'pzv-ep-stock',      name:'stock'},
        {id:'pzv-ep-sku',        name:'sku'},
        {id:'pzv-ep-short-desc', name:'short_desc'},
        {id:'pzv-ep-desc',       name:'desc'},
        {id:'pzv-ep-status',     name:'status'},
        {id:'pzv-ep-img-id',     name:'image_id'},
      ],
      extra: function(fd){ fd.append('product_id', pid); },
      validate: function(){
        var t = document.getElementById('pzv-ep-title');
        if(!t||!t.value.trim()) return 'Ürün adı gerekli.';
        return null;
      }
    });
  }

  // ── PROFİL formu ──
  pzvMedia('pzv-prf-logo-btn',  'pzv-prf-logo-rm',   'pzv-prf-logo-id',   'pzv-prf-logo-img',   'pzv-prf-logo-ph',   'Mağaza Logosu Seç');
  pzvMedia('pzv-prf-banner-btn','pzv-prf-banner-rm',  'pzv-prf-banner-id', 'pzv-prf-banner-img', 'pzv-prf-banner-ph', 'Mağaza Bannerı Seç');
  pzvSubmitForm({
    submitId: 'pzv-prf-save',
    msgId:    'pzv-prf-msg',
    action:   'pzv_save_vendor_profile',
    fields: [
      {id:'pzv-prf-name',    name:'store_name'},
      {id:'pzv-prf-slug',    name:'store_slug'},
      {id:'pzv-prf-phone',   name:'phone'},
      {id:'pzv-prf-city',    name:'city'},
      {id:'pzv-prf-address', name:'address'},
      {id:'pzv-prf-desc',    name:'description'},
      {id:'pzv-prf-iban',    name:'iban'},
      {id:'pzv-prf-logo-id',         name:'logo'},
      {id:'pzv-prf-banner-id',       name:'banner'},
      {id:'pzv-prf-dispatch',        name:'dispatch_days'},
      {id:'pzv-prf-tc-tax',          name:'tc_or_tax'},
      {id:'pzv-prf-instagram',       name:'instagram_url'},
      {id:'pzv-prf-twitter',         name:'twitter_url'},
      {id:'pzv-prf-working-hours',   name:'working_hours'},
      {id:'pzv-prf-return-policy',   name:'return_policy'},
      {id:'pzv-prf-nakit-rate',      name:'nakit_rate'},
    ]
  });

})();
