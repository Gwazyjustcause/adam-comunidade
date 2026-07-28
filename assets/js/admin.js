( function ( $ ) {
	'use strict';

	const labels = window.adamAdminUx || {};

	function feedback( form, message, target ) {
		let notice = form.querySelector( '[data-adam-form-feedback]' );
		if ( ! notice ) {
			notice = document.createElement( 'p' );
			notice.className = 'adam-form-feedback adam-form-feedback--error';
			notice.dataset.adamFormFeedback = '';
			notice.setAttribute( 'role', 'alert' );
			form.prepend( notice );
		}
		notice.textContent = message;
		if ( target ) {
			target.setAttribute( 'aria-invalid', 'true' );
			target.focus();
		}
	}

	function confirmation( message ) {
		if ( ! ( 'HTMLDialogElement' in window ) ) {
			return Promise.resolve( window.confirm( message ) );
		}

		return new Promise( ( resolve ) => {
			const dialog = document.createElement( 'dialog' );
			dialog.className = 'adam-confirm-dialog';
			const form = document.createElement( 'form' );
			form.method = 'dialog';
			const icon = document.createElement( 'div' );
			icon.className = 'adam-confirm-dialog__icon';
			icon.setAttribute( 'aria-hidden', 'true' );
			icon.textContent = '!';
			const title = document.createElement( 'h2' );
			title.id = 'adam-confirm-dialog-title';
			title.textContent = labels.confirmTitle || '';
			const description = document.createElement( 'p' );
			description.id = 'adam-confirm-dialog-description';
			description.textContent = message;
			dialog.setAttribute( 'aria-labelledby', title.id );
			dialog.setAttribute( 'aria-describedby', description.id );
			const actions = document.createElement( 'div' );
			actions.className = 'adam-confirm-dialog__actions';
			const cancel = document.createElement( 'button' );
			cancel.className = 'button';
			cancel.value = 'cancel';
			cancel.textContent = labels.cancel || '';
			const confirm = document.createElement( 'button' );
			confirm.className = 'button button-primary';
			confirm.value = 'confirm';
			confirm.textContent = labels.confirmAction || '';
			actions.append( cancel, confirm );
			form.append( icon, title, description, actions );
			dialog.appendChild( form );
			dialog.addEventListener( 'close', () => {
				const confirmed = 'confirm' === dialog.returnValue;
				dialog.remove();
				resolve( confirmed );
			}, { once: true } );
			document.body.appendChild( dialog );
			dialog.showModal();
		} );
	}
	window.adamConfirm = confirmation;

	function updateEntityPicker( picker ) {
		const query = picker.querySelector( '[data-adam-entity-search]' )?.value.trim().toLocaleLowerCase( 'pt-PT' ) || '';
		const choices = Array.from( picker.querySelectorAll( '[data-adam-entity-option]' ) );
		let visible = 0;
		choices.forEach( ( choice ) => {
			const matches = ! query || choice.textContent.toLocaleLowerCase( 'pt-PT' ).includes( query );
			choice.hidden = ! matches;
			visible += matches ? 1 : 0;
		} );
		picker.querySelectorAll( '[data-adam-entity-group]' ).forEach( ( group ) => {
			group.hidden = ! group.querySelector( '[data-adam-entity-option]:not([hidden])' );
		} );
		const selected = choices.filter( ( choice ) => choice.querySelector( 'input' )?.checked ).length;
		const counter = picker.querySelector( '[data-adam-entity-count]' );
		if ( counter ) {
			counter.textContent = 0 === selected
				? labels.noneSelected
				: ( 1 === selected ? labels.selectedOne : String( labels.selectedMany || '' ).replace( '%d', selected ) );
		}
		const empty = picker.querySelector( '[data-adam-entity-empty]' );
		if ( empty ) {
			empty.hidden = visible > 0;
		}
	}

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.adam-comunidade-colour' ).wpColorPicker();
		}

		document.querySelectorAll( '.adam-comunidade-admin .notice' ).forEach( ( notice ) => {
			notice.setAttribute( 'role', notice.classList.contains( 'notice-error' ) ? 'alert' : 'status' );
		} );

		document.querySelectorAll( '[data-adam-entity-picker]' ).forEach( ( picker ) => {
			updateEntityPicker( picker );
			picker.addEventListener( 'input', () => updateEntityPicker( picker ) );
			picker.addEventListener( 'change', () => updateEntityPicker( picker ) );
		} );

		$( document ).on( 'submit', '.adam-comunidade-admin form', function ( event ) {
			const form = this;
			const submitter = event.originalEvent && event.originalEvent.submitter
				? event.originalEvent.submitter
				: form.querySelector( 'button[type="submit"]:focus' );
			const message = ( submitter && submitter.dataset.adamConfirm ) || form.dataset.adamConfirm || '';
			const strategy = form.querySelector( '[name="assignment_action"]' );
			const target = form.querySelector( '[name="target_manager_id"]' );
			const decision = submitter && submitter.name === 'decision' ? submitter.value : '';
			const note = form.querySelector( '[name="admin_note"]' );
			const conflict = form.querySelector( '[name="confirm_conflict"]' );
			const entityPicker = form.querySelector( '[data-adam-entity-picker]' );

			form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( field ) => field.removeAttribute( 'aria-invalid' ) );
			form.querySelector( '[data-adam-form-feedback]' )?.remove();

			if ( strategy && 'transfer' === strategy.value && target && ! target.value ) {
				event.preventDefault();
				feedback( form, labels.transferRequired || '', target );
				return;
			}
			if ( [ 'reject', 'info', 'changes' ].includes( decision ) && note && ! note.value.trim() ) {
				event.preventDefault();
				feedback( form, labels.noteRequired || '', note );
				return;
			}
			if ( 'approve' === decision && conflict && ! conflict.checked ) {
				event.preventDefault();
				feedback( form, labels.conflictRequired || '', conflict );
				return;
			}
			if ( entityPicker && ! entityPicker.querySelector( 'input[name="entities[]"]:checked' ) ) {
				event.preventDefault();
				const search = entityPicker.querySelector( '[data-adam-entity-search]' );
				feedback( form, labels.entityRequired || '', search );
				return;
			}
			if ( message && ! form.dataset.adamConfirmed ) {
				event.preventDefault();
				confirmation( message ).then( ( confirmed ) => {
					if ( confirmed ) {
						form.dataset.adamConfirmed = 'true';
						form.requestSubmit( submitter || undefined );
					}
				} );
				return;
			}
			delete form.dataset.adamConfirmed;
			form.setAttribute( 'aria-busy', 'true' );
			const button = submitter || form.querySelector( 'button[type="submit"]' );
			if ( button ) {
				button.disabled = true;
				button.dataset.originalLabel = button.textContent;
				button.textContent = labels.processing || '…';
			}
		} );

		$( document ).on( 'click', '[data-adam-close-details]', function () {
			const details = this.closest( 'details' );
			if ( details ) {
				details.open = false;
				details.querySelector( 'summary' )?.focus();
			}
		} );
	} );
}( jQuery ) );
