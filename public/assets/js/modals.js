(function () {
  'use strict';

  // Generic popup controller: wires up any [data-modal-open="<id>"] /
  // [data-modal-close="<id>"] pair plus the .modal-overlay it targets —
  // hidden/flex class toggling (not the native <dialog> element), a
  // focus trap, Escape-to-close, and backdrop-click-to-close. First used
  // for the dashboard's single "Customize dashboard" modal, generalized
  // here so any page can drop in as many of these as it needs (Settings
  // → Categories has one per category; Tags has one per tag) without
  // each page re-implementing the same open/close/focus-trap logic.
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
