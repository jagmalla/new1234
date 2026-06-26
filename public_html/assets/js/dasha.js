/* Auto Business — expandable Dasha tree (reusable component).
 *
 * Pure vanilla JS. Renders a Vimshottari- or Mudda-style dasha as an expandable
 * tree, drilling 5 levels deep:
 *   Mahadasha -> Antardasha -> Pratyantardasha -> Sukshma -> Prana.
 *
 * Children are computed lazily in the browser from the standard Vimshottari
 * proportions, so the server only ships the 9 top-level periods (tiny payload)
 * rather than 9^5 leaves. Dates are converted from Julian Day (UT) to
 * DD-MM-YYYY at the chart's timezone.
 *
 * Usage:
 *   ABDasha.render(containerEl, topPeriods, {tz: 5.5});
 *     topPeriods = [{lord:'Venus', start_jd:.., end_jd:..}, ...]
 */
(function (global) {
  'use strict';

  var LORDS = ['Ketu','Venus','Sun','Moon','Mars','Rahu','Jupiter','Saturn','Mercury'];
  var YEARS = {Ketu:7, Venus:20, Sun:6, Moon:10, Mars:7, Rahu:18, Jupiter:16, Saturn:19, Mercury:17};
  var LEVEL = ['Mahadasha','Antardasha','Pratyantardasha','Sukshma','Prana'];

  // Same per-planet colours as the chart renderer, if present.
  var COLOR = (global.ABChart && global.ABChart.COLOR) || {};
  var PCOL = {
    Ketu:'#6b7280', Venus:'#db2777', Sun:'#dc2626', Moon:'#0891b2', Mars:'#ea580c',
    Rahu:'#6b7280', Jupiter:'#b45309', Saturn:'#1d4ed8', Mercury:'#16a34a'
  };

  function jdToDMY(jd, tz) {
    // JD (UT) -> Gregorian calendar date at the given timezone (hours east +).
    var z = jd + 0.5 + (tz || 0) / 24.0;
    var Z = Math.floor(z);
    var A = Z;
    if (Z >= 2299161) {
      var alpha = Math.floor((Z - 1867216.25) / 36524.25);
      A = Z + 1 + alpha - Math.floor(alpha / 4);
    }
    var B = A + 1524;
    var Cc = Math.floor((B - 122.1) / 365.25);
    var D = Math.floor(365.25 * Cc);
    var E = Math.floor((B - D) / 30.6001);
    var day = B - D - Math.floor(30.6001 * E);
    var month = (E < 14) ? E - 1 : E - 13;
    var year = (month > 2) ? Cc - 4716 : Cc - 4715;
    var p2 = function (x) { return (x < 10 ? '0' : '') + x; };
    return p2(day) + '-' + p2(month) + '-' + year;
  }

  // Children of a period: the 9 lords in order starting from the parent lord,
  // each portion proportional to its Vimshottari years.
  function children(period) {
    var startIdx = LORDS.indexOf(period.lord);
    var span = period.end_jd - period.start_jd;
    var out = [], cursor = period.start_jd;
    for (var k = 0; k < 9; k++) {
      var lord = LORDS[(startIdx + k) % 9];
      var end = cursor + span * (YEARS[lord] / 120.0);
      out.push({ lord: lord, start_jd: cursor, end_jd: end });
      cursor = end;
    }
    return out;
  }

  function row(period, level, tz, inline) {
    var wrap = document.createElement('div');
    var head = document.createElement('div');
    head.className = 'flex items-center gap-2 py-1 cursor-pointer select-none hover:bg-gray-50 rounded';
    head.style.paddingLeft = (level * 18 + 4) + 'px';

    var canExpand = level < 4;
    var tw = document.createElement('span');
    tw.className = 'inline-block w-4 text-center text-gray-400 font-mono text-xs';
    tw.textContent = canExpand ? '+' : '·';
    head.appendChild(tw);

    var dot = document.createElement('span');
    dot.className = 'inline-block w-2 h-2 rounded-full';
    dot.style.background = PCOL[period.lord] || '#6b7280';
    head.appendChild(dot);

    var name = document.createElement('span');
    name.className = 'font-medium';
    name.style.color = PCOL[period.lord] || '#111827';
    name.textContent = period.lord;
    head.appendChild(name);

    var lvl = document.createElement('span');
    lvl.className = 'text-xs text-gray-400';
    lvl.textContent = LEVEL[level];
    head.appendChild(lvl);

    var dates = document.createElement('span');
    // Default: push dates to the right edge. inline: keep them next to the name
    // (used in the wide Detail view so the dates aren't far from the planet).
    dates.className = 'text-xs text-gray-600 whitespace-nowrap ' + (inline ? 'ml-3' : 'ml-auto');
    dates.textContent = jdToDMY(period.start_jd, tz) + '  →  ' + jdToDMY(period.end_jd, tz);
    head.appendChild(dates);

    wrap.appendChild(head);

    if (canExpand) {
      var kids = document.createElement('div');
      kids.className = 'hidden';
      var built = false;
      head.addEventListener('click', function () {
        if (!built) {
          children(period).forEach(function (c) { kids.appendChild(row(c, level + 1, tz, inline)); });
          built = true;
        }
        var open = kids.classList.toggle('hidden') === false;
        tw.textContent = open ? '−' : '+';
      });
      wrap.appendChild(kids);
    }
    return wrap;
  }

  function render(container, topPeriods, opts) {
    opts = opts || {};
    container.innerHTML = '';
    (topPeriods || []).forEach(function (p) {
      container.appendChild(row({ lord: p.lord, start_jd: p.start_jd, end_jd: p.end_jd }, 0, opts.tz || 0, !!opts.datesInline));
    });
  }

  global.ABDasha = { render: render, jdToDMY: jdToDMY };
})(window);
