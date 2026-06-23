/* Auto Business — interactive Gochar (transit) panel (reusable component).
 *
 * Renders Date / Time / Country / State / City inputs (plus lat/lon/tz that the
 * city picker fills, and which stay editable). On load it defaults to the
 * current date+time and the viewer's location (browser geolocation, falling
 * back to the page default), then fetches transits from the calc/gochar JSON
 * endpoint and draws a North-Indian transit chart + a positions table.
 *
 * Depends on: northchart.js (ABChart), cities.js (window.AB_CITIES).
 *
 * Usage: ABGochar.init('#gochar-panel', {birth:{...}, fallback:{lat,lon,tz}});
 *   birth = {date,time,lat,lon,tz,ayanamsa}  (to rebuild the natal chart so the
 *   house-from-lagna / house-from-moon columns stay meaningful)
 */
(function (global) {
  'use strict';

  function h(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t || v; return o; }

  function init(sel, cfg) {
    cfg = cfg || {};
    var root = (typeof sel === 'string') ? document.querySelector(sel) : sel;
    if (!root) return;
    var birth = cfg.birth || {};
    var fallback = cfg.fallback || { lat: 28.61, lon: 77.21, tz: 5.5 };
    var CITIES = global.AB_CITIES || {};

    root.innerHTML = '';
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    var hhmm = pad(now.getHours()) + ':' + pad(now.getMinutes());

    // --- Controls -------------------------------------------------------
    var form = h('div', 'grid grid-cols-2 md:grid-cols-4 gap-3 text-sm');
    var fDate = h('input'); fDate.type = 'date'; fDate.value = today;
    var fTime = h('input'); fTime.type = 'time'; fTime.value = hhmm;
    var fCountry = h('select'); var fState = h('select'); var fCity = h('select');
    [fDate, fTime].forEach(function (i) { i.className = 'border rounded px-2 py-1'; });
    [fCountry, fState, fCity].forEach(function (s) { s.className = 'border rounded px-2 py-1 bg-white'; });
    var fLat = h('input', 'border rounded px-2 py-1');
    var fLon = h('input', 'border rounded px-2 py-1');
    var fTz = h('input', 'border rounded px-2 py-1');
    fLat.value = fallback.lat; fLon.value = fallback.lon; fTz.value = fallback.tz;

    function lab(text, node) { var l = h('label', 'flex flex-col gap-1'); l.appendChild(h('span', 'text-gray-500', text)); l.appendChild(node); return l; }

    fCountry.appendChild(opt('', '— country —'));
    Object.keys(CITIES).forEach(function (c) { fCountry.appendChild(opt(c)); });
    fCountry.addEventListener('change', function () {
      fState.innerHTML = ''; fCity.innerHTML = '';
      fState.appendChild(opt('', '— state —'));
      var st = CITIES[fCountry.value] || {};
      Object.keys(st).forEach(function (s) { fState.appendChild(opt(s)); });
    });
    fState.addEventListener('change', function () {
      fCity.innerHTML = ''; fCity.appendChild(opt('', '— city —'));
      var ci = (CITIES[fCountry.value] || {})[fState.value] || {};
      Object.keys(ci).forEach(function (c) { fCity.appendChild(opt(c)); });
    });
    fCity.addEventListener('change', function () {
      var rec = ((CITIES[fCountry.value] || {})[fState.value] || {})[fCity.value];
      if (rec) { fLat.value = rec.lat; fLon.value = rec.lon; fTz.value = rec.tz; }
    });
    fState.appendChild(opt('', '— state —'));
    fCity.appendChild(opt('', '— city —'));

    form.appendChild(lab('Date', fDate));
    form.appendChild(lab('Time', fTime));
    form.appendChild(lab('Country', fCountry));
    form.appendChild(lab('State / Province', fState));
    form.appendChild(lab('City', fCity));
    form.appendChild(lab('Latitude (N+)', fLat));
    form.appendChild(lab('Longitude (E+)', fLon));
    form.appendChild(lab('Timezone (hrs E+)', fTz));
    root.appendChild(form);

    var btn = h('button', 'mt-3 bg-blue-600 text-white rounded px-4 py-2 text-sm font-semibold', 'Show transit');
    root.appendChild(btn);
    var status = h('span', 'text-xs text-gray-500 ml-3'); root.appendChild(status);

    var out = h('div', 'grid grid-cols-1 md:grid-cols-2 gap-4 mt-4');
    var chartBox = h('div', 'bg-white rounded-lg shadow p-3');
    var tableBox = h('div', 'bg-white rounded-lg shadow p-3 overflow-x-auto');
    out.appendChild(chartBox); out.appendChild(tableBox);
    root.appendChild(out);

    var ABBR = { Sun:'Su', Moon:'Mo', Mars:'Ma', Mercury:'Me', Jupiter:'Ju', Venus:'Ve', Saturn:'Sa', Rahu:'Ra', Ketu:'Ke' };

    function fetchGochar() {
      status.textContent = 'calculating…';
      var q = new URLSearchParams({
        date: fDate.value, time: fTime.value,
        lat: fLat.value, lon: fLon.value, tz: fTz.value,
        bdate: birth.date || '', btime: birth.time || '',
        blat: birth.lat != null ? birth.lat : '', blon: birth.lon != null ? birth.lon : '',
        btz: birth.tz != null ? birth.tz : '', ayanamsa: birth.ayanamsa || 'lahiri'
      });
      fetch('/calc/gochar?' + q.toString(), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (g) {
          if (g.error) { status.textContent = 'Error: ' + g.error; return; }
          status.textContent = '';
          renderResult(g);
        })
        .catch(function (e) { status.textContent = 'Request failed: ' + e; });
    }

    function renderResult(g) {
      // Transit chart: ascendant sign + each planet's sign.
      var planets = [];
      Object.keys(g.transits).forEach(function (name) {
        var t = g.transits[name];
        planets.push({ abbr: ABBR[name] || name.slice(0, 2), sign: t.sign_index, retro: !!t.retro });
      });
      ABChart.renderNorth(chartBox, { asc_sign: g.ascendant.sign_index, planets: planets },
        { title: 'Gochar (Transit) — ' + g.label });

      // Positions table.
      var rows = ['<table class="w-full text-sm"><thead><tr class="text-left border-b">'
        + '<th class="py-1 pr-2">Planet</th><th class="pr-2">Transit</th><th class="pr-2">Sign</th>'
        + '<th class="pr-2">H/Lagna</th><th>H/Moon</th></tr></thead><tbody>'];
      Object.keys(g.transits).forEach(function (name) {
        var t = g.transits[name];
        rows.push('<tr class="border-b border-gray-100"><td class="py-1 pr-2 font-medium">' + name
          + (t.retro ? ' <span class="text-red-600">R</span>' : '') + '</td>'
          + '<td class="pr-2">' + t.formatted + '</td><td class="pr-2">' + t.sign + '</td>'
          + '<td class="pr-2">' + t.house_from_lagna + '</td><td>' + t.house_from_moon + '</td></tr>');
      });
      rows.push('</tbody></table>');
      var lg = g.ascendant ? '<div class="text-sm mb-2">Transit Lagna: <b>' + g.ascendant.formatted + '</b></div>' : '';
      tableBox.innerHTML = lg + rows.join('');
    }

    btn.addEventListener('click', fetchGochar);

    // Default to viewer's geolocation, then auto-calculate.
    function go() { fetchGochar(); }
    if (global.navigator && navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(function (pos) {
        fLat.value = pos.coords.latitude.toFixed(2);
        fLon.value = pos.coords.longitude.toFixed(2);
        fTz.value = (-new Date().getTimezoneOffset() / 60);
        go();
      }, function () { go(); }, { timeout: 4000 });
    } else { go(); }
  }

  global.ABGochar = { init: init };
})(window);
