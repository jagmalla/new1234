/* Auto Business — Save / Open birth charts (layout v2).
 *
 * Lets a registered user save the chart they have entered and re-open it later
 * from a searchable "Saved Charts" window. Saving/opening is gated to logged-in
 * users: window.AB_USER is null until the (future) login system sets it to
 * { id, name }. While it is null every Save/Open action shows a "please
 * register" message instead of storing anything.
 *
 * Storage: per-user in localStorage (key ab_saved_charts_v1__<userId>) as an
 * array of chart entries. Capped at 200 charts per user. When the server-side
 * account backend arrives, only load()/store() need to point at it — the UI and
 * gating stay the same.
 *
 * Buttons opt in with data-ab-save / data-ab-open attributes (delegated), so the
 * form, the top bar and any future page can trigger it without extra wiring.
 */
(function (global) {
  'use strict';

  var LIMIT = 200;
  var doc = global.document;

  function user() { return global.AB_USER || null; }
  function keyFor() { var u = user(); return u ? 'ab_saved_charts_v1__' + u.id : null; }
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
    ['ab-register-modal', 'ab-open-modal'].forEach(function (id) { var m = el(id); if (m) { m.classList.add('hidden'); } });
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

  // ---- save ---------------------------------------------------------------
  function save() {
    if (!user()) { needRegister(); return; }
    var d = collect();
    if (!d.date || !d.time) { toast('कृपया पहले जन्म-विवरण भरें — तारीख़ व समय आवश्यक हैं।', 'err'); return; }
    var list = load();
    // Same person (name + date + time + place) → update in place, don't duplicate.
    var idx = -1;
    for (var i = 0; i < list.length; i++) {
      if (list[i].name === d.name && list[i].date === d.date && list[i].time === d.time && list[i].place === d.place) { idx = i; break; }
    }
    if (idx >= 0) { d.id = list[idx].id; d.savedAt = Date.now(); list[idx] = d; store(list); toast('चार्ट अपडेट हो गया ✓', 'ok'); return; }
    if (list.length >= LIMIT) {
      toast('अधिकतम ' + LIMIT + ' चार्ट ही सहेजे जा सकते हैं। नया सहेजने हेतु "सहेजे गए चार्ट" में से कुछ हटाएँ।', 'err');
      return;
    }
    d.id = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    d.savedAt = Date.now();
    list.push(d); store(list);
    toast('चार्ट सहेजा गया ✓  (' + list.length + '/' + LIMIT + ')', 'ok');
  }

  // ---- open (browse + search) --------------------------------------------
  function openBrowser() {
    if (!user()) { needRegister(); return; }
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
      return ((c.name || '') + ' ' + (c.place || '') + ' ' + (c.date || '')).toLowerCase().indexOf(ql) >= 0;
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
    global.location.href = '/calc?' + p.toString();   // reopens on the D1 birth-chart page
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
  }

  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', bind); } else { bind(); }

  global.ABSaved = { save: save, open: openBrowser, needRegister: needRegister };
})(window);
