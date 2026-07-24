( function ( $, wp ) {
	'use strict';

	const config = window.adamFieldsAdmin || {};

	function slugify( value ) {
		return value.toString().normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' )
			.toLowerCase().trim().replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' );
	}

	function imageUrl( attachment, size ) {
		const sizes = attachment.get( 'sizes' ) || {};
		return sizes[ size ]?.url || sizes.medium?.url || attachment.get( 'url' );
	}

	function openCover( field ) {
		const frame = wp.media( {
			title: config.coverTitle,
			button: { text: config.useImage },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first();
			field.querySelector( 'input[type="hidden"]' ).value = attachment.get( 'id' );
			field.querySelector( '.adam-media-preview' ).innerHTML =
				`<img src="${ imageUrl( attachment, 'large' ) }" alt="">`;
		} );
		frame.open();
	}

	function galleryItem( attachment ) {
		const id = attachment.get( 'id' );
		const caption = attachment.get( 'caption' ) || '';
		const item = document.createElement( 'div' );
		item.className = 'adam-field-gallery-item';
		item.dataset.attachmentId = id;

		const hidden = document.createElement( 'input' );
		hidden.type = 'hidden';
		hidden.name = 'field[gallery_ids][]';
		hidden.value = id;

		const image = document.createElement( 'img' );
		image.src = imageUrl( attachment, 'thumbnail' );
		image.alt = '';

		const captionInput = document.createElement( 'input' );
		captionInput.type = 'text';
		captionInput.name = `field[gallery_captions][${ id }]`;
		captionInput.value = caption;
		captionInput.placeholder = config.caption;

		const remove = document.createElement( 'button' );
		remove.type = 'button';
		remove.className = 'adam-field-gallery-remove';
		remove.setAttribute( 'aria-label', config.removeImage );
		remove.innerHTML = '&times;';
		item.append( hidden, image, captionInput, remove );

		return item;
	}

	function openGallery( field ) {
		const frame = wp.media( {
			title: config.galleryTitle,
			button: { text: config.useImages },
			library: { type: 'image' },
			multiple: 'add',
		} );

		frame.on( 'select', () => {
			const list = field.querySelector( '.adam-field-gallery-list' );
			frame.state().get( 'selection' ).each( ( attachment ) => {
				if ( ! list.querySelector( `[data-attachment-id="${ attachment.get( 'id' ) }"]` ) ) {
					list.appendChild( galleryItem( attachment ) );
				}
			} );
		} );
		frame.open();
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

		document.querySelectorAll( '.adam-field-media-select' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const field = button.closest( '[data-adam-field-media]' );
				if ( 'gallery' === button.dataset.kind ) {
					openGallery( field );
				} else {
					openCover( field );
				}
			} );
		} );

		document.querySelectorAll( '.adam-field-media-remove' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const field = button.closest( '[data-adam-field-media]' );
				field.querySelector( 'input[type="hidden"]' ).value = '0';
				field.querySelector( '.adam-media-preview' ).innerHTML = '';
			} );
		} );

		document.addEventListener( 'click', ( event ) => {
			const remove = event.target.closest( '.adam-field-gallery-remove' );
			if ( remove ) {
				remove.closest( '.adam-field-gallery-item' ).remove();
			}
		} );
		$( '.adam-field-gallery-list' ).sortable( { items: '.adam-field-gallery-item' } );

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
				if ( ! window.confirm( config.confirmDelete ) ) {
					event.preventDefault();
				}
			} );
		} );
	} );
}( jQuery, wp ) );
