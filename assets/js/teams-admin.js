( function ( $, wp ) {
	'use strict';

	const config = window.adamTeamsAdmin || {};

	function activateTab( button ) {
		const tab = button.dataset.adamTab;

		document.querySelectorAll( '[data-adam-tab]' ).forEach( ( item ) => {
			item.setAttribute( 'aria-selected', item === button ? 'true' : 'false' );
		} );
		document.querySelectorAll( '.adam-editor-panel' ).forEach( ( panel ) => {
			panel.hidden = panel.id !== `adam-team-panel-${ tab }`;
		} );
	}

	function slugify( value ) {
		return value.toString().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-adam-tab]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => activateTab( button ) );
		} );

		$( '.adam-team-colour' ).wpColorPicker();

		const name = document.getElementById( 'adam-team-name' );
		const slug = document.getElementById( 'adam-team-slug' );
		if ( name && slug ) {
			let slugEdited = Boolean( slug.value );
			slug.addEventListener( 'input', () => { slugEdited = true; } );
			name.addEventListener( 'input', () => {
				if ( ! slugEdited ) {
					slug.value = slugify( name.value );
				}
			} );
		}

		document.querySelectorAll( '.adam-team-delete' ).forEach( ( link ) => {
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
