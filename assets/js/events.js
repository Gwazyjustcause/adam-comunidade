( function () {
	'use strict';

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'ArrowLeft' === event.key ) {
			document.querySelector( '.adam-events__calendar header a:first-child' )?.click();
		}
		if ( 'ArrowRight' === event.key ) {
			document.querySelector( '.adam-events__calendar header a:last-child' )?.click();
		}
	} );
}() );
