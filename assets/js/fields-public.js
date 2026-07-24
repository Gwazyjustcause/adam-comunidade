( function () {
	'use strict';

	const form = document.getElementById( 'adam-field-filters' );
	const results = document.getElementById( 'adam-field-results' );
	const pagination = document.getElementById( 'adam-field-pagination' );
	const total = document.getElementById( 'adam-field-total' );
	let requestController;
	let debounceTimer;

	async function filterFields( page = 1 ) {
		if ( ! form || ! results || ! window.adamFields ) {
			return;
		}
		requestController?.abort();
		requestController = new AbortController();
		const data = new FormData( form );
		data.append( 'action', 'adam_filter_fields' );
		data.append( 'nonce', window.adamFields.nonce );
		data.append( 'page_number', page );
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );

		try {
			const response = await fetch( window.adamFields.ajaxUrl, {
				method: 'POST',
				body: data,
				signal: requestController.signal,
				credentials: 'same-origin',
			} );
			const payload = await response.json();
			if ( ! response.ok || ! payload.success ) {
				throw new Error( 'Invalid response' );
			}
			results.innerHTML = payload.data.cards;
			pagination.innerHTML = payload.data.pagination;
			total.textContent = payload.data.total;
		} catch ( error ) {
			if ( 'AbortError' !== error.name ) {
				const message = document.createElement( 'div' );
				message.className = 'adam-comunidade__empty adam-fields-empty';
				message.textContent = window.adamFields.error;
				results.replaceChildren( message );
			}
		} finally {
			results.classList.remove( 'is-loading' );
			results.removeAttribute( 'aria-busy' );
		}
	}

	if ( form ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			filterFields();
		} );
		form.addEventListener( 'change', () => filterFields() );
		form.querySelector( 'input[type="search"]' ).addEventListener( 'input', () => {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( () => filterFields(), 300 );
		} );
		pagination.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-page]' );
			if ( button ) {
				filterFields( Number.parseInt( button.dataset.page, 10 ) || 1 );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	}

	const lightbox = document.querySelector( '.adam-field-lightbox' );
	if ( lightbox ) {
		const image = lightbox.querySelector( 'img' );
		const caption = lightbox.querySelector( 'figcaption' );
		document.querySelectorAll( '[data-field-lightbox]' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				image.src = link.href;
				caption.textContent = link.dataset.caption || '';
				lightbox.hidden = false;
				lightbox.querySelector( 'button' ).focus();
			} );
		} );
		function closeLightbox() {
			lightbox.hidden = true;
			image.src = '';
			caption.textContent = '';
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
