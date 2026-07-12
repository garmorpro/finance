(function () {
  'use strict';

  // The "Skip to main content" link (in this same partial, so it's on
  // every authenticated page) targets #main-content — there's no shared
  // layout wrapper in this app to add that id to <main> once, so it's
  // set here at runtime instead of on every individual view.
  var main = document.querySelector('main');
  if (main && !main.id) {
    main.id = 'main-content';
  }

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
