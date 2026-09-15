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

  // Settings > Audit Log's day-grouped timeline (resources/views/
  // settings/audit-log.php) is rendered with day headers grouped by
  // the *server's* UTC calendar day, as a no-JS fallback — but a 7pm
  // CDT event is already past midnight UTC, i.e. "tomorrow" server-
  // side, which would show it grouped under the wrong day relative to
  // the local time sitting right next to it once the pass above
  // converts that. Replaces those headers with ones grouped by the
  // viewer's own local calendar day instead, reading the same
  // <time datetime> instants rather than re-deriving anything.
  var timeline = document.querySelector('.audit-timeline');
  if (timeline) {
    timeline.querySelectorAll('.audit-day-label').forEach(function (el) {
      el.remove();
    });

    var lastLabel = null;
    timeline.querySelectorAll('.audit-event').forEach(function (event) {
      var timeEl = event.querySelector('time[datetime]');
      if (!timeEl) {
        return;
      }

      var date = new Date(timeEl.getAttribute('datetime'));
      if (isNaN(date.getTime())) {
        return;
      }

      var label = date.toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' });
      if (label !== lastLabel) {
        lastLabel = label;
        var heading = document.createElement('p');
        heading.className = 'audit-day-label';
        heading.textContent = label;
        timeline.insertBefore(heading, event);
      }
    });
  }
})();
