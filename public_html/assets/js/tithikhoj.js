/* 🔍 तिथि खोज + 📋 कार्य-मुहूर्त जाँच — मालिक की नियम-पुस्तिका (87 कार्य) से।
 *
 * markup व शैली: app/Http/views/_tithi_khoj.php · सूची: TithiKhojCatalog
 * खोज:  GET /calc/karyaScan  — अवधि के हर दिन का दर्जा + शुभ समय-खिड़कियाँ
 * जाँच: GET /calc/karyaCheck — एक तिथि/समय पर हर नियम की पंक्ति (✔ ★ ⚠ ✘ ℹ)
 * नियम: GET /calc/karyaInfo  — कार्य का नियम-पत्र
 *
 * दो जगह काम आता है: (1) मुहूर्त › तिथि खोज (ABTithiKhoj.ensure), और
 * (2) मुहूर्त फल की "कार्य-मुहूर्त जाँच" श्रेणी (ABTithiKhoj.mountPhal) — वहाँ
 * गोचर-फ़ॉर्म की तिथि/समय/स्थान पर वही जाँच चलती है।
 */
(function () {
  'use strict';

  var MONTHS = ['जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितम्बर', 'अक्टूबर', 'नवम्बर', 'दिसम्बर'];
  var WD = ['र', 'सो', 'मं', 'बु', 'गु', 'शु', 'श'];
  var STORE = 'ab_tithikhoj2';
  var ICON = { ok: '✔', good: '★', warn: '⚠', bad: '✘', info: 'ℹ' };
  var GROUPS = ['काल', 'तिथि', 'वार', 'नक्षत्र', 'योग-करण', 'विशेष योग', 'लग्न', 'समय', 'दिशा', 'व्यक्तिगत', 'पर्व'];
  var GROUP_HI = { 'काल': '🗓️ मास / वर्जित काल', 'तिथि': '🌙 पक्ष व तिथि', 'वार': '📅 वार', 'नक्षत्र': '⭐ नक्षत्र',
    'योग-करण': '🔗 योग व करण (भद्रा)', 'विशेष योग': '✨ विशेष योग', 'लग्न': '↗️ लग्न-शुद्धि', 'समय': '🕐 दिन का समय',
    'दिशा': '🧭 दिशा / वास', 'व्यक्तिगत': '👤 व्यक्तिगत बल', 'पर्व': '🌟 अबूझ / पर्व' };
  var TXT = [['shubh_tithi', 'शुभ तिथि'], ['varjit_tithi', 'वर्जित तिथि'], ['tithi_note', 'तिथि टिप्पणी'],
    ['shubh_vaar', 'शुभ वार'], ['varjit_vaar', 'वर्जित वार'], ['vaar_note', 'वार टिप्पणी'],
    ['shubh_nak', 'शुभ नक्षत्र'], ['varjit_nak', 'वर्जित नक्षत्र'], ['nak_note', 'नक्षत्र टिप्पणी'],
    ['shubh_lagna', 'शुभ लग्न'], ['lagna_shuddhi', 'लग्न व ग्रह शुद्धि'], ['kaal', 'शुभ मास / काल / समय'],
    ['varjit_kaal', 'वर्जित काल / दोष'], ['yoga_karan', 'योग-करण नियम'], ['apvad', 'अपवाद / विशेष नियम'],
    ['abujh', 'अबूझ / उत्तम पर्व'], ['matbhed', 'मतभेद / स्रोत']];

  var built = false, data = null, seq = 0, last = null, phalSeq = 0;
  var pref = { c: '', s: '', v: 'list', o: 'score', personal: '1', opts: {}, place: null };

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }
  function two(n) { return (n < 10 ? '0' : '') + n; }
  function fmt(d) { return two(d.getDate()) + '-' + two(d.getMonth() + 1) + '-' + d.getFullYear(); }
  function iso(d) { return d.getFullYear() + '-' + two(d.getMonth() + 1) + '-' + two(d.getDate()); }
  // आज से n महीने — महीना पहले जोड़ा, दिन बाद में उस महीने की लम्बाई पर काटा
  // (वही साँचा जो PHP में है; 31-01 से एक माह = 28/29-02, 3 मार्च नहीं)।
  function plusMonths(n) {
    var now = new Date(), day = now.getDate();
    var end = new Date(now.getFullYear(), now.getMonth() + n + 1, 0);
    return new Date(end.getFullYear(), end.getMonth(), Math.min(day, end.getDate()));
  }
  // DD-MM-YYYY → Date; असम्भव तिथि (31-02) → null — सर्वर ग़लत तिथि को चुपचाप
  // 1 जनवरी पढ़ लेता है, इसलिए जाँच यहीं होती है।
  function parse(s) {
    var m = /^\s*(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\s*$/.exec(s || '');
    if (!m) { return null; }
    var d = new Date(+m[3], +m[2] - 1, +m[1]);
    return (d.getFullYear() === +m[3] && d.getMonth() === +m[2] - 1 && d.getDate() === +m[1]) ? d : null;
  }
  function dayNum(d) { return Math.round(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 864e5); }
  function tone(t) { return t === 'pos' ? 'g' : (t === 'info' ? 'y' : 'r'); }

  function load() {
    try {
      var p = JSON.parse(window.localStorage.getItem(STORE) || '{}');
      ['c', 's', 'v', 'o', 'personal'].forEach(function (k) { if (typeof p[k] === 'string') { pref[k] = p[k]; } });
      if (p.opts && typeof p.opts === 'object') { pref.opts = p.opts; }
      if (p.place && typeof p.place === 'object' && isFinite(+p.place.lat)) { pref.place = p.place; }
    } catch (e) { /* निजी विंडो — पहले से तय मान ही रहें */ }
    if (pref.v !== 'cal') { pref.v = 'list'; }
    if (pref.o !== 'date') { pref.o = 'score'; }
  }
  function save() { try { window.localStorage.setItem(STORE, JSON.stringify(pref)); } catch (e) { /* निजी विंडो */ } }

  function getData() {
    if (!data) {
      try { data = JSON.parse(($('tk-data') || {}).textContent || '{}'); } catch (e) { data = {}; }
      data.cats = data.cats || []; data.options = data.options || {};
    }
    return data;
  }
  function findCat(k) { var c = getData().cats; for (var i = 0; i < c.length; i++) { if (c[i].key === k) { return c[i]; } } return null; }
  function findSub(c, k) {
    if (!c || !k) { return null; }
    for (var i = 0; i < c.subs.length; i++) { if (c.subs[i].key === k) { return c.subs[i]; } }
    return null;
  }
  function findTask(id) {
    var cats = getData().cats;
    for (var i = 0; i < cats.length; i++) { var s = findSub(cats[i], id); if (s) { return { cat: cats[i], sub: s }; } }
    return null;
  }

  // ── कार्य-चयन (दोनों जगह साझा) ──
  function fillSubs(sel, catKey, subKey) {
    var c = findCat(catKey);
    var h = '<option value="">' + (c ? '— कार्य चुनें (' + c.subs.length + ') —' : '— पहले मुख्य श्रेणी चुनें —') + '</option>';
    if (c) {
      c.subs.forEach(function (s, i) { h += '<option value="' + esc(s.key) + '">' + (i + 1) + '. ' + esc(s.label) + '</option>'; });
    }
    sel.innerHTML = h;
    sel.disabled = !c;
    sel.value = findSub(c, subKey) ? subKey : '';
  }
  // कार्य के विकल्प (दिशा, क्रय/विक्रय …) — चुने मान याद रहते हैं
  function renderOpts(host, sub, values, onChange) {
    host.innerHTML = '';
    if (!sub) { return; }
    sub.opts.forEach(function (k) {
      var def = getData().options[k];
      if (!def) { return; }
      var wrap = document.createElement('div');
      wrap.className = 'tk-fld tk-wide';
      var h = '<label>' + esc(def.label) + '</label><select data-opt="' + esc(k) + '">';
      Object.keys(def.choices).forEach(function (v) {
        h += '<option value="' + esc(v) + '"' + ((values[k] || '') === v ? ' selected' : '') + '>' + esc(def.choices[v]) + '</option>';
      });
      wrap.innerHTML = h + '</select>';
      host.appendChild(wrap);
    });
    host.querySelectorAll('select[data-opt]').forEach(function (s) {
      s.addEventListener('change', function () { values[s.getAttribute('data-opt')] = s.value; onChange(); });
      values[s.getAttribute('data-opt')] = s.value;
    });
  }
  function optQuery(sub, values) {
    var q = '';
    (sub ? sub.opts : []).forEach(function (k) { q += '&o_' + encodeURIComponent(k) + '=' + encodeURIComponent(values[k] || ''); });
    return q;
  }
  function birthQuery(on) {
    var b = window.AB_BIRTH || {};
    var q = '&personal=' + (on ? '1' : '0') + '&ayanamsa=' + encodeURIComponent(b.ayanamsa || 'lahiri');
    if (on && b.date) {
      q += '&bdate=' + encodeURIComponent(b.date) + '&btime=' + encodeURIComponent(b.time || '12:00') +
        '&blat=' + encodeURIComponent(b.lat) + '&blon=' + encodeURIComponent(b.lon) + '&btz=' + encodeURIComponent(b.tz) +
        '&gender=' + encodeURIComponent(window.AB_GENDER || '');
    }
    return q;
  }

  // ── नियम-पत्र ──
  var infoCache = {};
  function getInfo(id) {
    if (infoCache[id]) { return Promise.resolve(infoCache[id]); }
    return fetch('/calc/karyaInfo?task=' + encodeURIComponent(id), { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { if (d && d.ok) { infoCache[id] = d.info; return d.info; } return null; });
  }
  function infoHtml(info) {
    if (!info) { return ''; }
    var c = info.category || {};
    var h = '<table class="tk-rt">';
    h += '<tr><th>श्रेणी-सिद्धांत</th><td>' + esc(c.emoji + ' ' + c.name) + ' — ' + esc(c.siddhant) +
      '<br><span style="color:#6b7280">प्रधान बल: ' + esc(c.pradhan_bal) + ' · भाव: ' + esc(c.bhav) + ' · कारक: ' + esc(c.karak) +
      ' · मुख्य वर्जित: ' + esc(c.varjit) + '</span></td></tr>';
    TXT.forEach(function (p) { var v = (info.txt || {})[p[0]]; if (v) { h += '<tr><th>' + p[1] + '</th><td>' + esc(v) + '</td></tr>'; } });
    var ex = info.extra || {};
    Object.keys(ex).forEach(function (k) {
      var v = ex[k];
      var txt = Array.isArray(v) ? v.join(' • ') : (typeof v === 'object' ? Object.keys(v).map(function (x) { return v[x]; }).filter(Boolean).join(' · ') : v);
      h += '<tr><th>' + esc(k) + '</th><td>' + esc(txt) + '</td></tr>';
    });
    h += '</table>';
    if (info.note) { h += '<div class="tk-rnote">📝 ' + esc(info.note) + '</div>'; }
    if ((info.txt || {}).varjit_kaal && /सा\.दो\./.test(info.txt.varjit_kaal)) { h += '<div class="tk-keyl">सा.दो. = ' + esc(info.sado.replace(/^सा\.दो\.\s*/, '')) + '</div>'; }
    return h;
  }

  // ── एक तिथि/क्षण की पूरी जाँच — दोनों जगह यही ──
  function checkHtml(d, withInfo) {
    if (!d || !d.ok) { return '<div class="tk-none">⚠️ ' + esc((d && d.error) || 'जाँच नहीं हो सकी।') + '</div>'; }
    var v = d.verdict, cl = tone(v.tone);
    var h = '<div class="tk-v ' + cl + '"><div class="tk-v-h">' + esc(d.date) + ' <span class="tk-wd">' + esc(d.weekday) +
      '</span> · 🕐 ' + esc(d.time) + ' <span class="tk-gr ' + cl + '">' + esc(v.grade) + '</span><span class="tk-sc">' + v.score + '/100</span></div>';
    if (v.abujh) { h += '<div class="tk-v-l">🌟 <b>अबूझ: ' + esc(v.abujh) + '</b></div>'; }
    if (v.bad.length) { h += '<div class="tk-v-l">✘ <b>बाधा:</b> ' + v.bad.map(esc).join(' · ') + '</div>'; }
    if (v.warn.length) { h += '<div class="tk-v-l">⚠ <b>सावधानी:</b> ' + v.warn.map(esc).join(' · ') + '</div>'; }
    if (v.good.length) { h += '<div class="tk-v-l">★ <b>बल:</b> ' + v.good.map(esc).join(' · ') + '</div>'; }
    h += '</div>';
    var day = d.day || {};
    if (day.windows && day.windows.length) {
      h += '<div class="tk-cgrp"><div class="tk-cgrp-h">🕐 इस दिन के शुभ समय</div>' + day.windows.map(winHtml).join('') + '</div>';
    } else if (day.reason) {
      h += '<div class="tk-cgrp"><div class="tk-cgrp-h">🕐 इस दिन</div><div class="tk-rs">' + esc(day.reason) + '</div></div>';
    }
    if (day.fest && day.fest.length) { h += '<div>' + day.fest.map(function (f) { return '<span class="tk-fest">' + esc(f) + '</span>'; }).join('') + '</div>'; }
    var byG = {};
    (v.checks || []).forEach(function (c) { (byG[c.g] = byG[c.g] || []).push(c); });
    h += '<div class="tk-cg2">';
    GROUPS.forEach(function (g) {
      if (!byG[g]) { return; }
      h += '<div class="tk-cgrp"><div class="tk-cgrp-h">' + (GROUP_HI[g] || esc(g)) + '</div>';
      byG[g].forEach(function (c) {
        h += '<div class="tk-ci ' + c.s + '"><span class="i">' + ICON[c.s] + '</span><span class="l"><b>' + esc(c.l) + '</b> <span>' + esc(c.v) + '</span></span>' +
          '<span class="p">' + (c.p > 0 ? '+' + c.p : (c.p < 0 ? c.p : '')) + '</span></div>';
      });
      h += '</div>';
    });
    h += '</div>';
    if (d.panchang) {
      h += '<details class="tk-more"><summary>📖 इस दिन का पंचांग व काल</summary><table class="tk-pt">';
      Object.keys(d.panchang).forEach(function (k) { h += '<tr><th>' + esc(k) + '</th><td>' + esc(d.panchang[k]) + '</td></tr>'; });
      h += '</table></details>';
    }
    if (withInfo && d.info) { h += '<details class="tk-more"><summary>📜 इस कार्य के नियम</summary>' + infoHtml(d.info) + '</details>'; }
    return h;
  }

  function winHtml(w, i) {
    return '<div class="tk-win"><div class="tk-win-t">' + (i === 0 ? '⭐ ' : '') + esc(w.from) + '–' + esc(w.to) +
      ' <small>(' + w.mins + ' मि.) · ' + esc(w.grade) + ' ' + w.score + '</small></div>' +
      '<div class="tk-win-m">↗️ ' + esc(w.lagna) + ' लग्न · ⭐ ' + esc(w.nak) + ' · 🌙 ' + esc(w.tithi) + '</div>' +
      '<div>' + (w.good || []).map(function (g) { return '<span class="tk-tag g">★ ' + esc(g) + '</span>'; }).join('') +
      (w.warn || []).map(function (g) { return '<span class="tk-tag w">⚠ ' + esc(g) + '</span>'; }).join('') + '</div></div>';
  }

  // ════════════ तिथि खोज ════════════
  function current() { var c = findCat($('tk-cat').value); return { cat: c, sub: findSub(c, $('tk-sub').value) }; }

  function onTaskChange() {
    var cur = current(), go = $('tk-go'), hint = $('tk-go-hint'), rules = $('tk-rules');
    hint.classList.remove('err');
    pref.opts = pref.opts || {};
    renderOpts($('tk-opts'), cur.sub, pref.opts, save);
    if (!cur.sub) {
      go.disabled = true;
      hint.textContent = cur.cat ? 'अब कार्य चुनें' : 'पहले ऊपर कार्य चुनें';
      rules.hidden = true;
      return;
    }
    go.disabled = false;
    hint.textContent = '';
    rules.hidden = false;
    $('tk-rules-b').innerHTML = '<div class="tk-wait">⏳ नियम ला रहे…</div>';
    getInfo(cur.sub.key).then(function (info) {
      if (current().sub === cur.sub) { $('tk-rules-b').innerHTML = infoHtml(info); }
    }).catch(function () { $('tk-rules-b').innerHTML = ''; });
  }

  function markTab(btn) {
    document.querySelectorAll('#card-tithikhoj .tk-tab').forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  function placeQuery() {
    return '&lat=' + encodeURIComponent($('tk-lat').value) + '&lon=' + encodeURIComponent($('tk-lon').value) +
      '&tz=' + encodeURIComponent($('tk-tz').value) + '&zone=' + encodeURIComponent($('tk-zone').value);
  }

  function search() {
    var cur = current(), hint = $('tk-go-hint');
    if (!cur.sub) { return; }
    var f = parse($('tk-from').value), t = parse($('tk-to').value);
    var bad = function (msg) { hint.textContent = '⚠️ ' + msg; hint.classList.add('err'); };
    if (!f) { bad('प्रारम्भ तिथि DD-MM-YYYY में भरें (जैसे ' + fmt(new Date()) + ')।'); return; }
    if (!t) { bad('अन्तिम तिथि DD-MM-YYYY में भरें।'); return; }
    if (dayNum(t) < dayNum(f)) { bad('अन्तिम तिथि प्रारम्भ तिथि से पहले है।'); return; }
    if (!isFinite(parseFloat($('tk-lat').value))) { bad('कार्य-स्थान चुनें।'); return; }
    hint.textContent = '';
    hint.classList.remove('err');
    var personal = $('tk-personal').checked;
    var ctx = { cat: cur.cat, sub: cur.sub, from: fmt(f), to: fmt(t), place: $('tk-place').value, personal: personal };
    var url = '/calc/karyaScan?task=' + encodeURIComponent(cur.sub.key) + '&from=' + ctx.from + '&to=' + ctx.to +
      placeQuery() + optQuery(cur.sub, pref.opts) + birthQuery(personal);
    var go = $('tk-go'), res = $('tk-results'), my = ++seq;
    go.disabled = true;
    go.textContent = '⏳ खोज रहे…';
    var span = dayNum(t) - dayNum(f) + 1;
    res.innerHTML = '<div class="tk-card"><div class="tk-wait">⏳ ' + esc(ctx.from) + ' से ' + esc(ctx.to) + ' तक ' + span +
      ' दिनों का हर पल जाँच रहे हैं (तिथि, नक्षत्र, योग, करण, लग्न, राहुकाल …)' + (span > 120 ? ' — साल भर में कुछ सेकंड लगते हैं' : '') + '…</div></div>';
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (my !== seq) { return; }
        last = { d: d, ctx: ctx };
        render();
        var top = res.getBoundingClientRect().top;
        if (top > window.innerHeight - 120) { res.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      })
      .catch(function () {
        if (my !== seq) { return; }
        res.innerHTML = '<div class="tk-card"><div class="tk-none">⚠️ खोज पूरी नहीं हो सकी — इंटरनेट/सर्वर जाँचकर फिर से दबाएँ।</div></div>';
      })
      .then(function () { if (my === seq) { go.disabled = !current().sub; go.textContent = '🔍 शुभ मुहूर्त-तिथि खोजें'; } });
  }

  function seg(kind, val, label) {
    var on = pref[kind] === val;
    return '<button type="button" data-tk' + kind + '="' + val + '"' + (on ? ' class="on"' : '') +
      ' aria-pressed="' + (on ? 'true' : 'false') + '">' + label + '</button>';
  }

  function dayCard(r) {
    var cl = tone(r.tone);
    var h = '<div class="tk-day ' + cl + '" data-tkrow="' + esc(r.date) + '"><div class="tk-dh">📅 ' + esc(r.date) +
      ' <span class="tk-wd">' + esc(r.weekday) + '</span> <span class="tk-gr ' + cl + '">' + esc(r.grade) + '</span>' +
      '<span class="tk-sc">' + r.score + '/100</span></div>';
    if (r.abujh) { h += '<span class="tk-fest">🌟 अबूझ: ' + esc(r.abujh) + '</span>'; }
    (r.fest || []).forEach(function (f) { if (f !== r.abujh) { h += '<span class="tk-fest">' + esc(f) + '</span>'; } });
    if (r.windows && r.windows.length) {
      h += winHtml(r.windows[0], 0);
      if (r.windows.length > 1) {
        h += '<div class="tk-alt">अन्य समय: ' + r.windows.slice(1).map(function (w) {
          return esc(w.from) + '–' + esc(w.to) + ' (' + esc(w.lagna) + ', ' + esc(w.grade) + ')';
        }).join(' · ') + '</div>';
      }
    } else {
      h += '<div class="tk-rs">' + esc(r.reason) + '</div>';
    }
    h += '<button type="button" class="tk-btn2 tk-more-btn" data-tkdetail="' + esc(r.date) + '"' +
      (r.windows && r.windows[0] ? ' data-tktime="' + esc(r.windows[0].peak) + '"' : '') + '>🔎 पूरा विवरण (हर नियम)</button>' +
      '<div class="tk-detail"></div></div>';
    return h;
  }

  function sortKey(ddmmyyyy) { var p = ddmmyyyy.split('-'); return +(p[2] + p[1] + p[0]); }

  function listHtml(d) {
    var good = d.rows.filter(function (r) { return r.grade !== 'अशुभ'; });
    if (!good.length) {
      return '<div class="tk-none">इस अवधि में इस कार्य हेतु कोई शुभ/मध्यम दिन नहीं मिला — अवधि बढ़ाएँ, कार्य-स्थान/विकल्प जाँचें, या "पूरी अवधि की सूची" में हर दिन की बाधा देखें।</div>';
    }
    var days = good.slice().sort(function (x, y) {
      if (pref.o === 'score') {
        var gx = x.grade === 'शुभ' ? 1 : 0, gy = y.grade === 'शुभ' ? 1 : 0;
        if (gx !== gy) { return gy - gx; }
        if (y.rank !== x.rank) { return y.rank - x.rank; }
      }
      return sortKey(x.date) - sortKey(y.date);
    });
    var h = '<div class="tk-grid">';
    if (pref.o === 'date') {
      var per = {};
      days.forEach(function (x) {
        var p = x.date.split('-'), k = p[2] + '-' + p[1];
        per[k] = per[k] || { g: 0, y: 0 };
        per[k][tone(x.tone) === 'g' ? 'g' : 'y']++;
      });
      var curM = '';
      days.forEach(function (x) {
        var p = x.date.split('-'), k = p[2] + '-' + p[1];
        if (k !== curM) {
          curM = k;
          h += '<div class="tk-mh">' + MONTHS[+p[1] - 1] + ' ' + p[2] + '<span>🟢 ' + per[k].g + ' शुभ · 🟡 ' + per[k].y + ' मध्यम</span></div>';
        }
        h += dayCard(x);
      });
    } else {
      days.forEach(function (x) { h += dayCard(x); });
    }
    return h + '</div>';
  }

  function calHtml(d) {
    var rows = d.rows || [];
    if (!rows.length) { return '<div class="tk-none">कोई दिन नहीं जाँचा गया।</div>'; }
    var map = {};
    rows.forEach(function (x) { map[x.date] = x; });
    var f = parse(rows[0].date), t = parse(rows[rows.length - 1].date);
    var today = fmt(new Date());
    var h = '<div class="tk-cal-k"><span><i style="background:#22c55e"></i>शुभ</span><span><i style="background:#facc15"></i>मध्यम</span>' +
      '<span><i style="background:#fee2e2;box-shadow:inset 0 0 0 1px #fca5a5"></i>अशुभ</span><span><i style="box-shadow:inset 0 0 0 2px #1d4ed8"></i>आज</span>' +
      '<span>— किसी दिन को छूकर उसका विवरण देखें</span></div><div class="tk-cal">';
    var head = WD.map(function (w) { return '<span class="tk-cw">' + w + '</span>'; }).join('');
    var y = f.getFullYear(), m = f.getMonth(), endKey = t.getFullYear() * 12 + t.getMonth();
    while (y * 12 + m <= endKey) {
      var lead = new Date(y, m, 1).getDay(), days = new Date(y, m + 1, 0).getDate();
      var n = { g: 0, y: 0, r: 0 }, cells = '';
      for (var i = 0; i < lead; i++) { cells += '<span></span>'; }
      for (var dd = 1; dd <= days; dd++) {
        var ds = two(dd) + '-' + two(m + 1) + '-' + y, x = map[ds];
        var cls = 'tk-c ' + (x ? tone(x.tone) : 'out') + (ds === today ? ' today' : '');
        if (x) {
          n[tone(x.tone)]++;
          cells += '<button type="button" class="' + cls + '" data-tkday="' + ds + '" title="' + esc(ds + ' ' + x.weekday + ' — ' + x.grade) + '">' + dd + '</button>';
        } else {
          cells += '<span class="' + cls + '">' + dd + '</span>';
        }
      }
      h += '<div class="tk-mon"><div class="tk-mon-h">' + MONTHS[m] + ' ' + y + '<span>🟢' + n.g + ' · 🟡' + n.y + ' · 🔴' + n.r + '</span></div>' +
        '<div class="tk-cg">' + head + cells + '</div><div class="tk-cal-d"></div></div>';
      m++;
      if (m > 11) { m = 0; y++; }
    }
    return h + '</div>';
  }

  function fullHtml(d) {
    var h = '<details class="tk-more"><summary>📋 पूरी अवधि की तिथि-सूची (' + d.scanned + ' दिन — हर दिन का कारण)</summary>' +
      '<div class="tk-tblw"><table class="tk-tbl"><thead><tr><th>तिथि</th><th>वार</th><th>श्रेणी</th><th>अंक</th><th>श्रेष्ठ समय / बाधा</th></tr></thead><tbody>';
    d.rows.forEach(function (x) {
      h += '<tr class="' + tone(x.tone) + '"><td>' + esc(x.date) + '</td><td>' + esc(x.weekday) + '</td><td>' + esc(x.grade) +
        '</td><td>' + (+x.score) + '</td><td>' + esc(x.reason) + '</td></tr>';
    });
    return h + '</tbody></table></div></details>';
  }

  function render() {
    var res = $('tk-results');
    if (!last) { res.innerHTML = ''; return; }
    var d = last.d, ctx = last.ctx;
    var wasOpen = !!res.querySelector('.tk-more[open]');
    var h = '<div class="tk-res-h">🔍 परिणाम — ' + esc(ctx.sub.label) + '</div>' +
      '<div class="tk-res-m">' + esc(ctx.cat.emoji + ' ' + ctx.cat.label) + ' · 📅 ' + esc(ctx.from) + ' से ' + esc(ctx.to) +
      ' · 📍 ' + esc(ctx.place || '') + (d && d.personal ? ' · 👤 व्यक्तिगत बल सहित' : '') + '</div>';
    if (!d || !d.ok) {
      res.innerHTML = '<div class="tk-card">' + h + '<div class="tk-none" style="margin-top:10px">⚠️ ' + esc((d && d.error) || 'तिथि-सीमा जाँचें।') + '</div></div>';
      return;
    }
    var c = d.count || {};
    h += '<div class="tk-tally"><span class="tk-pill g">🟢 शुभ: ' + (c['शुभ'] || 0) + '</span>' +
      '<span class="tk-pill y">🟡 मध्यम: ' + (c['मध्यम'] || 0) + '</span>' +
      '<span class="tk-pill r">🔴 अशुभ: ' + (c['अशुभ'] || 0) + '</span>' +
      '<span class="tk-pill n">' + (+d.scanned) + ' दिन जाँचे</span></div>';
    h += '<div class="tk-keyl">🟢 <b>शुभ</b> = नियम-पुस्तिका के सभी नियम शुद्ध · 🟡 <b>मध्यम</b> = कोई बड़ा दोष नहीं पर कुछ कमी (जैसे लग्न-शुद्धि अपूर्ण) — ⚠ में लिखी · समय कार्य-स्थान के स्थानीय समय में; (+1) = अगली तारीख़ का भोर (वार सूर्योदय तक वही)।</div>';
    if (d.capped) { h += '<div class="tk-warn">⚠️ अवधि बड़ी है — पहले ' + (+d.max_days) + ' दिन ही जाँचे गए (कुल ' + (+d.total) + ')। छोटी अवधि चुनें।</div>'; }
    h += '<div class="tk-bar"><div class="tk-seg" role="group" aria-label="दृश्य">' + seg('v', 'list', '📋 शुभ दिन सूची') + seg('v', 'cal', '📆 कैलेंडर') + '</div>' +
      (pref.v === 'list' ? '<div><span class="tk-seg-l">क्रम:</span><div class="tk-seg" role="group" aria-label="क्रम">' +
        seg('o', 'score', '⭐ श्रेष्ठ पहले') + seg('o', 'date', '📅 तिथि-क्रम') + '</div></div>' : '') + '</div>';
    h += pref.v === 'cal' ? calHtml(d) : listHtml(d);
    h += fullHtml(d);
    h += '<div class="tk-note">📌 हर दिन सूर्योदय से अगले सूर्योदय तक पल-पल जाँचा गया — तिथि, नक्षत्र, योग, करण व लग्न जिस क्षण बदलते हैं, राहुकाल, यमगण्ड, गुलिक, दुर्मुहूर्त, भद्रा, विष घटी, गण्डान्त, संक्रान्ति, ग्रहण-सूतक — सब कटे। मास/अस्त/ग्रहण/पर्व तिथियाँ गणना से (पूर्णिमान्त मास)। अंतिम निर्णय से पहले स्थानीय पंचांग व कुल-परंपरा से पुष्टि करें।</div>';
    res.innerHTML = '<div class="tk-card">' + h + '</div>';
    if (wasOpen) { var dt = res.querySelector('.tk-more'); if (dt) { dt.open = true; } }
  }

  // किसी दिन का पूरा विवरण — उसी कार्ड के नीचे
  function loadDetail(btn, host) {
    if (!last) { return; }
    var cur = current();
    if (!cur.sub) { cur = { sub: last.ctx.sub }; }
    if (host.innerHTML && !host.querySelector('.tk-wait')) { host.innerHTML = ''; btn.textContent = '🔎 पूरा विवरण (हर नियम)'; return; }
    host.innerHTML = '<div class="tk-wait">⏳ हर नियम जाँच रहे…</div>';
    var time = btn.getAttribute('data-tktime') || '';
    var url = '/calc/karyaCheck?task=' + encodeURIComponent(last.ctx.sub.key) + '&date=' + encodeURIComponent(btn.getAttribute('data-tkdetail')) +
      (time ? '&time=' + encodeURIComponent(time.replace(/\s.*$/, '')) : '') + placeQuery() + optQuery(last.ctx.sub, pref.opts) + birthQuery(last.ctx.personal);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) { host.innerHTML = checkHtml(d, false); btn.textContent = '▲ विवरण बंद करें'; })
      .catch(function () { host.innerHTML = '<div class="tk-none">⚠️ विवरण नहीं आ सका।</div>'; });
  }

  function showCalDay(btn) {
    if (!last || !last.d.rows) { return; }
    var ds = btn.getAttribute('data-tkday'), x = null;
    last.d.rows.some(function (r) { if (r.date === ds) { x = r; return true; } return false; });
    if (!x) { return; }
    var res = $('tk-results');
    res.querySelectorAll('.tk-c.sel').forEach(function (el) { el.classList.remove('sel'); });
    res.querySelectorAll('.tk-cal-d').forEach(function (el) { el.innerHTML = ''; });
    btn.classList.add('sel');
    var mon = btn.closest('.tk-mon'), box = mon ? mon.querySelector('.tk-cal-d') : null;
    if (box) { box.innerHTML = dayCard(x); }
  }

  function ensure() {
    if (built) { return; }
    var cat = $('tk-cat');
    if (!$('tk-data') || !cat) { return; }
    built = true;
    getData();
    load();
    if (findCat(pref.c)) { cat.value = pref.c; }
    fillSubs($('tk-sub'), cat.value, pref.s);
    onTaskChange();
    $('tk-personal').checked = pref.personal !== '0';
    // पन्ना कल खुला छोड़ा गया हो तो सर्वर की भरी "आज" पुरानी पड़ जाती — इसलिए
    // चालू टैब की तिथियाँ यहीं, इस ब्राउज़र की घड़ी से, दोबारा भरी जाती हैं।
    var on = document.querySelector('#card-tithikhoj .tk-tab.on');
    if (on) {
      $('tk-from').value = fmt(new Date());
      $('tk-to').value = fmt(plusMonths(parseInt(on.getAttribute('data-months'), 10) || 6));
    }
    // कार्य-स्थान — पिछली बार चुना स्थान याद रहता है
    if (pref.place) {
      $('tk-place').value = pref.place.label || '';
      $('tk-lat').value = pref.place.lat; $('tk-lon').value = pref.place.lon;
      $('tk-tz').value = pref.place.tz; $('tk-zone').value = pref.place.zone || '';
    }
    if (window.ABCitySearch) {
      window.ABCitySearch.init({
        input: '#tk-place', results: '#tk-place-res', lat: '#tk-lat', lon: '#tk-lon', tz: '#tk-tz',
        getDate: function () { return parse($('tk-from').value) || new Date(); },
        onSelect: function (r) {
          $('tk-zone').value = r.timezone || '';
          setTimeout(function () {
            pref.place = { label: $('tk-place').value, lat: $('tk-lat').value, lon: $('tk-lon').value, tz: $('tk-tz').value, zone: $('tk-zone').value };
            save();
          }, 0);
        }
      });
    }

    cat.addEventListener('change', function () {
      fillSubs($('tk-sub'), cat.value, '');
      pref.c = cat.value; pref.s = ''; save();
      onTaskChange();
    });
    $('tk-sub').addEventListener('change', function () { pref.s = $('tk-sub').value; save(); onTaskChange(); });
    $('tk-personal').addEventListener('change', function () { pref.personal = $('tk-personal').checked ? '1' : '0'; save(); });

    var tabs = document.querySelector('#card-tithikhoj .tk-tabs');
    if (tabs) {
      tabs.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.tk-tab') : null;
        if (!b) { return; }
        $('tk-from').value = fmt(new Date());
        $('tk-to').value = fmt(plusMonths(parseInt(b.getAttribute('data-months'), 10) || 1));
        markTab(b);
      });
    }
    ['tk-from', 'tk-to'].forEach(function (id) {
      var el = $(id);
      // हाथ से तिथि बदलते ही कोई टैब चालू नहीं रहता — वरना पट्टी कुछ और कहती, डिब्बे कुछ और
      el.addEventListener('input', function () { markTab(null); });
      el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { search(); } });
    });
    // 📅 — डिब्बे के ऊपर रखा पारदर्शी date-input: छूते ही फ़ोन/iPad का अपना
    // कैलेंडर; कंप्यूटर पर showPicker()। चुनी तिथि DD-MM-YYYY में डिब्बे में।
    document.querySelectorAll('#card-tithikhoj .tk-pick input[type=date]').forEach(function (di) {
      var txt = $(di.getAttribute('data-for'));
      var sync = function () { var p = parse(txt.value); di.value = p ? iso(p) : ''; };
      di.addEventListener('pointerdown', sync);
      di.addEventListener('focus', sync);
      di.addEventListener('click', function () {
        sync();
        if (typeof di.showPicker === 'function') { try { di.showPicker(); } catch (e) { /* पहले से खुला */ } }
      });
      di.addEventListener('change', function () {
        if (!di.value) { return; }
        var p = di.value.split('-');
        txt.value = p[2] + '-' + p[1] + '-' + p[0];
        markTab(null);
      });
    });
    $('tk-go').addEventListener('click', search);
    $('tk-results').addEventListener('click', function (e) {
      if (!e.target.closest) { return; }
      var v = e.target.closest('[data-tkv]'), o = e.target.closest('[data-tko]'), day = e.target.closest('[data-tkday]');
      var det = e.target.closest('[data-tkdetail]');
      if (v) { pref.v = v.getAttribute('data-tkv'); save(); render(); return; }
      if (o) { pref.o = o.getAttribute('data-tko'); save(); render(); return; }
      if (det) { loadDetail(det, det.parentElement.querySelector('.tk-detail')); return; }
      if (day) { showCalDay(day); }
    });
  }

  // बाहर से कार्य चुनकर खोज (मुहूर्त फल पैनल का "शुभ तिथियाँ खोजें")
  function openWith(id, opts) {
    ensure();
    var t = findTask(id);
    if (!t) { return; }
    $('tk-cat').value = t.cat.key;
    fillSubs($('tk-sub'), t.cat.key, id);
    pref.c = t.cat.key; pref.s = id; pref.opts = Object.assign({}, opts || {}); save();
    onTaskChange();
    search();
  }

  // ════════════ मुहूर्त फल — "कार्य-मुहूर्त जाँच" पैनल ════════════
  var phal = null;   // { host, cat, sub, opts }
  function gocharWhen() {
    var root = $('mah-inputs');
    var ins = root ? root.querySelectorAll('input') : [];
    var meta = window.AB_GOCHAR_META_MAH || {};
    // गोचर-फ़ॉर्म: तिथि, समय, स्थान, अक्षांश, देशान्तर, समय-क्षेत्र (उसी क्रम में)
    return {
      date: meta.date || (ins[0] && ins[0].value) || fmt(new Date()),
      time: meta.time || (ins[1] && ins[1].value) || '12:00',
      place: meta.place || (ins[2] && ins[2].value) || '',
      lat: meta.lat != null ? meta.lat : (ins[3] && ins[3].value),
      lon: meta.lon != null ? meta.lon : (ins[4] && ins[4].value),
      tz: meta.tz != null ? meta.tz : (ins[5] && ins[5].value)
    };
  }
  function phalCheck() {
    if (!phal || !phal.host.isConnected) { return; }
    var c = findCat(phal.catSel.value), s = findSub(c, phal.subSel.value);
    var out = phal.host.querySelector('.tk-mp-out');
    if (!s) { out.innerHTML = '<div class="tk-rs">ऊपर से कार्य चुनें — चुनी तिथि/समय/स्थान पर उस कार्य के हर नियम की जाँच यहाँ दिखेगी।</div>'; return; }
    var w = gocharWhen();
    var on = phal.host.querySelector('.tk-mp-personal').checked;
    phal.host.querySelector('.tk-mp-when').textContent = '🕐 जाँच का क्षण: ' + w.date + ' · ' + w.time + (w.place ? ' · 📍 ' + w.place : '') + ' (ऊपर गोचर-फ़ॉर्म से बदलें)';
    out.innerHTML = '<div class="tk-wait">⏳ जाँच रहे…</div>';
    var my = ++phalSeq;
    var url = '/calc/karyaCheck?task=' + encodeURIComponent(s.key) + '&date=' + encodeURIComponent(w.date) + '&time=' + encodeURIComponent(w.time) +
      '&lat=' + encodeURIComponent(w.lat) + '&lon=' + encodeURIComponent(w.lon) + '&tz=' + encodeURIComponent(w.tz) +
      optQuery(s, phal.opts) + birthQuery(on);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (my !== phalSeq) { return; }
        out.innerHTML = checkHtml(d, true) +
          '<div style="margin-top:10px"><button type="button" class="tk-btn2 tk-mp-find">🔍 इस कार्य की शुभ तिथियाँ खोजें (तिथि खोज) →</button></div>';
      })
      .catch(function () { if (my === phalSeq) { out.innerHTML = '<div class="tk-none">⚠️ जाँच नहीं हो सकी।</div>'; } });
  }
  function mountPhal(host) {
    if (!host || !$('tk-data')) { return; }
    getData();
    load();
    if (phal && phal.host === host) { phalCheck(); return; }
    var opts = '';
    getData().cats.forEach(function (c, i) { opts += '<option value="' + esc(c.key) + '">' + (i + 1) + '. ' + esc(c.emoji + ' ' + c.label) + '</option>'; });
    host.innerHTML = '<div class="tk-mp-pick"><div class="tk-fld tk-wide"><label>मुख्य श्रेणी</label><select class="tk-mp-cat"><option value="">— चुनें —</option>' + opts + '</select></div>' +
      '<div class="tk-fld tk-wide"><label>कार्य</label><select class="tk-mp-sub" disabled></select></div></div>' +
      '<div class="tk-opts tk-mp-opts"></div>' +
      '<label class="tk-chk"><input type="checkbox" class="tk-mp-personal"' + (pref.personal !== '0' ? ' checked' : '') + '><span>👤 खुली कुंडली का चंद्र/तारा बल भी</span></label>' +
      '<div class="tk-mp-when"></div><div class="tk-mp-out"></div>';
    phal = { host: host, catSel: host.querySelector('.tk-mp-cat'), subSel: host.querySelector('.tk-mp-sub'), opts: Object.assign({}, pref.opts || {}) };
    if (findCat(pref.c)) { phal.catSel.value = pref.c; }
    fillSubs(phal.subSel, phal.catSel.value, pref.s);
    var redo = function () {
      renderOpts(host.querySelector('.tk-mp-opts'), findSub(findCat(phal.catSel.value), phal.subSel.value), phal.opts, phalCheck);
      phalCheck();
    };
    phal.catSel.addEventListener('change', function () { fillSubs(phal.subSel, phal.catSel.value, ''); pref.c = phal.catSel.value; pref.s = ''; save(); redo(); });
    phal.subSel.addEventListener('change', function () { pref.s = phal.subSel.value; save(); redo(); });
    host.querySelector('.tk-mp-personal').addEventListener('change', phalCheck);
    host.addEventListener('click', function (e) {
      if (!e.target.closest || !e.target.closest('.tk-mp-find')) { return; }
      var id = phal.subSel.value;
      var opt = Object.assign({}, phal.opts);
      var btn = document.querySelector('#side-menu [data-sec="muhurat"][data-mview="tithi"]');
      if (btn) { btn.click(); }
      setTimeout(function () { openWith(id, opt); }, 50);
    });
    redo();
  }
  // गोचर का क्षण बदला (स्टेपर/तिथि) — पैनल खुला हो तो उसी पर फिर जाँच
  function refreshPhal(meta) {
    window.AB_GOCHAR_META_MAH = meta || null;
    if (phal && phal.host.isConnected && phal.host.offsetParent) { phalCheck(); }
    else { phal = null; }
  }

  window.ABTithiKhoj = { ensure: ensure, mountPhal: mountPhal, refreshPhal: refreshPhal, openWith: openWith };
})();
