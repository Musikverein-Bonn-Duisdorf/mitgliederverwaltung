/**
 * Live IBAN check (ISO 13616 / MOD-97) + DE-BLZ → Kreditinstitut.
 * Bind: <input class="js-iban-check" data-iban-bank="fieldId" …>
 * Bank: <input class="js-iban-bank" id="fieldId"> — manuell überschreibbar
 */
(function (global) {
  'use strict';

  var LENGTHS = {
    AL: 28, AD: 24, AT: 20, AZ: 28, BH: 22, BY: 28, BE: 16, BA: 20, BR: 29, BG: 22,
    CR: 22, HR: 21, CY: 28, CZ: 24, DK: 18, DO: 28, EE: 20, FO: 18, FI: 18, FR: 27,
    GE: 22, DE: 22, GI: 23, GR: 27, GL: 18, GT: 28, HU: 28, IS: 26, IE: 22, IL: 23,
    IT: 27, JO: 30, KZ: 20, XK: 20, KW: 30, LV: 21, LB: 28, LI: 21, LT: 20, LU: 20,
    MK: 19, MT: 31, MR: 27, MU: 30, MD: 24, MC: 27, ME: 22, NL: 18, NO: 15, PK: 24,
    PS: 29, PL: 28, PT: 25, QA: 29, RO: 24, SM: 27, SA: 24, RS: 22, SK: 24, SI: 19,
    ES: 24, SE: 24, CH: 21, TN: 24, TR: 26, UA: 29, AE: 23, GB: 22, VG: 24
  };

  var lookupUrl = 'blzLookup.php';
  var bankTimers = {};

  function normalize(raw) {
    return String(raw || '').replace(/\s+/g, '').toUpperCase();
  }

  function formatGrouped(raw) {
    var n = normalize(raw);
    if (!n) return '';
    return n.replace(/(.{4})/g, '$1 ').trim();
  }

  function check(raw) {
    var iban = normalize(raw);
    if (!iban) {
      return { state: 'empty', ok: false };
    }
    if (!/^[A-Z]{2}/.test(iban)) {
      return { state: iban.length < 2 ? 'incomplete' : 'invalid', ok: false };
    }
    if (iban.length >= 4 && !/^[A-Z]{2}[0-9]{2}/.test(iban)) {
      return { state: 'invalid', ok: false };
    }
    if (!/^[A-Z]{2}[0-9]{2}[A-Z0-9]*$/.test(iban)) {
      return { state: 'invalid', ok: false };
    }
    var expected = LENGTHS[iban.slice(0, 2)];
    if (expected) {
      if (iban.length < expected) {
        return { state: 'incomplete', ok: false };
      }
      if (iban.length > expected) {
        return { state: 'invalid', ok: false };
      }
    } else if (iban.length < 15) {
      return { state: 'incomplete', ok: false };
    } else if (iban.length > 34) {
      return { state: 'invalid', ok: false };
    }
    var rearranged = iban.slice(4) + iban.slice(0, 4);
    var expanded = '';
    for (var i = 0; i < rearranged.length; i++) {
      var ch = rearranged.charAt(i);
      if (ch >= 'A' && ch <= 'Z') {
        expanded += String(ch.charCodeAt(0) - 55);
      } else {
        expanded += ch;
      }
    }
    var checksum = 0;
    for (var j = 0; j < expanded.length; j++) {
      checksum = (checksum * 10 + (expanded.charCodeAt(j) - 48)) % 97;
    }
    return checksum === 1
      ? { state: 'valid', ok: true }
      : { state: 'invalid', ok: false };
  }

  function ensureHint(input) {
    var id = input.getAttribute('aria-describedby');
    if (id) {
      var existing = document.getElementById(id);
      if (existing && existing.classList.contains('iban-check-hint')) {
        return existing;
      }
    }
    var hint = document.createElement('span');
    hint.className = 'iban-check-hint';
    hint.setAttribute('aria-live', 'polite');
    hint.id = 'iban-hint-' + Math.random().toString(36).slice(2, 10);
    input.setAttribute('aria-describedby', hint.id);
    input.insertAdjacentElement('afterend', hint);
    return hint;
  }

  function setState(input, hint, result, allowEmpty) {
    input.classList.remove('iban-check--valid', 'iban-check--invalid', 'iban-check--incomplete');
    hint.textContent = '';
    hint.className = 'iban-check-hint';
    if (result.state === 'empty') {
      input.removeAttribute('aria-invalid');
      if (!allowEmpty) {
        input.classList.add('iban-check--invalid');
        input.setAttribute('aria-invalid', 'true');
        hint.textContent = 'IBAN fehlt.';
        hint.classList.add('iban-check-hint--invalid');
      }
      return;
    }
    if (result.state === 'incomplete') {
      input.classList.add('iban-check--incomplete');
      input.removeAttribute('aria-invalid');
      return;
    }
    if (result.ok) {
      input.classList.add('iban-check--valid');
      input.setAttribute('aria-invalid', 'false');
      return;
    }
    input.classList.add('iban-check--invalid');
    input.setAttribute('aria-invalid', 'true');
    hint.textContent = 'IBAN ungültig.';
    hint.classList.add('iban-check-hint--invalid');
  }

  function resolveBankInput(ibanInput) {
    var id = ibanInput.getAttribute('data-iban-bank');
    if (id) {
      return document.getElementById(id);
    }
    var form = ibanInput.form;
    if (!form) {
      return null;
    }
    return form.querySelector('input.js-iban-bank, input[name="BankName"]');
  }

  function blzFromPartialIban(raw) {
    var iban = normalize(raw);
    if (iban.length < 12 || iban.slice(0, 2) !== 'DE') {
      return '';
    }
    // DE + 2 Prüfziffern + 8-stellige BLZ — reicht für Instituts-Lookup
    if (!/^DE[0-9]{2}\d{8}/.test(iban.slice(0, 12))) {
      return '';
    }
    return iban.slice(4, 12);
  }

  function fetchBankName(blz) {
    return fetch(lookupUrl + '?blz=' + encodeURIComponent(blz), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    }).then(function (r) {
      if (!r.ok) {
        throw new Error('lookup failed');
      }
      return r.json();
    });
  }

  function bindBankAutofill(ibanInput) {
    var bank = resolveBankInput(ibanInput);
    if (!bank || bank.getAttribute('data-iban-bank-bound') === '1') {
      return;
    }
    bank.setAttribute('data-iban-bank-bound', '1');

    var lastBlz = '';
    var timerKey = ibanInput.id || ibanInput.name || String(Math.random());

    bank.addEventListener('input', function () {
      if (bank.value.trim() === '') {
        lastBlz = '';
      }
    });

    function applyName(name, blz) {
      if (!name || bank.value.trim() !== '') {
        return;
      }
      bank.value = name;
      lastBlz = blz || lastBlz;
    }

    function requestFill() {
      if (bank.value.trim() !== '') {
        return;
      }
      var blz = blzFromPartialIban(ibanInput.value);
      if (!blz || blz === lastBlz) {
        return;
      }
      clearTimeout(bankTimers[timerKey]);
      bankTimers[timerKey] = setTimeout(function () {
        if (bank.value.trim() !== '') {
          return;
        }
        var currentBlz = blzFromPartialIban(ibanInput.value);
        if (!currentBlz || currentBlz !== blz) {
          return;
        }
        fetchBankName(blz).then(function (data) {
          if (bank.value.trim() !== '') {
            return;
          }
          if (blzFromPartialIban(ibanInput.value) !== blz) {
            return;
          }
          if (data && data.ok && data.name) {
            applyName(data.name, blz);
          }
        }).catch(function () {});
      }, 80);
    }

    ibanInput.addEventListener('input', requestFill);
    ibanInput.addEventListener('blur', requestFill);
    requestFill();
  }

  function bind(input) {
    if (input.getAttribute('data-iban-bound') === '1') {
      return;
    }
    input.setAttribute('data-iban-bound', '1');
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('inputmode', 'text');
    var hint = ensureHint(input);
    var allowEmpty = input.getAttribute('data-iban-required') !== '1';
    var showIncompleteInvalid = false;

    function refresh() {
      var result = check(input.value);
      if (result.state === 'incomplete' && showIncompleteInvalid) {
        setState(input, hint, { state: 'invalid', ok: false }, allowEmpty);
        return;
      }
      setState(input, hint, result, allowEmpty);
    }

    input.addEventListener('input', function () {
      showIncompleteInvalid = false;
      refresh();
    });
    input.addEventListener('blur', function () {
      var n = normalize(input.value);
      if (n) {
        input.value = formatGrouped(n);
        showIncompleteInvalid = true;
      }
      refresh();
    });
    refresh();
    bindBankAutofill(input);
  }

  function init(root) {
    var scope = root || document;
    var scriptEl = document.querySelector('script[data-blz-lookup]');
    if (scriptEl) {
      var u = scriptEl.getAttribute('data-blz-lookup');
      if (u) {
        lookupUrl = u;
      }
    }
    var list = scope.querySelectorAll('input.js-iban-check');
    for (var i = 0; i < list.length; i++) {
      bind(list[i]);
    }
  }

  function formHasInvalidIban(form) {
    var list = form.querySelectorAll('input.js-iban-check');
    for (var i = 0; i < list.length; i++) {
      var input = list[i];
      if (input.disabled || input.offsetParent === null) {
        continue;
      }
      var sepaSection = input.closest('[hidden]');
      if (sepaSection && sepaSection.hasAttribute('hidden')) {
        continue;
      }
      var allowEmpty = input.getAttribute('data-iban-required') !== '1';
      var result = check(input.value);
      if (result.state === 'empty' && allowEmpty) {
        continue;
      }
      if (!result.ok) {
        bind(input);
        input.dispatchEvent(new Event('blur'));
        input.focus();
        return true;
      }
    }
    return false;
  }

  document.addEventListener('DOMContentLoaded', function () {
    init(document);
    document.addEventListener('submit', function (e) {
      var form = e.target;
      if (!form || !form.querySelector || !form.querySelector('input.js-iban-check')) {
        return;
      }
      if (formHasInvalidIban(form)) {
        e.preventDefault();
      }
    }, true);
  });

  global.IbanCheck = {
    init: init,
    check: check,
    normalize: normalize,
    format: formatGrouped,
    isValid: function (raw) {
      return check(raw).ok;
    }
  };
})(window);
