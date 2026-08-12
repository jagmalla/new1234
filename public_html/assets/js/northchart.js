/* Auto Business — North-Indian chart renderer (reusable component).
 *
 * Pure vanilla JS + inline SVG, no libraries. Draws a traditional North-Indian
 * (diamond) chart for any divisional or transit chart, given the lagna sign and
 * each planet's sign. Ascendant sits in house 1 (top-centre); houses are fixed,
 * signs rotate. Planet abbreviations are colour-coded (Parashara's Light style),
 * degrees shown on request, retrograde marked with a circled-R (®).
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
    Ve:'#db2777', Sa:'#1d4ed8', Ra:'#3d4554', Ke:'#3d4554', As:'#111827', MUN:'#7c3aed'
  };

  // Own-rashi (rulership) sign indices per planet (0=Aries…11=Pisces). When a
  // planet sits in a sign it rules, it (and that house's rashi number) is
  // underlined. Rahu/Ketu own no sign.
  var OWN = {
    Su:[4], Mo:[3], Ma:[0,7], Me:[2,5], Ju:[8,11], Ve:[1,6], Sa:[9,10]
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

  var AV_COLOR = '#1d4ed8';  // Ashtakavarga (bindus)  — fallback text colour
  var BB_COLOR = '#15803d';  // Bhava Bala (virupas)   — fallback text colour
  var ROMAN = ['', 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];

  // Strength → chip colours (red/yellow/green) with a contrasting font colour.
  // AV (Ashtakavarga bindus): Low 0–24, Medium 25–28, High 29–56.
  function avColors(av) {
    av = Number(av);
    if (!isFinite(av)) { return null; }
    if (av <= 24) { return { bg: '#FF4C4C', fg: '#FFFFFF' }; }
    if (av <= 28) { return { bg: '#FFD700', fg: '#000000' }; }
    return { bg: '#4CAF50', fg: '#FFFFFF' };
  }
  // BB (Bhava Bala virupas): Low <360, Medium 360–510, High >510.
  function bbColors(bb) {
    bb = Number(bb);
    if (!isFinite(bb)) { return null; }
    if (bb < 360) { return { bg: '#FF4C4C', fg: '#FFFFFF' }; }
    if (bb <= 510) { return { bg: '#FFD700', fg: '#000000' }; }
    return { bg: '#4CAF50', fg: '#FFFFFF' };
  }

  // After the SVG is in the DOM, draw a snug rounded rect behind every AV / BB
  // chip (measuring the tspan so the box hugs the text and never bleeds into the
  // chart), then switch the chip's font to its contrasting colour. Measuring
  // needs layout, so this runs post-append; if a chip cannot be measured (chart
  // not laid out yet) it is left in its readable fallback colour, no box.
  function paintAvBbChips(svg) {
    var spans = svg.querySelectorAll('tspan.ab-chip');
    for (var i = 0; i < spans.length; i++) {
      var sp = spans[i];
      var bg = sp.getAttribute('data-bg'), fg = sp.getAttribute('data-fg');
      if (!bg) { continue; }
      var textEl = sp.parentNode;              // the <text> the tspan lives in
      if (!textEl || !textEl.parentNode) { continue; }
      var box;
      try { box = sp.getBBox(); } catch (e) { continue; }
      if (!box || !(box.width > 0)) { continue; }   // not laid out → keep fallback
      // A solid block that fills the full height of the AV/BB band (≈4.4 units,
      // the band is 5) and hugs the score's width — not a thin box around the
      // glyphs. Centred on the glyph's vertical middle so it sits square in the
      // band; the same text transform keeps rotated (left/right) bands upright.
      var padX = 0.7, bandH = 4.4;
      var cy = box.y + box.height / 2;
      var rect = el('rect', {
        x: box.x - padX, y: cy - bandH / 2,
        width: box.width + 2 * padX, height: bandH,
        rx: 0.7, fill: bg
      });
      var tf = textEl.getAttribute('transform');   // rotated (left/right) bands
      if (tf) { rect.setAttribute('transform', tf); }
      textEl.parentNode.insertBefore(rect, textEl);   // draw behind the text
      if (fg) { sp.setAttribute('fill', fg); }        // now safe to use contrast
    }
  }

  // Edge + segment-centre per house (3 segments/edge at 16.5/50/83.5).
  // side: t=top, b=bottom, l=left (rotate -90), r=right (rotate 90).
  var EDGE = {
    1:['t',50], 2:['t',16.5], 12:['t',83.5],
    7:['b',50], 6:['b',16.5], 8:['b',83.5],
    4:['l',50], 3:['l',16.5], 5:['l',83.5],
    10:['r',50], 11:['r',16.5], 9:['r',83.5]
  };
  // Label anchor [x, y, rotation] centred `mid` units outside the chart edge.
  function bandPos(hh, mid) {
    var e = EDGE[hh], s = e[1];
    switch (e[0]) {
      case 't': return [s, -mid, 0];
      case 'b': return [s, 100 + mid, 0];
      case 'l': return [-mid, s, -90];
      default:  return [100 + mid, s, 90];
    }
  }

  // Draw the outer ring(s): a Drishti band (graha aspects) just outside the
  // chart, then the AV/BB band outside that. Nesting inside-out: (1) chart,
  // (2) Drishti, (3) AV/BB.
  function drawOuterRing(svg, ring) {
    var DR = 5, O = 10;  // band outer offsets: Drishti 0..5, AV/BB 5..10 (slim, equal)
    var sep = function (x1,y1,x2,y2) {
      svg.appendChild(el('line', {x1:x1,y1:y1,x2:x2,y2:y2, stroke:'#cbd5e1', 'stroke-width':0.4}));
    };
    // rectangles: outer (AV/BB) and the Drishti / AV boundary
    svg.appendChild(el('rect', {x:-O, y:-O, width:100 + 2 * O, height:100 + 2 * O, fill:'none', stroke:'#9ca3af', 'stroke-width':0.6, rx:1}));
    svg.appendChild(el('rect', {x:-DR, y:-DR, width:100 + 2 * DR, height:100 + 2 * DR, fill:'none', stroke:'#cbd5e1', 'stroke-width':0.5, rx:1}));
    // radial separators from the chart edge out to the outer rectangle
    sep(0,0,-O,-O); sep(100,0,100+O,-O); sep(100,100,100+O,100+O); sep(0,100,-O,100+O);
    [33,67].forEach(function (t) {
      sep(t,0,t,-O); sep(t,100,t,100+O);   // top, bottom
      sep(0,t,-O,t); sep(100,t,100+O,t);   // left, right
    });

    function placed(p, fontSize) {
      var t = el('text', {x:p[0], y:p[1], 'text-anchor':'middle', 'font-size':fontSize, 'font-weight':'700'});
      if (p[2]) { t.setAttribute('transform', 'rotate(' + p[2] + ',' + p[0] + ',' + p[1] + ')'); }
      t.span = function (txt, fill) { var s = el('tspan', {fill:fill}); s.textContent = txt; t.appendChild(s); };
      // A "chip" span: drawn now in a readable fallback colour, but tagged so
      // paintAvBbChips() can put a coloured box behind it and switch to the
      // contrasting font once the text has been measured in the DOM.
      t.chip = function (txt, chip, fallbackFill) {
        var s = el('tspan', {fill: fallbackFill, 'class': 'ab-chip'});
        if (chip) { s.setAttribute('data-bg', chip.bg); s.setAttribute('data-fg', chip.fg); }
        s.textContent = txt; t.appendChild(s);
      };
      return t;
    }

    for (var hh = 1; hh <= 12; hh++) {
      var v = ring[hh] || ring[String(hh)];
      if (!v) { continue; }

      // AV/BB (outer band). The AV and BB scores each get their own colour chip
      // (red/yellow/green by strength) drawn behind them after layout; the house
      // roman and the separator stay on the plain band. Format kept as "AV:score"
      // and "BB:score".
      var a = placed(bandPos(hh, 7.5), 2.5);
      var bb = (v.bb_virupa != null) ? Math.round(v.bb_virupa) : v.bb;
      a.span(ROMAN[hh] + '=', '#111827');
      a.chip('AV:' + v.av, avColors(v.av), AV_COLOR);
      a.span(', ', '#111827');
      a.chip('BB:' + bb, bbColors(bb), BB_COLOR);
      svg.appendChild(a);

      // Drishti (inner band): "Dr: " + colour-coded aspecting planets, styled to
      // match the AV/BB band (same font size 2.5 and weight 700).
      var d = placed(bandPos(hh, 2.5), 2.5);
      d.span('Dr: ', '#111827');
      var list = v.drishti || [];
      if (!list.length) { d.span('—', '#9ca3af'); }
      list.forEach(function (ab, i) {
        if (i) { d.span(', ', '#111827'); }
        d.span(ab, COLOR[ab] || '#111827');
      });
      svg.appendChild(d);
    }
  }

  function renderNorth(container, data, opts) {
    opts = opts || {};
    container.innerHTML = '';

    // Rotation: which house is drawn at position 1 (top-centre). 1 = lagna
    // (default, no rotation). Value 0..11 is the house-1 offset in signs. The
    // planets and signs stay in the zodiac; only the house frame turns.
    var rotate = (((((opts.rotate || 1) - 1) % 12) + 12) % 12);

    if (opts.title) {
      var h = document.createElement('div');
      h.className = 'text-xs font-semibold text-center mb-1 text-gray-700';
      h.textContent = opts.title;
      container.appendChild(h);
    }

    // An optional outer ring shows Ashtakavarga (AV) and Bhava Bala (BB) per
    // house just outside the chart; it widens the viewBox to make room.
    var ring = opts.outer || null;
    // Rotate the AV/BB/Drishti ring with the signs so each value stays attached
    // to its own sign/bhava at the new on-screen house position.
    if (ring && rotate) {
      var rr = {};
      for (var rh = 1; rh <= 12; rh++) { rr[rh] = ring[((rh - 1 + rotate) % 12) + 1] || ring[String(((rh - 1 + rotate) % 12) + 1)]; }
      ring = rr;
    }
    // Default: scale to the container WIDTH (height follows, keeping the square).
    // fit:true → scale to fit BOTH width and height (contain), so the chart
    // always fits inside a freely-resized panel without overflowing or clipping.
    var svg = el('svg', opts.fit
      ? { viewBox: ring ? '-10.6 -10.6 121.2 121.2' : '0 0 100 100',
          width: '100%', height: '100%', preserveAspectRatio: 'xMidYMid meet',
          'class': 'rounded', style: 'display:block' }
      : { viewBox: ring ? '-10.6 -10.6 121.2 121.2' : '0 0 100 100',
          width: '100%', height: 'auto', 'class': 'rounded' });

    if (ring) { drawOuterRing(svg, ring); }

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
    // dispAsc = sign shown in house 1 after rotation; the real ascendant then
    // falls into whatever house now holds it (house 1 when not rotated).
    var dispAsc = (ascSign + rotate) % 12;
    var houseOf = function (sign) { return (((sign - dispAsc) % 12) + 12) % 12 + 1; };

    // Group planet labels by fixed house.
    var byHouse = {};
    for (var i = 1; i <= 12; i++) { byHouse[i] = []; }

    // Ascendant marker sits in the house that holds the lagna sign.
    byHouse[houseOf(ascSign)].push({
      abbr: 'As',
      txt: 'As' + (opts.showDeg && data.asc_deg != null ? ' ' + data.asc_deg + '°' : '')
    });

    (data.planets || []).forEach(function (p) {
      var house = houseOf(p.sign);
      var d = DIGN[p.abbr];
      var mark = d ? (p.sign === d.ex ? '↑' : (p.sign === d.de ? '↓' : '')) : '';
      var txt = p.abbr + mark
        + (opts.showDeg && p.deg != null ? ' ' + p.deg + '°' : '');
      // Own rashi = the planet's sign in THIS chart is one it rules.
      var own = !!(OWN[p.abbr] && OWN[p.abbr].indexOf(p.sign) >= 0);
      byHouse[house].push({ abbr: p.abbr, txt: txt, retro: !!p.retro, own: own });
    });

    for (var hh = 1; hh <= 12; hh++) {
      var cx = C[hh][0], cy = C[hh][1];
      var signNum = ((dispAsc + (hh - 1)) % 12);

      var items = byHouse[hh];
      var n = items.length;
      // Underline the rashi number when an occupant rules this sign (own rashi).
      var houseOwn = items.some(function (it) { return it.own; });

      // Rashi (sign) number only — black, tucked at the house's inner corner.
      var rnAttrs = {
        x: INNER[hh][0], y: INNER[hh][1] + 1.2, 'text-anchor':'middle',
        'font-size':3.5, fill:'#000000', 'font-weight':'700'
      };
      if (houseOwn) { rnAttrs['text-decoration'] = 'underline'; }
      svg.appendChild(el('text', rnAttrs, String(signNum + 1)));

      // Planets centred in the house body, colour-coded (Dasha palette).
      var lineH = 4.2;
      var startY = cy - ((n - 1) * lineH) / 2 + 1.3;
      for (var j = 0; j < n; j++) {
        var fs = opts.big ? 4.2 : 3.8;
        var ptAttrs = {
          x: cx, y: startY + j * lineH, 'text-anchor':'middle',
          'font-size': fs, fill: COLOR[items[j].abbr] || '#111827', 'font-weight':'600'
        };
        if (items[j].own) { ptAttrs['text-decoration'] = 'underline'; }
        var pt = el('text', ptAttrs, items[j].txt);
        // Retrograde: a raised circled-R (®) after the planet — bold red and
        // close to the planet's own size so it's clearly visible, tucked close
        // (small dx) and raised only slightly as a superscript.
        if (items[j].retro) {
          var sup = el('tspan', {
            'font-size': (fs * 1.05).toFixed(2), fill: '#b91c1c', 'font-weight': '800',
            dx: 0.3, dy: (-fs * 0.22).toFixed(2)
          });
          sup.textContent = '®';
          pt.appendChild(sup);
        }
        svg.appendChild(pt);
      }
    }

    // Mark every rendered chart so the click-to-zoom lightbox (chartzoom.js) can
    // find it; carry a title for the popup header.
    svg.classList.add('ab-chart-svg');
    if (opts.title) { svg.setAttribute('data-chart-title', opts.title); }
    container.appendChild(svg);
    // AV/BB colour chips need the text measured, so paint them once the SVG is
    // in the DOM. Only present when the outer ring was drawn.
    if (ring) { paintAvBbChips(svg); }
  }

  function renderAll(vargas, houses) {
    document.querySelectorAll('[data-varga]').forEach(function (elm) {
      var key = elm.getAttribute('data-varga');
      if (vargas[key]) {
        renderNorth(elm, vargas[key], {
          // Skip the built-in title when the container supplies its own header.
          title: elm.hasAttribute('data-notitle') ? null : vargas[key].label,
          showDeg: true,
          big: key === 'D1',
          // Outer AV/BB ring only on a D1 container marked with data-ring.
          outer: (houses && elm.getAttribute('data-ring')) ? houses : null
        });
      }
    });
  }

  global.ABChart = { renderNorth: renderNorth, renderAll: renderAll, COLOR: COLOR };
})(window);
