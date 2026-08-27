/**
 * AV Froburger Events Integration enhancements:
 * - "Mehr anzeigen" progressive enhancement
 * - public calendar subscribe dialog
 */
( function () {
	'use strict';

	function buildHref( queryVar, value ) {
		var url = new URL( window.location.href );
		url.searchParams.set( queryVar, String( value ) );
		return url.toString();
	}

	function handleMoreClick( event ) {
		var link = event.currentTarget;
		var type;
		var shown;
		var step;
		var nonce;
		var queryVar;
		var targetId;
		var list;
		var originalHref;
		var body;

		if ( 'true' === link.getAttribute( 'aria-busy' ) ) {
			event.preventDefault();
			return;
		}

		event.preventDefault();

		type = link.getAttribute( 'data-type' );
		shown = link.getAttribute( 'data-shown' );
		step = link.getAttribute( 'data-step' );
		nonce = link.getAttribute( 'data-nonce' );
		queryVar = link.getAttribute( 'data-query-var' );
		targetId = link.getAttribute( 'data-list-target' );
		list = targetId ? document.getElementById( targetId ) : null;
		originalHref = link.href;

		if ( ! window.avfEventsAjax || ! list || ! type || ! shown || ! step || ! nonce ) {
			window.location.href = originalHref;
			return;
		}

		link.setAttribute( 'aria-busy', 'true' );

		body = new URLSearchParams();
		body.set( 'action', window.avfEventsAjax.action );
		body.set( 'nonce', nonce );
		body.set( 'type', type );
		body.set( 'shown', shown );
		body.set( 'step', step );

		fetch( window.avfEventsAjax.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} )
			.then( function ( response ) {
				if ( ! response.ok ) {
					throw new Error( 'Request failed' );
				}
				return response.json();
			} )
			.then( function ( json ) {
				var newShown;
				var wrap;

				if ( ! json || ! json.success || ! json.data ) {
					throw new Error( 'Unsuccessful response' );
				}

				if ( json.data.html ) {
					list.insertAdjacentHTML( 'beforeend', json.data.html );
				}

				newShown = json.data.shown ? parseInt( json.data.shown, 10 ) : ( parseInt( shown, 10 ) + parseInt( step, 10 ) );

				if ( json.data.has_more ) {
					link.setAttribute( 'data-shown', String( newShown ) );
					link.href = buildHref( queryVar, newShown + parseInt( step, 10 ) );
					link.removeAttribute( 'aria-busy' );
				} else {
					wrap = link.closest( '.avf-events-more-wrap' );
					if ( wrap && wrap.parentNode ) {
						wrap.parentNode.removeChild( wrap );
					} else {
						link.removeAttribute( 'aria-busy' );
						link.style.display = 'none';
					}
				}
			} )
			.catch( function () {
				window.location.href = originalHref;
			} );
	}

	function initMoreLinks() {
		var links = document.querySelectorAll( '[data-avf-events-more]' );
		var i;

		for ( i = 0; i < links.length; i++ ) {
			links[ i ].addEventListener( 'click', handleMoreClick );
		}
	}

	function copyText( value ) {
		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			return navigator.clipboard.writeText( value );
		}

		return new Promise( function ( resolve, reject ) {
			var input = document.createElement( 'input' );
			input.value = value;
			document.body.appendChild( input );
			input.select();
			if ( document.execCommand( 'copy' ) ) {
				document.body.removeChild( input );
				resolve();
				return;
			}
			document.body.removeChild( input );
			reject( new Error( 'copy_failed' ) );
		} );
	}

	function isAndroidDevice() {
		return /Android/i.test( navigator.userAgent || '' );
	}

	function openCalendarSubscription( button, subscribeUrl, fallbackUrl ) {

		if ( button && 'true' === button.getAttribute( 'aria-busy' ) ) {
			return;
		}

		function clearBusy() {
			if ( button ) {
				button.removeAttribute( 'aria-busy' );
			}
		}

		function openFallback() {
			clearBusy();
			window.location.assign( fallbackUrl );
		}

		if ( ! subscribeUrl || ! fallbackUrl || isAndroidDevice() ) {
			if ( fallbackUrl ) {
				if ( button ) {
					button.setAttribute( 'aria-busy', 'true' );
				}
				openFallback();
			}
			return;
		}

		if ( button ) {
			button.setAttribute( 'aria-busy', 'true' );
		}

		try {
			window.location.assign( subscribeUrl );
		} catch ( error ) {
			openFallback();
			return;
		}

		/* A browser without a webcal handler stays on this page. */
		window.setTimeout( clearBusy, 1500 );
	}

	function initCalendarActions() {
		var roots = document.querySelectorAll( '[data-avf-calendar-actions]' );

		roots.forEach( function ( root ) {
			var openButton = root.querySelector( '[data-avf-calendar-dialog-open]' );
			var dialog = root.querySelector( '.avf-events-calendar-actions__dialog' );
			var closeButtons = root.querySelectorAll( '[data-avf-calendar-dialog-close]' );
			var openCalendarButton = root.querySelector( '[data-avf-calendar-open]' );
			var subscribeButton = root.querySelector( '[data-avf-calendar-subscribe]' );
			var copyButton = root.querySelector( '[data-avf-calendar-copy]' );
			var lastTrigger = null;

			function closeDialog() {
				if ( ! dialog ) {
					return;
				}
				if ( typeof dialog.close === 'function' && dialog.open ) {
					dialog.close();
				} else {
					dialog.removeAttribute( 'open' );
				}
				if ( lastTrigger && typeof lastTrigger.focus === 'function' ) {
					lastTrigger.focus();
				}
			}

			if ( openButton && dialog ) {
				openButton.addEventListener( 'click', function () {
					lastTrigger = openButton;
					if ( typeof dialog.showModal === 'function' ) {
						dialog.showModal();
					} else {
						dialog.setAttribute( 'open', 'open' );
					}
				} );

				dialog.addEventListener( 'click', function ( event ) {
					if ( event.target === dialog ) {
						closeDialog();
					}
				} );

				dialog.addEventListener( 'cancel', function ( event ) {
					event.preventDefault();
					closeDialog();
				} );
			}

			closeButtons.forEach( function ( button ) {
				button.addEventListener( 'click', closeDialog );
			} );

			if ( openCalendarButton ) {
				openCalendarButton.addEventListener( 'click', function () {
					var url = openCalendarButton.getAttribute( 'data-open-url' ) || '';
					var opened = null;

					if ( ! url ) {
						return;
					}

					try {
						opened = window.open( url, '_blank', 'noopener,noreferrer' );
					} catch ( error ) {
						opened = null;
					}

					if ( ! opened ) {
						window.location.href = url;
					}
				} );
			}

			if ( subscribeButton ) {
				subscribeButton.addEventListener( 'click', function () {
					var subscribeUrl = subscribeButton.getAttribute( 'data-subscribe-url' ) || '';
					var fallbackUrl = subscribeButton.getAttribute( 'data-fallback-url' ) || '';

					openCalendarSubscription( subscribeButton, subscribeUrl, fallbackUrl );
				} );
			}

			if ( copyButton ) {
				copyButton.addEventListener( 'click', function () {
					var value = copyButton.getAttribute( 'data-copy-value' ) || '';
					copyText( value ).catch( function () {} );
				} );
			}
		} );
	}

	function focusNotice( root ) {
		var notice = root.querySelector( '.avf-event-detail__notice' );

		if ( notice && typeof notice.focus === 'function' ) {
			window.requestAnimationFrame( function () {
				notice.focus();
			} );
		}
	}

	function demoteHeroHeading( root ) {
		var hero = document.querySelector( '.elementor-element-2eabd2b' );
		var title = root.getAttribute( 'data-event-title' ) || '';
		var heading;
		var replacement;
		var emptyHeadings;

		if ( hero ) {
			heading = hero.querySelector( 'h1' );

			if ( heading ) {
				replacement = document.createElement( 'p' );
				replacement.className = heading.className ? heading.className + ' avf-event-detail__hero-label' : 'avf-event-detail__hero-label';
				replacement.textContent = title ? 'Anlässe' : heading.textContent;
				heading.parentNode.replaceChild( replacement, heading );
			}
		}

		emptyHeadings = document.querySelectorAll( 'h1' );
		emptyHeadings.forEach( function ( item ) {
			if ( item.closest( '.avf-event-detail' ) ) {
				return;
			}

			if ( ! item.textContent || ! item.textContent.trim() ) {
				item.parentNode.removeChild( item );
			}
		} );
	}

	function toggleFieldError( field, message ) {
		var describedBy = field.getAttribute( 'aria-describedby' ) || '';
		var ids = describedBy.split( /\s+/ );
		var errorId = null;
		var errorNode = null;

		ids.forEach( function ( id ) {
			if ( ! errorId && /-error$/.test( id ) ) {
				errorId = id;
			}
		} );

		if ( errorId ) {
			errorNode = document.getElementById( errorId );
		}

		if ( message ) {
			field.setAttribute( 'aria-invalid', 'true' );
			if ( errorNode ) {
				errorNode.hidden = false;
				errorNode.textContent = message;
			}
			return false;
		}

		field.removeAttribute( 'aria-invalid' );
		if ( errorNode ) {
			errorNode.hidden = true;
			errorNode.textContent = '';
		}
		return true;
	}

	function validateField( field ) {
		var type = ( field.getAttribute( 'type' ) || '' ).toLowerCase();
		var tag = field.tagName.toLowerCase();
		var message = '';

		if ( field.disabled || ! field.hasAttribute( 'required' ) ) {
			return toggleFieldError( field, '' );
		}

		if ( 'checkbox' === type ) {
			if ( ! field.checked ) {
				message = field.getAttribute( 'data-avf-required-message' ) || 'Dieses Feld ist erforderlich.';
			}
			return toggleFieldError( field, message );
		}

		if ( 'select' === tag && ! field.value ) {
			message = field.getAttribute( 'data-avf-required-message' ) || 'Bitte wählen.';
			return toggleFieldError( field, message );
		}

		if ( ! String( field.value || '' ).trim() ) {
			message = field.getAttribute( 'data-avf-required-message' ) || 'Dieses Feld ist erforderlich.';
		}

		return toggleFieldError( field, message );
	}

	function initEventForm( root ) {
		var form = root.querySelector( '[data-avf-event-form]' );
		var fields;

		if ( ! form ) {
			return;
		}

		form.setAttribute( 'novalidate', 'novalidate' );
		fields = form.querySelectorAll( 'input[required], select[required], textarea[required]' );

		fields.forEach( function ( field ) {
			var eventName = 'checkbox' === ( field.getAttribute( 'type' ) || '' ).toLowerCase() ? 'change' : 'input';
			field.addEventListener( eventName, function () {
				validateField( field );
			} );
			field.addEventListener( 'blur', function () {
				validateField( field );
			} );
		} );

		form.addEventListener( 'submit', function ( event ) {
			var firstInvalid = null;

			fields.forEach( function ( field ) {
				if ( ! validateField( field ) && ! firstInvalid ) {
					firstInvalid = field;
				}
			} );

			if ( firstInvalid ) {
				event.preventDefault();
				firstInvalid.focus();
			}
		} );
	}

	function initStickyCta( root ) {
		var sticky = root.querySelector( '.avf-event-detail__sticky-cta' );
		var formBlock = root.querySelector( '.avf-event-detail__form-block' );
		var mediaQuery = window.matchMedia( '(max-width: 767px)' );
		var observer = null;

		function updateByViewport() {
			if ( ! sticky ) {
				return;
			}

			if ( ! mediaQuery.matches ) {
				root.classList.remove( 'avf-event-detail--show-sticky-cta' );
			}
		}

		if ( ! sticky || ! formBlock || ! ( 'IntersectionObserver' in window ) ) {
			return;
		}

		observer = new IntersectionObserver( function ( entries ) {
			entries.forEach( function ( entry ) {
				if ( ! mediaQuery.matches ) {
					root.classList.remove( 'avf-event-detail--show-sticky-cta' );
					return;
				}

				root.classList.toggle( 'avf-event-detail--show-sticky-cta', ! entry.isIntersecting );
			} );
		}, { threshold: 0.2 } );

		observer.observe( formBlock );
		updateByViewport();
		if ( typeof mediaQuery.addEventListener === 'function' ) {
			mediaQuery.addEventListener( 'change', updateByViewport );
		} else if ( typeof mediaQuery.addListener === 'function' ) {
			mediaQuery.addListener( updateByViewport );
		}
	}

	function initEventDetail() {
		var root = document.querySelector( '.avf-event-detail' );

		if ( ! root ) {
			return;
		}

		demoteHeroHeading( root );
		focusNotice( root );
		initEventForm( root );
		initStickyCta( root );
	}

	function init() {
		initMoreLinks();
		initCalendarActions();
		initEventDetail();
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
