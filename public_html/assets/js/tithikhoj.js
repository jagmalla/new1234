/* 🔍 तिथि खोज — मुहूर्त पन्ने का दूसरा दृश्य (मेन्यू: Mahurat › Tithi Khoj)।
 *
 * markup व शैली: app/Http/views/_tithi_khoj.php · कार्य-सूची: TithiKhojCatalog
 * खोज: GET /calc/muhuratScan?type=…&from=…&to=…&tz=…&lite=1 (MuhuratDateScanner)
 *
 * पहली बार दृश्य खुलने पर ABTithiKhoj.ensure() सब जोड़ता है — पन्ना खुलते समय
 * इसका कोई ख़र्च नहीं। चुना हुआ कार्य, समय-क्षेत्र, दृश्य (सूची/कैलेंडर) और क्रम
 * इसी ब्राउज़र में याद रहते हैं; तिथियाँ हर बार आज से भरती हैं।
 */
(function () {
  'use strict';

  var MONTHS = ['जनवरी', 'फ़रवरी', 'मार्च', 'अप्रैल', 'मई', 'जून', 'जुलाई', 'अगस्त', 'सितम्बर', 'अक्टूबर', 'नवम्बर', 'दिसम्बर'];
  var WD = ['र', 'सो', 'मं', 'बु', 'गु', 'शु', 'श'];
  var MARK = { rule: '✅', general: '🟡', pending: '⏳' };
  var STORE = 'ab_tithikhoj';

  var built = false, cats = [], seq = 0, last = null;
  var pref = { c: '', s: '', tz: '', v: 'list', o: 'score' };

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
  // DD-MM-YYYY (या / . से) → Date। असम्भव तिथि (31-02) → null — सर्वर ग़लत
  // तिथि को चुपचाप 1 जनवरी पढ़ लेता है, इसलिए जाँच यहीं होती है।
  function parse(s) {
    var m = /^\s*(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})\s*$/.exec(s || '');
    if (!m) { return null; }
    var d = new Date(+m[3], +m[2] - 1, +m[1]);
    return (d.getFullYear() === +m[3] && d.getMonth() === +m[2] - 1 && d.getDate() === +m[1]) ? d : null;
  }
  function dayNum(d) { return Math.round(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()) / 864e5); }
  function sortKey(ddmmyyyy) { var p = ddmmyyyy.split('-'); return +(p[2] + p[1] + p[0]); }
  function tone(x) { return x.tone === 'pos' ? 'g' : (x.tone === 'info' ? 'y' : 'r'); }

  function load() {
    try {
      var p = JSON.parse(window.localStorage.getItem(STORE) || '{}');
      Object.keys(pref).forEach(function (k) { if (typeof p[k] === 'string') { pref[k] = p[k]; } });
    } catch (e) { /* निजी विंडो — पहले से तय मान ही रहें */ }
    if (pref.v !== 'cal') { pref.v = 'list'; }
    if (pref.o !== 'date') { pref.o = 'score'; }
  }
  function save() { try { window.localStorage.setItem(STORE, JSON.stringify(pref)); } catch (e) { /* निजी विंडो */ } }

  function findCat(k) { for (var i = 0; i < cats.length; i++) { if (cats[i].key === k) { return cats[i]; } } return null; }
  function findSub(c, k) {
    if (!c || !k) { return null; }
    for (var i = 0; i < c.subs.length; i++) { if (c.subs[i].key === k) { return c.subs[i]; } }
    return null;
  }
  function current() { var c = findCat($('tk-cat').value); return { cat: c, sub: findSub(c, $('tk-sub').value) }; }

  // ── ① कार्य: मुख्य श्रेणी बदलते ही उप-श्रेणी की सूची उसी की ──
  function fillSubs(catKey, subKey) {
    var sel = $('tk-sub'), c = findCat(catKey);
    var h = '<option value="">' + (c ? '— कार्य चुनें (' + c.subs.length + ') —' : '— पहले मुख्य श्रेणी चुनें —') + '</option>';
    if (c) {
      c.subs.forEach(function (s, i) {
        h += '<option value="' + esc(s.key) + '">' + (i + 1) + '. ' + esc(s.label) + ' ' + (MARK[s.status] || '') + '</option>';
      });
    }
    sel.innerHTML = h;
    sel.disabled = !c;
    sel.value = findSub(c, subKey) ? subKey : '';
  }

  // नियम की स्थिति + खोज-बटन — दोनों एक ही जगह से तय, ताकि कभी बेमेल न हों
  function updateStatus() {
    var cur = current(), box = $('tk-status'), go = $('tk-go'), hint = $('tk-go-hint');
    hint.classList.remove('err');
    hint.textContent = '';
    if (!cur.sub) {
      box.className = 'tk-status';
      box.innerHTML = '';
      go.disabled = true;
      go.textContent = '🔍 शुभ मुहूर्त-तिथि खोजें';
      hint.textContent = cur.cat ? 'अब उप-श्रेणी (कौन-सा कार्य) चुनें' : 'पहले ऊपर कार्य चुनें';
      return;
    }
    var s = cur.sub;
    box.className = 'tk-status on ' + s.status;
    if (s.status === 'rule') {
      box.innerHTML = '✅ <b>इस कार्य के शास्त्रीय नियम उपलब्ध हैं।</b> खोज इस नियम से होगी: <b>'
        + esc(s.rule) + '</b> (मुहूर्त-चिन्तामणि)।';
      go.disabled = false;
      go.textContent = '🔍 शुभ मुहूर्त-तिथि खोजें';
    } else if (s.status === 'general') {
      box.innerHTML = '🟡 <b>इस कार्य के विशेष नियम अभी जोड़े जाने हैं।</b> तब तक खोज '
        + '<b>सामान्य पंचांग-शुद्धि</b> से होगी — तिथि · वार · नक्षत्र · नित्य-योग · करण (भद्रा)।';
      go.disabled = false;
      go.textContent = '🔍 सामान्य शुभ-तिथि खोजें';
    } else {
      box.innerHTML = '⏳ <b>इस कार्य के तिथि-नियम अभी जोड़े जाने हैं।</b> इसकी कसौटी सामान्य शुभ-दिन से '
        + 'अलग है, इसलिए ग़लत दिन न दिखें — नियम जुड़ने तक इसकी खोज बंद है।';
      go.disabled = true;
      go.textContent = '⏳ नियम शीघ्र जुड़ेंगे';
    }
  }

  // ── ② अवधि ──
  function markTab(btn) {
    document.querySelectorAll('#card-tithikhoj .tk-tab').forEach(function (b) {
      var on = b === btn;
      b.classList.toggle('on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  }

  // ── ③ खोज ──
  function search() {
    var cur = current(), hint = $('tk-go-hint');
    if (!cur.sub || cur.sub.status === 'pending') { return; }
    var f = parse($('tk-from').value), t = parse($('tk-to').value);
    var bad = function (msg) { hint.textContent = '⚠️ ' + msg; hint.classList.add('err'); };
    if (!f) { bad('प्रारम्भ तिथि DD-MM-YYYY में भरें (जैसे ' + fmt(new Date()) + ')।'); return; }
    if (!t) { bad('अन्तिम तिथि DD-MM-YYYY में भरें।'); return; }
    if (dayNum(t) < dayNum(f)) { bad('अन्तिम तिथि प्रारम्भ तिथि से पहले है।'); return; }
    hint.textContent = '';
    hint.classList.remove('err');

    var tzSel = $('tk-tz');
    var ctx = {
      cat: cur.cat, sub: cur.sub, from: fmt(f), to: fmt(t),
      tz: tzSel.value, tzLabel: (tzSel.options[tzSel.selectedIndex] || {}).text || ''
    };
    var b = window.AB_BIRTH || {};
    var q = new URLSearchParams({
      type: cur.sub.type, from: ctx.from, to: ctx.to, tz: ctx.tz,
      ayanamsa: b.ayanamsa || 'lahiri', lite: '1'
    });
    var go = $('tk-go'), res = $('tk-results'), my = ++seq;
    go.disabled = true;
    go.textContent = '⏳ खोज रहे…';
    res.innerHTML = '<div class="tk-card tk-res"><div class="tk-wait">⏳ ' + esc(ctx.from) + ' से '
      + esc(ctx.to) + ' तक हर दिन की गणना हो रही है…</div></div>';
    fetch('/calc/muhuratScan?' + q.toString(), { headers: { 'Accept': 'application/json' } })
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
        res.innerHTML = '<div class="tk-card tk-res"><div class="tk-none">⚠️ खोज पूरी नहीं हो सकी — '
          + 'इंटरनेट/सर्वर जाँचकर फिर से दबाएँ।</div></div>';
      })
      .then(function () { if (my === seq) { updateStatus(); } });
  }

  // ── परिणाम ──
  function seg(kind, val, label) {
    var on = pref[kind] === val;
    return '<button type="button" data-tk' + kind + '="' + val + '"' + (on ? ' class="on"' : '')
      + ' aria-pressed="' + (on ? 'true' : 'false') + '">' + label + '</button>';
  }

  function dayCard(x) {
    var cl = tone(x);
    return '<div class="tk-day ' + cl + '"><div class="tk-dh">📅 ' + esc(x.date)
      + ' <span class="tk-wd">' + esc(x.weekday) + '</span> <span class="tk-gr ' + cl + '">' + esc(x.grade) + '</span></div>'
      + (x.bar_html || '') + '<div class="tk-rs">' + esc(x.reason) + '</div></div>';
  }

  function listHtml(d) {
    if (!d.best || !d.best.length) {
      return '<div class="tk-none">इस अवधि में कोई शुभ/मध्यम दिन नहीं मिला — अवधि बढ़ाएँ या दूसरी तिथि-सीमा आज़माएँ।</div>';
    }
    var days = d.best.slice().sort(function (x, y) {
      if (pref.o === 'score' && y.score !== x.score) { return y.score - x.score; }
      return sortKey(x.date) - sortKey(y.date);
    });
    var h = '<div class="tk-grid">';
    if (pref.o === 'date') {
      // तिथि-क्रम में महीने-वार शीर्षक, हर महीने की शुभ/मध्यम गिनती सहित
      var per = {};
      days.forEach(function (x) {
        var p = x.date.split('-'), k = p[2] + '-' + p[1];
        per[k] = per[k] || { g: 0, y: 0 };
        per[k][tone(x) === 'g' ? 'g' : 'y']++;
      });
      var curM = '';
      days.forEach(function (x) {
        var p = x.date.split('-'), k = p[2] + '-' + p[1];
        if (k !== curM) {
          curM = k;
          h += '<div class="tk-mh">' + MONTHS[+p[1] - 1] + ' ' + p[2] + '<span>🟢 ' + per[k].g
            + ' शुभ · 🟡 ' + per[k].y + ' मध्यम</span></div>';
        }
        h += dayCard(x);
      });
    } else {
      days.forEach(function (x) { h += dayCard(x); });
    }
    return h + '</div>';
  }

  function calHtml(d) {
    if (!d.rows || !d.rows.length) { return '<div class="tk-none">कोई दिन नहीं जाँचा गया।</div>'; }
    var map = {};
    d.rows.forEach(function (x) { map[x.date] = x; });
    var f = parse(d.rows[0].date), t = parse(d.rows[d.rows.length - 1].date);
    var today = fmt(new Date());
    var h = '<div class="tk-cal-k"><span><i style="background:#22c55e"></i>शुभ</span>'
      + '<span><i style="background:#facc15"></i>मध्यम</span>'
      + '<span><i style="background:#fee2e2;box-shadow:inset 0 0 0 1px #fca5a5"></i>अशुभ</span>'
      + '<span><i style="box-shadow:inset 0 0 0 2px #1d4ed8"></i>आज</span>'
      + '<span>— किसी दिन को छूकर उसका विवरण देखें</span></div><div class="tk-cal">';
    var head = WD.map(function (w) { return '<span class="tk-cw">' + w + '</span>'; }).join('');
    var y = f.getFullYear(), m = f.getMonth(), endKey = t.getFullYear() * 12 + t.getMonth();
    while (y * 12 + m <= endKey) {
      var lead = new Date(y, m, 1).getDay(), days = new Date(y, m + 1, 0).getDate();
      var n = { g: 0, y: 0, r: 0 }, cells = '';
      for (var i = 0; i < lead; i++) { cells += '<span></span>'; }
      for (var dd = 1; dd <= days; dd++) {
        var ds = two(dd) + '-' + two(m + 1) + '-' + y, x = map[ds];
        var cls = 'tk-c ' + (x ? tone(x) : 'out') + (ds === today ? ' today' : '');
        if (x) {
          n[tone(x)]++;
          cells += '<button type="button" class="' + cls + '" data-tkday="' + ds + '" title="'
            + esc(ds + ' ' + x.weekday + ' — ' + x.grade + ' · ' + x.score + '%') + '">' + dd + '</button>';
        } else {
          cells += '<span class="' + cls + '">' + dd + '</span>';
        }
      }
      h += '<div class="tk-mon"><div class="tk-mon-h">' + MONTHS[m] + ' ' + y
        + '<span>🟢' + n.g + ' · 🟡' + n.y + ' · 🔴' + n.r + '</span></div>'
        + '<div class="tk-cg">' + head + cells + '</div><div class="tk-cal-d"></div></div>';
      m++;
      if (m > 11) { m = 0; y++; }
    }
    return h + '</div>';
  }

  function fullHtml(d) {
    var h = '<details class="tk-more"><summary>📋 पूरी अवधि की तिथि-सूची (' + d.scanned + ' दिन)</summary>'
      + '<div class="tk-tblw"><table class="tk-tbl"><thead><tr><th>तिथि</th><th>वार</th><th>श्रेणी</th>'
      + '<th>अंक</th><th>कारण</th></tr></thead><tbody>';
    d.rows.forEach(function (x) {
      h += '<tr class="' + tone(x) + '"><td>' + esc(x.date) + '</td><td>' + esc(x.weekday) + '</td><td>'
        + esc(x.grade) + '</td><td>' + (+x.score) + '%</td><td>' + esc(x.reason) + '</td></tr>';
    });
    return h + '</tbody></table></div></details>';
  }

  function render() {
    var res = $('tk-results');
    if (!last) { res.innerHTML = ''; return; }
    var d = last.d, ctx = last.ctx;
    var wasOpen = !!res.querySelector('.tk-more[open]');
    var h = '<div class="tk-res-h">🔍 परिणाम — ' + esc(ctx.sub.label) + '</div>'
      + '<div class="tk-res-m">' + esc(ctx.cat.emoji + ' ' + ctx.cat.label) + ' · 📅 ' + esc(ctx.from)
      + ' से ' + esc(ctx.to) + ' · 🕰️ ' + esc(ctx.tzLabel) + ' · नियम: <b>' + esc(ctx.sub.rule) + '</b></div>';
    if (!d || !d.ok) {
      res.innerHTML = '<div class="tk-card tk-res">' + h + '<div class="tk-none" style="margin-top:10px">⚠️ '
        + esc((d && d.error) || 'तिथि-सीमा जाँचें।') + '</div></div>';
      return;
    }
    if (ctx.sub.status === 'general') {
      h += '<div class="tk-warn">🟡 <b>' + esc(ctx.sub.label) + '</b> के विशेष नियम अभी जोड़े जाने हैं — ये दिन केवल '
        + '<b>सामान्य पंचांग-शुद्धि</b> से चुने गए हैं। विशेष नियम जुड़ने पर सूची और सटीक होगी।</div>';
    }
    var c = d.count || {};
    h += '<div class="tk-tally">'
      + '<span class="tk-pill g">🟢 शुभ: ' + (c['शुभ'] || 0) + '</span>'
      + '<span class="tk-pill y">🟡 मध्यम: ' + (c['मध्यम'] || 0) + '</span>'
      + '<span class="tk-pill r">🔴 अशुभ: ' + (c['अशुभ'] || 0) + '</span>'
      + '<span class="tk-pill n">' + (+d.scanned) + ' दिन जाँचे</span></div>';
    if (d.capped) {
      h += '<div class="tk-warn">⚠️ अवधि बड़ी है — पहले ' + (+d.max_days) + ' दिन ही जाँचे गए (कुल '
        + (+d.total) + ')। छोटी अवधि चुनें।</div>';
    }
    h += '<div class="tk-bar"><div class="tk-seg" role="group" aria-label="दृश्य">'
      + seg('v', 'list', '📋 शुभ दिन सूची') + seg('v', 'cal', '📆 कैलेंडर') + '</div>'
      + (pref.v === 'list'
        ? '<div><span class="tk-seg-l">क्रम:</span><div class="tk-seg" role="group" aria-label="क्रम">'
          + seg('o', 'score', '⭐ श्रेष्ठ पहले') + seg('o', 'date', '📅 तिथि-क्रम') + '</div></div>'
        : '')
      + '</div>';
    h += pref.v === 'cal' ? calHtml(d) : listHtml(d);
    h += fullHtml(d);
    h += '<div class="tk-note">📌 हर दिन सूर्योदय के आसपास (प्रातः 6 बजे, कार्य-स्थान के समय से) के पंचांग पर '
      + 'आधारित। दिन चुनने के बाद उस दिन का सटीक समय (लग्न, राहु काल आदि) <b>मुहूर्त फल</b> पन्ने पर वही '
      + 'तिथि डालकर परखें।</div>';
    res.innerHTML = '<div class="tk-card tk-res">' + h + '</div>';
    if (wasOpen) { var dt = res.querySelector('.tk-more'); if (dt) { dt.open = true; } }
  }

  // कैलेंडर में छुए गए दिन का विवरण — उसी महीने के नीचे, ताकि फ़ोन पर दूर न जाए
  function showDay(btn) {
    if (!last || !last.d.rows) { return; }
    var ds = btn.getAttribute('data-tkday'), x = null, best = null;
    last.d.rows.some(function (r) { if (r.date === ds) { x = r; return true; } return false; });
    (last.d.best || []).some(function (r) { if (r.date === ds) { best = r; return true; } return false; });
    if (!x) { return; }
    var res = $('tk-results');
    res.querySelectorAll('.tk-c.sel').forEach(function (el) { el.classList.remove('sel'); });
    res.querySelectorAll('.tk-cal-d').forEach(function (el) { el.innerHTML = ''; });
    btn.classList.add('sel');
    var cl = tone(x), col = cl === 'g' ? '#16a34a' : (cl === 'y' ? '#d97706' : '#dc2626');
    var mon = btn.closest('.tk-mon'), box = mon ? mon.querySelector('.tk-cal-d') : null;
    if (!box) { return; }
    box.innerHTML = '<div class="tk-day ' + cl + '"><div class="tk-dh">📅 ' + esc(x.date) + ' <span class="tk-wd">'
      + esc(x.weekday) + '</span> <span class="tk-gr ' + cl + '">' + esc(x.grade)
      // शुभ/मध्यम दिन की पट्टी अपना अंक ख़ुद दिखाती है; अशुभ दिन के लिए छोटा मापक
      + (best && best.bar_html ? '</span></div>' + best.bar_html
        : ' · ' + (+x.score) + '%</span></div><div class="tk-meter"><b style="width:' + Math.max(4, +x.score)
          + '%;background:' + col + '"></b></div>')
      + '<div class="tk-rs">' + esc(x.reason) + '</div></div>';
  }

  function ensure() {
    if (built) { return; }
    var dataEl = $('tk-data'), cat = $('tk-cat');
    if (!dataEl || !cat) { return; }
    built = true;
    try { cats = JSON.parse(dataEl.textContent).cats || []; } catch (e) { cats = []; }
    load();

    var tz = $('tk-tz');
    if (pref.tz) {
      for (var i = 0; i < tz.options.length; i++) { if (tz.options[i].value === pref.tz) { tz.value = pref.tz; break; } }
    }
    if (findCat(pref.c)) { cat.value = pref.c; }
    fillSubs(cat.value, pref.s);
    updateStatus();
    // पन्ना कल खुला छोड़ा गया हो तो सर्वर की भरी "आज" पुरानी पड़ जाती — इसलिए
    // चालू टैब की तिथियाँ यहीं, इस ब्राउज़र की घड़ी से, दोबारा भरी जाती हैं।
    var on = document.querySelector('#card-tithikhoj .tk-tab.on');
    if (on) {
      $('tk-from').value = fmt(new Date());
      $('tk-to').value = fmt(plusMonths(parseInt(on.getAttribute('data-months'), 10) || 6));
    }

    cat.addEventListener('change', function () {
      fillSubs(cat.value, '');
      pref.c = cat.value;
      pref.s = '';
      save();
      updateStatus();
    });
    $('tk-sub').addEventListener('change', function () { pref.s = $('tk-sub').value; save(); updateStatus(); });
    tz.addEventListener('change', function () { pref.tz = tz.value; save(); });

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
      if (v) { pref.v = v.getAttribute('data-tkv'); save(); render(); return; }
      if (o) { pref.o = o.getAttribute('data-tko'); save(); render(); return; }
      if (day) { showDay(day); }
    });
  }

  window.ABTithiKhoj = { ensure: ensure };
})();
