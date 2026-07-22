/**
 * AV Froburger Events Integration - "Mehr anzeigen" progressive enhancement
 * for [avf_events_upcoming] and [avf_events_past].
 *
 * Without this script, the "Mehr anzeigen" link PHP already renders is a
 * plain <a href="...?avf_upcoming_shown=18">: a full page reload that shows
 * more items server-side. This script intercepts clicks on that same link,
 * fetches only the new cards via AJAX and appends them without a reload -
 * on any failure it falls back to the original link navigation, so the
 * feature never dead-ends.
 *
 * Handles any number of [avf_events_upcoming]/[avf_events_past] instances
 * on the same page independently (each button carries its own state via
 * data-attributes, none of it relies on a single global id).
 */
( function () {
	'use strict';

	if ( typeof window.avfEventsAjax === 'undefined' ) {
		return;
	}

	/**
	 * Rebuilds a "Mehr anzeigen" href with an updated "shown" query value,
	 * preserving the rest of the current URL (including any other
	 * shortcode's own query var, e.g. avf_past_shown alongside
	 * avf_upcoming_shown).
	 *
	 * @param {string} queryVar Query var name for this instance.
	 * @param {number} value    New value.
	 * @return {string}
	 */
	function buildHref( queryVar, value ) {
		var url = new URL( window.location.href );
		url.searchParams.set( queryVar, String( value ) );
		return url.toString();
	}

	/**
	 * Handles a single "Mehr anzeigen" click: fetches the next batch and
	 * appends it, or falls back to a normal navigation on failure.
	 *
	 * @param {MouseEvent} event Click event.
	 * @return {void}
	 */
	function handleClick( event ) {
		var link = event.currentTarget;

		if ( 'true' === link.getAttribute( 'aria-busy' ) ) {
			event.preventDefault();
			return;
		}

		event.preventDefault();

		var type      = link.getAttribute( 'data-type' );
		var shown     = link.getAttribute( 'data-shown' );
		var step      = link.getAttribute( 'data-step' );
		var nonce     = link.getAttribute( 'data-nonce' );
		var queryVar  = link.getAttribute( 'data-query-var' );
		var targetId  = link.getAttribute( 'data-list-target' );
		var list      = targetId ? document.getElementById( targetId ) : null;
		var originalHref = link.href;

		if ( ! list || ! type || ! shown || ! step || ! nonce ) {
			window.location.href = originalHref;
			return;
		}

		link.setAttribute( 'aria-busy', 'true' );

		var body = new URLSearchParams();
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
				if ( ! json || ! json.success || ! json.data ) {
					throw new Error( 'Unsuccessful response' );
				}

				if ( json.data.html ) {
					list.insertAdjacentHTML( 'beforeend', json.data.html );
				}

				var newShown = json.data.shown ? parseInt( json.data.shown, 10 ) : ( parseInt( shown, 10 ) + parseInt( step, 10 ) );

				if ( json.data.has_more ) {
					link.setAttribute( 'data-shown', String( newShown ) );
					link.href = buildHref( queryVar, newShown + parseInt( step, 10 ) );
					link.removeAttribute( 'aria-busy' );
				} else {
					var wrap = link.closest( '.avf-events-more-wrap' );
					if ( wrap && wrap.parentNode ) {
						wrap.parentNode.removeChild( wrap );
					} else {
						link.removeAttribute( 'aria-busy' );
						link.style.display = 'none';
					}
				}
			} )
			.catch( function () {
				// Never dead-end: fall back to the plain link the server
				// already rendered (full page reload, still shows more items).
				window.location.href = originalHref;
			} );
	}

	function init() {
		var links = document.querySelectorAll( '[data-avf-events-more]' );
		for ( var i = 0; i < links.length; i++ ) {
			links[ i ].addEventListener( 'click', handleClick );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
