# About History Timeline Refactor

This file keeps the semantic target state for the `/ueber-uns/` history section
separate from the CSS-only repair that ships in `assets/css/site-fixes.css`.

## Variant B Markup

```html
<section class="avf-history" aria-labelledby="avf-history-title">
	<h2 id="avf-history-title" class="avf-history__heading">Geschichte</h2>
	<ol class="avf-timeline" role="list">
		<li class="avf-timeline__item">
			<time class="avf-timeline__year" datetime="1938">1938</time>
			<p class="avf-timeline__text">
				Der Wunsch nach einer Reformverbindung ...
			</p>
		</li>
		<li class="avf-timeline__item">
			<time class="avf-timeline__year" datetime="1939">1939</time>
			<p class="avf-timeline__text">
				...
			</p>
		</li>
	</ol>
</section>
```

## Variant B CSS

```css
.avf-history {
	--avf-history-line-width: 3px;
	--avf-history-dot-size: 14px;
	--avf-history-dot-ring: 6px;
	--avf-history-year-inline-size: 4ch;
	--avf-history-column-gap: clamp(16px, 3vw, 32px);
	--avf-history-row-gap: clamp(28px, 4vw, 48px);
	--avf-history-line-fade: 96px;
}

.avf-history .avf-timeline {
	position: relative;
	display: grid;
	grid-template-columns: max-content var(--avf-history-line-width) minmax(0, 1fr);
	column-gap: var(--avf-history-column-gap);
	row-gap: var(--avf-history-row-gap);
	margin: 0;
	padding: 0;
	list-style: none;
}

.avf-history .avf-timeline::before {
	content: "";
	position: absolute;
	inset-block: 0;
	left: calc(var(--avf-history-year-inline-size) + var(--avf-history-column-gap) + (var(--avf-history-line-width) / 2));
	width: var(--avf-history-line-width);
	transform: translateX(-50%);
	background: linear-gradient(
		to bottom,
		var(--avf-green) 0%,
		var(--avf-green) calc(100% - var(--avf-history-line-fade)),
		transparent 100%
	);
}

.avf-history .avf-timeline__item {
	display: contents;
}

.avf-history .avf-timeline__year {
	grid-column: 1;
	min-inline-size: var(--avf-history-year-inline-size);
	margin: 0;
	font-family: var(--avf-font-display, "Cormorant Garamond", serif);
	font-size: clamp(28px, 2vw + 18px, 48px);
	font-variant-numeric: tabular-nums;
	line-height: 1;
	color: var(--avf-green-dark);
	white-space: nowrap;
}

.avf-history .avf-timeline__year::after {
	content: "";
	display: block;
	inline-size: var(--avf-history-dot-size);
	block-size: var(--avf-history-dot-size);
	margin-block-start: 2px;
	margin-inline-start: calc(100% + var(--avf-history-column-gap) - ((var(--avf-history-dot-size) - var(--avf-history-line-width)) / 2));
	border: 3px solid var(--avf-orange);
	border-radius: 50%;
	background: var(--avf-green);
	box-shadow: 0 0 0 var(--avf-history-dot-ring) var(--avf-surface);
}

.avf-history .avf-timeline__text {
	grid-column: 3;
	min-width: 0;
	margin: 0;
	line-height: 1.55;
	color: var(--avf-text);
	overflow-wrap: anywhere;
	hyphens: auto;
}

@media (max-width: 767px) {
	.avf-history .avf-timeline {
		grid-template-columns: 14px minmax(0, 1fr);
		column-gap: 16px;
		row-gap: 32px;
	}

	.avf-history .avf-timeline::before {
		left: calc(var(--avf-history-dot-size) / 2);
		transform: none;
	}

	.avf-history .avf-timeline__year,
	.avf-history .avf-timeline__text {
		grid-column: 2;
	}

	.avf-history .avf-timeline__year::after {
		margin-inline-start: calc((0px - 100%) - 16px + ((var(--avf-history-dot-size) - var(--avf-history-line-width)) / 2));
	}
}
```

## Migration Notes

1. Replace the current nested Elementor container trio (year / rail / text) with
   one list item per milestone.
2. Convert year headings to `<time>` and keep only the section title as a
   heading.
3. Keep line and marker decorative via pseudo-elements so they stay out of the
   accessibility tree.
