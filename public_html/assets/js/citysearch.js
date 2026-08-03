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

  // Enacted timezone rules not yet shipped in the browser's tz database. Mirrors
  // the server-side TimeZoneResolver so the displayed offset matches the chart.
  // Each entry: fixed offset (hours east) for dates on/after `from`.
  var TZ_OVERRIDES = {
    // British Columbia: permanent Daylight Saving Time (UTC-7) — no fall-back to
    // UTC-8 in winter — from the 2026 spring-forward date.
    'America/Vancouver': [{ from: '2026-03-08', offset: -7 }]
  };

  function ymd(date) {
    return date.getFullYear() + '-' +
      ('0' + (date.getMonth() + 1)).slice(-2) + '-' +
      ('0' + date.getDate()).slice(-2);
  }

  function overrideOffset(tz, date) {
    var rules = TZ_OVERRIDES[tz];
    if (!rules) return null;
    var d = ymd(date);
    for (var i = 0; i < rules.length; i++) {
      if (d >= rules[i].from) return rules[i].offset;
    }
    return null;
  }

  // UTC offset (hours, east +) for an IANA timezone on a given date — accounts
  // for that date's DST rules (and any enacted override) so the offset is right
  // for the specific date, not just "now".
  function ianaOffset(tz, date) {
    try {
      date = date || new Date();
      var ov = overrideOffset(tz, date);
      if (ov != null) return ov;
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
    var latEl = sel(opts.lat), lonEl = sel(opts.lon), tzEl = sel(opts.tz), tzidEl = sel(opts.tzid);
    var getDate = opts.getDate || function () { return new Date(); };
    // Remember the selected IANA zone so the offset can be re-derived whenever the
    // date changes (DST/rule differences make the offset date-dependent).
    var lastZone = (tzidEl && tzidEl.value) || null;

    function hide() { results.style.display = 'none'; results.innerHTML = ''; }

    // Re-derive the numeric offset from the remembered zone for the current date.
    function recompute() {
      if (!lastZone || !tzEl) return;
      var off = ianaOffset(lastZone, getDate());
      if (off != null) tzEl.value = off;
    }

    function fill(r) {
      if (latEl) latEl.value = (+r.latitude).toFixed(4);
      if (lonEl) lonEl.value = (+r.longitude).toFixed(4);
      if (r.timezone) {
        lastZone = r.timezone;
        if (tzidEl) tzidEl.value = r.timezone;
        if (tzEl) {
          var off = ianaOffset(r.timezone, getDate());
          if (off != null) tzEl.value = off;
        }
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

    return {
      fill: fill,
      setLabel: function (s) { input.value = s; },
      ianaOffset: ianaOffset,
      recompute: recompute,
      // The IANA zone id backing the current offset (empty when none selected).
      zone: function () { return lastZone || ''; }
    };
  }

  global.ABCitySearch = { init: init, ianaOffset: ianaOffset, labelOf: labelOf };
})(window);
