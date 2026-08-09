/* Auto Business — Lal Kitab Varsh Kundali (annual chart) selector.
 *
 * Mirrors the Vedic Varshaphal year-picker for Lal Kitab: an age selector that
 * fetches the rotated Lal-Kitab annual chart + its prediction/remedy/करें-न-करें
 * fragment from the calc/lalkitabVarsh JSON endpoint, draws the annual (fixed-
 * Aries) chart with ABChart.renderNorth and swaps in the server-rendered body.
 *
 * Depends on: northchart.js (ABChart). Reads window.AB_BIRTH + window.AB_LK_AGE.
 *
 * Usage:
 *   ABLalVarsh.init();          // wire the +/- / input / Show controls once
 *   ABLalVarsh.ensureFirst();   // load the native's current-age varsh (lazily)
 *   ABLalVarsh.load(age);       // load a specific age
 */
(function (global) {
  'use strict';

  function sel(x) { return (typeof x === 'string') ? global.document.querySelector(x) : x; }

  var wired = false, initialized = false, curAge = 0;

  function birthQuery(age) {
    var b = global.AB_BIRTH || {};
    return new URLSearchParams({
      age: age,
      bdate: b.date || '', btime: b.time || '',
      blat: b.lat != null ? b.lat : '', blon: b.lon != null ? b.lon : '',
      btz: b.tz != null ? b.tz : '', ayanamsa: b.ayanamsa || 'lahiri'
    }).toString();
  }

  /* ── उसी वर्ष की वैदिक वर्ष-कुंडली, मुंथा सहित ──
     लाल किताब का फल इससे नहीं बनता; यह सिर्फ़ मिलान के लिए बग़ल में रखी है।
     मुंथा वर्षफल के अपने endpoint से आता है और चित्र में 'MUN' बनकर बैठता है —
     वही तरीक़ा जो वर्षफल पन्ना ख़ुद बरतता है, ताकि दोनों जगह एक ही चीज़ दिखे। */
  var vedicBusy = 0;
  function loadVedic(year) {
    var box = sel('#lkv-vedic');
    if (!box || !year) { return; }
    var st = sel('#lkv-vstatus'), yl = sel('#lkv-vyear');
    var dt = sel('#lkv-vdate'), ld = sel('#lkv-vlord');
    if (yl) { yl.textContent = 'सन् ' + year; }
    if (st) { st.textContent = ' · गणना हो रही है…'; }
    var b = global.AB_BIRTH || {};
    var q = new URLSearchParams({
      year: year, bdate: b.date || '', btime: b.time || '',
      blat: b.lat != null ? b.lat : '', blon: b.lon != null ? b.lon : '',
      btz: b.tz != null ? b.tz : '', ayanamsa: b.ayanamsa || 'lahiri'
    });
    var mine = ++vedicBusy;   // देर से लौटा पुराना जवाब नया चित्र न मिटाए
    fetch('/calc/varshaphal?' + q.toString(), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (v) {
        if (mine !== vedicBusy) { return; }
        if (v.error) { if (st) { st.textContent = ' · त्रुटि: ' + v.error; } return; }
        if (st) { st.textContent = ''; }
        if (yl) { yl.textContent = 'सन् ' + v.year; }
        if (dt) { dt.textContent = v.varsha_start || '—'; }
        if (ld) { ld.textContent = (v.varshesh && v.varshesh.lord) ? v.varshesh.lord : '—'; }
        var chart = v.chart;
        if (v.muntha_sign_index != null && chart && chart.planets) {
          chart = { asc_sign: chart.asc_sign, asc_deg: chart.asc_deg, planets: chart.planets.slice() };
          chart.planets.push({ abbr: 'MUN', sign: v.muntha_sign_index, retro: false });
        }
        if (global.ABChart && chart) {
          ABChart.renderNorth(box, chart, { showDeg: true, big: true });
        }
      })
      .catch(function (e) { if (mine === vedicBusy && st) { st.textContent = ' · अनुरोध विफल'; } });
  }

  function load(age) {
    age = parseInt(age, 10) || 1;
    if (age < 1) { age = 1; }
    if (age > 96) { age = 96; }
    var status = sel('#lkv-status'), body = sel('#lkv-body'), chart = sel('#lkv-chart');
    var input = sel('#lkv-age'), agelab = sel('#lkv-agelab');
    if (input) { input.value = age; }
    if (agelab) { agelab.textContent = age; }
    if (status) { status.textContent = 'गणना हो रही है…'; }
    fetch('/calc/lalkitabVarsh?' + birthQuery(age), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (v) {
        if (v.error) { if (status) { status.textContent = 'त्रुटि: ' + v.error; } return; }
        if (status) { status.textContent = ''; }
        curAge = v.age;
        var yl = sel('#lkv-yearlab'); if (yl) { yl.textContent = 'वर्ष ≈ ' + v.year + ' ई.'; }
        if (agelab) { agelab.textContent = v.age; }
        var sum = sel('#lkv-summary'); if (sum) { sum.innerHTML = v.summary_html || ''; }
        var bar = sel('#lkv-bar'); if (bar) { bar.innerHTML = v.bar_html || ''; }
        if (chart && global.ABChart && v.north) {
          ABChart.renderNorth(chart, v.north, { showDeg: false, big: true });
        }
        if (body) { body.innerHTML = v.html || ''; }
        // वही वर्ष वैदिक तरफ़ भी — दोनों कुंडलियाँ हमेशा एक ही साल की रहें।
        // सन् लाल किताब के अपने जवाब से लिया जाता है (जन्म-वर्ष + आयु), अलग से
        // गिना नहीं जाता, वरना दोनों तरफ़ अलग-अलग साल बन सकता है।
        loadVedic(v.year);
        // Reference: the fixed-Aries Lal Kitab janam (teva) chart, shown below.
        var janam = sel('#lkv-janam');
        if (janam && global.ABChart && global.AB_LALKITAB && global.AB_LALKITAB.planets) {
          ABChart.renderNorth(janam, global.AB_LALKITAB, { showDeg: false, big: true });
        }
      })
      .catch(function (e) { if (status) { status.textContent = 'अनुरोध विफल: ' + e; } });
  }

  function ensureFirst() {
    if (initialized) { return; }
    initialized = true;
    var start = (global.AB_LK_AGE && global.AB_LK_AGE > 0) ? global.AB_LK_AGE : 1;
    load(start);
  }

  function curVal() {
    var input = sel('#lkv-age');
    return parseInt((input && input.value) || curAge, 10) || 1;
  }

  function init() {
    if (wired) { return; }
    wired = true;
    var prev = sel('#lkv-prev'), next = sel('#lkv-next'), show = sel('#lkv-show'), input = sel('#lkv-age');
    if (prev) { prev.addEventListener('click', function () { load(curVal() - 1); }); }
    if (next) { next.addEventListener('click', function () { load(curVal() + 1); }); }
    if (show) { show.addEventListener('click', function () { load(curVal()); }); }
    if (input) {
      input.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter') { ev.preventDefault(); load(input.value); }
      });
    }
  }

  global.ABLalVarsh = { init: init, load: load, ensureFirst: ensureFirst };
})(window);
