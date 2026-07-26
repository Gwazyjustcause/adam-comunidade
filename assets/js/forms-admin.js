( function ( $ ) {
	'use strict';

	$( '[data-adam-form-tab]' ).on( 'click', function ( event ) {
		event.preventDefault();
		const type = $( this ).data( 'adam-form-tab' );
		$( '[data-adam-form-tab]' ).removeClass( 'nav-tab-active' );
		$( this ).addClass( 'nav-tab-active' );
		$( '[data-adam-form-panel]' ).removeClass( 'is-active' );
		$( '[data-adam-form-panel="' + type + '"]' ).addClass( 'is-active' );
		window.history.replaceState( null, '', '#adam-form-' + type );
	} );

	$( '[data-adam-sortable]' ).sortable( {
		handle: '.adam-form-builder__handle',
		axis: 'y',
		placeholder: 'adam-form-builder__placeholder',
	} );

	const hash = window.location.hash.replace( '#adam-form-', '' );
	if ( hash ) {
		$( '[data-adam-form-tab="' + hash + '"]' ).trigger( 'click' );
	}
}( jQuery ) );
