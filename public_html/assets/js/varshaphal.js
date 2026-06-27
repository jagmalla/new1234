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
    var summaryRoot = sel(cfg.summary) || null;
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

    // Summary (Varsha Lagna / Muntha / Varshesh / age) renders in the year row
    // when a summary target is given; otherwise it sits above the output.
    var summary = h('div', 'text-sm');
    if (summaryRoot) { summaryRoot.innerHTML = ''; summaryRoot.appendChild(summary); }

    // Output: Varsha chart | Mudda dasha side by side; the dasha fills the chart
    // height and scrolls.
    outRoot.innerHTML = '';
    var grid = h('div', 'grid grid-cols-1 lg:grid-cols-2 gap-4 items-start');
    var chartCell = h('div', 'bg-white rounded-lg shadow p-3 flex flex-col');
    chartCell.appendChild(h('div', 'text-sm font-semibold text-center mb-2 text-gray-700', 'Varsha (Annual) Chart'));
    var chartBox = h('div', 'w-full max-w-md mx-auto');
    chartCell.appendChild(chartBox);
    var dashaCell = h('div', 'bg-white rounded-lg shadow p-3');
    dashaCell.appendChild(h('h3', 'font-semibold mb-2 text-sm', 'Mudda Dasha — drill 5 levels'));
    var dashaBox = h('div', 'text-sm');
    dashaCell.appendChild(dashaBox);
    grid.appendChild(chartCell); grid.appendChild(dashaCell);
    if (!summaryRoot) { outRoot.appendChild(summary); }
    outRoot.appendChild(grid);

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
          summary.innerHTML =
              '<div><b>Year</b> ' + v.year + '</div>'
            + '<div><b>Varsha Lagna</b> ' + v.varsha_lagna.sign + ' (lord ' + v.varsha_lagna.lord + ')</div>'
            + '<div><b>Muntha</b> ' + v.muntha.sign + ' (lord ' + v.muntha.lord + ')</div>'
            + '<div><b>Age</b> ' + v.age_completed + '</div>'
            + '<div><b>Varshaphal Date</b>: ' + (v.varsha_start || '—') + '</div>';
          // Show the Muntha as a "MUN" marker in its house on the Varsha chart.
          var chart = v.chart;
          if (v.muntha_sign_index != null) {
            chart = { asc_sign: v.chart.asc_sign, asc_deg: v.chart.asc_deg, planets: v.chart.planets.slice() };
            chart.planets.push({ abbr: 'MUN', sign: v.muntha_sign_index, retro: false });
          }
          ABChart.renderNorth(chartBox, chart, { title: v.ascendant_formatted, showDeg: true, big: true });
          ABDasha.render(dashaBox, v.mudda_dasha, { tz: tz, datesInline: true, maxRows: 12 });
        })
        .catch(function (e) { status.textContent = 'Request failed: ' + e; });
    }

    btn.addEventListener('click', fetchVp);
    fetchVp();
  }

  global.ABVarsha = { init: init };
})(window);
