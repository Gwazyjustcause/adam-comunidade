( function ( $ ) {
	'use strict';

	$( function () {
		if ( $.fn.wpColorPicker ) {
			$( '.adam-comunidade-colour' ).wpColorPicker();
		}

		$( document ).on( 'submit', '.adam-managers-admin form', function ( event ) {
			const form = this;
			const message = form.dataset.adamConfirm || '';
			const strategy = form.querySelector( '[name="assignment_action"]' );
			const target = form.querySelector( '[name="target_manager_id"]' );

			if ( strategy && 'transfer' === strategy.value && target && ! target.value ) {
				event.preventDefault();
				window.alert( 'Selecione o Gestor que irá receber as organizações.' );
				target.focus();
				return;
			}
			if ( message && ! window.confirm( message ) ) {
				event.preventDefault();
				return;
			}
			form.setAttribute( 'aria-busy', 'true' );
			const button = form.querySelector( 'button[type="submit"]' );
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
