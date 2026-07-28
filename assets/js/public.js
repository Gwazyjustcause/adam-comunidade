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

	function passwordIcon( visible ) {
		const namespace = 'http://www.w3.org/2000/svg';
		const svg = document.createElementNS( namespace, 'svg' );
		svg.setAttribute( 'viewBox', '0 0 24 24' );
		svg.setAttribute( 'aria-hidden', 'true' );
		svg.setAttribute( 'focusable', 'false' );

		const outline = document.createElementNS( namespace, 'path' );
		outline.setAttribute( 'd', 'M2.5 12s3.5-6.5 9.5-6.5 9.5 6.5 9.5 6.5-3.5 6.5-9.5 6.5S2.5 12 2.5 12Z' );
		const pupil = document.createElementNS( namespace, 'circle' );
		pupil.setAttribute( 'cx', '12' );
		pupil.setAttribute( 'cy', '12' );
		pupil.setAttribute( 'r', '2.75' );
		svg.append( outline, pupil );

		if ( visible ) {
			const slash = document.createElementNS( namespace, 'path' );
			slash.setAttribute( 'd', 'M4 4l16 16' );
			svg.appendChild( slash );
		}
		return svg;
	}

	function enhancePasswords( portal ) {
		portal.querySelectorAll( 'input[type="password"]' ).forEach( ( input, index ) => {
			if ( input.parentElement?.classList.contains( 'adam-password-control' ) ) {
				return;
			}
			if ( ! input.id ) {
				input.id = 'adam-manager-password-' + ( index + 1 );
			}
			const control = document.createElement( 'span' );
			control.className = 'adam-password-control';
			input.parentNode.insertBefore( control, input );
			control.appendChild( input );
			const toggle = document.createElement( 'button' );
			toggle.className = 'adam-password-toggle';
			toggle.type = 'button';
			toggle.setAttribute( 'aria-pressed', 'false' );
			toggle.setAttribute( 'aria-controls', input.id );
			const updateToggle = ( visible ) => {
				const label = visible ? labels.hidePassword : labels.showPassword;
				toggle.setAttribute( 'aria-label', label || '' );
				toggle.title = label || '';
				toggle.setAttribute( 'aria-pressed', visible ? 'true' : 'false' );
				toggle.replaceChildren( passwordIcon( visible ) );
			};
			updateToggle( false );
			toggle.addEventListener( 'click', () => {
				const visible = 'password' === input.type;
				input.type = visible ? 'text' : 'password';
				updateToggle( visible );
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
