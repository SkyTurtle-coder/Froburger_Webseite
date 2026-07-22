/**
 * AV Froburger Zirkel Decor.
 *
 * Finds ".avf-decor" elements and injects a purely decorative,
 * aria-hidden layer of Zirkel rings, positioned with a deterministic
 * pseudo-random function seeded from the current path, the section's
 * index on the page, the ring's index and an optional manual seed
 * attribute. Vanilla JS, no dependencies.
 *
 * In addition, a small set of project-wide "auto presets" lets specific
 * semantic section classes (e.g. ".avf-values-section") opt into the
 * decoration system automatically, without editors having to add the
 * ".avf-decor" base class, a variant/density class or a seed by hand.
 * Manual classes and a manual seed, when already present, always win.
 *
 * A separate, independent "page-wide" mode automatically decorates
 * ".avf-home-main" (front page) and any ".avf-has-zirkel-bg" container
 * (opt-in via Elementor "Erweitert -> CSS-Klassen" on any element) with
 * a single ".avf-page-zirkel-decor-layer" each. Its
 * distribution logic (position, size, rotation, opacity, color) uses a
 * seeded pseudo-random generator plus circle-based rejection sampling so
 * rings land at edge-biased, randomised-looking positions without ever
 * visibly overlapping each other, while staying perfectly stable across
 * reloads (same seed in, same layout out - "random" here means
 * "randomly generated once, then stored via a deterministic seed", not
 * "different on every page load"). It never interacts with the
 * ".avf-decor" pipeline above.
 *
 * A third, independent mode decorates ".avf-page-head" (the global page
 * header template used on normal pages) with a fixed, art-directed pair
 * of rings - one large, bleeding off the right edge, one small accent
 * ring top-left. Deliberately not seeded/randomised like the two modes
 * above: a hero banner benefits from one deliberate composition rather
 * than per-page variation. Position/size/opacity are plain CSS
 * (".avf-page-head__ring--large/--small"); this pipeline only handles
 * idempotent injection.
 */
( function () {
	'use strict';

	var BASE_CLASS = 'avf-decor';
	var LAYER_CLASS = 'avf-zirkel-decor-layer';
	var READY_ATTR = 'data-avf-decor-ready';
	var SEED_ATTR = 'data-avf-decor-seed';
	var VARIANT_CLASSES = [ 'avf-decor--light', 'avf-decor--dark' ];
	var DENSITY_CLASSES = [ 'avf-decor--subtle', 'avf-decor--dense' ];

	/**
	 * Project-wide auto presets: a semantic section class automatically
	 * receives the base class, a variant class, a density class and a
	 * seed – unless the editor already set one of these manually.
	 */
	var AUTO_PRESETS = [
		{
			selector: '.avf-values-section',
			variantClass: 'avf-decor--light',
			densityClass: 'avf-decor--dense',
			seed: 'werte-startseite',
		},
		{
			selector: '.avf-quote-section',
			variantClass: 'avf-decor--dark',
			densityClass: 'avf-decor--subtle',
			seed: 'zitat-startseite',
		},
	];

	var AUTO_PRESET_SELECTORS = [];
	for ( var presetIndex = 0; presetIndex < AUTO_PRESETS.length; presetIndex++ ) {
		AUTO_PRESET_SELECTORS.push( AUTO_PRESETS[ presetIndex ].selector );
	}

	/**
	 * Selector matching everything the observer/initial scan must look
	 * for: manually decorated elements plus every auto-preset section.
	 */
	var CANDIDATE_SELECTOR = [ '.' + BASE_CLASS ].concat( AUTO_PRESET_SELECTORS ).join( ', ' );

	var SIZE_RANGES = {
		desktop: [ 380, 760 ],
		tablet: [ 300, 560 ],
		mobile: [ 220, 380 ],
	};

	var OPACITY_RANGES = {
		light: [ 0.07, 0.12 ],
		dark: [ 0.035, 0.09 ],
	};

	/* -----------------------------------------------------------
	 * Shared helpers (used by both the manual ".avf-decor" pipeline
	 * and the page-wide ".avf-home-main" pipeline below).
	 * ----------------------------------------------------------- */

	/**
	 * Small, fast, deterministic string hash (FNV-1a, 32-bit).
	 *
	 * @param {string} str Input string.
	 * @return {number} Unsigned 32-bit hash.
	 */
	function fnv1a( str ) {
		var hash = 0x811c9dc5;
		for ( var i = 0; i < str.length; i++ ) {
			hash ^= str.charCodeAt( i );
			hash = ( hash * 0x01000193 ) >>> 0;
		}
		return hash >>> 0;
	}

	/**
	 * Deterministically derives a value in [0, 1) from an arbitrary key.
	 * Used by the manual ".avf-decor" pipeline, where every value has its
	 * own uniquely-built key string.
	 *
	 * @param {string} key Seed key.
	 * @return {number} Value between 0 (inclusive) and 1 (exclusive).
	 */
	function seededUnitFloat( key ) {
		return fnv1a( key ) / 4294967296;
	}

	/**
	 * Linear interpolation between min and max by fraction t.
	 *
	 * @param {number} min Minimum value.
	 * @param {number} max Maximum value.
	 * @param {number} t   Fraction between 0 and 1.
	 * @return {number}
	 */
	function lerp( min, max, t ) {
		return min + ( max - min ) * t;
	}

	/**
	 * Resolves the current responsive size bucket.
	 *
	 * @return {string} "desktop", "tablet" or "mobile".
	 */
	function getBreakpoint() {
		var width = window.innerWidth;
		if ( width <= 560 ) {
			return 'mobile';
		}
		if ( width <= 900 ) {
			return 'tablet';
		}
		return 'desktop';
	}

	/**
	 * Applies the computed custom properties to a ring element.
	 *
	 * @param {Element} ring   Ring <span>.
	 * @param {Object}  values Computed values (size in px, left/top as
	 *                         CSS length strings, rotate in degrees,
	 *                         opacity as a string).
	 * @return {void}
	 */
	function applyRingStyle( ring, values ) {
		ring.style.setProperty( '--avf-ring-size', values.size + 'px' );
		ring.style.setProperty( '--avf-ring-left', values.left );
		ring.style.setProperty( '--avf-ring-top', values.top );
		ring.style.setProperty( '--avf-ring-rotate', values.rotate + 'deg' );
		ring.style.setProperty( '--avf-ring-opacity', values.opacity );
	}

	/* -----------------------------------------------------------
	 * Manual ".avf-decor" pipeline (unchanged). Sections opt in via
	 * the "avf-decor" class (directly or through an auto preset) and
	 * get 1-3 rings positioned via the simple side/band heuristic
	 * below. Left entirely as-is - only the page-wide ".avf-home-main"
	 * pipeline further down was reworked.
	 * ----------------------------------------------------------- */

	/**
	 * Number of rings for a given .avf-decor element. "subtle" wins over
	 * "dense" when both are present.
	 *
	 * @param {Element} el Decor element.
	 * @return {number}
	 */
	function getRingCount( el ) {
		if ( el.classList.contains( 'avf-decor--subtle' ) ) {
			return 1;
		}
		if ( el.classList.contains( 'avf-decor--dense' ) ) {
			return 3;
		}
		return 2;
	}

	/**
	 * Alternates ring color: green, orange, green, orange, ...
	 *
	 * @param {number} index Ring index within its layer.
	 * @return {string} "green" or "orange".
	 */
	function getColorForIndex( index ) {
		return index % 2 === 0 ? 'green' : 'orange';
	}

	/**
	 * Builds the deterministic seed key shared by every value derived for
	 * one specific ring.
	 *
	 * @param {number} sectionIndex Index of the section among all .avf-decor elements.
	 * @param {string} seedAttr     Optional manual seed attribute value.
	 * @param {number} ringIndex    Index of the ring within its section.
	 * @return {string}
	 */
	function buildRingKey( sectionIndex, seedAttr, ringIndex ) {
		return window.location.pathname + '|' + sectionIndex + '|' + ringIndex + '|' + seedAttr;
	}

	/**
	 * Computes the CSS custom property values for a single ring.
	 *
	 * @param {string}  ringKey    Deterministic key for this ring.
	 * @param {boolean} isDark     Whether the section uses the dark variant.
	 * @param {string}  breakpoint Current responsive bucket.
	 * @return {Object}
	 */
	function computeRingValues( ringKey, isDark, breakpoint ) {
		var sizeRange = SIZE_RANGES[ breakpoint ] || SIZE_RANGES.desktop;
		var size = Math.round( lerp( sizeRange[ 0 ], sizeRange[ 1 ], seededUnitFloat( ringKey + '|size' ) ) );

		var onLeftSide = seededUnitFloat( ringKey + '|side' ) < 0.5;
		var overflowFraction = lerp( 0.25, 0.55, seededUnitFloat( ringKey + '|overflow' ) );
		var left;
		if ( onLeftSide ) {
			left = '-' + Math.round( size * overflowFraction ) + 'px';
		} else {
			left = 'calc(100% - ' + Math.round( size * ( 1 - overflowFraction ) ) + 'px)';
		}

		var inTopBand = seededUnitFloat( ringKey + '|vband' ) < 0.5;
		var vFrac = seededUnitFloat( ringKey + '|vfrac' );
		var top = inTopBand ? lerp( 0, 28, vFrac ) : lerp( 72, 100, vFrac );

		var rotate = Math.round( lerp( -35, 35, seededUnitFloat( ringKey + '|rotate' ) ) );

		var opacityRange = isDark ? OPACITY_RANGES.dark : OPACITY_RANGES.light;
		var opacity = lerp( opacityRange[ 0 ], opacityRange[ 1 ], seededUnitFloat( ringKey + '|opacity' ) );

		return {
			size: size,
			left: left,
			top: top.toFixed( 1 ) + '%',
			rotate: rotate,
			opacity: opacity.toFixed( 3 ),
		};
	}

	/**
	 * Returns the index of a decor element among all .avf-decor elements
	 * currently in the document, in DOM order.
	 *
	 * @param {Element} el Decor element.
	 * @return {number}
	 */
	function getSectionIndex( el ) {
		var all = document.querySelectorAll( '.' + BASE_CLASS );
		for ( var i = 0; i < all.length; i++ ) {
			if ( all[ i ] === el ) {
				return i;
			}
		}
		return 0;
	}

	/**
	 * Finds the auto preset matching an element, if any.
	 *
	 * @param {Element} el Candidate element.
	 * @return {Object|null}
	 */
	function findAutoPreset( el ) {
		if ( typeof el.matches !== 'function' ) {
			return null;
		}
		for ( var i = 0; i < AUTO_PRESETS.length; i++ ) {
			if ( el.matches( AUTO_PRESETS[ i ].selector ) ) {
				return AUTO_PRESETS[ i ];
			}
		}
		return null;
	}

	/**
	 * Whether the element already carries one of the given classes.
	 *
	 * @param {Element} el         Element to check.
	 * @param {string[]} classList List of class names.
	 * @return {boolean}
	 */
	function hasAnyClass( el, classList ) {
		for ( var i = 0; i < classList.length; i++ ) {
			if ( el.classList.contains( classList[ i ] ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Applies a matching auto preset to an element: adds the base class,
	 * a variant class and a density class only when the editor hasn't
	 * already set one manually, and sets a default seed only when no
	 * seed attribute is present yet. No-op if no preset matches.
	 *
	 * @param {Element} el Candidate element.
	 * @return {void}
	 */
	function applyAutoPreset( el ) {
		var preset = findAutoPreset( el );
		if ( ! preset ) {
			return;
		}

		if ( ! el.classList.contains( BASE_CLASS ) ) {
			el.classList.add( BASE_CLASS );
		}

		if ( ! hasAnyClass( el, VARIANT_CLASSES ) ) {
			el.classList.add( preset.variantClass );
		}

		if ( ! hasAnyClass( el, DENSITY_CLASSES ) ) {
			el.classList.add( preset.densityClass );
		}

		var existingSeed = el.getAttribute( SEED_ATTR );
		if ( ! existingSeed || '' === existingSeed.replace( /\s+/g, '' ) ) {
			el.setAttribute( SEED_ATTR, preset.seed );
		}
	}

	/**
	 * Creates and inserts the decoration layer for a single element.
	 *
	 * @param {Element} el Decor element, not yet processed.
	 * @return {void}
	 */
	function buildLayer( el ) {
		var sectionIndex = getSectionIndex( el );
		var seedAttr = el.getAttribute( SEED_ATTR ) || '';
		var isDark = el.classList.contains( 'avf-decor--dark' );
		var breakpoint = getBreakpoint();
		var ringCount = getRingCount( el );

		var layer = document.createElement( 'div' );
		layer.className = LAYER_CLASS;
		layer.setAttribute( 'aria-hidden', 'true' );

		for ( var i = 0; i < ringCount; i++ ) {
			var ringKey = buildRingKey( sectionIndex, seedAttr, i );
			var values = computeRingValues( ringKey, isDark, breakpoint );

			var ring = document.createElement( 'span' );
			ring.className = 'avf-zirkel avf-zirkel--' + getColorForIndex( i );
			applyRingStyle( ring, values );

			layer.appendChild( ring );
		}

		el.insertBefore( layer, el.firstChild );
		el.setAttribute( READY_ATTR, 'true' );
	}

	/**
	 * Processes a node: if it (or any descendant) is an un-processed
	 * .avf-decor element or an auto-preset section, a decoration layer
	 * is built for it. Auto-preset classes/seed are applied first, so
	 * the rest of the pipeline only ever has to deal with ".avf-decor".
	 *
	 * @param {Node} node Root node to inspect (itself and its subtree).
	 * @return {void}
	 */
	function processNode( node ) {
		if ( ! node || node.nodeType !== 1 ) {
			return;
		}

		var candidates = [];

		if ( typeof node.matches === 'function' && node.matches( CANDIDATE_SELECTOR ) ) {
			candidates.push( node );
		}

		if ( typeof node.querySelectorAll === 'function' ) {
			var found = node.querySelectorAll( CANDIDATE_SELECTOR );
			for ( var i = 0; i < found.length; i++ ) {
				candidates.push( found[ i ] );
			}
		}

		for ( var j = 0; j < candidates.length; j++ ) {
			var el = candidates[ j ];

			if ( el.getAttribute( READY_ATTR ) === 'true' ) {
				continue;
			}

			if ( el.querySelector( ':scope > .' + LAYER_CLASS ) ) {
				el.setAttribute( READY_ATTR, 'true' );
				continue;
			}

			applyAutoPreset( el );

			if ( ! el.classList.contains( BASE_CLASS ) ) {
				continue;
			}

			buildLayer( el );
		}
	}

	/**
	 * Recomputes only the size/left custom properties of already-placed
	 * rings after the responsive bucket changed (e.g. viewport resize).
	 *
	 * @param {string} breakpoint New responsive bucket.
	 * @return {void}
	 */
	function refreshRingSizes( breakpoint ) {
		var sections = document.querySelectorAll( '.' + BASE_CLASS + '[' + READY_ATTR + '="true"]' );

		for ( var i = 0; i < sections.length; i++ ) {
			var el = sections[ i ];
			var sectionIndex = getSectionIndex( el );
			var seedAttr = el.getAttribute( SEED_ATTR ) || '';
			var isDark = el.classList.contains( 'avf-decor--dark' );
			var rings = el.querySelectorAll( '.avf-zirkel' );

			for ( var r = 0; r < rings.length; r++ ) {
				var ringKey = buildRingKey( sectionIndex, seedAttr, r );
				var values = computeRingValues( ringKey, isDark, breakpoint );
				rings[ r ].style.setProperty( '--avf-ring-size', values.size + 'px' );
				rings[ r ].style.setProperty( '--avf-ring-left', values.left );
			}
		}
	}

	/* -----------------------------------------------------------
	 * Page-wide decoration mode (".avf-home-main" and ".avf-has-zirkel-bg").
	 *
	 * Reworked distribution logic: a seeded pseudo-random generator
	 * (createSeededRandom) drives randomised-looking, edge-biased ring
	 * placement, with circle-based rejection sampling
	 * (generateCandidate/circlesOverlap/canPlaceCandidate) to guarantee
	 * rings never visibly collide, plus soft exclusion zones over key
	 * content and a distinct opacity range for the dark quote band.
	 * ----------------------------------------------------------- */

	// Every class that opts a container into the page-wide decoration
	// mode: ".avf-home-main" (front page, automatic) plus ".avf-has-zirkel-bg"
	// (manual opt-in via Elementor "Erweitert -> CSS-Klassen" on any
	// container). Adding a class here is enough to support it everywhere
	// below - no selector is hard-coded to a single class past this point.
	var PAGE_CLASSES = [ 'avf-home-main', 'avf-has-zirkel-bg' ];
	var PAGE_LAYER_CLASS = 'avf-page-zirkel-decor-layer';
	var PAGE_READY_ATTR = 'data-avf-page-decor-ready';
	var PAGE_RING_HEIGHT_PX = 700; // ~1 ring per 700px of container height.

	/**
	 * Builds a selector matching every page-wide opt-in class, optionally
	 * with a suffix (e.g. an attribute selector) applied to each one
	 * individually - "a, b[x]" is not the same as "a, b"[x], so a plain
	 * join would silently misbehave once a suffix is involved.
	 *
	 * @param {string} [suffix] Optional suffix appended to each class selector.
	 * @return {string} Combined selector, comma-separated.
	 */
	function buildPageSelector( suffix ) {
		var parts = [];
		for ( var i = 0; i < PAGE_CLASSES.length; i++ ) {
			parts.push( '.' + PAGE_CLASSES[ i ] + ( suffix || '' ) );
		}
		return parts.join( ', ' );
	}

	var PAGE_SELECTOR = buildPageSelector();

	// Lower bounds intentionally left low enough that a short container
	// (e.g. ".avf-has-zirkel-bg" on a page with just one or two posts)
	// isn't forced up to the homepage's ring density: rawCount from
	// height/PAGE_RING_HEIGHT_PX already exceeds these minimums on the
	// homepage's own (much taller) ".avf-home-main", so its ring count is
	// unaffected - only genuinely short containers actually hit the floor.
	var PAGE_RING_COUNT_LIMITS = {
		desktop: [ 3, 9 ],
		tablet: [ 2, 7 ],
		mobile: [ 1, 4 ],
	};

	var PAGE_SIZE_RANGES = {
		desktop: [ 360, 680 ],
		tablet: [ 280, 500 ],
		mobile: [ 190, 330 ],
	};

	var PAGE_GAP_RANGES = {
		desktop: [ 50, 80 ],
		tablet: [ 35, 60 ],
		mobile: [ 20, 40 ],
	};

	var PAGE_OPACITY_LIGHT_RANGE = [ 0.08, 0.15 ];
	var PAGE_OPACITY_DARK_RANGE = [ 0.05, 0.10 ];

	// Edge-vs-center bias: ~70% of rings sit on the left/right edge.
	var PAGE_EDGE_PROBABILITY = 0.7;
	var PAGE_EDGE_OVERFLOW_RANGE = [ 0.3, 0.6 ];
	var PAGE_CENTER_BAND = [ 0.3, 0.7 ]; // fraction of container width for non-edge rings.
	var PAGE_ZONE_VFRAC_RANGE = [ 0.12, 0.88 ]; // where within its zone a ring's center may fall.

	// Minimum fraction of a ring that must stay vertically inside the container.
	var PAGE_MIN_VERTICAL_VISIBLE = 0.3;

	// Rejection-sampling passes per ring: shrink size and drop the (soft)
	// exclusion-zone check on later passes rather than looping forever.
	// Total attempts across all passes stays within the 150-250 range.
	var PAGE_PLACEMENT_PASSES = [
		{ sizeMultiplier: 1, attempts: 96, enforceExclusion: true },
		{ sizeMultiplier: 1, attempts: 64, enforceExclusion: false },
		{ sizeMultiplier: 0.8, attempts: 40, enforceExclusion: false },
		{ sizeMultiplier: 0.65, attempts: 20, enforceExclusion: false },
	];

	// Descendant selectors treated as soft "keep clear" zones so rings
	// avoid sitting dead-center behind key copy. Purely a placement
	// preference (see PAGE_PLACEMENT_PASSES: only enforced in the first
	// pass) - never allowed to block placement outright.
	var PAGE_EXCLUSION_SELECTORS = [
		'.avf-featured-post__content',
		'.avf-home-values__heading',
		'.avf-home-quote__inner',
	];
	var PAGE_EXCLUSION_PADDING = 24;

	// Per-container state (breakpoint + height at last build) so resize
	// handling can tell "nothing meaningfully changed" apart from a real
	// breakpoint/height change, without any extra DOM attributes.
	var pageLayoutState = ( typeof WeakMap === 'function' ) ? new WeakMap() : null;

	/**
	 * Deterministic 32-bit PRNG (mulberry32), seeded from an FNV-1a hash
	 * of an arbitrary string. Unlike seededUnitFloat() above, this
	 * returns a *function* that yields a fresh deterministic value in
	 * [0, 1) on every call, which is what rejection sampling needs (many
	 * sequential random decisions per ring, without hand-building a
	 * unique key string for each one). The sequence is fully determined
	 * by the seed string alone, so two independent reloads with the same
	 * seed always produce the same sequence of values.
	 *
	 * @param {string} seedString Seed key.
	 * @return {Function} Call with no arguments to get the next value.
	 */
	function createSeededRandom( seedString ) {
		var state = fnv1a( seedString ) || 1; // avoid a zero state.
		return function () {
			state |= 0;
			state = ( state + 0x6d2b79f5 ) | 0;
			var t = Math.imul( state ^ ( state >>> 15 ), 1 | state );
			t = ( t + Math.imul( t ^ ( t >>> 7 ), 61 | t ) ) ^ t;
			return ( ( t ^ ( t >>> 14 ) ) >>> 0 ) / 4294967296;
		};
	}

	/**
	 * Resolves the size/gap/count configuration for the current
	 * responsive bucket.
	 *
	 * @param {string} breakpoint "desktop", "tablet" or "mobile".
	 * @return {Object}
	 */
	function getResponsiveConfig( breakpoint ) {
		return {
			sizeRange: PAGE_SIZE_RANGES[ breakpoint ] || PAGE_SIZE_RANGES.desktop,
			gapRange: PAGE_GAP_RANGES[ breakpoint ] || PAGE_GAP_RANGES.desktop,
			countLimits: PAGE_RING_COUNT_LIMITS[ breakpoint ] || PAGE_RING_COUNT_LIMITS.desktop,
		};
	}

	/**
	 * Clamps a raw ring count into the allowed [min, max] range for the
	 * current breakpoint.
	 *
	 * @param {number} count      Raw computed ring count.
	 * @param {string} breakpoint Current responsive bucket.
	 * @return {number}
	 */
	function clampPageRingCount( count, breakpoint ) {
		var limits = PAGE_RING_COUNT_LIMITS[ breakpoint ] || PAGE_RING_COUNT_LIMITS.desktop;
		return Math.max( limits[ 0 ], Math.min( limits[ 1 ], count ) );
	}

	/**
	 * Splits the container height into `count` equal vertical zones, used
	 * to spread rings across the full height instead of letting rejection
	 * sampling cluster them by chance.
	 *
	 * @param {number} containerHeight Container height in px.
	 * @param {number} count           Number of zones/rings.
	 * @return {Object[]} Array of { top, height } in px.
	 */
	function buildVerticalZones( containerHeight, count ) {
		var zoneHeight = containerHeight / count;
		var zones = [];
		for ( var i = 0; i < count; i++ ) {
			zones.push( { top: i * zoneHeight, height: zoneHeight } );
		}
		return zones;
	}

	/**
	 * Deterministic Fisher-Yates shuffle, driven by a seeded random
	 * function instead of Math.random().
	 *
	 * @param {Array}    array Array to shuffle (not mutated).
	 * @param {Function} rand  Seeded random function.
	 * @return {Array} A new, shuffled array.
	 */
	function shuffleDeterministic( array, rand ) {
		var result = array.slice();
		for ( var i = result.length - 1; i > 0; i-- ) {
			var j = Math.floor( rand() * ( i + 1 ) );
			var tmp = result[ i ];
			result[ i ] = result[ j ];
			result[ j ] = tmp;
		}
		return result;
	}

	/**
	 * Circle-overlap test using a simplified circular bounding box per
	 * ring, as specified: two rings collide if the distance between
	 * their centers is smaller than the sum of their radii plus a safety
	 * gap.
	 *
	 * @param {Object} a   Circle with centerX/centerY/radius.
	 * @param {Object} b   Circle with centerX/centerY/radius.
	 * @param {number} gap Minimum required clearance, in px.
	 * @return {boolean}
	 */
	function circlesOverlap( a, b, gap ) {
		var dx = a.centerX - b.centerX;
		var dy = a.centerY - b.centerY;
		var distance = Math.hypot( dx, dy );

		return distance < a.radius + b.radius + gap;
	}

	/**
	 * Circle-vs-rectangle overlap test (nearest-point method), used for
	 * the soft content exclusion zones.
	 *
	 * @param {Object} rect Rect with left/right/top/bottom.
	 * @param {number} cx   Circle center X.
	 * @param {number} cy   Circle center Y.
	 * @param {number} r    Circle radius.
	 * @return {boolean}
	 */
	function rectOverlapsCircle( rect, cx, cy, r ) {
		var nearestX = Math.max( rect.left, Math.min( cx, rect.right ) );
		var nearestY = Math.max( rect.top, Math.min( cy, rect.bottom ) );
		var dx = cx - nearestX;
		var dy = cy - nearestY;
		return dx * dx + dy * dy < r * r;
	}

	/**
	 * Locates the dark "Zitatband" (.avf-home-quote) within the
	 * container, if present, and returns its vertical extent relative to
	 * the container's own top - used to pick the darker opacity range
	 * for rings that land on it.
	 *
	 * @param {Element} el The ".avf-home-main" container.
	 * @return {Object|null} { top, bottom } in px relative to el, or null.
	 */
	function findDarkZoneRect( el ) {
		var quote = el.querySelector( '.avf-home-quote' );
		if ( ! quote ) {
			return null;
		}
		var elRect = el.getBoundingClientRect();
		var qRect = quote.getBoundingClientRect();
		return {
			top: qRect.top - elRect.top,
			bottom: qRect.bottom - elRect.top,
		};
	}

	/**
	 * Locates the soft "keep clear" content zones within the container
	 * (featured-post copy, the values heading, the quote text), if
	 * present. Missing elements are silently skipped - this is a
	 * placement preference, not a hard requirement.
	 *
	 * @param {Element} el The ".avf-home-main" container.
	 * @return {Object[]} Array of { left, right, top, bottom } in px
	 *                     relative to el.
	 */
	function findExclusionZones( el ) {
		var elRect = el.getBoundingClientRect();
		var zones = [];

		for ( var i = 0; i < PAGE_EXCLUSION_SELECTORS.length; i++ ) {
			var target = el.querySelector( PAGE_EXCLUSION_SELECTORS[ i ] );
			if ( ! target ) {
				continue;
			}
			var r = target.getBoundingClientRect();
			if ( r.width <= 0 || r.height <= 0 ) {
				continue;
			}
			zones.push( {
				left: r.left - elRect.left - PAGE_EXCLUSION_PADDING,
				right: r.right - elRect.left + PAGE_EXCLUSION_PADDING,
				top: r.top - elRect.top - PAGE_EXCLUSION_PADDING,
				bottom: r.bottom - elRect.top + PAGE_EXCLUSION_PADDING,
			} );
		}

		return zones;
	}

	/**
	 * Generates one randomised ring candidate within a given vertical
	 * zone. Horizontal placement is edge-biased (~70% left/right edge,
	 * 30-60% of the ring hanging outside the container; the rest nearer
	 * the horizontal center). Vertical placement is a random point within
	 * the zone, keeping the overall layout spread across the full
	 * container height without a mechanical fixed grid.
	 *
	 * @param {Function} rand           Seeded random function.
	 * @param {Object}   config         { sizeRange } for this breakpoint.
	 * @param {number}   containerWidth Container width in px.
	 * @param {Object}   zone           { top, height } for this ring.
	 * @param {number}   sizeMultiplier Shrink factor for later rejection
	 *                                  passes (1 = full size).
	 * @return {Object} Candidate circle: size, radius, centerX, centerY,
	 *                   left, top, rotate.
	 */
	function generateCandidate( rand, config, containerWidth, zone, sizeMultiplier ) {
		var sizeRange = config.sizeRange;
		var size = Math.round( lerp( sizeRange[ 0 ], sizeRange[ 1 ], rand() ) * sizeMultiplier );
		var radius = size / 2;

		var centerX;
		if ( rand() < PAGE_EDGE_PROBABILITY ) {
			var onLeftSide = rand() < 0.5;
			var overflowFraction = lerp( PAGE_EDGE_OVERFLOW_RANGE[ 0 ], PAGE_EDGE_OVERFLOW_RANGE[ 1 ], rand() );
			centerX = onLeftSide ?
				size * ( 0.5 - overflowFraction ) :
				containerWidth - size * ( 0.5 - overflowFraction );
		} else {
			centerX = lerp( containerWidth * PAGE_CENTER_BAND[ 0 ], containerWidth * PAGE_CENTER_BAND[ 1 ], rand() );
		}

		var vFrac = lerp( PAGE_ZONE_VFRAC_RANGE[ 0 ], PAGE_ZONE_VFRAC_RANGE[ 1 ], rand() );
		var centerY = zone.top + zone.height * vFrac;

		var rotate = Math.round( lerp( -35, 35, rand() ) );

		return {
			size: size,
			radius: radius,
			centerX: centerX,
			centerY: centerY,
			left: centerX - radius,
			top: centerY - radius,
			rotate: rotate,
		};
	}

	/**
	 * Whether a candidate ring can be placed: it must keep at least
	 * PAGE_MIN_VERTICAL_VISIBLE of its height inside the container
	 * vertically, must not collide with any already-placed ring (plus
	 * the safety gap), and - only while `enforceExclusion` is true for
	 * this pass - should not sit on top of a soft content exclusion
	 * zone. Horizontal overflow is intentionally unrestricted: the decor
	 * layer itself clips via `overflow: hidden`, so an off-edge ring
	 * never causes a horizontal scrollbar.
	 *
	 * @param {Object}  candidate        Candidate circle.
	 * @param {Object[]} placed          Already-accepted circles.
	 * @param {number}  gap              Safety gap in px.
	 * @param {Object[]} exclusionZones  Soft exclusion rects.
	 * @param {boolean} enforceExclusion Whether to check exclusionZones.
	 * @param {number}  containerHeight  Container height in px.
	 * @return {boolean}
	 */
	function canPlaceCandidate( candidate, placed, gap, exclusionZones, enforceExclusion, containerHeight ) {
		var visibleTop = Math.max( candidate.top, 0 );
		var visibleBottom = Math.min( candidate.top + candidate.size, containerHeight );
		var visibleHeight = Math.max( 0, visibleBottom - visibleTop );
		if ( visibleHeight < candidate.size * PAGE_MIN_VERTICAL_VISIBLE ) {
			return false;
		}

		for ( var i = 0; i < placed.length; i++ ) {
			if ( circlesOverlap( candidate, placed[ i ], gap ) ) {
				return false;
			}
		}

		if ( enforceExclusion && exclusionZones && exclusionZones.length ) {
			for ( var j = 0; j < exclusionZones.length; j++ ) {
				if ( rectOverlapsCircle( exclusionZones[ j ], candidate.centerX, candidate.centerY, candidate.radius ) ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Picks this ring's color. Colors are chosen randomly, except: if
	 * this is the last ring of the layer, at least two rings exist, and
	 * every previously *placed* ring so far shares the same color, the
	 * opposite color is forced - guaranteeing both colors appear
	 * whenever two or more rings end up placed, without ever using
	 * unseeded randomness.
	 *
	 * @param {Function} rand         Seeded random function.
	 * @param {number}   index        This ring's slot index.
	 * @param {number}   totalCount   Total number of ring slots.
	 * @param {string[]} chosenSoFar  Colors of already-placed rings.
	 * @return {string} "green" or "orange".
	 */
	function chooseColor( rand, index, totalCount, chosenSoFar ) {
		var rolled = rand() < 0.5 ? 'green' : 'orange';
		var isLastSlot = index === totalCount - 1;

		if ( isLastSlot && totalCount > 1 && chosenSoFar.length > 0 ) {
			var allSame = chosenSoFar.every( function ( color ) {
				return color === chosenSoFar[ 0 ];
			} );
			if ( allSame ) {
				return chosenSoFar[ 0 ] === 'green' ? 'orange' : 'green';
			}
		}

		return rolled;
	}

	/**
	 * Picks this ring's opacity: the darker range if its center falls
	 * within the dark quote band, otherwise the lighter range for the
	 * cream/white sections.
	 *
	 * @param {Function}   rand         Seeded random function.
	 * @param {Object}     candidate    Accepted candidate circle.
	 * @param {Object|null} darkZoneRect { top, bottom } of the dark band, or null.
	 * @return {number}
	 */
	function computeOpacity( rand, candidate, darkZoneRect ) {
		var isDark = !! darkZoneRect && candidate.centerY >= darkZoneRect.top && candidate.centerY <= darkZoneRect.bottom;
		var range = isDark ? PAGE_OPACITY_DARK_RANGE : PAGE_OPACITY_LIGHT_RANGE;
		return lerp( range[ 0 ], range[ 1 ], rand() );
	}

	/**
	 * Edge-to-edge distance from a circle to the nearest circle in a
	 * list (negative would mean overlap, though canPlaceCandidate never
	 * lets that happen). Debug-only.
	 *
	 * @param {Object}   circle Circle to measure from.
	 * @param {Object[]} others Other circles.
	 * @return {number|null} Distance in px, or null if `others` is empty.
	 */
	function nearestNeighborDistance( circle, others ) {
		var min = null;
		for ( var i = 0; i < others.length; i++ ) {
			var dx = circle.centerX - others[ i ].centerX;
			var dy = circle.centerY - others[ i ].centerY;
			var d = Math.hypot( dx, dy ) - circle.radius - others[ i ].radius;
			if ( min === null || d < min ) {
				min = d;
			}
		}
		return min;
	}

	/**
	 * Attempts to place a single ring within its zone via rejection
	 * sampling: generates a random candidate, checks it, and retries on
	 * collision. Runs through PAGE_PLACEMENT_PASSES (shrinking the size
	 * and dropping the exclusion-zone check on later passes) rather than
	 * looping forever; if no pass succeeds, the ring is skipped.
	 *
	 * @param {Function} rand            Seeded random function.
	 * @param {Object}   config          { sizeRange, gap }.
	 * @param {number}   containerWidth  Container width in px.
	 * @param {number}   containerHeight Container height in px.
	 * @param {Object}   zone            { top, height } for this ring.
	 * @param {Object[]} placed          Already-accepted circles.
	 * @param {Object[]} exclusionZones  Soft exclusion rects.
	 * @param {Object|null} darkZoneRect Dark band rect, or null.
	 * @param {string}   color           This ring's chosen color.
	 * @return {Object|null} { circle, color, opacity, attempts } or null.
	 */
	function placeRing( rand, config, containerWidth, containerHeight, zone, placed, exclusionZones, darkZoneRect, color ) {
		var totalAttempts = 0;

		for ( var p = 0; p < PAGE_PLACEMENT_PASSES.length; p++ ) {
			var pass = PAGE_PLACEMENT_PASSES[ p ];

			for ( var a = 0; a < pass.attempts; a++ ) {
				totalAttempts++;

				var candidate = generateCandidate( rand, config, containerWidth, zone, pass.sizeMultiplier );

				if ( canPlaceCandidate( candidate, placed, config.gap, exclusionZones, pass.enforceExclusion, containerHeight ) ) {
					return {
						circle: candidate,
						color: color,
						opacity: computeOpacity( rand, candidate, darkZoneRect ),
						attempts: totalAttempts,
					};
				}
			}
		}

		return null;
	}

	/**
	 * Builds a full, collision-free ring layout for one container: splits
	 * the height into deterministically-shuffled vertical zones (so rings
	 * spread across the full height instead of clustering by chance), then
	 * places one ring per zone via rejection sampling. Rings that can't be
	 * placed after all passes are skipped - the actual placed count may
	 * be lower than targetCount, by design.
	 *
	 * @param {Function} rand            Seeded random function.
	 * @param {Object}   config          { sizeRange, gap }.
	 * @param {number}   containerWidth  Container width in px.
	 * @param {number}   containerHeight Container height in px.
	 * @param {number}   targetCount     Desired ring count.
	 * @param {Object|null} darkZoneRect Dark band rect, or null.
	 * @param {Object[]} exclusionZones  Soft exclusion rects.
	 * @return {Object} { rings, targetCount, debugEntries }.
	 */
	function createNonOverlappingLayout( rand, config, containerWidth, containerHeight, targetCount, darkZoneRect, exclusionZones ) {
		var zones = shuffleDeterministic( buildVerticalZones( containerHeight, targetCount ), rand );
		var placed = [];
		var chosenColors = [];
		var debugEntries = [];

		for ( var i = 0; i < targetCount; i++ ) {
			var color = chooseColor( rand, i, targetCount, chosenColors );
			var placement = placeRing( rand, config, containerWidth, containerHeight, zones[ i ], placed, exclusionZones, darkZoneRect, color );

			if ( placement ) {
				var nearest = nearestNeighborDistance( placement.circle, placed );

				placement.circle.color = placement.color;
				placement.circle.opacity = placement.opacity;
				placed.push( placement.circle );
				chosenColors.push( placement.color );

				debugEntries.push( {
					index: i,
					placed: true,
					attempts: placement.attempts,
					size: placement.circle.size,
					color: placement.color,
					opacity: placement.opacity.toFixed( 3 ),
					left: Math.round( placement.circle.left ),
					top: Math.round( placement.circle.top ),
					nearestGap: null === nearest ? null : Math.round( nearest ),
				} );
			} else {
				debugEntries.push( { index: i, placed: false } );
			}
		}

		return { rings: placed, targetCount: targetCount, debugEntries: debugEntries };
	}

	/**
	 * Renders the accepted ring layout into a fresh decoration layer and
	 * inserts it as the container's first child. In the Elementor editor
	 * preview, opacity is dampened so the decoration reads as clearly
	 * secondary while editing.
	 *
	 * @param {Element} el           The ".avf-home-main" container.
	 * @param {Object}  layoutResult Result of createNonOverlappingLayout().
	 * @return {Element} The inserted layer element.
	 */
	function renderRings( el, layoutResult ) {
		var layer = document.createElement( 'div' );
		layer.className = PAGE_LAYER_CLASS;
		layer.setAttribute( 'aria-hidden', 'true' );

		var isEditorPreview = document.body.classList.contains( 'elementor-editor-active' );

		for ( var i = 0; i < layoutResult.rings.length; i++ ) {
			var circle = layoutResult.rings[ i ];
			var ring = document.createElement( 'span' );
			ring.className = 'avf-zirkel avf-zirkel--' + circle.color;

			var opacity = isEditorPreview ? circle.opacity * 0.6 : circle.opacity;

			applyRingStyle( ring, {
				size: circle.size,
				left: Math.round( circle.left ) + 'px',
				top: Math.round( circle.top ) + 'px',
				rotate: circle.rotate,
				opacity: opacity.toFixed( 3 ),
			} );

			layer.appendChild( ring );
		}

		el.insertBefore( layer, el.firstChild );
		return layer;
	}

	/**
	 * Returns the index of a page-wide container (".avf-home-main" or
	 * ".avf-has-zirkel-bg") among all such elements currently in the
	 * document, in DOM order - part of this mode's seed, per spec,
	 * alongside the path, the optional manual seed and the responsive
	 * bucket.
	 *
	 * @param {Element} el The page-wide container.
	 * @return {number}
	 */
	function getPageContainerIndex( el ) {
		var all = document.querySelectorAll( PAGE_SELECTOR );
		for ( var i = 0; i < all.length; i++ ) {
			if ( all[ i ] === el ) {
				return i;
			}
		}
		return 0;
	}

	/**
	 * Debug output, active only when `window.AVF_ZIRKEL_DEBUG === true`.
	 * No console output otherwise, ever.
	 *
	 * @param {Element} el           The ".avf-home-main" container.
	 * @param {Object}  layoutResult Result of createNonOverlappingLayout().
	 * @param {Object}  config       { sizeRange, gap }.
	 * @param {string}  breakpoint   Current responsive bucket.
	 * @return {void}
	 */
	function debugLog( el, layoutResult, config, breakpoint ) {
		if ( true !== window.AVF_ZIRKEL_DEBUG || 'undefined' === typeof console ) {
			return;
		}

		var containerLabel = '.' + PAGE_CLASSES[ 0 ];
		for ( var c = 0; c < PAGE_CLASSES.length; c++ ) {
			if ( el.classList.contains( PAGE_CLASSES[ c ] ) ) {
				containerLabel = '.' + PAGE_CLASSES[ c ];
				break;
			}
		}
		var label = '[avf-zirkel-decor] ' + containerLabel + ' (' + breakpoint + ')';
		var canGroup = typeof console.groupCollapsed === 'function';

		if ( canGroup ) {
			console.groupCollapsed( label );
		} else {
			console.log( label );
		}

		console.log( 'gewuenschte Zirkel:', layoutResult.targetCount );
		console.log( 'tatsaechlich platziert:', layoutResult.rings.length );
		console.log( 'Sicherheitsabstand (gap):', config.gap + 'px' );

		if ( typeof console.table === 'function' ) {
			console.table( layoutResult.debugEntries );
		} else {
			for ( var i = 0; i < layoutResult.debugEntries.length; i++ ) {
				console.log( layoutResult.debugEntries[ i ] );
			}
		}

		if ( canGroup ) {
			console.groupEnd();
		}
	}

	/**
	 * Builds the single page-wide decoration layer for ".avf-home-main":
	 * resolves the responsive config, builds the seed (path + manual seed
	 * + container index + responsive bucket), computes a collision-free
	 * layout and renders it.
	 *
	 * @param {Element} el The ".avf-home-main" element, not yet processed.
	 * @return {void}
	 */
	function buildPageWideLayer( el ) {
		var breakpoint = getBreakpoint();
		var height = el.scrollHeight || el.offsetHeight || 0;

		if ( height <= 0 ) {
			// Nothing measurable yet (e.g. hidden ancestor); mark ready to
			// avoid retrying forever, but skip building an empty layer.
			el.setAttribute( PAGE_READY_ATTR, 'true' );
			return;
		}

		var width = el.clientWidth || el.offsetWidth || 0;
		var containerIndex = getPageContainerIndex( el );
		var seedAttr = el.getAttribute( SEED_ATTR ) || '';
		var seedKey = window.location.pathname + '|' + seedAttr + '|' + containerIndex + '|' + breakpoint;
		var rand = createSeededRandom( seedKey );

		var responsive = getResponsiveConfig( breakpoint );
		var rawCount = Math.round( height / PAGE_RING_HEIGHT_PX );
		var targetCount = clampPageRingCount( rawCount, breakpoint );

		var gap = Math.round( lerp( responsive.gapRange[ 0 ], responsive.gapRange[ 1 ], rand() ) );
		var config = { sizeRange: responsive.sizeRange, gap: gap };

		var darkZoneRect = findDarkZoneRect( el );
		var exclusionZones = findExclusionZones( el );

		var layoutResult = createNonOverlappingLayout( rand, config, width, height, targetCount, darkZoneRect, exclusionZones );

		renderRings( el, layoutResult );

		if ( pageLayoutState ) {
			pageLayoutState.set( el, { breakpoint: breakpoint, height: height } );
		}

		el.setAttribute( PAGE_READY_ATTR, 'true' );

		debugLog( el, layoutResult, config, breakpoint );
	}

	/**
	 * Processes a node for the page-wide decoration mode: builds the layer
	 * for any page-wide container (".avf-home-main" or ".avf-has-zirkel-bg",
	 * itself or within its subtree) if not already done, and guards against
	 * duplicate layers.
	 *
	 * @param {Node} node Root node to inspect (itself and its subtree).
	 * @return {void}
	 */
	function processPageWideNode( node ) {
		if ( ! node || node.nodeType !== 1 ) {
			return;
		}

		var candidates = [];

		if ( typeof node.matches === 'function' && node.matches( PAGE_SELECTOR ) ) {
			candidates.push( node );
		}

		if ( typeof node.querySelectorAll === 'function' ) {
			var found = node.querySelectorAll( PAGE_SELECTOR );
			for ( var i = 0; i < found.length; i++ ) {
				candidates.push( found[ i ] );
			}
		}

		for ( var j = 0; j < candidates.length; j++ ) {
			var el = candidates[ j ];

			if ( el.getAttribute( PAGE_READY_ATTR ) === 'true' ) {
				continue;
			}

			if ( el.querySelector( ':scope > .' + PAGE_LAYER_CLASS ) ) {
				el.setAttribute( PAGE_READY_ATTR, 'true' );
				continue;
			}

			buildPageWideLayer( el );
		}
	}

	/**
	 * Rebuilds already-ready page-wide layers whose responsive bucket or
	 * container height changed meaningfully since their last build.
	 * Skipped entirely when neither changed, so a resize that doesn't
	 * cross a breakpoint or meaningfully change height is a no-op here.
	 * Duplicate layers are avoided by removing the old layer and clearing
	 * the ready flag before rebuilding through the normal codepath.
	 *
	 * @param {string} breakpoint Current responsive bucket.
	 * @return {void}
	 */
	function refreshPageWideLayouts( breakpoint ) {
		var containers = document.querySelectorAll( buildPageSelector( '[' + PAGE_READY_ATTR + '="true"]' ) );

		for ( var i = 0; i < containers.length; i++ ) {
			var el = containers[ i ];
			var state = pageLayoutState ? pageLayoutState.get( el ) : null;
			var newHeight = el.scrollHeight || el.offsetHeight || 0;

			var breakpointChanged = ! state || state.breakpoint !== breakpoint;
			var heightChanged = !! state && Math.abs( newHeight - state.height ) > Math.max( 60, state.height * 0.08 );

			if ( ! breakpointChanged && ! heightChanged ) {
				continue;
			}

			var oldLayer = el.querySelector( ':scope > .' + PAGE_LAYER_CLASS );
			if ( oldLayer ) {
				oldLayer.remove();
			}
			el.removeAttribute( PAGE_READY_ATTR );

			buildPageWideLayer( el );
		}
	}

	/* -----------------------------------------------------------
	 * Page-head decoration mode (".avf-page-head").
	 *
	 * A third, independent, deliberately non-random mode: the global
	 * page-header template ("AVF - Seitenvorlage") wraps its title/intro
	 * block in an element carrying this class. Unlike the ".avf-decor"
	 * and page-wide modes above, this is a fixed, art-directed two-ring
	 * composition (one large ring bleeding off the right edge, one small
	 * accent ring top-left) - not seeded/randomised, since a hero banner
	 * benefits from a deliberate composition rather than per-page
	 * variation. Position/size come entirely from CSS
	 * (".avf-page-head__ring--large/--small" in zirkel-decor.css); this
	 * pipeline only handles idempotent injection, exactly like the other
	 * two modes.
	 * ----------------------------------------------------------- */

	var PAGE_HEAD_CLASS = 'avf-page-head';
	var PAGE_HEAD_READY_ATTR = 'data-avf-page-head-ready';
	var PAGE_HEAD_RING_CLASS = 'avf-page-head__ring';

	/**
	 * Creates and inserts the two decorative rings as the first children
	 * of a ".avf-page-head" element.
	 *
	 * @param {Element} el ".avf-page-head" element, not yet processed.
	 * @return {void}
	 */
	function buildPageHeadRings( el ) {
		var small = document.createElement( 'span' );
		small.className = 'avf-zirkel avf-zirkel--orange ' + PAGE_HEAD_RING_CLASS + ' ' + PAGE_HEAD_RING_CLASS + '--small';
		small.setAttribute( 'aria-hidden', 'true' );

		var large = document.createElement( 'span' );
		large.className = 'avf-zirkel avf-zirkel--green ' + PAGE_HEAD_RING_CLASS + ' ' + PAGE_HEAD_RING_CLASS + '--large';
		large.setAttribute( 'aria-hidden', 'true' );

		el.insertBefore( small, el.firstChild );
		el.insertBefore( large, el.firstChild );
		el.setAttribute( PAGE_HEAD_READY_ATTR, 'true' );
	}

	/**
	 * Processes a node for the page-head decoration mode: builds the
	 * rings for ".avf-page-head" (itself or within its subtree) if not
	 * already done, and guards against duplicate rings.
	 *
	 * @param {Node} node Root node to inspect (itself and its subtree).
	 * @return {void}
	 */
	function processPageHeadNode( node ) {
		if ( ! node || node.nodeType !== 1 ) {
			return;
		}

		var candidates = [];

		if ( typeof node.matches === 'function' && node.matches( '.' + PAGE_HEAD_CLASS ) ) {
			candidates.push( node );
		}

		if ( typeof node.querySelectorAll === 'function' ) {
			var found = node.querySelectorAll( '.' + PAGE_HEAD_CLASS );
			for ( var i = 0; i < found.length; i++ ) {
				candidates.push( found[ i ] );
			}
		}

		for ( var j = 0; j < candidates.length; j++ ) {
			var el = candidates[ j ];

			if ( el.getAttribute( PAGE_HEAD_READY_ATTR ) === 'true' ) {
				continue;
			}

			if ( el.querySelector( ':scope > .' + PAGE_HEAD_RING_CLASS ) ) {
				el.setAttribute( PAGE_HEAD_READY_ATTR, 'true' );
				continue;
			}

			buildPageHeadRings( el );
		}
	}

	/* -----------------------------------------------------------
	 * Init, observer and resize wiring (shared by all three pipelines).
	 * ----------------------------------------------------------- */

	var currentBreakpoint = getBreakpoint();
	var resizeScheduled = false;

	function handleResize() {
		if ( resizeScheduled ) {
			return;
		}
		resizeScheduled = true;

		window.requestAnimationFrame( function () {
			resizeScheduled = false;
			var breakpoint = getBreakpoint();

			// Page-wide layouts decide for themselves (per container)
			// whether a rebuild is actually warranted.
			refreshPageWideLayouts( breakpoint );

			if ( breakpoint === currentBreakpoint ) {
				return;
			}
			currentBreakpoint = breakpoint;
			refreshRingSizes( breakpoint );
		} );
	}

	function init() {
		if ( ! document.body ) {
			return;
		}

		processNode( document.body );
		processPageWideNode( document.body );
		processPageHeadNode( document.body );

		if ( 'MutationObserver' in window ) {
			var observer = new MutationObserver( function ( mutations ) {
				for ( var i = 0; i < mutations.length; i++ ) {
					var mutation = mutations[ i ];
					if ( mutation.type !== 'childList' || mutation.addedNodes.length === 0 ) {
						continue;
					}
					for ( var j = 0; j < mutation.addedNodes.length; j++ ) {
						processNode( mutation.addedNodes[ j ] );
						processPageWideNode( mutation.addedNodes[ j ] );
						processPageHeadNode( mutation.addedNodes[ j ] );
					}
				}
			} );

			observer.observe( document.body, { childList: true, subtree: true } );
		}

		window.addEventListener( 'resize', handleResize, { passive: true } );
	}

	init();
} )();
