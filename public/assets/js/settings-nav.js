(function () {
  'use strict';

  // Settings sub-nav search (partials/_nav.php) — filters the nav
  // links themselves by their visible label as you type, rather than
  // being a decorative input that does nothing. A whole
  // .settings-nav-group (its heading included) hides once none of its
  // links match, so a search for "tag" doesn't leave an empty
  // "Account" heading sitting above nothing.
  //
  // Toggled via the "hidden" Tailwind utility *class*
  // (classList.add/remove), not the hidden DOM *attribute*
  // (el.hidden = true) — .settings-nav-link sets display:flex, which
  // (same specificity, later in the cascade) silently wins over the
  // browser's native [hidden]{display:none} and would leave a
  // "hidden" link still visible. Same reasoning as modals.js's own
  // hidden/flex classList toggling for the exact same conflict.
  var input = document.getElementById('settings-nav-search');
  if (!input) {
    return;
  }

  var groups = document.querySelectorAll('.settings-nav-group');
  var emptyState = document.getElementById('settings-nav-empty');

  input.addEventListener('input', function () {
    var query = input.value.trim().toLowerCase();
    var anyVisible = false;

    groups.forEach(function (group) {
      var groupHasMatch = false;

      group.querySelectorAll('a').forEach(function (link) {
        var matches = query === '' || link.textContent.toLowerCase().includes(query);
        link.classList.toggle('hidden', !matches);
        if (matches) {
          groupHasMatch = true;
        }
      });

      group.classList.toggle('hidden', !groupHasMatch);
      if (groupHasMatch) {
        anyVisible = true;
      }
    });

    if (emptyState) {
      emptyState.classList.toggle('hidden', anyVisible);
    }
  });
})();
