/* Auto Business — on-demand English translation of PREDICTION TEXT only.
 *
 * The app's predictions are authored in Hindi. This module translates just the
 * prediction panes into English on demand (via a free online translation
 * service) while leaving the whole UI — buttons, menu, dropdowns, window titles
 * — untouched. Selecting हिन्दी restores the original Hindi instantly.
 *
 * Scope: only the containers listed in ROOTS (the prediction/phal areas). Each
 * Hindi text node's original is kept, so switching back is exact and repeat
 * switches are instant (cached). Requires internet on the live site; if the
 * service is unreachable it falls back to Hindi and shows a short notice.
 */
(function (global) {
  'use strict';
  var doc = global.document;

  // Only prediction / phal containers — never the UI chrome.
  var ROOTS = [
    '#pred-scroll',            // D1 birth-chart predictions
    '#planet-detail-pane', '#house-detail-pane', '#karaka-detail-pane',
    '#gochar-phal', '#gochar-detail-pane',   // Gochar predictions
    '#vp-pred-scroll',         // Varshaphal predictions
    '#milan-content'           // Kundali Milan result text
  ];

  var CACHE = {};                 // hi string -> en string (session)
  var ORIG = new WeakMap();       // text node -> original hi value
  var state = 'hi';
  var busy = false;
  var refreshT;

  function roots() { var o = []; ROOTS.forEach(function (s) { var e = doc.querySelector(s); if (e) { o.push(e); } }); return o; }

  function textNodes(root) {
    var w = doc.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (n) {
        var v = n.nodeValue;
        if (!v || !v.trim()) { return NodeFilter.FILTER_REJECT; }
        if (!/[ऀ-ॿ]/.test(v)) { return NodeFilter.FILTER_REJECT; }   // must contain Devanagari
        var p = n.parentNode;
        if (p) { var t = p.nodeName; if (t === 'SCRIPT' || t === 'STYLE' || t === 'SELECT' || t === 'OPTION' || t === 'TEXTAREA') { return NodeFilter.FILTER_REJECT; } }
        return NodeFilter.FILTER_ACCEPT;
      }
    });
    var a = [], n; while ((n = w.nextNode())) { a.push(n); }
    return a;
  }

  function chunk(a, n) { var o = []; for (var i = 0; i < a.length; i += n) { o.push(a.slice(i, i + n)); } return o; }

  // Batch-translate uncached strings via the free gtx endpoint.
  function fetchTranslations(strings) {
    var todo = strings.filter(function (s) { return !(s in CACHE); });
    if (!todo.length) { return Promise.resolve(); }
    var batches = chunk(todo, 30);
    return Promise.all(batches.map(function (b) {
      var qs = b.map(function (s) { return 'q=' + encodeURIComponent(s); }).join('&');
      var url = 'https://translate.googleapis.com/translate_a/t?client=gtx&sl=hi&tl=en&' + qs;
      return fetch(url).then(function (r) { return r.json(); }).then(function (data) {
        // Multiple q -> [["en1"],["en2"],…]; single q -> ["en1"].
        var arr = (b.length === 1) ? [data] : data;
        b.forEach(function (s, i) {
          var seg = arr[i];
          var en = Array.isArray(seg) ? seg[0] : seg;
          CACHE[s] = (typeof en === 'string' && en) ? en : s;
        });
      });
    }));
  }

  function applyEnglish() {
    var nodes = [];
    roots().forEach(function (r) { nodes = nodes.concat(textNodes(r)); });
    if (!nodes.length) { return Promise.resolve(); }
    var strings = [];
    nodes.forEach(function (n) {
      if (!ORIG.has(n)) { ORIG.set(n, n.nodeValue); }
      var s = ORIG.get(n).trim();
      if (strings.indexOf(s) < 0) { strings.push(s); }
    });
    return fetchTranslations(strings).then(function () {
      nodes.forEach(function (n) {
        var orig = ORIG.get(n); var s = orig.trim(); var en = CACHE[s];
        if (en && en !== s) { n.nodeValue = orig.replace(s, en); }
      });
    });
  }

  function restoreHindi() {
    roots().forEach(function (r) {
      var w = doc.createTreeWalker(r, NodeFilter.SHOW_TEXT, null);
      var n; while ((n = w.nextNode())) { if (ORIG.has(n)) { n.nodeValue = ORIG.get(n); } }
    });
  }

  function notice(msg) {
    if (global.ABSaved && msg) { /* reuse toast if present */ }
    var t = doc.getElementById('ab-toast');
    if (t && msg) { t.textContent = msg; t.className = 'ab-toast ab-toast-err'; setTimeout(function () { t.classList.add('hidden'); }, 3500); }
  }

  function setLang(lang) {
    state = (lang === 'en') ? 'en' : 'hi';
    try { global.localStorage.setItem('ab_pred_lang', state); } catch (e) {}
    if (state === 'en') {
      if (busy) { return; } busy = true;
      applyEnglish().then(function () { busy = false; }).catch(function () {
        busy = false; restoreHindi();
        notice('English translation unavailable right now (needs internet). Showing Hindi.');
      });
    } else {
      restoreHindi();
    }
  }

  // Re-apply after prediction panes are (re)rendered by AJAX, if English is on.
  function refresh() { if (state === 'en' && !busy) { busy = true; applyEnglish().then(function () { busy = false; }).catch(function () { busy = false; }); } }

  global.ABTranslate = { setLang: setLang, refresh: refresh, lang: function () { return state; } };

  // Restore the user's last choice on load (after predictions are in the DOM),
  // and keep English applied when panes re-render via AJAX.
  doc.addEventListener('DOMContentLoaded', function () {
    if (global.MutationObserver) {
      var mo = new MutationObserver(function () {
        if (state === 'en' && !busy) { clearTimeout(refreshT); refreshT = setTimeout(refresh, 300); }
      });
      roots().forEach(function (r) { try { mo.observe(r, { childList: true, subtree: true }); } catch (e) {} });
    }
    var saved = null; try { saved = global.localStorage.getItem('ab_pred_lang'); } catch (e) {}
    if (saved === 'en') { setTimeout(function () { setLang('en'); }, 400); }
  });
})(window);
