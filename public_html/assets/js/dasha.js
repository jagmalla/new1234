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
    Ketu:'#3d4554', Venus:'#db2777', Sun:'#dc2626', Moon:'#0891b2', Mars:'#ea580c',
    Rahu:'#3d4554', Jupiter:'#b45309', Saturn:'#1d4ed8', Mercury:'#16a34a'
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
    head.className = 'py-1 cursor-pointer select-none hover:bg-gray-50';
    head.style.display = 'flex';
    head.style.alignItems = 'stretch';
    // Thin separator line between every dasha period.
    head.style.borderBottom = '1px solid #e8ecf3';

    // Name column: in the wide Detail view (inline) it is a fixed width so the
    // date column always starts at the same x for all five levels. The width is
    // sized for the widest content — deepest indent + "Mercury" + the longest
    // level label ("Pratyantardasha"). The narrow Chart-view column keeps auto
    // width with right-aligned dates.
    var nameCell = document.createElement('div');
    nameCell.className = 'whitespace-nowrap';
    nameCell.style.display = 'flex';
    nameCell.style.alignItems = 'center';
    nameCell.style.gap = '0.5rem';
    nameCell.style.boxSizing = 'border-box';
    if (inline) {
      nameCell.style.flex = '0 0 18rem';   // fixed name column so dates align
      nameCell.style.width = '18rem';
      nameCell.style.borderRight = '1px solid #e8ecf3';
    }
    nameCell.style.paddingLeft = (level * 18 + 4) + 'px';

    var canExpand = level < 4;
    var tw = document.createElement('span');
    tw.className = 'inline-block w-4 text-center text-gray-400 font-mono text-xs';
    tw.textContent = canExpand ? '+' : '·';
    nameCell.appendChild(tw);

    var dot = document.createElement('span');
    dot.className = 'inline-block w-2 h-2 rounded-full shrink-0';
    dot.style.background = PCOL[period.lord] || '#6b7280';
    nameCell.appendChild(dot);

    var name = document.createElement('span');
    name.className = 'font-medium';
    name.style.color = PCOL[period.lord] || '#111827';
    name.textContent = period.lord;
    nameCell.appendChild(name);

    var lvl = document.createElement('span');
    lvl.className = 'text-xs text-gray-400';
    lvl.textContent = LEVEL[level];
    nameCell.appendChild(lvl);
    head.appendChild(nameCell);

    var dates = document.createElement('span');
    // inline (Detail): dates begin just past the fixed name-column edge.
    // Otherwise (Chart view): pushed to the right edge.
    dates.className = 'text-xs whitespace-nowrap ' + (inline ? 'pl-3' : 'ml-auto');
    dates.style.display = 'flex';
    dates.style.alignItems = 'center';
    dates.style.color = '#000000';      // date text: dark black …
    dates.style.fontWeight = '700';     // … and bold
    if (inline) { dates.style.paddingLeft = '0.75rem'; }
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

    // Fixed-height scroll window: show ~maxRows rows; the rest scrolls inside the
    // box instead of expanding the section. Row height is measured live (after a
    // tick, so utility-class styling/padding is applied first) so the window
    // matches the real row height.
    if (opts.maxRows) {
      var applyScroll = function () {
        var firstHead = container.firstChild ? container.firstChild.firstChild : null;
        var rh = firstHead ? firstHead.getBoundingClientRect().height : 0;
        if (!rh) { rh = 30; }   // fallback if container not laid out (hidden tab)
        container.style.maxHeight = Math.round(opts.maxRows * rh) + 'px';
        container.style.overflowY = 'auto';
        container.style.overflowX = 'hidden';
      };
      setTimeout(applyScroll, 120);
    }
  }

  global.ABDasha = { render: render, jdToDMY: jdToDMY };
})(window);
