(function () {
  'use strict';

  var shell = document.querySelector('.review-shell');
  if (!shell) {
    return;
  }

  var steps = Array.prototype.slice.call(document.querySelectorAll('.review-step'));
  if (steps.length === 0) {
    return;
  }

  var totalSteps = steps.length; // includes the synthetic Summary step
  var contentStepCount = totalSteps - 1;
  var currentStep = 0;

  var track = document.getElementById('reviewProgressTrack');
  var caption = document.getElementById('reviewStepCaption');
  var backBtn = document.getElementById('reviewBackBtn');
  var nextBtn = document.getElementById('reviewNextBtn');
  var remainingEl = document.getElementById('reviewRemaining');

  var goalContributions = parseFloat(shell.dataset.goalContributions) || 0;

  // One progress segment per content step — the Summary step is the
  // destination, not something to fill in, so it doesn't get its own tick.
  for (var i = 0; i < contentStepCount; i++) {
    var seg = document.createElement('div');
    seg.className = 'review-progress-seg';
    track.appendChild(seg);
  }
  var segments = Array.prototype.slice.call(track.children);

  function money(n) {
    var sign = n < 0 ? '-' : '';
    n = Math.abs(n);
    return sign + '$' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  // ---- Live "left to budget" preview -----------------------------------
  // Client-side addition only, for a responsive number as you type. Every
  // *persisted* figure still goes through the real /budgets/items endpoint
  // and bcmath server-side — this is a UI preview of numbers the user is
  // actively typing, not a source of truth.
  function recompute() {
    var income = 0;
    var expense = 0;

    document.querySelectorAll('.review-amount-input').forEach(function (input) {
      var value = parseFloat(input.value) || 0;
      if (input.dataset.type === 'income') {
        income += value;
      } else {
        expense += value;
      }
    });

    var remaining = income - expense - goalContributions;
    var positive = remaining >= 0;

    remainingEl.textContent = money(remaining);
    remainingEl.classList.toggle('text-emerald-700', positive);
    remainingEl.classList.toggle('dark:text-emerald-400', positive);
    remainingEl.classList.toggle('text-red-600', !positive);
    remainingEl.classList.toggle('dark:text-red-400', !positive);

    var summaryIncome = document.getElementById('summaryIncome');
    var summaryExpense = document.getElementById('summaryExpense');
    var summaryRemaining = document.getElementById('summaryRemaining');
    var summaryHero = document.getElementById('summaryHero');
    var summaryHeroLabel = document.getElementById('summaryHeroLabel');
    var summaryHeroIcon = document.getElementById('summaryHeroIcon');

    if (summaryIncome) { summaryIncome.textContent = money(income); }
    if (summaryExpense) { summaryExpense.textContent = money(expense); }
    if (summaryRemaining) { summaryRemaining.textContent = money(remaining); }
    if (summaryHero) {
      summaryHero.classList.toggle('stat-hero-positive', positive);
      summaryHero.classList.toggle('stat-hero-negative', !positive);
    }
    if (summaryHeroIcon) {
      summaryHeroIcon.classList.toggle('text-emerald-600', positive);
      summaryHeroIcon.classList.toggle('text-red-600', !positive);
    }
    if (summaryHeroLabel) {
      summaryHeroLabel.classList.toggle('text-emerald-700', positive);
      summaryHeroLabel.classList.toggle('dark:text-emerald-400', positive);
      summaryHeroLabel.classList.toggle('text-red-700', !positive);
      summaryHeroLabel.classList.toggle('dark:text-red-400', !positive);
    }
  }

  document.addEventListener('input', function (event) {
    if (event.target.classList.contains('review-amount-input')) {
      recompute();
    }
  });

  // ---- Per-field autosave -----------------------------------------------
  // Same /budgets/items endpoint the main Budgets page's autosave uses,
  // but deliberately without that page's "reload after saving" behavior —
  // a full reload here would wipe out which step of the wizard we're on.
  function commitField(input) {
    var value = input.value.trim();
    if (value === input.dataset.lastSaved) {
      return;
    }
    input.dataset.lastSaved = value;

    var form = input.closest('form');
    fetch(form.getAttribute('action'), {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      body: new FormData(form),
    }).catch(function () {
      // Silent failure is acceptable here, same reasoning as elsewhere in
      // this app's autosave forms: worst case this one field is out of
      // sync until the user revisits it.
    });
  }

  function commitStep(index) {
    var step = steps[index];
    if (!step) {
      return;
    }
    step.querySelectorAll('.review-amount-input').forEach(commitField);
  }

  function goToStep(target) {
    target = Math.max(0, Math.min(totalSteps - 1, target));
    if (target !== currentStep) {
      commitStep(currentStep);
    }
    currentStep = target;

    steps.forEach(function (step, index) {
      step.classList.toggle('active', index === currentStep);
    });
    segments.forEach(function (seg, index) {
      seg.classList.toggle('done', index < currentStep);
      seg.classList.toggle('active', index === currentStep);
    });

    var isSummary = currentStep === contentStepCount;
    var heading = steps[currentStep].querySelector('h1');
    caption.textContent = isSummary
      ? 'Review complete'
      : 'Step ' + (currentStep + 1) + ' of ' + contentStepCount + (heading ? ' · ' + heading.textContent : '');

    backBtn.style.visibility = currentStep === 0 ? 'hidden' : 'visible';
    nextBtn.style.display = isSummary ? 'none' : '';

    if (!isSummary) {
      var onLastContentStep = currentStep === contentStepCount - 1;
      var nextHeading = !onLastContentStep ? steps[currentStep + 1].querySelector('h1') : null;
      nextBtn.textContent = onLastContentStep
        ? 'Next: Review'
        : (nextHeading ? 'Next: ' + nextHeading.textContent : 'Next');
    }

    window.scrollTo({ top: 0, behavior: 'auto' });
  }

  nextBtn.addEventListener('click', function () { goToStep(currentStep + 1); });
  backBtn.addEventListener('click', function () { goToStep(currentStep - 1); });

  // Best-effort: catch anything on the current step that was typed but
  // hasn't been committed yet if the tab closes or navigates away mid-step.
  window.addEventListener('pagehide', function () { commitStep(currentStep); });

  recompute();
  goToStep(0);
})();
