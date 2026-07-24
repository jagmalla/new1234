/* Auto Business — Chart zoom lightbox.
 *
 * Click any rendered kundali chart (an SVG carrying the .ab-chart-svg class,
 * added by northchart.js) to open it enlarged in a centered popup with a close
 * (✕) button. Closes on ✕, backdrop click or Esc. Fully responsive — the popup
 * is a square sized to the smaller of the viewport width/height, so it fits
 * phones, tablets and desktops. Uses event delegation, so charts rendered later
 * (varga switch, gochar panes, milan, etc.) work automatically.
 */
(function (global) {
  'use strict';
  var doc = global.document;
  if (!doc) { return; }

  var overlay, box, titleEl, bodyEl, lastFocus;

  function injectStyles() {
    if (doc.getElementById('ab-cz-styles')) { return; }
    var css = ''
      + '.ab-chart-svg{cursor:zoom-in}'
      + '#ab-cz-overlay{position:fixed;inset:0;z-index:2000;background:rgba(15,12,8,.72);'
      + 'display:none;align-items:center;justify-content:center;padding:12px;'
      + '-webkit-tap-highlight-color:transparent}'
      + '#ab-cz-overlay.open{display:flex}'
      + '.ab-cz-box{background:#fff;border-radius:14px;box-shadow:0 18px 60px rgba(0,0,0,.45);'
      + 'width:min(92vw,92vh);height:auto;max-width:96vw;max-height:96vh;display:flex;'
      + 'flex-direction:column;overflow:hidden;animation:ab-cz-in .16s ease}'
      + '@keyframes ab-cz-in{from{transform:scale(.94);opacity:.4}to{transform:scale(1);opacity:1}}'
      + '.ab-cz-head{display:flex;align-items:center;gap:8px;padding:9px 12px;'
      + 'border-bottom:1px solid #eee5d6;font-family:inherit}'
      + '.ab-cz-title{font-weight:700;font-size:.98rem;color:#26221c;flex:1;min-width:0;'
      + 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
      + '.ab-cz-x{border:0;background:#f1f5f9;color:#334155;width:36px;height:36px;'
      + 'min-width:36px;border-radius:9px;font-size:1.1rem;line-height:1;cursor:pointer;'
      + 'display:flex;align-items:center;justify-content:center}'
      + '.ab-cz-x:hover{background:#e2e8f0}'
      + '.ab-cz-body{flex:1;min-height:0;padding:10px;display:flex;align-items:center;justify-content:center}'
      + '.ab-cz-body svg{width:100%;height:100%;max-width:100%;max-height:calc(92vh - 60px);display:block}'
      + '.ab-cz-hint{padding:0 12px 9px;font-size:.72rem;color:#94a3b8;text-align:center}'
      + '@media (max-width:480px){.ab-cz-box{width:96vw}.ab-cz-title{font-size:.9rem}}';
    var s = doc.createElement('style');
    s.id = 'ab-cz-styles';
    s.textContent = css;
    doc.head.appendChild(s);
  }

  function build() {
    if (overlay) { return; }
    injectStyles();
    overlay = doc.createElement('div');
    overlay.id = 'ab-cz-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', 'कुंडली चार्ट — बड़ा दृश्य');
    box = doc.createElement('div');
    box.className = 'ab-cz-box';
    box.innerHTML =
      '<div class="ab-cz-head"><span class="ab-cz-title" id="ab-cz-title">कुंडली चार्ट</span>'
      + '<button type="button" class="ab-cz-x" id="ab-cz-x" aria-label="बंद करें / Close">✕</button></div>'
      + '<div class="ab-cz-body" id="ab-cz-body"></div>'
      + '<div class="ab-cz-hint">बंद करने हेतु ✕, बाहर क्लिक या Esc दबाएँ</div>';
    overlay.appendChild(box);
    doc.body.appendChild(overlay);
    titleEl = doc.getElementById('ab-cz-title');
    bodyEl = doc.getElementById('ab-cz-body');

    doc.getElementById('ab-cz-x').addEventListener('click', close);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });
    doc.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) { close(); } });
  }

  // Best-effort title: the SVG's own title, else a nearby caption, else default.
  function titleFor(svg) {
    var t = svg.getAttribute('data-chart-title');
    if (t) { return t; }
    var host = svg.parentElement;
    for (var i = 0; host && i < 3; i++) {
      // preceding caption-like sibling
      var prev = host.previousElementSibling;
      if (prev) {
        var cls = prev.className && prev.className.baseVal !== undefined ? prev.className.baseVal : (prev.className || '');
        if (/cap|gp-sub|cell/.test(String(cls)) && prev.textContent.trim()) { return prev.textContent.trim(); }
      }
      if (host.getAttribute && host.getAttribute('aria-label')) { return host.getAttribute('aria-label'); }
      host = host.parentElement;
    }
    return 'कुंडली चार्ट';
  }

  function open(svg) {
    build();
    lastFocus = doc.activeElement;
    titleEl.textContent = titleFor(svg);
    bodyEl.innerHTML = '';
    var clone = svg.cloneNode(true);
    clone.classList.remove('ab-chart-svg');           // don't re-trigger zoom
    clone.removeAttribute('style');
    clone.setAttribute('width', '100%');
    clone.setAttribute('height', '100%');
    clone.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    bodyEl.appendChild(clone);
    overlay.classList.add('open');
    doc.body.style.overflow = 'hidden';
    var x = doc.getElementById('ab-cz-x'); if (x) { x.focus(); }
  }

  function close() {
    if (!overlay) { return; }
    overlay.classList.remove('open');
    doc.body.style.overflow = '';
    if (bodyEl) { bodyEl.innerHTML = ''; }
    try { if (lastFocus && lastFocus.focus) { lastFocus.focus(); } } catch (e) {}
  }

  // Delegated click — works for charts rendered at any time.
  doc.addEventListener('click', function (e) {
    var svg = e.target.closest ? e.target.closest('.ab-chart-svg') : null;
    if (svg) { e.preventDefault(); open(svg); }
  });

  global.ABChartZoom = { open: open, close: close };
})(window);
