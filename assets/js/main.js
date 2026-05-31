/* 724 Pazaryeri - Main JS */
(function($) {
  'use strict';

  /* ── SLIDER ── */
  var slider = {
    el: document.getElementById('heroSlider'),
    track: null, dots: null, current: 0, total: 0, timer: null,
    init: function() {
      if (!this.el) return;
      this.track = this.el.querySelector('.slider-track');
      this.dots  = this.el.querySelectorAll('.slider-dot');
      this.total = this.el.querySelectorAll('.slide').length;
      document.getElementById('sliderPrev') && document.getElementById('sliderPrev').addEventListener('click', this.prev.bind(this));
      document.getElementById('sliderNext') && document.getElementById('sliderNext').addEventListener('click', this.next.bind(this));
      this.dots.forEach(function(d, i) { d.addEventListener('click', function() { slider.go(i); }); });
      this.autoplay();
    },
    go: function(n) {
      this.current = (n + this.total) % this.total;
      this.track.style.transform = 'translateX(-' + (this.current * 100) + '%)';
      this.dots.forEach(function(d, i) { d.classList.toggle('active', i === slider.current); });
    },
    next: function() { this.go(this.current + 1); },
    prev: function() { this.go(this.current - 1); },
    autoplay: function() {
      clearInterval(this.timer);
      this.timer = setInterval(this.next.bind(this), 5000);
    }
  };

  /* ── COUNTDOWN ── */
  function countdown() {
    var end = new Date();
    end.setHours(23, 59, 59, 0);
    function tick() {
      var now  = new Date();
      var diff = Math.max(0, end - now);
      var h = Math.floor(diff / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      var hEl = document.getElementById('cd-h');
      var mEl = document.getElementById('cd-m');
      var sEl = document.getElementById('cd-s');
      if (hEl) hEl.textContent = String(h).padStart(2,'0');
      if (mEl) mEl.textContent = String(m).padStart(2,'0');
      if (sEl) sEl.textContent = String(s).padStart(2,'0');
    }
    tick();
    setInterval(tick, 1000);
  }

  /* ── TOAST ── */
  window.showToast = function(msg, type) {
    var container = document.getElementById('toast-container');
    if (!container) return;
    var toast = document.createElement('div');
    toast.className = 'toast ' + (type || '');
    toast.innerHTML = '<span style="font-size:20px">' + (type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️') + '</span><span>' + msg + '</span>';
    container.appendChild(toast);
    setTimeout(function() { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = 'all .3s'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
  };

  /* ── WISHLIST ── */
  $(document).on('click', '.product-wishlist', function(e) {
    e.preventDefault();
    var btn = $(this);
    var pid = btn.data('product-id');
    if (!pazaryeriData) return;
    $.post(pazaryeriData.ajaxUrl, { action: 'pazaryeri_toggle_wishlist', product_id: pid, nonce: pazaryeriData.nonce }, function(res) {
      if (res.success) {
        var added = res.data.added;
        btn.toggleClass('active', added);
        btn.find('i').toggleClass('fas', added).toggleClass('far', !added);
        showToast(added ? pazaryeriData.i18n.addToWishlist : pazaryeriData.i18n.removeFromWish, 'success');
      }
    });
  });

  /* ── ADD TO CART SUCCESS ── */
  $(document.body).on('added_to_cart', function(e, frags, hash, btn) {
    showToast(pazaryeriData.i18n.addedToCart, 'success');
    var count = frags['.cart-count'] ? parseInt($(frags['.cart-count']).text()) : 0;
    if (count > 0) {
      $('.cart-count').text(count).show();
    }
  });

  /* ── QUANTITY CONTROLS ── */
  $(document).on('click', '.qty-btn', function() {
    var btn   = $(this);
    var input = btn.siblings('.qty-input');
    var val   = parseInt(input.val()) || 1;
    input.val(btn.hasClass('qty-plus') ? val + 1 : Math.max(1, val - 1)).trigger('change');
  });

  /* ── STICKY HEADER SHADOW ── */
  window.addEventListener('scroll', function() {
    var header = document.querySelector('.site-header');
    if (header) header.classList.toggle('scrolled', window.scrollY > 10);
  });

  /* ── SEARCH AUTOCOMPLETE (basic) ── */
  var searchInput = document.querySelector('.search-input');
  if (searchInput) {
    var debounce = null;
    searchInput.addEventListener('input', function() {
      clearTimeout(debounce);
      var val = this.value.trim();
      if (val.length < 2) return;
      debounce = setTimeout(function() {
        /* Future: fetch suggestions via AJAX */
      }, 300);
    });
  }

  /* ── INIT ── */
  $(function() {
    slider.init();
    countdown();
  });

})(jQuery);
