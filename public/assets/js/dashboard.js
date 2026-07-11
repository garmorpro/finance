(function () {
  'use strict';

  var grid = document.getElementById('dashboard-grid');
  if (!grid) {
    return;
  }

  var csrfToken = grid.dataset.csrfToken;
  var dragging = null;

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

  if (!modal || !openBtn || !closeBtn || !list) {
    return;
  }

  function openModal() {
    modal.classList.remove('hidden');
    modal.classList.add('flex');
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
  }

  openBtn.addEventListener('click', openModal);
  closeBtn.addEventListener('click', closeModal);
  modal.addEventListener('click', function (event) {
    if (event.target === modal) {
      closeModal();
    }
  });

  function saveVisibility() {
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
  }

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
  });

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

    var nowWide = !tile.classList.contains('lg:col-span-2');
    tile.classList.toggle('lg:col-span-2', nowWide);
    btn.dataset.wide = nowWide ? '1' : '0';
    btn.title = nowWide ? 'Shrink to column width' : 'Expand to full width';

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
