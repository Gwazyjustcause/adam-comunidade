( function () {
	'use strict';

	const form = document.getElementById( 'adam-field-filters' );
	const results = document.getElementById( 'adam-field-results' );
	const pagination = document.getElementById( 'adam-field-pagination' );
	const total = document.getElementById( 'adam-field-total' );
	let requestController;
	let debounceTimer;

	document.querySelectorAll( '[data-adam-fields-carousel], [data-adam-directory-carousel]' ).forEach( ( carousel ) => {
		const slides = Array.from( carousel.querySelectorAll( '[data-adam-fields-slide], [data-adam-directory-slide]' ) );
		const indicators = Array.from( carousel.querySelectorAll( '[data-adam-fields-indicator], [data-adam-directory-indicator]' ) );
		if ( slides.length < 2 ) {
			return;
		}
		const reducedMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
		const autoplay = 'true' === carousel.dataset.autoplay && ! reducedMotion;
		const interval = Math.max( 3000, Number.parseInt( carousel.dataset.interval || '6000', 10 ) );
		let current = 0;
		let timer;
		let touchStartX = null;

		function show( index ) {
			current = ( index + slides.length ) % slides.length;
			slides.forEach( ( slide, slideIndex ) => {
				const active = slideIndex === current;
				slide.classList.toggle( 'is-active', active );
				slide.setAttribute( 'aria-hidden', active ? 'false' : 'true' );
			} );
			indicators.forEach( ( indicator, indicatorIndex ) => {
				indicator.setAttribute( 'aria-current', indicatorIndex === current ? 'true' : 'false' );
			} );
		}

		function stop() {
			window.clearInterval( timer );
			timer = undefined;
		}

		function start() {
			stop();
			if ( autoplay && ! document.hidden ) {
				timer = window.setInterval( () => show( current + 1 ), interval );
			}
		}

		carousel.querySelector( '[data-adam-fields-prev], [data-adam-directory-prev]' )?.addEventListener( 'click', () => {
			show( current - 1 );
			start();
		} );
		carousel.querySelector( '[data-adam-fields-next], [data-adam-directory-next]' )?.addEventListener( 'click', () => {
			show( current + 1 );
			start();
		} );
		indicators.forEach( ( indicator ) => indicator.addEventListener( 'click', () => {
			show( Number.parseInt( indicator.dataset.adamFieldsIndicator ?? indicator.dataset.adamDirectoryIndicator, 10 ) );
			start();
		} ) );
		carousel.addEventListener( 'mouseenter', stop );
		carousel.addEventListener( 'mouseleave', start );
		carousel.addEventListener( 'focusin', stop );
		carousel.addEventListener( 'focusout', ( event ) => {
			if ( ! carousel.contains( event.relatedTarget ) ) {
				start();
			}
		} );
		carousel.addEventListener( 'touchstart', ( event ) => {
			touchStartX = event.changedTouches[ 0 ]?.clientX ?? null;
			stop();
		}, { passive: true } );
		carousel.addEventListener( 'touchend', ( event ) => {
			const touchEndX = event.changedTouches[ 0 ]?.clientX ?? touchStartX;
			if ( null !== touchStartX && Math.abs( touchEndX - touchStartX ) > 45 ) {
				show( current + ( touchEndX < touchStartX ? 1 : -1 ) );
			}
			touchStartX = null;
			start();
		}, { passive: true } );
		document.addEventListener( 'visibilitychange', () => document.hidden ? stop() : start() );
		start();
	} );

	async function filterFields( page = 1, moveFocus = false ) {
		if ( ! form || ! results || ! window.adamFields ) {
			return;
		}
		requestController?.abort();
		requestController = new AbortController();
		const currentRequest = requestController;
		const data = new FormData( form );
		data.append( 'action', 'adam_filter_fields' );
		data.append( 'nonce', window.adamFields.nonce );
		data.append( 'page_number', page );
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );
		form.setAttribute( 'aria-busy', 'true' );

		try {
			const response = await fetch( window.adamFields.ajaxUrl, {
				method: 'POST',
				body: data,
				signal: currentRequest.signal,
				credentials: 'same-origin',
			} );
			const payload = await response.json();
			if ( ! response.ok || ! payload.success ) {
				throw new Error( 'Invalid response' );
			}
			results.innerHTML = payload.data.cards;
			pagination.innerHTML = payload.data.pagination;
			total.textContent = payload.data.total;
			const url = new URL( window.location.href );
			url.search = '';
			new FormData( form ).forEach( ( value, key ) => {
				if ( value && 'all' !== value ) {
					url.searchParams.set( key, value );
				}
			} );
			if ( page > 1 ) {
				url.searchParams.set( 'pagina', page );
			}
			window.history.replaceState( {}, '', url );
			if ( moveFocus ) {
				results.tabIndex = -1;
				results.focus( { preventScroll: true } );
			}
		} catch ( error ) {
			if ( 'AbortError' !== error.name ) {
				const message = document.createElement( 'div' );
				message.className = 'adam-comunidade__empty adam-fields-empty';
				message.textContent = window.adamFields.error;
				results.replaceChildren( message );
			}
		} finally {
			if ( requestController === currentRequest ) {
				results.classList.remove( 'is-loading' );
				results.removeAttribute( 'aria-busy' );
				form.removeAttribute( 'aria-busy' );
			}
		}
	}

	if ( form ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			filterFields();
		} );
		form.addEventListener( 'change', () => filterFields() );
		form.querySelector( 'input[type="search"]' )?.addEventListener( 'input', () => {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( () => filterFields(), 300 );
		} );
		pagination.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-page]' );
			if ( button ) {
				event.preventDefault();
				filterFields( Number.parseInt( button.dataset.page, 10 ) || 1, true );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	}

	const lightbox = document.querySelector( '.adam-field-lightbox' );
	if ( lightbox ) {
		const image = lightbox.querySelector( 'img' );
		const caption = lightbox.querySelector( 'figcaption' );
		let previousFocus = null;
		document.querySelectorAll( '[data-field-lightbox]' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				previousFocus = link;
				image.src = link.href;
				caption.textContent = link.dataset.caption || '';
				image.alt = link.dataset.caption || '';
				lightbox.hidden = false;
				lightbox.querySelector( 'button' ).focus();
			} );
		} );
		function closeLightbox() {
			lightbox.hidden = true;
			image.src = '';
			image.alt = '';
			caption.textContent = '';
			previousFocus?.focus();
			previousFocus = null;
		}
		lightbox.addEventListener( 'click', ( event ) => {
			if ( event.target === lightbox || event.target.closest( 'button' ) ) {
				closeLightbox();
			}
		} );
		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key && ! lightbox.hidden ) {
				closeLightbox();
			}
		} );
		lightbox.addEventListener( 'keydown', ( event ) => {
			if ( 'Tab' === event.key ) {
				event.preventDefault();
				lightbox.querySelector( 'button' ).focus();
			}
		} );
	}

	const toast = document.querySelector( '.adam-field-toast' );
	async function copyText( value ) {
		if ( navigator.clipboard?.writeText ) {
			await navigator.clipboard.writeText( value );
			return;
		}

		const input = document.createElement( 'textarea' );
		input.value = value;
		input.setAttribute( 'readonly', '' );
		input.style.position = 'fixed';
		input.style.opacity = '0';
		document.body.appendChild( input );
		input.select();
		const copied = document.execCommand( 'copy' );
		input.remove();
		if ( ! copied ) {
			throw new Error( 'Copy failed' );
		}
	}

	document.querySelectorAll( '[data-copy-gps]' ).forEach( ( button ) => {
		button.addEventListener( 'click', async () => {
			try {
				await copyText( button.dataset.copyGps );
				toast.textContent = window.adamFields.copied;
			} catch ( error ) {
				toast.textContent = window.adamFields.copyFail;
			}
			toast.hidden = false;
			window.setTimeout( () => { toast.hidden = true; }, 2500 );
		} );
	} );

	if ( window.matchMedia( '(max-width: 700px)' ).matches ) {
		document.querySelectorAll( '.adam-field-collapsible' ).forEach( ( details ) => {
			details.removeAttribute( 'open' );
		} );
	}
}() );
