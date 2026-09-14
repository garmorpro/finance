(function () {
  'use strict';

  if (!window.PublicKeyCredential) {
    // No WebAuthn support at all (very old browser) — leave any
    // "passkey" buttons on the page alone; they simply won't do
    // anything if clicked, same as a plain <button> with no handler.
    // Password/2FA login and manual passkey management both still work
    // through the regular form posts either way.
    return;
  }

  function base64urlToBuffer(base64url) {
    var base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    var padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    var binary = atob(padded);
    var bytes = new Uint8Array(binary.length);
    for (var i = 0; i < binary.length; i++) {
      bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
  }

  function bufferToBase64url(buffer) {
    var bytes = new Uint8Array(buffer);
    var binary = '';
    for (var i = 0; i < bytes.byteLength; i++) {
      binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  function creationOptionsFromJSON(json) {
    var options = Object.assign({}, json);
    options.challenge = base64urlToBuffer(json.challenge);
    options.user = Object.assign({}, json.user, { id: base64urlToBuffer(json.user.id) });
    if (Array.isArray(json.excludeCredentials)) {
      options.excludeCredentials = json.excludeCredentials.map(function (cred) {
        return Object.assign({}, cred, { id: base64urlToBuffer(cred.id) });
      });
    }
    return options;
  }

  function requestOptionsFromJSON(json) {
    var options = Object.assign({}, json);
    options.challenge = base64urlToBuffer(json.challenge);
    if (Array.isArray(json.allowCredentials)) {
      options.allowCredentials = json.allowCredentials.map(function (cred) {
        return Object.assign({}, cred, { id: base64urlToBuffer(cred.id) });
      });
    }
    return options;
  }

  function attestationCredentialToJSON(credential) {
    return {
      id: credential.id,
      rawId: bufferToBase64url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
        attestationObject: bufferToBase64url(credential.response.attestationObject),
      },
    };
  }

  function assertionCredentialToJSON(credential) {
    return {
      id: credential.id,
      rawId: bufferToBase64url(credential.rawId),
      type: credential.type,
      response: {
        clientDataJSON: bufferToBase64url(credential.response.clientDataJSON),
        authenticatorData: bufferToBase64url(credential.response.authenticatorData),
        signature: bufferToBase64url(credential.response.signature),
        userHandle: credential.response.userHandle ? bufferToBase64url(credential.response.userHandle) : null,
      },
    };
  }

  function postForm(url, fields) {
    var body = new URLSearchParams();
    Object.keys(fields).forEach(function (key) {
      body.set(key, fields[key]);
    });
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(function (res) {
      return res.json();
    });
  }

  // --- Registration: Settings → Security "Add a passkey" ---
  var registerBtn = document.getElementById('webauthn-register');
  if (registerBtn) {
    registerBtn.addEventListener('click', function () {
      var statusEl = document.getElementById('webauthn-register-status');
      var setStatus = function (text, isError) {
        if (statusEl) {
          statusEl.textContent = text;
          statusEl.classList.toggle('text-red-600', !!isError);
          statusEl.classList.toggle('dark:text-red-400', !!isError);
        }
      };

      registerBtn.disabled = true;
      setStatus('Follow the prompt on your device…');

      fetch('/settings/security/webauthn/register-options')
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (data.error) {
            throw new Error(data.error);
          }
          return navigator.credentials.create({ publicKey: creationOptionsFromJSON(data.options) })
            .then(function (credential) {
              return postForm('/settings/security/webauthn/register', {
                csrf_token: data.csrf_token,
                credential: JSON.stringify(attestationCredentialToJSON(credential)),
              });
            });
        })
        .then(function (result) {
          if (result.error) {
            throw new Error(result.error);
          }
          window.location.reload();
        })
        .catch(function (err) {
          registerBtn.disabled = false;
          // A cancelled/dismissed platform prompt throws a DOMException,
          // not a plain Error with a useful message worth surfacing —
          // treat anything without one as a silent cancel.
          if (err && err.message) {
            setStatus(err.message, true);
          } else {
            setStatus('');
          }
        });
    });
  }

  // --- Sign-in: login page "Sign in with a passkey" ---
  var loginBtn = document.getElementById('webauthn-login');
  if (loginBtn) {
    loginBtn.addEventListener('click', function () {
      var statusEl = document.getElementById('webauthn-login-status');
      var setStatus = function (text, isError) {
        if (statusEl) {
          statusEl.textContent = text;
          statusEl.classList.toggle('text-red-600', !!isError);
          statusEl.classList.toggle('dark:text-red-400', !!isError);
        }
      };

      loginBtn.disabled = true;
      setStatus('Follow the prompt on your device…');

      fetch('/login/webauthn/options')
        .then(function (res) {
          return res.json();
        })
        .then(function (data) {
          if (data.error) {
            throw new Error(data.error);
          }
          return navigator.credentials.get({ publicKey: requestOptionsFromJSON(data.options) })
            .then(function (credential) {
              return postForm('/login/webauthn/verify', {
                csrf_token: data.csrf_token,
                credential: JSON.stringify(assertionCredentialToJSON(credential)),
              });
            });
        })
        .then(function (result) {
          if (result.error) {
            throw new Error(result.error);
          }
          window.location.href = result.redirect || '/';
        })
        .catch(function (err) {
          loginBtn.disabled = false;
          if (err && err.message) {
            setStatus(err.message, true);
          } else {
            setStatus('');
          }
        });
    });
  }
})();
