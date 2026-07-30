(function () {
  'use strict';

  // Drag-to-reorder within one group's category list — same interaction
  // and persistence pattern as the dashboard's tile reordering
  // (dashboard.js's setupDragReorder): each .category-group-list is its
  // own independent drag scope, so a category only ever reorders among
  // its own group's siblings, never across groups (moving to a different
  // group is what the "More" -> Section control is for).
  var csrfInput = document.querySelector('input[name="csrf_token"]');
  var csrfToken = csrfInput ? csrfInput.value : '';

  document.querySelectorAll('.category-group-list').forEach(function (container) {
    var dragging = null;

    function currentOrder() {
      return Array.prototype.map.call(container.querySelectorAll('[data-category-id]'), function (el) {
        return parseInt(el.dataset.categoryId, 10);
      });
    }

    function saveOrder() {
      var body = new URLSearchParams();
      body.set('csrf_token', csrfToken);
      body.set('order', JSON.stringify(currentOrder()));

      fetch('/settings/categories/reorder', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      }).catch(function () {
        // Silent failure is acceptable here: worst case, the next page
        // load falls back to the last successfully saved order.
      });
    }

    container.addEventListener('dragstart', function (event) {
      var row = event.target.closest('[data-category-id]');
      if (!row || row.parentElement !== container) {
        return;
      }
      dragging = row;
      row.classList.add('opacity-50');
      event.dataTransfer.effectAllowed = 'move';
    });

    container.addEventListener('dragend', function () {
      if (dragging) {
        dragging.classList.remove('opacity-50');
      }
      dragging = null;
      saveOrder();
    });

    container.addEventListener('dragover', function (event) {
      if (!dragging) {
        return;
      }
      event.preventDefault();

      var target = event.target.closest('[data-category-id]');
      if (!target || target === dragging || target.parentElement !== container) {
        return;
      }

      var rect = target.getBoundingClientRect();
      var before = (event.clientY - rect.top) < rect.height / 2;

      container.insertBefore(dragging, before ? target : target.nextSibling);
    });
  });

  // "More" panels use <details> for their disclosure (no JS required to
  // show/hide them) — the only script-driven behavior on this page is
  // reordering above.
})();
