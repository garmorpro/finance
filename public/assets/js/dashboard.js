(function () {
  'use strict';

  var grid = document.getElementById('dashboard-grid');
  if (!grid) {
    return;
  }

  var csrfToken = grid.dataset.csrfToken;
  var dragging = null;

  // --- Masonry layout ---
  // Tiles flow into the shortest column, computed here rather than via
  // CSS multi-column — `columns` + `break-inside: avoid` + `column-span:
  // all` + a box-shadowed child proved unreliable across browsers (it
  // produced two different rendering artifacts at the column-fragment
  // boundary next to a wide tile). Absolute positioning sidesteps that
  // class of bug entirely: nothing ever gets fragmented across a column
  // boundary because there's no column boundary as far as the browser's
  // layout engine is concerned.
  var GAP = 16; // px, matches Tailwind's gap-4 / 1rem
  var MIN_TWO_COL_WIDTH = 560; // px; below this a 2-column split leaves each tile too cramped

  function layoutMasonry() {
    var containerWidth = grid.clientWidth;
    if (containerWidth === 0) {
      return;
    }

    // Based on the grid's own width, not the viewport — the grid can be
    // narrower than the viewport (e.g. sharing the row with the sticky
    // "Lifecycle of a Dollar" sidebar), and a viewport-only check would
    // still try to split it into two columns even when there isn't
    // really room for that.
    var columns = containerWidth >= MIN_TWO_COL_WIDTH ? 2 : 1;
    var colWidth = columns === 1 ? containerWidth : (containerWidth - GAP) / 2;
    var colHeights = new Array(columns).fill(0);

    var tiles = Array.prototype.filter.call(grid.querySelectorAll('[data-widget]'), function (tile) {
      return !tile.classList.contains('hidden');
    });

    tiles.forEach(function (tile) {
      var isWide = columns > 1 && tile.classList.contains('tile-wide');
      var width = isWide ? containerWidth : colWidth;
      tile.style.position = 'absolute';
      tile.style.width = width + 'px';

      if (isWide) {
        var top = Math.max.apply(null, colHeights);
        tile.style.left = '0px';
        tile.style.top = top + 'px';
        var newHeight = top + tile.offsetHeight + GAP;
        colHeights = colHeights.map(function () {
          return newHeight;
        });
      } else {
        var shortest = 0;
        for (var i = 1; i < colHeights.length; i++) {
          if (colHeights[i] < colHeights[shortest]) {
            shortest = i;
          }
        }
        tile.style.left = (shortest * (colWidth + GAP)) + 'px';
        tile.style.top = colHeights[shortest] + 'px';
        colHeights[shortest] += tile.offsetHeight + GAP;
      }
    });

    var maxHeight = 0;
    for (var j = 0; j < colHeights.length; j++) {
      maxHeight = Math.max(maxHeight, colHeights[j]);
    }
    grid.style.height = Math.max(0, maxHeight - GAP) + 'px';
  }

  var layoutScheduled = false;
  function scheduleLayout() {
    if (layoutScheduled) {
      return;
    }
    layoutScheduled = true;
    requestAnimationFrame(function () {
      layoutScheduled = false;
      layoutMasonry();
    });
  }

  layoutMasonry();
  window.addEventListener('load', scheduleLayout);
  window.addEventListener('resize', scheduleLayout);

  // Chart tiles render asynchronously (charts.js runs after this file) —
  // a ResizeObserver on every tile catches that late height change (and
  // any other content that changes height after initial layout) without
  // guessing at a fixed delay. Chart canvases sit in a fixed-height
  // wrapper, so this doesn't loop: width changes from layoutMasonry()
  // don't feed back into another height change.
  if (window.ResizeObserver) {
    var observer = new ResizeObserver(scheduleLayout);
    Array.prototype.forEach.call(grid.querySelectorAll('.tile-card'), function (card) {
      observer.observe(card);
    });
  }

  // --- Drag to reorder ---
  function currentOrder() {
    return Array.prototype.map.call(grid.querySelectorAll('[data-widget]'), function (el) {
      return el.dataset.widget;
    });
  }

  function saveOrder() {
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('order', JSON.stringify(currentOrder()));

    fetch('/dashboard/layout', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).catch(function () {
      // Silent failure is acceptable here: worst case, the next page
      // load falls back to the last successfully saved order.
    });
  }

  grid.addEventListener('dragstart', function (event) {
    var tile = event.target.closest('[data-widget]');
    if (!tile) {
      return;
    }
    dragging = tile;
    tile.classList.add('opacity-50');
    event.dataTransfer.effectAllowed = 'move';
  });

  grid.addEventListener('dragend', function () {
    if (dragging) {
      dragging.classList.remove('opacity-50');
    }
    dragging = null;
    saveOrder();
    layoutMasonry();
  });

  grid.addEventListener('dragover', function (event) {
    event.preventDefault();

    var target = event.target.closest('[data-widget]');
    if (!target || target === dragging || !dragging) {
      return;
    }

    var rect = target.getBoundingClientRect();
    var before = (event.clientY - rect.top) < rect.height / 2;

    grid.insertBefore(dragging, before ? target : target.nextSibling);
  });

  // --- Customize modal: show/hide tiles ---
  var modal = document.getElementById('customize-modal');
  var openBtn = document.getElementById('customize-open');
  var closeBtn = document.getElementById('customize-close');
  var list = document.getElementById('customize-list');

  if (modal && openBtn && closeBtn && list) {
    var lastFocused = null;

    function focusableElements() {
      return Array.prototype.slice.call(
        modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])')
      );
    }

    var openModal = function () {
      lastFocused = document.activeElement;
      modal.classList.remove('hidden');
      modal.classList.add('flex');
      closeBtn.focus();
      document.addEventListener('keydown', onModalKeydown);
    };

    var closeModal = function () {
      modal.classList.add('hidden');
      modal.classList.remove('flex');
      document.removeEventListener('keydown', onModalKeydown);
      if (lastFocused) {
        lastFocused.focus();
      }
    };

    // Escape closes the modal; Tab/Shift+Tab wrap within it instead of
    // escaping into the page behind it, since this is a modal dialog —
    // a sighted keyboard user tabbing past the last control shouldn't
    // land back on the dashboard grid underneath.
    function onModalKeydown(event) {
      if (event.key === 'Escape') {
        closeModal();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      var focusable = focusableElements();
      if (focusable.length === 0) {
        return;
      }

      var first = focusable[0];
      var last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal();
      }
    });

    var saveVisibility = function () {
      var hidden = Array.prototype.filter
        .call(list.querySelectorAll('[data-widget-toggle]'), function (input) {
          return !input.checked;
        })
        .map(function (input) {
          return input.dataset.widgetToggle;
        });

      var body = new URLSearchParams();
      body.set('csrf_token', list.dataset.csrfToken);
      body.set('hidden', JSON.stringify(hidden));

      fetch('/dashboard/widgets', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      }).catch(function () {
        // Silent failure: worst case, the next page load falls back to
        // the last successfully saved visibility state.
      });
    };

    list.addEventListener('change', function (event) {
      var toggle = event.target.closest('[data-widget-toggle]');
      if (!toggle) {
        return;
      }

      var tile = grid.querySelector('[data-widget="' + toggle.dataset.widgetToggle + '"]');
      if (tile) {
        tile.classList.toggle('hidden', !toggle.checked);
      }

      saveVisibility();
      layoutMasonry();
    });
  }

  // --- Per-tile resize toggle (full width vs. column width) ---
  grid.addEventListener('click', function (event) {
    var btn = event.target.closest('[data-widget-resize]');
    if (!btn) {
      return;
    }

    var tile = btn.closest('[data-widget]');
    if (!tile) {
      return;
    }

    var nowWide = !tile.classList.contains('tile-wide');
    tile.classList.toggle('tile-wide', nowWide);
    btn.dataset.wide = nowWide ? '1' : '0';
    btn.title = nowWide ? 'Shrink to column width' : 'Expand to full width';
    layoutMasonry();

    var wide = Array.prototype.map
      .call(grid.querySelectorAll('[data-widget-resize][data-wide="1"]'), function (el) {
        return el.closest('[data-widget]').dataset.widget;
      });

    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('wide', JSON.stringify(wide));

    fetch('/dashboard/width', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).catch(function () {
      // Silent failure is acceptable here too: worst case, the next
      // page load falls back to the last successfully saved width state.
    });
  });
})();
