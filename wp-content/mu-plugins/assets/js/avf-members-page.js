(() => {
  const GRID_SELECTOR = '.avf-members-grid';
  const CARD_SELECTOR = '[data-avf-member-card]';
  const TOGGLE_SELECTOR = '[data-avf-member-toggle]';
  const PANEL_SELECTOR = '[data-avf-member-panel]';

  const syncPhotoFallbacks = (root = document) => {
    root.querySelectorAll('[data-avf-member-photo]').forEach((image) => {
      if (image.dataset.avfPhotoBound === '1') {
        return;
      }

      image.dataset.avfPhotoBound = '1';
      image.addEventListener(
        'error',
        () => {
          const initials = (image.dataset.avfMemberInitials || '?').trim() || '?';
          const media = image.closest('.avf-member-card__media');
          if (!media) {
            return;
          }

          const placeholder = document.createElement('span');
          placeholder.className = 'avf-member-card__placeholder';
          placeholder.setAttribute('aria-hidden', 'true');
          placeholder.textContent = initials;
          media.replaceChildren(placeholder);
        },
        { once: true }
      );
    });
  };

  const syncGridNameLayout = (root = document) => {
    root.querySelectorAll(GRID_SELECTOR).forEach((grid) => {
      const names = Array.from(grid.querySelectorAll('[data-avf-member-display-name]'));
      grid.classList.remove('avf-members-grid--stacked-names');

      const needsStackedLayout = names.some((name) => name.scrollWidth > name.clientWidth + 1);
      grid.classList.toggle('avf-members-grid--stacked-names', needsStackedLayout);
    });
  };

  const syncCardState = (card, isExpanded) => {
    const toggle = card.querySelector(TOGGLE_SELECTOR);
    const panel = card.querySelector(PANEL_SELECTOR);
    if (!toggle || !panel) {
      return;
    }

    card.classList.toggle('is-expanded', isExpanded);
    toggle.setAttribute('aria-expanded', isExpanded ? 'true' : 'false');
    toggle.setAttribute(
      'aria-label',
      isExpanded ? toggle.dataset.avfLabelCollapse || '' : toggle.dataset.avfLabelExpand || ''
    );
    panel.hidden = false;
    panel.setAttribute('aria-hidden', isExpanded ? 'false' : 'true');
  };

  const closeSiblingCards = (grid, activeCard) => {
    grid.querySelectorAll(CARD_SELECTOR).forEach((card) => {
      if (card !== activeCard) {
        syncCardState(card, false);
      }
    });
  };

  const initGrid = (grid) => {
    grid.dataset.avfAccordionReady = '1';

    grid.querySelectorAll(CARD_SELECTOR).forEach((card) => {
      const toggle = card.querySelector(TOGGLE_SELECTOR);
      const panel = card.querySelector(PANEL_SELECTOR);
      if (!toggle) {
        return;
      }

      if (panel) {
        panel.hidden = false;
      }

      if (toggle.dataset.avfToggleBound !== '1') {
        toggle.dataset.avfToggleBound = '1';
        toggle.addEventListener('click', () => {
          const nextState = !card.classList.contains('is-expanded');
          if (nextState) {
            closeSiblingCards(grid, card);
          }
          syncCardState(card, nextState);
        });
      }

      syncCardState(card, card.classList.contains('is-expanded'));
    });
  };

  let frameId = 0;
  const scheduleNameLayoutSync = () => {
    if (frameId) {
      cancelAnimationFrame(frameId);
    }

    frameId = requestAnimationFrame(() => {
      frameId = 0;
      syncGridNameLayout();
    });
  };

  const initMemberCards = (root = document) => {
    syncPhotoFallbacks(root);
    root.querySelectorAll(GRID_SELECTOR).forEach((grid) => initGrid(grid));
    scheduleNameLayoutSync();
  };

  document.addEventListener('DOMContentLoaded', () => {
    initMemberCards();
    window.addEventListener('load', scheduleNameLayoutSync, { once: true });
    window.addEventListener('resize', scheduleNameLayoutSync, { passive: true });

    if ('ResizeObserver' in window) {
      const resizeObserver = new ResizeObserver(() => {
        scheduleNameLayoutSync();
      });

      document.querySelectorAll(GRID_SELECTOR).forEach((grid) => {
        resizeObserver.observe(grid);
      });
    }

    if ('MutationObserver' in window) {
      const mutationObserver = new MutationObserver((mutations) => {
        let shouldInit = false;

        mutations.forEach((mutation) => {
          mutation.addedNodes.forEach((node) => {
            if (!(node instanceof HTMLElement)) {
              return;
            }

            if (node.matches?.(GRID_SELECTOR) || node.querySelector?.(GRID_SELECTOR)) {
              shouldInit = true;
            }
          });
        });

        if (shouldInit) {
          initMemberCards();
        }
      });

      mutationObserver.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  });
})();
