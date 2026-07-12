(function () {
  'use strict';

  var sidebar = document.getElementById('sidebar');
  var overlay = document.getElementById('sidebar-overlay');
  var openBtn = document.getElementById('sidebar-open');
  var closeBtn = document.getElementById('sidebar-close');

  if (!sidebar || !overlay || !openBtn || !closeBtn) {
    return;
  }

  function openDrawer() {
    sidebar.classList.add('sidebar-open');
    overlay.classList.remove('hidden');
  }

  function closeDrawer() {
    sidebar.classList.remove('sidebar-open');
    overlay.classList.add('hidden');
  }

  openBtn.addEventListener('click', openDrawer);
  closeBtn.addEventListener('click', closeDrawer);
  overlay.addEventListener('click', closeDrawer);

  // Close automatically after following a nav link, so the drawer isn't
  // left open when the next page loads under a browser's back/forward
  // cache.
  sidebar.addEventListener('click', function (event) {
    if (event.target.closest('a')) {
      closeDrawer();
    }
  });
})();
