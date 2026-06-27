/* Auto Business — city search (reusable component).
 *
 * Type-ahead place search backed by the Open-Meteo geocoding API (keyless,
 * CORS-enabled, worldwide). As the user types a city it shows matching
 * "City, State/Region, Country" results; selecting one fills the latitude,
 * longitude and timezone fields so the chart/gochar is computed for that exact
 * place. Replaces the small embedded gazetteer.
 *
 * Usage:
 *   ABCitySearch.init({
 *     input:'#b-place', results:'#b-place-results',
 *     lat:'#b-lat', lon:'#b-lon', tz:'#b-tz',
 *     getDate: function(){ return new Date(2000,0,1); }  // optional: tz offset at this date
 *   });
 */
(function (global) {
  'use strict';

  function sel(x) { return (typeof x === 'string') ? document.querySelector(x) : x; }

  function debounce(fn, ms) {
    var t;
    return function () { clearTimeout(t); t = setTimeout(fn, ms); };
  }

  // UTC offset (hours, east +) for an IANA timezone at a given date — accounts
  // for that date's DST rules as the browser knows them.
  function ianaOffset(tz, date) {
    try {
      date = date || new Date();
      var utc = new Date(date.toLocaleString('en-US', { timeZone: 'UTC' }));
      var loc = new Date(date.toLocaleString('en-US', { timeZone: tz }));
      return Math.round((loc - utc) / 60000) / 60; // hours, to the minute
    } catch (e) { return null; }
  }

  function labelOf(r) {
    return [r.name, r.admin1, r.country].filter(Boolean).join(', ');
  }

  function init(opts) {
    var input = sel(opts.input), results = sel(opts.results);
    if (!input || !results) return null;
    var latEl = sel(opts.lat), lonEl = sel(opts.lon), tzEl = sel(opts.tz);
    var getDate = opts.getDate || function () { return new Date(); };

    function hide() { results.style.display = 'none'; results.innerHTML = ''; }

    function fill(r) {
      if (latEl) latEl.value = (+r.latitude).toFixed(4);
      if (lonEl) lonEl.value = (+r.longitude).toFixed(4);
      if (tzEl && r.timezone) {
        var off = ianaOffset(r.timezone, getDate());
        if (off != null) tzEl.value = off;
      }
      input.value = labelOf(r);
      if (opts.onSelect) opts.onSelect(r);
    }

    var run = debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { hide(); return; }
      fetch('https://geocoding-api.open-meteo.com/v1/search?name=' + encodeURIComponent(q) + '&count=8&language=en&format=json')
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var list = (d && d.results) || [];
          if (!list.length) { results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">No matches</div>'; results.style.display = 'block'; return; }
          results.innerHTML = '';
          list.forEach(function (r) {
            var item = document.createElement('div');
            item.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
            item.textContent = labelOf(r);
            item.addEventListener('mousedown', function (e) { e.preventDefault(); fill(r); hide(); });
            results.appendChild(item);
          });
          results.style.display = 'block';
        })
        .catch(function () { hide(); });
    }, 300);

    input.addEventListener('input', run);
    input.addEventListener('focus', function () { if (results.innerHTML) results.style.display = 'block'; });
    input.addEventListener('blur', function () { setTimeout(hide, 200); });

    return { fill: fill, setLabel: function (s) { input.value = s; }, ianaOffset: ianaOffset };
  }

  global.ABCitySearch = { init: init, ianaOffset: ianaOffset, labelOf: labelOf };
})(window);
