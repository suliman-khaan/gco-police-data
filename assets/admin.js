/**
 * Round editor: live value/status checks, one-field JSON submit (avoids max_input_vars), PDF picker.
 * Two tabs (waiting times, applications/backlog/standards) share one form; rows of the same force are
 * merged into one payload entry. Vanilla JS; wp.media is only used for the PDF picker.
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

	/* ------------------------------------------------------------------ Tabs */
	var gridTabs = Array.prototype.slice.call( form.querySelectorAll( '[data-gco-gridtab]' ) );

	function showGrid( name ) {
		gridTabs.forEach( function ( tab ) {
			tab.classList.toggle( 'nav-tab-active', tab.getAttribute( 'data-gco-gridtab' ) === name );
		} );
		form.querySelectorAll( '[data-gco-grid]' ).forEach( function ( grid ) {
			grid.hidden = grid.getAttribute( 'data-gco-grid' ) !== name;
		} );
	}

	gridTabs.forEach( function ( tab ) {
		tab.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			showGrid( tab.getAttribute( 'data-gco-gridtab' ) );
		} );
	} );

	/* ------------------------------------------------------------ Validation */

	/** Whether a number input must be a whole number (decimals are only allowed for percentages). */
	function wholeOnly( input, row ) {
		var kind = input.getAttribute( 'data-kind' );
		if ( kind === 'percent' ) {
			return false;
		}
		if ( kind === 'band' ) {
			var unit = row.querySelector( '[data-gco-unit]' );
			return ! unit || unit.value !== 'percent';
		}
		return true;
	}

	/** Returns a message when the number and its status disagree (single figures). */
	function pairError( input, select, row ) {
		var value = input.value.trim();
		var status = select.value;
		if ( value !== '' ) {
			if ( wholeOnly( input, row ) && ! /^\d+(\.0+)?$/.test( value ) ) {
				return 'Whole numbers only.';
			}
			if ( ! wholeOnly( input, row ) && ! /^\d+(\.\d+)?$/.test( value ) ) {
				return 'Numbers only (decimals allowed).';
			}
			if ( input.getAttribute( 'data-kind' ) === 'percent' && parseFloat( value ) > 100 ) {
				return 'A percentage cannot be more than 100.';
			}
		}
		if ( value !== '' && status === 'not_provided' ) {
			return 'Has a number but is marked Not provided.';
		}
		if ( value === '' && status !== 'not_provided' ) {
			return 'Marked ' + select.options[ select.selectedIndex ].text + ' but has no number.';
		}
		return '';
	}

	/** Backlog bands share one status: any band filled needs Actual/Estimate; none needs Not provided. */
	function backlogError( row ) {
		var bands = Array.prototype.slice.call( row.querySelectorAll( '[data-group="backlog"]' ) );
		var select = row.querySelector( '[data-group-status="backlog"]' );
		if ( ! bands.length || ! select ) {
			return { message: '', cells: [] };
		}
		var filled = bands.filter( function ( band ) {
			return band.value.trim() !== '';
		} );
		var cells = [];
		var message = '';
		bands.forEach( function ( band ) {
			var value = band.value.trim();
			if ( value === '' ) {
				return;
			}
			if ( wholeOnly( band, row ) && ! /^\d+(\.0+)?$/.test( value ) ) {
				message = 'Backlog counts must be whole numbers. If the force gave percentages, set the unit to Percentage.';
				cells.push( band );
			} else if ( ! wholeOnly( band, row ) && ( ! /^\d+(\.\d+)?$/.test( value ) || parseFloat( value ) > 100 ) ) {
				message = 'Backlog percentages must be between 0 and 100.';
				cells.push( band );
			}
		} );
		if ( ! message ) {
			if ( filled.length && select.value === 'not_provided' ) {
				message = 'Backlog figures are filled in but the status is Not provided.';
				cells.push( select );
			} else if ( ! filled.length && select.value !== 'not_provided' ) {
				message = 'Backlog marked ' + select.options[ select.selectedIndex ].text + ' but has no figures.';
				cells.push( select );
			}
		}
		return { message: message, cells: cells };
	}

	function checkRow( row ) {
		var messages = [];
		row.querySelectorAll( '.gco-pd-metric.is-bad' ).forEach( function ( cell ) {
			cell.classList.remove( 'is-bad' );
		} );

		row.querySelectorAll( 'input[data-status]' ).forEach( function ( input ) {
			var select = row.querySelector( '[data-f="' + input.getAttribute( 'data-status' ) + '"]' );
			if ( ! select ) {
				return;
			}
			var message = pairError( input, select, row );
			if ( message ) {
				input.closest( 'td' ).classList.add( 'is-bad' );
				messages.push( ( input.getAttribute( 'aria-label' ) || input.getAttribute( 'data-f' ) ) + ': ' + message );
			}
		} );

		row.querySelectorAll( 'input[data-kind]:not([data-status]):not([data-group])' ).forEach( function ( input ) {
			var value = input.value.trim();
			var kind = input.getAttribute( 'data-kind' );
			var message = '';
			if ( value !== '' ) {
				if ( kind === 'percent' ) {
					if ( ! /^\d+(\.\d+)?$/.test( value ) || parseFloat( value ) > 100 ) {
						message = 'Enter a percentage between 0 and 100.';
					}
				} else if ( ! /^\d+(\.0+)?$/.test( value ) ) {
					message = 'Whole numbers only.';
				}
			}
			if ( message ) {
				input.closest( 'td' ).classList.add( 'is-bad' );
				messages.push( ( input.getAttribute( 'aria-label' ) || input.getAttribute( 'data-f' ) ) + ': ' + message );
			}
		} );

		var backlog = backlogError( row );
		if ( backlog.message ) {
			backlog.cells.forEach( function ( cell ) {
				cell.closest( 'td' ).classList.add( 'is-bad' );
			} );
			messages.push( backlog.message );
		}

		var box = row.querySelector( '.gco-pd-live-error' );
		if ( box ) {
			box.hidden = messages.length === 0;
			box.textContent = messages.join( ' ' );
		}
		return messages.length;
	}

	function checkAll() {
		var bad = 0;
		var seen = {};
		form.querySelectorAll( 'tr.gco-pd-row' ).forEach( function ( row ) {
			if ( checkRow( row ) ) {
				seen[ row.getAttribute( 'data-force' ) ] = true;
			}
		} );
		bad = Object.keys( seen ).length;
		if ( problemsNote ) {
			problemsNote.textContent = bad ? bad + ' row(s) need attention (red cells).' : '';
		}
		return bad;
	}

	/** Typing a number flips "Not provided" to "Actual"; clearing it flips back. Saves a click and a mistake. */
	function followStatus( target, row ) {
		if ( target.type !== 'number' ) {
			return;
		}
		var select = null;
		var filled = target.value.trim() !== '';
		if ( target.hasAttribute( 'data-group' ) ) {
			select = row.querySelector( '[data-group-status="' + target.getAttribute( 'data-group' ) + '"]' );
			filled = Array.prototype.some.call( row.querySelectorAll( '[data-group="' + target.getAttribute( 'data-group' ) + '"]' ), function ( input ) {
				return input.value.trim() !== '';
			} );
		} else if ( target.hasAttribute( 'data-status' ) ) {
			select = row.querySelector( '[data-f="' + target.getAttribute( 'data-status' ) + '"]' );
		}
		if ( ! select ) {
			return;
		}
		if ( filled && select.value === 'not_provided' ) {
			select.value = 'actual';
		} else if ( ! filled && select.value !== 'not_provided' ) {
			select.value = 'not_provided';
		}
	}

	form.addEventListener( 'input', function ( event ) {
		var target = event.target;
		var row = target.closest( 'tr.gco-pd-row' );
		if ( ! row ) {
			return;
		}
		dirty = true;
		followStatus( target, row );
		checkRow( row );
	} );
	form.addEventListener( 'change', function ( event ) {
		var target = event.target;
		var row = target.closest( 'tr.gco-pd-row' );
		if ( ! row ) {
			return;
		}
		dirty = true;
		// The unit decides whether backlog bands take decimals.
		if ( target.hasAttribute( 'data-gco-unit' ) ) {
			row.querySelectorAll( '[data-group="backlog"]' ).forEach( function ( band ) {
				band.step = target.value === 'percent' ? '0.01' : '1';
			} );
		}
		checkRow( row );
	} );

	form.addEventListener( 'submit', function ( event ) {
		if ( checkAll() ) {
			event.preventDefault();
			var firstBad = form.querySelector( '.gco-pd-metric.is-bad' );
			if ( firstBad ) {
				var grid = firstBad.closest( '[data-gco-grid]' );
				if ( grid && grid.hidden ) {
					showGrid( grid.getAttribute( 'data-gco-grid' ) );
				}
				firstBad.scrollIntoView( { block: 'center', inline: 'center' } );
			}
			return;
		}
		// One payload entry per force, merged across both tabs.
		var payload = {};
		form.querySelectorAll( 'tr.gco-pd-row' ).forEach( function ( row ) {
			var id = row.getAttribute( 'data-force' );
			var type = row.getAttribute( 'data-type' );
			var data = payload[ id ] || {};
			var target = data;
			if ( type ) {
				data.types = data.types || {};
				data.types[ type ] = data.types[ type ] || {};
				target = data.types[ type ];
			}
			row.querySelectorAll( '[data-f]' ).forEach( function ( field ) {
				target[ field.getAttribute( 'data-f' ) ] = field.value;
			} );
			payload[ id ] = data;
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
