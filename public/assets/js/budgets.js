(function () {
  'use strict';

  var forms = document.querySelectorAll('[data-autosave="budget-item"]');

  // Every planned/actual/remaining figure on the page (section totals,
  // grand totals, the sidebar bars, left-to-budget) is computed
  // server-side with bcmath for currency-safe rounding. Recomputing that
  // in JS would risk drifting from the real numbers, so instead of
  // patching the DOM we just reload once things settle — debounced so
  // tabbing through several fields in a row doesn't reload mid-edit.
  var reloadTimer = null;
  var scheduleReload = function () {
    window.clearTimeout(reloadTimer);
    reloadTimer = window.setTimeout(function () {
      window.location.reload();
    }, 800);
  };

  var money = new window.Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

  forms.forEach(function (form) {
    var input = form.querySelector('input[name="planned_amount"]');
    var status = form.querySelector('.budget-item-status');
    if (!input || !status) {
      return;
    }

    var lastSaved = input.value;
    var applyCheckbox = form.querySelector('.budget-item-apply-future');

    var save = function () {
      var applying = applyCheckbox !== null && applyCheckbox.checked;
      if (input.value === lastSaved && !applying) {
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
            scheduleReload();
          } else {
            status.textContent = result.data.message || 'Error saving';
            status.classList.add('text-red-600', 'dark:text-red-400');
            window.setTimeout(function () {
              status.textContent = '';
            }, 2000);
          }
        })
        .catch(function () {
          status.classList.remove('text-emerald-700', 'dark:text-emerald-400');
          status.classList.add('text-red-600', 'dark:text-red-400');
          status.textContent = 'Error saving';
          window.setTimeout(function () {
            status.textContent = '';
          }, 2000);
        });
    };

    // --- History popover -------------------------------------------------
    var historyBox = form.querySelector('.budget-item-history');
    var historyLast = form.querySelector('.budget-item-history-last');
    var historyAvg = form.querySelector('.budget-item-history-avg');
    var applyLabel = form.querySelector('.budget-item-apply-future-label');
    var categoryIdInput = form.querySelector('input[name="category_id"]');
    var periodMonthInput = form.querySelector('input[name="period_month"]');
    var historyRequested = false;

    var updateApplyLabel = function () {
      if (!applyLabel) {
        return;
      }
      var amount = parseFloat(input.value);
      applyLabel.textContent = window.isNaN(amount)
        ? 'Apply to all future months'
        : 'Apply ' + money.format(amount) + ' to all future months';
    };

    var loadHistory = function () {
      if (historyRequested || !historyBox || !categoryIdInput || !periodMonthInput) {
        return;
      }
      historyRequested = true;

      var url = '/budgets/category-history?category_id=' + window.encodeURIComponent(categoryIdInput.value) +
        '&month=' + window.encodeURIComponent(periodMonthInput.value);

      fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (response) {
          return response.json();
        })
        .then(function (data) {
          if (!data.success) {
            return;
          }
          if (historyLast) {
            historyLast.textContent = money.format(parseFloat(data.lastMonth));
          }
          if (historyAvg) {
            historyAvg.textContent = money.format(parseFloat(data.average));
          }
        })
        .catch(function () {
          if (historyLast) {
            historyLast.textContent = 'Unavailable';
          }
          if (historyAvg) {
            historyAvg.textContent = 'Unavailable';
          }
        });
    };

    // Positioned with fixed (viewport-relative) coordinates computed here,
    // rather than CSS absolute/top-100%, because the section cards use
    // overflow-hidden for their rounded corners — an absolutely positioned
    // popover would get clipped by that the moment it extends past the
    // card's edge.
    var positionHistory = function () {
      var rect = input.getBoundingClientRect();
      var popoverWidth = 304; // matches the 19rem inline width below
      var popoverHeight = historyBox.offsetHeight || 260;

      var left = Math.min(rect.right - popoverWidth, window.innerWidth - popoverWidth - 8);
      left = Math.max(left, 8);

      var top = rect.bottom + 8;
      if (top + popoverHeight > window.innerHeight - 8) {
        top = rect.top - popoverHeight - 8;
      }
      top = Math.max(top, 8);

      historyBox.style.left = left + 'px';
      historyBox.style.top = top + 'px';
    };

    var openHistory = function () {
      if (!historyBox) {
        return;
      }
      historyBox.classList.remove('hidden');
      positionHistory();
      updateApplyLabel();
      loadHistory();
    };

    var closeHistory = function () {
      if (historyBox) {
        historyBox.classList.add('hidden');
      }
    };

    input.addEventListener('focus', openHistory);
    input.addEventListener('input', updateApplyLabel);
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        input.blur();
      } else if (event.key === 'Escape') {
        closeHistory();
        input.blur();
      }
    });

    // Saving and closing the popover both happen only once focus leaves
    // the form entirely — not on the input's own blur — so clicking the
    // "apply to future months" checkbox (which moves focus within the
    // same form) doesn't fire a premature save before the click finishes
    // toggling it.
    form.addEventListener('focusout', function () {
      window.setTimeout(function () {
        if (form.contains(document.activeElement)) {
          return;
        }
        closeHistory();
        save();
      }, 0);
    });
  });
})();
