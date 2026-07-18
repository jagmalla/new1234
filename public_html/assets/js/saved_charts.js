/* Auto Business — Save / Open birth charts (layout v2).
 *
 * Lets the user save the chart they have entered and re-open it later from a
 * searchable "Saved Charts" window. Works immediately without login: until the
 * (future) account system sets window.AB_USER = { id, name }, charts are kept
 * under a shared "guest" bucket in this browser's localStorage. Once per-user
 * accounts arrive, each user's charts live under their own key automatically —
 * nothing else changes.
 *
 * Storage: localStorage key ab_saved_charts_v1__<userId|guest> as an array of
 * chart entries, capped at 200. When the server-side account backend arrives,
 * only load()/store() need to point at it — the UI stays the same.
 *
 * Buttons opt in with data-ab-save / data-ab-open attributes (delegated), so the
 * form, the top bar and any future page can trigger it without extra wiring.
 */
(function (global) {
  'use strict';

  var LIMIT = 200;
  var doc = global.document;

  function user() { return global.AB_USER || null; }
  // No login yet → charts live under the shared "guest" bucket; per-user keys
  // take over automatically once AB_USER is set by the future account system.
  function keyFor() { var u = user(); return 'ab_saved_charts_v1__' + (u ? u.id : 'guest'); }
  function load() {
    var k = keyFor(); if (!k) { return []; }
    try { var a = JSON.parse(global.localStorage.getItem(k) || '[]'); return Array.isArray(a) ? a : []; }
    catch (e) { return []; }
  }
  function store(a) { var k = keyFor(); if (!k) { return; } try { global.localStorage.setItem(k, JSON.stringify(a)); } catch (e) {} }
  function byId(id) { var l = load(); for (var i = 0; i < l.length; i++) { if (l[i].id === id) { return l[i]; } } return null; }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // ---- UI helpers ---------------------------------------------------------
  function el(id) { return doc.getElementById(id); }
  function openOverlay(id) { var m = el(id); if (m) { m.classList.remove('hidden'); doc.body.style.overflow = 'hidden'; } }
  function closeOverlays() {
    ['ab-register-modal', 'ab-open-modal', 'ab-save-modal'].forEach(function (id) { var m = el(id); if (m) { m.classList.add('hidden'); } });
    doc.body.style.overflow = '';
  }

  var toastT;
  function toast(msg, kind) {
    var t = el('ab-toast'); if (!t) { return; }
    t.textContent = msg;
    t.className = 'ab-toast ab-toast-' + (kind || 'ok');
    clearTimeout(toastT);
    toastT = setTimeout(function () { t.classList.add('hidden'); }, 3200);
  }

  function needRegister() { openOverlay('ab-register-modal'); }

  // ---- collect current chart data from the birth form ---------------------
  function collect() {
    var f = el('birth-form');
    function v(n) { var e = f ? f.querySelector('[name="' + n + '"]') : null; return e ? String(e.value || '').trim() : ''; }
    return {
      name: v('name'), gender: v('gender'), date: v('date'), time: v('time'),
      place: v('place'), lat: v('lat'), lon: v('lon'), tz: v('tz'),
      ayanamsa: v('ayanamsa') || 'lahiri'
    };
  }

  // ---- save (profile popup) ----------------------------------------------
  // Save opens a profile popup asking Name / Phone / Email / Address / City /
  // Country, prefilled from the chart form (and from the previously saved
  // entry of the same person). नाम + जन्म-तिथि/समय/स्थान अनिवार्य; बाकी optional.
  var CONTACT = ['phone', 'email', 'address', 'city', 'country'];

  function findExisting(list, d) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].name === d.name && list[i].date === d.date && list[i].time === d.time && list[i].place === d.place) { return i; }
    }
    return -1;
  }

  function save() {
    var d = collect();
    if (!d.date || !d.time || !d.place) {
      toast('कृपया पहले जन्म-विवरण भरें — तारीख़, समय व जन्म-स्थान आवश्यक हैं।', 'err');
      return;
    }
    // summary of the mandatory chart data (read-only in the popup)
    var sum = el('ab-save-sum');
    if (sum) {
      sum.innerHTML = '<b>जन्म-विवरण:</b> ' + esc(d.date) + ' &nbsp;' + esc(d.time) +
        ' &nbsp;·&nbsp; ' + esc(d.place) + '<br><span style="font-size:.74rem;color:#94a3b8">(बदलने हेतु पहले New/Profile फ़ॉर्म में सुधार करें)</span>';
    }
    // prefill: name from the form; contact details from an earlier save of the
    // same person (so re-saving edits, not blanks); city guessed from place.
    var prev = null;
    var list = load();
    var pi = findExisting(list, d);
    if (pi >= 0) { prev = list[pi]; }
    var set = function (id, v) { var e = el(id); if (e) { e.value = v || ''; } };
    set('ab-sv-name', d.name || (prev && prev.name) || '');
    set('ab-sv-phone', prev ? prev.phone : '');
    set('ab-sv-email', prev ? prev.email : '');
    set('ab-sv-address', prev ? prev.address : '');
    set('ab-sv-city', (prev && prev.city) || String(d.place || '').split(',')[0].trim());
    set('ab-sv-country', prev ? prev.country : '');
    var err = el('ab-save-err'); if (err) { err.classList.add('hidden'); }
    openOverlay('ab-save-modal');
    var nm = el('ab-sv-name'); if (nm) { setTimeout(function () { nm.focus(); }, 60); }
  }

  function confirmSave() {
    var d = collect();
    var gv = function (id) { var e = el(id); return e ? String(e.value || '').trim() : ''; };
    d.name = gv('ab-sv-name');
    var err = el('ab-save-err');
    var fail = function (msg) { if (err) { err.textContent = msg; err.classList.remove('hidden'); } };
    if (!d.name) { fail('नाम आवश्यक है — कृपया नाम भरें।'); var e = el('ab-sv-name'); if (e) { e.focus(); } return; }
    if (!d.date || !d.time || !d.place) { fail('जन्म-तिथि, समय व स्थान आवश्यक हैं — पहले New/Profile फ़ॉर्म भरें।'); return; }
    var email = gv('ab-sv-email');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { fail('Email ID सही प्रारूप में नहीं है।'); return; }
    d.phone = gv('ab-sv-phone');
    d.email = email;
    d.address = gv('ab-sv-address');
    d.city = gv('ab-sv-city');
    d.country = gv('ab-sv-country');

    // keep the name typed in the popup on the form too, so Calculate/URL match
    var f = el('birth-form');
    if (f) { var fn = f.querySelector('[name="name"]'); if (fn && !fn.value) { fn.value = d.name; } }

    var list = load();
    var idx = findExisting(list, d);
    if (idx >= 0) {
      d.id = list[idx].id; d.savedAt = Date.now(); list[idx] = d; store(list);
      closeOverlays(); toast('चार्ट अपडेट हो गया ✓', 'ok'); return;
    }
    if (list.length >= LIMIT) {
      fail('अधिकतम ' + LIMIT + ' चार्ट ही सहेजे जा सकते हैं। "सहेजे गए चार्ट" में से कुछ हटाएँ।');
      return;
    }
    d.id = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    d.savedAt = Date.now();
    list.push(d); store(list);
    closeOverlays();
    toast('चार्ट सहेजा गया ✓  (' + list.length + '/' + LIMIT + ')', 'ok');
  }

  // ---- open (browse + search) --------------------------------------------
  function openBrowser() {
    var q = el('ab-open-q'); if (q) { q.value = ''; }
    renderList('');
    openOverlay('ab-open-modal');
    if (q) { setTimeout(function () { q.focus(); }, 60); }
  }

  function renderList(query) {
    var host = el('ab-open-list'); if (!host) { return; }
    var list = load().slice().sort(function (a, b) { return (b.savedAt || 0) - (a.savedAt || 0); });
    var cnt = el('ab-open-count'); if (cnt) { cnt.textContent = '(' + list.length + '/' + LIMIT + ')'; }
    var ql = String(query || '').toLowerCase().trim();
    var f = ql ? list.filter(function (c) {
      return ((c.name || '') + ' ' + (c.place || '') + ' ' + (c.date || '') + ' ' +
              (c.phone || '') + ' ' + (c.email || '') + ' ' + (c.city || '') + ' ' +
              (c.country || '')).toLowerCase().indexOf(ql) >= 0;
    }) : list;
    if (!f.length) {
      host.innerHTML = '<div class="ab-open-empty">' +
        (list.length ? 'इस खोज से कोई चार्ट नहीं मिला।' : 'अभी कोई चार्ट सहेजा नहीं गया। जन्म-विवरण भरकर “Save Chart” दबाएँ।') +
        '</div>';
      return;
    }
    host.innerHTML = f.map(function (c) {
      var when = c.savedAt ? new Date(c.savedAt).toLocaleDateString() : '';
      return '<div class="ab-open-row">' +
        '<div class="ab-open-main">' +
          '<div class="ab-open-name">' + esc(c.name || '—') + (c.gender ? ' <span class="ab-open-g">' + esc(c.gender) + '</span>' : '') + '</div>' +
          '<div class="ab-open-meta">' + esc(c.date || '') + '  ' + esc(c.time || '') + (c.place ? '  ·  ' + esc(c.place) : '') + '</div>' +
          ((c.phone || c.email || c.city) ? '<div class="ab-open-meta">' +
            [c.phone ? '📞 ' + esc(c.phone) : '', c.email ? '✉ ' + esc(c.email) : '', c.city ? '🏙 ' + esc(c.city) + (c.country ? ', ' + esc(c.country) : '') : '']
              .filter(Boolean).join('  ·  ') + '</div>' : '') +
        '</div>' +
        '<div class="ab-open-acts">' +
          '<button type="button" class="ab-btn ab-btn-sm" data-open="' + esc(c.id) + '">खोलें / Open</button>' +
          '<button type="button" class="ab-btn ab-btn-ghost ab-btn-sm" data-del="' + esc(c.id) + '" title="हटाएँ">✕</button>' +
        '</div>' +
        (when ? '<div class="ab-open-when">' + esc(when) + '</div>' : '') +
        '</div>';
    }).join('');
  }

  function openChart(c) {
    if (!c) { return; }
    var p = new global.URLSearchParams();
    ['name', 'gender', 'date', 'time', 'place', 'lat', 'lon', 'tz', 'ayanamsa'].forEach(function (k) {
      if (c[k]) { p.set(k, c[k]); }
    });
    p.set('layout', 'new');
    // Reopen through the same URL the Calculate form submits to, so it works
    // on every hosting (pretty /calc rewrite or plain index.php routing alike).
    var f = el('birth-form');
    var base = (f && f.getAttribute('action')) || '/calc';
    global.location.href = base + '?' + p.toString();
  }

  function del(id) {
    var list = load().filter(function (c) { return c.id !== id; });
    store(list); renderList((el('ab-open-q') || {}).value || '');
    toast('चार्ट हटा दिया गया।', 'ok');
  }

  // ---- wiring (delegated) -------------------------------------------------
  function bind() {
    doc.addEventListener('click', function (e) {
      if (e.target.closest('[data-ab-save]')) { e.preventDefault(); save(); return; }
      if (e.target.closest('[data-ab-open]')) { e.preventDefault(); openBrowser(); return; }
      var op = e.target.closest('[data-open]'); if (op) { openChart(byId(op.getAttribute('data-open'))); return; }
      var dl = e.target.closest('[data-del]'); if (dl) { del(dl.getAttribute('data-del')); return; }
      if (e.target.closest('[data-close]') || e.target.classList.contains('ab-modal-overlay')) { closeOverlays(); return; }
    });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeOverlays(); } });
    var q = el('ab-open-q'); if (q) { q.addEventListener('input', function () { renderList(this.value); }); }
    var cf = el('ab-save-confirm'); if (cf) { cf.addEventListener('click', confirmSave); }
    // Enter inside the save popup = Save
    var sm = el('ab-save-modal');
    if (sm) { sm.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); confirmSave(); } }); }
  }

  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', bind); } else { bind(); }

  global.ABSaved = { save: save, open: openBrowser, needRegister: needRegister };
})(window);
