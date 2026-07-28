( function () {
	'use strict';

	document.addEventListener( 'submit', function ( event ) {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) || ! form.closest( '.adam-manager-portal' ) ) {
			return;
		}
		form.setAttribute( 'aria-busy', 'true' );
		const button = form.querySelector( 'button[type="submit"]' );
		if ( button ) {
			button.disabled = true;
			button.textContent = 'A processar…';
		}
	} );

	document.dispatchEvent( new CustomEvent( 'adamComunidadeReady' ) );
}() );
