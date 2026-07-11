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
})();
