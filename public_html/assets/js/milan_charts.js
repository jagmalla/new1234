/* Auto Business — Kundali Milan: per-side Save / Open charts.
 *
 * On the मिलान (Milan) page each side — वर (boy) and कन्या (girl) — gets its own
 * Save and Open buttons. Charts are stored in the SAME localStorage bucket the
 * birth-chart page uses (ab_saved_charts_v1__<userId|guest>) and in the SAME
 * entry format, so a chart saved here shows up on the birth-chart "Saved Charts"
 * window and vice-versa. Opening fills that side's fields in place (it does NOT
 * navigate away), so two different saved kundalis can be loaded side-by-side.
 *
 * Buttons opt in with data-mlk-save="boy|girl" / data-mlk-open="boy|girl".
 */
(function (global) {
  'use strict';

  var doc = global.document;
  var LIMIT = 200;

  function user() { return global.AB_USER || null; }
  function keyFor() { var u = user(); return 'ab_saved_charts_v1__' + (u ? u.id : 'guest'); }
  function load() {
    try { var a = JSON.parse(global.localStorage.getItem(keyFor()) || '[]'); return Array.isArray(a) ? a : []; }
    catch (e) { return []; }
  }
  function store(a) { try { global.localStorage.setItem(keyFor(), JSON.stringify(a)); } catch (e) {} }
  function byId(id) { var l = load(); for (var i = 0; i < l.length; i++) { if (l[i].id === id) { return l[i]; } } return null; }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function el(id) { return doc.getElementById(id); }

  // side = which person the current Save/Open action targets ('boy'|'girl').
  var side = 'boy';

  function fieldEl(s, n) { return doc.querySelector('[name="' + s + '_' + n + '"]'); }
  function fval(s, n) { var e = fieldEl(s, n); return e ? String(e.value || '').trim() : ''; }
  function fset(s, n, v) { var e = fieldEl(s, n); if (e) { e.value = v == null ? '' : v; } }

  // Collect one side's birth details into the shared chart-entry shape.
  function collect(s) {
    return {
      name: fval(s, 'name'), gender: '', date: fval(s, 'date'), time: fval(s, 'time'),
      place: fval(s, 'place'), lat: fval(s, 'lat'), lon: fval(s, 'lon'),
      tz: fval(s, 'tz') || '5:30', ayanamsa: 'lahiri'
    };
  }

  // Fill one side's form fields from a stored entry (in place, no navigation).
  function fill(s, c) {
    ['name', 'date', 'time', 'place', 'lat', 'lon', 'tz'].forEach(function (n) { fset(s, n, c[n]); });
  }

  // ---- overlays / toast ---------------------------------------------------
  function open(id) { var m = el(id); if (m) { m.classList.remove('hidden'); doc.body.style.overflow = 'hidden'; } }
  function closeAll() {
    ['mlk-open-modal', 'mlk-save-modal'].forEach(function (id) { var m = el(id); if (m) { m.classList.add('hidden'); } });
    doc.body.style.overflow = '';
  }
  var toastT;
  function toast(msg, kind) {
    var t = el('mlk-toast'); if (!t) { return; }
    t.textContent = msg; t.className = 'mlk-toast mlk-toast-' + (kind || 'ok');
    clearTimeout(toastT); toastT = setTimeout(function () { t.classList.add('hidden'); }, 3200);
  }

  function sideLabel(s) { return s === 'girl' ? 'कन्या' : 'वर'; }

  // ---- save (profile popup) ----------------------------------------------
  function findExisting(list, d) {
    for (var i = 0; i < list.length; i++) {
      if (list[i].name === d.name && list[i].date === d.date && list[i].time === d.time && list[i].place === d.place) { return i; }
    }
    return -1;
  }

  // location is present if we have a place name OR usable coordinates.
  function hasLoc(d) { return !!(d.place || (d.lat && d.lon)); }

  function save(s) {
    side = s;
    var d = collect(s);
    if (!d.date || !d.time || !hasLoc(d)) {
      toast(sideLabel(s) + ' के जन्म-विवरण भरें — तारीख़, समय व जन्म-स्थान (या अक्षांश/देशांतर) आवश्यक हैं।', 'err');
      return;
    }
    var list = load();
    var pi = findExisting(list, d);
    var prev = pi >= 0 ? list[pi] : null;
    var setv = function (id, v) { var e = el(id); if (e) { e.value = v || ''; } };
    var sum = el('mlk-save-sum');
    if (sum) {
      sum.innerHTML = '<b>' + esc(sideLabel(s)) + ' — जन्म-विवरण:</b> ' + esc(d.date) + ' &nbsp;' + esc(d.time) +
        ' &nbsp;·&nbsp; ' + esc(d.place) +
        '<br><span style="font-size:.74rem;color:#94a3b8">(बदलने हेतु ऊपर फ़ॉर्म में सुधार करें)</span>';
    }
    setv('mlk-sv-name', d.name || (prev && prev.name) || '');
    setv('mlk-sv-phone', prev ? prev.phone : '');
    setv('mlk-sv-email', prev ? prev.email : '');
    setv('mlk-sv-address', prev ? prev.address : '');
    setv('mlk-sv-city', (prev && prev.city) || String(d.place || '').split(',')[0].trim());
    setv('mlk-sv-country', prev ? prev.country : '');
    var err = el('mlk-save-err'); if (err) { err.classList.add('hidden'); }
    open('mlk-save-modal');
    var nm = el('mlk-sv-name'); if (nm) { setTimeout(function () { nm.focus(); }, 60); }
  }

  function confirmSave() {
    var gv = function (id) { var e = el(id); return e ? String(e.value || '').trim() : ''; };
    var err = el('mlk-save-err');
    var fail = function (msg) { if (err) { err.textContent = msg; err.classList.remove('hidden'); } };

    var d = collect(side);
    d.name = gv('mlk-sv-name');
    if (!d.name) { fail('नाम आवश्यक है — कृपया नाम भरें।'); var e = el('mlk-sv-name'); if (e) { e.focus(); } return; }
    if (!d.date || !d.time || !hasLoc(d)) { fail('जन्म-तिथि, समय व स्थान (या अक्षांश/देशांतर) आवश्यक हैं — पहले फ़ॉर्म भरें।'); return; }
    var email = gv('mlk-sv-email');
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { fail('Email ID सही प्रारूप में नहीं है।'); return; }
    d.phone = gv('mlk-sv-phone'); d.email = email; d.address = gv('mlk-sv-address');
    d.city = gv('mlk-sv-city'); d.country = gv('mlk-sv-country');

    // reflect the entered name back into the milan form field for this side
    fset(side, 'name', d.name);

    var list = load();
    var idx = findExisting(list, d);
    if (idx >= 0) {
      d.id = list[idx].id; d.savedAt = Date.now(); list[idx] = d; store(list);
      closeAll(); toast(sideLabel(side) + ' का चार्ट अपडेट हो गया ✓', 'ok'); return;
    }
    if (list.length >= LIMIT) { fail('अधिकतम ' + LIMIT + ' चार्ट ही सहेजे जा सकते हैं।'); return; }
    d.id = 'c' + Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
    d.savedAt = Date.now();
    list.push(d); store(list);
    closeAll();
    toast(sideLabel(side) + ' का चार्ट सहेजा गया ✓  (' + list.length + '/' + LIMIT + ')', 'ok');
  }

  // ---- open (browse + search + fill in place) -----------------------------
  function openBrowser(s) {
    side = s;
    var title = el('mlk-open-title'); if (title) { title.textContent = '📂 ' + sideLabel(s) + ' — सहेजे गए चार्ट खोलें'; }
    var q = el('mlk-open-q'); if (q) { q.value = ''; }
    renderList('');
    open('mlk-open-modal');
    if (q) { setTimeout(function () { q.focus(); }, 60); }
  }

  function renderList(query) {
    var host = el('mlk-open-list'); if (!host) { return; }
    var list = load().slice().sort(function (a, b) { return (b.savedAt || 0) - (a.savedAt || 0); });
    var cnt = el('mlk-open-count'); if (cnt) { cnt.textContent = '(' + list.length + '/' + LIMIT + ')'; }
    var ql = String(query || '').toLowerCase().trim();
    var f = ql ? list.filter(function (c) {
      return ((c.name || '') + ' ' + (c.place || '') + ' ' + (c.date || '') + ' ' +
              (c.phone || '') + ' ' + (c.email || '') + ' ' + (c.city || '') + ' ' +
              (c.country || '')).toLowerCase().indexOf(ql) >= 0;
    }) : list;
    if (!f.length) {
      host.innerHTML = '<div class="mlk-open-empty">' +
        (list.length ? 'इस खोज से कोई चार्ट नहीं मिला।' : 'अभी कोई चार्ट सहेजा नहीं गया। ऊपर विवरण भरकर “Save” दबाएँ।') +
        '</div>';
      return;
    }
    host.innerHTML = f.map(function (c) {
      var when = c.savedAt ? new Date(c.savedAt).toLocaleDateString() : '';
      return '<div class="mlk-open-row">' +
        '<div class="mlk-open-main">' +
          '<div class="mlk-open-name">' + esc(c.name || '—') + '</div>' +
          '<div class="mlk-open-meta">' + esc(c.date || '') + '  ' + esc(c.time || '') + (c.place ? '  ·  ' + esc(c.place) : '') + '</div>' +
          ((c.phone || c.email || c.city) ? '<div class="mlk-open-meta">' +
            [c.phone ? '📞 ' + esc(c.phone) : '', c.email ? '✉ ' + esc(c.email) : '', c.city ? '🏙 ' + esc(c.city) + (c.country ? ', ' + esc(c.country) : '') : '']
              .filter(Boolean).join('  ·  ') + '</div>' : '') +
        '</div>' +
        '<div class="mlk-open-acts">' +
          '<button type="button" class="mlk-btn mlk-btn-sm" data-mlk-load="' + esc(c.id) + '">इस ओर खोलें</button>' +
          '<button type="button" class="mlk-btn mlk-btn-ghost mlk-btn-sm" data-mlk-del="' + esc(c.id) + '" title="हटाएँ">✕</button>' +
        '</div>' +
        (when ? '<div class="mlk-open-when">' + esc(when) + '</div>' : '') +
        '</div>';
    }).join('');
  }

  function loadInto(id) {
    var c = byId(id); if (!c) { return; }
    fill(side, c);
    closeAll();
    toast(sideLabel(side) + ' में “' + (c.name || 'चार्ट') + '” भर दिया — अब “मिलान करें” दबाएँ।', 'ok');
  }

  function del(id) {
    var list = load().filter(function (c) { return c.id !== id; });
    store(list); renderList((el('mlk-open-q') || {}).value || '');
    toast('चार्ट हटा दिया गया।', 'ok');
  }

  // ---- wiring (delegated) -------------------------------------------------
  function bind() {
    doc.addEventListener('click', function (e) {
      var sv = e.target.closest('[data-mlk-save]'); if (sv) { e.preventDefault(); save(sv.getAttribute('data-mlk-save')); return; }
      var op = e.target.closest('[data-mlk-open]'); if (op) { e.preventDefault(); openBrowser(op.getAttribute('data-mlk-open')); return; }
      var ld = e.target.closest('[data-mlk-load]'); if (ld) { e.preventDefault(); loadInto(ld.getAttribute('data-mlk-load')); return; }
      var dl = e.target.closest('[data-mlk-del]'); if (dl) { e.preventDefault(); del(dl.getAttribute('data-mlk-del')); return; }
      if (e.target.closest('[data-mlk-close]') || e.target.classList.contains('mlk-modal-overlay')) { closeAll(); return; }
    });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeAll(); } });
    var q = el('mlk-open-q'); if (q) { q.addEventListener('input', function () { renderList(this.value); }); }
    var cf = el('mlk-save-confirm'); if (cf) { cf.addEventListener('click', confirmSave); }
    var sm = el('mlk-save-modal');
    if (sm) { sm.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); confirmSave(); } }); }
  }

  if (doc.readyState === 'loading') { doc.addEventListener('DOMContentLoaded', bind); } else { bind(); }

  global.ABMilanCharts = { save: save, open: openBrowser };
})(window);
