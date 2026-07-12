(function () {
  'use strict';

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
