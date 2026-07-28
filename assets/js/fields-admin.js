( function ( $, wp ) {
	'use strict';

	const config = window.adamFieldsAdmin || {};

	function slugify( value ) {
		return value.toString().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-adam-field-tab]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const tab = button.dataset.adamFieldTab;
				document.querySelectorAll( '[data-adam-field-tab]' ).forEach( ( item ) => {
					item.setAttribute( 'aria-selected', item === button ? 'true' : 'false' );
				} );
				document.querySelectorAll( '.adam-field-panel' ).forEach( ( panel ) => {
					panel.hidden = panel.id !== `adam-field-panel-${ tab }`;
				} );
			} );
		} );

		const name = document.getElementById( 'adam-field-name' );
		const slug = document.getElementById( 'adam-field-slug' );
		if ( name && slug ) {
			let edited = Boolean( slug.value );
			slug.addEventListener( 'input', () => { edited = true; } );
			name.addEventListener( 'input', () => {
				if ( ! edited ) {
					slug.value = slugify( name.value );
				}
			} );
		}

		document.querySelectorAll( '.adam-field-delete' ).forEach( ( link ) => {
			link.addEventListener( 'click', ( event ) => {
				event.preventDefault();
				const confirmAction = window.adamConfirm
					? window.adamConfirm( config.confirmDelete )
					: Promise.resolve( window.confirm( config.confirmDelete ) );
				confirmAction.then( ( confirmed ) => {
					if ( confirmed ) {
						window.location.assign( link.href );
					}
				} );
			} );
		} );
	} );
}( jQuery, wp ) );
