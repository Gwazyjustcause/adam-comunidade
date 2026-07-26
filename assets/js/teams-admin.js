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

	function imageUrl( attachment, size ) {
		const sizes = attachment.get( 'sizes' ) || {};
		return sizes[ size ]?.url || sizes.medium?.url || attachment.get( 'url' );
	}

	function singleMediaFrame( field, kind ) {
		const frame = wp.media( {
			title: kind === 'logo' ? config.logoTitle : config.coverTitle,
			button: { text: config.useImage },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first();
			const size = kind === 'logo' ? 'thumbnail' : 'large';
			field.querySelector( 'input[type="hidden"]' ).value = attachment.get( 'id' );
			field.querySelector( '.adam-media-preview' ).innerHTML = `<img src="${ imageUrl( attachment, size ) }" alt="">`;
		} );
		frame.open();
	}

	function galleryMediaFrame( field ) {
		const frame = wp.media( {
			title: config.galleryTitle,
			button: { text: config.useImages },
			library: { type: 'image' },
			multiple: 'add',
		} );

		frame.on( 'select', () => {
			const list = field.querySelector( '.adam-gallery-list' );
			frame.state().get( 'selection' ).each( ( attachment ) => {
				const id = attachment.get( 'id' );
				if ( list.querySelector( `[data-attachment-id="${ id }"]` ) ) {
					return;
				}
				const item = document.createElement( 'div' );
				item.className = 'adam-gallery-item';
				item.dataset.attachmentId = id;
				item.innerHTML = [
					`<input type="hidden" name="team[gallery][]" value="${ id }">`,
					`<img src="${ imageUrl( attachment, 'thumbnail' ) }" alt="">`,
					'<button type="button" class="adam-gallery-remove" aria-label="Remover imagem">&times;</button>',
				].join( '' );
				list.appendChild( item );
			} );
		} );
		frame.open();
	}

	function slugify( value ) {
		return value.toString().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-adam-tab]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => activateTab( button ) );
		} );

		document.querySelectorAll( '.adam-media-select' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const field = button.closest( '[data-adam-media]' );
				if ( button.dataset.mediaKind === 'gallery' ) {
					galleryMediaFrame( field );
				} else {
					singleMediaFrame( field, button.dataset.mediaKind );
				}
			} );
		} );

		document.querySelectorAll( '.adam-media-remove' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const field = button.closest( '[data-adam-media]' );
				field.querySelector( 'input[type="hidden"]' ).value = '0';
				field.querySelector( '.adam-media-preview' ).innerHTML = '';
			} );
		} );

		document.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest( '.adam-gallery-remove' );
			if ( remove ) {
				remove.closest( '.adam-gallery-item' ).remove();
			}
		} );

		$( '.adam-gallery-list' ).sortable( { items: '.adam-gallery-item' } );
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
				if ( ! window.confirm( config.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
}( jQuery, wp ) );
