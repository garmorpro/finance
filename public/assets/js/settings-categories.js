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

  // Popups: every "Manage" trigger and "New category" open a
  // [data-modal-open]-addressed overlay, same hidden/flex toggling and
  // focus-trap pattern as the dashboard's "Customize dashboard" modal
  // (dashboard.js), generalized here since this page can have dozens of
  // these (one per category) rather than just one.
  var openModalEl = null;
  var lastFocused = null;

  function focusableElements(modal) {
    return Array.prototype.slice.call(
      modal.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])')
    );
  }

  function openModal(modal) {
    if (openModalEl && openModalEl !== modal) {
      closeModal(openModalEl);
    }
    lastFocused = document.activeElement;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    openModalEl = modal;

    var focusable = focusableElements(modal);
    if (focusable.length > 0) {
      focusable[0].focus();
    }

    document.addEventListener('keydown', onModalKeydown);
  }

  function closeModal(modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (openModalEl === modal) {
      openModalEl = null;
      document.removeEventListener('keydown', onModalKeydown);
      if (lastFocused) {
        lastFocused.focus();
      }
      lastFocused = null;
    }
  }

  function onModalKeydown(event) {
    if (!openModalEl) {
      return;
    }

    if (event.key === 'Escape') {
      closeModal(openModalEl);
      return;
    }

    if (event.key !== 'Tab') {
      return;
    }

    var focusable = focusableElements(openModalEl);
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

  document.querySelectorAll('[data-modal-open]').forEach(function (btn) {
    var modal = document.getElementById(btn.getAttribute('data-modal-open'));
    if (!modal) {
      return;
    }
    btn.addEventListener('click', function () {
      openModal(modal);
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(function (btn) {
    var modal = document.getElementById(btn.getAttribute('data-modal-close'));
    if (!modal) {
      return;
    }
    btn.addEventListener('click', function () {
      closeModal(modal);
    });
  });

  document.querySelectorAll('.modal-overlay').forEach(function (modal) {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        closeModal(modal);
      }
    });
  });
})();
