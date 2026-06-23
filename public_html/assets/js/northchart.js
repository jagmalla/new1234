/* Auto Business — North-Indian chart renderer (reusable component).
 *
 * Pure vanilla JS + inline SVG, no libraries. Draws a traditional North-Indian
 * (diamond) chart for any divisional chart, given the lagna sign and each
 * planet's sign. Designed to be reused by the client/astrologer screens
 * (Module 5d) as well as the /calc test page.
 *
 * Usage:
 *   ABChart.renderNorth(containerEl, {asc_sign: 0..11, planets:[{abbr,sign,deg,retro}]},
 *                       {title: 'Rasi (D1)', showDeg: true});
 *   ABChart.renderAll(vargasObject);   // renders into elements with data-varga
 */
(function (global) {
  'use strict';

  var SIGNS = ['Ar','Ta','Ge','Cn','Le','Vi','Li','Sc','Sg','Cp','Aq','Pi'];

  // House label centroids in a 100x100 viewBox (North-Indian fixed houses).
  var C = {
    1:[50,23], 2:[26,10], 3:[10,26], 4:[23,50], 5:[10,74], 6:[26,90],
    7:[50,77], 8:[74,90], 9:[90,74], 10:[77,50], 11:[90,26], 12:[74,10]
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
      h.className = 'text-xs font-semibold text-center mb-1';
      h.textContent = opts.title;
      container.appendChild(h);
    }

    var svg = el('svg', {
      viewBox: '0 0 100 100', width: '100%', height: 'auto',
      'class': 'border border-gray-300 bg-white'
    });

    // Frame: outer square, both diagonals, inner diamond.
    var line = function (x1,y1,x2,y2) {
      svg.appendChild(el('line', {x1:x1,y1:y1,x2:x2,y2:y2, stroke:'#374151', 'stroke-width':0.6}));
    };
    svg.appendChild(el('rect', {x:0,y:0,width:100,height:100, fill:'none', stroke:'#374151', 'stroke-width':0.8}));
    line(0,0,100,100); line(100,0,0,100);          // diagonals
    line(50,0,100,50); line(100,50,50,100);         // diamond
    line(50,100,0,50); line(0,50,50,0);

    var ascSign = ((data.asc_sign % 12) + 12) % 12;

    // Group planets by house.
    var byHouse = {};
    for (var i = 1; i <= 12; i++) { byHouse[i] = []; }
    (data.planets || []).forEach(function (p) {
      var house = (((p.sign - ascSign) % 12) + 12) % 12 + 1;
      var label = p.abbr + (opts.showDeg && p.deg != null ? '°' + p.deg : '') + (p.retro ? '(R)' : '');
      byHouse[house].push(label);
    });

    for (var hh = 1; hh <= 12; hh++) {
      var cx = C[hh][0], cy = C[hh][1];
      var signNum = ((ascSign + (hh - 1)) % 12);
      // Faint sign abbreviation/number as the house anchor.
      svg.appendChild(el('text', {
        x: cx, y: cy - 4, 'text-anchor':'middle', 'font-size':3.4,
        fill:'#9ca3af'
      }, (signNum + 1) + ' ' + SIGNS[signNum]));
      // Planets stacked under the sign label.
      var planets = byHouse[hh];
      for (var j = 0; j < planets.length; j++) {
        svg.appendChild(el('text', {
          x: cx, y: cy + 0.5 + j * 4, 'text-anchor':'middle', 'font-size':3.6,
          fill:'#1d4ed8', 'font-weight':'600'
        }, planets[j]));
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
          showDeg: key === 'D1'
        });
      }
    });
  }

  global.ABChart = { renderNorth: renderNorth, renderAll: renderAll };
})(window);
