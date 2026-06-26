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

  // Planet abbreviation -> colour (matches the Dasha palette / legend exactly).
  var COLOR = {
    Su:'#dc2626', Mo:'#0891b2', Ma:'#ea580c', Me:'#16a34a', Ju:'#b45309',
    Ve:'#db2777', Sa:'#1d4ed8', Ra:'#3d4554', Ke:'#3d4554', As:'#111827'
  };

  // Exaltation (ex) and debilitation (de) sign index per planet (0=Aries…11=Pi).
  // ↑ shown when a planet sits in its exalted sign, ↓ when debilitated.
  var DIGN = {
    Su:{ex:0, de:6},  Mo:{ex:1, de:7},  Ma:{ex:9, de:3},  Me:{ex:5, de:11},
    Ju:{ex:3, de:9},  Ve:{ex:11, de:5}, Sa:{ex:6, de:0},
    Ra:{ex:1, de:7},  Ke:{ex:7, de:1}
  };

  // House label centroids in a 100x100 viewBox (North-Indian fixed houses):
  // planet abbreviations sit here, toward the outer body of each house.
  var C = {
    1:[50,25], 2:[25,12], 3:[10,25], 4:[25,50], 5:[10,75], 6:[25,88],
    7:[50,75], 8:[75,88], 9:[90,75], 10:[75,50], 11:[90,25], 12:[75,12]
  };
  // Rashi (sign) number anchors — each tucked just inside its own house, against
  // the inner vertex, with a clear margin from every dividing line so the number
  // never spills into the neighbouring house. The 4 central diamond houses sit
  // just off the centre (50,50); the 8 triangles sit just off their corner.
  var INNER = {
    1:[50,44], 4:[44,50], 7:[50,56], 10:[56,50],     // diamonds, around centre
    2:[25,20], 3:[20,25],                            // top-left corner (25,25)
    12:[75,20], 11:[80,25],                          // top-right corner (75,25)
    5:[20,75], 6:[25,80],                            // bottom-left corner (25,75)
    9:[80,75], 8:[75,80]                             // bottom-right corner (75,75)
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
      viewBox: '0 0 100 100', width: '100%', height: 'auto', 'class': 'rounded'
    });

    // Chart background fill (rendered as part of the SVG so it shows everywhere).
    svg.appendChild(el('rect', {x:0, y:0, width:100, height:100, fill:'#f0f9ff'}));

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
      var d = DIGN[p.abbr];
      var mark = d ? (p.sign === d.ex ? '↑' : (p.sign === d.de ? '↓' : '')) : '';
      var txt = p.abbr + mark
        + (opts.showDeg && p.deg != null ? ' ' + p.deg + '°' : '')
        + (p.retro ? ' R' : '');
      byHouse[house].push({ abbr: p.abbr, txt: txt });
    });

    for (var hh = 1; hh <= 12; hh++) {
      var cx = C[hh][0], cy = C[hh][1];
      var signNum = ((ascSign + (hh - 1)) % 12);

      // Rashi (sign) number only — black, tucked at the house's inner corner.
      svg.appendChild(el('text', {
        x: INNER[hh][0], y: INNER[hh][1] + 1.2, 'text-anchor':'middle',
        'font-size':3.5, fill:'#000000', 'font-weight':'700'
      }, String(signNum + 1)));

      // Planets centred in the house body, colour-coded (Dasha palette).
      var items = byHouse[hh];
      var n = items.length;
      var lineH = 4.2;
      var startY = cy - ((n - 1) * lineH) / 2 + 1.3;
      for (var j = 0; j < n; j++) {
        svg.appendChild(el('text', {
          x: cx, y: startY + j * lineH, 'text-anchor':'middle',
          'font-size': opts.big ? 4.2 : 3.8,
          fill: COLOR[items[j].abbr] || '#111827', 'font-weight':'600'
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
          showDeg: true,
          big: key === 'D1'
        });
      }
    });
  }

  global.ABChart = { renderNorth: renderNorth, renderAll: renderAll, COLOR: COLOR };
})(window);
