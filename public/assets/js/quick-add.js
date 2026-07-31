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
})();
