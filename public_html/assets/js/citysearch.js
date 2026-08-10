/* Auto Business — city search (reusable component).
 *
 * Type-ahead place search over the system's OWN gazetteer (1,48,038 towns,
 * shipped with the app: app/Astro/Geo/cities.tsv.gz, searched by
 * `GET calc/citySearch`). As the user types a city it shows matching
 * "City, State/Region, Country" results; selecting one fills the latitude,
 * longitude and timezone fields so the chart/gochar is computed for that exact
 * place.
 *
 * OFFLINE FIRST. The search used to go straight to the Open-Meteo geocoding
 * API, which meant that with no internet — on localhost, or wherever the net
 * does not reach — a birth place could not be picked at all, and without
 * coordinates no calculation starts. The local gazetteer is now the primary
 * source; Open-Meteo is only consulted when the local one finds nothing AND
 * the browser reports itself online, so a rare hamlet still resolves when a
 * connection happens to be there.
 *
 * TIMEZONE / DST: the offset written into the tz field is resolved for the
 * *entered birth date* (not "today"), so a summer birth gets the daylight
 * offset and a winter birth gets the standard offset. The base offset comes
 * from the browser's IANA database; a small deterministic override table then
 * corrects zones whose modern rules the browser may not know yet (e.g. British
 * Columbia's permanent Daylight Time from 2026-03-08). Re-computes if the
 * birth date/time is edited after a place is chosen.
 *
 * Usage:
 *   ABCitySearch.init({
 *     input:'#b-place', results:'#b-place-results',
 *     lat:'#b-lat', lon:'#b-lon', tz:'#b-tz',
 *     dateInput:'[name="date"]', timeInput:'[name="time"]'  // birth date/time -> DST-correct tz
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

  // ---- Robust birth date/time parsing -------------------------------------
  // The form takes the birth date as DD-MM-YYYY (or "DD MM YYYY", "DD/MM/YYYY")
  // and 4-digit-first as YYYY-MM-DD. Native `new Date("08-03-2026")` is
  // unreliable for these, so parse the fields explicitly and build a real Date.
  function parseBirthDate(dateStr, timeStr) {
    if (!dateStr) return new Date();
    var p = String(dateStr).trim().split(/[-\/.\s]+/).filter(Boolean).map(Number);
    if (p.length !== 3 || p.some(function (n) { return isNaN(n); })) return new Date();
    var y, mo, d;
    if (p[0] > 31) { y = p[0]; mo = p[1]; d = p[2]; }   // YYYY-MM-DD
    else { d = p[0]; mo = p[1]; y = p[2]; }             // DD-MM-YYYY
    var tp = String(timeStr || '').trim().split(/[:\s.]+/).filter(Boolean).map(Number);
    var hh = tp.length ? (tp[0] || 0) : 12;             // default noon (DST-safe)
    var mm = tp.length > 1 ? (tp[1] || 0) : 0;
    var dt = new Date(y, (mo || 1) - 1, (d || 1), hh, mm, 0);
    return isNaN(dt) ? new Date() : dt;
  }

  // ---- Deterministic timezone overrides -----------------------------------
  // North-American DST (2007-): 2nd Sunday of March 02:00 .. 1st Sunday of
  // November 02:00. `nthSunday` returns the day-of-month of the nth Sunday.
  function nthSunday(year, month, n) {
    var firstDow = new Date(year, month - 1, 1).getDay(); // 0 = Sunday
    var firstSun = 1 + ((7 - firstDow) % 7);
    return firstSun + (n - 1) * 7;
  }
  function naDaylight(y, m, d) {
    var start = nthSunday(y, 3, 2);   // spring forward (March)
    var end = nthSunday(y, 11, 1);    // fall back (November)
    var afterStart = (m > 3) || (m === 3 && d >= start);
    var beforeEnd = (m < 11) || (m === 11 && d < end);
    return afterStart && beforeEnd;
  }
  function onOrAfter(y, m, d, yy, mm, dd) {
    if (y !== yy) return y > yy;
    if (m !== mm) return m > mm;
    return d >= dd;
  }

  // Offset (hours, east +) for zones whose current rules the browser's tz data
  // may not encode. Returns null when the zone has no special rule (caller then
  // uses the browser's IANA offset).
  function overrideOffset(tz, date) {
    var y = date.getFullYear(), m = date.getMonth() + 1, d = date.getDate();
    switch (tz) {
      // British Columbia — observed normal Pacific DST until it adopts
      // permanent Daylight Time on 2026-03-08 (2nd Sunday of March), then stays
      // on PDT (-7) year-round. Before that date, standard Pacific rules apply.
      case 'America/Vancouver':
      case 'America/Fort_Nelson':
        if (onOrAfter(y, m, d, 2026, 3, 8)) return -7;
        return naDaylight(y, m, d) ? -7 : -8;
      // Yukon — stopped changing clocks in 2020; permanent Daylight Time (-7).
      case 'America/Whitehorse':
      case 'America/Dawson':
        if (onOrAfter(y, m, d, 2020, 11, 1)) return -7;
        return naDaylight(y, m, d) ? -7 : -8;
      // Saskatchewan — permanent Standard Time (CST, -6) since 1966; no DST.
      case 'America/Regina':
      case 'America/Swift_Current':
        if (y >= 1966) return -6;
        return null;
      default:
        return null;
    }
  }

  // Final offset for a place on a given date: deterministic override if we have
  // one, otherwise the browser's IANA offset for that date.
  function resolveOffset(tz, date) {
    var o = overrideOffset(tz, date);
    return (o != null) ? o : ianaOffset(tz, date);
  }

  function labelOf(r) {
    return [r.name, r.admin1, r.country].filter(Boolean).join(', ');
  }

  function init(opts) {
    var input = sel(opts.input), results = sel(opts.results);
    if (!input || !results) return null;
    var latEl = sel(opts.lat), lonEl = sel(opts.lon), tzEl = sel(opts.tz);
    var dateEl = sel(opts.dateInput), timeEl = sel(opts.timeInput);
    var getDate = opts.getDate || function () {
      return parseBirthDate(dateEl && dateEl.value, timeEl && timeEl.value);
    };
    var lastZone = null; // remember the chosen place so date edits re-resolve tz

    function hide() { results.style.display = 'none'; results.innerHTML = ''; }

    function applyTz() {
      if (!tzEl || !lastZone) return;
      var off = resolveOffset(lastZone, getDate());
      if (off != null) tzEl.value = off;
    }

    function fill(r) {
      if (latEl) latEl.value = (+r.latitude).toFixed(4);
      if (lonEl) lonEl.value = (+r.longitude).toFixed(4);
      if (r.timezone) { lastZone = r.timezone; applyTz(); }
      input.value = labelOf(r);
      if (opts.onSelect) opts.onSelect(r);
    }

    function draw(list, note) {
      if (!list.length) {
        results.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">कोई स्थान नहीं मिला / No matches</div>';
        results.style.display = 'block';
        return;
      }
      results.innerHTML = '';
      list.forEach(function (r) {
        var item = document.createElement('div');
        item.className = 'px-3 py-2 text-sm cursor-pointer hover:bg-blue-50';
        item.textContent = labelOf(r);
        item.addEventListener('mousedown', function (e) { e.preventDefault(); fill(r); hide(); });
        results.appendChild(item);
      });
      if (note) {
        var n = document.createElement('div');
        n.className = 'px-3 py-1 text-xs text-gray-400 border-t';
        n.textContent = note;
        results.appendChild(n);
      }
      results.style.display = 'block';
    }

    // ऑनलाइन सेवा का जवाब अपने कोश जैसा बना दो, ताकि आगे का काम एक ही रहे।
    function fromOpenMeteo(d) {
      return ((d && d.results) || []).map(function (r) {
        return { name: r.name, admin1: r.admin1, country: r.country,
                 latitude: r.latitude, longitude: r.longitude, timezone: r.timezone };
      });
    }

    var run = debounce(function () {
      var q = input.value.trim();
      if (q.length < 2) { hide(); return; }
      // पहले अपना कोश — यही बिना इंटरनेट भी चलता है
      fetch('/calc/citySearch?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          var list = ((d && d.results) || []).map(function (r) {
            return { name: r.name, admin1: r.admin, country: r.country,
                     latitude: r.lat, longitude: r.lon, timezone: r.tz };
          });
          if (list.length) { draw(list); return; }
          // कोश में न मिला — तभी बाहर पूछो, और तभी जब नेट है
          if (navigator.onLine === false) { draw([]); return; }
          return fetch('https://geocoding-api.open-meteo.com/v1/search?name=' + encodeURIComponent(q) + '&count=8&language=en&format=json')
            .then(function (r) { return r.json(); })
            .then(function (od) { draw(fromOpenMeteo(od), 'ऑनलाइन खोज से'); })
            .catch(function () { draw([]); });
        })
        .catch(function () { hide(); });
    }, 260);

    input.addEventListener('input', run);
    input.addEventListener('focus', function () { if (results.innerHTML) results.style.display = 'block'; });
    input.addEventListener('blur', function () { setTimeout(hide, 200); });

    // If the birth date/time is edited after a place was chosen, re-resolve the
    // offset so summer/winter (and permanent-DST rules) stay correct.
    if (dateEl) dateEl.addEventListener('change', applyTz);
    if (timeEl) timeEl.addEventListener('change', applyTz);

    return {
      fill: fill,
      setLabel: function (s) { input.value = s; },
      ianaOffset: ianaOffset,
      resolveOffset: resolveOffset,
      refreshTz: applyTz
    };
  }

  global.ABCitySearch = {
    init: init,
    ianaOffset: ianaOffset,
    resolveOffset: resolveOffset,
    overrideOffset: overrideOffset,
    parseBirthDate: parseBirthDate,
    labelOf: labelOf
  };
})(window);
