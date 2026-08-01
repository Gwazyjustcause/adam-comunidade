( function () {
	'use strict';

	const labels = window.adamUpload || {};

	function format( template, first, second ) {
		return String( template || '' )
			.replace( '%1$d', first )
			.replace( '%2$d', second )
			.replace( '%d', first );
	}

	function humanSize( bytes ) {
		if ( ! bytes ) {
			return labels.unknownSize || '';
		}
		const units = [ 'B', 'KB', 'MB', 'GB' ];
		let value = bytes;
		let unit = 0;
		while ( value >= 1024 && unit < units.length - 1 ) {
			value /= 1024;
			unit++;
		}
		return `${ value >= 10 || unit === 0 ? value.toFixed( 0 ) : value.toFixed( 1 ) } ${ units[ unit ] }`;
	}

	function extension( filename ) {
		const parts = String( filename || '' ).split( '.' );
		return parts.length > 1 ? parts.pop().toUpperCase() : '';
	}

	function imageUrl( attachment ) {
		const sizes = attachment.sizes || {};
		return sizes.thumbnail?.url || sizes.medium?.url || attachment.url || '';
	}

	function init( upload ) {
		const mode = upload.dataset.mode;
		const kind = upload.dataset.kind;
		const max = parseInt( upload.dataset.max || '1', 10 );
		const multiple = max > 1;
		const list = upload.querySelector( '[data-adam-upload-list]' );
		const add = upload.querySelector( '[data-adam-upload-add]' );
		const count = upload.querySelector( '[data-adam-upload-count]' );
		const input = upload.querySelector( '[data-adam-upload-input]' );
		const singleValue = upload.querySelector( '[data-adam-upload-value]' );
		const progress = upload.querySelector( '[data-adam-upload-progress]' );
		const live = upload.querySelector( '[data-adam-upload-live]' );
		let files = [];
		let replaceIndex = null;
		let dragged = null;
		const existingCount = () => parseInt( upload.dataset.existingCount || '0', 10 );

		function countLabel() {
			return format( 'image' === kind ? labels.imageCount : labels.documentCount, existingCount() + list.querySelectorAll( '[data-adam-upload-item]' ).length, max );
		}

		function refresh() {
			const items = Array.from( list.querySelectorAll( '[data-adam-upload-item]' ) );
			const total = existingCount() + items.length;
			count.textContent = countLabel();
			upload.classList.toggle( 'is-full', total >= max );
			add.hidden = total >= max;
			items.forEach( ( item, index ) => {
				const earlier = item.querySelector( '[data-adam-upload-move="-1"]' );
				const later = item.querySelector( '[data-adam-upload-move="1"]' );
				if ( earlier ) {
					earlier.disabled = 0 === index;
				}
				if ( later ) {
					later.disabled = index === total - 1;
				}
			} );
		}

		function announce( message ) {
			if ( live ) {
				live.textContent = '';
				window.requestAnimationFrame( () => {
					live.textContent = message;
				} );
			}
		}

		function clearClientError() {
			upload.querySelectorAll( '.adam-upload__error--client' ).forEach( ( error ) => error.remove() );
			if ( input ) {
				input.setCustomValidity( '' );
				input.removeAttribute( 'aria-invalid' );
				if ( ( input.getAttribute( 'aria-describedby' ) || '' ).includes( '-client-error' ) ) {
					input.removeAttribute( 'aria-describedby' );
				}
			}
		}

		function showClientError( message ) {
			clearClientError();
			const error = document.createElement( 'span' );
			error.className = 'adam-field-error adam-upload__error--client';
			error.id = `${ upload.id || 'adam-upload' }-client-error`;
			error.setAttribute( 'role', 'alert' );
			error.textContent = message;
			upload.appendChild( error );
			if ( input ) {
				input.setCustomValidity( message );
				input.setAttribute( 'aria-invalid', 'true' );
				input.setAttribute( 'aria-describedby', error.id );
			}
		}

		function accepted( file ) {
			const accept = String( upload.dataset.accept || '' ).split( ',' ).map( ( value ) => value.trim().toLowerCase() ).filter( Boolean );
			if ( accept.length ) {
				const suffix = `.${ String( file.name || '' ).split( '.' ).pop().toLowerCase() }`;
				const mime = String( file.type || '' ).toLowerCase();
				const valid = accept.some( ( rule ) => {
					if ( rule.endsWith( '/*' ) ) {
						return mime.startsWith( rule.slice( 0, -1 ) );
					}
					return rule.startsWith( '.' ) ? suffix === rule : mime === rule;
				} );
				if ( ! valid ) {
					showClientError( labels.invalidType || '' );
					return false;
				}
			}
			if ( file.size > parseInt( upload.dataset.maxSize || '10', 10 ) * 1024 * 1024 ) {
				showClientError( format( labels.tooLarge, upload.dataset.maxSize ) );
				return false;
			}
			return true;
		}

		function itemElement( item, index, isLocal ) {
			const card = document.createElement( 'article' );
			card.className = 'adam-upload__item';
			card.dataset.adamUploadItem = '';
			card.dataset.index = String( index );
			card.dataset.id = item.id || '';
			card.draggable = multiple;
			card.setAttribute( 'role', 'listitem' );
			if ( multiple ) {
				card.tabIndex = 0;
				card.setAttribute( 'aria-label', `${ item.filename }. ${ labels.dragHint || '' }` );
			}

			if ( 'library' === mode && multiple ) {
				const hidden = document.createElement( 'input' );
				hidden.type = 'hidden';
				hidden.dataset.adamUploadItemValue = '';
				hidden.name = upload.dataset.name;
				hidden.value = item.id;
				card.appendChild( hidden );
			}

			const preview = document.createElement( 'div' );
			preview.className = 'adam-upload__preview';
			if ( 'image' === kind ) {
				const image = document.createElement( 'img' );
				image.src = item.url;
				image.alt = '';
				preview.appendChild( image );
			} else {
				const icon = document.createElement( 'span' );
				icon.className = 'adam-upload__document-icon';
				icon.setAttribute( 'aria-hidden', 'true' );
				icon.textContent = '📄';
				preview.appendChild( icon );
			}

			const actions = document.createElement( 'div' );
			actions.className = 'adam-upload__actions';
			const replace = document.createElement( 'button' );
			replace.type = 'button';
			replace.dataset.adamUploadReplace = '';
			replace.textContent = labels.replace;
			replace.setAttribute( 'aria-label', `${ labels.replace } ${ item.filename }` );
			const removeHover = document.createElement( 'button' );
			removeHover.type = 'button';
			removeHover.dataset.adamUploadRemove = '';
			removeHover.textContent = labels.remove;
			removeHover.setAttribute( 'aria-label', `${ labels.remove } ${ item.filename }` );
			actions.append( replace, removeHover );
			preview.appendChild( actions );
			card.appendChild( preview );

			const meta = document.createElement( 'div' );
			meta.className = 'adam-upload__meta';
			const filename = document.createElement( 'strong' );
			filename.textContent = item.filename;
			filename.title = item.filename;
			const detail = document.createElement( 'small' );
			detail.textContent = `✓ ${ item.type || extension( item.filename ) || labels.file || '' }${ item.size ? ` · ${ item.size }` : '' }`;
			meta.append( filename, detail );
			if ( multiple ) {
				const order = document.createElement( 'div' );
				order.className = 'adam-upload__order';
				[ [ -1, '←', labels.moveEarlier ], [ 1, '→', labels.moveLater ] ].forEach( ( control ) => {
					const button = document.createElement( 'button' );
					button.type = 'button';
					button.dataset.adamUploadMove = String( control[ 0 ] );
					button.textContent = control[ 1 ];
					button.setAttribute( 'aria-label', `${ control[ 2 ] || '' } ${ item.filename }` );
					order.appendChild( button );
				} );
				meta.appendChild( order );
			}

			const captionPattern = upload.dataset.captionPattern;
			if ( 'library' === mode && multiple && captionPattern ) {
				const caption = document.createElement( 'input' );
				caption.type = 'text';
				caption.name = captionPattern.replace( '__ID__', item.id );
				caption.value = item.caption || '';
				caption.placeholder = labels.caption || '';
				meta.appendChild( caption );
			}
			const togglePattern = upload.dataset.togglePattern;
			if ( 'library' === mode && multiple && togglePattern ) {
				const toggle = document.createElement( 'label' );
				toggle.className = 'adam-upload__toggle';
				const checkbox = document.createElement( 'input' );
				checkbox.type = 'checkbox';
				checkbox.name = togglePattern.replace( '__ID__', item.id );
				checkbox.value = '1';
				checkbox.checked = true;
				toggle.append( checkbox, document.createTextNode( ` ${ upload.dataset.toggleLabel || '' }` ) );
				meta.appendChild( toggle );
			}
			card.appendChild( meta );

			const remove = document.createElement( 'button' );
			remove.type = 'button';
			remove.className = 'adam-upload__remove';
			remove.dataset.adamUploadRemove = '';
			remove.setAttribute( 'aria-label', `${ labels.remove } ${ item.filename }` );
			remove.textContent = '×';
			card.appendChild( remove );
			if ( isLocal ) {
				card._adamFile = item.file; // DOM-only reference; never serialized.
			}
			return card;
		}

		function syncFileInput() {
			if ( ! input || 'undefined' === typeof DataTransfer ) {
				return;
			}
			const transfer = new DataTransfer();
			files.forEach( ( item ) => transfer.items.add( item.file ) );
			input.files = transfer.files;
		}

		function renderFiles() {
			list.querySelectorAll( '[data-adam-upload-item]' ).forEach( ( item ) => item.remove() );
			files.forEach( ( item, index ) => list.insertBefore( itemElement( item, index, true ), add ) );
			syncFileInput();
			refresh();
		}

		function addFiles( selected ) {
			clearClientError();
			const signatures = new Set( files.map( ( item ) => `${ item.filename }|${ item.file.size }|${ item.file.lastModified }` ) );
			let duplicate = false;
			const incoming = Array.from( selected || [] ).filter( accepted ).filter( ( file ) => {
				const signature = `${ file.name }|${ file.size }|${ file.lastModified }`;
				if ( null === replaceIndex && signatures.has( signature ) ) {
					duplicate = true;
					return false;
				}
				signatures.add( signature );
				return true;
			} );
			if ( ! incoming.length ) {
				if ( duplicate ) {
					showClientError( labels.duplicate || '' );
					input?.setCustomValidity( '' );
				}
				return;
			}
			// Rejected files are not added, so valid files can still be submitted.
			input?.setCustomValidity( '' );
			const mapped = incoming.map( ( file ) => ( {
				file,
				filename: file.name,
				size: humanSize( file.size ),
				type: extension( file.name ),
				url: 'image' === kind ? URL.createObjectURL( file ) : '',
			} ) );
			if ( null !== replaceIndex ) {
				const old = files[ replaceIndex ];
				if ( old?.url ) {
					URL.revokeObjectURL( old.url );
				}
				files.splice( replaceIndex, 1, mapped[ 0 ] );
				mapped.slice( 1 ).forEach( ( item ) => item.url && URL.revokeObjectURL( item.url ) );
				replaceIndex = null;
			} else {
				const remaining = Math.max( 0, max - existingCount() - files.length );
				if ( mapped.length > remaining ) {
					mapped.slice( remaining ).forEach( ( item ) => item.url && URL.revokeObjectURL( item.url ) );
					showClientError( format( labels.limit, max ) );
					input?.setCustomValidity( '' );
				}
				files.push( ...mapped.slice( 0, remaining ) );
			}
			renderFiles();
			if ( duplicate ) {
				showClientError( labels.duplicate || '' );
				input?.setCustomValidity( '' );
			}
			announce( labels.added || '' );
		}

		function moveItem( item, direction ) {
			const items = Array.from( list.querySelectorAll( '[data-adam-upload-item]' ) );
			const from = items.indexOf( item );
			const to = from + direction;
			if ( from < 0 || to < 0 || to >= items.length ) {
				return;
			}
			if ( 'file' === mode ) {
				[ files[ from ], files[ to ] ] = [ files[ to ], files[ from ] ];
				renderFiles();
				list.querySelectorAll( '[data-adam-upload-item]' )[ to ]?.focus();
			} else {
				const reference = direction < 0 ? items[ to ] : items[ to ].nextSibling;
				list.insertBefore( item, reference );
				item.focus();
				refresh();
			}
			announce( labels.reordered || '' );
		}

		function attachmentItem( model ) {
			const item = model.toJSON ? model.toJSON() : model;
			return {
				id: item.id,
				url: imageUrl( item ),
				filename: item.filename || item.title || '',
				type: String( item.subtype || extension( item.filename ) ).toUpperCase(),
				size: item.filesizeHumanReadable || '',
				caption: item.caption || '',
			};
		}

		function openLibrary( target ) {
			if ( ! window.wp?.media ) {
				return;
			}
			const replacing = target || null;
			const library = {};
			if ( 'image' === kind ) {
				library.type = 'image';
			} else if ( '.pdf' === String( upload.dataset.accept || '' ).trim().toLowerCase() ) {
				library.type = 'application/pdf';
			}
			const frame = window.wp.media( {
				title: labels.mediaTitle,
				button: { text: labels.useMedia },
				library,
				multiple: replacing || ! multiple ? false : 'add',
			} );
			frame.on( 'select', () => {
				const selected = frame.state().get( 'selection' );
				const models = [];
				selected.each( ( model ) => models.push( attachmentItem( model ) ) );
				if ( replacing && models[ 0 ] ) {
					const replacement = itemElement( models[ 0 ], 0, false );
					replacing.replaceWith( replacement );
					if ( ! multiple && singleValue ) {
						singleValue.value = models[ 0 ].id;
					}
				} else {
					const existing = new Set( Array.from( list.querySelectorAll( '[data-adam-upload-item]' ) ).map( ( item ) => item.dataset.id ) );
					const room = max - existing.size;
					models.filter( ( item ) => ! existing.has( String( item.id ) ) ).slice( 0, room )
						.forEach( ( item ) => list.insertBefore( itemElement( item, 0, false ), add ) );
					if ( ! multiple && models[ 0 ] && singleValue ) {
						singleValue.value = models[ 0 ].id;
					}
				}
				refresh();
				announce( labels.added || '' );
			} );
			frame.open();
		}

		add.addEventListener( 'click', () => {
			replaceIndex = null;
			if ( 'file' === mode ) {
				input.click();
			} else {
				openLibrary();
			}
		} );

		if ( input ) {
			input.addEventListener( 'change', () => addFiles( input.files ) );
		}
		upload.addEventListener( 'adam-upload-existing-count', refresh );

		upload.addEventListener( 'click', ( event ) => {
			const item = event.target.closest( '[data-adam-upload-item]' );
			if ( ! item ) {
				return;
			}
			const move = event.target.closest( '[data-adam-upload-move]' );
			if ( move ) {
				moveItem( item, parseInt( move.dataset.adamUploadMove || '0', 10 ) );
				return;
			}
			if ( event.target.closest( '[data-adam-upload-remove]' ) ) {
				const index = parseInt( item.dataset.index || '-1', 10 );
				if ( 'file' === mode && index >= 0 ) {
					const removed = files.splice( index, 1 )[ 0 ];
					if ( removed?.url ) {
						URL.revokeObjectURL( removed.url );
					}
					renderFiles();
				} else {
					item.remove();
					if ( ! multiple && singleValue ) {
						singleValue.value = '0';
					}
					refresh();
				}
				announce( labels.removed || '' );
				return;
			}
			if ( event.target.closest( '[data-adam-upload-replace]' ) ) {
				if ( 'file' === mode ) {
					replaceIndex = parseInt( item.dataset.index || '0', 10 );
					input.click();
				} else {
					openLibrary( item );
				}
			}
		} );

		upload.addEventListener( 'dragstart', ( event ) => {
			dragged = event.target.closest( '[data-adam-upload-item]' );
			if ( dragged ) {
				dragged.classList.add( 'is-dragging' );
			}
		} );
		upload.addEventListener( 'dragend', () => {
			if ( dragged ) {
				dragged.classList.remove( 'is-dragging' );
			}
			dragged = null;
			if ( 'file' === mode ) {
				files = Array.from( list.querySelectorAll( '[data-adam-upload-item]' ) ).map( ( item ) => {
					const original = files.find( ( file ) => file.file === item._adamFile );
					return original;
				} ).filter( Boolean );
				renderFiles();
			} else {
				refresh();
			}
			announce( labels.reordered || '' );
		} );
		upload.addEventListener( 'dragover', ( event ) => {
			event.preventDefault();
			upload.classList.add( 'is-dragover' );
			const target = event.target.closest( '[data-adam-upload-item]' );
			if ( dragged && target && target !== dragged ) {
				const box = target.getBoundingClientRect();
				list.insertBefore( dragged, event.clientX < box.left + box.width / 2 ? target : target.nextSibling );
			}
		} );
		upload.addEventListener( 'keydown', ( event ) => {
			const item = event.target.closest( '[data-adam-upload-item]' );
			if ( ! multiple || ! item || ! event.altKey || ! [ 'ArrowLeft', 'ArrowRight' ].includes( event.key ) ) {
				return;
			}
			event.preventDefault();
			moveItem( item, 'ArrowLeft' === event.key ? -1 : 1 );
		} );
		upload.addEventListener( 'dragleave', ( event ) => {
			if ( ! upload.contains( event.relatedTarget ) ) {
				upload.classList.remove( 'is-dragover' );
			}
		} );
		upload.addEventListener( 'drop', ( event ) => {
			event.preventDefault();
			upload.classList.remove( 'is-dragover' );
			if ( 'file' === mode && ! dragged && event.dataTransfer?.files?.length ) {
				addFiles( event.dataTransfer.files );
			}
		} );

		const form = upload.closest( 'form' );
		if ( form && progress ) {
			form.addEventListener( 'submit', () => {
				if ( ! form.checkValidity() ) {
					return;
				}
				progress.hidden = false;
				upload.classList.add( 'is-uploading' );
			} );
		}
		refresh();
	}

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-adam-upload]' ).forEach( init );
	} );
}() );
