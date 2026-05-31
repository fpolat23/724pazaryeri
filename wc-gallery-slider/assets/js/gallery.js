/* global Swiper */
( function () {
	'use strict';

	var AUTOPLAY_DELAY = 4000;

	/* Her galeri wrap'ını başlat */
	document.querySelectorAll( '.wc-gallery-wrap' ).forEach( function ( wrap ) {
		var uid     = wrap.id;
		var images  = ( window.wcGalleryData && window.wcGalleryData[ uid ] ) || [];
		var count   = images.length;

		if ( ! count ) return;

		var mainEl   = wrap.querySelector( '.wc-gallery-main' );
		var thumbsEl = wrap.querySelector( '.wc-gallery-thumbs' );

		if ( ! mainEl ) return;

		var thumbsSwiper = null;

		/* ---- Thumbnail Swiper ---- */
		if ( thumbsEl && count > 1 ) {
			thumbsSwiper = new Swiper( thumbsEl, {
				spaceBetween: 8,
				slidesPerView: 'auto',
				freeMode: true,
				watchSlidesProgress: true,
				slideToClickedSlide: true,
				a11y: false,
			} );
		}

		/* ---- Ana Swiper ---- */
		var mainSwiper = new Swiper( mainEl, {
			loop: count > 1,
			speed: 500,
			autoplay: count > 1 ? {
				delay: AUTOPLAY_DELAY,
				disableOnInteraction: false,
				pauseOnMouseEnter: true,
			} : false,
			navigation: count > 1 ? {
				nextEl: mainEl.querySelector( '.swiper-button-next' ),
				prevEl: mainEl.querySelector( '.swiper-button-prev' ),
			} : false,
			thumbs: thumbsSwiper ? { swiper: thumbsSwiper } : undefined,
			keyboard: { enabled: true, onlyInViewport: true },
			on: {
				realIndexChange: function ( swiper ) {
					updateCounter( wrap, swiper.realIndex, count );
					resetProgressBar( wrap );
				},
				autoplayTimeLeft: function ( swiper, time, progress ) {
					updateProgressBar( wrap, 1 - progress );
				},
				autoplayStop: function () {
					resetProgressBar( wrap );
				},
			},
		} );

		/* ---- Lightbox ---- */
		var lightbox     = null;
		var lbSwiper     = null;
		var lbCounter    = null;

		function buildLightbox() {
			if ( lightbox ) return;

			var slides = images.map( function ( img ) {
				return '<div class="swiper-slide"><img src="' +
					escAttr( img.full ) + '" alt="' + escAttr( img.alt ) + '"></div>';
			} ).join( '' );

			var el = document.createElement( 'div' );
			el.className = 'wc-lightbox';
			el.setAttribute( 'role', 'dialog' );
			el.setAttribute( 'aria-modal', 'true' );
			el.innerHTML =
				'<div class="wc-lightbox-inner">' +
					'<div class="swiper wc-lightbox-swiper">' +
						'<div class="swiper-wrapper">' + slides + '</div>' +
						( count > 1 ? '<button class="swiper-button-prev" aria-label="Önceki"></button>' +
						              '<button class="swiper-button-next" aria-label="Sonraki"></button>' +
						              '<div class="swiper-pagination"></div>' : '' ) +
					'</div>' +
				'</div>' +
				'<div class="wc-lightbox-counter">' + ( count > 1 ? '1 / ' + count : '' ) + '</div>' +
				'<button class="wc-lightbox-close" aria-label="Kapat">&#x2715;</button>';

			document.body.appendChild( el );
			lightbox = el;
			lbCounter = el.querySelector( '.wc-lightbox-counter' );

			lbSwiper = new Swiper( el.querySelector( '.wc-lightbox-swiper' ), {
				loop: count > 1,
				speed: 380,
				navigation: count > 1 ? {
					nextEl: el.querySelector( '.swiper-button-next' ),
					prevEl: el.querySelector( '.swiper-button-prev' ),
				} : false,
				pagination: count > 1 ? {
					el: el.querySelector( '.swiper-pagination' ),
					clickable: true,
				} : false,
				keyboard: { enabled: true },
				a11y: { enabled: true },
				on: {
					realIndexChange: function ( swiper ) {
						if ( lbCounter && count > 1 ) {
							lbCounter.textContent = ( swiper.realIndex + 1 ) + ' / ' + count;
						}
					},
				},
			} );

			/* Dışarı tıklayınca kapat */
			el.addEventListener( 'click', function ( e ) {
				if ( e.target === el ) closeLightbox();
			} );

			/* Kapat düğmesi */
			el.querySelector( '.wc-lightbox-close' ).addEventListener( 'click', closeLightbox );
		}

		function openLightbox( index ) {
			buildLightbox();
			lbSwiper.slideToLoop( index, 0, false );
			lightbox.classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
			mainSwiper.autoplay && mainSwiper.autoplay.stop();
			trapFocus( lightbox );
		}

		function closeLightbox() {
			if ( ! lightbox ) return;
			lightbox.classList.remove( 'is-open' );
			document.body.style.overflow = '';
			mainSwiper.autoplay && mainSwiper.autoplay.start();
		}

		/* Ana slider'a tıklayınca lightbox aç */
		mainEl.addEventListener( 'click', function ( e ) {
			if ( e.target.closest( '.swiper-button-prev' ) ||
			     e.target.closest( '.swiper-button-next' ) ) return;
			openLightbox( mainSwiper.realIndex );
		} );

		/* ESC tuşuyla kapat */
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) closeLightbox();
		} );
	} );

	/* ---- Yardımcılar ---- */

	function updateCounter( wrap, index, total ) {
		var counter = wrap.querySelector( '.wc-gallery-counter' );
		if ( counter ) counter.textContent = ( index + 1 ) + ' / ' + total;
	}

	function updateProgressBar( wrap, fraction ) {
		var bar = wrap.querySelector( '.wc-gallery-progress-inner' );
		if ( bar ) {
			bar.style.transitionDuration = '100ms';
			bar.style.width = ( fraction * 100 ).toFixed( 1 ) + '%';
		}
	}

	function resetProgressBar( wrap ) {
		var bar = wrap.querySelector( '.wc-gallery-progress-inner' );
		if ( bar ) {
			bar.style.transitionDuration = '0ms';
			bar.style.width = '0%';
		}
	}

	function escAttr( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /"/g, '&quot;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' );
	}

	function trapFocus( el ) {
		var focusable = el.querySelectorAll( 'button, [href], [tabindex]:not([tabindex="-1"])' );
		if ( focusable.length ) focusable[ 0 ].focus();
	}

} )();
