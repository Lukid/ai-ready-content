/**
 * Share with AI - Dropdown button behavior.
 * Vanilla JS IIFE, no dependencies.
 */
( function() {
	'use strict';

	if ( typeof aircShare === 'undefined' ) {
		return;
	}

	var config = aircShare;
	var widget = document.getElementById( 'airc-share-widget' );
	if ( ! widget ) {
		return;
	}

	var fab   = widget.querySelector( '.airc-share-fab' );
	var menu  = widget.querySelector( '.airc-share-menu' );
	var toast = widget.querySelector( '.airc-share-toast' );
	var items = menu.querySelectorAll( '.airc-share-item' );

	// --- Service URL definitions ---
	var services = {
		markdown: {
			getUrl: function() {
				return config.mdUrl;
			},
			clipboard: false
		},
		chatgpt: {
			getUrl: function( prompt ) {
				return 'https://chatgpt.com/?hints=search&q=' + encodeURIComponent( prompt );
			},
			clipboard: false
		},
		claude: {
			getUrl: function( prompt ) {
				return 'https://claude.ai/new?q=' + encodeURIComponent( prompt );
			},
			clipboard: true // also copy as backup since URL param support is unreliable
		},
		perplexity: {
			getUrl: function( prompt ) {
				return 'https://www.perplexity.ai/search?q=' + encodeURIComponent( prompt );
			},
			clipboard: false
		}
	};

	// --- Prompt construction ---
	function buildPrompt() {
		var template = config.promptTemplate || 'Read and summarize the following page: {url}';
		return template
			.replace( /\{url\}/g, config.mdUrl )
			.replace( /\{title\}/g, config.postTitle );
	}

	// --- Toggle menu ---
	function openMenu() {
		menu.removeAttribute( 'hidden' );
		// Force reflow before adding class for CSS transition.
		void menu.offsetHeight;
		menu.classList.add( 'airc-share-menu--open' );
		fab.setAttribute( 'aria-expanded', 'true' );
		// Focus first item.
		if ( items.length ) {
			items[0].focus();
		}
	}

	function closeMenu() {
		menu.classList.remove( 'airc-share-menu--open' );
		fab.setAttribute( 'aria-expanded', 'false' );
		// Hide after transition.
		setTimeout( function() {
			if ( fab.getAttribute( 'aria-expanded' ) === 'false' ) {
				menu.setAttribute( 'hidden', '' );
			}
		}, 160 );
	}

	function toggleMenu() {
		var isOpen = fab.getAttribute( 'aria-expanded' ) === 'true';
		if ( isOpen ) {
			closeMenu();
			fab.focus();
		} else {
			openMenu();
		}
	}

	// --- Clipboard ---
	function copyToClipboard( text ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( text );
		}
		// Fallback for HTTP or older browsers.
		return new Promise( function( resolve, reject ) {
			var ta = document.createElement( 'textarea' );
			ta.value = text;
			ta.style.position = 'fixed';
			ta.style.opacity = '0';
			ta.style.left = '-9999px';
			document.body.appendChild( ta );
			ta.select();
			try {
				document.execCommand( 'copy' );
				resolve();
			} catch ( err ) {
				reject( err );
			}
			document.body.removeChild( ta );
		} );
	}

	// --- Toast ---
	var toastTimer = null;

	function showToast( message ) {
		if ( toastTimer ) {
			clearTimeout( toastTimer );
		}
		toast.textContent = message;
		toast.removeAttribute( 'hidden' );
		void toast.offsetHeight;
		toast.classList.add( 'airc-share-toast--visible' );
		toastTimer = setTimeout( function() {
			toast.classList.remove( 'airc-share-toast--visible' );
			setTimeout( function() {
				toast.setAttribute( 'hidden', '' );
			}, 200 );
		}, 2500 );
	}

	// --- Service action ---
	function handleService( serviceName ) {
		var service = services[ serviceName ];
		if ( ! service ) {
			return;
		}

		var prompt = buildPrompt();

		if ( service.clipboard ) {
			// Copy prompt first, then open.
			copyToClipboard( prompt ).then( function() {
				showToast( config.i18n.copied );
				setTimeout( function() {
					window.open( service.getUrl( prompt ), '_blank', 'noopener,noreferrer' );
				}, 800 );
			} ).catch( function() {
				showToast( config.i18n.copyFailed );
				// Open anyway.
				window.open( service.getUrl( prompt ), '_blank', 'noopener,noreferrer' );
			} );
		} else {
			window.open( service.getUrl( prompt ), '_blank', 'noopener,noreferrer' );
		}

		closeMenu();
	}

	// --- Event listeners ---

	fab.addEventListener( 'click', function( e ) {
		e.preventDefault();
		e.stopPropagation();
		toggleMenu();
	} );

	// Click outside.
	document.addEventListener( 'click', function( e ) {
		if ( fab.getAttribute( 'aria-expanded' ) === 'true' && ! widget.contains( e.target ) ) {
			closeMenu();
		}
	} );

	// Delegate clicks on menu items.
	menu.addEventListener( 'click', function( e ) {
		var item = e.target.closest( '.airc-share-item' );
		if ( ! item ) {
			return;
		}
		e.preventDefault();
		handleService( item.dataset.service );
	} );

	// Keyboard navigation.
	menu.addEventListener( 'keydown', function( e ) {
		var currentIndex = Array.prototype.indexOf.call( items, document.activeElement );

		switch ( e.key ) {
			case 'ArrowDown':
				e.preventDefault();
				items[ ( currentIndex + 1 ) % items.length ].focus();
				break;
			case 'ArrowUp':
				e.preventDefault();
				items[ ( currentIndex - 1 + items.length ) % items.length ].focus();
				break;
			case 'Escape':
				e.preventDefault();
				closeMenu();
				fab.focus();
				break;
			case 'Tab':
				closeMenu();
				break;
		}
	} );

	// Escape on FAB when menu is open.
	fab.addEventListener( 'keydown', function( e ) {
		if ( e.key === 'Escape' && fab.getAttribute( 'aria-expanded' ) === 'true' ) {
			e.preventDefault();
			closeMenu();
		}
	} );
} )();
