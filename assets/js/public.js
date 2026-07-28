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

	let draggedGalleryItem = null;
	document.addEventListener( 'dragstart', function ( event ) {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}
		const item = event.target.closest( '[data-adam-current-gallery] > label' );
		if ( item ) {
			draggedGalleryItem = item;
			item.classList.add( 'is-dragging' );
		}
	} );
	document.addEventListener( 'dragover', function ( event ) {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}
		const item = event.target.closest( '[data-adam-current-gallery] > label' );
		if ( ! item || ! draggedGalleryItem || item === draggedGalleryItem ) {
			return;
		}
		event.preventDefault();
		const bounds = item.getBoundingClientRect();
		item.parentElement.insertBefore( draggedGalleryItem, event.clientX < bounds.left + bounds.width / 2 ? item : item.nextSibling );
	} );
	document.addEventListener( 'dragend', function () {
		if ( draggedGalleryItem ) {
			draggedGalleryItem.classList.remove( 'is-dragging' );
			draggedGalleryItem.focus();
			draggedGalleryItem = null;
		}
	} );
	document.addEventListener( 'keydown', function ( event ) {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}
		const item = event.target.closest( '[data-adam-current-gallery] > label' );
		if ( ! item || ! event.altKey || ! [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
			return;
		}
		const sibling = 'ArrowLeft' === event.key ? item.previousElementSibling : item.nextElementSibling;
		if ( ! sibling ) {
			return;
		}
		event.preventDefault();
		item.parentElement.insertBefore( item, 'ArrowLeft' === event.key ? sibling : sibling.nextSibling );
		item.focus();
	} );

	document.dispatchEvent( new CustomEvent( 'adamComunidadeReady' ) );
}() );
