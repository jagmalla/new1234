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
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); }
  // Date is entered/shown as DD-MM-YYYY; the endpoint + Date() need YYYY-MM-DD.
  function ddmmToISO(s) {
    var m = String(s || '').trim().match(/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/);
    return m ? (m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2)) : String(s || '');
  }
  // Time is entered/shown as 24-hour HH:MM; tolerate a stray am/pm on input.
  function norm24(s) {
    s = String(s || '').trim();
    var m = s.match(/^(\d{1,2}):(\d{2})\s*([ap]m)?$/i);
    if (!m) { return s; }
    var h = parseInt(m[1], 10), mm = m[2];
    if (m[3]) { var pm = /p/i.test(m[3]); if (pm && h < 12) { h += 12; } if (!pm && h === 12) { h = 0; } }
    return ('0' + h).slice(-2) + ':' + mm;
  }

  function init(cfg) {
    cfg = cfg || {};
    var inRoot = sel(cfg.inputs), outRoot = sel(cfg.output);
    if (!inRoot || !outRoot) return;
    var birth = cfg.birth || {};
    var fallback = cfg.fallback || { lat: 28.61, lon: 77.21, tz: 5.5 };

    inRoot.innerHTML = '';
    var now = new Date();
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    // DD-MM-YYYY date and 24-hour HH:MM time (matches the birth form's format).
    var today = pad(now.getDate()) + '-' + pad(now.getMonth() + 1) + '-' + now.getFullYear();
    var hhmm = pad(now.getHours()) + ':' + pad(now.getMinutes());

    var form = h('div', 'grid grid-cols-2 md:grid-cols-4 gap-3 text-sm');
    var fDate = h('input'); fDate.type = 'text'; fDate.value = today;
    fDate.placeholder = 'DD-MM-YYYY'; fDate.setAttribute('inputmode', 'numeric');
    var fTime = h('input'); fTime.type = 'text'; fTime.value = hhmm;
    fTime.placeholder = 'HH:MM'; fTime.setAttribute('inputmode', 'numeric');
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

    form.appendChild(lab('Date (DD-MM-YYYY)', fDate));
    form.appendChild(lab('Time (24h HH:MM)', fTime));
    var placeCell = lab('Place (search city)', fPlace, 'relative col-span-2');
    placeCell.appendChild(fResults);
    form.appendChild(placeCell);
    inRoot.appendChild(form);

    // Advanced (lat/lon/tz): auto-filled by the city search, so hidden by default.
    var adv = h('div', 'grid grid-cols-2 md:grid-cols-4 gap-3 text-sm mt-3 hidden');
    adv.appendChild(lab('Latitude (N+)', fLat));
    adv.appendChild(lab('Longitude (E+)', fLon));
    adv.appendChild(lab('Timezone (hrs E+)', fTz));
    inRoot.appendChild(adv);

    // Worldwide city search fills lat/lon/tz (tz offset at the gochar date).
    if (global.ABCitySearch) {
      global.ABCitySearch.init({
        input: fPlace, results: fResults, lat: fLat, lon: fLon, tz: fTz,
        getDate: function () {
          var dt = new Date(ddmmToISO(fDate.value) + 'T' + (norm24(fTime.value) || '12:00') + ':00');
          return isNaN(dt) ? new Date() : dt;
        }
      });
    }

    var bar = h('div', 'mt-3 flex flex-wrap items-center gap-3');
    var btn = h('button', 'bg-blue-600 text-white rounded px-4 py-2 text-sm font-semibold', 'Show transit');
    btn.type = 'button';
    var advBtn = h('button', 'text-sm text-blue-700 font-semibold border border-blue-200 rounded px-3 py-2 hover:bg-blue-50', '⚙ Advanced (Lat/Lon · Timezone)');
    advBtn.type = 'button';
    advBtn.setAttribute('aria-expanded', 'false');
    advBtn.addEventListener('click', function () {
      var hidden = adv.classList.toggle('hidden');
      advBtn.setAttribute('aria-expanded', hidden ? 'false' : 'true');
    });
    var status = h('span', 'text-xs text-gray-500');
    bar.appendChild(btn); bar.appendChild(advBtn); bar.appendChild(status);
    inRoot.appendChild(bar);

    // Output: a header row (Gochar (Transit) + date / time / place) then the
    // transit chart. The header matches the natal D1 header so the two cards
    // in the row line up.
    outRoot.innerHTML = '';
    var header = h('div', 'flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700');
    var chartBox = h('div', 'w-full');
    outRoot.appendChild(header); outRoot.appendChild(chartBox);

    function fetchGochar() {
      status.textContent = 'calculating…';
      var q = new URLSearchParams({
        date: ddmmToISO(fDate.value), time: norm24(fTime.value),
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
          // Gochar Phal panel (server-rendered) — inject beside the chart and
          // (re)bind its planet filter, so predictions follow the date/place.
          if (g.phal_html != null && cfg.injectPhal !== false) {
            var box = document.getElementById('gochar-phal');
            if (box) {
              box.innerHTML = g.phal_html;
              if (global.ABBindGocharPhal) { global.ABBindGocharPhal(); }
              if (global.ABBindSadeTimeline) { global.ABBindSadeTimeline(); }
            }
          }
          // Optional consumer hook (e.g. the Mahurat page renders its own view
          // from the same transit result without a second server round-trip).
          // Second arg carries the transit moment's date/time/place for subtitles.
          if (typeof cfg.onResult === 'function') {
            try {
              cfg.onResult(g, { date: fDate.value, time: fTime.value, place: (fPlace.value || '').trim() });
            } catch (e) {}
          }
        })
        .catch(function (e) { status.textContent = 'Request failed: ' + e; });
    }

    function renderResult(g) {
      var planets = [];
      Object.keys(g.transits).forEach(function (name) {
        var t = g.transits[name];
        planets.push({ abbr: ABBR[name] || name.slice(0, 2), sign: t.sign_index, deg: t.deg, retro: !!t.retro });
      });
      var place = (fPlace.value || '').trim();
      header.innerHTML =
          '<span class="font-semibold text-gray-800">Gochar (Transit)</span>'
        + '<span class="ml-auto flex flex-wrap items-center gap-x-4">'
        +   '<span>' + esc(fDate.value) + '</span>'
        +   '<span>' + esc(fTime.value) + '</span>'
        +   (place ? '<span class="font-semibold text-gray-800">' + esc(place) + '</span>' : '')
        + '</span>';
      ABChart.renderNorth(chartBox, { asc_sign: g.ascendant.sign_index, planets: planets },
        { showDeg: true });
    }

    btn.addEventListener('click', fetchGochar);
    // Changing the date or time (or the advanced lat/lon/tz) recomputes at once,
    // so the transit chart AND the Gochar Phal predictions follow the new
    // moment without needing the button. 'change' fires on commit/blur, not on
    // every keystroke, so this is one fetch per change.
    [fDate, fTime, fLat, fLon, fTz].forEach(function (i) {
      i.addEventListener('change', fetchGochar);
    });

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
