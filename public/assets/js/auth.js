(function () {
  'use strict';

  var toggleBtn = document.getElementById('toggle-password');
  var passwordInput = document.getElementById('password');
  var eyeIcon = document.getElementById('icon-eye');
  var eyeOffIcon = document.getElementById('icon-eye-off');
  if (!toggleBtn || !passwordInput || !eyeIcon || !eyeOffIcon) {
    return;
  }

  toggleBtn.addEventListener('click', function () {
    var isHidden = passwordInput.type === 'password';
    passwordInput.type = isHidden ? 'text' : 'password';
    toggleBtn.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
    eyeIcon.classList.toggle('hidden', isHidden);
    eyeOffIcon.classList.toggle('hidden', !isHidden);
  });
})();
