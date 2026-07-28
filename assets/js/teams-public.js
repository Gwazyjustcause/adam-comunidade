( function () {
	'use strict';

	const form = document.getElementById( 'adam-team-filters' );
	const results = document.getElementById( 'adam-team-results' );
	const pagination = document.getElementById( 'adam-team-pagination' );
	const total = document.getElementById( 'adam-team-total' );
	let requestController;
	let debounceTimer;

	async function filterTeams( page = 1, moveFocus = false ) {
		if ( ! form || ! results || ! window.adamTeams ) {
			return;
		}

		if ( requestController ) {
			requestController.abort();
		}
		requestController = new AbortController();
		const currentRequest = requestController;
		const data = new FormData( form );
		data.append( 'action', 'adam_filter_teams' );
		data.append( 'nonce', window.adamTeams.nonce );
		data.append( 'page_number', page );
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );
		form.setAttribute( 'aria-busy', 'true' );

		try {
			const response = await fetch( window.adamTeams.ajaxUrl, {
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
			if ( error.name !== 'AbortError' ) {
				const errorMessage = document.createElement( 'div' );
				errorMessage.className = 'adam-comunidade__empty adam-teams-empty';
				errorMessage.textContent = window.adamTeams.error;
				results.replaceChildren( errorMessage );
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
			filterTeams();
		} );
		form.addEventListener( 'change', () => filterTeams() );
		form.querySelector( 'input[type="search"]' )?.addEventListener( 'input', () => {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( () => filterTeams(), 300 );
		} );
		pagination.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-page]' );
			if ( button ) {
				event.preventDefault();
				filterTeams( Number.parseInt( button.dataset.page, 10 ) || 1, true );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	}

	const lightbox = document.querySelector( '.adam-lightbox' );
	if ( lightbox ) {
		const image = lightbox.querySelector( 'img' );
		let previousFocus = null;
		document.querySelectorAll( '[data-adam-lightbox]' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				previousFocus = link;
				image.src = link.href;
				image.alt = link.dataset.alt || '';
				lightbox.hidden = false;
				lightbox.querySelector( 'button' ).focus();
			} );
		} );
		function closeLightbox() {
			lightbox.hidden = true;
			image.src = '';
			image.alt = '';
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
}() );
