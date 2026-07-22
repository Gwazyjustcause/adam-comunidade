( function () {
	'use strict';

	const form = document.getElementById( 'adam-team-filters' );
	const results = document.getElementById( 'adam-team-results' );
	const pagination = document.getElementById( 'adam-team-pagination' );
	const total = document.getElementById( 'adam-team-total' );
	let requestController;
	let debounceTimer;

	async function filterTeams( page = 1 ) {
		if ( ! form || ! results || ! window.adamTeams ) {
			return;
		}

		if ( requestController ) {
			requestController.abort();
		}
		requestController = new AbortController();
		const data = new FormData( form );
		data.append( 'action', 'adam_filter_teams' );
		data.append( 'nonce', window.adamTeams.nonce );
		data.append( 'page_number', page );
		results.classList.add( 'is-loading' );
		results.setAttribute( 'aria-busy', 'true' );

		try {
			const response = await fetch( window.adamTeams.ajaxUrl, {
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
			if ( error.name !== 'AbortError' ) {
				const errorMessage = document.createElement( 'div' );
				errorMessage.className = 'adam-comunidade__empty adam-teams-empty';
				errorMessage.textContent = window.adamTeams.error;
				results.replaceChildren( errorMessage );
			}
		} finally {
			results.classList.remove( 'is-loading' );
			results.removeAttribute( 'aria-busy' );
		}
	}

	if ( form ) {
		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			filterTeams();
		} );
		form.addEventListener( 'change', () => filterTeams() );
		form.querySelector( 'input[type="search"]' ).addEventListener( 'input', () => {
			window.clearTimeout( debounceTimer );
			debounceTimer = window.setTimeout( () => filterTeams(), 300 );
		} );
		pagination.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-page]' );
			if ( button ) {
				filterTeams( Number.parseInt( button.dataset.page, 10 ) || 1 );
				form.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			}
		} );
	}

	const lightbox = document.querySelector( '.adam-lightbox' );
	if ( lightbox ) {
		const image = lightbox.querySelector( 'img' );
		document.querySelectorAll( '[data-adam-lightbox]' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				image.src = link.href;
				lightbox.hidden = false;
				lightbox.querySelector( 'button' ).focus();
			} );
		} );
		lightbox.addEventListener( 'click', ( event ) => {
			if ( event.target === lightbox || event.target.closest( 'button' ) ) {
				lightbox.hidden = true;
				image.src = '';
			}
		} );
		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key && ! lightbox.hidden ) {
				lightbox.hidden = true;
				image.src = '';
			}
		} );
	}
}() );
