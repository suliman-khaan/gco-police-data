/**
 * Round editor: live value/status checks, one-field JSON submit (avoids max_input_vars), PDF picker.
 * Vanilla JS; wp.media is only used for the PDF picker.
 */
( function () {
	'use strict';

	var form = document.getElementById( 'gco-pd-grid-form' );
	if ( ! form ) {
		return;
	}

	var payloadField = document.getElementById( 'gco-pd-payload' );
	var problemsNote = document.getElementById( 'gco-pd-problems' );
	var dirty = false;
	var submitting = false;

	/** Value/status pairs: [valueField, statusField]. Derived from the markup so PHP stays the single source. */
	function pairsFor( row ) {
		var pairs = [];
		row.querySelectorAll( 'select[data-f$="_status"]' ).forEach( function ( select ) {
			var statusName = select.getAttribute( 'data-f' );
			var valueName = statusName.replace( /_status$/, '' );
			var valueInput = row.querySelector( '[data-f="' + valueName + '_days"]' ) || row.querySelector( '[data-f="' + valueName + '"]' );
			if ( valueInput ) {
				pairs.push( [ valueInput, select ] );
			}
		} );
		return pairs;
	}

	/** Returns a message when the number and its status disagree. */
	function pairError( valueInput, select ) {
		var value = valueInput.value.trim();
		var status = select.value;
		if ( value !== '' && ! /^\d+$/.test( value ) ) {
			return 'Whole numbers only.';
		}
		if ( value !== '' && status === 'not_provided' ) {
			return 'Has a number but is marked Not provided.';
		}
		if ( value === '' && status !== 'not_provided' ) {
			return 'Marked ' + select.options[ select.selectedIndex ].text + ' but has no number.';
		}
		return '';
	}

	function checkRow( row ) {
		var messages = [];
		pairsFor( row ).forEach( function ( pair ) {
			var message = pairError( pair[ 0 ], pair[ 1 ] );
			var cell = pair[ 0 ].closest( 'td' );
			cell.classList.toggle( 'is-bad', message !== '' );
			if ( message ) {
				var label = pair[ 0 ].getAttribute( 'aria-label' ) || pair[ 0 ].getAttribute( 'data-f' );
				messages.push( label + ': ' + message );
			}
		} );
		var box = row.querySelector( '.gco-pd-live-error' );
		box.hidden = messages.length === 0;
		box.textContent = messages.join( ' ' );
		return messages.length;
	}

	function checkAll() {
		var bad = 0;
		form.querySelectorAll( 'tr.gco-pd-row' ).forEach( function ( row ) {
			bad += checkRow( row ) ? 1 : 0;
		} );
		if ( problemsNote ) {
			problemsNote.textContent = bad ? bad + ' row(s) need attention (red cells).' : '';
		}
		return bad;
	}

	form.addEventListener( 'input', function ( event ) {
		var target = event.target;
		var row = target.closest( 'tr.gco-pd-row' );
		if ( ! row ) {
			return;
		}
		dirty = true;
		// Typing a number flips "Not provided" to "Actual"; clearing it flips back. Saves a click and a mistake.
		if ( target.type === 'number' ) {
			var select = row.querySelector( '[data-f="' + target.getAttribute( 'data-f' ).replace( /_days$/, '' ) + '_status"]' );
			if ( select ) {
				if ( target.value.trim() !== '' && select.value === 'not_provided' ) {
					select.value = 'actual';
				} else if ( target.value.trim() === '' && select.value !== 'not_provided' ) {
					select.value = 'not_provided';
				}
			}
		}
		checkRow( row );
	} );
	form.addEventListener( 'change', function ( event ) {
		var row = event.target.closest( 'tr.gco-pd-row' );
		if ( row ) {
			dirty = true;
			checkRow( row );
		}
	} );

	form.addEventListener( 'submit', function ( event ) {
		if ( checkAll() ) {
			event.preventDefault();
			var firstBad = form.querySelector( '.gco-pd-metric.is-bad' );
			if ( firstBad ) {
				firstBad.scrollIntoView( { block: 'center', inline: 'center' } );
			}
			return;
		}
		var payload = {};
		form.querySelectorAll( 'tr.gco-pd-row' ).forEach( function ( row ) {
			var data = {};
			row.querySelectorAll( '[data-f]' ).forEach( function ( field ) {
				data[ field.getAttribute( 'data-f' ) ] = field.value;
			} );
			payload[ row.getAttribute( 'data-force' ) ] = data;
		} );
		payloadField.value = JSON.stringify( payload );
		submitting = true;
	} );

	window.addEventListener( 'beforeunload', function ( event ) {
		if ( dirty && ! submitting ) {
			event.preventDefault();
			event.returnValue = '';
		}
	} );

	// PDF picker (media library, PDFs only).
	form.addEventListener( 'click', function ( event ) {
		var pick = event.target.closest( '.gco-pd-pick-pdf' );
		var clear = event.target.closest( '.gco-pd-clear-pdf' );
		if ( ! pick && ! clear ) {
			return;
		}
		var cell = event.target.closest( '.gco-pd-pdf' );
		var idField = cell.querySelector( '[data-f="pdf_id"]' );
		var nameLabel = cell.querySelector( '.gco-pd-pdf-name' );
		var removeLink = cell.querySelector( '.gco-pd-clear-pdf' );

		if ( clear ) {
			idField.value = '';
			nameLabel.textContent = '—';
			removeLink.hidden = true;
			dirty = true;
			return;
		}
		if ( ! window.wp || ! window.wp.media ) {
			return;
		}
		var frame = window.wp.media( {
			title: 'Choose the FOI letter (PDF)',
			library: { type: 'application/pdf' },
			multiple: false,
			button: { text: 'Use this PDF' }
		} );
		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			idField.value = attachment.id;
			nameLabel.textContent = attachment.filename || attachment.title;
			removeLink.hidden = false;
			dirty = true;
		} );
		frame.open();
	} );

	checkAll();
	dirty = false;
}() );
