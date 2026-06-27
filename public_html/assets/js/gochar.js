/* Auto Business — interactive Gochar (transit) panel (reusable component).
 *
 * Renders Date / Time / Country / State / City inputs into one container and the
 * transit result (North-Indian chart + positions table) into another, so the
 * dashboard can place the inputs and the chart in different rows. On load it
 * defaults to the current date+time and the viewer's IP-based location, then
 * fetches transits from the calc/gochar JSON endpoint and draws them.
 *
 * Depends on: northchart.js (ABChart), citysearch.js (ABCitySearch).
 *
 * Usage:
 *   ABGochar.init({
 *     inputs: '#gochar-inputs', output: '#gochar-output',
 *     birth: {date,time,lat,lon,tz,ayanamsa}, fallback:{lat,lon,tz}
 *   });
 */
(function (global) {
  'use strict';

  // Per-planet colours by full name (matches the Dasha + chart palette).
  var PCOL = {
    Sun:'#dc2626', Moon:'#0891b2', Mars:'#ea580c', Mercury:'#16a34a', Jupiter:'#b45309',
    Venus:'#db2777', Saturn:'#1d4ed8', Rahu:'#3d4554', Ketu:'#3d4554'
  };
  var ABBR = { Sun:'Su', Moon:'Mo', Mars:'Ma', Mercury:'Me', Jupiter:'Ju', Venus:'Ve', Saturn:'Sa', Rahu:'Ra', Ketu:'Ke' };

  function h(tag, cls, html) {
    var e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html != null) e.innerHTML = html;
    return e;
  }
  function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t || v; return o; }
  function sel(x) { return (typeof x === 'string') ? document.querySelector(x) : x; }

  function init(cfg) {
    cfg = cfg || {};
    var inRoot = sel(cfg.inputs), outRoot = sel(cfg.output);
    if (!inRoot || !outRoot) return;
    var birth = cfg.birth || {};
    var fallback = cfg.fallback || { lat: 28.61, lon: 77.21, tz: 5.5 };

    inRoot.innerHTML = '';
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var today = now.getFullYear() + '-' + pad(now.getMonth() + 1) + '-' + pad(now.getDate());
    var hhmm = pad(now.getHours()) + ':' + pad(now.getMinutes());

    var form = h('div', 'grid grid-cols-2 md:grid-cols-4 gap-3 text-sm');
    var fDate = h('input'); fDate.type = 'date'; fDate.value = today;
    var fTime = h('input'); fTime.type = 'time'; fTime.value = hhmm;
    [fDate, fTime].forEach(function (i) { i.className = 'border rounded px-2 py-1'; });
    var fPlace = h('input', 'border rounded px-2 py-1'); fPlace.type = 'text';
    fPlace.placeholder = 'Type a city…'; fPlace.autocomplete = 'off';
    var fResults = h('div', 'absolute z-20 left-0 right-0 top-full mt-1 bg-white border rounded shadow max-h-60 overflow-y-auto hidden');
    var fLat = h('input', 'border rounded px-2 py-1');
    var fLon = h('input', 'border rounded px-2 py-1');
    var fTz = h('input', 'border rounded px-2 py-1');
    fLat.value = fallback.lat; fLon.value = fallback.lon; fTz.value = fallback.tz;

    function lab(text, node, extra) {
      var l = h('label', 'flex flex-col gap-1 ' + (extra || ''));
      l.appendChild(h('span', 'text-gray-500', text)); l.appendChild(node); return l;
    }

    form.appendChild(lab('Date', fDate));
    form.appendChild(lab('Time', fTime));
    var placeCell = lab('Place (search city)', fPlace, 'relative col-span-2');
    placeCell.appendChild(fResults);
    form.appendChild(placeCell);
    form.appendChild(lab('Latitude (N+)', fLat));
    form.appendChild(lab('Longitude (E+)', fLon));
    form.appendChild(lab('Timezone (hrs E+)', fTz));
    inRoot.appendChild(form);

    // Worldwide city search fills lat/lon/tz (tz offset at the gochar date).
    if (global.ABCitySearch) {
      global.ABCitySearch.init({
        input: fPlace, results: fResults, lat: fLat, lon: fLon, tz: fTz,
        getDate: function () {
          var dt = new Date(fDate.value + 'T' + (fTime.value || '12:00') + ':00');
          return isNaN(dt) ? new Date() : dt;
        }
      });
    }

    var bar = h('div', 'mt-3 flex items-center gap-3');
    var btn = h('button', 'bg-blue-600 text-white rounded px-4 py-2 text-sm font-semibold', 'Show transit');
    var status = h('span', 'text-xs text-gray-500');
    bar.appendChild(btn); bar.appendChild(status);
    inRoot.appendChild(bar);

    // Output: chart + table.
    outRoot.innerHTML = '';
    var title = h('div', 'text-sm font-semibold text-center mb-2 text-gray-700', 'Gochar (Transit)');
    var chartBox = h('div', 'max-w-xs mx-auto');
    var tableBox = h('div', 'mt-3 overflow-x-auto');
    outRoot.appendChild(title); outRoot.appendChild(chartBox); outRoot.appendChild(tableBox);

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
      var planets = [];
      Object.keys(g.transits).forEach(function (name) {
        var t = g.transits[name];
        planets.push({ abbr: ABBR[name] || name.slice(0, 2), sign: t.sign_index, deg: t.deg, retro: !!t.retro });
      });
      ABChart.renderNorth(chartBox, { asc_sign: g.ascendant.sign_index, planets: planets },
        { title: g.label, showDeg: true });

      var rows = ['<table class="w-full text-sm"><thead><tr class="text-left border-b">'
        + '<th class="py-1 pr-2">Planet</th><th class="pr-2">Transit</th><th class="pr-2">Sign</th>'
        + '<th class="pr-2">H/Lagna</th><th>H/Moon</th></tr></thead><tbody>'];
      Object.keys(g.transits).forEach(function (name) {
        var t = g.transits[name];
        rows.push('<tr class="border-b border-gray-100"><td class="py-1 pr-2 font-semibold" style="color:' + (PCOL[name] || '#111') + '">' + name
          + (t.retro ? ' <span class="text-red-600">R</span>' : '') + '</td>'
          + '<td class="pr-2">' + t.formatted + '</td><td class="pr-2">' + t.sign + '</td>'
          + '<td class="pr-2">' + t.house_from_lagna + '</td><td>' + t.house_from_moon + '</td></tr>');
      });
      rows.push('</tbody></table>');
      var lg = g.ascendant ? '<div class="text-sm mb-2">Transit Lagna: <b>' + g.ascendant.formatted + '</b></div>' : '';
      tableBox.innerHTML = lg + rows.join('');
    }

    btn.addEventListener('click', fetchGochar);

    // Default to the viewer's IP location — city, state, country + lat/lon/tz —
    // and the current date/time, then compute (no permission prompt).
    status.textContent = 'locating…';
    fetch('https://ipapi.co/json/')
      .then(function (r) { return r.json(); })
      .then(function (loc) {
        if (loc && loc.latitude != null) {
          fLat.value = (+loc.latitude).toFixed(2);
          fLon.value = (+loc.longitude).toFixed(2);
          if (loc.utc_offset) { // e.g. "+0530"
            var s = loc.utc_offset, sign = s[0] === '-' ? -1 : 1;
            fTz.value = sign * (parseInt(s.substr(1, 2), 10) + parseInt(s.substr(3, 2), 10) / 60);
          }
          var label = [loc.city, loc.region, loc.country_name].filter(Boolean).join(', ');
          if (label) { fPlace.value = label; }
        }
      })
      .catch(function () { /* keep fallback */ })
      .then(function () { fetchGochar(); });
  }

  global.ABGochar = { init: init, PCOL: PCOL, ABBR: ABBR };
})(window);
