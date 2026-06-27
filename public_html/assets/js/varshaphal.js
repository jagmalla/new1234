/* Auto Business — Varshaphal (annual chart) question box (reusable component).
 *
 * Renders a "which year?" input. On submit it fetches the annual (solar-return)
 * chart + Mudda dasha for that year from the calc/varshaphal JSON endpoint, then
 * draws the Varsha chart (North-Indian) and the Mudda dasha tree (5 levels)
 * starting from that year's Varsha Pravesh date.
 *
 * Depends on: northchart.js (ABChart), dasha.js (ABDasha).
 *
 * Usage:
 *   ABVarsha.init({ box:'#vp-box', output:'#vp-output',
 *                   birth:{date,lat,lon,tz,ayanamsa}, tz:5.5, year:2026 });
 */
(function (global) {
  'use strict';

  function h(tag, cls, html) { var e = document.createElement(tag); if (cls) e.className = cls; if (html != null) e.innerHTML = html; return e; }
  function sel(x) { return (typeof x === 'string') ? document.querySelector(x) : x; }

  function init(cfg) {
    cfg = cfg || {};
    var boxRoot = sel(cfg.box), outRoot = sel(cfg.output);
    if (!boxRoot || !outRoot) return;
    var birth = cfg.birth || {};
    var tz = cfg.tz || 0;

    boxRoot.innerHTML = '';
    var row = h('div', 'flex flex-wrap items-end gap-3 text-sm');
    var lab = h('label', 'flex flex-col gap-1');
    lab.appendChild(h('span', 'text-gray-500', 'Varshaphal for which year?'));
    var fYear = h('input', 'border rounded px-2 py-1 w-32');
    fYear.type = 'number'; fYear.value = cfg.year || new Date().getFullYear();
    lab.appendChild(fYear);
    row.appendChild(lab);
    var btn = h('button', 'bg-blue-600 text-white rounded px-4 py-2 font-semibold', 'Show Varshaphal');
    row.appendChild(btn);
    var status = h('span', 'text-xs text-gray-500'); row.appendChild(status);
    boxRoot.appendChild(row);

    // Output scaffold: summary, then chart | mudda dasha.
    outRoot.innerHTML = '';
    var summary = h('div', 'text-sm mb-3');
    // Stack vertically: small annual chart on top, then the Mudda dasha full
    // width so its two-column (name | date) layout has room.
    var stack = h('div', 'space-y-4');
    var chartCell = h('div', 'bg-white rounded-lg shadow p-3');
    var chartBox = h('div', 'max-w-xs mx-auto');
    chartCell.appendChild(h('div', 'text-sm font-semibold text-center mb-2 text-gray-700', 'Varsha (Annual) Chart'));
    chartCell.appendChild(chartBox);
    var dashaCell = h('div', 'bg-white rounded-lg shadow p-3');
    dashaCell.appendChild(h('h3', 'font-semibold mb-2 text-sm', 'Mudda Dasha — drill 5 levels'));
    var dashaBox = h('div', 'text-sm');
    dashaCell.appendChild(dashaBox);
    stack.appendChild(chartCell); stack.appendChild(dashaCell);
    outRoot.appendChild(summary); outRoot.appendChild(stack);

    function fetchVp() {
      status.textContent = 'calculating…';
      var q = new URLSearchParams({
        year: fYear.value,
        bdate: birth.date || '', btime: birth.time || '',
        blat: birth.lat != null ? birth.lat : '', blon: birth.lon != null ? birth.lon : '',
        btz: birth.tz != null ? birth.tz : '', ayanamsa: birth.ayanamsa || 'lahiri'
      });
      fetch('/calc/varshaphal?' + q.toString(), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (v) {
          if (v.error) { status.textContent = 'Error: ' + v.error; return; }
          status.textContent = '';
          summary.innerHTML = 'Year <b>' + v.year + '</b> · Varsha Lagna <b>' + v.varsha_lagna.sign
            + '</b> (lord ' + v.varsha_lagna.lord + ') · Muntha ' + v.muntha.sign
            + ' (lord ' + v.muntha.lord + ') · age ' + v.age_completed;
          ABChart.renderNorth(chartBox, v.chart, { title: v.ascendant_formatted, showDeg: true, big: true });
          ABDasha.render(dashaBox, v.mudda_dasha, { tz: tz, datesInline: true, maxRows: 10 });
        })
        .catch(function (e) { status.textContent = 'Request failed: ' + e; });
    }

    btn.addEventListener('click', fetchVp);
    fetchVp();
  }

  global.ABVarsha = { init: init };
})(window);
