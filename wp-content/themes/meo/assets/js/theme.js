/**
 * MEO — front-end behaviour.
 *
 * Two things only: the colour-scheme toggle and the mobile nav. Everything
 * else is CSS. No dependencies, no build step.
 */
( function () {
	'use strict';

	var STORAGE_KEY = 'meo-theme';
	var root = document.documentElement;

	/**
	 * localStorage throws in private mode and when site data is blocked, and
	 * a colour preference is never worth breaking the page over — so both
	 * accessors swallow failure and the site falls back to prefers-color-scheme.
	 */
	function readStored() {
		try {
			return localStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	}

	function writeStored( value ) {
		try {
			localStorage.setItem( STORAGE_KEY, value );
		} catch ( e ) {
			/* Preference is not persisted; the toggle still works for this page. */
		}
	}

	function currentTheme() {
		var explicit = root.getAttribute( 'data-theme' );
		if ( explicit === 'dark' || explicit === 'light' ) {
			return explicit;
		}
		return window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
	}

	function applyTheme( theme, toggles ) {
		root.setAttribute( 'data-theme', theme );
		for ( var i = 0; i < toggles.length; i++ ) {
			toggles[ i ].setAttribute( 'aria-pressed', theme === 'dark' ? 'true' : 'false' );
		}
	}

	function initThemeToggle() {
		var toggles = document.querySelectorAll( '[data-meo-theme-toggle]' );
		if ( ! toggles.length ) {
			return;
		}

		// Reflect the state the inline head script (or the OS) already produced.
		applyTheme( currentTheme(), toggles );

		for ( var i = 0; i < toggles.length; i++ ) {
			toggles[ i ].addEventListener( 'click', function () {
				var next = currentTheme() === 'dark' ? 'light' : 'dark';
				applyTheme( next, toggles );
				writeStored( next );
			} );
		}

		// Follow the OS only while the visitor has made no explicit choice.
		var media = window.matchMedia( '(prefers-color-scheme: dark)' );
		var onChange = function ( event ) {
			if ( readStored() ) {
				return;
			}
			applyTheme( event.matches ? 'dark' : 'light', toggles );
		};

		if ( typeof media.addEventListener === 'function' ) {
			media.addEventListener( 'change', onChange );
		} else if ( typeof media.addListener === 'function' ) {
			media.addListener( onChange ); // Safari < 14.
		}
	}

	function initMenu() {
		var toggle = document.querySelector( '[data-meo-menu-toggle]' );
		var nav = document.getElementById( 'meo-nav' );

		if ( ! toggle || ! nav ) {
			return;
		}

		function close() {
			nav.classList.remove( 'is-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
		}

		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );

		document.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Escape' && nav.classList.contains( 'is-open' ) ) {
				close();
				toggle.focus();
			}
		} );

		// Clicking outside the open menu closes it.
		document.addEventListener( 'click', function ( event ) {
			if ( ! nav.classList.contains( 'is-open' ) ) {
				return;
			}
			if ( ! nav.contains( event.target ) && ! toggle.contains( event.target ) ) {
				close();
			}
		} );

		// Leaving the mobile breakpoint should not leave a stale open panel.
		var wide = window.matchMedia( '(min-width: 861px)' );
		if ( typeof wide.addEventListener === 'function' ) {
			wide.addEventListener( 'change', close );
		} else if ( typeof wide.addListener === 'function' ) {
			wide.addListener( close );
		}
	}

	function init() {
		initThemeToggle();
		initMenu();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
