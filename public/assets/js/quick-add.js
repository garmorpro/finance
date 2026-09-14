(function () {
  'use strict';

  // Small color swatch next to Account/Category in the quick-add popup,
  // reflecting whichever option is actually selected (each <option>
  // carries its real color as data-color) — a plain <select> has no way
  // to show that itself, and a blank/unset dot would look like a bug
  // rather than "nothing chosen yet".
  document.querySelectorAll('[data-swatch-target]').forEach(function (select) {
    var swatch = document.getElementById(select.getAttribute('data-swatch-target'));
    if (!swatch) {
      return;
    }

    function sync() {
      var option = select.options[select.selectedIndex];
      var color = option ? option.dataset.color : null;
      swatch.style.backgroundColor = color || 'transparent';
      swatch.style.opacity = color ? '1' : '0';
    }

    select.addEventListener('change', sync);
    sync();
  });

  // transactions/quick-add.php only — the sidebar popup's own form is a
  // plain POST-and-redirect (it wants the normal full-page navigation
  // back to whatever page it was opened from) and is left alone here.
  // This one instead posts via fetch() with Accept: application/json so
  // TransactionController::store() replies with JSON instead of a
  // redirect, and shows its own 2-second inline "Added!" state without
  // ever leaving the page — the whole point of a dedicated home-screen
  // launch target being fast for back-to-back entries.
  var pageForm = document.getElementById('quick-add-page-form');
  if (pageForm) {
    var submitBtn = document.getElementById('quick-add-page-submit');
    var errorBox = document.getElementById('quick-add-error');
    var successOverlay = document.getElementById('quick-add-success');
    var amountField = document.getElementById('quick-add-amount');
    var payeeField = document.getElementById('quick-add-payee');

    pageForm.addEventListener('submit', function (event) {
      event.preventDefault();

      errorBox.classList.add('hidden');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Adding…';

      fetch(pageForm.action, {
        method: 'POST',
        body: new FormData(pageForm),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
        .then(function (response) {
          // An expired session sends AuthMiddleware::requireAuth()'s
          // plain "Location: /login" redirect instead of JSON (it only
          // records an intended-URL to return to for a GET request, not
          // this POST) — fetch() follows it to the login page's HTML,
          // which response.json() can't parse. Treated as "please sign
          // in again" rather than the generic error below.
          if (response.redirected) {
            window.location.href = response.url;
            return Promise.reject(new Error('__redirecting__'));
          }

          return response.json().then(function (data) {
            return { ok: response.ok, data: data };
          });
        })
        .then(function (result) {
          if (!result.ok) {
            throw new Error(result.data && result.data.error ? result.data.error : 'Something went wrong. Please try again.');
          }

          // Amount/payee are the two fields that genuinely differ every
          // time; account and category are left as-is since the next
          // transaction is often the same account (sometimes the same
          // category too) — re-picking either one on every single entry
          // is exactly the friction this page exists to remove.
          amountField.value = '';
          payeeField.value = '';

          successOverlay.classList.remove('hidden');
          window.setTimeout(function () {
            successOverlay.classList.add('hidden');
            amountField.focus();
          }, 2000);
        })
        .catch(function (error) {
          if (error.message === '__redirecting__') {
            return;
          }
          errorBox.textContent = error.message || 'Something went wrong. Please try again.';
          errorBox.classList.remove('hidden');
        })
        .finally(function () {
          submitBtn.disabled = false;
          submitBtn.textContent = 'Add transaction';
        });
    });
  }
})();
