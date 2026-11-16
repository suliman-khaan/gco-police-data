/**
 * GCO Police Data - force page editor: tabs, repeatable rows, image pickers.
 * Vanilla JS; wp.media for the image picker.
 */
( function () {
	'use strict';

	/* ------------------------------------------------------------------ Tabs */
	document.querySelectorAll( '[data-gco-tabs-admin]' ).forEach( function ( form ) {
		var tabs = form.querySelectorAll( '[data-gco-tab]' );
		var panels = form.querySelectorAll( '[data-gco-panel]' );
		var input = form.querySelector( '[data-gco-tab-input]' );

		function show( slug ) {
			tabs.forEach( function ( tab ) {
				tab.classList.toggle( 'nav-tab-active', tab.getAttribute( 'data-gco-tab' ) === slug );
			} );
			panels.forEach( function ( panel ) {
				panel.hidden = panel.getAttribute( 'data-gco-panel' ) !== slug;
			} );
			if ( input ) {
				input.value = slug;
			}
		}

		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( tab.getAttribute( 'data-gco-tab' ) );
				if ( window.history && window.history.replaceState ) {
					var url = new URL( window.location.href );
					url.searchParams.set( 'tab', tab.getAttribute( 'data-gco-tab' ) );
					window.history.replaceState( null, '', url.toString() );
				}
			} );
		} );

		// Warn before leaving with unsaved changes.
		var dirty = false;
		form.addEventListener( 'input', function () {
			dirty = true;
		} );
		form.addEventListener( 'submit', function () {
			dirty = false;
		} );
		window.addEventListener( 'beforeunload', function ( event ) {
			if ( dirty ) {
				event.preventDefault();
				event.returnValue = '';
			}
		} );
	} );

	/* ------------------------------------------------------------ Repeaters */
	var counter = Date.now();

	document.querySelectorAll( '[data-gco-repeater]' ).forEach( function ( repeater ) {
		var rows = repeater.querySelector( '[data-gco-rows]' );
		var template = repeater.querySelector( '[data-gco-template]' );

		repeater.querySelector( '[data-gco-add]' ).addEventListener( 'click', function () {
			var html = template.innerHTML.replace( /__i__/g, 'n' + ( counter++ ) );
			var holder = document.createElement( 'ol' );
			holder.innerHTML = html;
			var row = holder.firstElementChild;
			rows.insertBefore( row, template );
			var first = row.querySelector( 'input, textarea' );
			if ( first ) {
				first.focus();
			}
			repeater.closest( 'form' ).dispatchEvent( new Event( 'input' ) );
		} );

		rows.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( 'button' );
			if ( ! button ) {
				return;
			}
			var row = button.closest( '.gco-pd-repeater__row' );
			if ( button.hasAttribute( 'data-gco-remove' ) ) {
				row.remove();
			} else if ( button.hasAttribute( 'data-gco-move' ) ) {
				if ( '-1' === button.getAttribute( 'data-gco-move' ) ) {
					var prev = row.previousElementSibling;
					if ( prev && prev.classList.contains( 'gco-pd-repeater__row' ) ) {
						rows.insertBefore( row, prev );
					}
				} else {
					var next = row.nextElementSibling;
					if ( next && next.classList.contains( 'gco-pd-repeater__row' ) ) {
						rows.insertBefore( next, row );
					}
				}
				button.focus();
			}
			repeater.closest( 'form' ).dispatchEvent( new Event( 'input' ) );
		} );
	} );

	/* ---------------------------------------------------------------- Media */
	document.querySelectorAll( '[data-gco-media]' ).forEach( function ( box ) {
		var input = box.querySelector( '[data-gco-media-id]' );
		var preview = box.querySelector( '[data-gco-media-preview]' );
		var clear = box.querySelector( '[data-gco-media-clear]' );
		var frame;

		box.querySelector( '[data-gco-media-choose]' ).addEventListener( 'click', function () {
			if ( ! window.wp || ! window.wp.media ) {
				return;
			}
			if ( ! frame ) {
				frame = window.wp.media( {
					title: 'Choose image',
					library: { type: 'image' },
					button: { text: 'Use this image' },
					multiple: false
				} );
				frame.on( 'select', function () {
					var attachment = frame.state().get( 'selection' ).first().toJSON();
					var size = attachment.sizes && ( attachment.sizes.medium || attachment.sizes.full );
					input.value = attachment.id;
					preview.textContent = '';
					var img = document.createElement( 'img' );
					img.src = size ? size.url : attachment.url;
					img.alt = '';
					preview.appendChild( img );
					clear.hidden = false;
					box.closest( 'form' ).dispatchEvent( new Event( 'input' ) );
				} );
			}
			frame.open();
		} );

		clear.addEventListener( 'click', function () {
			input.value = '0';
			preview.textContent = '';
			clear.hidden = true;
			box.closest( 'form' ).dispatchEvent( new Event( 'input' ) );
		} );
	} );
}() );
