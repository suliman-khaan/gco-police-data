/**
 * GCO Police Data front end: live force search, waiting-time tabs, A-Z filter.
 * Vanilla JS, no dependencies. Everything works without it (list, stacked panels, full A-Z).
 */
( function () {
	'use strict';

	function norm( text ) {
		return String( text || '' ).toLowerCase().replace( /&/g, ' and ' ).replace( /[^a-z0-9]+/g, '' );
	}

	/* ---------------------------------------------------------------- Search */
	function initSearch( root ) {
		var dataNode = root.querySelector( '.gco-pd-search__data' );
		var input = root.querySelector( '.gco-pd-search__input' );
		var list = root.querySelector( '.gco-pd-search__list' );
		var status = root.querySelector( '.gco-pd-search__status' );
		var forces;
		try {
			forces = JSON.parse( dataNode.textContent );
		} catch ( e ) {
			return;
		}
		forces.forEach( function ( force ) {
			force.keys = [ force.n, force.s.replace( /-/g, ' ' ) ].concat( force.a || [] ).map( norm );
		} );

		var active = -1;
		var shown = [];

		function close() {
			list.hidden = true;
			input.setAttribute( 'aria-expanded', 'false' );
			input.removeAttribute( 'aria-activedescendant' );
			active = -1;
		}

		function highlight( index ) {
			var items = list.children;
			for ( var i = 0; i < items.length; i++ ) {
				items[ i ].setAttribute( 'aria-selected', i === index ? 'true' : 'false' );
			}
			active = index;
			if ( index >= 0 && items[ index ] ) {
				input.setAttribute( 'aria-activedescendant', items[ index ].id );
				items[ index ].scrollIntoView( { block: 'nearest' } );
			} else {
				input.removeAttribute( 'aria-activedescendant' );
			}
		}

		function render() {
			var q = norm( input.value );
			list.textContent = '';
			shown = [];
			if ( q === '' ) {
				status.textContent = '';
				close();
				return;
			}
			var starts = [];
			var contains = [];
			forces.forEach( function ( force ) {
				var startsWith = force.keys.some( function ( key ) { return key.indexOf( q ) === 0; } );
				var has = startsWith || force.keys.some( function ( key ) { return key.indexOf( q ) !== -1; } );
				if ( startsWith ) {
					starts.push( force );
				} else if ( has ) {
					contains.push( force );
				}
			} );
			shown = starts.concat( contains ).slice( 0, 8 );

			shown.forEach( function ( force, i ) {
				var li = document.createElement( 'li' );
				li.id = list.id + '-o' + i;
				li.setAttribute( 'role', 'option' );
				li.setAttribute( 'aria-selected', 'false' );
				var node;
				if ( force.u ) {
					node = document.createElement( 'a' );
					node.href = force.u;
				} else {
					node = document.createElement( 'span' );
				}
				var name = document.createElement( 'strong' );
				name.textContent = force.n;
				node.appendChild( name );
				if ( force.w ) {
					var wait = document.createElement( 'span' );
					wait.className = 'gco-pd-search__wait';
					wait.textContent = 'New shotgun certificate: ' + force.w;
					node.appendChild( wait );
				}
				li.appendChild( node );
				list.appendChild( li );
			} );

			list.hidden = shown.length === 0;
			input.setAttribute( 'aria-expanded', shown.length ? 'true' : 'false' );
			status.textContent = shown.length
				? shown.length + ( shown.length === 1 ? ' force found' : ' forces found' )
				: 'No police force matches that search.';
			active = -1;
		}

		input.addEventListener( 'input', render );
		input.addEventListener( 'focus', render );
		input.addEventListener( 'keydown', function ( event ) {
			if ( list.hidden || ! shown.length ) {
				return;
			}
			if ( event.key === 'ArrowDown' ) {
				event.preventDefault();
				highlight( ( active + 1 ) % shown.length );
			} else if ( event.key === 'ArrowUp' ) {
				event.preventDefault();
				highlight( ( active - 1 + shown.length ) % shown.length );
			} else if ( event.key === 'Enter' ) {
				var target = shown[ active >= 0 ? active : ( shown.length === 1 ? 0 : -1 ) ];
				if ( target && target.u ) {
					event.preventDefault();
					window.location.href = target.u;
				}
			} else if ( event.key === 'Escape' ) {
				close();
			}
		} );
		document.addEventListener( 'click', function ( event ) {
			if ( ! root.contains( event.target ) ) {
				close();
			}
		} );
	}

	/* ------------------------------------------------------------------ Tabs */
	function initTabs( root ) {
		var tabs = Array.prototype.slice.call( root.querySelectorAll( '[role="tab"]' ) );
		if ( ! tabs.length ) {
			return;
		}
		root.classList.add( 'is-js' );

		function select( tab, focus ) {
			tabs.forEach( function ( other ) {
				var on = other === tab;
				other.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				other.tabIndex = on ? 0 : -1;
				var panel = document.getElementById( other.getAttribute( 'aria-controls' ) );
				if ( panel ) {
					if ( on ) {
						panel.removeAttribute( 'data-inactive' );
					} else {
						panel.setAttribute( 'data-inactive', '' );
					}
				}
			} );
			if ( focus ) {
				tab.focus();
			}
		}

		tabs.forEach( function ( tab, index ) {
			tab.addEventListener( 'click', function () { select( tab, false ); } );
			tab.addEventListener( 'keydown', function ( event ) {
				var next = null;
				if ( event.key === 'ArrowRight' ) {
					next = tabs[ ( index + 1 ) % tabs.length ];
				} else if ( event.key === 'ArrowLeft' ) {
					next = tabs[ ( index - 1 + tabs.length ) % tabs.length ];
				} else if ( event.key === 'Home' ) {
					next = tabs[ 0 ];
				} else if ( event.key === 'End' ) {
					next = tabs[ tabs.length - 1 ];
				}
				if ( next ) {
					event.preventDefault();
					select( next, true );
				}
			} );
		} );
	}

	/* ------------------------------------------------------------------- A-Z */
	function initAz( root ) {
		var letters = Array.prototype.slice.call( root.querySelectorAll( '.gco-pd-az__letter' ) );
		var groups = Array.prototype.slice.call( root.querySelectorAll( '.gco-pd-az__group' ) );
		var nav = root.querySelector( '.gco-pd-az__nav' );
		if ( ! letters.length ) {
			return;
		}
		root.classList.add( 'is-js' );

		var all = document.createElement( 'button' );
		all.type = 'button';
		all.className = 'gco-pd-az__letter gco-pd-az__all';
		all.textContent = 'All';
		nav.insertBefore( all, nav.firstChild );
		var controls = [ all ].concat( letters );

		function show( letter ) {
			groups.forEach( function ( group ) {
				group.hidden = letter !== null && group.getAttribute( 'data-letter' ) !== letter;
			} );
			controls.forEach( function ( control ) {
				var mine = control === all ? letter === null : control.getAttribute( 'data-letter' ) === letter;
				control.setAttribute( 'aria-pressed', mine ? 'true' : 'false' );
			} );
		}

		all.addEventListener( 'click', function () { show( null ); } );
		letters.forEach( function ( link ) {
			link.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				show( link.getAttribute( 'data-letter' ) );
			} );
		} );
		show( null );
	}

	function ready() {
		document.querySelectorAll( '[data-gco-search]' ).forEach( initSearch );
		document.querySelectorAll( '[data-gco-tabs]' ).forEach( initTabs );
		document.querySelectorAll( '[data-gco-az]' ).forEach( initAz );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
}() );
