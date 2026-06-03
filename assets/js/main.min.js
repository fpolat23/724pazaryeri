
/* (eski statik mega menü kaldırıldı — artık PHP dinamik + megaShow) */

/* ── OPEN/CLOSE STATE ── */
let openId = null;

function closeAll() {
  var _ov = document.getElementById('overlay');
  var _ds = document.getElementById('drop-search');
  var _mp = document.getElementById('mega-panel');
  if(openId) {
    var el = document.getElementById(openId);
    if(el) el.classList.remove('open','show');
    document.querySelectorAll('.nav-btn.open,.all-cats-btn.open,.ci.open').forEach(function(x){ x.classList.remove('open'); });
    if(_ov) _ov.classList.remove('show');
    openId = null;
  }
  if(_ds) _ds.classList.remove('open');
  document.querySelectorAll('.ci-drop.open,.nav-drop.open').forEach(function(x){ x.classList.remove('open'); });
  if(_mp) _mp.classList.remove('open');
  if(_ov) _ov.classList.remove('show');
}

function open(dropId, btnId) {
  closeAll();
  const drop = document.getElementById(dropId);
  if(drop) drop.classList.add('open');
  if(btnId) { var btn = document.getElementById(btnId); if(btn) btn.classList.add('open'); }
  var _ov2 = document.getElementById('overlay'); if(_ov2) _ov2.classList.add('show');
  openId = dropId;
}

function toggleDrop(dropId, btnId) {
  const drop = document.getElementById(dropId);
  if(drop && drop.classList.contains('open')) { closeAll(); return; }
  open(dropId, btnId);
}

function toggleMega() {
  const mp = document.getElementById('mega-panel');
  const ab = document.getElementById('btn-allcats');
  if(mp.classList.contains('open')) { closeAll(); return; }
  closeAll();
  mp.classList.add('open');
  ab.classList.add('open');
  document.getElementById('overlay').classList.add('show');
}

function toggleCiDrop(dropId, ciId) {
  const drop = document.getElementById(dropId);
  const ci = document.getElementById(ciId);
  if(drop && drop.classList.contains('open')) { closeAll(); return; }
  closeAll();
  if(drop) drop.classList.add('open');
  if(ci) ci.classList.add('open');
  document.getElementById('overlay').classList.add('show');
}

/* search focus — pz-ai-input uses live search; skip old static dropdown */
var _si=document.getElementById('search-input');
if(_si && !_si.classList.contains('pz-ai-input')) {
  _si.addEventListener('focus', function(e) {
    e.stopPropagation();
    closeAll();
    document.getElementById('drop-search').classList.add('open');
    document.getElementById('overlay').classList.add('show');
  });
}
/* search-go button: navigate to search results page */
var _sgBtn = document.querySelector('.search-go');
if(_sgBtn) {
  _sgBtn.addEventListener('click', function(e){
    e.preventDefault();
    var inp = document.getElementById('search-input');
    var val = inp ? inp.value.trim() : '';
    if(val) window.location.href = (window.pzHomeUrl||'/') + '?s=' + encodeURIComponent(val) + '&post_type=product';
  });
}

/* stop propagation inside panels */
document.querySelectorAll('.nav-drop,.mega-panel,.ci-drop,.search-drop').forEach(el => {
  el.addEventListener('click', e => e.stopPropagation());
});
var _cb=document.querySelector('.catbar'); if(_cb) _cb.addEventListener('click', function(e){ e.stopPropagation(); });
var _hd=document.querySelector('.header'); if(_hd) _hd.addEventListener('click', function(e){ e.stopPropagation(); });
document.body.addEventListener('click', closeAll);
document.addEventListener('keydown', e => { if(e.key==='Escape') closeAll(); });

/* ── TIMER ── */
let ts = 3*3600+47*60+22;
setInterval(() => {
  ts = Math.max(0, ts-1);
  (document.getElementById('dh')||{}).textContent = String(Math.floor(ts/3600)).padStart(2,'0');
  (document.getElementById('dm')||{}).textContent = String(Math.floor((ts%3600)/60)).padStart(2,'0');
  (document.getElementById('ds')||{}).textContent = String(ts%60).padStart(2,'0');
}, 1000);

/* ── INTERACTIONS ── */
function toggleWish(el) {
  var card=el.closest('.pcard');
  if(!card){el.textContent=el.textContent==='🤍'?'❤️':'🤍';el.classList.toggle('on');return;}
  var data={};try{data=JSON.parse(card.getAttribute('data-pzcomp')||'{}');}catch(e){}
  if(!data.id){el.textContent=el.textContent==='🤍'?'❤️':'🤍';el.classList.toggle('on');return;}
  var FAV_KEY='pzFavs';
  var favs=[];try{favs=JSON.parse(localStorage.getItem(FAV_KEY)||'[]');}catch(e){}
  var idx=-1;for(var i=0;i<favs.length;i++){if(String(favs[i].id)===String(data.id)){idx=i;break;}}
  if(idx>-1){favs.splice(idx,1);el.textContent='🤍';el.classList.remove('on');}
  else{favs.push({id:data.id,name:data.title||'',img:data.img||'',url:data.url||''});el.textContent='❤️';el.classList.add('on');}
  try{localStorage.setItem(FAV_KEY,JSON.stringify(favs));}catch(e){}
  var cnt=document.querySelector('.pz-fav-count');if(cnt)cnt.textContent=favs.length;
}
function addCart(btn) {
  const o = btn.textContent;
  btn.textContent = '✓';
  btn.classList.add('added');
  setTimeout(() => { btn.textContent = o; btn.classList.remove('added'); }, 1600);
}
function toggleFollow(btn) {
  btn.classList.toggle('on');
  btn.textContent = btn.classList.contains('on') ? '✓ Takip Ediliyor' : 'Takip Et';
}
document.querySelectorAll('.fp').forEach(p => {
  p.addEventListener('click', function() {
    document.querySelectorAll('.fp').forEach(x => x.classList.remove('on'));
    this.classList.add('on');
  });
});

/* ═══ HERO SLIDER ═══ */
(function(){
  function initSlider(){
    var slides=document.getElementById('slides');
    if(!slides) return false;
    if(slides._pzInit) return true;   // tekrar başlatma
    var slideEls = slides.querySelectorAll('.slide');
    var total = slideEls.length || 4;
    if(total < 2) return true;        // tek slide varsa kaydırma gereksiz
    slides._pzInit = true;

    var idx=0, timer=null, DURATION=6000;
    var dots=document.querySelectorAll('.sdot');

    function render(){
      slides.style.transform='translateX(-'+(idx*100)+'%)';
      dots.forEach(function(d,i){
        d.classList.toggle('on', i===idx);
        var bar=d.querySelector('i');
        if(!bar) return;
        bar.style.transition='none'; bar.style.width='0';
        void bar.offsetWidth;
        if(i===idx){ bar.style.transition='width '+(DURATION/1000)+'s linear'; bar.style.width='100%'; }
      });
    }
    function slideGo(i){ idx=(i+total)%total; render(); restart(); }
    function restart(){ clearInterval(timer); timer=setInterval(function(){ idx=(idx+1)%total; render(); }, DURATION); }
    window.slideGo=slideGo;
    window.slideNext=function(){ slideGo(idx+1); };
    window.slidePrev=function(){ slideGo(idx-1); };

    var sl=document.getElementById('slider');
    if(sl){
      sl.addEventListener('mouseenter', function(){ clearInterval(timer); var b=document.querySelector('.sdot.on i'); if(b){ var w=getComputedStyle(b).width; b.style.transition='none'; b.style.width=w; } });
      sl.addEventListener('mouseleave', function(){ var bar=document.querySelector('.sdot.on i'); if(bar){ bar.style.transition='width 3s linear'; bar.style.width='100%'; } restart(); });
    }
    render(); restart();

    // ── Fare ile sürükleme + dokunmatik kaydırma (swipe) ──
    var dragStartX=null, dragging=false;
    function dragStart(x){ dragStartX=x; dragging=true; clearInterval(timer); slides.style.transition='none'; }
    function dragMove(x){
      if(!dragging || dragStartX===null) return;
      var dx = x - dragStartX;
      slides.style.transform='translateX(calc(-'+(idx*100)+'% + '+dx+'px))';
    }
    function dragEnd(x){
      if(!dragging || dragStartX===null) return;
      var dx = x - dragStartX;
      dragging=false; dragStartX=null;
      slides.style.transition='transform .55s cubic-bezier(0.23,1,0.32,1)';
      if(dx < -60) slideGo(idx+1);
      else if(dx > 60) slideGo(idx-1);
      else { render(); restart(); }
    }
    // Fare
    slides.addEventListener('mousedown', function(e){ e.preventDefault(); dragStart(e.clientX); });
    window.addEventListener('mousemove', function(e){ if(dragging) dragMove(e.clientX); });
    window.addEventListener('mouseup', function(e){ if(dragging) dragEnd(e.clientX); });
    // Dokunmatik
    slides.addEventListener('touchstart', function(e){ dragStart(e.touches[0].clientX); }, {passive:true});
    slides.addEventListener('touchmove', function(e){ if(dragging) dragMove(e.touches[0].clientX); }, {passive:true});
    slides.addEventListener('touchend', function(e){ if(dragging) dragEnd(e.changedTouches[0].clientX); });
    // Sürüklerken link tıklamasını engelle
    slides.querySelectorAll('a').forEach(function(a){
      a.addEventListener('click', function(e){ if(Math.abs((dragStartX||0))>0 && !dragging){} });
    });
    slides.style.cursor='grab';

    // flash slide timer
    var t=3*3600+47*60+22;
    setInterval(function(){
      t=Math.max(0,t-1);
      var fh=document.getElementById('fh'),fm=document.getElementById('fm'),fs=document.getElementById('fs');
      if(fh){fh.textContent=String(Math.floor(t/3600)).padStart(2,'0');}
      if(fm){fm.textContent=String(Math.floor((t%3600)/60)).padStart(2,'0');}
      if(fs){fs.textContent=String(t%60).padStart(2,'0');}
    },1000);
    return true;
  }

  // Birden fazla giriş noktası — JS defer/birleştirme/geç yükleme durumlarına dayanıklı
  if(!initSlider()){
    if(document.readyState==='loading'){
      document.addEventListener('DOMContentLoaded', initSlider);
    }
    // yine de garanti için kısa gecikmeli tekrar dene
    var tries=0;
    var iv=setInterval(function(){ tries++; if(initSlider() || tries>20){ clearInterval(iv); } }, 250);
  }
})();


/* ═══ HERO SIDE CATEGORY MENU (Alibaba-style flyout) ═══ */
(function(){
  // alt kategori + marka verisi (WooCommerce slug ile)
  var VC = {
    'fly-elek':{slug:'elektronik',title:'Elektronik',cols:[
      {h:'Telefon & Tablet',l:['Akıllı Telefonlar','Tablet','Aksesuar & Kablo']},
      {h:'Bilgisayar',l:['Laptop & Bilgisayar','TV & Ses','Yazıcı & Tarayıcı']},
      {h:'Görüntü & Oyun',l:['Fotoğraf & Kamera','Oyun & Konsol','Drone']}],
      brands:['Apple','Samsung','Sony','Xiaomi','Asus']},
    'fly-moda':{slug:'moda',title:'Moda',cols:[
      {h:'Kadın',l:['Kadın Giyim','Çanta & Aksesuar','Takı & Mücevher']},
      {h:'Erkek',l:['Erkek Giyim','Ayakkabı','Saat']},
      {h:'Diğer',l:['Çocuk Giyim','İç Giyim','Güneş Gözlüğü']}],
      brands:['Nike','Adidas','Zara','H&M','Mango']},
    'fly-ev':{slug:'ev-dekor',title:'Ev & Dekor',cols:[
      {h:'Mobilya',l:['Mobilya','Aydınlatma','Halı & Kilim']},
      {h:'Dekorasyon',l:['Dekorasyon','Çerçeve & Poster','Avize & Aplik']},
      {h:'Yaşam',l:['Mutfak & Pişirme','Banyo','Bahçe']}],
      brands:['IKEA','Bellona','İstikbal','Karaca']},
    'fly-elyapimi':{slug:'el-yapimi',title:'El Yapımı',cols:[
      {h:'Sanat',l:['Yağlıboya Tablo','Seramik','Heykel']},
      {h:'Tekstil',l:['Örgü Ürünler','Nakış','Makrome']},
      {h:'Takı',l:['Gümüş Takı','Taş Takı','Ahşap Takı']}],
      brands:['Butik','Atölye','Özel Tasarım']},
    'fly-kitap':{slug:'kitap-ve-hobi',title:'Kitap & Hobi',cols:[
      {h:'Kitap',l:['Roman','Kişisel Gelişim','Çocuk']},
      {h:'Hobi',l:['Puzzle','Masa Oyunu','Koleksiyon']},
      {h:'Müzik',l:['Gitar','Piyano','Davul']}],
      brands:['İş Bankası','Yapı Kredi','Faber']},
    'fly-spor':{slug:'spor',title:'Spor',cols:[
      {h:'Fitness',l:['Halter & Dambıl','Yoga Matı','Koşu Bandı']},
      {h:'Takım',l:['Futbol','Basketbol','Tenis']},
      {h:'Outdoor',l:['Kamp & Trekking','Bisiklet','Kayak']}],
      brands:['Nike','Adidas','Decathlon','Wilson']},
    'fly-kozmetik':{slug:'kozmetik',title:'Kozmetik',cols:[
      {h:'Cilt',l:['Nemlendirici','Serum','Güneş Kremi']},
      {h:'Makyaj',l:['Fondöten','Ruj','Maskara']},
      {h:'Saç',l:['Şampuan','Saç Maskesi','Saç Boyası']}],
      brands:['LOreal','Maybelline','MAC','The Ordinary']},
    'fly-otomotiv':{slug:'otomotiv',title:'Otomotiv',cols:[
      {h:'Bakım',l:['Motor Yağı','Fren Parçaları','Lastik']},
      {h:'Aksesuar',l:['Oto Kılıf','Navigasyon','Kamera']},
      {h:'2 Teker',l:['Motosiklet','Bisiklet','Kask']}],
      brands:['Bosch','Castrol','Michelin']},
    'fly-bebek':{slug:'anne-ve-bebek',title:'Anne & Bebek',cols:[
      {h:'Giyim',l:['0-6 Ay','6-12 Ay','1-2 Yaş']},
      {h:'Ekipman',l:['Bebek Arabası','Oto Koltuğu','Beşik']},
      {h:'Anne',l:['Hamile Giyim','Emzirme','Vitamin']}],
      brands:['Chicco','Peg Perego','Philips Avent']},
    'fly-muzik':{slug:'muzik',title:'Müzik',cols:[
      {h:'Enstrüman',l:['Akustik Gitar','Elektro Gitar','Piyano']},
      {h:'Ses',l:['Mikrofon','Mixer','Amfi']},
      {h:'Aksesuar',l:['Tel & Pena','Stand','Kılıf & Çanta']}],
      brands:['Fender','Gibson','Yamaha','Shure']}
  };
  function vslug(t){t=t.toLowerCase().trim();var tr={'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','â':'a'};for(var k in tr){t=t.split(k).join(tr[k]);}return t.replace(/&/g,'ve').replace(/[^a-z0-9\s-]/g,'').replace(/\s+/g,'-').replace(/-+/g,'-').replace(/^-|-$/g,'');}
  var flyout=document.getElementById('vcat-flyout');
  var items=document.querySelectorAll('.vcat-item');
  var hideTimer=null;
  // Eski statik flyout yoksa (yeni dinamik vcat-fly kullanılıyor veya ürün sayfası) çıkma
  if(!flyout){ return; }
  var BANNERS={'fly-elek':{t:'Teknoloji Fırsatları',s:'En yeni cihazlar burada',g:'linear-gradient(135deg,#ff6a00,#ff9248)',e:'📱',tag:'%40 İNDİRİM',cnt:'12.400+ ürün'},'fly-moda':{t:'Yeni Sezon Moda',s:'Tarzını yansıt',g:'linear-gradient(135deg,#1a1024,#6d28d9)',e:'👗',tag:'YENİ SEZON',cnt:'8.900+ ürün'},'fly-ev':{t:'Evini Yenile',s:'Dekorasyonda büyük indirim',g:'linear-gradient(135deg,#0c4a6e,#0ea5e9)',e:'🛋️',tag:'%35 İNDİRİM',cnt:'6.200+ ürün'},'fly-elyapimi':{t:'El Emeği Eserler',s:'Tek parça özel tasarımlar',g:'linear-gradient(135deg,#831843,#db2777)',e:'🎨',tag:'ÖZEL',cnt:'3.100+ ürün'},'fly-kitap':{t:'Kitap & Hobi',s:'Keşfetmeye başla',g:'linear-gradient(135deg,#14532d,#22c55e)',e:'📚',tag:'ÇOK SATAN',cnt:'5.400+ ürün'},'fly-spor':{t:'Spor Sezonu',s:'Formda kal, hareketlen',g:'linear-gradient(135deg,#9a3412,#f97316)',e:'⚽',tag:'%30 İNDİRİM',cnt:'4.700+ ürün'},'fly-kozmetik':{t:'Güzellik & Bakım',s:'Kendine iyi bak',g:'linear-gradient(135deg,#831843,#ec4899)',e:'💄',tag:'POPÜLER',cnt:'7.300+ ürün'},'fly-otomotiv':{t:'Otomotiv Dünyası',s:'Aracına en iyisi',g:'linear-gradient(135deg,#1e1b4b,#6366f1)',e:'🚗',tag:'FIRSAT',cnt:'2.800+ ürün'},'fly-bebek':{t:'Anne & Bebek',s:'Minikler için en iyisi',g:'linear-gradient(135deg,#155e75,#06b6d4)',e:'🍼',tag:'%25 İNDİRİM',cnt:'5.900+ ürün'},'fly-muzik':{t:'Müzik Tutkusu',s:'Enstrümanlar & ekipman',g:'linear-gradient(135deg,#4a1d6e,#a21caf)',e:'🎸',tag:'YENİ',cnt:'1.900+ ürün'},};
  function build(key){
    var d=VC[key]; if(!d)return'';
    var h='<div class="fly-title">'+d.title+'</div><div class="fly-cols">';
    d.cols.forEach(function(c){
      h+='<div class="fly-col"><div class="fly-col-h">'+c.h+'</div>';
      c.l.forEach(function(l){ h+='<a class="fly-link" href="/product-category/'+d.slug+'/'+vslug(l)+'/">'+l+'</a>'; });
      h+='</div>';
    });
    h+='</div><div class="fly-brands"><span class="fly-brands-h">Popüler Markalar</span><div class="fly-brand-pills">';
    d.brands.forEach(function(b){ h+='<a class="fly-pill" href="/brand/'+vslug(b)+'/">'+b+'</a>'; });
    h+='</div></div>';
    var bn=BANNERS[key];
    if(bn){
      h+='<a class="fly-banner" href="/product-category/'+d.slug+'/" style="background:'+bn.g+'">'
        +'<div class="fly-banner-orb fbo1"></div><div class="fly-banner-orb fbo2"></div>'
        +'<div class="fly-banner-txt">'
        +'<span class="fly-banner-tag">'+bn.tag+'</span>'
        +'<div class="fly-banner-t">'+bn.t+'</div>'
        +'<div class="fly-banner-s">'+bn.s+'</div>'
        +'<div class="fly-banner-foot"><span class="fly-banner-btn">Hemen Keşfet →</span><span class="fly-banner-cnt">'+bn.cnt+'</span></div>'
        +'</div>'
        +'<div class="fly-banner-emoji">'+bn.e+'</div>'
        +'</a>';
    }
    return h;
  }
  items.forEach(function(it){
    it.addEventListener('mouseenter',function(){
      clearTimeout(hideTimer);
      items.forEach(function(x){x.classList.remove('active');});
      it.classList.add('active');
      flyout.innerHTML=build(it.dataset.fly);
      flyout.classList.add('show');
    });
  });
  var vcat=document.getElementById('vcat');
  vcat.addEventListener('mouseleave',function(){
    hideTimer=setTimeout(function(){flyout.classList.remove('show');items.forEach(function(x){x.classList.remove('active');});},120);
  });
  flyout.addEventListener('mouseenter',function(){clearTimeout(hideTimer);});
  flyout.addEventListener('mouseleave',function(){flyout.classList.remove('show');items.forEach(function(x){x.classList.remove('active');});});
})();


/* ═══ KATEGORİ TAB AJAX ═══ */
function switchCatTab(btn, cat){
  document.querySelectorAll('.cat-tab').forEach(function(t){t.classList.remove('on');});
  btn.classList.add('on');
  var grid=document.getElementById('catProductGrid');
  var loading=document.getElementById('catLoading');
  grid.style.opacity='.4';
  loading.style.display='block';
  // WordPress AJAX (admin-ajax.php) çağrısı
  var _nonce=(window.bazario_ajax&&window.bazario_ajax.nonce)?window.bazario_ajax.nonce:'';
  var data='action=bazario_filter_products&nonce='+encodeURIComponent(_nonce)+'&category='+encodeURIComponent(cat);
  fetch((window.bazario_ajax && window.bazario_ajax.url) ? window.bazario_ajax.url : '/wp-admin/admin-ajax.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:data
  })
  .then(function(r){return r.text();})
  .then(function(html){
    grid.innerHTML=html;
    grid.style.opacity='1';
    loading.style.display='none';
    bindCardEvents();
  })
  .catch(function(){
    grid.style.opacity='1';
    loading.style.display='none';
  });
}
// kart event'lerini yeniden bağla (AJAX sonrası)
function bindCardEvents(){
  // Tüm ürün kartları (Vitrindekiler, AJAX sekmeler, vb.) tıklanınca ürün detayına gider
  document.querySelectorAll('.pcard').forEach(function(c){
    if(c.dataset.href && !c._bound){
      c._bound=true; c.style.cursor='pointer';
      c.addEventListener('click',function(e){
        if(e.target.closest('.pcart,.p-wish,a,button'))return;
        // Aynı sekmede aç
        window.location.href=c.dataset.href;
      });
    }
  });
}
// Sayfa yüklenince tüm kartları bağla
if(document.readyState!=='loading'){ bindCardEvents(); }
else{ document.addEventListener('DOMContentLoaded', bindCardEvents); }



/* ═══════════ ÜRÜN DETAY SAYFASI JS ═══════════ */

/* ÜRÜN DETAY FONKSİYONLARI */
(function(){




/* (mega menü kodu header IIFE'sinde; burada kaldırıldı) */


function setThumb(el,emoji){document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('on'));el.classList.add('on');var me=document.getElementById('mainEmoji');if(me)me.textContent=emoji;}
function setThumbImg(el,src){document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('on'));el.classList.add('on');var m=document.getElementById('mainImgEl');if(m)m.src=src;}
function toggleImgFav(){
  var hbBtn=document.getElementById('hbFavBtn');
  if(hbBtn){hbBtn.click();setTimeout(function(){var f=document.getElementById('imgFav');if(f){var on=hbBtn.classList.contains('active');f.textContent=on?'❤️':'🤍';f.classList.toggle('on',on);}},30);return;}
  var f=document.getElementById('imgFav');if(!f)return;
  f.textContent=f.textContent==='🤍'?'❤️':'🤍';f.classList.toggle('on');
}
function selColor(el,n,attr){document.querySelectorAll('.pd-color').forEach(c=>c.classList.remove('on'));el.classList.add('on');var s=document.getElementById('sel-'+attr);if(s)s.textContent=n;}
function selVar(el,n,attr){if(el.classList.contains('dis'))return;el.parentNode.querySelectorAll('.pd-var').forEach(v=>v.classList.remove('on'));el.classList.add('on');var s=document.getElementById('sel-'+attr);if(s)s.textContent=n;}
function qty(d){
  var i=document.getElementById('qtyVal');
  if(!i)return;
  var cur=parseInt(i.value)||1;
  var max=i.max?parseInt(i.max):9999;
  var min=i.min?parseInt(i.min):1;
  var next=cur+d;
  if(next<min)next=min;
  if(next>max)next=max;
  i.value=next;
  // değişiklik event'i tetikle (WooCommerce dinliyor olabilir)
  i.dispatchEvent(new Event('change',{bubbles:true}));
}
function addToCart(){const b=document.getElementById('cartBtn');b.textContent='✓ Sepete Eklendi!';b.classList.add('added');showToast('🛒 Ürün sepete eklendi!');setTimeout(()=>{b.textContent='🛒 Sepete Ekle';b.classList.remove('added');},1800);}
function toggleWishRow(){const w=document.getElementById('wishRow');w.classList.toggle('on');w.innerHTML=w.classList.contains('on')?'❤️ <span>Favorilerde</span>':'🤍 <span>Favorilere Ekle</span>';}
function tab(btn,id){document.querySelectorAll('.tbn').forEach(b=>b.classList.remove('on'));document.querySelectorAll('.tab-pane').forEach(p=>p.classList.remove('on'));btn.classList.add('on');document.getElementById(id).classList.add('on');}
function goTab(i){const btns=document.querySelectorAll('.tbn');tab(btns[i],['t-desc','t-spec','t-rev','t-sim','t-qa'][i]);document.querySelector('.pd-tabs').scrollIntoView({behavior:'smooth'});}
function revFilter(el){document.querySelectorAll('.rev-f').forEach(f=>f.classList.remove('on'));el.classList.add('on');}
let toastTimer;
function showToast(msg){const t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>t.classList.remove('show'),2200);}
document.querySelectorAll('.pd-ip').forEach(p=>p.addEventListener('click',function(){showToast('💳 Taksit seçeneği: '+this.textContent);}));
document.querySelector('.sc-btn')&&document.querySelector('.sc-btn').addEventListener('click',function(){});





/* ═══════════ İLETİŞİM SAYFASI JS ═══════════ */
function selectCat(el){
  document.querySelectorAll('.cf-cat').forEach(function(c){c.classList.remove('on');});
  el.classList.add('on');
  var h=document.getElementById('cf-kategori');
  if(h) h.value=el.dataset.v||el.textContent.trim();
}
if(typeof selectCat==="function")window.selectCat=selectCat;
function toggleFaq(el){
  var a=el.nextElementSibling;
  var isOpen=a.classList.contains('open');
  document.querySelectorAll('.faq-q.open').forEach(function(q){q.classList.remove('open');q.nextElementSibling.classList.remove('open');});
  if(!isOpen){el.classList.add('open');a.classList.add('open');}
}
if(typeof toggleFaq==="function")window.toggleFaq=toggleFaq;
function faqSearch(q){
  q=(q||'').toLowerCase();
  document.querySelectorAll('.faq-item').forEach(function(item){
    var t=item.textContent.toLowerCase();
    item.style.display = t.indexOf(q)>-1 ? '' : 'none';
  });
}
if(typeof faqSearch==="function")window.faqSearch=faqSearch;
function submitContactForm(e){
  e.preventDefault();
  var form=document.getElementById('pazaryeriContactForm');
  var btn=document.getElementById('cfSubmitBtn');
  var note=document.getElementById('cfNote');
  if(!form) return false;
  var fd=new FormData(form);
  fd.append('action','pazaryeri_contact');
  fd.append('nonce',(window.bazario_ajax&&window.bazario_ajax.nonce)||'');
  btn.textContent='⏳ Gönderiliyor...'; btn.disabled=true;
  fetch((window.bazario_ajax&&window.bazario_ajax.url)||'/wp-admin/admin-ajax.php',{
    method:'POST', body:fd
  })
  .then(function(r){return r.json();})
  .then(function(res){
    if(res.success){
      btn.textContent='✓ Gönderildi!'; btn.classList.add('sent');
      if(note){note.textContent=res.data.message; note.style.color='var(--green)';}
      form.reset();
      setTimeout(function(){btn.textContent='Gönder →';btn.classList.remove('sent');btn.disabled=false;},4000);
    } else {
      btn.textContent='Gönder →'; btn.disabled=false;
      if(note){note.textContent=res.data.message; note.style.color='#dc2626';}
    }
  })
  .catch(function(){
    btn.textContent='Gönder →'; btn.disabled=false;
    if(note){note.textContent='Bağlantı hatası, tekrar deneyin.'; note.style.color='#dc2626';}
  });
  return false;
}
if(typeof submitContactForm==="function")window.submitContactForm=submitContactForm;



// global erişim (inline onclick için)
if(typeof setThumb==='function')window.setThumb=setThumb;
if(typeof setThumbImg==='function')window.setThumbImg=setThumbImg;
if(typeof toggleImgFav==='function')window.toggleImgFav=toggleImgFav;
if(typeof selColor==='function')window.selColor=selColor;
if(typeof selVar==='function')window.selVar=selVar;
if(typeof qty==='function')window.qty=qty;
if(typeof addToCart==='function')window.addToCart=addToCart;
if(typeof tab==='function')window.tab=tab;
if(typeof goTab==='function')window.goTab=goTab;
if(typeof revFilter==='function')window.revFilter=revFilter;
if(typeof showToast==='function')window.showToast=showToast;
if(typeof toggleWishRow==='function')window.toggleWishRow=toggleWishRow;
})();

/* ── ÜRÜN KARŞILAŞTIRMA ── */
var pzCompItems=(function(){try{return JSON.parse(localStorage.getItem('pzComp')||'[]');}catch(e){return [];}}());
var PZ_COMP_MAX=4;

function pzToggleComp(btn){
  var card=btn.closest('.pcard');
  var data;
  if(card){
    try{data=JSON.parse(card.getAttribute('data-pzcomp')||'{}');}catch(e){return;}
  }else{
    var src=btn.closest('[data-pzcomp]')||btn;
    try{data=JSON.parse(src.getAttribute('data-pzcomp')||'{}');}catch(e){return;}
  }
  if(!data.id)return;
  var idx=-1;
  for(var i=0;i<pzCompItems.length;i++){if(pzCompItems[i].id===data.id){idx=i;break;}}
  if(idx>-1){
    pzCompItems.splice(idx,1);
    btn.classList.remove('on');
    var lbl=btn.querySelector('.pcomp-lbl');if(lbl)lbl.textContent='Karşılaştır';
    var ic=btn.querySelector('.pcomp-ico-cmp');if(ic)ic.style.display='';
    var ck=btn.querySelector('.pcomp-ico-chk');if(ck)ck.style.display='none';
  }else{
    if(pzCompItems.length>=PZ_COMP_MAX){if(typeof showToast==='function')showToast('En fazla '+PZ_COMP_MAX+' ürün karşılaştırabilirsiniz!');return;}
    pzCompItems.push(data);
    btn.classList.add('on');
    var lbl=btn.querySelector('.pcomp-lbl');if(lbl)lbl.textContent='Eklendi';
    var ic=btn.querySelector('.pcomp-ico-cmp');if(ic)ic.style.display='none';
    var ck=btn.querySelector('.pcomp-ico-chk');if(ck)ck.style.display='';
  }
  try{localStorage.setItem('pzComp',JSON.stringify(pzCompItems));}catch(e){}
  pzUpdateCompBar();
}

function pzUpdateCompBar(){
  var bar=document.getElementById('pzCompBar');
  var slots=document.getElementById('pzCompSlots');
  var cnt=document.getElementById('pzCompCount');
  var cta=document.getElementById('pzCompCta');
  if(!bar)return;
  bar.classList.toggle('show',pzCompItems.length>0);
  if(cnt)cnt.textContent=pzCompItems.length+' ürün seçili (max '+PZ_COMP_MAX+')';
  if(cta){cta.disabled=pzCompItems.length<2;cta.innerHTML='<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 8L22 12L18 16M6 8L2 12L6 16M14 4L10 20"/></svg> Karşılaştır'+(pzCompItems.length>0?' ('+pzCompItems.length+')':'');}
  if(!slots)return;
  var html='';
  pzCompItems.forEach(function(p){
    html+='<div class="pz-comp-slot"><img src="'+p.img+'" alt=""><button class="pz-comp-slot-rm" onclick="event.stopPropagation();pzRemoveComp('+p.id+')">✕</button></div>';
  });
  for(var i=pzCompItems.length;i<PZ_COMP_MAX;i++){
    html+='<div class="pz-comp-slot pz-comp-slot-empty"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg></div>';
  }
  slots.innerHTML=html;
  pzSyncCompBtns();
}

function pzSyncCompBtns(){
  document.querySelectorAll('.pcomp-btn').forEach(function(btn){
    var card=btn.closest('.pcard');if(!card)return;
    var data;try{data=JSON.parse(card.getAttribute('data-pzcomp')||'{}');}catch(e){return;}
    var isIn=pzCompItems.some(function(p){return p.id===data.id;});
    btn.classList.toggle('on',isIn);
    var lbl=btn.querySelector('.pcomp-lbl');if(lbl)lbl.textContent=isIn?'Eklendi':'Karşılaştır';
    var ic=btn.querySelector('.pcomp-ico-cmp');if(ic)ic.style.display=isIn?'none':'';
    var ck=btn.querySelector('.pcomp-ico-chk');if(ck)ck.style.display=isIn?'':'none';
  });
}

function pzRemoveComp(id){
  pzCompItems=pzCompItems.filter(function(p){return p.id!==id;});
  try{localStorage.setItem('pzComp',JSON.stringify(pzCompItems));}catch(e){}
  pzUpdateCompBar();
  var modal=document.getElementById('pzCompModal');
  if(modal&&modal.classList.contains('show')){
    if(pzCompItems.length<1)pzCloseComp();else pzBuildCompTable();
  }
}

function pzClearComp(){
  pzCompItems=[];
  try{localStorage.removeItem('pzComp');}catch(e){}
  pzUpdateCompBar();
  var modal=document.getElementById('pzCompModal');
  if(modal){modal.classList.remove('show');document.body.style.overflow='';}
}

function pzOpenComp(){
  if(pzCompItems.length<2){if(typeof showToast==='function')showToast('⚖️ Karşılaştırmak için en az 2 ürün seçin!');return;}
  var modal=document.getElementById('pzCompModal');if(!modal)return;
  // Özellikleri olmayan ürünler için AJAX ile çek
  var needIds=pzCompItems.filter(function(p){return !p.attrs;}).map(function(p){return p.id;});
  function _open(){
    pzBuildCompTable();
    modal.classList.add('show');
    document.body.style.overflow='hidden';
  }
  if(needIds.length===0){_open();return;}
  var fd=new FormData();
  fd.append('action','pz_get_comp_attrs');
  fd.append('ids',JSON.stringify(needIds));
  fd.append('nonce',(window.bazario_ajax||{}).nonce||'');
  fetch(((window.bazario_ajax||{}).url||'/wp-admin/admin-ajax.php'),{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(res){
      if(res.success&&res.data){
        pzCompItems.forEach(function(p){if(!p.attrs&&res.data[p.id])p.attrs=res.data[p.id];});
        try{localStorage.setItem('pzComp',JSON.stringify(pzCompItems));}catch(e){}
      }
    })
    .catch(function(){})
    .finally(function(){_open();});
}

function pzCloseComp(){
  var modal=document.getElementById('pzCompModal');
  if(modal)modal.classList.remove('show');
  document.body.style.overflow='';
}

function pzFmtPrice(n){
  if(typeof n!=='number')return '—';
  return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';
}

function pzBuildCompTable(){
  var table=document.getElementById('pzCompTable');
  if(!table||pzCompItems.length===0)return;
  var minPrice=Math.min.apply(null,pzCompItems.map(function(p){return p.price;}));
  var maxDisc=Math.max.apply(null,pzCompItems.map(function(p){return p.discount;}));
  var maxRating=Math.max.apply(null,pzCompItems.map(function(p){return p.rating;}));
  var maxReviews=Math.max.apply(null,pzCompItems.map(function(p){return p.reviews;}));
  var multi=pzCompItems.length>1;

  var html='<thead><tr><th class="pz-ct-lbl"></th>';
  pzCompItems.forEach(function(p){
    html+='<th class="pz-ct-head">'+
      '<div class="pz-ct-img-wrap"><img src="'+p.img+'" alt="'+p.title+'"></div>'+
      '<div class="pz-ct-name">'+p.title+'</div>'+
      '<div class="pz-ct-price">'+pzFmtPrice(p.price)+'</div>'+
      '<a class="pz-ct-link" href="'+p.url+'" target="_blank">İncele →</a>'+
      '<button class="pz-ct-rm" onclick="pzRemoveComp('+p.id+')">✕ Çıkar</button>'+
    '</th>';
  });
  html+='</tr></thead><tbody>';

  // Fiyat
  html+='<tr><td class="pz-ct-lbl">💰 Fiyat</td>';
  pzCompItems.forEach(function(p){
    var best=multi&&p.price===minPrice;
    html+='<td class="pz-ct-cell'+(best?' pz-ct-best':'')+'">'+
      (best?'<span class="pz-ct-crown">🏆</span>':'')+
      (p.regular>p.price?'<div class="pz-ct-old">'+pzFmtPrice(p.regular)+'</div>':'')+
      '<div class="pz-ct-main">'+pzFmtPrice(p.price)+'</div>'+
    '</td>';
  });
  html+='</tr>';

  // İndirim
  html+='<tr><td class="pz-ct-lbl">🏷️ İndirim</td>';
  pzCompItems.forEach(function(p){
    var best=multi&&p.discount>0&&p.discount===maxDisc;
    html+='<td class="pz-ct-cell'+(best?' pz-ct-best':'')+'">'+
      (best?'<span class="pz-ct-crown">🏆</span>':'')+
      '<div class="pz-ct-main">'+(p.discount>0?'<span class="pz-ct-disc">%'+p.discount+'</span>':'<span class="pz-ct-na">—</span>')+'</div>'+
    '</td>';
  });
  html+='</tr>';

  // Puan
  html+='<tr><td class="pz-ct-lbl">⭐ Puan</td>';
  pzCompItems.forEach(function(p){
    var best=multi&&p.rating>0&&p.rating===maxRating;
    var stars='';for(var i=1;i<=5;i++)stars+=i<=Math.round(p.rating)?'★':'☆';
    html+='<td class="pz-ct-cell'+(best?' pz-ct-best':'')+'">'+
      (best?'<span class="pz-ct-crown">🏆</span>':'')+
      '<div class="pz-ct-main"><span class="pz-ct-stars">'+stars+'</span><br><small>'+(p.rating>0?p.rating+' / 5':'Henüz yok')+'</small></div>'+
    '</td>';
  });
  html+='</tr>';

  // Yorum
  html+='<tr><td class="pz-ct-lbl">💬 Yorumlar</td>';
  pzCompItems.forEach(function(p){
    var best=multi&&p.reviews>0&&p.reviews===maxReviews;
    html+='<td class="pz-ct-cell'+(best?' pz-ct-best':'')+'">'+
      (best?'<span class="pz-ct-crown">🏆</span>':'')+
      '<div class="pz-ct-main">'+(p.reviews>0?p.reviews+' yorum':'<span class="pz-ct-na">Henüz yok</span>')+'</div>'+
    '</td>';
  });
  html+='</tr>';

  // Stok
  html+='<tr><td class="pz-ct-lbl">📦 Stok</td>';
  pzCompItems.forEach(function(p){
    html+='<td class="pz-ct-cell"><div class="pz-ct-main '+(p.inStock?'pz-ct-yes':'pz-ct-no')+'">'+(p.inStock?'✓ Stokta':'✗ Tükendi')+'</div></td>';
  });
  html+='</tr>';

  // Kargo
  html+='<tr><td class="pz-ct-lbl">🚚 Kargo</td>';
  pzCompItems.forEach(function(p){
    html+='<td class="pz-ct-cell"><div class="pz-ct-main '+(p.freeShip?'pz-ct-yes':'')+'">'+  (p.freeShip?'🚚 Ücretsiz':'Ücretli')+'</div></td>';
  });
  html+='</tr>';

  // Satıcı
  html+='<tr><td class="pz-ct-lbl">🏪 Satıcı</td>';
  pzCompItems.forEach(function(p){
    html+='<td class="pz-ct-cell"><div class="pz-ct-main">'+p.seller+'</div></td>';
  });
  html+='</tr>';

  // Ürün Özellikleri bölümü
  var allAttrKeys={};
  pzCompItems.forEach(function(p){
    if(p.attrs&&typeof p.attrs==='object'){
      Object.keys(p.attrs).forEach(function(k){allAttrKeys[k]=true;});
    }
  });
  var attrKeys=Object.keys(allAttrKeys);
  if(attrKeys.length>0){
    html+='<tr class="pz-ct-sect"><td colspan="'+(pzCompItems.length+1)+'">📋 Ürün Özellikleri</td></tr>';
    attrKeys.forEach(function(key){
      html+='<tr><td class="pz-ct-lbl pz-ct-attr-lbl">'+key+'</td>';
      pzCompItems.forEach(function(p){
        var val=(p.attrs&&p.attrs[key])?p.attrs[key]:'<span class="pz-ct-na">—</span>';
        html+='<td class="pz-ct-cell"><div class="pz-ct-main pz-ct-attr">'+val+'</div></td>';
      });
      html+='</tr>';
    });
  }

  html+='</tbody>';
  table.innerHTML=html;
}

document.addEventListener('DOMContentLoaded',function(){
  pzUpdateCompBar();
});

function pzShareApp(platform,url,title){
  var labels={instagram:'Instagram',tiktok:'TikTok'};
  if(navigator.share){
    navigator.share({title:title,url:url}).catch(function(){});
  } else {
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(url).then(function(){
        if(typeof showToast==='function')showToast('🔗 Bağlantı kopyalandı! '+labels[platform]+' uygulamasında paylaşabilirsiniz.');
      });
    } else {
      var ta=document.createElement('textarea');ta.value=url;ta.style.position='fixed';ta.style.opacity='0';
      document.body.appendChild(ta);ta.select();
      try{document.execCommand('copy');if(typeof showToast==='function')showToast('🔗 Bağlantı kopyalandı! '+labels[platform]+' uygulamasında paylaşabilirsiniz.');}catch(e){}
      document.body.removeChild(ta);
    }
  }
}
function pzCopyLink(url){
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(url).then(function(){
      if(typeof showToast==='function')showToast('🔗 Bağlantı kopyalandı!');
    });
  } else {
    var ta=document.createElement('textarea');
    ta.value=url;ta.style.position='fixed';ta.style.opacity='0';
    document.body.appendChild(ta);ta.select();
    try{document.execCommand('copy');if(typeof showToast==='function')showToast('🔗 Bağlantı kopyalandı!');}catch(e){}
    document.body.removeChild(ta);
  }
}
function pzCopySku(sku){
  if(navigator.clipboard&&navigator.clipboard.writeText){
    navigator.clipboard.writeText(sku).then(function(){
      if(typeof showToast==='function')showToast('📋 SKU kopyalandı: '+sku);
    });
  } else {
    var ta=document.createElement('textarea');
    ta.value=sku;ta.style.position='fixed';ta.style.opacity='0';
    document.body.appendChild(ta);ta.select();
    try{document.execCommand('copy');if(typeof showToast==='function')showToast('📋 SKU kopyalandı: '+sku);}catch(e){}
    document.body.removeChild(ta);
  }
}
/* ═══ HEPSIBURADA ÜRÜN DETAY GALERİ ═══ */
/* eski hbSetImg kaldırıldı; yeni window.hbSetImg kullanılıyor */


/* ═══ AMAZON TARZI GÖRSEL ZOOM (ürün detay) ═══ */
(function(){
  function initZoom(){
    var box = document.querySelector('.hb-main-img');
    var img = document.getElementById('mainImgEl');
    if(!box || !img) return;
    if(box._zoomInit) return; box._zoomInit = true;

    box.addEventListener('mousemove', function(e){
      var rect = img.getBoundingClientRect();
      // imleç görselin üzerinde mi?
      if(e.clientX < rect.left || e.clientX > rect.right || e.clientY < rect.top || e.clientY > rect.bottom){
        img.style.transform = '';
        img.style.transformOrigin = 'center center';
        return;
      }
      var x = ((e.clientX - rect.left) / rect.width) * 100;
      var y = ((e.clientY - rect.top) / rect.height) * 100;
      img.style.transformOrigin = x + '% ' + y + '%';
      img.style.transform = 'scale(2.4)';
    });
    box.addEventListener('mouseleave', function(){
      img.style.transform = '';
      img.style.transformOrigin = 'center center';
    });
  }
  if(document.readyState !== 'loading'){ initZoom(); }
  else { document.addEventListener('DOMContentLoaded', initZoom); }
  // thumbnail değişince img referansı aynı kalır, sorun yok
  window._hbInitZoom = initZoom;
})();

/* Taksit listesini aç/kapa */
function hbToggleInst(btn){
  var list = document.getElementById('hbInstList');
  if(!list) return;
  var hidden = list.querySelectorAll('.hb-inst-hidden');
  var isOpen = btn.dataset.open === '1';
  hidden.forEach(function(el){ el.style.display = isOpen ? 'none' : 'flex'; });
  btn.dataset.open = isOpen ? '0' : '1';
  btn.textContent = isOpen ? 'Tümünü Gör ›' : 'Daha Az ▴';
}


/* ═══ HEMEN AL — sepete ekle + ödemeye yönlendir ═══ */
(function(){
  function initBuyNow(){
    var btn = document.querySelector('.hb-buy-now');
    var form = document.querySelector('.hb-cart-form');
    if(!btn || !form || btn._bn) return; btn._bn = true;
    btn.addEventListener('click', function(){
      // forma gizli hb_buy_now alanı ekle (PHP checkout'a yönlendirecek)
      if(!form.querySelector('input[name="hb_buy_now"]')){
        var hid = document.createElement('input');
        hid.type='hidden'; hid.name='hb_buy_now'; hid.value='1';
        form.appendChild(hid);
      }
    });
  }
  if(document.readyState!=='loading'){ initBuyNow(); }
  else{ document.addEventListener('DOMContentLoaded', initBuyNow); }
})();


/* ═══ DİNAMİK MEGA MENÜ (kategori hover) ═══ */
function megaShow(key, el){
  // sol aktif
  document.querySelectorAll('.ml-item').forEach(function(m){m.classList.remove('on');});
  if(el) el.classList.add('on');
  var _mp = document.getElementById('megapanel-'+key);
  if(_mp && window.pzInitMenuMore) window.pzInitMenuMore(_mp);
  // sağ panel değiştir
  document.querySelectorAll('.mega-cat-panel').forEach(function(p){p.classList.remove('on');});
  var panel = document.getElementById('megapanel-'+key);
  if(panel) panel.classList.add('on');
}
window.megaShow = megaShow;


/* ═══ ALTERNATİF ÜRÜNLER YATAY KAYDIRMA ═══ */
function altScroll(dir){
  var row = document.getElementById('altProductsRow');
  if(!row) return;
  row.scrollBy({ left: dir * 440, behavior: 'smooth' });
}
window.altScroll = altScroll;


/* ═══ DİKEY KATEGORİ MENÜSÜ FLYOUT (alt kategoriler) ═══ */
function vcatShow(id){
  document.querySelectorAll('.vcat-fly').forEach(function(f){f.classList.remove('on');});
  var fly = document.getElementById(id);
  if(fly && window.pzInitMenuMore) window.pzInitMenuMore(fly);
  if(fly) fly.classList.add('on');
}
function vcatHide(){
  document.querySelectorAll('.vcat-fly').forEach(function(f){f.classList.remove('on');});
}
window.vcatShow = vcatShow;
window.vcatHide = vcatHide;


/* ═══ ÜST MENÜ HOVER DROPDOWN ═══ */
var _ciTimer = null;
function ciOpen(id){
  if(_ciTimer){ clearTimeout(_ciTimer); _ciTimer=null; }
  document.querySelectorAll('.ci-drop.on').forEach(function(d){ if(d.id!==id) d.classList.remove('on'); });
  var drop = document.getElementById(id);
  if(drop && window.pzInitMenuMore) window.pzInitMenuMore(drop);
  if(drop) drop.classList.add('on');
}
function ciClose(id){
  _ciTimer = setTimeout(function(){
    var drop = document.getElementById(id);
    if(drop) drop.classList.remove('on');
  }, 150);
}
window.ciOpen = ciOpen;
window.ciClose = ciClose;


/* ═══ MARKA FİLTRESİ ARAMA ═══ */
function filterBrandList(val){
  var q = (val || '').toLowerCase().trim();
  var list = document.getElementById('shopBrandList');
  if(!list) return;
  list.querySelectorAll('li').forEach(function(li){
    var name = li.getAttribute('data-brand-name') || '';
    li.style.display = (q === '' || name.indexOf(q) !== -1) ? '' : 'none';
  });
}
window.filterBrandList = filterBrandList;


/* ═══════════════════════════════════════════════
   ÜRÜN DETAY — Varyasyon seçimi + resim + zoom + thumb kaydırma
═══════════════════════════════════════════════ */
(function(){
  // ── Varyasyon verisi ──
  var pzVarData = [];
  var pzSelected = {}; // {attribute_pa_renk: 'siyah', ...}
  var pzCarouselGoTo = null; // varyasyon resim değiştiğinde carousel'i günceller

  function pzLoadVarData(){
    var el = document.getElementById('pzVariationData');
    if(!el) return;
    try { pzVarData = JSON.parse(el.textContent); } catch(e){ pzVarData = []; }
  }

  // Seçilen attribute'lara uyan varyasyonu bul
  function pzNorm(s){ return String(s||'').toLowerCase().replace(/\s+/g,'-').replace(/[^a-z0-9\-ışğüöç]/gi,''); }
  function pzMatch(a, b){
    if(!a || !b) return true; // biri "any"
    if(a === b) return true;
    return pzNorm(a) === pzNorm(b);
  }
  function pzGetSel(key){
    // pzSelected'tan key'i esnek bul (anahtar uyuşmazlığına karşı)
    if(pzSelected[key] !== undefined) return pzSelected[key];
    var nk = pzNorm(key);
    for(var sk in pzSelected){ if(pzNorm(sk) === nk) return pzSelected[sk]; }
    return undefined;
  }
  function pzFindVariation(){
    var selKeys = Object.keys(pzSelected);
    for(var i=0;i<pzVarData.length;i++){
      var v = pzVarData[i], ok = true;
      for(var key in v.attributes){
        var want = v.attributes[key]; // boş = "any"
        if(want && !pzMatch(pzGetSel(key), want)){ ok = false; break; }
      }
      if(ok) return v;
    }
    return null;
  }

  // Varyasyon seçimini uygula (resim, fiyat, stok, gizli alanlar)
  function pzApplyVariation(){
    var vid = document.getElementById('pzVariationId');
    var addBtn = document.getElementById('pzAddCart');
    var warn = document.getElementById('pzVarWarning');
    if(!vid) return; // basit ürün

    var v = pzFindVariation();
    if(v && v.id){
      // Eşleşen varyasyon bulundu
      vid.value = v.id;
      // gizli attribute alanlarını doldur (JSON'daki gerçek anahtarlarla)
      for(var key in v.attributes){
        var f = document.getElementById('pzAttr-'+key);
        if(f){ f.value = (v.attributes[key] || pzGetSel(key) || ''); }
      }
      if(v.image){ pzSetMainImage(v.image); }
      if(v.price_html){
        var pa = document.querySelector('.hb-price-area');
        if(pa) pa.innerHTML = v.price_html;
      }
      if(addBtn){
        if(v.in_stock !== false){ addBtn.disabled = false; addBtn.textContent = '🛒 Sepete Ekle'; }
        else { addBtn.disabled = true; addBtn.textContent = 'Bu seçenek tükendi'; }
      }
      if(warn) warn.style.display = 'none';
      // STOK SAYISI BİLGİSİNİ GÜNCELLE
      pzUpdateStockInfo(v);
    } else {
      // Henüz tam eşleşme yok — butonu kilitleme, sadece submit'te uyar
      vid.value = '';
      if(addBtn){ addBtn.disabled = false; }
      pzUpdateStockInfo(null);
    }
  }

  // Seçili varyasyonun stok sayısını ekrana yansıt
  function pzUpdateStockInfo(v){
    var stockEl = document.getElementById('pzStockInfo');
    if(!stockEl) return;
    if(!v){
      stockEl.innerHTML = stockEl.getAttribute('data-orig') || stockEl.innerHTML;
      return;
    }
    if(!stockEl.getAttribute('data-orig')) stockEl.setAttribute('data-orig', stockEl.innerHTML);
    if(v.in_stock === false){
      stockEl.innerHTML = '<span class="hb-stock-out">⚠ Bu seçenek tükendi</span>';
    } else if(v.max_qty && parseInt(v.max_qty) > 0){
      var qty = parseInt(v.max_qty);
      var cls = qty <= 5 ? 'hb-stock-low' : 'hb-stock-ok';
      var icon = qty <= 5 ? '⚡' : '✓';
      stockEl.innerHTML = '<span class="'+cls+'">'+icon+' '+qty+' adet stokta</span>';
    } else {
      stockEl.innerHTML = '<span class="hb-stock-ok">✓ Stokta</span>';
    }
  }

  // STOKSUZ SEÇENEKLERİ İŞARETLE (sayfa yüklenince ve seçim değiştiğinde)
  function pzMarkUnavailableOptions(){
    if(!pzVarData || !pzVarData.length) return;
    // Her varyant butonu için: bu değer içeren bir in_stock varyasyon var mı?
    document.querySelectorAll('.hb-var, .hb-color').forEach(function(btn){
      var attr = btn.getAttribute('data-attr');
      var val  = btn.getAttribute('data-value');
      if(!attr || !val) return;
      // Mevcut DİĞER seçimlerle birlikte bu değer için stokta varyasyon var mı?
      var hasStock = false;
      for(var i=0; i<pzVarData.length; i++){
        var v = pzVarData[i];
        if(v.in_stock === false) continue;
        // Bu varyasyon, bu butonun değerini içeriyor mu?
        var attrVal = v.attributes[attr];
        if(!attrVal) attrVal = v.attributes[attr.replace(/^attribute_/, 'attribute_pa_')];
        // Slug veya isim eşleşmesi
        if(pzMatch(val, attrVal) || val === attrVal){
          // Diğer seçili attribute'lar da eşleşiyor mu?
          var otherOk = true;
          for(var key in v.attributes){
            if(key === attr) continue;
            var want = v.attributes[key];
            var got = pzGetSel(key);
            if(want && got && !pzMatch(got, want)){ otherOk = false; break; }
          }
          if(otherOk){ hasStock = true; break; }
        }
      }
      if(hasStock){
        btn.classList.remove('hb-out-of-stock');
        btn.removeAttribute('aria-disabled');
      } else {
        btn.classList.add('hb-out-of-stock');
        btn.setAttribute('aria-disabled', 'true');
      }
    });
  }

  // Varyant butonu tıklama (renk/beden)
  window.pzSelectVar = function(btn, name, attrName){
    var attr = btn.getAttribute('data-attr');
    var val  = btn.getAttribute('data-value');
    if(!attr){
      // basit görsel varyant (varyasyon yok) - sadece etiket güncelle
      var lbl = document.getElementById('sel-'+attrName);
      if(lbl) lbl.textContent = name;
      var grp0 = btn.parentElement.querySelectorAll('button');
      grp0.forEach(function(b){ b.classList.remove('on'); });
      btn.classList.add('on');
      return;
    }
    // aynı gruptaki diğerlerini pasifleştir
    var grp = btn.parentElement.querySelectorAll('button');
    grp.forEach(function(b){ b.classList.remove('on'); });
    btn.classList.add('on');
    // etiket
    var lbl2 = document.getElementById('sel-'+attrName);
    if(lbl2) lbl2.textContent = name;
    // seçimi kaydet
    pzSelected[attr] = val;
    pzApplyVariation();
    pzMarkUnavailableOptions();
  };

  // ── Büyük resim değiştirme (thumb + varyasyon ortak) ──
  function pzSetMainImage(src){
    var main = document.getElementById('mainImgEl');
    if(main){ main.src = src; }
    var lb = document.getElementById('pzLightboxImg');
    if(lb){ lb.src = src; }
    if(pzCarouselGoTo) pzCarouselGoTo(src);
  }
  window.hbSetImg = function(el, src){
    document.querySelectorAll('.hb-thumb').forEach(function(t){ t.classList.remove('on'); });
    if(el) el.classList.add('on');
    pzSetMainImage(src);
  };

  // ── 6+ thumbnail kaydırma kontrolü ──
  function pzInitThumbScroll(){
    var thumbs = document.querySelector('.hb-thumbs');
    if(!thumbs || thumbs._scrollInit) return;
    var items = thumbs.querySelectorAll('.hb-thumb');
    if(items.length <= 6) return; // 6 veya az ise normal göster
    thumbs._scrollInit = true;

    // thumbs'ı bir sarmalayıcıya al (galerinin flex item'ı olarak kalsın)
    var wrap = document.createElement('div');
    wrap.className = 'hb-thumbs-wrap';
    thumbs.parentElement.insertBefore(wrap, thumbs);

    var up = document.createElement('button');
    up.className = 'hb-thumb-nav'; up.innerHTML = '▲'; up.type = 'button';
    var down = document.createElement('button');
    down.className = 'hb-thumb-nav'; down.innerHTML = '▼'; down.type = 'button';

    // sıra: up -> thumbs -> down, hepsi wrap içinde
    wrap.appendChild(up);
    wrap.appendChild(thumbs);
    wrap.appendChild(down);
    thumbs.classList.add('hb-thumbs-scroll');

    up.onclick = function(){ thumbs.scrollBy({ top:-150, behavior:'smooth' }); };
    down.onclick = function(){ thumbs.scrollBy({ top:150, behavior:'smooth' }); };
  }

  // ── Ürün Galerisi: Otomatik Karusel + Kaydırma + Galeri Lightbox ──
  function pzInitClickZoom(){
    var mainBox = document.querySelector('.hb-main-img');
    var mainImg = document.getElementById('mainImgEl');
    if(!mainBox || !mainImg || mainImg._clickZoom) return;
    mainImg._clickZoom = true;

    // Tüm resim URL'lerini data-full attribute'undan topla (güvenilir)
    var carImgs = [];
    document.querySelectorAll('.hb-thumb').forEach(function(th){
      var full = th.getAttribute('data-full');
      if(full) carImgs.push(full);
    });
    if(!carImgs.length) carImgs = [mainImg.src];
    var N = carImgs.length;

    // Tek resim: sadece lightbox aç
    if(N < 2){
      mainImg.style.cursor = 'zoom-in';
      mainImg.addEventListener('click', function(){ pzOpenGalleryLb(carImgs, 0); });
      return;
    }

    // ── Çok resim: Karusel kur ──
    // Tüm layout-critical CSS inline — harici CSS'e bağımlılık yok
    var carWrap = document.createElement('div');
    carWrap.className = 'pz-car-wrap';
    carWrap.style.cssText = 'position:absolute;top:0;left:0;right:0;bottom:0;overflow:hidden;cursor:zoom-in;';

    var carTrack = document.createElement('div');
    carTrack.className = 'pz-car-track';
    carTrack.style.cssText = 'display:flex;width:100%;height:100%;will-change:transform;-webkit-user-select:none;user-select:none;';

    carImgs.forEach(function(src, i){
      var sl = document.createElement('div');
      sl.className = 'pz-car-slide';
      sl.style.cssText = 'flex:0 0 100%;width:100%;height:100%;display:flex;align-items:center;justify-content:center;';
      var im = document.createElement('img');
      if(i === 0) im.setAttribute('src', src);
      im.setAttribute('data-src', src);
      im.alt = ''; im.draggable = false;
      im.style.cssText = 'max-width:92%;max-height:420px;object-fit:contain;pointer-events:none;display:block;';
      sl.appendChild(im);
      carTrack.appendChild(sl);
    });

    var btnPrev = document.createElement('button');
    btnPrev.type='button'; btnPrev.className='pz-car-btn pz-car-prev'; btnPrev.innerHTML='&#8249;'; btnPrev.setAttribute('aria-label','Önceki');
    var btnNext = document.createElement('button');
    btnNext.type='button'; btnNext.className='pz-car-btn pz-car-next'; btnNext.innerHTML='&#8250;'; btnNext.setAttribute('aria-label','Sonraki');

    var dotBar = document.createElement('div');
    dotBar.className = 'pz-car-dots';
    for(var di = 0; di < N; di++){
      var dotEl = document.createElement('button');
      dotEl.type='button'; dotEl.className='pz-car-dot'+(di===0?' on':'');
      dotBar.appendChild(dotEl);
    }

    carWrap.appendChild(carTrack);
    carWrap.appendChild(btnPrev);
    carWrap.appendChild(btnNext);
    carWrap.appendChild(dotBar);
    mainBox.appendChild(carWrap);
    mainImg.style.display = 'none';

    var cur = 0, autoTimer, dragStartX = 0, dragging = false, dragDx = 0;

    function loadNear(idx){
      carTrack.querySelectorAll('.pz-car-slide').forEach(function(sl, i){
        if(Math.abs(i - idx) <= 1){
          var im = sl.querySelector('img');
          if(im && !im.getAttribute('src') && im.getAttribute('data-src')){
            im.setAttribute('src', im.getAttribute('data-src'));
          }
        }
      });
    }

    function goTo(idx, anim){
      idx = ((idx % N) + N) % N;
      cur = idx;
      loadNear(idx);
      carTrack.style.transition = anim===false ? 'none' : 'transform .42s cubic-bezier(.22,.61,.36,1)';
      carTrack.style.transform = 'translateX(-'+(idx*100)+'%)';
      document.querySelectorAll('.hb-thumb').forEach(function(t, i){ t.classList.toggle('on', i===idx); });
      dotBar.querySelectorAll('.pz-car-dot').forEach(function(dt, i){ dt.classList.toggle('on', i===idx); });
      mainImg.src = carImgs[idx] || mainImg.src;
      clearInterval(autoTimer);
      autoTimer = setInterval(function(){ goTo(cur+1); }, 5000);
    }

    btnPrev.addEventListener('click', function(e){ e.stopPropagation(); goTo(cur-1); });
    btnNext.addEventListener('click', function(e){ e.stopPropagation(); goTo(cur+1); });
    dotBar.querySelectorAll('.pz-car-dot').forEach(function(dt, i){
      dt.addEventListener('click', function(e){ e.stopPropagation(); goTo(i); });
    });

    // Karusel'e tıklama → lightbox
    carWrap.addEventListener('click', function(){
      if(Math.abs(dragDx) > 6) return;
      pzOpenGalleryLb(carImgs, cur);
    });

    // Sürükleme (dokunmatik + fare)
    function dStart(x){ dragStartX=x; dragging=true; dragDx=0; clearInterval(autoTimer); carTrack.style.transition='none'; }
    function dMove(x){ if(!dragging) return; dragDx=x-dragStartX; carTrack.style.transform='translateX(calc(-'+(cur*100)+'% + '+dragDx+'px))'; }
    function dEnd(x){
      if(!dragging) return; dragging=false;
      dragDx = x - dragStartX;
      carTrack.style.transition='transform .42s cubic-bezier(.22,.61,.36,1)';
      if(dragDx < -50) goTo(cur+1);
      else if(dragDx > 50) goTo(cur-1);
      else goTo(cur);
    }
    carTrack.addEventListener('mousedown', function(e){ if(e.button) return; e.preventDefault(); dStart(e.clientX); carTrack.style.cursor='grabbing'; });
    document.addEventListener('mousemove', function(e){ dMove(e.clientX); });
    document.addEventListener('mouseup', function(e){ if(dragging){ dEnd(e.clientX); carTrack.style.cursor=''; } });
    carTrack.addEventListener('touchstart', function(e){ dStart(e.touches[0].clientX); },{passive:true});
    carTrack.addEventListener('touchmove', function(e){ dMove(e.touches[0].clientX); },{passive:true});
    carTrack.addEventListener('touchend', function(e){ dEnd(e.changedTouches[0].clientX); });

    // thumb tıklama → carousel'i senkronize et
    window.hbSetImg = function(el, src){
      document.querySelectorAll('.hb-thumb').forEach(function(t){ t.classList.remove('on'); });
      if(el) el.classList.add('on');
      var idx = carImgs.indexOf(src);
      if(idx >= 0){ goTo(idx); }
      else {
        carImgs[0]=src;
        var fi=carTrack.querySelector('.pz-car-slide:first-child img');
        if(fi){ fi.setAttribute('src',src); fi.setAttribute('data-src',src); }
        goTo(0);
      }
    };

    // varyasyon resim değişikliği hook
    pzCarouselGoTo = function(src){
      var idx = carImgs.indexOf(src);
      if(idx >= 0){ goTo(idx); }
      else {
        carImgs[0]=src;
        var fi=carTrack.querySelector('.pz-car-slide:first-child img');
        if(fi){ fi.setAttribute('src',src); fi.setAttribute('data-src',src); }
        goTo(0);
      }
    };

    goTo(0, false);
  }

  // ── Galeri Lightbox (tüm resimler, kaydırılabilir) ──
  function pzOpenGalleryLb(imgs, startIdx){
    var lb = document.getElementById('pzGalleryLb');
    if(!lb) lb = pzBuildGalleryLb(imgs);
    lb._goTo(startIdx, false);
    lb.classList.add('on');
    document.body.style.overflow = 'hidden';
  }

  function pzBuildGalleryLb(imgs){
    var N = imgs.length;
    var lbIdx = 0;

    var lb = document.createElement('div');
    lb.id = 'pzGalleryLb'; lb.className = 'pz-glb';

    var tHtml = '';
    imgs.forEach(function(src){ tHtml += '<div class="pz-glb-slide"><img data-src="'+src.replace(/"/g,'&quot;')+'" alt=""></div>'; });
    var dHtml = '';
    if(N>1) imgs.forEach(function(_,i){ dHtml += '<button type="button" class="pz-glb-dot'+(i===0?' on':'')+'"></button>'; });

    lb.innerHTML =
      '<button class="pz-glb-close" type="button" aria-label="Kapat">&#10005;</button>'+
      (N>1 ? '<button class="pz-glb-btn pz-glb-prev" type="button" aria-label="Önceki">&#8249;</button><button class="pz-glb-btn pz-glb-next" type="button" aria-label="Sonraki">&#8250;</button>' : '')+
      '<div class="pz-glb-stage"><div class="pz-glb-track">'+tHtml+'</div></div>'+
      '<div class="pz-glb-foot"><span class="pz-glb-counter">1 / '+N+'</span></div>'+
      (N>1 ? '<div class="pz-glb-dots">'+dHtml+'</div>' : '');

    document.body.appendChild(lb);

    var lbTrack = lb.querySelector('.pz-glb-track');
    var lbSX=0, lbDrag=false, lbDx=0;

    function closeLb(){ lb.classList.remove('on'); document.body.style.overflow=''; }

    function goToLb(idx, anim){
      lbIdx = ((idx%N)+N)%N;
      lbTrack.querySelectorAll('.pz-glb-slide img').forEach(function(im, i){
        if(Math.abs(i-lbIdx)<=1){
          var ds=im.getAttribute('data-src');
          if(ds && !im.src) im.src=ds;
        }
      });
      lbTrack.style.transition = anim===false ? 'none' : 'transform .35s cubic-bezier(.22,.61,.36,1)';
      lbTrack.style.transform = 'translateX(-'+(lbIdx*100)+'%)';
      lb.querySelectorAll('.pz-glb-dot').forEach(function(dt,i){ dt.classList.toggle('on',i===lbIdx); });
      var cnt=lb.querySelector('.pz-glb-counter');
      if(cnt) cnt.textContent=(lbIdx+1)+' / '+N;
    }

    lb._goTo = goToLb;
    lb.querySelector('.pz-glb-close').addEventListener('click', closeLb);
    lb.addEventListener('click', function(e){ if(e.target===lb) closeLb(); });
    document.addEventListener('keydown', function(e){
      if(!lb.classList.contains('on')) return;
      if(e.key==='Escape') closeLb();
      if(e.key==='ArrowLeft' && N>1) goToLb(lbIdx-1,true);
      if(e.key==='ArrowRight' && N>1) goToLb(lbIdx+1,true);
    });
    var pB=lb.querySelector('.pz-glb-prev'), nB=lb.querySelector('.pz-glb-next');
    if(pB) pB.addEventListener('click', function(e){ e.stopPropagation(); goToLb(lbIdx-1,true); });
    if(nB) nB.addEventListener('click', function(e){ e.stopPropagation(); goToLb(lbIdx+1,true); });
    lb.querySelectorAll('.pz-glb-dot').forEach(function(dt,i){ dt.addEventListener('click', function(e){ e.stopPropagation(); goToLb(i,true); }); });

    var stage=lb.querySelector('.pz-glb-stage');
    function lbDS(x){ lbSX=x; lbDrag=true; lbDx=0; lbTrack.style.transition='none'; }
    function lbDM(x){ if(!lbDrag) return; lbDx=x-lbSX; lbTrack.style.transform='translateX(calc(-'+(lbIdx*100)+'% + '+lbDx+'px))'; }
    function lbDE(x){ if(!lbDrag) return; lbDrag=false; lbDx=x-lbSX; lbTrack.style.transition='transform .35s cubic-bezier(.22,.61,.36,1)'; if(lbDx<-50) goToLb(lbIdx+1,true); else if(lbDx>50) goToLb(lbIdx-1,true); else goToLb(lbIdx,true); }
    stage.addEventListener('touchstart', function(e){ lbDS(e.touches[0].clientX); },{passive:true});
    stage.addEventListener('touchmove', function(e){ lbDM(e.touches[0].clientX); },{passive:true});
    stage.addEventListener('touchend', function(e){ lbDE(e.changedTouches[0].clientX); });
    stage.addEventListener('mousedown', function(e){ if(e.button) return; e.preventDefault(); lbDS(e.clientX); stage.style.cursor='grabbing'; });
    document.addEventListener('mousemove', function(e){ lbDM(e.clientX); });
    document.addEventListener('mouseup', function(e){ if(lbDrag){ lbDE(e.clientX); stage.style.cursor='grab'; } });

    return lb;
  }

  function pzInitSelected(){
    // Variable üründe ilk seçili (.on) renk/beden butonlarını JS state'e al
    if(!document.getElementById('pzVariationId')) return;
    document.querySelectorAll('.hb-color.on[data-attr], .hb-var.on[data-attr]').forEach(function(btn){
      var attr = btn.getAttribute('data-attr');
      var val = btn.getAttribute('data-value');
      if(attr && val) pzSelected[attr] = val;
    });
    pzApplyVariation();
    // Sayfa yüklenince: stokta olmayan seçenekleri işaretle
    if(typeof pzMarkUnavailableOptions === 'function') pzMarkUnavailableOptions();
  }
  function pzInitFormGuard(){
    var form = document.querySelector('.hb-cart-form');
    var vid = document.getElementById('pzVariationId');
    if(!form || !vid) return; // basit ürün — guard yok, normal çalışır
    form.addEventListener('submit', function(e){
      // Variable üründe varyasyon seçilmemişse engelle ve net uyar
      if(!vid.value){
        // Son bir kez eşleşme dene
        pzApplyVariation();
        if(!vid.value){
          e.preventDefault();
          var warn = document.getElementById('pzVarWarning');
          if(warn){
            warn.style.display='block';
            warn.textContent='Lütfen renk/beden gibi tüm seçenekleri belirleyin.';
          } else {
            alert('Lütfen ürün seçeneklerini (renk/beden) belirleyin.');
          }
          var vb = document.querySelector('.hb-variant-block');
          if(vb) vb.scrollIntoView({behavior:'smooth', block:'center'});
          return false;
        }
      }
    });
  }
  function pzInitAll(){
    try { pzLoadVarData(); } catch(e){ console.warn('pz var data:', e); }
    try { pzInitSelected(); } catch(e){ console.warn('pz selected:', e); }
    try { pzInitFormGuard(); } catch(e){ console.warn('pz form guard:', e); }
    try { pzInitThumbScroll(); } catch(e){ console.warn('pz thumb scroll:', e); }
    try { pzInitClickZoom(); } catch(e){ console.warn('pz click zoom:', e); }
  }
  if(document.readyState !== 'loading'){ pzInitAll(); }
  else { document.addEventListener('DOMContentLoaded', pzInitAll); }
})();


/* ═══════════════════════════════════════════════
   ÜRÜN SORU & CEVAP (dinamik soru gönderme + arama)
═══════════════════════════════════════════════ */
function pzSubmitQuestion(e){
  e.preventDefault();
  var pid = document.getElementById('qaProductId');
  var nameEl = document.getElementById('qaName');
  var qEl = document.getElementById('qaQuestion');
  var btn = document.getElementById('qaSubmitBtn');
  var note = document.getElementById('qaFormNote');
  if(!pid || !qEl) return false;

  var question = (qEl.value || '').trim();
  var name = (nameEl ? nameEl.value : '').trim();
  if(question.length < 5){
    if(note){ note.style.display='block'; note.style.color='#d32f2f'; note.textContent='Lütfen en az 5 karakterlik bir soru yazın.'; }
    return false;
  }

  var data = (typeof bazario_ajax !== 'undefined') ? bazario_ajax : null;
  if(!data){ if(note){note.style.display='block';note.textContent='Bağlantı hatası.';} return false; }

  if(btn){ btn.disabled = true; btn.textContent = 'Gönderiliyor...'; }

  var body = new URLSearchParams();
  body.append('action', 'pz_submit_question');
  body.append('nonce', data.nonce);
  body.append('product_id', pid.value);
  body.append('name', name);
  body.append('question', question);

  fetch(data.url, { method:'POST', body:body, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if(btn){ btn.disabled = false; btn.textContent = 'Soru Sor'; }
      if(note){
        note.style.display='block';
        note.style.color = res.success ? 'var(--green)' : '#d32f2f';
        note.textContent = (res.data && res.data.message) ? res.data.message : (res.success ? 'Sorunuz alındı!' : 'Bir hata oluştu.');
      }
      if(res.success){ qEl.value=''; if(nameEl) nameEl.value=''; }
    })
    .catch(function(){
      if(btn){ btn.disabled = false; btn.textContent = 'Soru Sor'; }
      if(note){ note.style.display='block'; note.style.color='#d32f2f'; note.textContent='Bağlantı hatası, tekrar deneyin.'; }
    });
  return false;
}
window.pzSubmitQuestion = pzSubmitQuestion;

function pzFilterQuestions(val){
  var q = (val||'').toLowerCase().trim();
  var list = document.getElementById('qaList');
  if(!list) return;
  var items = list.querySelectorAll('.qa-item');
  var anyVisible = false;
  items.forEach(function(it){
    var txt = it.getAttribute('data-q') || '';
    var show = (q==='' || txt.indexOf(q)!==-1);
    it.style.display = show ? '' : 'none';
    if(show) anyVisible = true;
  });
}
window.pzFilterQuestions = pzFilterQuestions;


/* Satıcı/yönetici soruya cevap verme */
function pzSubmitAnswer(btn, commentId){
  var form = btn.closest('.qa-answer-form');
  if(!form) return;
  var input = form.querySelector('.qa-answer-input');
  var answer = (input ? input.value : '').trim();
  if(answer.length < 2){ input && input.focus(); return; }

  var data = (typeof bazario_ajax !== 'undefined') ? bazario_ajax : null;
  if(!data) return;

  var orig = btn.textContent;
  btn.disabled = true; btn.textContent = 'Kaydediliyor...';

  var body = new URLSearchParams();
  body.append('action', 'pz_submit_answer');
  body.append('nonce', data.nonce);
  body.append('comment_id', commentId);
  body.append('answer', answer);

  fetch(data.url, { method:'POST', body:body, credentials:'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(res){
      btn.disabled = false;
      if(res.success){
        // "henüz yanıtlanmadı" notunu cevapla değiştir
        var pending = document.getElementById('qaPending-'+commentId);
        if(pending){
          pending.classList.remove('qa-a-pending');
          pending.innerHTML = '<span class="qa-ico qa-ico-a">C</span><span class="qa-a-text">'+answer.replace(/</g,'&lt;')+'</span>';
        } else {
          // zaten cevap vardı — mevcut cevap metnini güncelle
          var item = form.closest('.qa-item');
          var aText = item ? item.querySelector('.qa-a .qa-a-text') : null;
          if(aText) aText.textContent = answer;
        }
        btn.textContent = '✓ Kaydedildi';
        setTimeout(function(){ btn.textContent = 'Cevabı Güncelle'; }, 2000);
      } else {
        btn.textContent = orig;
        alert((res.data && res.data.message) ? res.data.message : 'Cevap kaydedilemedi.');
      }
    })
    .catch(function(){ btn.disabled = false; btn.textContent = orig; alert('Bağlantı hatası.'); });
}
window.pzSubmitAnswer = pzSubmitAnswer;

/* ═══════════════════════════════════════════════
   DEĞERLENDİRME FORMU — yıldız + submit
═══════════════════════════════════════════════ */
(function(){
  var hints = ['','Çok Kötü','Kötü','Orta','İyi','Mükemmel'];
  var starsWrap = document.getElementById('pzRevStars');
  var hint = document.getElementById('pzRevStarHint');
  if(starsWrap && hint){
    starsWrap.querySelectorAll('label').forEach(function(lbl){
      lbl.addEventListener('mouseenter', function(){
        var val = lbl.getAttribute('for').replace('pzStar','');
        hint.textContent = hints[+val] || '';
      });
      lbl.addEventListener('mouseleave', function(){
        var checked = starsWrap.querySelector('input:checked');
        hint.textContent = checked ? (hints[+checked.value] || '') : '';
      });
    });
    starsWrap.querySelectorAll('input').forEach(function(inp){
      inp.addEventListener('change', function(){
        hint.textContent = hints[+inp.value] || '';
      });
    });
  }
  var form = document.getElementById('pzReviewForm');
  if(!form) return;
  form.addEventListener('submit', function(e){
    e.preventDefault();
    var rating = form.querySelector('input[name="rating"]:checked');
    var comment = document.getElementById('pzRevComment');
    var msg = document.getElementById('pzRevMsg');
    msg.style.display = 'none'; msg.className = 'pz-rev-msg';
    if(!rating){ msg.className='pz-rev-msg err'; msg.textContent='Lütfen bir puan seçin.'; msg.style.display='block'; return; }
    if(!comment || comment.value.trim().length < 10){ msg.className='pz-rev-msg err'; msg.textContent='Yorumunuz en az 10 karakter olmalı.'; msg.style.display='block'; return; }
    var btn = form.querySelector('.pz-rev-submit');
    btn.disabled = true; btn.textContent = 'Gönderiliyor…';
    var fd = new FormData(form);
    fetch(form.action, { method:'POST', body:fd, credentials:'same-origin' })
      .then(function(r){
        if(r.redirected || r.ok){
          msg.className='pz-rev-msg ok';
          msg.textContent='✓ Değerlendirmeniz alındı, onay bekliyor.';
          msg.style.display='block';
          form.reset();
          if(hint) hint.textContent='';
          btn.textContent='Gönderildi';
        } else {
          return r.text().then(function(t){
            var m = t.match(/<p[^>]*>([\s\S]*?)<\/p>/);
            throw new Error(m ? m[1].replace(/<[^>]+>/g,'') : 'Hata oluştu.');
          });
        }
      })
      .catch(function(err){
        btn.disabled=false; btn.textContent='Gönder';
        msg.className='pz-rev-msg err';
        msg.textContent='✗ ' + (err.message||'Bağlantı hatası');
        msg.style.display='block';
      });
  });
})();

/* ═══════════════════════════════════════════════
   MENÜ alt kategori KAYAR PENCERE (Hepsiburada tarzı)
   Üst menü (ci-drop), Tüm Kategoriler (mega), Sol menü (vcat-fly)
   Bir kolonda çok alt link varsa → sabit yükseklik + dikey scroll
   Panelde çok kolon varsa → panel grid yatay/dikey scroll
═══════════════════════════════════════════════ */
(function(){
  var COL_LINK_LIMIT = 6;   // bir kolonda kaydırmasız gösterilecek link sayısı
  var COL_MAX_HEIGHT = 200; // px — bu yüksekliği aşan kolon kaydırılabilir olur

  function setupColumn(col, linkSelector){
    if(col._scrollInit) return; col._scrollInit = true;
    var links = col.querySelectorAll(linkSelector);
    if(links.length > COL_LINK_LIMIT){
      // kolonu kayar pencereye çevir: linkleri saran bir scroll alanı oluştur
      col.classList.add('pz-col-scroll');
      // başlık (title) scroll dışında kalsın, sadece linkler kaysın
      var title = col.querySelector('.ci-drop-title, .mega-col-title, .vcat-fly-title');
      var box = document.createElement('div');
      box.className = 'pz-col-linkbox';
      // başlıktan sonraki tüm linkleri box'a taşı
      var toMove = [];
      links.forEach(function(l){ toMove.push(l); });
      toMove.forEach(function(l){ box.appendChild(l); });
      if(title && title.nextSibling){ col.insertBefore(box, title.nextSibling); }
      else { col.appendChild(box); }
    }
  }

  function setupPanel(panel, colSelector, linkSelector){
    if(!panel || panel._panelScrollInit) return; panel._panelScrollInit = true;
    var cols = panel.querySelectorAll(colSelector);
    cols.forEach(function(col){ setupColumn(col, linkSelector); });
  }

  window.pzInitMenuMore = function(panelEl){
    if(!panelEl) return;
    if(panelEl.querySelector('.ci-drop-col')) setupPanel(panelEl, '.ci-drop-col', '.ci-drop-link');
    if(panelEl.querySelector('.mega-col')) setupPanel(panelEl, '.mega-col', '.mega-col-link');
    if(panelEl.querySelector('.vcat-fly-col')) setupPanel(panelEl, '.vcat-fly-col', '.vcat-fly-link');
  };

  function initAllMenus(){
    document.querySelectorAll('.ci-drop, .mega-cat-panel, .vcat-fly').forEach(function(p){
      try { window.pzInitMenuMore(p); } catch(e){}
    });
  }
  if(document.readyState !== 'loading'){ initAllMenus(); }
  else { document.addEventListener('DOMContentLoaded', initAllMenus); }
})();


/* Shop sidebar: kayar kategori listesinde aktif kategoriye otomatik kaydır */
(function(){
  function scrollToActiveCat(){
    var list = document.querySelector('.shop-cat-list');
    if(!list) return;
    var active = list.querySelector('li.on');
    if(active && active.offsetTop > list.clientHeight - active.offsetHeight){
      list.scrollTop = active.offsetTop - (list.clientHeight / 2);
    }
  }
  if(document.readyState !== 'loading'){ scrollToActiveCat(); }
  else { document.addEventListener('DOMContentLoaded', scrollToActiveCat); }
})();


/* ═══ MOBİL MENÜ (DRAWER) ═══ */
window.pzToggleMobileMenu = function(){
  var menu = document.getElementById('mobileMenu');
  var ov = document.getElementById('mobileMenuOverlay');
  if(!menu) return;
  var isOpen = menu.classList.contains('open');
  if(isOpen){ window.pzCloseMobileMenu(); }
  else {
    menu.classList.add('open');
    if(ov) ov.classList.add('open');
    document.body.style.overflow='hidden'; // arka plan kaymasın
  }
};
window.pzCloseMobileMenu = function(){
  var menu = document.getElementById('mobileMenu');
  var ov = document.getElementById('mobileMenuOverlay');
  if(menu) menu.classList.remove('open');
  if(ov) ov.classList.remove('open');
  document.body.style.overflow='';
};


/* ═══ MOBİL FİLTRE AÇ/KAPA ═══ */
window.pzToggleFilters = function(){
  var sidebar = document.querySelector('.shop-sidebar');
  var btn = document.getElementById('mobFilterBtn');
  if(!sidebar) return;
  var isOpen = sidebar.classList.toggle('mob-open');
  if(btn) btn.classList.toggle('on', isOpen);
};


/* ═══ MOBİL MENÜ ALT KATEGORİ AÇ/KAPA (accordion) ═══ */
window.pzToggleSubmenu = function(btn){
  if(!btn) return;
  var li = btn.closest('.mm-cat-item');
  if(!li) return;
  li.classList.toggle('open');
};


/* ═══ MOBİL MENÜ 3. SEVİYE ALT KATEGORİ AÇ/KAPA ═══ */
window.pzToggleSubmenu2 = function(btn){
  if(!btn) return;
  var li = btn.closest('.mm-sub-item');
  if(!li) return;
  li.classList.toggle('open');
};


/* ═══ TAM EKRAN KATEGORİ SAYFASI (alt nav kategori butonu) ═══ */
window.pzOpenCatPage = function(){
  var cp = document.getElementById('catPage');
  if(!cp) return;
  cp.classList.add('open');
  document.body.style.overflow='hidden';
  // Geri tuşuyla kapanması için history'ye state ekle
  try { history.pushState({pzCatPage:true}, ''); } catch(e){}
};
window.pzCloseCatPage = function(fromPop){
  var cp = document.getElementById('catPage');
  if(!cp) return;
  cp.classList.remove('open');
  document.body.style.overflow='';
  // Eğer programatik kapatma ise ve history state varsa geri al
  if(!fromPop){
    try { if(history.state && history.state.pzCatPage){ history.back(); } } catch(e){}
  }
};
// Telefon geri tuşu / tarayıcı geri → kategori sayfasını kapat
window.addEventListener('popstate', function(){
  var cp = document.getElementById('catPage');
  if(cp && cp.classList.contains('open')){
    window.pzCloseCatPage(true);
  }
});


/* ═══ MOBİL VARYASYON TAŞIMA — masaüstünde hb-info içinde, mobilde buybox üstüne kopyala ═══ */
(function(){
  function moveVariations(){
    return; // devre dışı — CSS ile hallediliyor
    if (window.innerWidth > 860) return;
    var blocks = document.querySelectorAll('.hb-info .hb-variant-block');
    if (!blocks.length) return;
    var buybox = document.querySelector('.hb-buybox');
    if (!buybox) return;
    // Daha önce taşıma yapıldı mı?
    if (document.getElementById('pz-mobile-variants')) return;
    var wrap = document.createElement('div');
    wrap.id = 'pz-mobile-variants';
    wrap.className = 'pz-mobile-variants';
    // hb-info'daki orijinalleri klonla (orijinaller masaüstü için kalsın)
    blocks.forEach(function(b){
      var clone = b.cloneNode(true);
      wrap.appendChild(clone);
    });
    // Buybox'ın hemen ÖNÜNE ekle
    buybox.parentNode.insertBefore(wrap, buybox);
    // Klonlanmış butonlara onclick tekrar bağla (cloneNode inline onclick'i korur ama emin olalım)
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', moveVariations);
  } else {
    moveVariations();
  }
})();


/* ═══ SON GEZİLEN ÜRÜNLER — localStorage + AJAX render ═══ */
(function(){
  var STORAGE_KEY = 'pz_recently_viewed';
  var MAX_ITEMS = 12;
  var ajaxRetries = 0;
  var MAX_RETRIES = 8;

  function getRecent(){
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return [];
      var arr = JSON.parse(raw);
      return Array.isArray(arr) ? arr : [];
    } catch(e) { return []; }
  }
  function setRecent(arr){
    try { localStorage.setItem(STORAGE_KEY, JSON.stringify(arr.slice(0, MAX_ITEMS))); } catch(e){}
  }
  function addId(id){
    id = parseInt(id, 10);
    if (!id || id < 1) return;
    var arr = getRecent().filter(function(x){ return x !== id; });
    arr.unshift(id);
    setRecent(arr);
  }
  function addCurrent(){
    // Ürün detay sayfasında: window.pzCurrentProductId
    if (window.pzCurrentProductId) addId(window.pzCurrentProductId);
  }
  // Anasayfa/kategori/sepet sayfalarındaki ürün kartı tıklamalarını da yakala
  function bindCardClicks(){
    document.addEventListener('click', function(e){
      // Kart üstündeki herhangi bir yere tıklama
      var card = e.target.closest('.pcard, .bazario-product-card, .product, [data-product-id]');
      if (!card) return;
      var pid = card.getAttribute('data-product-id');
      if (!pid) {
        var m = (card.className || '').match(/post-(\d+)/);
        if (m) pid = m[1];
      }
      if (pid) addId(pid);
    }, true);
  }
  function loadAndRender(){
    // Sayfadaki tüm "son gezilen" wrapper'larını bul (eski ID + yeni data attr)
    var wraps = document.querySelectorAll('[data-rv-wrap], #recentlyViewedWrap');
    if (!wraps.length) return;
    var allIds = getRecent();
    if (!allIds.length) return;
    if (typeof bazario_ajax === 'undefined' || !bazario_ajax.ajax_url) {
      if (ajaxRetries++ < MAX_RETRIES) {
        setTimeout(loadAndRender, 400);
      }
      return;
    }
    wraps.forEach(function(wrap){
      var excludeId = parseInt(wrap.getAttribute('data-rv-exclude') || '0', 10) || window.pzCurrentProductId || 0;
      var ids = allIds.filter(function(id){ return id !== excludeId; });
      if (!ids.length) { wrap.style.display='none'; return; }
      var row = wrap.querySelector('[data-rv-row], #recentProductsRow');
      if (!row) return;
      var fd = new FormData();
      fd.append('action', 'pz_recent_products');
      fd.append('nonce', bazario_ajax.nonce);
      ids.forEach(function(id){ fd.append('ids[]', id); });
      fetch(bazario_ajax.ajax_url, { method:'POST', body: fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res && res.success && res.data && res.data.html) {
            row.innerHTML = res.data.html;
            wrap.style.display = 'block';
            if (typeof bindCardEvents === 'function') bindCardEvents();
          } else {
            wrap.style.display = 'none';
          }
        })
        .catch(function(){ wrap.style.display = 'none'; });
    });
  }
  // Temizle butonu
  window.pzClearRecent = function(){
    if (!confirm('Son gezdiğin ürünler temizlensin mi?')) return;
    try { localStorage.removeItem(STORAGE_KEY); } catch(e){}
    document.querySelectorAll('[data-rv-wrap], #recentlyViewedWrap').forEach(function(w){ w.style.display='none'; });
  };

  function init(){
    addCurrent();
    bindCardClicks();
    loadAndRender();
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();


/* ═══════════════════════════════════════════════
   ✨ AKILLI ARAMA (AI-style autocomplete)
   - Anlık öneri, Türkçe karakter toleransı
   - Kategori/marka/ürün/SKU, klavye nav, geçmiş
═══════════════════════════════════════════════ */
(function(){
  var HISTORY_KEY = 'pz_search_history';
  var MAX_HISTORY = 8;

  /* AJAX URL — wp_localize_script, ajaxurl, veya fallback */
  function getAjaxUrl(){
    if (window.bazario_ajax && window.bazario_ajax.ajax_url) return window.bazario_ajax.ajax_url;
    if (typeof ajaxurl !== 'undefined') return ajaxurl;
    return '/wp-admin/admin-ajax.php';
  }
  function getHomeUrl(){
    if (window.pzHomeUrl) return window.pzHomeUrl;
    if (window.bazario_ajax && window.bazario_ajax.home_url) return window.bazario_ajax.home_url;
    return '/';
  }

  function getHistory(){
    try { var raw = localStorage.getItem(HISTORY_KEY); return raw ? JSON.parse(raw) : []; }
    catch(e){ return []; }
  }
  function addHistory(q){
    if (!q || q.length < 2) return;
    var arr = getHistory().filter(function(x){ return x.toLowerCase() !== q.toLowerCase(); });
    arr.unshift(q);
    try { localStorage.setItem(HISTORY_KEY, JSON.stringify(arr.slice(0, MAX_HISTORY))); } catch(e){}
  }
  function escapeHTML(s){ return (s+'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function highlight(text, q){
    if (!q) return escapeHTML(text);
    var re = new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');
    return escapeHTML(text).replace(re,'<mark>$1</mark>');
  }

  function renderEmpty(dd){
    var hist = getHistory(); var homeUrl = getHomeUrl();
    var html = '';
    if (hist.length) {
      html += '<div class="pz-ai-section"><div class="pz-ai-sect-title">🕐 Son Aramalar</div><div class="pz-ai-chips">';
      hist.forEach(function(h){
        html += '<a href="'+homeUrl+'?s='+encodeURIComponent(h)+'&post_type=product" class="pz-ai-chip">'+escapeHTML(h)+'</a>';
      });
      html += '</div></div>';
    }
    html += '<div class="pz-ai-section"><div class="pz-ai-sect-title">🔥 Popüler Aramalar</div><div class="pz-ai-chips">';
    ['Ayakkabı','Çanta','T-shirt','Telefon','Kulaklık','Saat','Parfüm','Elbise'].forEach(function(p){
      html += '<a href="'+homeUrl+'?s='+encodeURIComponent(p)+'&post_type=product" class="pz-ai-chip pz-ai-pop">'+p+'</a>';
    });
    html += '</div></div>';
    dd.innerHTML = html;
  }

  function renderResults(dd, data, q){
    var homeUrl = getHomeUrl(); var html = ''; var hasResult = false;
    if (data.categories && data.categories.length) {
      hasResult = true;
      html += '<div class="pz-ai-section"><div class="pz-ai-sect-title">📂 Kategoriler</div>';
      data.categories.forEach(function(c){
        html += '<a class="pz-ai-item pz-ai-cat" href="'+escapeHTML(c.url)+'"><span class="pz-ai-cat-ico">📂</span><span>'+highlight(c.name,q)+'</span></a>';
      });
      html += '</div>';
    }
    if (data.brands && data.brands.length) {
      hasResult = true;
      html += '<div class="pz-ai-section"><div class="pz-ai-sect-title">🏷️ Markalar</div>';
      data.brands.forEach(function(b){
        html += '<a class="pz-ai-item pz-ai-brand" href="'+escapeHTML(b.url)+'"><span class="pz-ai-cat-ico">🏷️</span><span>'+highlight(b.name,q)+'</span></a>';
      });
      html += '</div>';
    }
    if (data.products && data.products.length) {
      hasResult = true;
      html += '<div class="pz-ai-section"><div class="pz-ai-sect-title">🛒 Ürünler</div>';
      data.products.forEach(function(p){
        var skuBadge = p.sku ? '<span class="pz-ai-sku">SKU: '+escapeHTML(p.sku)+'</span>' : '';
        html += '<a class="pz-ai-item pz-ai-prod" href="'+escapeHTML(p.url)+'">'+
          '<img class="pz-ai-prod-img" src="'+escapeHTML(p.img)+'" alt="" loading="lazy" onerror="this.style.display=\'none\'">'+
          '<div class="pz-ai-prod-info">'+
            '<div class="pz-ai-prod-title">'+highlight(p.title,q)+'</div>'+
            '<div class="pz-ai-prod-price">'+p.price+skuBadge+'</div>'+
          '</div></a>';
      });
      html += '</div>';
    }
    if (!hasResult) {
      html += '<div class="pz-ai-empty">😔 "<b>'+escapeHTML(q)+'</b>" için sonuç bulunamadı.<br><small>Farklı bir kelime veya SKU deneyin.</small></div>';
    } else {
      html += '<a class="pz-ai-all" href="'+homeUrl+'?s='+encodeURIComponent(q)+'&post_type=product">🔎 "<b>'+escapeHTML(q)+'</b>" için tüm sonuçları gör →</a>';
    }
    dd.innerHTML = html;
  }

  function doSearch(dd, q, lastQ){
    var ajaxUrl = getAjaxUrl();
    var fd = new FormData();
    fd.append('action','pz_ai_search');
    fd.append('q', q);
    dd.classList.add('pz-loading');
    fetch(ajaxUrl, {method:'POST', body:fd, credentials:'same-origin'})
      .then(function(r){
        if (!r.ok) throw new Error('HTTP '+r.status);
        return r.json();
      })
      .then(function(res){
        dd.classList.remove('pz-loading');
        if (res && res.success && res.data) {
          renderResults(dd, res.data, q);
          dd.classList.add('pz-open');
        } else {
          renderEmpty(dd); dd.classList.add('pz-open');
        }
      })
      .catch(function(){
        dd.classList.remove('pz-loading');
        dd.innerHTML = '<div class="pz-ai-empty">⚠️ Arama bağlantısı kurulamadı. Lütfen tekrar deneyin.</div>';
        dd.classList.add('pz-open');
      });
  }

  function bindForm(form){
    if (form._aiBound) return; form._aiBound = true;
    var input = form.querySelector('.pz-ai-input');
    var dd    = form.querySelector('.pz-ai-dropdown');
    if (!input || !dd) return;

    /* per-form state: birden fazla arama formu birbirini ezmez */
    var debounceTimer = null;
    var activeIndex   = -1;
    var lastQuery     = '';

    function updateActive(items){
      items.forEach(function(it,i){ it.classList.toggle('pz-ai-active', i===activeIndex); });
      if (activeIndex>=0 && items[activeIndex]) items[activeIndex].scrollIntoView({block:'nearest'});
    }

    input.addEventListener('focus', function(){
      var q = input.value.trim();
      if (q.length < 2) { renderEmpty(dd); dd.classList.add('pz-open'); }
      else if (q !== lastQuery) { lastQuery=q; doSearch(dd, q); }
      else { dd.classList.add('pz-open'); }
    });

    input.addEventListener('input', function(){
      var q = input.value.trim();
      lastQuery = q; activeIndex = -1;
      clearTimeout(debounceTimer);
      if (q.length < 2) {
        debounceTimer = setTimeout(function(){ renderEmpty(dd); dd.classList.add('pz-open'); }, 80);
        return;
      }
      debounceTimer = setTimeout(function(){ doSearch(dd, q); }, 240);
    });

    input.addEventListener('keydown', function(e){
      var items = dd.querySelectorAll('.pz-ai-item, .pz-ai-chip, .pz-ai-all');
      if (e.key==='ArrowDown'){ e.preventDefault(); activeIndex=Math.min(activeIndex+1,items.length-1); updateActive(items); }
      else if (e.key==='ArrowUp'){ e.preventDefault(); activeIndex=Math.max(activeIndex-1,-1); updateActive(items); }
      else if (e.key==='Enter'){
        if (activeIndex>=0 && items[activeIndex]){ e.preventDefault(); window.location.href=items[activeIndex].getAttribute('href'); }
        else { addHistory(input.value.trim()); }
      }
      else if (e.key==='Escape'){ dd.classList.remove('pz-open'); input.blur(); }
    });

    if (form.tagName==='FORM') form.addEventListener('submit', function(){ addHistory(input.value.trim()); });
    document.addEventListener('click', function(e){ if (!form.contains(e.target)) dd.classList.remove('pz-open'); });
  }

  function init(){
    document.querySelectorAll('form.pz-ai-search, div.pz-ai-search').forEach(bindForm);
  }
  if (document.readyState==='loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();


/* ═══ SATICI BAŞVURU - E-POSTA DOĞRULAMA KOD GÖNDERME ═══ */
window.pzSendVerifyCode = function(btn){
  var input = document.getElementById('pz-email-input');
  var codeRow = document.getElementById('pz-code-row');
  if (!input || !input.value || !input.value.includes('@')) {
    alert('Önce geçerli bir e-posta adresi girin.');
    if (input) input.focus();
    return;
  }
  // AJAX URL'i çoklu yedekle bul
  var ajaxUrl = '';
  if (typeof bazario_ajax !== 'undefined' && bazario_ajax.ajax_url) {
    ajaxUrl = bazario_ajax.ajax_url;
  } else if (typeof ajaxurl !== 'undefined') {
    ajaxUrl = ajaxurl;
  } else {
    // Son çare: site URL'inden tahmin et
    ajaxUrl = window.location.origin + '/wp-admin/admin-ajax.php';
  }

  btn.disabled = true;
  var origText = btn.textContent;
  btn.textContent = '⏳ Gönderiliyor...';

  var fd = new FormData();
  fd.append('action', 'pz_send_email_code');
  fd.append('email', input.value);

  fetch(ajaxUrl, { method:'POST', body: fd, credentials:'same-origin' })
    .then(function(r){
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text().then(function(txt){
        try { return JSON.parse(txt); }
        catch(e){
          console.error('Sunucu yanıtı JSON değil:', txt);
          throw new Error('Sunucu yanıtı bozuk. (HTTP ' + r.status + ')');
        }
      });
    })
    .then(function(res){
      btn.disabled = false;
      if (res && res.success) {
        btn.textContent = '✓ Gönderildi';
        btn.style.background = '#1a7a4a';
        if (codeRow) codeRow.style.display = 'flex';
        alert((res.data && res.data.message) || 'Kod gönderildi, e-postanı kontrol et.');
        setTimeout(function(){
          btn.textContent = '🔁 Yeniden Gönder';
          btn.style.background = '';
        }, 60000);
      } else {
        btn.textContent = origText;
        alert((res && res.data && res.data.message) || 'Kod gönderilemedi. Sayfayı yenileyip tekrar deneyin.');
      }
    })
    .catch(function(err){
      btn.disabled = false;
      btn.textContent = origText;
      console.error('pzSendVerifyCode hatası:', err);
      alert('Bağlantı hatası: ' + (err && err.message ? err.message : 'bilinmeyen') + '\n\nSayfayı yenileyip tekrar deneyin.');
    });
};

/* ── Dinamik kargo sayacı — vendor dispatch_days destekli ── */
(function(){
  var fixedHols = ['1-1','4-23','5-1','5-19','7-15','8-30','10-29'];
  var lunarHols = {
    2025: ['3-28','3-29','3-30','3-31','4-1','6-5','6-6','6-7','6-8','6-9'],
    2026: ['3-19','3-20','3-21','3-22','5-26','5-27','5-28','5-29','5-30'],
    2027: ['3-9','3-10','3-11','5-16','5-17','5-18','5-19','5-20']
  };

  // Türkiye UTC+3 sabit (DST yok). Date.now()+3h ile UTC metodları kullanılır.
  function trNow() {
    return new Date(Date.now() + 3 * 3600 * 1000);
  }

  function isHol(d) {
    var key = (d.getUTCMonth() + 1) + '-' + d.getUTCDate();
    if (fixedHols.indexOf(key) !== -1) return true;
    var yh = lunarHols[d.getUTCFullYear()];
    return yh ? yh.indexOf(key) !== -1 : false;
  }

  function isWorkDay(d) {
    var w = d.getUTCDay();
    return w !== 0 && w !== 6 && !isHol(d);
  }

  function dayName(d) {
    return ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'][d.getUTCDay()];
  }

  function startOfDay(d) {
    return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
  }

  /* Bugünden itibaren n iş günü sonrasını döndürür (n=0: bugün/sonraki iş günü) */
  function nthWorkDay(from, n) {
    var d = startOfDay(from);
    if (n === 0) {
      if (isWorkDay(d)) return d;
      n = 1;
    }
    var count = 0;
    d = new Date(d.getTime() + 86400000);
    for (var safety = 0; safety < 60; safety++) {
      if (isWorkDay(d)) { count++; if (count >= n) return d; }
      d = new Date(d.getTime() + 86400000);
    }
    return d;
  }

  /* dispatch: vendor'un kargoya verme süresi (iş günü) */
  function computeText(dispatch, tr) {
    var todayStart = startOfDay(tr);
    var cutoff = new Date(todayStart.getTime() + 14 * 3600 * 1000); // 14:00 TR saati

    if (dispatch === 0) {
      if (isWorkDay(todayStart) && tr.getTime() < cutoff.getTime()) {
        var mins = Math.ceil((cutoff.getTime() - tr.getTime()) / 60000);
        var h = Math.floor(mins / 60), m = mins % 60;
        var t = (h > 0 ? h + ' saat ' : '') + (m > 0 ? m + ' dakika' : '');
        return t.trim() + ' içinde sipariş verirseniz bugün kargoda';
      }
      // 14:00 geçtiyse veya iş günü değilse sonraki iş gününe kaydır
      dispatch = 1;
    }

    var shipDay = nthWorkDay(tr, dispatch);
    var today    = todayStart;
    var tomorrow = new Date(todayStart.getTime() + 86400000);

    var when;
    if (shipDay.getTime() === today.getTime())         when = 'bugün';
    else if (shipDay.getTime() === tomorrow.getTime()) when = 'yarın';
    else                                               when = dayName(shipDay);

    return 'En geç ' + when + ' kargoya verilir';
  }

  function update() {
    var els = document.querySelectorAll('.pship-txt');
    if (!els.length) return;
    var tr = trNow();
    for (var i = 0; i < els.length; i++) {
      var container = els[i].closest('[data-dispatch]');
      var dispatch  = container ? parseInt(container.getAttribute('data-dispatch') || '1', 10) : 1;
      if (isNaN(dispatch) || dispatch < 0) dispatch = 1;
      els[i].textContent = computeText(dispatch, tr);
    }
  }

  function init() { update(); setInterval(update, 60000); }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();


/* ═══════════════════════════════════════════
   FAVORİ (localStorage)
═══════════════════════════════════════════ */
(function(){
  var FAV_KEY='pzFavs';
  function loadFavs(){try{return JSON.parse(localStorage.getItem(FAV_KEY)||'[]');}catch(e){return[];}}
  function saveFavs(f){try{localStorage.setItem(FAV_KEY,JSON.stringify(f));}catch(e){}}

  window.pzToggleFav=function(btn,id,name,img,url){
    var favs=loadFavs();
    var idx=favs.findIndex(function(f){return f.id==id;});
    var span=btn.querySelector('span');
    if(idx>-1){
      favs.splice(idx,1);
      btn.classList.remove('active');
      if(span)span.textContent='Favorilerime Ekle';
    }else{
      favs.push({id:id,name:name,img:img,url:url});
      btn.classList.add('active');
      if(span)span.textContent='Favorilerde ✓';
    }
    saveFavs(favs);
  };

  function initFavBtn(){
    var favs=loadFavs();
    // hbFavBtn (product detail page)
    var btn=document.getElementById('hbFavBtn');
    if(btn){
      var pid=btn.getAttribute('data-pid');
      if(pid&&favs.some(function(f){return f.id==pid;})){
        btn.classList.add('active');
        var span=btn.querySelector('span');
        if(span)span.textContent='Favorilerde ✓';
      }
    }
    // .p-wish buttons (product card grids)
    document.querySelectorAll('.p-wish').forEach(function(w){
      var card=w.closest('.pcard[data-pzcomp]');
      if(!card)return;
      var data={};try{data=JSON.parse(card.getAttribute('data-pzcomp')||'{}');}catch(e){return;}
      if(!data.id)return;
      if(favs.some(function(f){return String(f.id)===String(data.id);})){w.textContent='❤️';w.classList.add('on');}
    });
    // .sim-fav buttons (similar products tab)
    document.querySelectorAll('.sim-fav[data-pid]').forEach(function(w){
      var pid=w.getAttribute('data-pid');
      if(favs.some(function(f){return String(f.id)===String(pid);})){w.classList.add('active');}
    });
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initFavBtn);
  else initFavBtn();
})();

/* ═══════════════════════════════════════════
   BİLDİRİM MODALI (Stok & Fiyat Alarmı)
═══════════════════════════════════════════ */
(function(){
  var _type,_pid;

  window.pzOpenNotify=function(type,pid,name){
    _type=type;_pid=pid;
    var modal=document.getElementById('hbNotifyModal');
    if(!modal)return;
    var icon =document.getElementById('hbNIcon');
    var title=document.getElementById('hbNTitle');
    var desc =document.getElementById('hbNDesc');
    var email=document.getElementById('hbNEmail');
    var sub  =document.getElementById('hbNSubmit');
    if(type==='stock'){
      if(icon)icon.innerHTML='<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>';
      if(title)title.textContent='Stoğa Gelince Haber Ver';
      if(desc)desc.textContent='"'+name+'" stoğa girdiğinde anında e-posta alacaksınız.';
    }else{
      if(icon)icon.innerHTML='<svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>';
      if(title)title.textContent='Fiyat Düşünce Haber Ver';
      if(desc)desc.textContent='"'+name+'" ürününün fiyatı düştüğünde anında e-posta alacaksınız.';
    }
    if(email)email.value='';
    if(sub){sub.textContent='Beni Haberdar Et';sub.style.background='';sub.disabled=false;}
    modal.classList.add('open');
    document.body.style.overflow='hidden';
    setTimeout(function(){if(email)email.focus();},120);
  };

  window.pzCloseNotify=function(){
    var m=document.getElementById('hbNotifyModal');
    if(m)m.classList.remove('open');
    document.body.style.overflow='';
  };

  window.pzSubmitNotify=function(){
    var email=document.getElementById('hbNEmail');
    var sub  =document.getElementById('hbNSubmit');
    if(!email)return;
    var val=email.value.trim();
    if(!val||val.indexOf('@')<0){
      email.style.borderColor='#dc2626';
      email.focus();
      setTimeout(function(){email.style.borderColor='';},1500);
      return;
    }
    if(sub){sub.disabled=true;sub.textContent='Gönderiliyor...';}
    var fd=new FormData();
    fd.append('action','pz_notify_subscribe');
    fd.append('type',_type);
    fd.append('pid',_pid);
    fd.append('email',val);
    fd.append('nonce',(window.bazario_ajax||{}).nonce||'');
    fetch(((window.bazario_ajax||{}).url||'/wp-admin/admin-ajax.php'),{method:'POST',body:fd})
      .then(function(r){return r.json();})
      .then(function(){
        if(sub){sub.textContent='✓ Kaydedildi!';sub.style.background='#16a34a';}
        setTimeout(function(){window.pzCloseNotify();},1600);
      })
      .catch(function(){
        if(sub){sub.disabled=false;sub.textContent='Beni Haberdar Et';}
      });
  };

  document.addEventListener('click',function(e){
    var m=document.getElementById('hbNotifyModal');
    if(m&&e.target===m)window.pzCloseNotify();
  });
  document.addEventListener('keydown',function(e){
    if(e.key==='Escape')window.pzCloseNotify();
  });
})();

/* ═══════════════════════════════════════════
   FAVORİLERİM SAYFASI (üye paneli)
═══════════════════════════════════════════ */
(function(){
  function pzFmtPriceLocal(n){
    if(typeof n!=='number')return '';
    return n.toLocaleString('tr-TR',{minimumFractionDigits:2,maximumFractionDigits:2})+' ₺';
  }

  function renderFavPage(){
    var list =document.getElementById('pzFavPageList');
    var empty=document.getElementById('pzFavPageEmpty');
    if(!list)return;
    var favs=[];
    try{favs=JSON.parse(localStorage.getItem('pzFavs')||'[]');}catch(e){}
    if(!favs.length){list.style.display='none';if(empty)empty.style.display='flex';return;}
    var html='';
    favs.forEach(function(p){
      html+='<div class="pz-fav-card" data-fav-id="'+p.id+'" style="transition:opacity .3s,transform .3s;">'+
        '<a href="'+p.url+'" class="pz-fav-card-img"><img src="'+p.img+'" alt="" loading="lazy"></a>'+
        '<div class="pz-fav-card-body">'+
          '<a href="'+p.url+'" class="pz-fav-card-name">'+p.name+'</a>'+
          '<div class="pz-fav-card-foot">'+
            '<a href="'+p.url+'" class="pz-fav-card-view">Ürünü İncele →</a>'+
            '<button class="pz-fav-card-rm" onclick="pzFavPageRemove(this,'+p.id+')">✕ Favorilerden Çıkar</button>'+
          '</div>'+
        '</div>'+
      '</div>';
    });
    list.innerHTML=html;
    if(empty)empty.style.display='none';
    list.style.display='';
  }

  window.pzFavPageRemove=function(btn,id){
    var favs=[];
    try{favs=JSON.parse(localStorage.getItem('pzFavs')||'[]');}catch(e){}
    favs=favs.filter(function(f){return f.id!=id;});
    try{localStorage.setItem('pzFavs',JSON.stringify(favs));}catch(e){}
    var card=btn.closest('.pz-fav-card');
    if(card){card.style.opacity='0';card.style.transform='scale(0.9)';setTimeout(function(){card.remove();if(!document.querySelector('.pz-fav-card')){var list=document.getElementById('pzFavPageList');var empty=document.getElementById('pzFavPageEmpty');if(list)list.style.display='none';if(empty)empty.style.display='flex';}},300);}
  };

  // Dashboard stat sayaçları
  function updateDashStats(){
    var favCnt =document.querySelector('.pz-acc-fav-cnt');
    var compCnt=document.querySelector('.pz-acc-comp-cnt');
    if(favCnt){try{var f=JSON.parse(localStorage.getItem('pzFavs')||'[]');favCnt.textContent=f.length;}catch(e){}}
    if(compCnt){try{var c=JSON.parse(localStorage.getItem('pzComp')||'[]');compCnt.textContent=c.length;}catch(e){}}
  }

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){renderFavPage();updateDashStats();});
  else{renderFavPage();updateDashStats();}
})();

/* ═══════════════════════════════════════════
   KARŞILAŞTIRMA LİSTEM SAYFASI (üye paneli)
═══════════════════════════════════════════ */
(function(){
  function renderCompPage(){
    var list =document.getElementById('pzCompPageList');
    var empty=document.getElementById('pzCompPageEmpty');
    var ctaW =document.getElementById('pzCompPageCtaWrap');
    if(!list)return;
    var items=[];
    try{items=JSON.parse(localStorage.getItem('pzComp')||'[]');}catch(e){}
    if(!items.length){
      list.style.display='none';
      if(empty)empty.style.display='flex';
      if(ctaW)ctaW.style.display='none';
      return;
    }
    var html='';
    items.forEach(function(p){
      var disc=p.discount>0?'<span class="pz-comp-page-disc">%'+p.discount+' İndirim</span>':'';
      var free=p.freeShip?'<span class="pz-comp-page-free">🚚 Ücretsiz Kargo</span>':'';
      html+='<div class="pz-comp-page-card" data-comp-id="'+p.id+'" style="transition:opacity .3s,transform .3s;">'+
        '<div class="pz-comp-page-img"><a href="'+p.url+'"><img src="'+p.img+'" alt="" loading="lazy"></a>'+disc+'</div>'+
        '<div class="pz-comp-page-body">'+
          '<a href="'+p.url+'" class="pz-comp-page-name">'+p.title+'</a>'+
          '<div class="pz-comp-page-price">'+pzFmtPrice(p.price)+'</div>'+
          free+
          '<div class="pz-comp-page-foot">'+
            '<a href="'+p.url+'" class="pz-comp-page-view">İncele</a>'+
            '<button class="pz-comp-page-rm" onclick="pzCompPageRemove(this,'+p.id+')">✕ Çıkar</button>'+
          '</div>'+
        '</div>'+
      '</div>';
    });
    list.innerHTML=html;
    if(empty)empty.style.display='none';
    list.style.display='';
    if(ctaW)ctaW.style.display=items.length>=2?'block':'none';
  }

  window.pzCompPageRemove=function(btn,id){
    pzRemoveComp(id);
    var card=btn.closest('.pz-comp-page-card');
    if(card){
      card.style.opacity='0';card.style.transform='scale(0.9)';
      setTimeout(function(){
        card.remove();
        var remaining=document.querySelectorAll('.pz-comp-page-card').length;
        var ctaW=document.getElementById('pzCompPageCtaWrap');
        if(ctaW)ctaW.style.display=remaining>=2?'block':'none';
        if(!remaining){var list=document.getElementById('pzCompPageList');var empty=document.getElementById('pzCompPageEmpty');if(list)list.style.display='none';if(empty)empty.style.display='flex';}
      },300);
    }
  };

  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',renderCompPage);
  else renderCompPage();
})();

/* ── Özellik fade kontrolü + Tüm Özellikleri Gör ── */
(function(){
  function initAttrsCollapse(){
    var outer=document.getElementById('hbAttrsOuter');
    var moreBtn=document.getElementById('hbAttrsMore');
    if(!outer||!moreBtn)return;
    var wrap=document.getElementById('hbAttrsWrap');
    // İçerik max-height'dan kısaysa butonu gizle
    if(wrap&&wrap.scrollHeight<=outer.offsetHeight+8){
      moreBtn.classList.add('hidden');
      outer.style.maxHeight='none';
      var fade=outer.querySelector('.hb-attrs-fade');
      if(fade)fade.style.display='none';
    }
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initAttrsCollapse);
  else initAttrsCollapse();

  window.hbShowSpecs=function(){
    goTab(1);
    setTimeout(function(){
      var tabs=document.querySelector('.pd-tabs');
      if(tabs)tabs.scrollIntoView({behavior:'smooth',block:'start'});
    },80);
  };
})();
