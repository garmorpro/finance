(function () {
  'use strict';

  // App\Support\LocalTime::html() emits <time datetime="...Z">a UTC-
  // labeled fallback</time> for every full timestamp in the app. This
  // rewrites the visible text to whatever time zone the browser itself
  // currently reports — not a stored preference, so the exact same
  // login reads in CDT from Texas and MDT from Utah automatically, with
  // nothing to configure and nothing to go stale after traveling.
  // Left alone (showing the UTC fallback) if JS never runs, or if the
  // browser can't parse a particular value — never breaks the page.
  document.querySelectorAll('time[datetime]').forEach(function (el) {
    var date = new Date(el.getAttribute('datetime'));
    if (isNaN(date.getTime())) {
      return;
    }

    el.textContent = date.toLocaleString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: 'numeric',
      minute: '2-digit',
    });
  });
})();
