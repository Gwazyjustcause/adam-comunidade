( function () {
	'use strict';

	const labels = window.adamPublicUx || {};

	function announce( portal, message ) {
		const region = portal.querySelector( '[data-adam-manager-live]' );
		if ( region ) {
			region.textContent = '';
			window.requestAnimationFrame( () => {
				region.textContent = message;
			} );
		}
	}

	function enhancePasswords( portal ) {
		portal.querySelectorAll( 'input[type="password"]' ).forEach( ( input ) => {
			if ( input.parentElement?.classList.contains( 'adam-password-control' ) ) {
				return;
			}
			const control = document.createElement( 'span' );
			control.className = 'adam-password-control';
			input.parentNode.insertBefore( control, input );
			control.appendChild( input );
			const toggle = document.createElement( 'button' );
			toggle.className = 'adam-password-toggle';
			toggle.type = 'button';
			toggle.textContent = labels.showPassword || '';
			toggle.setAttribute( 'aria-pressed', 'false' );
			toggle.addEventListener( 'click', () => {
				const visible = 'password' === input.type;
				input.type = visible ? 'text' : 'password';
				toggle.textContent = visible ? labels.hidePassword : labels.showPassword;
				toggle.setAttribute( 'aria-pressed', visible ? 'true' : 'false' );
				input.focus();
			} );
			control.appendChild( toggle );
		} );
	}

	function enhanceEditor( form ) {
		const state = form.querySelector( '[data-adam-save-state]' );
		if ( ! state ) {
			return;
		}
		const markDirty = () => {
			if ( 'true' === form.dataset.adamDirty ) {
				return;
			}
			form.dataset.adamDirty = 'true';
			state.textContent = labels.unsaved || '';
			state.classList.add( 'is-dirty' );
		};
		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );
		state.textContent = labels.saved || '';
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		const portal = document.querySelector( '.adam-manager-portal' );
		if ( ! portal ) {
			return;
		}
		portal.querySelectorAll( '.adam-manager-notice' ).forEach( ( notice ) => notice.setAttribute( 'role', 'status' ) );
		enhancePasswords( portal );
		portal.querySelectorAll( '[data-adam-manager-editor]' ).forEach( enhanceEditor );
	} );

	document.addEventListener( 'submit', ( event ) => {
		const form = event.target;
		if ( ! ( form instanceof HTMLFormElement ) || ! form.closest( '.adam-manager-portal' ) ) {
			return;
		}
		form.dataset.adamDirty = 'false';
		form.setAttribute( 'aria-busy', 'true' );
		const button = event.submitter || form.querySelector( 'button[type="submit"]' );
		if ( button ) {
			button.disabled = true;
			button.dataset.originalLabel = button.textContent;
			button.textContent = labels.processing || '…';
		}
	} );

	let draggedGalleryItem = null;
	document.addEventListener( 'dragstart', ( event ) => {
		if ( ! ( event.target instanceof Element ) ) {
			return;
		}
		const item = event.target.closest( '[data-adam-current-gallery] > label' );
		if ( item ) {
			draggedGalleryItem = item;
			item.classList.add( 'is-dragging' );
		}
	} );
	document.addEventListener( 'dragover', ( event ) => {
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
	document.addEventListener( 'dragend', () => {
		if ( draggedGalleryItem ) {
			const portal = draggedGalleryItem.closest( '.adam-manager-portal' );
			draggedGalleryItem.classList.remove( 'is-dragging' );
			draggedGalleryItem.focus();
			if ( portal ) {
				announce( portal, labels.reordered || '' );
			}
			draggedGalleryItem = null;
		}
	} );
	document.addEventListener( 'keydown', ( event ) => {
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
		const portal = item.closest( '.adam-manager-portal' );
		if ( portal ) {
			announce( portal, labels.reordered || '' );
		}
	} );

	document.dispatchEvent( new CustomEvent( 'adamComunidadeReady' ) );
}() );
