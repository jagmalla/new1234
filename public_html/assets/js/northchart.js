/* Auto Business — North-Indian chart renderer (reusable component).
 *
 * Pure vanilla JS + inline SVG, no libraries. Draws a traditional North-Indian
 * (diamond) chart for any divisional or transit chart, given the lagna sign and
 * each planet's sign. Ascendant sits in house 1 (top-centre); houses are fixed,
 * signs rotate. Planet abbreviations are colour-coded (Parashara's Light style),
 * degrees shown on request, retrograde marked with R.
 *
 * Designed for reuse by the client/astrologer screens (Module 5d) and the gochar
 * panel as well as the /calc test page.
 *
 * Usage:
 *   ABChart.renderNorth(containerEl,
 *     {asc_sign:0..11, asc_deg:?, planets:[{abbr,sign,deg,retro}]},
 *     {title:'Rasi (D1)', showDeg:true});
 *   ABChart.renderAll(vargasObject);   // renders into elements with data-varga
 */
(function (global) {
  'use strict';

  var SIGNS = ['Ar','Ta','Ge','Cn','Le','Vi','Li','Sc','Sg','Cp','Aq','Pi'];

  // Planet abbreviation -> colour (Parashara's Light-like palette).
  var COLOR = {
    Su:'#dc2626', Mo:'#0891b2', Ma:'#ea580c', Me:'#16a34a', Ju:'#b45309',
    Ve:'#db2777', Sa:'#1d4ed8', Ra:'#6b7280', Ke:'#6b7280', As:'#111827'
  };

  // House label centroids in a 100x100 viewBox (North-Indian fixed houses).
  var C = {
    1:[50,25], 2:[25,12], 3:[12,25], 4:[25,50], 5:[12,75], 6:[25,88],
    7:[50,75], 8:[75,88], 9:[88,75], 10:[75,50], 11:[88,25], 12:[75,12]
  };
  // Where to tuck the small fixed house number inside each house region.
  var HN = {
    1:[50,40], 2:[15,5], 3:[5,15], 4:[40,50], 5:[5,85], 6:[15,95],
    7:[50,60], 8:[85,95], 9:[95,85], 10:[60,50], 11:[95,15], 12:[85,5]
  };

  function el(tag, attrs, text) {
    var e = document.createElementNS('http://www.w3.org/2000/svg', tag);
    for (var k in attrs) { e.setAttribute(k, attrs[k]); }
    if (text != null) { e.textContent = text; }
    return e;
  }

  function renderNorth(container, data, opts) {
    opts = opts || {};
    container.innerHTML = '';

    if (opts.title) {
      var h = document.createElement('div');
      h.className = 'text-xs font-semibold text-center mb-1 text-gray-700';
      h.textContent = opts.title;
      container.appendChild(h);
    }

    var svg = el('svg', {
      viewBox: '0 0 100 100', width: '100%', height: 'auto',
      'class': 'bg-amber-50 rounded'
    });

    // Frame: outer square, both diagonals, inner diamond.
    var line = function (x1,y1,x2,y2) {
      svg.appendChild(el('line', {x1:x1,y1:y1,x2:x2,y2:y2, stroke:'#92400e', 'stroke-width':0.5}));
    };
    svg.appendChild(el('rect', {x:1,y:1,width:98,height:98, fill:'none', stroke:'#92400e', 'stroke-width':0.9}));
    line(1,1,99,99); line(99,1,1,99);               // diagonals
    line(50,1,99,50); line(99,50,50,99);            // diamond
    line(50,99,1,50); line(1,50,50,1);

    var ascSign = ((data.asc_sign % 12) + 12) % 12;

    // Group planet labels by fixed house.
    var byHouse = {};
    for (var i = 1; i <= 12; i++) { byHouse[i] = []; }

    // Ascendant marker always sits in house 1.
    byHouse[1].push({
      abbr: 'As',
      txt: 'As' + (opts.showDeg && data.asc_deg != null ? ' ' + data.asc_deg + '°' : '')
    });

    (data.planets || []).forEach(function (p) {
      var house = (((p.sign - ascSign) % 12) + 12) % 12 + 1;
      var txt = p.abbr
        + (opts.showDeg && p.deg != null ? ' ' + p.deg + '°' : '')
        + (p.retro ? ' R' : '');
      byHouse[house].push({ abbr: p.abbr, txt: txt });
    });

    for (var hh = 1; hh <= 12; hh++) {
      var cx = C[hh][0], cy = C[hh][1];
      var signNum = ((ascSign + (hh - 1)) % 12);

      // Rotating sign number (prominent, as in Parashara's Light) + faint abbr.
      svg.appendChild(el('text', {
        x: cx, y: cy - 5, 'text-anchor':'middle', 'font-size':4.2,
        fill:'#b45309', 'font-weight':'700'
      }, String(signNum + 1)));

      // Fixed house number, small and faint, tucked toward the centre.
      svg.appendChild(el('text', {
        x: HN[hh][0], y: HN[hh][1], 'text-anchor':'middle', 'font-size':2.6,
        fill:'#cbd5e1'
      }, 'H' + hh));

      // Planets stacked under the sign number, colour-coded.
      var items = byHouse[hh];
      var n = items.length;
      var startY = cy - 0.5 - ((n - 1) * 4) / 2 + 4;
      for (var j = 0; j < n; j++) {
        svg.appendChild(el('text', {
          x: cx, y: startY + j * 4, 'text-anchor':'middle',
          'font-size': opts.big ? 4.0 : 3.7,
          fill: COLOR[items[j].abbr] || '#1d4ed8', 'font-weight':'600'
        }, items[j].txt));
      }
    }

    container.appendChild(svg);
  }

  function renderAll(vargas) {
    document.querySelectorAll('[data-varga]').forEach(function (elm) {
      var key = elm.getAttribute('data-varga');
      if (vargas[key]) {
        renderNorth(elm, vargas[key], {
          title: vargas[key].label,
          showDeg: key === 'D1',
          big: key === 'D1'
        });
      }
    });
  }

  global.ABChart = { renderNorth: renderNorth, renderAll: renderAll, COLOR: COLOR };
})(window);
