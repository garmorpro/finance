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

  // Expense/Income toggle — present on both the sidebar popup and the
  // standalone quick-add page (same ids; only one of the two is ever on
  // a given page). Swaps which category <option>s are visible (each one
  // already carries its own data-type, hidden server-side to match the
  // default "Expense" state) rather than re-fetching anything, clears
  // the category if the one that was picked no longer applies to the
  // new type, and updates the Payee field's placeholder/hint since
  // "who you paid" isn't quite right once this is income.
  var typeInput = document.getElementById('quick-add-type');
  var typeButtons = document.querySelectorAll('.quickadd-type-btn');
  if (typeInput && typeButtons.length) {
    var categorySelect = document.getElementById('quick-add-category');
    var payeeInput = document.getElementById('quick-add-payee');
    var payeeHelp = document.getElementById('quick-add-payee-help');
    var payeeCopy = {
      expense: {
        placeholder: 'e.g. Whole Foods',
        help: 'Who you paid — the merchant, company, or person you spent this on.',
      },
      income: {
        placeholder: 'e.g. Paycheck, Employer',
        help: 'Who paid you — your employer, client, or the source of this income.',
      },
    };

    typeButtons.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var type = btn.dataset.type;
        typeInput.value = type;

        typeButtons.forEach(function (b) {
          b.setAttribute('aria-pressed', b.dataset.type === type ? 'true' : 'false');
        });

        if (categorySelect) {
          var selectedStillValid = false;
          categorySelect.options.forEach(function (option) {
            if (option.value === '') {
              return;
            }
            var matches = option.dataset.type === type;
            option.hidden = !matches;
            if (matches && option.selected) {
              selectedStillValid = true;
            }
          });

          if (!selectedStillValid) {
            categorySelect.value = '';
            categorySelect.dispatchEvent(new Event('change'));
          }
        }

        if (payeeInput && payeeCopy[type]) {
          payeeInput.placeholder = payeeCopy[type].placeholder;
        }
        if (payeeHelp && payeeCopy[type]) {
          payeeHelp.textContent = payeeCopy[type].help;
        }
      });
    });
  }

  // transactions/quick-add.php only — the sidebar popup's own form is a
  // plain POST-and-redirect (it wants the normal full-page navigation
  // back to whatever page it was opened from) and is left alone here.
  // This one instead posts to TransactionController::storeQuickAdd()
  // via fetch() with Accept: application/json, which always replies
  // with JSON (success or error) rather than a redirect, and shows its
  // own 2-second inline "Added!" state without ever leaving the page —
  // the whole point of a dedicated home-screen launch target being fast
  // for back-to-back entries.
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
          // 401 means resolveQuickAddAuth() found neither a session nor
          // a valid key cookie — the session expired, or the key was
          // revoked from another device mid-visit. A reload re-renders
          // the "enter your key" screen server-side rather than trying
          // to fake that state here.
          if (response.status === 401) {
            window.location.reload();
            return Promise.reject(new Error('__reloading__'));
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
          if (error.message === '__reloading__') {
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
