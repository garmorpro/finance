(function () {
  'use strict';

  var forms = document.querySelectorAll('[data-autosave="budget-item"]');

  forms.forEach(function (form) {
    var input = form.querySelector('input[name="planned_amount"]');
    var status = form.querySelector('.budget-item-status');
    if (!input || !status) {
      return;
    }

    var lastSaved = input.value;

    var save = function () {
      if (input.value === lastSaved) {
        return;
      }
      lastSaved = input.value;

      status.textContent = 'Saving…';

      fetch(form.getAttribute('action'), {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: new FormData(form),
      })
        .then(function (response) {
          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          status.classList.remove('text-emerald-700', 'dark:text-emerald-400', 'text-red-600', 'dark:text-red-400');
          if (result.ok && result.data.success) {
            status.textContent = 'Saved';
            status.classList.add('text-emerald-700', 'dark:text-emerald-400');
          } else {
            status.textContent = result.data.message || 'Error saving';
            status.classList.add('text-red-600', 'dark:text-red-400');
          }
        })
        .catch(function () {
          status.classList.remove('text-emerald-700', 'dark:text-emerald-400');
          status.classList.add('text-red-600', 'dark:text-red-400');
          status.textContent = 'Error saving';
        })
        .finally(function () {
          window.setTimeout(function () {
            status.textContent = '';
          }, 2000);
        });
    };

    input.addEventListener('blur', save);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        input.blur();
      }
    });
  });
})();
