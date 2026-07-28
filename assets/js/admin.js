( function ( $ ) {
	'use strict';

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.adam-comunidade-colour' ).wpColorPicker();
		}

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

			if ( strategy && 'transfer' === strategy.value && target && ! target.value ) {
				event.preventDefault();
				window.alert( 'Selecione o Gestor que irá receber as organizações.' );
				target.focus();
				return;
			}
			if ( [ 'reject', 'info', 'changes' ].includes( decision ) && note && ! note.value.trim() ) {
				event.preventDefault();
				window.alert( 'Indique ao Gestor o motivo da decisão ou a informação necessária.' );
				note.focus();
				return;
			}
			if ( 'approve' === decision && conflict && ! conflict.checked ) {
				event.preventDefault();
				window.alert( 'Confirme que reviu o conflito com a versão publicada.' );
				conflict.focus();
				return;
			}
			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
				return;
			}
			form.setAttribute( 'aria-busy', 'true' );
			const button = submitter || form.querySelector( 'button[type="submit"]' );
			if ( button ) {
				button.disabled = true;
				button.dataset.originalLabel = button.textContent;
				button.textContent = 'A processar…';
			}
		} );

		$( document ).on( 'input', '[data-adam-entity-search]', function () {
			const query = this.value.trim().toLocaleLowerCase( 'pt-PT' );
			const select = this.parentElement.querySelector( 'select[multiple]' );
			if ( ! select ) {
				return;
			}
			Array.from( select.options ).forEach( function ( option ) {
				const matches = ! query || option.textContent.toLocaleLowerCase( 'pt-PT' ).includes( query );
				option.hidden = ! matches;
			} );
			Array.from( select.querySelectorAll( 'optgroup' ) ).forEach( function ( group ) {
				group.hidden = ! Array.from( group.children ).some( function ( option ) {
					return ! option.hidden;
				} );
			} );
		} );

		$( document ).on( 'click', '[data-adam-close-details]', function () {
			const details = this.closest( 'details' );
			if ( details ) {
				details.open = false;
			}
		} );
	} );
}( jQuery ) );
