(function () {
  'use strict';

  // Clicking anywhere on a transaction row opens its edit page — the
  // explicit "Edit" link and checkbox remain the real, keyboard-accessible
  // controls; this just saves mouse users a trip to a small target. A
  // click that started on an interactive element (checkbox, link, button)
  // is left alone so it keeps its own behavior instead of also navigating.
  document.querySelectorAll('.row-clickable[data-href]').forEach(function (row) {
    row.addEventListener('click', function (event) {
      if (event.target.closest('a, button, input, label')) {
        return;
      }

      window.location.href = row.dataset.href;
    });
  });

  var form = document.getElementById('bulk-form');
  if (!form) {
    return;
  }

  var selectAll = document.getElementById('select-all-checkbox');
  var rowCheckboxes = form.querySelectorAll('.row-checkbox');
  var deleteBtn = document.getElementById('bulk-delete-btn');

  if (selectAll) {
    selectAll.addEventListener('change', function () {
      rowCheckboxes.forEach(function (checkbox) {
        checkbox.checked = selectAll.checked;
      });
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', function (event) {
      var checkedCount = form.querySelectorAll('.row-checkbox:checked').length;
      if (checkedCount === 0) {
        // Let the server-side "please select at least one" message
        // handle an empty selection rather than confirming nothing.
        return;
      }

      var message = 'Delete ' + checkedCount + ' selected transaction' + (checkedCount === 1 ? '' : 's') + '? This also reverses their effect on account balances.';
      if (!window.confirm(message)) {
        event.preventDefault();
      }
    });
  }
})();
