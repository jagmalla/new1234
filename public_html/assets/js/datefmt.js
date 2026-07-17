/* Auto Business — shared date/time validator + auto-fixer (ABDate).
 *
 * Used by every birth/gochar/milan date & time input. On blur it AUTO-FIXES a
 * loosely-typed value to the canonical form (Date -> DD-MM-YYYY, Time -> 24h
 * HH:MM), accepting many separators, month NAMES (jan/january…), am/pm and
 * different field orders. If the value truly cannot be understood it shows a
 * small inline error under the field with the correct format; a form-submit
 * guard blocks calculation and pops the same message so nothing is computed
 * from a bad date.
 *
 *   ABDate.normalizeDate(str) -> { ok:true, value:'DD-MM-YYYY' } | { ok:false, error }
 *   ABDate.normalizeTime(str) -> { ok:true, value:'HH:MM' }      | { ok:false, error }
 *   ABDate.attach(inputEl, 'date'|'time')   // auto-fix on blur + inline error
 *   ABDate.guardForm(formEl, [{el,kind},…]) // block submit + alert if invalid
 */
(function (global) {
  'use strict';

  var MONTHS = {
    jan: 1, january: 1, feb: 2, february: 2, mar: 3, march: 3, apr: 4, april: 4,
    may: 5, jun: 6, june: 6, jul: 7, july: 7, aug: 8, august: 8, sep: 9, sept: 9,
    september: 9, oct: 10, october: 10, nov: 11, november: 11, dec: 12, december: 12
  };
  var DATE_HINT = 'सही रूप: DD-MM-YYYY (उदा. 20-01-1983) / Correct format: DD-MM-YYYY (e.g. 20-01-1983).';
  var TIME_HINT = 'सही रूप: HH:MM 24-घंटे (उदा. 09:05) / Correct format: HH:MM 24-hour (e.g. 09:05).';

  function pad2(n) { return (n < 10 ? '0' : '') + n; }

  function normalizeDate(raw) {
    var s = String(raw == null ? '' : raw).trim();
    if (!s) { return { ok: false, error: 'तिथि दर्ज करें / Enter a date. ' + DATE_HINT }; }
    var parts = s.split(/[\-\/.,\s]+/).filter(Boolean);
    if (parts.length !== 3) { return { ok: false, error: DATE_HINT }; }

    var d, m, y, monthName = null, monthPos = -1;
    parts.forEach(function (p, i) {
      var w = p.toLowerCase().replace(/[^a-z]/g, '');
      if (w && MONTHS[w] != null) { monthName = MONTHS[w]; monthPos = i; }
    });

    if (monthName != null) {
      var rest = [];
      parts.forEach(function (p, i) { if (i !== monthPos) { rest.push(parseInt(p, 10)); } });
      if (rest.length !== 2 || rest.some(isNaN)) { return { ok: false, error: DATE_HINT }; }
      m = monthName;
      var A = rest[0], B = rest[1];
      if (A > 31) { y = A; d = B; } else if (B > 31) { y = B; d = A; } else { d = A; y = B; }
    } else {
      var a = parseInt(parts[0], 10), b = parseInt(parts[1], 10), c = parseInt(parts[2], 10);
      if ([a, b, c].some(isNaN)) { return { ok: false, error: DATE_HINT }; }
      // First field > 31 => YYYY-MM-DD, otherwise DD-MM-YYYY.
      if (a > 31) { y = a; m = b; d = c; } else { d = a; m = b; y = c; }
    }

    if (y < 100) { y += (y <= (new Date().getFullYear() % 100)) ? 2000 : 1900; }
    if (m < 1 || m > 12) { return { ok: false, error: 'महीना 1–12 होना चाहिए / Month must be 1–12. ' + DATE_HINT }; }
    if (d < 1 || d > 31) { return { ok: false, error: 'दिन 1–31 होना चाहिए / Day must be 1–31. ' + DATE_HINT }; }
    var dt = new Date(y, m - 1, d);
    if (dt.getFullYear() !== y || dt.getMonth() !== m - 1 || dt.getDate() !== d) {
      return { ok: false, error: 'यह तिथि मौजूद नहीं / That date does not exist. ' + DATE_HINT };
    }
    return { ok: true, value: pad2(d) + '-' + pad2(m) + '-' + y };
  }

  function normalizeTime(raw) {
    var s = String(raw == null ? '' : raw).trim();
    if (!s) { return { ok: false, error: 'समय दर्ज करें / Enter a time. ' + TIME_HINT }; }
    var ampm = null, am = s.match(/([ap])\.?\s*m\.?$/i);
    if (am) { ampm = am[1].toLowerCase(); s = s.replace(/([ap])\.?\s*m\.?$/i, '').trim(); }

    var parts = s.split(/[:\s.]+/).filter(Boolean);
    if (parts.length === 1 && /^\d{3,4}$/.test(parts[0])) {   // "0905" / "905"
      var v = ('000' + parts[0]).slice(-4);
      parts = [v.slice(0, 2), v.slice(2)];
    }
    if (!parts.length || parts.some(function (x) { return !/^\d+$/.test(x); })) {
      return { ok: false, error: TIME_HINT };
    }
    var hh = parseInt(parts[0], 10), mi = parseInt(parts[1] || '0', 10);
    if (isNaN(hh) || isNaN(mi)) { return { ok: false, error: TIME_HINT }; }
    if (ampm) {
      if (hh < 1 || hh > 12) { return { ok: false, error: '12-घंटे समय 1–12 / 12-hour time must be 1–12 (e.g. 9:05 am).' }; }
      if (ampm === 'p' && hh < 12) { hh += 12; }
      if (ampm === 'a' && hh === 12) { hh = 0; }
    }
    if (hh > 23) { return { ok: false, error: 'घंटा 0–23 होना चाहिए / Hour must be 0–23. ' + TIME_HINT }; }
    if (mi > 59) { return { ok: false, error: 'मिनट 0–59 होना चाहिए / Minute must be 0–59. ' + TIME_HINT }; }
    return { ok: true, value: pad2(hh) + ':' + pad2(mi) };
  }

  function fnFor(kind) { return kind === 'time' ? normalizeTime : normalizeDate; }

  function attach(el, kind) {
    if (!el || el._abdate) { return; }
    el._abdate = true;
    var fn = fnFor(kind), errEl = null;
    function clearErr() { if (errEl) { errEl.parentNode && errEl.parentNode.removeChild(errEl); errEl = null; } el.style.borderColor = ''; el.removeAttribute('aria-invalid'); }
    function showErr(msg) {
      clearErr();
      el.style.borderColor = '#dc2626';
      el.setAttribute('aria-invalid', 'true');
      errEl = document.createElement('div');
      errEl.className = 'abdate-err';
      errEl.textContent = msg;
      errEl.style.cssText = 'color:#b91c1c;font-size:.72rem;line-height:1.35;margin-top:3px';
      (el.parentNode || document.body).appendChild(errEl);
    }
    el.addEventListener('blur', function () {
      var v = (el.value || '').trim();
      if (!v) { clearErr(); return; }
      var r = fn(v);
      if (r.ok) { el.value = r.value; clearErr(); } else { showErr(r.error); }
    });
    el.addEventListener('focus', clearErr);
    el.addEventListener('input', clearErr);
    el._abdateValidate = function () {
      var v = (el.value || '').trim();
      if (!v) { return null; }
      var r = fn(v);
      if (r.ok) { el.value = r.value; clearErr(); return null; }
      showErr(r.error);
      return r.error;
    };
  }

  // Block a form's submit if any attached field is invalid; alert the first error.
  function guardForm(formEl, fields) {
    if (!formEl) { return; }
    formEl.addEventListener('submit', function (e) {
      var firstErr = null, firstEl = null;
      (fields || []).forEach(function (f) {
        var el = typeof f.el === 'string' ? formEl.querySelector(f.el) : f.el;
        if (!el || !el._abdateValidate) { return; }
        var err = el._abdateValidate();
        if (err && !firstErr) { firstErr = err; firstEl = el; }
      });
      if (firstErr) {
        e.preventDefault();
        if (firstEl && firstEl.focus) { firstEl.focus(); }
        try { global.alert(firstErr); } catch (x) {}
      }
    }, true);
  }

  global.ABDate = {
    normalizeDate: normalizeDate, normalizeTime: normalizeTime,
    attach: attach, guardForm: guardForm
  };
})(window);
