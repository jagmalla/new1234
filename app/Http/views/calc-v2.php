<?php
declare(strict_types=1);

/** @var array $view  (in, error, chart, vp, gochar, meta) */
use AutoBusiness\Core\Csrf;

$in = $view['in'];
$chart = $view['chart'];
$vp = $view['vp'];
$gochar = $view['gochar'];
$meta = $view['meta'];
$dashaNow = $view['dashaNow'] ?? null;

$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

// Planet name -> colour (same palette as the Chart view / Dasha legend).
$planetColors = [
    'Sun' => '#dc2626', 'Moon' => '#0891b2', 'Mars' => '#ea580c', 'Mercury' => '#16a34a',
    'Jupiter' => '#b45309', 'Venus' => '#db2777', 'Saturn' => '#1d4ed8',
    'Rahu' => '#3d4554', 'Ketu' => '#3d4554',
];
$pcolor = static fn($name) => $planetColors[$name] ?? '#111827';

// Shadbala strength colour band: red < 0.95, green > 1.01, orange in between.
$shadColor = static function (float $ratio): string {
    if ($ratio < 0.95) {
        return '#dc2626'; // red
    }
    if ($ratio > 1.01) {
        return '#16a34a'; // green
    }
    return '#f97316';     // orange (0.95–1.01 inclusive)
};

// "Lord" helper: the house number(s) from the Ascendant of the Rashi(s) a planet
// rules (Sign indexes 0=Aries … 11=Pisces). Used by the D1 and house tables.
$lordSigns = [
    'Sun' => [4], 'Moon' => [3], 'Mars' => [0, 7], 'Mercury' => [2, 5],
    'Jupiter' => [8, 11], 'Venus' => [1, 6], 'Saturn' => [9, 10], 'Rahu' => [], 'Ketu' => [],
];
$ascSignIdx = $chart !== null ? (int) $chart['ascendant']['sign_index'] : 0;
$lordHouses = static function (string $planet) use ($lordSigns, $ascSignIdx): string {
    $houses = [];
    foreach ($lordSigns[$planet] ?? [] as $sign) {
        $houses[] = (($sign - $ascSignIdx) % 12 + 12) % 12 + 1;
    }
    sort($houses);
    return implode(', ', $houses);
};

// ---- Layout v2 (Phase 1): Hindi labels for the top bar + overview tiles ----
$rashiHi = [
    'Aries' => 'मेष', 'Taurus' => 'वृषभ', 'Gemini' => 'मिथुन', 'Cancer' => 'कर्क',
    'Leo' => 'सिंह', 'Virgo' => 'कन्या', 'Libra' => 'तुला', 'Scorpio' => 'वृश्चिक',
    'Sagittarius' => 'धनु', 'Capricorn' => 'मकर', 'Aquarius' => 'कुंभ', 'Pisces' => 'मीन',
];
$grahaHi = [
    'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
    'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
];
$pobTop = $in['place'] !== '' ? $in['place'] : ($in['latIn'] . ', ' . $in['lonIn']);
$phalaLang = (string) ($view['phala']['lang'] ?? 'hi');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Analysis of Karma — Auto Business</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Martel:wght@800&family=Mukta:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        /* ============ APPROVED DESIGN TOKENS (layout v2) — single source ============ */
        :root {
            --paper:        #FBF7F0;   /* page background */
            --card:         #FFFFFF;   /* panel surfaces */
            --ink:          #26221C;   /* primary text */
            --ink-soft:     #6B6156;   /* labels, secondary text */
            --line:         #E4DCCE;   /* borders, dividers */
            --sindoor:      #B3341C;   /* accent: active menu, links, pills, buttons */
            --sindoor-soft: #F6E3DD;   /* accent tint: active/hover backgrounds */
            --haldi:        #C98A1B;   /* remedy label, small highlights only */
            --shubh:        #2E6E4E;   /* positive / benefic (green) */
            --ashubh:       #B91C1C;   /* negative / malefic (red)   */
            --mishra:       #1D4ED8;   /* mixed / neutral   (blue)   */
            --header-bg:    #1F2A33;   /* top bar */
        }
        body {
            background: var(--paper); color: var(--ink);
            font-family: 'Mukta', 'Noto Sans Devanagari', system-ui, sans-serif;
            line-height: 1.65;   /* Devanagari needs air — never clip matras */
        }
        h1, h2, h3 { font-family: 'Martel', 'Mukta', serif; }
        table { font-variant-numeric: tabular-nums; }
        /* Secondary text: warm ink-soft instead of cool gray-400 (a11y contrast ≥4.5:1).
           !important because the Tailwind runtime stylesheet loads after this block. */
        .text-gray-400 { color: var(--ink-soft) !important; }
        /* PROTECTED: the chart SVGs keep their pre-redesign font stack so the
           rendered chart stays pixel-identical (rings, planets, markers). */
        svg { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        /* Controls: 6px radius + sindoor focus ring (token spec). */
        input, select, textarea { border-radius: 6px; }
        input:focus-visible, select:focus-visible, textarea:focus-visible,
        button:focus-visible, a:focus-visible {
            outline: none; border-color: var(--sindoor);
            box-shadow: 0 0 0 3px var(--sindoor-soft);
        }

        /* ---- Top bar (sticky) ---- */
        .topbar { position: sticky; top: 0; z-index: 50; background: var(--header-bg); color: #F7F3EA; }
        .topbar-inner { max-width: 1400px; margin: 0 auto; padding: 10px 16px;
            display: flex; align-items: center; gap: 12px 20px; flex-wrap: wrap; }
        .topbar .brand { font-family: 'Martel', serif; font-weight: 800; font-size: 1.25rem; line-height: 1.3; }
        /* "Under testing" banner in the top-bar gap — high contrast, prominent. */
        .test-banner { flex: 1 1 240px; text-align: center; font-size: 1rem; font-weight: 800;
            color: #FFD84D; line-height: 1.4; min-width: 0; letter-spacing: .01em;
            text-shadow: 0 1px 2px rgba(0,0,0,.45); padding: 4px 10px; border-radius: 8px;
            background: rgba(255,216,77,.12); border: 1px solid rgba(255,216,77,.35); }
        .test-banner a { color: #FFFFFF; text-decoration: underline; font-weight: 800; }
        .topbar .meta { margin-left: auto; display: flex; align-items: center; gap: 8px 16px;
            flex-wrap: wrap; font-size: .85rem; color: #C9C2B4; }
        .topbar .meta b { color: #FFFFFF; font-weight: 600; }
        .topbar select { background: #2A3742; color: #F7F3EA; border: 1px solid #3B4854;
            padding: 6px 10px; min-height: 44px; font-size: .85rem; }
        .btn-sindoor { background: var(--sindoor); color: #fff; font-weight: 600; font-size: .9rem;
            padding: 6px 16px; min-height: 44px; border-radius: 6px; display: inline-flex; align-items: center; }
        .btn-sindoor:hover { filter: brightness(1.1); }
        /* Hamburger menu button — hidden on desktop, shown ≤1099px (see media query). */
        #menu-btn { display: none; align-items: center; gap: 8px; background: #2A3742;
            color: #F7F3EA; border: 1px solid #3B4854; border-radius: 8px; font-weight: 700;
            font-size: .9rem; padding: 8px 14px; min-height: 44px; cursor: pointer; }
        #menu-btn:hover { background: #34424F; }
        #menu-btn .menu-btn-bars { position: relative; width: 18px; height: 2px; background: currentColor;
            border-radius: 2px; box-shadow: 0 -6px 0 currentColor, 0 6px 0 currentColor; }
        #menu-overlay { position: fixed; inset: 0; background: rgba(20,16,10,.5); z-index: 55; }

        /* ---- Overview tiles (colourful, larger; birth info included) ---- */
        /* All 8 tiles on ONE row on wide screens; graceful wrap below. */
        .ov-tiles { display: grid; grid-template-columns: repeat(8, minmax(0, 1fr)); gap: 10px; }
        @media (max-width: 1200px) { .ov-tiles { grid-template-columns: repeat(4, minmax(0, 1fr)); } }
        @media (max-width: 699px) { .ov-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .ov-tile { background: var(--ov-bg, #fff); border: 1px solid var(--line);
            border-left: 5px solid var(--ov-acc, var(--sindoor)); border-radius: 12px;
            box-shadow: 0 2px 7px rgba(38,34,28,.10); padding: 9px 14px; min-width: 0;
            transition: transform .12s ease, box-shadow .12s ease; }
        .ov-tile:hover { transform: translateY(-2px); box-shadow: 0 5px 14px rgba(38,34,28,.16); }
        .ov-tile[data-nav] { cursor: pointer; }
        .ov-tile[data-nav]:focus-visible { outline: 2px solid var(--ov-acc, var(--sindoor)); outline-offset: 2px; }
        .ov-label { font-size: 11.5px; text-transform: uppercase; letter-spacing: .6px; font-weight: 800;
            color: var(--ov-acc, var(--ink-soft)); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-value { font-size: 1.32rem; font-weight: 800; color: var(--ov-acc, var(--ink)); line-height: 1.22;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-sub { font-size: .76rem; font-weight: 600; color: var(--ink-soft);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 480px) { .ov-value { font-size: 1.14rem; } }
        /* Per-tile accent + soft tinted background (Name·DOB·Place·Lagna·Moon·Sun·Dasha·Yoga). */
        .ov-tile:nth-child(1) { --ov-acc:#475569; --ov-bg:#f1f5f9; }
        .ov-tile:nth-child(2) { --ov-acc:#0f766e; --ov-bg:#ecfdf5; }
        .ov-tile:nth-child(3) { --ov-acc:#b45309; --ov-bg:#fdf6e9; }
        .ov-tile:nth-child(4) { --ov-acc:#7c3aed; --ov-bg:#f6f1ff; }
        .ov-tile:nth-child(5) { --ov-acc:#0891b2; --ov-bg:#ecfeff; }
        .ov-tile:nth-child(6) { --ov-acc:#dc2626; --ov-bg:#fef2f2; }
        .ov-tile:nth-child(7) { --ov-acc:#c2410c; --ov-bg:#fff3ec; }
        .ov-tile:nth-child(8) { --ov-acc:#16a34a; --ov-bg:#f0fdf4; }

        /* ---- Three-column shell (Phase 2) ---- */
        .l2-wrap { max-width: 1400px; }
        .l2-grid { display: grid; grid-template-columns: 180px minmax(0, 50fr) minmax(0, 40fr);
            gap: 16px; align-items: start; }
        .l2-full { grid-column: 2 / 4; min-width: 0; }
        .l2-card { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); }
        .l2-panel { display: flex; flex-direction: column; padding: 12px 14px; min-height: 560px; min-width: 0; }
        .l2-panel-title { font-size: 1rem; font-weight: 800; margin-bottom: 6px; }
        .l2-legend { text-align: center; font-size: 12px; color: var(--ink-soft); margin-bottom: 6px; }
        .l2-menu { padding: 6px 0; align-self: start; position: sticky; top: 76px; overflow: hidden; }
        .l2-menu button { display: block; width: 100%; text-align: left; padding: 10px 14px;
            border-left: 3px solid transparent; color: var(--ink); font-weight: 500; font-size: .95rem; }
        .l2-menu button:hover { background: var(--sindoor-soft); }
        /* Kundali Milan opens a separate page, so it's an anchor styled as a menu row. */
        .l2-mi-link { display: block; width: 100%; text-align: left; padding: 10px 14px;
            border-left: 3px solid transparent; color: var(--ink); font-weight: 700; font-size: .95rem;
            text-decoration: none; }
        .l2-mi-link:hover { background: var(--sindoor-soft); color: var(--sindoor); }
        .l2-menu button.active { background: var(--sindoor-soft); border-left-color: var(--sindoor);
            color: var(--sindoor); font-weight: 700; }
        /* Expand caret on menu items that have a sub-menu (added by JS). */
        .l2-menu > .l2-mi > button { position: relative; }
        .l2-caret { position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            font-size: .9rem; font-weight: 700; color: var(--ink-soft); line-height: 1; }
        .l2-mi.open > button .l2-caret { color: var(--sindoor); }
        /* Sub-menu: shown under the active section for direct jumps. */
        .l2-sub { display: none; }
        .l2-mi.open .l2-sub { display: block; }
        .l2-sub button { font-size: .82rem; padding: 6px 14px 6px 26px; color: var(--ink-soft);
            font-weight: 500; border-left: 3px solid var(--sindoor-soft); }
        .l2-sub button:hover { color: var(--sindoor); }
        .l2-sub button.active { color: var(--sindoor); font-weight: 700;
            background: var(--sindoor-soft); border-left-color: var(--sindoor); }
        /* Birth-details form: token styling (matches the approved design). */
        #birth-form h2 { font-size: 1rem; }
        #birth-form span { color: var(--ink-soft); }
        #birth-form input, #birth-form select { border: 1px solid var(--line); min-height: 44px;
            background: var(--card); color: var(--ink); }
        #birth-form .bg-blue-600 { background: var(--sindoor) !important; min-height: 44px; }
        .chart-frame { width: 100%; margin: 0 auto; }
        /* All dropdowns get an obvious "select me" look: accent border, tinted
           background and a visible caret — plus a leading label (see .pick-tag). */
        .l2-select, .pred-inline-select, .dp-select {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            border: 2px solid var(--sindoor); border-radius: 8px; color: var(--ink);
            background-color: var(--sindoor-soft); font-weight: 700; cursor: pointer;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23c0392b'><path d='M5 7.5l5 5 5-5z'/></svg>");
            background-repeat: no-repeat; background-position: right 10px center; background-size: 14px;
            padding-right: 30px; }
        .l2-select { width: 100%; padding: 9px 30px 9px 12px; min-height: 44px; margin-bottom: 8px; }
        /* Label sits to the LEFT of a smaller dropdown (chart / prediction pickers). */
        .pick-tag { font-size: 1.05rem; font-weight: 800; color: var(--sindoor); white-space: nowrap; }
        .l2-picker { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; flex-wrap: nowrap; }
        .l2-picker .l2-select { width: auto; flex: 1 1 auto; min-width: 0; margin-bottom: 0; }
        /* Chart header: a smaller "Chart" dropdown + a "Rotate" dropdown share the
           row. The chart select is trimmed to make room; both wrap on narrow
           screens so nothing overflows. */
        .chart-picker { flex-wrap: wrap; gap: 6px 8px; }
        .chart-picker #chart-select { flex: 1 1 130px; }
        .chart-picker #chart-rotate { flex: 1 1 120px; }
        .chart-picker .rot-tag { color: #1d4ed8; }
        .chart-picker .rot-select { border-color: #1d4ed8; background-color: #eff4ff;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%231d4ed8'><path d='M5 7.5l5 5 5-5z'/></svg>"); }
        /* Dasha strip — pinned to the chart panel bottom (mt-auto + divider). */
        .dasha-strip { margin-top: auto; border-top: 1px solid var(--line); padding-top: 8px;
            font-size: .85rem; line-height: 1.6; }
        .dasha-strip .ds-label { color: var(--ink); font-weight: 700; }
        .dasha-strip .ds-dates { color: var(--ink-soft); }
        .dasha-strip .ds-arrow { color: var(--ink-soft); }
        .ds-pill { background: var(--sindoor-soft); color: var(--sindoor); border-radius: 999px;
            padding: 1px 8px; font-size: .72rem; font-weight: 700; vertical-align: 1px; }

        /* ---- Prediction panel (Phase 4) ---- */
        .pred-head { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
        .pred-expand { width: 44px; height: 44px; min-height: 44px; flex: 0 0 auto; margin-left: auto;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid var(--line); border-radius: 6px; color: var(--sindoor);
            font-size: 1.15rem; font-weight: 700; background: var(--card); }
        .pred-expand:hover { background: var(--sindoor-soft); }
        /* Prediction body copy reads at 13px base (bumps to 15px when expanded). */
        .pred-view { font-size: 13px; }
        .pred-view .whitespace-pre-line, .pred-view li { line-height: 1.68; }
        /* कारण / reason & other small descriptive lines: bigger + darker so they
           are easy to read (was tiny light-grey). Applies across all predictions. */
        #pred-scroll .text-gray-500 { color: #55504A; }
        #pred-scroll .text-xs { font-size: .82rem; }
        /* Yoga + Bhavesh read at the same size as the other predictions (~1.02rem). */
        .pred-view[data-pred="yoga"], .pred-view[data-pred="bhavesh"] { font-size: 1.02rem; line-height: 1.6; }
        /* Inline dropdown pickers (replace the old side lists). */
        .pred-picker { display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--line); }
        .pred-picker-label { font-size: .8rem; font-weight: 800; color: var(--sindoor); white-space: nowrap;
            text-transform: uppercase; letter-spacing: .02em; }
        .pred-inline-select { flex: 1; min-width: 160px; padding: 9px 30px 9px 12px; min-height: 40px; }
        /* सहम + मुद्दा-दशा dropdowns share one line: saham wider, mudda narrower. */
        /* "Dasha Details" button + popup (responsive; close ✕ always visible). */
        .dasha-detail-btn { font-size: .78rem; font-weight: 700; border: 1px solid var(--sindoor);
            color: var(--sindoor); background: #fff; border-radius: 8px; padding: 6px 12px; cursor: pointer; white-space: nowrap; }
        .dasha-detail-btn:hover { background: var(--sindoor); color: #fff; }
        .dm-overlay { position: fixed; inset: 0; z-index: 2000; background: rgba(15,12,8,.55);
            display: flex; align-items: center; justify-content: center; padding: 14px; }
        .dm-overlay.hidden { display: none; }
        .dm-box { background: #fff; border-radius: 12px; width: 100%; max-width: 680px; max-height: 88vh;
            display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 14px 48px rgba(0,0,0,.35); }
        .dm-head { display: flex; align-items: center; gap: 10px; padding: 12px 14px;
            border-bottom: 1px solid var(--line); background: #fff; flex: 0 0 auto; }
        .dm-title { font-weight: 800; font-size: 1rem; color: var(--sindoor); }
        .dm-close { margin-left: auto; width: 36px; height: 36px; min-width: 36px; border-radius: 50%;
            border: 1px solid var(--line); background: #f8fafc; color: #334155; cursor: pointer;
            font-size: 1.05rem; line-height: 1; display: flex; align-items: center; justify-content: center; }
        .dm-close:hover { background: #fee2e2; color: #b91c1c; border-color: #fca5a5; }
        .dm-body { overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 12px 14px; flex: 1 1 auto; }
        .dm-body::-webkit-scrollbar { width: 9px; }
        .dm-body::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 8px; }
        .dm-tree .dasha-tree, .dm-tree { font-size: .9rem; }
        .dasha-strip { cursor: pointer; }
        @media (max-width: 640px) { .dm-box { max-height: 92vh; border-radius: 10px; } .dm-body { padding: 10px 10px; } }
        .saham-sel-main { flex: 3 1 190px; }
        .saham-sel-mudda { flex: 1 1 120px; min-width: 120px; max-width: 210px; }
        /* भाव-फल: भाव + श्रेणी selects share one line with the checkbox. */
        .tb-pickrow .tb-sel { flex: 1 1 140px; min-width: 130px; }
        /* Dasha Maha/Antar picker — always ONE line (own full-width row, no wrap). */
        .dp-picker { display: flex; align-items: center; gap: 6px 12px; flex-wrap: nowrap;
            flex-basis: 100%; width: 100%; }
        .dp-field { display: flex; align-items: center; gap: 6px; min-width: 0; }
        .dp-field > span { font-size: .74rem; font-weight: 700; color: var(--ink-soft); white-space: nowrap; }
        .dp-select { padding: 7px 26px 7px 9px; min-height: 38px; min-width: 0; max-width: 100%; }
        .dp-picker .pred-picker-label { flex: 0 0 auto; }
        /* Gochar prediction placeholder ("coming soon"). */
        .gochar-pred-soon { flex: 1; display: flex; flex-direction: column; align-items: center;
            justify-content: center; text-align: center; gap: 10px; padding: 30px 16px;
            color: var(--ink-soft); border: 2px dashed var(--line); border-radius: 10px;
            background: #FBF8F2; min-height: 240px; }
        .gochar-pred-soon .gps-icon { font-size: 2.2rem; line-height: 1; }
        .gochar-pred-soon .gps-title { font-weight: 800; font-size: 1.05rem; color: var(--sindoor); }
        .gochar-pred-soon .gps-sub { font-size: .88rem; max-width: 360px; line-height: 1.5; }
        /* Saham (Varshaphal) panel. */
        .saham-active { border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px;
            background: #FBF8F2; font-size: .92rem; }
        .saham-related { margin-top: 6px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; font-size: .8rem; color: var(--ink-soft); }
        .saham-chip { cursor: pointer; border: 1px solid transparent; }
        .saham-chip .saham-why { font-weight: 400; opacity: .8; }
        .saham-card { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; }
        .saham-card-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
        .saham-name { font-weight: 800; font-size: 1.08rem; }
        .saham-tag { font-size: .7rem; font-weight: 700; border-radius: 999px; padding: 1px 8px; }
        .saham-tag.rel { background: var(--sindoor-soft); color: var(--sindoor); }
        .saham-tag.dup { background: #EEF2FF; color: #4338ca; }
        .saham-signifies { color: var(--ink-soft); font-size: .85rem; margin-bottom: 3px; }
        .saham-explain { background: #F4F1EA; border-left: 3px solid #C9A227; border-radius: 4px;
            padding: 5px 8px; margin-bottom: 6px; font-size: .92rem; line-height: 1.5; color: #3A352D; }
        .saham-explain b { color: #7A5C00; }
        .saham-pos { font-size: .95rem; margin-bottom: 3px; }
        .saham-facts { font-size: .85rem; color: #453F37; margin-bottom: 4px; }
        .saham-phal { font-size: 1rem; line-height: 1.6; margin: 2px 0; }
        .saham-timing { font-size: .85rem; color: var(--haldi); margin-top: 5px; border-top: 1px dashed var(--line); padding-top: 5px; }
        /* गोचर फल pane (shares the saham card look). */
        .gph-section-title { font-weight: 800; font-size: .92rem; color: var(--sindoor);
            border-bottom: 1px solid var(--line); padding-bottom: 3px; margin: 4px 0 8px; }
        .gph-house { font-size: .78rem; font-weight: 700; background: #EEF2FF; color: #3730a3; border-radius: 999px; padding: 1px 9px; }
        .gochar-card .saham-card-head { gap: 6px; }
        /* Tone-coloured Gochar prediction cards: green = शुभ, red = अशुभ,
           blue = मिश्र/सूचना. Applied across every gochar category. */
        .gochar-card.gcard-pos { border-left: 4px solid #15803d; background: #f6fef9; }
        .gochar-card.gcard-neg { border-left: 4px solid #b91c1c; background: #fef6f6; }
        .gochar-card.gcard-mix { border-left: 4px solid #1d4ed8; background: #f5f8ff; }
        .gph-note { font-size: .82rem; line-height: 1.5; border-left: 3px solid var(--line);
            padding: 2px 8px; margin: 3px 0; border-radius: 3px; }
        .gph-note.gph-pos { border-left-color: #15803d; background: #eef6f0; color: #15803d; }
        .gph-note.gph-neg { border-left-color: #b91c1c; background: #fbeae7; color: #b91c1c; }
        .gph-note.gph-info { border-left-color: #1d4ed8; background: #eff4ff; color: #1e40af; }
        .gph-shubh { font-size: .75rem; color: var(--ink-soft); margin-top: 4px; }
        /* Combined Ashtakvarga card: the three sub-predictions per planet. */
        .gph-avline { margin: 6px 0 0; padding-top: 6px; border-top: 1px dashed var(--line); }
        .gph-avline:first-of-type { border-top: 0; padding-top: 2px; }
        .gph-avhead { font-size: .8rem; color: #475569; margin-bottom: 2px; display: flex;
            align-items: center; gap: 6px; flex-wrap: wrap; }
        /* ---- Sade-Sati / Dhaiyya full timeline ---- */
        .sade-controls { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px; }
        .sade-basis { display: inline-flex; border: 1px solid var(--line); border-radius: 8px; overflow: hidden; }
        .sade-basis-btn { padding: 7px 12px; font-size: .82rem; font-weight: 700; background: var(--card);
            color: var(--ink-soft); min-height: 38px; border: 0; cursor: pointer; }
        .sade-basis-btn.active { background: var(--sindoor-soft); color: var(--sindoor); }
        .sade-card { border: 1px solid var(--line); border-left: 4px solid #94a3b8; border-radius: 10px;
            padding: 9px 12px; margin-bottom: 10px; background: #fff; }
        .sade-card.sade-active { border-left-color: #ea580c; background: #fff8f3; }
        .sade-card.sade-future { border-left-color: #1d4ed8; background: #f5f8ff; }
        .sade-card.sade-past { border-left-color: #94a3b8; background: #fafafa; }
        .sade-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .sade-title { font-weight: 800; font-size: .95rem; color: #334155; }
        .sade-badge { font-size: .72rem; font-weight: 800; padding: 1px 9px; border-radius: 999px; }
        .sade-b-active { background: #ffedd5; color: #c2410c; }
        .sade-b-future { background: #dbeafe; color: #1d4ed8; }
        .sade-b-past { background: #e5e7eb; color: #4b5563; }
        .sade-ref-tag { font-size: .72rem; font-weight: 700; color: #6b21a8; background: #f3e8ff;
            border-radius: 999px; padding: 1px 8px; }
        /* Gochar General Overview — clickable per-category summary cards. */
        .gov-card { border: 1px solid var(--line); border-left: 4px solid #94a3b8; border-radius: 9px;
            padding: 7px 11px; margin: 7px 0; background: #fff; cursor: pointer;
            transition: box-shadow .15s ease, transform .05s ease; }
        .gov-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.12); }
        .gov-card:active { transform: translateY(1px); }
        .gov-card.gov-pos { border-left-color: #15803d; background: #f6fef9; }
        .gov-card.gov-neg { border-left-color: #b91c1c; background: #fef6f6; }
        .gov-card.gov-mix { border-left-color: #1d4ed8; background: #f5f8ff; }
        .gov-h { font-weight: 800; font-size: .88rem; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .gov-card.gov-pos .gov-h { color: #15803d; }
        .gov-card.gov-neg .gov-h { color: #b91c1c; }
        .gov-card.gov-mix .gov-h { color: #1d4ed8; }
        .gov-sum { font-size: .84rem; color: #2b2620; margin-top: 2px; line-height: 1.5; }
        .gov-jump { font-size: .74rem; font-weight: 700; color: var(--sindoor); margin-top: 3px; }
        .gov-card.gov-pos .gov-jump { color: #15803d; }
        .gov-card.gov-neg .gov-jump { color: #b91c1c; }
        .gov-card.gov-mix .gov-jump { color: #1d4ed8; }
        /* ---- आगामी गोचर (Upcoming Gochar) panel ---- */
        .ug-panel { font-size: .9rem; color: #2b2620; }
        .ug-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px; }
        .ug-body { max-height: 460px; overflow-y: auto; padding-right: 6px; }
        .ug-body::-webkit-scrollbar { width: 8px; }
        .ug-body::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 8px; }
        .ug-body::-webkit-scrollbar-track { background: #f5f5f4; }
        .ug-title { font-weight: 800; font-size: 1rem; color: var(--sindoor); }
        .ug-sub { font-size: .74rem; color: #9ca3af; font-weight: 400; }
        .ug-copy { margin-left: auto; font-size: .78rem; font-weight: 700; border: 1px solid var(--line);
            background: #f8fafc; border-radius: 7px; padding: 5px 11px; cursor: pointer; color: #334155; }
        .ug-copy:hover { background: #eef2f7; }
        .ug-highlight { background: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #ea580c;
            border-radius: 8px; padding: 8px 11px; font-size: .9rem; margin-bottom: 10px; color: #7c2d12; }
        .ug-sec { margin-bottom: 10px; }
        .ug-sec-h { font-weight: 800; font-size: .82rem; color: #475569; text-transform: none;
            border-bottom: 1px solid var(--line); padding-bottom: 3px; margin-bottom: 5px; }
        .ug-line { font-size: .88rem; line-height: 1.6; margin: 3px 0; display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
        .ug-muted { color: #94a3b8; }
        .ug-date { color: #1d4ed8; font-weight: 700; white-space: nowrap; }
        .ug-badge { font-size: .68rem; font-weight: 800; padding: 1px 7px; border-radius: 999px; flex: 0 0 auto; }
        .ug-b-retro { background: #ffedd5; color: #c2410c; }
        .ug-b-ast { background: #fee2e2; color: #b91c1c; }
        .ug-sade-active { color: #b91c1c; font-weight: 600; }
        /* Mahurat prediction box scrolls internally, height matched to the
           transit chart card (max-height set by JS; fallback for narrow view). */
        #mah-phal { overflow-y: auto; max-height: 460px; padding-right: 6px; }
        #mah-phal::-webkit-scrollbar { width: 8px; }
        #mah-phal::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 8px; }
        #mah-phal::-webkit-scrollbar-track { background: #f5f5f4; }
        /* Gochar page configurable panes — chart + prediction, each with a
           dropdown; equal height so the two-column rows stay aligned. */
        .gpane { --gp-h: 560px; background: #fff; border-radius: .5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,.08); display: flex; flex-direction: column;
            overflow: hidden; border: 1px solid #eef0f2; }
        .gp-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
            padding: 9px 12px; border-bottom: 1px solid var(--line); background: #fcfcfb; min-height: 46px; }
        .gp-sel { font-weight: 700; font-size: .92rem; color: #1f2937; cursor: pointer;
            border: 1px solid var(--line); border-radius: 8px; padding: 6px 30px 6px 11px;
            background: #f8fafc url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23475569' stroke-width='3'><path d='M6 9l6 6 6-6'/></svg>") no-repeat right 9px center;
            -webkit-appearance: none; appearance: none; max-width: 100%; }
        .gp-sel:hover { background-color: #eef2f7; }
        .gp-sub { font-size: .72rem; color: #9ca3af; margin-left: auto; white-space: nowrap; }
        .gp-body { height: var(--gp-h); padding: 12px 14px; }
        .gp-body-chart { display: flex; align-items: center; justify-content: center; padding: 10px; }
        .gp-chart-host { width: 100%; height: 100%; max-width: var(--gp-h); }
        .gp-chart-host svg { display: block; margin: 0 auto; }
        .gp-body-pred { overflow-y: auto; padding-right: 6px; }
        .gp-body-pred::-webkit-scrollbar { width: 8px; }
        .gp-body-pred::-webkit-scrollbar-thumb { background: #d6d3d1; border-radius: 8px; }
        .gp-body-pred::-webkit-scrollbar-track { background: #f5f5f4; }
        /* Inside a pred slot the panel/phal block drops its own outer chrome. */
        .gp-body-pred .ug-panel { font-size: .9rem; }
        .gp-body-pred .ug-body { max-height: none; overflow: visible; padding-right: 0; }
        @media (max-width: 640px) { .gpane { --gp-h: 420px; } }
        /* Date / time +/- steppers under the Gochar inputs — one labelled column
           per unit, with a red minus + green plus stacked; aligned & compact. */
        .gc-steps { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 7px; }
        .gc-step-col { display: flex; flex-direction: column; gap: 4px;
            padding: 4px 5px 5px; border: 1px solid #e8eaed; border-radius: 9px;
            background: #fbfbfa; min-width: 50px; }
        .gc-step-lbl { font-size: .6rem; font-weight: 800; letter-spacing: .04em;
            text-transform: uppercase; color: #94a3b8; text-align: center; margin-bottom: 1px; }
        .gc-step { font-size: .74rem; font-weight: 700; line-height: 1; cursor: pointer;
            border: 1px solid transparent; border-radius: 7px; padding: 5px 6px; text-align: center;
            transition: background .12s, border-color .12s, transform .05s; }
        .gc-step-minus { background: #fef2f2; color: #b91c1c; border-color: #fecaca; }
        .gc-step-minus:hover { background: #fee2e2; border-color: #fca5a5; }
        .gc-step-plus { background: #ecfdf5; color: #047857; border-color: #a7f3d0; }
        .gc-step-plus:hover { background: #d1fae5; border-color: #6ee7b7; }
        .gc-step:active { transform: translateY(1px); }
        @media (max-width: 480px) { .gc-step-col { min-width: 44px; flex: 1 1 auto; } }
        .sade-dates { font-size: .86rem; color: #475569; font-weight: 600; margin: 3px 0; }
        .sade-progress { height: 8px; background: #e5e7eb; border-radius: 999px; overflow: hidden; margin: 4px 0 2px; }
        .sade-bar { height: 100%; background: linear-gradient(90deg, #f59e0b, #ea580c); }
        .sade-prog-note { font-size: .78rem; color: #c2410c; font-weight: 700; margin-bottom: 4px; }
        .sade-phase { font-size: .84rem; color: #2b2620; margin: 3px 0; line-height: 1.5;
            display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .sade-ph-dot { width: 7px; height: 7px; border-radius: 50%; background: #94a3b8; flex: 0 0 auto; }
        .sade-ph-active .sade-ph-dot { background: #ea580c; }
        .sade-ph-future .sade-ph-dot { background: #1d4ed8; }
        .sade-ph-tag { font-size: .7rem; font-weight: 700; padding: 0 6px; border-radius: 999px; }
        .sade-ph-bindu { font-size: .72rem; color: #1d4ed8; font-weight: 700; }
        .sade-detail { margin-top: 6px; }
        .sade-detail > summary { cursor: pointer; font-size: .82rem; font-weight: 700; color: var(--sindoor);
            list-style: revert; }
        .sade-layer { border-top: 1px dashed var(--line); padding-top: 6px; margin-top: 6px; }
        .sade-layer-h { font-weight: 700; font-size: .84rem; color: #6b21a8; margin-bottom: 3px; }
        .sade-layer-line { font-size: .84rem; line-height: 1.6; color: #2b2620; margin: 2px 0; }
        .sade-t-pos { color: #15803d; } .sade-t-neg { color: #b91c1c; } .sade-t-mix { color: #1d4ed8; }
        .sade-remedy { font-size: .82rem; color: #6b6459; margin-top: 5px; border-top: 1px dashed var(--line); padding-top: 5px; }
        .gph-l3ev { border-top: 1px dashed var(--line); padding-top: 5px; margin-top: 5px; }
        .gph-l3head { font-size: .85rem; color: #453F37; margin-bottom: 2px; }
        .gph-cond { color: #7A5C00; }
        /* ताजिक भाव-फल pane chips + condition line. */
        .gc-chip.tb-mrityu { background: #4c0519; color: #fecdd3; }
        .gc-chip.tb-niyam { background: #e5e7eb; color: #374151; }
        .gc-chip.tb-off { background: #eef2f7; color: #64748b; }
        .gc-chip.tb-ref { background: #fbf7ef; color: #9a8149; border: 1px solid #ece3cf; }
        .tb-card .saham-card-head { gap: 6px; flex-wrap: wrap; }
        .tb-cond { font-size: .82rem; color: #4b5563; margin: 3px 0; line-height: 1.5; }
        .tb-cond b { color: #334155; }
        /* दशा-फल pane — Patyayini timeline. */
        .dp-timeline { display: flex; flex-direction: column; gap: 3px; }
        .dp-trow { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left;
            background: #fbfcfe; border: 1px solid var(--line); border-radius: 8px; padding: 4px 10px;
            font-size: .82rem; cursor: pointer; transition: background .1s; }
        .dp-trow:hover { background: #eef2ff; }
        .dp-trow.dp-now { background: #f0fdf4; border-color: #86efac; }
        .dp-dot { width: 9px; height: 9px; border-radius: 50%; flex: none; }
        .dp-trow-dates { color: var(--ink-soft); font-size: .76rem; margin-left: auto; }
        .dp-trow-days { color: #94a3b8; font-size: .72rem; min-width: 52px; text-align: right; }
        /* Varshaphal year summary (top-right of the year box) — big, colourful. */
        .vp-sum-grid { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: stretch; }
        .vp-sum-item { display: flex; flex-direction: column; justify-content: center;
            border: 1px solid var(--line); border-left-width: 5px; border-radius: 10px;
            padding: 6px 16px; min-width: 118px; background: #fff; }
        .vp-sum-lab { font-size: .72rem; font-weight: 700; letter-spacing: .01em; color: var(--ink-soft);
            text-transform: uppercase; margin-bottom: 1px; }
        .vp-sum-val { font-size: 1.4rem; font-weight: 800; line-height: 1.15; }
        @media (max-width: 480px) { .vp-sum-val { font-size: 1.2rem; } .vp-sum-item { min-width: 104px; padding: 5px 12px; } }
        /* ताजिक योग pane (shares the saham card look). */
        .tajik-matrix { border-collapse: collapse; font-size: .78rem; white-space: nowrap; }
        .tajik-matrix th, .tajik-matrix td { border: 1px solid var(--line); padding: 3px 8px; text-align: center; }
        .tajik-matrix thead th { background: #F4F1EA; }
        .tajik-matrix .tm-sneha { background: #dff0e6; color: #15803d; font-weight: 700; }
        .tajik-matrix .tm-vair  { background: #fbdcd7; color: #b91c1c; font-weight: 700; }
        .tajik-matrix .tm-none  { color: #9ca3af; }
        .tajik-matrix .tm-self  { color: #d1d5db; }
        /* अष्टकवर्ग मत — the SAV/BAV opinion block inside each house card. */
        .av-mat { border-top: 1px dashed var(--line); padding-top: 8px; }
        .av-mat-title { font-weight: 700; font-size: 1rem; color: #7c3aed; margin-bottom: 4px; }
        .bb-mat .av-mat-title { color: #b45309; }   /* भाव बल मत — amber, distinct from AV purple */
        .av-mat-list { list-style: none; padding-left: 0; margin: 0;
            font-size: 1.02rem; line-height: 1.6; }
        .av-mat-list li { position: relative; padding-left: 18px; margin-bottom: 3px; }
        .av-mat-list li::before { content: '●'; position: absolute; left: 0; font-size: .7rem; top: .28em; }
        .av-mat-list li.av-pos::before { color: #16a34a; }
        .av-mat-list li.av-neg::before { color: #dc2626; }
        .av-mat-list li.av-info::before { color: #9ca3af; }
        /* Colored dot labels + token colours for the curated text sections. */
        #pred-scroll .text-green-700 { color: var(--shubh) !important; }
        #pred-scroll .text-red-700 { color: var(--ashubh) !important; }
        #pred-scroll .text-blue-700 { color: var(--haldi) !important; }
        #phala-sections > div > .font-semibold::before { content: '● '; }
        #pred-scroll .pp-sub::before { content: '● '; }
        /* Yoga card variant: 4px sindoor left border, कारण/फल lines. */
        .yoga-card { border: 1px solid var(--line); border-left: 4px solid var(--sindoor);
            border-radius: 8px; padding: 10px 12px; margin-bottom: 12px; background: var(--card); }
        .yoga-card.yoga-bad { border-left-color: var(--ashubh); }
        .yoga-title { font-weight: 700; font-size: 1.1rem; color: var(--ink); margin-bottom: 2px; }
        .yoga-why { color: #453F37; font-size: .9rem; }
        .yoga-why b { color: var(--ink-soft); }
        .yoga-res b { color: var(--shubh); }
        .yoga-bad .yoga-res b { color: var(--ashubh); }
        /* Phaladeepika yoga catalogue */
        .yoga-sec-title { font-weight: 800; font-size: .95rem; color: var(--sindoor);
            border-bottom: 1px solid var(--line); padding-bottom: 3px; margin: 4px 0 8px; }
        .yoga-cat-head { font-weight: 700; font-size: .86rem; color: #1e293b; margin: 12px 0 5px; }
        .py-card { padding: 8px 11px; margin-bottom: 8px; }
        .py-card .yoga-title { font-size: .98rem; }
        .py-card .yoga-why, .py-card .yoga-res { font-size: .84rem; line-height: 1.5; }
        /* Colour-code every yoga by TYPE: green = शुभ, red = अशुभ, blue = मिश्र.
           The left border shows the type; when the yoga is active in this chart
           (.py-on) the card gets a matching light tint. */
        .py-card.py-shubh   { border-left-color: var(--shubh); }
        .py-card.py-ashubh  { border-left-color: var(--ashubh); }
        .py-card.py-mishrit { border-left-color: var(--mishra); }
        .py-card.py-shubh.py-on   { background: #f2fbf6; }
        .py-card.py-ashubh.py-on  { background: #fdf3f2; }
        .py-card.py-mishrit.py-on { background: #eef3ff; }
        /* Yogakaraka (Adhyaya 32) classification box + role chips + फल-दशा */
        .yk-box { background: #f6f1ff; border: 1px solid #e0d4f7; border-radius: 10px; padding: 8px 11px; margin: 8px 0; }
        .yk-head { font-size: .82rem; font-weight: 700; color: #6b21a8; margin-bottom: 6px; }
        .yk-grid { display: flex; flex-wrap: wrap; gap: 6px; }
        .yk-pill { display: inline-flex; align-items: center; gap: 4px; background: #fff; border: 1px solid var(--line);
            border-radius: 999px; padding: 2px 8px; font-size: .8rem; }
        .gc-chip.yk-yoga { background: #ede9fe; color: #5b21b6; }
        .gc-chip.yk-marak { background: #fde2e4; color: #9d174d; }
        .yk-ref { font-size: .74rem; color: #7c6f5a; margin-top: 6px; line-height: 1.5; }
        /* ---- राजयोग (BPHS 36) ---- */
        .ry-box { margin: 8px 0 4px; }
        .ry-karakas { font-size: .8rem; color: #475569; background: #f8fafc; border: 1px solid var(--line);
            border-radius: 8px; padding: 5px 10px; margin-bottom: 6px; }
        .ry-grp { font-weight: 800; font-size: .86rem; margin: 8px 0 4px; }
        .ry-grp-pos { color: #15803d; } .ry-grp-neg { color: #b91c1c; }
        .ry-card { border: 1px solid var(--line); border-left: 4px solid #94a3b8; border-radius: 9px;
            padding: 7px 11px; margin-bottom: 7px; background: #fff; }
        .ry-c-pos { border-left-color: #15803d; background: #f6fef9; }
        .ry-c-neg { border-left-color: #b91c1c; background: #fef6f6; }
        .ry-card-h { display: flex; align-items: center; gap: 7px; flex-wrap: wrap; }
        .ry-name { font-weight: 800; font-size: .9rem; color: #334155; }
        .ry-src { font-size: .72rem; color: #7c6f5a; font-weight: 700; }
        .ry-badge { font-size: .72rem; font-weight: 800; padding: 1px 8px; border-radius: 999px; }
        .ry-b-pos { background: #dcfce7; color: #15803d; } .ry-b-neg { background: #fee2e2; color: #b91c1c; }
        .ry-tag { font-size: .68rem; font-weight: 800; padding: 1px 7px; border-radius: 999px; background: #ede9fe; color: #5b21b6; }
        .ry-matched { font-size: .84rem; color: #2b2620; margin: 3px 0; line-height: 1.55; }
        .ry-matched i { color: #1d4ed8; font-style: normal; font-weight: 600; }
        .ry-result { font-size: .84rem; color: #15803d; line-height: 1.55; }
        .ry-c-neg .ry-result { color: #b91c1c; }
        .ry-bhanga-note { font-size: .8rem; color: #b45309; margin: 2px 0; line-height: 1.5; }
        .ry-none { font-size: .84rem; color: #6b6459; background: #fafafa; border: 1px dashed var(--line);
            border-radius: 8px; padding: 7px 10px; }
        .ry-unavail { margin: 6px 0; }
        .ry-unavail > summary { cursor: pointer; font-size: .8rem; font-weight: 700; color: #64748b; }
        .ry-unavail-item { font-size: .8rem; color: #475569; margin: 3px 0 3px 6px; }
        .ry-summary { font-size: .86rem; color: #1e40af; background: #eff4ff; border: 1px solid #cdddff;
            border-radius: 8px; padding: 7px 10px; margin-top: 8px; line-height: 1.55; }
        .ry-caveat { font-size: .76rem; color: #92400e; margin-top: 5px; line-height: 1.5; }
        .yoga-dasha { margin-top: 6px; padding-top: 5px; border-top: 1px dashed var(--line); font-size: .84rem; }
        .yoga-dasha > b { color: #6b21a8; }
        .yoga-dasha-pill { display: inline-flex; align-items: center; gap: 4px; background: #faf7ff; border: 1px solid #ece3fb;
            border-radius: 7px; padding: 2px 7px; margin: 2px 4px 2px 0; font-size: .82rem; }
        .yd-dates { color: var(--ink-soft); font-size: .76rem; }
        /* सामान्य (General) summary — plain conclusion cards */
        .gen-title { font-weight: 800; font-size: 1rem; color: var(--sindoor); margin: 6px 0 8px; }
        .gen-card { border: 1px solid var(--line); border-left: 4px solid #6b21a8; border-radius: 10px;
            padding: 8px 12px; margin-bottom: 9px; background: #fff; }
        .gen-h { font-weight: 700; font-size: .92rem; color: #6b21a8; margin-bottom: 4px; }
        .gen-line { font-size: .92rem; line-height: 1.6; margin: 3px 0; color: #2b2620; }
        .gen-line.gen-pos { color: #15803d; } .gen-line.gen-neg { color: #b91c1c; }
        .gen-sub { font-size: .78rem; color: #6b6459; margin-top: 3px; }
        .gen-concl { border-radius: 10px; padding: 10px 13px; font-size: .96rem; line-height: 1.6; margin-top: 4px; }
        .gen-concl.gen-pos { background: #f0fdf4; border: 1px solid #bbf7d0; color: #14532d; }
        /* mixed conclusion → blue (matches the शुभ/मिश्र/अशुभ = green/blue/red code) */
        .gen-concl.gen-mix { background: #eff4ff; border: 1px solid #cdddff; color: #1e40af; }
        /* Per-card colour code: शुभ = green, अशुभ = red, मिश्र = blue. The left
           bar + tinted background + heading colour all follow the card's tone. */
        .gen-card.gen-c-pos { border-left-color: #15803d; background: #f6fef9; }
        .gen-card.gen-c-neg { border-left-color: #b91c1c; background: #fef6f6; }
        .gen-card.gen-c-mix { border-left-color: #1d4ed8; background: #f5f8ff; }
        .gen-card.gen-c-pos .gen-h { color: #15803d; }
        .gen-card.gen-c-neg .gen-h { color: #b91c1c; }
        .gen-card.gen-c-mix .gen-h { color: #1d4ed8; }
        /* Clickable summary cards jump to the matching detailed prediction. */
        .gen-card.gen-clickable { cursor: pointer; transition: box-shadow .15s ease, transform .05s ease; }
        .gen-card.gen-clickable:hover { box-shadow: 0 3px 12px rgba(0,0,0,.12); }
        .gen-card.gen-clickable:active { transform: translateY(1px); }
        .gen-jumphint { font-size: .74rem; font-weight: 700; color: var(--sindoor); margin-top: 5px; }
        .gen-card.gen-c-pos .gen-jumphint { color: #15803d; }
        .gen-card.gen-c-neg .gen-jumphint { color: #b91c1c; }
        .gen-card.gen-c-mix .gen-jumphint { color: #1d4ed8; }
        /* Varshaphal prediction card: cap height + scroll long panes. */
        .vp-pred-scroll { flex: 1 1 auto; min-height: 0; max-height: 620px; overflow-y: auto; padding-right: 4px; }
        .vp-pred-scroll::-webkit-scrollbar { width: 8px; }
        .vp-pred-scroll::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }
        .vp-pred-scroll::-webkit-scrollbar-thumb:hover { background: #cbb9a3; }
        /* शाप-दोष pane */
        .sh-card { padding: 8px 11px; margin-bottom: 8px; }
        .sh-card .yoga-title { font-size: .95rem; }
        .sh-card .yoga-why, .sh-card .yoga-res { font-size: .84rem; line-height: 1.5; }
        .sh-card.py-on { border-left-color: #b91c1c !important; background: #fdf3f2; }
        .shaap-note { font-size: .8rem; line-height: 1.55; background: #fdf6e9; border: 1px solid #f0e2c6;
            border-radius: 8px; padding: 8px 11px; color: #6b5600; }
        .shaap-remedy-box { background: #f0f7ff; border: 1px solid #cfe0f5; border-radius: 8px; padding: 6px 11px; margin-top: 8px; }
        .shaap-remedy { font-size: .82rem; line-height: 1.55; padding: 5px 0; border-top: 1px dashed #cfe0f5; }
        .shaap-remedy:first-of-type { border-top: 0; }
        /* भावेश फल cards */
        .bh-card { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px;
            margin-bottom: 12px; background: var(--card); }
        .bh-title { font-weight: 700; font-size: 1.1rem; margin-bottom: 4px; }

        /* ---- Full-width section restyle (Phase 6): token-driven tables ---- */
        .l2-section .bg-white { border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); }
        .l2-section table { font-variant-numeric: tabular-nums; }
        .l2-section table thead th { font-size: 12px; text-transform: uppercase; letter-spacing: .4px;
            color: var(--ink-soft); font-weight: 600; }
        .l2-section table thead tr { border-color: var(--line); }
        .l2-section .border-gray-100, .l2-section .border-b { border-color: var(--line); }
        .l2-section tbody tr:hover { background: var(--sindoor-soft); }
        /* बल tabs */
        .bal-tabbar { display: flex; gap: 8px; flex-wrap: wrap; }
        .bal-tabbar button { border: 1px solid var(--line); border-radius: 6px; background: var(--card);
            padding: 8px 14px; min-height: 44px; font-weight: 600; font-size: .9rem; color: var(--ink); }
        .bal-tabbar button.active { background: var(--sindoor-soft); border-color: var(--sindoor);
            color: var(--sindoor); }

        /* ---- Print (Phase 6): stacked, expanded, controls hidden, chart intact ---- */
        @media print {
            body { background: #fff; }
            .topbar { position: static; }
            .topbar select, #new-kundli, #side-menu, #menu-btn, #menu-overlay, #birth-form, .pred-expand,
            .phala-toggle, #karaka-copy, #hd-copy, #chart-select, #pred-select,
            .bal-tabbar { display: none !important; }
            .l2-grid { display: block; }
            .l2-panel { height: auto !important; min-height: 0; }
            #pred-scroll { overflow: visible; max-height: none; }
            #pred-scroll .hidden { display: block !important; }   /* all cards expanded */
            #pred-scroll pre.hidden { display: none !important; } /* copy buffers stay hidden */
            .l2-card, .l2-section .bg-white { box-shadow: none; }
        }

        /* ---- Expand / collapse reading mode (Phase 5) ---- */
        #chart-panel, #pred-panel { transition: opacity .18s ease; }
        @media (prefers-reduced-motion: reduce) {
            #chart-panel, #pred-panel { transition: none; }
        }
        @media (min-width: 1100px) {
            .pred-expanded #chart-panel { display: none; }
            .pred-expanded #pred-panel { grid-column: 2 / 4; }
            /* Reading mode: bigger copy; card lists flow in two columns, and the
               picker-based cards keep full width with their reading text in two
               columns instead (a single card cannot split across CSS columns). */
            .pred-expanded .pred-view { font-size: 15px; }
            .pred-expanded .pred-view[data-pred="bhavesh"]:not(.hidden),
            .pred-expanded .pred-view[data-pred="yoga"]:not(.hidden) { column-count: 2; column-gap: 32px; }
            .pred-expanded .pred-view[data-pred="bhavesh"] > div,
            .pred-expanded .pred-view[data-pred="yoga"] > div { break-inside: avoid; }
            .pred-expanded .planet-detail:not(.hidden),
            .pred-expanded .house-detail:not(.hidden),
            .pred-expanded .karaka-detail:not(.hidden) { column-count: 2; column-gap: 32px; }
            .pred-expanded #planet-detail-pane, .pred-expanded #house-detail-pane,
            .pred-expanded #karaka-detail-pane { max-height: none !important; }
        }
        #pred-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding-right: 4px;
            scrollbar-width: thin; scrollbar-color: var(--line) transparent; }
        #pred-scroll::-webkit-scrollbar { width: 8px; }
        #pred-scroll::-webkit-scrollbar-thumb { background: var(--line); border-radius: 4px; }
        /* Cards nested in the prediction panel: flatter, token-bordered. */
        #pred-scroll > div, #pred-scroll .pred-view > div { box-shadow: none; border: 1px solid var(--line);
            border-radius: 8px; margin-bottom: 12px; }
        /* ≤1099px (tablet + phone): the side menu becomes a slide-in drawer opened
           by the ☰ Menu button; panels stack full-width. The drawer keeps the full
           vertical menu with expandable sub-items (unlike the old chip bar). */
        @media (max-width: 1099px) {
            .l2-grid { display: block; }
            #menu-btn { display: inline-flex; }
            .l2-menu {
                position: fixed; top: 0; left: 0; z-index: 60;
                width: min(84vw, 300px); height: 100dvh; overflow-y: auto;
                border-radius: 0; margin: 0; padding: 8px 0;
                box-shadow: 2px 0 18px rgba(0,0,0,.28);
                transform: translateX(-100%); transition: transform .22s ease;
                -webkit-overflow-scrolling: touch;
            }
            body.menu-open { overflow: hidden; }
            body.menu-open .l2-menu { transform: translateX(0); }
            .l2-panel { min-height: 0; height: auto !important; margin-bottom: 16px; }
            #pred-scroll { max-height: 70vh; }
            .l2-section { margin-top: 12px; }
            /* Prediction picker: keep it compact and inside the panel on phones
               (a <select> won't shrink below its option text, so we size it to
               content, cap at 100%, and let the row wrap as a safety net so the
               ⤢ button never gets pushed off-screen). */
            .pred-head { flex-wrap: wrap; }
            .l2-picker { flex-wrap: wrap; gap: 6px 8px; }
            .l2-picker .pick-tag { font-size: .95rem; }
            .l2-picker .l2-select { flex: 0 1 auto; width: auto; max-width: 100%; min-width: 0;
                font-size: .86rem; padding: 6px 26px 6px 10px; min-height: 36px; }
        }
        @media (min-width: 1100px) { #menu-overlay { display: none !important; } }
        @media (prefers-reduced-motion: reduce) { .l2-menu { transition: none; } }

        /* Short button labels (New / Save) are shown on phones only. */
        .btn-lbl-short { display: none; }
        /* ---- Tablet + phone (≤1099px) top bar: keep only the controls on ONE
           compact row. Hide the brand title, the "under testing" banner and the
           name/date/place text so the header doesn't eat the working screen.
           (≤1099px is also where the side menu becomes the ☰ drawer.) */
        @media (max-width: 1099px) {
            .topbar .brand { display: none; }
            .test-banner { display: none; }
            .topbar .meta > span { display: none; }        /* name / date / place */
            .topbar-inner { flex-wrap: nowrap; gap: 10px; padding: 8px 14px; }
            .topbar .meta { margin-left: auto; gap: 10px; flex-wrap: nowrap; align-items: center; }
        }
        /* Phone (≤640px): tighten further and use short button labels so Menu ·
           language · Save · New still fit one row on the narrowest screens. */
        @media (max-width: 640px) {
            .topbar-inner { gap: 8px; padding: 8px 10px; }
            .topbar .meta { gap: 6px; }
            .topbar select { min-height: 40px; padding: 6px 22px 6px 8px; font-size: .8rem; }
            .btn-sindoor, .btn-save { min-height: 40px; padding: 7px 11px; font-size: .82rem; }
            #menu-btn { min-height: 40px; padding: 7px 11px; font-size: .82rem; }
            .btn-lbl-full { display: none; }
            .btn-lbl-short { display: inline; }
        }
        /* Detail-view cards: gentle tint + definition; headers get a colour accent. */
        #details-view > div { background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%); border: 1px solid #e6edf6; }
        #details-view h2 {
            background: linear-gradient(90deg, #dbeafe 0%, #eff5ff 55%, rgba(255,255,255,0) 100%);
            border-left: 4px solid #2563eb; padding: 5px 10px; border-radius: 4px;
        }
        /* Prediction body text +20% (over text-sm) for readability. */
        #phala-pos, #phala-neg, #phala-rem,
        #planet-phala-card .whitespace-pre-line { font-size: 1.05rem; line-height: 1.6; }
        /* Planet Prediction headings — bold + larger so they read as headings. */
        #planet-phala-card .planet-pick { font-size: 1.1rem; font-weight: 600; }
        #planet-phala-card .pp-name { font-size: 1.3rem;  font-weight: 700; }
        /* ग्रह स्थिति computed block (migration 008) */
        .gc-block { border: 1px solid var(--line); border-left: 4px solid var(--sindoor);
            border-radius: 8px; padding: 8px 12px; margin: 6px 0 12px; background: #fffdf9; }
        .gc-head { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
        .gc-status { font-weight: 600; color: var(--ink); margin-bottom: 4px; }
        .gc-combust { color: var(--ashubh); margin-bottom: 4px; }
        .gc-lines { list-style: disc; padding-left: 20px; margin: 0; }
        .gc-line { margin: 2px 0; }
        .gc-line.gc-good { color: var(--shubh); }
        .gc-line.gc-bad { color: var(--ashubh); }
        .gc-line.gc-yoga { font-weight: 600; }
        .gc-chip { font-size: .75rem; font-weight: 700; border-radius: 999px; padding: 2px 10px; white-space: nowrap; }
        /* Verdict scale — green (favourable) · blue (mixed) · red (adverse).
           Gochar uses a finer 5-step ramp but stays inside the same 3 colours:
           very-shubh/shubh = green, mishrit = blue, pratikul/ati/ashubh = red. */
        .gc-vshubh { background: #cbead8; color: #14532d; }
        .gc-shubh  { background: #dff0e6; color: #15803d; }
        .gc-mishrit{ background: #dce8ff; color: #1d4ed8; }
        .gc-pratikul { background: #fbdcd7; color: #c0392b; }
        .gc-ati    { background: #f4b8ae; color: #7f1d1d; }
        .gc-ashubh { background: #fbdcd7; color: #b91c1c; }
        .gc-legend { font-size: .74rem; color: #94a3b8; margin: 0 0 8px; display: flex; align-items: center; gap: 5px; flex-wrap: wrap; }
        .gc-legend .gc-chip { font-size: .68rem; padding: 1px 7px; }
        /* Tajik भाव-फल rules — colour-code the left border by type (green/red/
           blue/मृत्यु), with a light tint when the rule applies this year. */
        .tb-card.tbc-shubh   { border-left: 4px solid var(--shubh); }
        .tb-card.tbc-ashubh  { border-left: 4px solid var(--ashubh); }
        .tb-card.tbc-mishrit { border-left: 4px solid var(--mishra); }
        .tb-card.tbc-mrityu  { border-left: 4px solid #4c0519; }
        .tb-card.tbc-niyam   { border-left: 4px solid #94a3b8; }
        .tb-card.tbc-on.tbc-shubh   { background: #f2fbf6; }
        .tb-card.tbc-on.tbc-ashubh  { background: #fdf3f2; }
        .tb-card.tbc-on.tbc-mishrit { background: #eef3ff; }
        .tb-card.tbc-on.tbc-mrityu  { background: #fdf2f5; }
        .tb-card.tbc-on.tbc-niyam   { background: #f8fafc; }

        /* ---- Calculated Dasha engine cards (दशा फल v2) ---- */
        .de-overall { display: flex; align-items: flex-start; gap: 8px; background: #fffdf9;
            border: 1px solid var(--line); border-left: 4px solid var(--sindoor); border-radius: 8px;
            padding: 10px 12px; margin-bottom: 12px; }
        .de-overall-txt { font-weight: 600; color: var(--ink); }
        .de-card { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px; margin-bottom: 10px; background: var(--card); }
        .de-head { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; flex-wrap: wrap; }
        .de-title { font-weight: 700; font-size: 13px; color: var(--ink); }
        .de-facts { font-size: .9rem; color: #453F37; font-weight: 500; margin-bottom: 5px; }
        .de-line { margin: 3px 0; line-height: 1.6; }
        .de-line b { font-weight: 700; }
        .de-pos b { color: var(--shubh); }
        .de-neg b { color: var(--ashubh); }
        .de-rem b, .de-rem { color: var(--haldi); }
        /* उपाय — highlighted remedy box (matches the Lal Kitab remedy styling) */
        .de-rembox { background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px;
                     padding: 7px 11px; margin: 7px 0 3px; color: #7c2d12; line-height: 1.6; }
        .de-rembox b { color: #9a3412; }
        .de-remlist { list-style: none; padding-left: 0; margin: 0; }
        .de-remlist li { color: #7c2d12; margin: 3px 0; }
        .de-classical { margin-top: 8px; border-top: 1px dashed var(--line); padding-top: 8px; }
        .de-classical-btn { font-size: 12px; font-weight: 600; color: var(--ink-soft); }
        .de-classical-btn:hover { color: var(--sindoor); }
        #planet-phala-card .pp-sec  { font-size: 1.15rem; font-weight: 700; }
        #planet-phala-card .pp-sub  { font-size: 1.1rem;  font-weight: 700; }

        /* ---- Custom Screen ---- */
        .l2-grid.custom-active > #sec-custom { grid-column: 1 / 4; }
        /* On the Custom Screen the side menu is not a permanent column — it opens
           as an off-canvas drawer via the ☰ Menu button (works on phone AND
           laptop, since the button is force-shown by body.cs-mode below). */
        body.cs-mode #menu-btn { display: inline-flex !important; }
        .l2-grid.custom-active > #side-menu {
            display: block; position: fixed; top: 0; left: 0; z-index: 60;
            width: min(84vw, 320px); height: 100dvh; overflow-y: auto;
            border-radius: 0; margin: 0; padding: 8px 0;
            box-shadow: 2px 0 18px rgba(0,0,0,.28);
            transform: translateX(-100%); transition: transform .22s ease;
        }
        body.menu-open .l2-grid.custom-active > #side-menu { transform: translateX(0); }
        body.cs-mode.menu-open #menu-overlay { display: block !important; }
        @media (prefers-reduced-motion: reduce) { .l2-grid.custom-active > #side-menu { transition: none; } }
        /* Reduce wasted space at the top of the Custom Screen: no overview tiles,
           a compact banner, and minimal padding above the panel grid. */
        body.cs-mode #ov-strip { display: none !important; }
        body.cs-mode main.l2-wrap { padding-top: 6px; }
        body.cs-mode #sec-home { margin-top: 0 !important; }   /* drop the space-y-4 gap */
        body.cs-mode .cs-bar { margin-top: 0; margin-bottom: 8px; }
        body.cs-mode .test-banner { font-size: .8rem; line-height: 1.2; padding: 3px 8px; flex-basis: 200px; }
        .cs-bar { display: flex; align-items: center; gap: 10px 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .cs-title { font-size: 1.15rem; font-weight: 800; color: var(--ink); }
        .cs-title-hi { color: var(--ink-soft); font-weight: 600; font-size: .95rem; }
        .cs-hint { font-size: .82rem; color: var(--ink-soft); }
        .cs-toolbtn { border: 1px solid var(--line); background: var(--card); color: var(--ink);
            font-weight: 700; font-size: .82rem; padding: 7px 12px; border-radius: 8px; }
        .cs-toolbtn:hover { background: var(--sindoor-soft); border-color: var(--sindoor); color: var(--sindoor); }
        .cs-grid { display: flex; flex-wrap: wrap; gap: 14px; align-items: flex-start; }
        .cs-slot { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); display: flex; flex-direction: column;
            overflow: hidden; position: relative;
            /* Drag the bottom-right corner to resize width & height. */
            width: 380px; height: 360px; min-width: 260px; min-height: 200px; max-width: 100%;
            resize: both; }
        .cs-slot.cs-empty { resize: none; height: 300px; }
        /* Hint grip in the corner so users know panels are resizable. */
        .cs-slot:not(.cs-empty)::after { content: ''; position: absolute; right: 2px; bottom: 2px;
            width: 12px; height: 12px; pointer-events: none; opacity: .5;
            background: linear-gradient(135deg, transparent 50%, var(--sindoor) 50%, var(--sindoor) 62%, transparent 62%, transparent 74%, var(--sindoor) 74%, var(--sindoor) 86%, transparent 86%); }
        .cs-slot.cs-empty { border: 2px dashed var(--line); box-shadow: none; align-items: center; justify-content: center;
            cursor: pointer; background: #FBF8F2; }
        .cs-slot.cs-empty:hover { border-color: var(--sindoor); background: var(--sindoor-soft); }
        .cs-plus { font-size: 3rem; line-height: 1; color: var(--sindoor); font-weight: 300; }
        .cs-empty .cs-plus-lbl { font-size: .8rem; color: var(--ink-soft); margin-top: 6px; }
        .cs-head { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border-bottom: 1px solid var(--line);
            background: #FBF8F2; }
        .cs-head .cs-sel { flex: 1 1 auto; min-width: 0; -webkit-appearance: none; appearance: none;
            border: 1px solid var(--sindoor); border-radius: 6px; background: var(--card); color: var(--ink);
            font-weight: 700; font-size: .85rem; padding: 6px 24px 6px 8px;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%23c0392b'><path d='M5 7.5l5 5 5-5z'/></svg>");
            background-repeat: no-repeat; background-position: right 6px center; background-size: 12px; }
        .cs-iconbtn { flex: 0 0 auto; width: 30px; height: 30px; border: 1px solid var(--line); border-radius: 6px;
            background: var(--card); color: var(--ink-soft); font-size: .9rem; display: inline-flex;
            align-items: center; justify-content: center; }
        .cs-iconbtn:hover { border-color: var(--sindoor); color: var(--sindoor); background: var(--sindoor-soft); }
        .cs-body { padding: 10px; overflow: auto; flex: 1 1 auto; min-height: 0; }
        /* Chart panels: the chart fills the box and scales to fit on resize. */
        .cs-body.cs-body-chart { display: flex; overflow: hidden; padding: 8px; }
        .cs-chart-host { flex: 1 1 auto; width: 100%; height: 100%; min-height: 0; min-width: 0;
            display: flex; align-items: center; justify-content: center; }
        .cs-chart-host svg { max-width: 100%; max-height: 100%; }
        .cs-body .pred-view { font-size: 13px; }
        /* picker modal */
        .cs-modal { position: fixed; inset: 0; z-index: 100; background: rgba(31,42,51,.55);
            display: flex; align-items: center; justify-content: center; padding: 16px; }
        .cs-modal.hidden { display: none; }
        .cs-modal-box { background: var(--card); border-radius: 12px; max-width: 560px; width: 100%;
            max-height: 82vh; overflow: auto; box-shadow: 0 10px 40px rgba(0,0,0,.3); }
        .cs-modal-head { display: flex; align-items: center; justify-content: space-between; padding: 14px 16px;
            border-bottom: 1px solid var(--line); font-weight: 800; font-size: 1.05rem; position: sticky; top: 0; background: var(--card); }
        .cs-modal-x { font-size: 1.1rem; color: var(--ink-soft); width: 32px; height: 32px; border-radius: 6px; }
        .cs-modal-x:hover { background: var(--sindoor-soft); color: var(--sindoor); }
        .cs-modal-body { padding: 12px 16px 18px; }
        .cs-group-title { font-weight: 800; color: var(--sindoor); font-size: .9rem; margin: 10px 0 6px; }
        .cs-opts { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; }
        .cs-opt { text-align: left; border: 1px solid var(--line); border-radius: 8px; padding: 9px 11px;
            background: var(--card); color: var(--ink); font-weight: 600; font-size: .85rem; }
        .cs-opt:hover { border-color: var(--sindoor); background: var(--sindoor-soft); color: var(--sindoor); }

        /* ---- Save / Open charts: top-bar Save button, modals, toast ---- */
        .btn-save { background: #0f766e; color: #fff; border: none; border-radius: 8px;
            padding: 7px 13px; font-weight: 700; font-size: .82rem; cursor: pointer; white-space: nowrap; }
        .btn-save:hover { background: #0b5c55; }
        .ab-modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.5); z-index: 1000;
            display: flex; align-items: center; justify-content: center; padding: 16px; }
        .ab-modal-overlay.hidden { display: none; }
        .ab-modal { background: #fff; border-radius: 14px; box-shadow: 0 20px 50px rgba(0,0,0,.3);
            width: 100%; max-width: 420px; overflow: hidden; animation: abpop .16s ease-out; }
        .ab-modal.ab-modal-lg { max-width: 640px; display: flex; flex-direction: column; max-height: 84vh; }
        @keyframes abpop { from { transform: translateY(8px) scale(.98); opacity: 0; } to { transform: none; opacity: 1; } }
        .ab-modal-head { display: flex; align-items: center; gap: 8px; font-weight: 800; font-size: 1rem;
            color: var(--ink); padding: 13px 16px; border-bottom: 1px solid var(--line); }
        .ab-modal-x { margin-left: auto; background: none; border: none; font-size: 1rem; color: #94a3b8;
            cursor: pointer; line-height: 1; padding: 4px; }
        .ab-modal-x:hover { color: #475569; }
        .ab-modal-body { padding: 15px 16px; font-size: .9rem; line-height: 1.6; color: var(--ink); }
        .ab-modal-sub { color: #64748b; font-size: .82rem; margin: 0 0 8px; }
        .ab-modal-note { color: #0f766e; font-size: .78rem; margin: 0; background: #f0fdfa;
            border: 1px solid #ccfbf1; border-radius: 8px; padding: 7px 10px; }
        .ab-modal-foot { padding: 12px 16px; border-top: 1px solid var(--line); text-align: right; }
        /* Save-chart profile popup */
        .ab-save-sum { background: #f8fafc; border: 1px solid var(--line); border-radius: 8px;
                       padding: 7px 11px; font-size: .82rem; color: #475569; margin-bottom: 10px; line-height: 1.6; }
        .ab-save-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px 12px; }
        @media (max-width: 560px) { .ab-save-grid { grid-template-columns: 1fr; } }
        .ab-save-grid label { display: flex; flex-direction: column; gap: 3px;
                              font-size: .76rem; font-weight: 700; color: #475569; }
        .ab-save-grid input { border: 1px solid #cbd5e1; border-radius: 8px; padding: 7px 10px;
                              font-size: .86rem; font-weight: 400; color: #0f172a; }
        .ab-save-grid input:focus { outline: 2px solid #99f6e4; border-color: #0f766e; }
        .ab-req { color: #b91c1c; }
        .ab-save-err { margin-top: 8px; background: #fef2f2; border: 1px solid #fecaca;
                       border-radius: 8px; padding: 6px 10px; color: #b91c1c; font-size: .8rem; }
        .ab-btn { background: var(--sindoor); color: #fff; border: none; border-radius: 8px; padding: 8px 16px;
            font-weight: 700; font-size: .84rem; cursor: pointer; }
        .ab-btn:hover { filter: brightness(.94); }
        .ab-btn-ghost { background: #f1f5f9; color: #b91c1c; }
        .ab-btn-edit { background: #f1f5f9; color: #0f766e; border: 1px solid #99f6e4; }
        .ab-btn-edit:hover { background: #ccfbf1; }
        .ab-btn-sm { padding: 5px 11px; font-size: .78rem; }
        .ab-open-count { font-size: .78rem; font-weight: 600; color: #94a3b8; }
        .ab-open-search { padding: 12px 16px; border-bottom: 1px solid var(--line); }
        .ab-open-search input { width: 100%; border: 1px solid var(--line); border-radius: 9px;
            padding: 9px 12px; font-size: .9rem; }
        .ab-open-search input:focus { outline: 2px solid var(--sindoor); outline-offset: 1px; border-color: var(--sindoor); }
        .ab-open-list { overflow-y: auto; padding: 6px 10px; flex: 1 1 auto; min-height: 120px; }
        .ab-open-row { display: flex; align-items: center; gap: 10px; padding: 9px 8px;
            border-bottom: 1px solid #f1f5f9; }
        .ab-open-row:hover { background: #f8fafc; }
        .ab-open-main { flex: 1 1 auto; min-width: 0; }
        .ab-open-name { font-weight: 700; font-size: .92rem; color: var(--ink); }
        .ab-open-g { font-weight: 500; font-size: .74rem; color: #94a3b8; }
        .ab-open-meta { font-size: .78rem; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ab-open-acts { display: flex; gap: 6px; flex: none; }
        .ab-open-when { font-size: .7rem; color: #cbd5e1; flex: none; width: 70px; text-align: right; }
        .ab-open-empty { text-align: center; color: #94a3b8; font-size: .88rem; padding: 30px 12px; }
        .ab-open-tip { font-size: .74rem; color: #94a3b8; padding: 10px 16px; border-top: 1px solid var(--line); background: #fafafa; }
        .ab-toast { position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%); z-index: 1100;
            padding: 11px 18px; border-radius: 10px; font-weight: 700; font-size: .86rem;
            box-shadow: 0 10px 30px rgba(0,0,0,.22); max-width: 90vw; text-align: center; }
        .ab-toast.hidden { display: none; }
        .ab-toast-ok  { background: #ecfdf5; color: #15803d; border: 1px solid #a7f3d0; }
        .ab-toast-err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        @media (max-width: 640px) {
            .ab-open-meta { white-space: normal; }
            .ab-open-when { display: none; }
        }
    </style>
</head>
<body class="text-gray-900">

<!-- ============ TOP BAR (layout v2, Phase 1) ============ -->
<header class="topbar">
    <div class="topbar-inner">
        <?php if ($chart !== null): ?>
        <button type="button" id="menu-btn" aria-label="मेन्यू / Menu" aria-expanded="false" aria-controls="side-menu">
            <span class="menu-btn-bars" aria-hidden="true"></span>Menu
        </button>
        <?php endif; ?>
        <h1 class="brand" style="margin:0">Analysis of Karma</h1>
        <div class="test-banner">System is Under Testing — Not Finalized Yet.<br>Feedback: <a href="mailto:analysisofkarma@gmail.com">analysisofkarma@gmail.com</a></div>
        <!-- Shown only on the Custom Screen: jump back to the D1 birth chart. -->
        <button type="button" id="cs-back" class="btn-sindoor" style="display:none">Birth Chart (D1)</button>
        <div class="meta">
            <span><b><?= $in['name'] !== '' ? $h($in['name']) : '—' ?></b></span>
            <span><?= $h($in['date']) ?>, <?= $h($in['time']) ?></span>
            <span><?= $h($pobTop) ?></span>
            <select id="topbar-lang" aria-label="भाषा / Language">
                <option value="hi" <?= $phalaLang === 'hi' ? 'selected' : '' ?>>हिन्दी</option>
                <option value="en" <?= $phalaLang === 'en' ? 'selected' : '' ?>>English</option>
            </select>
            <?php if ($chart !== null): ?><button type="button" data-ab-save class="btn-save" title="इस चार्ट को सहेजें">💾 <span class="btn-lbl-full">Save Chart</span><span class="btn-lbl-short">Save</span></button><?php endif; ?>
            <button type="button" id="new-kundli" class="btn-sindoor"><span class="btn-lbl-full">New Kundli</span><span class="btn-lbl-short">New</span></button>
        </div>
    </div>
</header>
<script>
// Top-bar language switch — translates ONLY the prediction text (via ABTranslate);
// the UI (buttons, menu, dropdowns, titles) stays as-is. No page reload.
(function () {
    var sel = document.getElementById('topbar-lang');
    if (!sel) { return; }
    try { var saved = localStorage.getItem('ab_pred_lang'); if (saved) { sel.value = saved; } } catch (e) {}
    sel.addEventListener('change', function () {
        if (window.ABTranslate) { window.ABTranslate.setLang(this.value); }
    });
})();
</script>

<main class="l2-wrap mx-auto space-y-4 p-3 sm:p-4">

    <!-- ============ OVERVIEW TILES (layout v2, Phase 1) ============ -->
    <?php if ($chart !== null): ?>
    <?php
        $ovLagna = (string) ($chart['ascendant']['sign'] ?? '');
        $ovMoon  = (string) ($chart['planets']['Moon']['sign'] ?? '');
        $ovMaha  = (string) ($dashaNow['maha']['lord'] ?? '');
        $ovAntar = (string) ($dashaNow['antar']['lord'] ?? '');
        $ovPrat  = (string) ($dashaNow['pratyantar']['lord'] ?? '');
    ?>
    <?php $ovSun = (string) ($chart['planets']['Sun']['sign'] ?? ''); ?>
    <div id="ov-strip" class="ov-tiles">
        <div class="ov-tile" data-nav="profile" role="button" tabindex="0" title="संपादित करें — New / Profile">
            <div class="ov-label">Name</div>
            <div class="ov-value"><?= $in['name'] !== '' ? $h($in['name']) : '—' ?></div>
            <div class="ov-sub"><?= $in['gender'] !== '' ? $h($in['gender']) : '&nbsp;' ?></div>
        </div>
        <div class="ov-tile" data-nav="profile" role="button" tabindex="0" title="संपादित करें — New / Profile">
            <div class="ov-label">DOB / Time</div>
            <div class="ov-value"><?= $h($in['date']) ?></div>
            <div class="ov-sub"><?= $h($in['time']) ?></div>
        </div>
        <div class="ov-tile" data-nav="profile" role="button" tabindex="0" title="संपादित करें — New / Profile">
            <div class="ov-label">Birth Place</div>
            <div class="ov-value" title="<?= $h($pobTop) ?>"><?= $h($pobTop) ?></div>
            <div class="ov-sub"><?= $h($in['latIn']) ?>, <?= $h($in['lonIn']) ?></div>
        </div>
        <div class="ov-tile" data-nav="chart" role="button" tabindex="0" title="जन्म कुंडली (D1) देखें">
            <div class="ov-label">Lagna (Asc)</div>
            <div class="ov-value"><?= $h($rashiHi[$ovLagna] ?? $ovLagna) ?></div>
            <div class="ov-sub"><?= $h($ovLagna) ?> · <?= $h((string) ($chart['ascendant']['formatted'] ?? '')) ?></div>
        </div>
        <div class="ov-tile" data-nav="chart" role="button" tabindex="0" title="जन्म कुंडली (D1) देखें">
            <div class="ov-label">Moon Sign (राशि)</div>
            <div class="ov-value"><?= $h($rashiHi[$ovMoon] ?? $ovMoon) ?></div>
            <div class="ov-sub">चंद्र राशि · <?= $h($ovMoon) ?></div>
        </div>
        <div class="ov-tile" data-nav="chart" role="button" tabindex="0" title="जन्म कुंडली (D1) देखें">
            <div class="ov-label">Sun Sign</div>
            <div class="ov-value"><?= $h($rashiHi[$ovSun] ?? $ovSun) ?></div>
            <div class="ov-sub">Sun Sign · <?= $h($ovSun) ?></div>
        </div>
        <div class="ov-tile" data-nav="dasha" role="button" tabindex="0" title="दशा देखें">
            <div class="ov-label">Current Dasha</div>
            <div class="ov-value acc-dasha"><?= $h(($grahaHi[$ovMaha] ?? $ovMaha) . ($ovAntar !== '' ? ' – ' . ($grahaHi[$ovAntar] ?? $ovAntar) : '')) ?></div>
            <div class="ov-sub"><?= $ovPrat !== '' ? 'प्रत्यंतर: ' . $h($grahaHi[$ovPrat] ?? $ovPrat) : '&nbsp;' ?></div>
        </div>
        <?php
            // Total ACTIVE yogas in the kundali (unified Phaladeepika detection).
            $ovPY = $view['phala_yoga'] ?? null;
            $ovYActive = (int) ($ovPY['detected_count'] ?? 0);
            $ovYSum = $ovPY['active_summary'] ?? ['shubh' => 0, 'ashubh' => 0, 'mishrit' => 0];
        ?>
        <div class="ov-tile" data-nav="yoga" role="button" tabindex="0" title="योग फलादेश देखें">
            <div class="ov-label">Yoga (योग)</div>
            <div class="ov-value acc-yoga"><?= $ovYActive > 0 ? $ovYActive . ' सक्रिय योग' : '—' ?></div>
            <div class="ov-sub"><?= $ovYActive > 0
                ? $h((int) ($ovYSum['shubh'] ?? 0) . ' शुभ · ' . (int) ($ovYSum['ashubh'] ?? 0) . ' अशुभ' . (($ovYSum['mishrit'] ?? 0) ? ' · ' . (int) $ovYSum['mishrit'] . ' मिश्र' : ''))
                : 'कोई सक्रिय योग नहीं' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($view['error'] !== null): ?>
        <div class="bg-red-100 text-red-800 rounded p-3 text-sm">Error: <?= $h($view['error']) ?></div>
    <?php endif; ?>

    <?php if ($chart === null): ?>
        <!-- No valid chart yet: show the entry form standalone so the visitor can
             enter details. Once a chart exists it moves into the New / Profile
             side-menu section (below). -->
        <?php require __DIR__ . '/_birth_form.php'; ?>
    <?php endif; ?>

    <?php if ($chart !== null): ?>

    <?php
        $pob = $in['place'] !== '' ? $in['place'] : ($in['latIn'] . ', ' . $in['lonIn']);
        $field = static function (string $label, string $value) use ($h): string {
            return '<div><div class="text-xs text-gray-500">' . $h($label) . '</div>'
                . '<div class="font-semibold text-gray-800">' . ($value !== '' ? $h($value) : '—') . '</div></div>';
        };
    ?>

    <!-- ============ THREE-PANEL SHELL (layout v2, Phase 2) ============ -->
    <div id="sec-home" class="l2-grid">

        <!-- Side menu -->
        <nav id="side-menu" class="l2-menu l2-card" aria-label="Sections">
            <div class="l2-mi">
                <button type="button" data-sec="profile">New / Profile</button>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="today">Today (आज का Consult)</button>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="custom">Custom Screen</button>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="home" class="active">Birth Chart</button>
                <div class="l2-sub">
                    <button type="button" data-sec="home" data-target="chart-panel">Kundali Chart</button>
                    <button type="button" data-sec="home" data-target="pred-panel">Predictions</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="grah">Planet Positions</button>
                <div class="l2-sub">
                    <button type="button" data-sec="grah" data-target="card-native">Birth Details</button>
                    <button type="button" data-sec="grah" data-target="card-housedet">House Details</button>
                    <button type="button" data-sec="grah" data-target="card-d1pos">Positions (D1)</button>
                    <button type="button" data-sec="grah" data-target="card-gochardetails">Gochar Details</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="varga">Varga Charts</button>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="dasha">Dasha</button>
                <div class="l2-sub">
                    <button type="button" data-sec="dasha" data-target="card-curdasha">Current Dasha</button>
                    <button type="button" data-sec="dasha" data-target="card-vimtree">Vimshottari (5 levels)</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="bal">Bala (Strength)</button>
                <div class="l2-sub">
                    <button type="button" data-sec="bal" data-tab="shad">Shadbala</button>
                    <button type="button" data-sec="bal" data-tab="bb">Bhava Bala</button>
                    <button type="button" data-sec="bal" data-tab="av">Ashtakavarga</button>
                    <button type="button" data-sec="bal" data-tab="vim">Vimshopaka</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="gochar">Gochar (Transit)</button>
                <div class="l2-sub">
                    <button type="button" data-sec="gochar" data-target="card-gocharcalc">Gochar Calculation</button>
                    <button type="button" data-sec="gochar" data-target="card-gocharpair">Chart + Prediction (Row 1)</button>
                    <button type="button" data-sec="gochar" data-target="card-gochardet">Chart + Prediction (Row 2)</button>
                    <button type="button" data-sec="gochar" data-target="yt-card">12-Month Timeline</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="muhurat">Mahurat (मुहूर्त)</button>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="varsha">Varshaphal</button>
                <div class="l2-sub">
                    <button type="button" data-sec="varsha" data-target="card-vpbox">Year Selection</button>
                    <button type="button" data-sec="varsha" data-target="vp-output">Varsha Chart + Mudda Dasha</button>
                    <button type="button" data-sec="varsha" data-target="card-varshadet">Annual Positions</button>
                    <button type="button" data-sec="varsha" data-target="vp-row3">Bala + Year Lord</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="lalkitab">Laal Kitab (लाल किताब)</button>
            </div>
            <div class="l2-mi">
                <a href="<?= $h(\AutoBusiness\Core\Asset::url('/milan')) ?>" class="l2-mi-link">Kundali Milan</a>
            </div>
        </nav>
        <!-- Backdrop for the mobile/tablet slide-in menu drawer. -->
        <div id="menu-overlay" hidden></div>

        <!-- Chart panel (middle column) -->
        <?php
            // Chart selector: every computed varga + गोचर + वर्ष कुंडली.
            $vargaHi = [
                'D1' => 'Birth Chart (Rasi)', 'D2' => 'Hora', 'D3' => 'Drekkana', 'D4' => 'Chaturthamsa',
                'D7' => 'Saptamsa', 'D9' => 'Navamsa', 'D10' => 'Dasamsa', 'D12' => 'Dwadasamsa',
                'D16' => 'Shodasamsa', 'D20' => 'Vimsamsa', 'D24' => 'Chaturvimsamsa', 'D27' => 'Bhamsa',
                'D30' => 'Trimsamsa', 'D40' => 'Khavedamsa', 'D60' => 'Shashtiamsa',
            ];
        ?>
        <section id="chart-panel" class="l2-card l2-panel" aria-label="कुंडली चार्ट">
            <div class="l2-picker chart-picker">
            <span class="pick-tag">Chart ▾</span>
            <select id="chart-select" class="l2-select" aria-label="कुंडली चुनें">
                <?php foreach ($vargaHi as $vk => $vlbl): if (!isset($vargas[$vk])) { continue; } ?>
                    <option value="<?= $h($vk) ?>"><?= $h($vk) ?> — <?= $h($vlbl) ?></option>
                <?php endforeach; ?>
                <?php if ($gochar !== null): ?><option value="gochar">Gochar (Transit)</option><?php endif; ?>
                <?php if (($view['varshaNorth'] ?? null) !== null): ?><option value="varsha">Varsha Kundali (<?= (int) $in['forYear'] ?>)</option><?php endif; ?>
            </select>
            <span class="pick-tag rot-tag" title="किसी भी भाव को प्रथम भाव पर घुमाएँ">Rotate ▾</span>
            <select id="chart-rotate" class="l2-select rot-select" aria-label="चार्ट घुमाएँ (भाव चुनें)">
                <option value="1" selected>भाव 1 — लग्न</option>
                <?php for ($rh = 2; $rh <= 12; $rh++): ?><option value="<?= $rh ?>">भाव <?= $rh ?></option><?php endfor; ?>
            </select>
            </div><!-- /.l2-picker -->
            <div class="l2-legend">
                <span style="color:#1d4ed8"><b>AV:</b> Ashtakavarga</span> ·
                <span style="color:#15803d"><b>BB:</b> Bhav Bala</span> ·
                <span><b>Dr:</b> Drishti</span>
            </div>
            <div id="chart-frame" class="chart-frame"></div>

            <!-- DASHA STRIP: always visible at the panel bottom, every selection.
                 Existing Vimshottari data (running chain + next antar) — formatting only. -->
            <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null):
                $tzs = (float) ($meta['tz'] ?? 0);
                $dmy = static fn(array $p, string $sep = '–'): string =>
                    \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['start_jd'], $tzs)
                    . ' ' . $sep . ' ' . \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['end_jd'], $tzs);
                $stripRow = static function (string $label, ?array $p, int $depth, bool $running = false, string $sep = '–') use ($pcolor, $h, $dmy): string {
                    if (empty($p)) { return ''; }
                    $arrow = $depth > 0 ? '<span class="ds-arrow">↳</span> ' : '';
                    return '<div class="ds-row" style="padding-left:' . ($depth * 16) . 'px">' . $arrow
                        . '<b class="ds-label">' . $h($label) . ':</b> '
                        . '<b style="color:' . $pcolor($p['lord']) . '">' . $h($p['lord']) . '</b> '
                        . '<span class="ds-dates">(' . $h($dmy($p, $sep)) . ')</span>'
                        . ($running ? ' <span class="ds-pill">चालू</span>' : '')
                        . '</div>';
                };
            ?>
            <div class="dasha-strip" aria-label="चालू दशा">
                <?= $stripRow('MahaDasha', $dashaNow['maha'], 0) ?>
                <?= $stripRow('AntarDasha', $dashaNow['antar'], 1) ?>
                <?= $stripRow('Pratyantar', $dashaNow['pratyantar'], 2, true) ?>
                <?= $stripRow('Next Antardasha', $dashaNow['next_antar'], 1, false, '→') ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- Prediction panel (right column) -->
        <section id="pred-panel" class="l2-card l2-panel" aria-label="फलादेश">
            <div class="pred-head">
                <div class="l2-picker" style="flex:1; margin-bottom:0">
                <span class="pick-tag">Select ▾</span>
                <select id="pred-select" class="l2-select" aria-label="फलादेश चुनें" style="margin-bottom:0">
                    <option value="general" selected>General Overview</option>
                    <option value="dasha">Dasha (दशा)</option>
                    <option value="grah">Planet (ग्रह)</option>
                    <option value="bhav">House (भाव)</option>
                    <option value="bhavesh">Bhavesh (भावेश)</option>
                    <option value="karak">Karak (कारक)</option>
                    <option value="yoga">Kundali Yog (योग)</option>
                    <option value="dosha">Dosha (दोष व परिहार)</option>
                    <option value="shaap">Shrap (पूर्वशाप व सन्तान)</option>
                    <option value="search" hidden>🔍 खोज परिणाम</option>
                </select>
                </div>
                <button type="button" id="d1-print" class="pred-expand" aria-label="Print report" title="D1 रिपोर्ट Print करें" style="width:auto;padding:0 8px;font-size:.8rem">🖨</button>
                <button type="button" id="pred-expand" class="pred-expand" aria-label="Expand" title="Expand">⤢</button>
            </div>
            <!-- विषय-खोज: chips + free text, scans every prediction block. -->
            <div id="pred-topic-row" style="display:flex;align-items:center;gap:5px;flex-wrap:wrap;margin:6px 0 4px">
                <input type="text" id="pred-q" placeholder="🔍 विषय खोजें…" aria-label="फलादेश में खोजें"
                       style="flex:1;min-width:110px;border:1px solid #cbd5e1;border-radius:8px;padding:3px 9px;font-size:.76rem">
                <?php foreach (['धन' => '💰', 'विवाह' => '💑', 'संतान' => '👶', 'रोग' => '🩺', 'नौकरी' => '💼', 'शिक्षा' => '🎓', 'विदेश' => '✈'] as $tpc => $tpi): ?>
                <button type="button" class="lk-chip" data-topic="<?= $h($tpc) ?>" style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:999px;padding:1px 9px;font-size:.72rem;color:#475569;cursor:pointer"><?= $tpi ?> <?= $h($tpc) ?></button>
                <?php endforeach; ?>
            </div>
            <div id="pred-scroll">

            <!-- सामान्य (General) — default: conclusion of all birth-chart layers. -->
            <div class="pred-view" data-pred="general">
                <?php require __DIR__ . '/_general_summary.php'; ?>
            </div><!-- /pred-view general -->

            <div class="pred-view hidden" data-pred="dasha">
    <!-- Dasha Prediction (दशा फल): Maha/Antar dropdowns default to the running
         dasha; text comes from the editable dasha_phala table. Shown in both views. -->
    <?php
        $phala = $view['phala'] ?? ['lang' => 'hi', 'maha' => 'Sun', 'antar' => 'Sun', 'text' => null];
        $pLords = \AutoBusiness\Astro\Phala\DashaPhalaRepository::LORDS;
        $pHi    = \AutoBusiness\Astro\Phala\DashaPhalaRepository::LORDS_HI;
        $pOpt = static function (string $selected) use ($pLords, $pHi, $h): string {
            $out = '';
            foreach ($pLords as $L) {
                $out .= '<option value="' . $h($L) . '"' . ($L === $selected ? ' selected' : '') . '>'
                      . $h($L) . ' / ' . $h($pHi[$L] ?? '') . '</option>';
            }
            return $out;
        };
        $pText = $phala['text'];
    ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm" id="dasha-phala-card"
         data-lang="<?= $h((string) $phala['lang']) ?>">
        <div class="flex flex-wrap items-end gap-x-6 gap-y-3 mb-3">
            <h2 class="font-semibold">Dasha Prediction <span class="text-xs text-gray-400 font-normal">(दशा फल)</span></h2>
            <button type="button" id="dasha-detail-btn" class="dasha-detail-btn">📅 Dasha Details</button>
            <div class="dp-picker">
                <span class="pred-picker-label">यहाँ से चुनें ▾</span>
                <label class="dp-field"><span>Mahadasha</span>
                    <select id="phala-maha" class="dp-select"><?= $pOpt((string) $phala['maha']) ?></select></label>
                <label class="dp-field"><span>Antardasha</span>
                    <select id="phala-antar" class="dp-select"><?= $pOpt((string) $phala['antar']) ?></select></label>
            </div>
            <?php /* dp-picker sits on its own full-width row so Maha + Antar stay on one line */ ?>
            <span class="text-xs text-gray-400">Running now: <b><?= $h((string) $phala['maha']) ?></b> / <b><?= $h((string) $phala['antar']) ?></b></span>
            <button type="button" class="phala-toggle ml-auto text-xs bg-gray-100 hover:bg-gray-200 border rounded px-2 py-1 font-semibold" data-target="dasha-body" aria-expanded="true">Collapse ▴</button>
        </div>
        <div id="dasha-body">

        <!-- Calculated Dasha engine (दशा फल v2): chart-specific cards. -->
        <?php $de = $view['dashaEngine'] ?? null; ?>
        <div id="dasha-cards">
            <?php if ($de !== null && !empty($de['data'])): $eng = $de['data']; require __DIR__ . '/_dasha_cards.php'; ?>
            <?php elseif ($de !== null && !empty($de['error'])): ?>
                <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                    Database not reachable — staff note: <?= $h((string) $de['error']) ?>.
                    Check <code>.env</code> and that <code>migrations/011_dasha_engine.sql</code> is imported.
                </div>
            <?php else: ?>
                <div class="text-gray-500 italic">Calculated dasha analysis not available yet (import migration 011).</div>
            <?php endif; ?>
        </div>
        <div id="dasha-cards-loading" class="text-xs text-gray-400 hidden">गणना हो रही है…</div>

        <!-- 7. शास्त्रीय संदर्भ (BPHS) — the existing 81-combo text, collapsed. -->
        <div class="de-classical">
            <button type="button" id="classical-toggle" class="de-classical-btn" aria-expanded="true">▾ शास्त्रीय संदर्भ (BPHS 81 योग)</button>
            <div id="classical-body" class="mt-2">
        <div id="phala-sections" class="grid grid-cols-1 md:grid-cols-3 gap-4<?= $pText ? '' : ' hidden' ?>">
            <div>
                <div class="font-semibold text-green-700 mb-1">सकारात्मक फल <span class="text-gray-400 font-normal">(Positive)</span></div>
                <div id="phala-pos" class="whitespace-pre-line text-gray-800"><?= $h((string) ($pText['positive_text'] ?? '')) ?></div>
            </div>
            <div>
                <div class="font-semibold text-red-700 mb-1">नकारात्मक फल <span class="text-gray-400 font-normal">(Negative)</span></div>
                <div id="phala-neg" class="whitespace-pre-line text-gray-800"><?= $h((string) ($pText['negative_text'] ?? '')) ?></div>
            </div>
            <div>
                <div class="font-semibold text-blue-700 mb-1">उपाय <span class="text-gray-400 font-normal">(Remedy)</span></div>
                <div id="phala-rem" class="whitespace-pre-line text-gray-800"><?= $h((string) ($pText['remedy_text'] ?? '')) ?></div>
            </div>
        </div>
        <div id="phala-empty" class="text-gray-500 italic<?= $pText ? ' hidden' : '' ?>">Summary not available yet for this combination.</div>
        <?php if (!$pText && !empty($phala['error'])): ?>
        <div id="phala-dberr" class="mt-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
            Database not reachable for predictions — staff note: <?= $h((string) $phala['error']) ?>.
            Check the <code>.env</code> DB settings (DB_HOST / DB_NAME / DB_USER / DB_PASS) match the database you imported into.
        </div>
        <?php endif; ?>
            </div><!-- /#classical-body -->
        </div><!-- /.de-classical -->
        </div><!-- /#dasha-body -->
    </div>
            </div><!-- /pred-view dasha -->

            <div class="pred-view hidden" data-pred="grah">
    <!-- Planet Prediction: (A) as house-lord (Bhavesh Phal) and (B) as placement
         (Graha-in-Bhava). Built from the chart's ruled/placed houses. Both views. -->
    <?php
        $pp = $view['planetPhala'] ?? null;
        $ppHi = \AutoBusiness\Astro\Phala\DashaPhalaRepository::LORDS_HI;
        $ord2 = static function (int $n): string {
            $s = ['th','st','nd','rd'];
            $v = $n % 100;
            return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
        };
    ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm" id="planet-phala-card">
        <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mb-3">
            <h2 class="font-semibold">Planet Prediction <span class="text-xs text-gray-400 font-normal">(ग्रह फल)</span></h2>
            <span class="text-xs text-gray-400">(A) as House-Lord — Bhavesh Phal &nbsp;·&nbsp; (B) as Placement — Graha in Bhava</span>
            <button type="button" class="phala-toggle ml-auto text-xs bg-gray-100 hover:bg-gray-200 border rounded px-2 py-1 font-semibold" data-target="planet-body" aria-expanded="true">Collapse ▴</button>
        </div>
        <?php if ($pp === null): ?>
            <div class="text-gray-500 italic">Chart not available.</div>
        <?php else: ?>
            <div id="planet-body">
            <?php if (!empty($pp['error'])): ?>
            <div class="mb-3 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                Database not reachable — staff note: <?= $h((string) $pp['error']) ?>.
                Check <code>.env</code> DB settings and that <code>migrations/004_planet_phala.sql</code> is imported.
            </div>
            <?php endif; ?>
            <!-- Pick a planet from the dropdown -> its prediction (scrolls). -->
            <div id="planet-grid">
                <div class="pred-picker">
                    <label class="pred-picker-label" for="planet-select">Select Planet (ग्रह चुनें)</label>
                    <select id="planet-select" class="pred-inline-select">
                    <?php foreach ($pp['planets'] as $i => $row): $pl = $row['planet']; ?>
                        <option value="<?= $h($pl) ?>"><?= $h($pl) ?> (<?= $h($ppHi[$pl] ?? '') ?>)</option>
                    <?php endforeach; ?>
                    </select>
                </div>
                <div class="overflow-y-auto pr-1" style="max-height:460px" id="planet-detail-pane">
                    <?php foreach ($pp['planets'] as $i => $row):
                        $pl = $row['planet'];
                        $placed = (int) $row['placed_house'];
                        $rules = array_map(static fn($e) => (int) $e['ruled_house'], $row['lord_entries']);
                    ?>
                    <div class="planet-detail<?= $i === 0 ? '' : ' hidden' ?>" data-planet="<?= $h($pl) ?>">
                        <div class="pp-name text-gray-800 mb-1">
                            <span style="color:<?= $pcolor($pl) ?>"><?= $h($pl) ?></span>
                            <span class="text-gray-400 font-normal">(<?= $h($ppHi[$pl] ?? '') ?>)</span>
                            <span class="text-xs text-gray-500 font-normal">
                                — placed in <b><?= $ord2($placed) ?></b> house<?php
                                echo $rules ? ', rules ' . implode(', ', array_map($ord2, $rules)) . ' house' . (count($rules) > 1 ? 's' : '') : ', rules no house (node)'; ?>
                            </span>
                        </div>

                        <!-- Prediction-confidence + फल-काल (StrengthMeter): how
                             strongly this planet's results will manifest and in
                             which dasha windows. -->
                        <?php $sm = $view['strength']['planets'][$pl] ?? null; if ($sm !== null):
                            $smCls = $sm['tier'] === 'pos' ? 'gc-shubh' : ($sm['tier'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit'); ?>
                        <div style="border:1px dashed #cbd5e1;border-radius:8px;padding:6px 10px;margin:4px 0 8px;background:#fafcff">
                            <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                                <span class="pp-sub text-gray-700">फल-बल</span>
                                <span class="gc-chip <?= $smCls ?>"><?= $h((string) $sm['word']) ?> (<?= (int) $sm['score'] ?>)</span>
                                <?php if (!empty($sm['d9'])): ?><span class="gc-chip gc-mishrit" style="background:#ede9fe;color:#5b21b6"><?= $h((string) $sm['d9']) ?></span><?php endif; ?>
                                <?php $fn = $sm['functional'] ?? null; if ($fn !== null):
                                    $fnCls = $fn['tier'] === 'pos' ? 'gc-shubh' : ($fn['tier'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit'); ?>
                                    <span class="gc-chip <?= $fnCls ?>" title="<?= $h((string) $fn['why']) ?>">इस लग्न में <?= $h((string) $fn['word']) ?></span>
                                    <?php if (!empty($fn['maraka'])): ?><span class="gc-chip gc-ashubh" title="2/7 भाव का स्वामी">मारक</span><?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($sm['reasons'])): ?>
                                <div class="text-xs text-gray-500" style="margin-top:2px"><?= $h(implode(' · ', $sm['reasons'])) ?></div>
                            <?php endif; ?>
                            <?php $tmg = $sm['timing'] ?? null; if ($tmg !== null && ($tmg['maha'] !== null || $tmg['antar'] !== [])): ?>
                            <div class="text-xs" style="margin-top:3px;color:#475569">
                                <b>⏳ फल-काल:</b>
                                <?php if ($tmg['maha'] !== null): ?>
                                    महादशा <?= $h($tmg['maha']['from']) ?> – <?= $h($tmg['maha']['to']) ?>
                                    <?= $tmg['maha']['status'] === 'running' ? '<b style="color:#166534">(चालू)</b>' : ($tmg['maha']['status'] === 'past' ? '(बीत चुकी)' : '(आगामी)') ?>
                                <?php endif; ?>
                                <?php foreach ($tmg['antar'] as $ad): ?>
                                    · <?= $h($ppHi[$ad['maha']] ?? $ad['maha']) ?>-महादशा में अंतर्दशा <?= $h($ad['from']) ?> – <?= $h($ad['to']) ?><?= !empty($ad['running']) ? ' <b style="color:#166534">(चालू)</b>' : '' ?>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- ग्रह स्थिति block (migration 008) — computed dignity /
                             combustion / companions, above the unchanged (A)/(B). -->
                        <?php $gc = $row['condition'] ?? null; if ($gc !== null): ?>
                        <div class="gc-block">
                            <div class="gc-head">
                                <span class="pp-sub text-gray-700">ग्रह स्थिति</span>
                                <span class="gc-chip gc-<?= $h((string) ($gc['verdict']['tier'] ?? 'mishrit')) ?>"><?= $h((string) ($gc['verdict']['word'] ?? '')) ?></span>
                            </div>
                            <?php if (!empty($gc['status'])): ?>
                                <div class="gc-status"><?= $h((string) $gc['status']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($gc['combust'])): ?>
                                <div class="gc-combust"><?= $h((string) $gc['combust']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($gc['lines'])): ?>
                            <ul class="gc-lines">
                                <?php foreach ($gc['lines'] as $ln): ?>
                                    <li class="gc-line<?= $ln['kind'] === 'yoga' ? ' gc-yoga' : '' ?><?= $ln['good'] === true ? ' gc-good' : ($ln['good'] === false ? ' gc-bad' : '') ?>"><?= $h((string) $ln['text']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- (A) As House-Lord -->
                        <div class="mb-2">
                            <div class="pp-sec text-indigo-700 mb-0.5">(A) As House-Lord — Bhavesh Phal</div>
                            <?php if (!$row['lord_entries']): ?>
                                <div class="text-gray-500 italic">Not applicable — <?= $h($pl) ?> does not own a house.</div>
                            <?php else: foreach ($row['lord_entries'] as $e): ?>
                                <div class="mb-1">
                                    <span class="text-xs text-gray-500">Lord of <b><?= $ord2((int) $e['ruled_house']) ?></b> house, placed in <b><?= $ord2((int) $e['placed_house']) ?></b> house:</span>
                                    <?php if ($e['text'] !== null && $e['text'] !== ''): ?>
                                        <div class="whitespace-pre-line text-gray-800"><?= $h((string) $e['text']) ?></div>
                                    <?php else: ?>
                                        <div class="text-gray-500 italic">Summary not available yet for this combination.</div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                        <!-- (B) As Placement -->
                        <div>
                            <div class="pp-sec text-teal-700 mb-0.5">(B) As Placement — Graha in Bhava <span class="text-gray-400 font-normal text-sm">(in <?= $ord2($placed) ?> house)</span></div>
                            <?php $plc = $row['placement']; if ($plc !== null && (($plc['positive_text'] ?? '') !== '' || ($plc['negative_text'] ?? '') !== '')): ?>
                                <div class="mb-1">
                                    <span class="pp-sub text-green-700">शुभ फल (Positive):</span>
                                    <div class="whitespace-pre-line text-gray-800"><?= $h((string) ($plc['positive_text'] ?? '')) ?></div>
                                </div>
                                <div>
                                    <span class="pp-sub text-red-700">अशुभ फल (Negative):</span>
                                    <div class="whitespace-pre-line text-gray-800"><?= $h((string) ($plc['negative_text'] ?? '')) ?></div>
                                </div>
                            <?php else: ?>
                                <div class="text-gray-500 italic">Summary not available yet for this placement.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            </div><!-- /#planet-body -->
        <?php endif; ?>
    </div>
            </div><!-- /pred-view grah -->

            <div class="pred-view hidden" data-pred="bhav">
    <!-- House Prediction: rule-combined per-house Hindi reading. Shown in both views. -->
    <?php $hp = $view['housePred'] ?? null; ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm" id="house-pred-card">
        <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mb-3">
            <h2 class="font-semibold">House Prediction <span class="text-xs text-gray-400 font-normal">(भाव फल)</span></h2>
            <span class="text-xs text-gray-400">नियम-आधारित — राशि तत्व, मैत्री, दृष्टि व भावेश स्थिति के संयोजन से</span>
            <button type="button" class="phala-toggle ml-auto text-xs bg-gray-100 hover:bg-gray-200 border rounded px-2 py-1 font-semibold" data-target="house-body" aria-expanded="true">Collapse ▴</button>
        </div>
        <div id="house-body">
        <?php if ($hp === null || empty($hp['houses'])): ?>
            <?php if ($hp !== null && !empty($hp['error'])): ?>
            <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                Database not reachable — staff note: <?= $h((string) $hp['error']) ?>.
                Check <code>.env</code> DB settings and that <code>migrations/006_house_prediction.sql</code> is imported.
            </div>
            <?php endif; ?>
            <div class="text-gray-500 italic">House prediction not available yet (rule tables not imported).</div>
        <?php else: ?>
            <div id="house-grid">
                <div class="pred-picker">
                    <label class="pred-picker-label" for="house-select">Select House (भाव चुनें)</label>
                    <select id="house-select" class="pred-inline-select">
                        <option value="all">सभी भाव (All 12)</option>
                        <?php foreach ($hp['houses'] as $hh => $hd): ?>
                            <option value="<?= (int) $hh ?>"<?= $hh === 1 ? ' selected' : '' ?>><?= $ord2((int) $hh) ?> House — <?= $h((string) $hd['rashi_hi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="overflow-y-auto pr-1" style="max-height:480px" id="house-detail-pane">
                    <?php foreach ($hp['houses'] as $hh => $hd): ?>
                    <div class="house-detail<?= $hh === 1 ? '' : ' hidden' ?> mb-4" data-house="<?= (int) $hh ?>">
                        <div class="font-semibold text-gray-800 mb-1 flex items-center gap-2">
                            <span><?= $ord2((int) $hh) ?> House — <?= $h((string) $hd['rashi_hi']) ?> (<?= $h((string) $hd['rashi']) ?>)</span>
                            <?php if (!empty($hd['chip'])): ?><span class="gc-chip gc-<?= $h((string) $hd['chip']['tier']) ?>"><?= $h((string) $hd['chip']['word']) ?></span><?php endif; ?>
                        </div>
                        <?php // भावेश (house-lord) confidence + फल-काल from the StrengthMeter.
                            $hLord = $chart['houses'][$hh]['lord'] ?? null;
                            $smL = $hLord !== null ? ($view['strength']['planets'][$hLord] ?? null) : null;
                            if ($smL !== null):
                                $smLCls = $smL['tier'] === 'pos' ? 'gc-shubh' : ($smL['tier'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
                                $tmgL = $smL['timing'] ?? ['maha' => null, 'antar' => []]; ?>
                        <div class="text-xs" style="border:1px dashed #cbd5e1;border-radius:8px;padding:5px 9px;margin:2px 0 8px;background:#fafcff;color:#475569">
                            <b>भावेश <?= $h($ppHi[$hLord] ?? $hLord) ?></b>
                            <span class="gc-chip <?= $smLCls ?>"><?= $h((string) $smL['word']) ?></span>
                            <?php if ($tmgL['maha'] !== null || $tmgL['antar'] !== []): ?>
                                · <b>⏳ फल-काल:</b>
                                <?php if ($tmgL['maha'] !== null): ?>
                                    महादशा <?= $h($tmgL['maha']['from']) ?> – <?= $h($tmgL['maha']['to']) ?><?= $tmgL['maha']['status'] === 'running' ? ' <b style="color:#166534">(चालू)</b>' : ($tmgL['maha']['status'] === 'past' ? ' (बीत चुकी)' : '') ?>
                                <?php endif; ?>
                                <?php foreach ($tmgL['antar'] as $adL): ?>
                                    · <?= $h($ppHi[$adL['maha']] ?? $adL['maha']) ?>-महादशा में अंतर्दशा <?= $h($adL['from']) ?> – <?= $h($adL['to']) ?><?= !empty($adL['running']) ? ' <b style="color:#166534">(चालू)</b>' : '' ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="text-gray-600 mb-2 whitespace-pre-line" style="font-size:1.02rem"><?= $h((string) $hd['intro']) ?></div>
                        <?php if (!empty($hd['lines'])): ?>
                        <ul class="list-disc pl-5 space-y-1 text-gray-800" style="font-size:1.02rem; line-height:1.6">
                            <?php foreach ($hd['lines'] as $ln): ?>
                                <li class="whitespace-pre-line"><?= $h((string) $ln) ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php else: ?>
                            <div class="text-gray-500 italic">इस भाव के लिए कोई विशेष नियम लागू नहीं होता।</div>
                        <?php endif; ?>
                        <?php if (!empty($hd['av'])): ?>
                        <div class="av-mat mt-3">
                            <div class="av-mat-title"><?= $h((string) $hd['av']['title']) ?></div>
                            <ul class="av-mat-list">
                                <?php foreach ($hd['av']['lines'] as $al): ?>
                                <li class="av-<?= $h((string) ($al['tone'] ?? 'info')) ?>"><?= $h((string) $al['text']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($hd['bb'])): ?>
                        <div class="av-mat bb-mat mt-3">
                            <div class="av-mat-title"><?= $h((string) $hd['bb']['title']) ?></div>
                            <ul class="av-mat-list">
                                <?php foreach ($hd['bb']['lines'] as $bl): ?>
                                <li class="av-<?= $h((string) ($bl['tone'] ?? 'info')) ?>"><?= $h((string) $bl['text']) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- /#house-body -->
    </div>
            </div><!-- /pred-view bhav -->

            <div class="pred-view hidden" data-pred="karak">
    <!-- Karaka Prediction: each karaka paired with its main house's House Prediction. -->
    <?php
        $kp = $view['karakaPred'] ?? null;
        $hpHouses = $view['housePred']['houses'] ?? [];
        // Copy text (all karakas), Devanagari.
        $kCopyLines = [];
        if ($kp !== null) {
            foreach ($kp['karakas'] as $k) {
                $kCopyLines[] = '■ ' . $k['title'] . '  [' . $k['signifies'] . ']';
                // Karaka assessment block.
                $as = $k['assess'] ?? [];
                if (!empty($as['status'])) { $kCopyLines[] = 'ग्रह स्थिति: ' . $as['status']; }
                if (!empty($as['combust'])) { $kCopyLines[] = '• ' . $as['combust']; }
                foreach (($as['yuti'] ?? []) as $y) { $kCopyLines[] = '• ' . $y['text']; }
                if (!empty($as['bhavo_nashaya'])) { $kCopyLines[] = '• ' . $as['bhavo_nashaya']; }
                foreach ($k['paired_houses'] as $ph) {
                    if (!isset($hpHouses[$ph])) { continue; }
                    $kCopyLines[] = 'भाव फल — ' . $ord2((int) $ph) . ' House (' . $hpHouses[$ph]['rashi_hi'] . '):';
                    $kCopyLines[] = $hpHouses[$ph]['intro'];
                    foreach ($hpHouses[$ph]['lines'] as $ln) { $kCopyLines[] = '• ' . $ln; }
                }
                $kCopyLines[] = 'कारक विश्लेषण:';
                foreach ($k['karaka_lines'] as $l) {
                    foreach (($l['occupants'] ?? []) as $o) { $kCopyLines[] = '  ◦ ' . $o['text']; }
                    if (!empty($l['drishti'])) { $kCopyLines[] = '  ◦ ' . $l['drishti']; }
                    $kCopyLines[] = '• ' . $l['sentence'];
                    if (!empty($l['reason'])) { $kCopyLines[] = '   ' . $l['reason']; }
                }
                $kCopyLines[] = 'समग्र निष्कर्ष: ' . $k['combined'];
                $kCopyLines[] = '';
            }
        }
        $kCopyText = implode("\n", $kCopyLines);
    ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm" id="karaka-pred-card">
        <div id="karaka-body">
        <?php if ($kp === null || empty($kp['karakas'])): ?>
            <?php if ($kp !== null && !empty($kp['error'])): ?>
            <div class="mb-2 text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                Database not reachable — staff note: <?= $h((string) $kp['error']) ?>.
                Check <code>.env</code> DB settings and that <code>migrations/007_karaka_prediction.sql</code> is imported.
            </div>
            <?php endif; ?>
            <div class="text-gray-500 italic">Karaka prediction not available yet (rule tables not imported).</div>
        <?php else: ?>
            <div id="karaka-grid">
                <div class="pred-picker">
                    <label class="pred-picker-label" for="karaka-select">Select Karaka (कारक चुनें)</label>
                    <select id="karaka-select" class="pred-inline-select">
                        <?php foreach ($kp['karakas'] as $i => $k): ?>
                            <option value="<?= $h((string) $k['planet']) ?>"><?= $h((string) $k['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="overflow-y-auto pr-1" style="max-height:520px" id="karaka-detail-pane">
                    <?php foreach ($kp['karakas'] as $i => $k): ?>
                    <div class="karaka-detail<?= $i === 0 ? '' : ' hidden' ?>" data-karaka="<?= $h((string) $k['planet']) ?>">
                        <div class="font-bold text-gray-800" style="font-size:1.25rem"><?= $h((string) $k['title']) ?></div>
                        <div class="text-xs text-gray-500 mb-2">कारक: <?= $h((string) $k['signifies']) ?></div>

                        <!-- Karaka assessment block (migration 010): status/combust/yuti/bhavo-nashaya -->
                        <?php $as = $k['assess'] ?? null; if ($as !== null): ?>
                        <div class="gc-block">
                            <div class="gc-head">
                                <span class="pp-sub text-gray-700">कारक स्थिति</span>
                                <span class="gc-chip gc-<?= $h((string) ($as['verdict']['tier'] ?? 'mishrit')) ?>"><?= $h((string) ($as['verdict']['word'] ?? '')) ?></span>
                            </div>
                            <?php if (!empty($as['status'])): ?><div class="gc-status"><?= $h((string) $as['status']) ?></div><?php endif; ?>
                            <?php if (!empty($as['combust'])): ?><div class="gc-combust"><?= $h((string) $as['combust']) ?></div><?php endif; ?>
                            <?php if (!empty($as['yuti']) || !empty($as['bhavo_nashaya'])): ?>
                            <ul class="gc-lines">
                                <?php foreach (($as['yuti'] ?? []) as $y): ?>
                                    <li class="gc-line <?= !empty($y['good']) ? 'gc-good' : 'gc-bad' ?>"><?= $h((string) $y['text']) ?></li>
                                <?php endforeach; ?>
                                <?php if (!empty($as['bhavo_nashaya'])): ?><li class="gc-line gc-bad"><?= $h((string) $as['bhavo_nashaya']) ?></li><?php endif; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php foreach ($k['paired_houses'] as $ph): if (!isset($hpHouses[$ph])) { continue; } $hd = $hpHouses[$ph]; ?>
                            <div class="font-semibold text-indigo-700 mt-2" style="font-size:1.05rem">भाव फल — <?= $ord2((int) $ph) ?> House (<?= $h((string) $hd['rashi_hi']) ?>)</div>
                            <div class="text-gray-600 mb-1" style="font-size:1.02rem"><?= $h((string) $hd['intro']) ?></div>
                            <?php if (!empty($hd['lines'])): ?>
                            <ul class="list-disc pl-5 space-y-1 text-gray-800" style="font-size:1.02rem; line-height:1.6">
                                <?php foreach ($hd['lines'] as $ln): ?><li><?= $h((string) $ln) ?></li><?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <div class="font-semibold text-teal-700 mt-3" style="font-size:1.05rem">कारक विश्लेषण <span class="text-gray-400 font-normal text-xs">(भीतरी अनुभव — लग्न बनाम कारक)</span></div>
                        <?php if (!empty($k['karaka_lines'])): ?>
                        <div class="space-y-2 text-gray-800" style="font-size:1.02rem; line-height:1.6">
                            <?php foreach ($k['karaka_lines'] as $l): ?>
                            <div>
                                <?php if (!empty($l['occupants']) || !empty($l['drishti'])): ?>
                                <ul class="list-disc pl-5 text-gray-600" style="font-size:0.96rem">
                                    <?php foreach (($l['occupants'] ?? []) as $o): ?>
                                        <li class="<?= !empty($o['good']) ? 'text-green-700' : 'text-red-700' ?>"><?= $h((string) $o['text']) ?></li>
                                    <?php endforeach; ?>
                                    <?php if (!empty($l['drishti'])): ?><li class="text-gray-600"><?= $h((string) $l['drishti']) ?></li><?php endif; ?>
                                </ul>
                                <?php endif; ?>
                                <div class="font-medium"><?= $h((string) $l['sentence']) ?></div>
                                <?php if (!empty($l['reason'])): ?><div class="text-xs text-gray-500 pl-1"><?= $h((string) $l['reason']) ?></div><?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?><div class="text-gray-500 italic">इस कारक के लिए कोई व्याख्या उपलब्ध नहीं।</div><?php endif; ?>

                        <div class="mt-3 px-3 py-2 bg-amber-50 border-l-4 border-amber-300 text-gray-800 font-medium" style="font-size:1.02rem"><?= $h((string) $k['combined']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- /#karaka-body -->
    </div>
            </div><!-- /pred-view karak -->

            <!-- भावेश फल — the house-lord layer alone (same data as ग्रह फल part A). -->
            <div class="pred-view hidden" data-pred="bhavesh">
                <?php $pp2 = $view['planetPhala'] ?? null; ?>
                <?php if ($pp2 === null || empty($pp2['planets'])): ?>
                    <div class="text-gray-500 italic p-3">Chart not available.</div>
                <?php else: foreach ($pp2['planets'] as $row): if (!$row['lord_entries']) { continue; } $pl = $row['planet']; ?>
                    <div class="bh-card">
                        <div class="bh-title">
                            <span style="color:<?= $pcolor($pl) ?>"><?= $h($pl) ?></span>
                            <span class="text-gray-400 font-normal">(<?= $h($grahaHi[$pl] ?? '') ?>)</span>
                            — भावेश फल
                        </div>
                        <?php foreach ($row['lord_entries'] as $e): ?>
                            <div class="mb-1">
                                <div class="text-xs text-gray-500"><?= $ord2((int) $e['ruled_house']) ?> भाव का स्वामी, <?= $ord2((int) $e['placed_house']) ?> भाव में:</div>
                                <?php if ($e['text'] !== null && $e['text'] !== ''): ?>
                                    <div class="whitespace-pre-line text-gray-800"><?= $h((string) $e['text']) ?></div>
                                <?php else: ?>
                                    <div class="text-gray-500 italic">Summary not available yet for this combination.</div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; endif; ?>
            </div><!-- /pred-view bhavesh -->

            <!-- योग — unified Phaladeepika catalogue (single source; no doubling).
                 Active yogas + per-lagna Yogakaraka roles + फल-दशा. -->
            <div class="pred-view hidden" data-pred="yoga">
                <?php require __DIR__ . '/_phala_yoga.php';   // Yoga catalogue (incl. राजयोग + मंगल दोष cards) ?>
            </div><!-- /pred-view yoga -->

            <!-- शाप-दोष / सन्तान योग — Poorva Shaap (BPHS ch.86) catalogue + remedies. -->
            <div class="pred-view hidden" data-pred="dosha">
                <?php require __DIR__ . '/_dosha_panel.php'; ?>
            </div><!-- /pred-view dosha -->

            <div class="pred-view hidden" data-pred="search">
                <div class="bg-white rounded-lg shadow p-4 text-sm">
                    <h2 class="font-semibold mb-1">🔍 खोज परिणाम</h2>
                    <div id="pred-search-note" class="text-xs text-gray-500 mb-3"></div>
                    <div id="pred-search-results"></div>
                </div>
            </div><!-- /pred-view search -->

            <div class="pred-view hidden" data-pred="shaap">
                <?php require __DIR__ . '/_shaap.php'; ?>
            </div><!-- /pred-view shaap -->

            </div>
        </section>

        <!-- ============ New / Profile (full-width section) — the birth-details
             form, shown beside the menu like the Gochar Calculation card ======= -->
        <div id="sec-profile" class="l2-section l2-full hidden space-y-4 md:space-y-6">
            <?php require __DIR__ . '/_birth_form.php'; ?>
            <!-- आगामी गोचर summary — today's transit, below the birth-details form. -->
            <div class="bg-white rounded-lg shadow p-4">
                <?php $ug = $view['upcoming_gochar'] ?? null; require __DIR__ . '/_upcoming_gochar.php'; ?>
            </div>
        </div>

        <!-- ============ CUSTOM SCREEN (full-width, user-arranged panels) ======= -->
        <div id="sec-custom" class="l2-section l2-full hidden">
            <div class="cs-bar">
                <span class="cs-title">Custom Screen <span class="cs-title-hi">— अपनी स्क्रीन</span></span>
                <span class="cs-hint">Press <b>+</b> to add a chart / prediction (D1, Gochar, Varshaphal, Dasha, Shadbala…). <b>Drag the bottom-right corner</b> to resize any panel. Logged-in users' layout is remembered.</span>
                <span style="margin-left:auto"></span>
                <button type="button" id="cs-add" class="cs-toolbtn">+ Panel</button>
                <button type="button" id="cs-reset" class="cs-toolbtn">Reset</button>
            </div>
            <div id="custom-grid" class="cs-grid"></div>
        </div>

        <!-- Custom Screen picker modal -->
        <div id="cs-picker" class="cs-modal hidden">
            <div class="cs-modal-box">
                <div class="cs-modal-head"><span>Select a panel — पैनल चुनें</span>
                    <button type="button" class="cs-modal-x" aria-label="Close">✕</button></div>
                <div class="cs-modal-body" id="cs-opts-all">
                    <!-- Grouped panel options are injected here by the Custom Screen JS. -->
                </div>
            </div>
        </div>

        <!-- ============ ग्रह स्थिति (full-width section) ============ -->
        <div id="sec-grah" class="l2-section l2-full hidden space-y-4 md:space-y-6">
    <!-- Native (birth) summary: shown in both views, below the toggle buttons. -->
    <?php
        $pob = $in['place'] !== '' ? $in['place'] : ($in['latIn'] . ', ' . $in['lonIn']);
        $field = static function (string $label, string $value) use ($h): string {
            return '<div><div class="text-xs text-gray-500">' . $h($label) . '</div>'
                . '<div class="font-semibold text-gray-800">' . ($value !== '' ? $h($value) : '—') . '</div></div>';
        };
    ?>
    <div id="card-native" class="bg-white rounded-lg shadow p-4 text-sm">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-x-6 gap-y-3">
            <?= $field('Name', $in['name']) ?>
            <?= $field('Gender', $in['gender']) ?>
            <?= $field('Date of Birth', $in['date']) ?>
            <?= $field('Time of Birth', $in['time']) ?>
            <div>
                <div class="text-xs text-gray-500">Place of Birth</div>
                <div class="font-semibold text-gray-800" id="pob-value" data-place="<?= $h($in['place']) ?>"><?= $pob !== '' ? $h($pob) : '—' ?></div>
            </div>
        </div>
        <div class="border-t border-gray-100 my-3"></div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-3">
            <?= $field('Ascendant / Lagna Rashi', (string) ($chart['ascendant']['sign'] ?? '')) ?>
            <?= $field('Moon Sign Rashi', (string) ($chart['planets']['Moon']['sign'] ?? '')) ?>
            <?= $field('Sun Sign Rashi', (string) ($chart['planets']['Sun']['sign'] ?? '')) ?>
        </div>
    </div>


    <?php $num = static fn($v) => $h(number_format((float) $v, 0));
        // Ordinal (1->1st, 2->2nd, …) and a plain-text summary of every house,
        // used by the "Copy" button.
        $ord = static function (int $n): string {
            $v = $n % 100;
            $suf = ($v >= 11 && $v <= 13) ? 'th' : (['1' => 'st', '2' => 'nd', '3' => 'rd'][(string) ($n % 10)] ?? 'th');
            return $n . $suf;
        };
        // Drishti renderers (share the one computed list on each house):
        //  - HTML: short abbrs, colour-coded (chart ring + table use short names).
        //  - Full: full planet names for the Copy sentences.
        $drishtiHtml = static function (array $abbrs) use ($h, $pcolor): string {
            $parts = [];
            foreach ($abbrs as $ab) {
                $full = \AutoBusiness\Astro\Calc\Drishti::FULL[$ab] ?? $ab;
                $parts[] = '<span style="color:' . $pcolor($full) . '" class="font-semibold">' . $h($ab) . '</span>';
            }
            return implode(', ', $parts);
        };
        $drishtiFull = static function (array $abbrs): string {
            return implode(', ', array_map(
                static fn($ab) => \AutoBusiness\Astro\Calc\Drishti::FULL[$ab] ?? $ab,
                $abbrs
            ));
        };
        $copyLines = [];
        foreach (($chart['houses'] ?? []) as $H) {
            $line = 'In ' . $ord((int) $H['house']) . ' House, ';
            if (!empty($H['planets'])) {
                $ps = [];
                foreach ($H['planets'] as $pn) {
                    $lhStr = $lordHouses((string) $pn);
                    if ($lhStr !== '') {
                        $ords = array_map(static fn($x) => $ord((int) $x), explode(', ', $lhStr));
                        $ps[] = $pn . ' (lord of ' . implode(', ', $ords) . ' house)';
                    } else {
                        $ps[] = $pn;
                    }
                }
                $line .= 'Planet is ' . implode(', ', $ps) . ', ';
            }
            $line .= 'Rashi is ' . $H['sign'] . ' (' . (int) $H['rashi_num'] . '), ';
            $line .= 'House Lord is ' . $H['lord'] . ', ';
            $line .= 'Ashtakvarga score is ' . (int) $H['av'] . ', ';
            $line .= 'Bhav Bal is ' . number_format((float) ($H['bb_virupa'] ?? $H['bb'] * 60), 0);
            $dr = $H['drishti'] ?? [];
            if (!empty($dr)) {
                $line .= ', Drishti of ' . $drishtiFull($dr) . ' on ' . $ord((int) $H['house']) . ' house.';
            } else {
                $line .= '.';
            }
            $copyLines[] = $line;
        }
        $copyText = implode("\n", $copyLines);
        // Short details: only houses that hold planets — "In Nth House, Planet is A, B."
        $shortLines = [];
        foreach (($chart['houses'] ?? []) as $H) {
            if (!empty($H['planets'])) {
                $shortLines[] = 'In ' . $ord((int) $H['house']) . ' House, Planet is ' . implode(', ', $H['planets']) . '.';
            }
        }
        $shortText = implode("\n", $shortLines);
    ?>


    <!-- House details: planets, rashi, Ashtakavarga (AV), Bhava Bala total, lord -->
    <div id="card-housedet" class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-semibold">House Details</h2>
            <div class="flex items-center gap-2">
                <button id="hd-copy-short" type="button" class="text-xs bg-gray-100 hover:bg-gray-200 border rounded px-3 py-1 font-semibold">Short Details</button>
                <button id="hd-copy" type="button" class="text-xs bg-gray-100 hover:bg-gray-200 border rounded px-3 py-1 font-semibold">Long Details</button>
            </div>
        </div>
        <pre id="hd-copy-text" class="hidden"><?= $h($copyText) ?></pre>
        <pre id="hd-short-text" class="hidden"><?= $h($shortText) ?></pre>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b align-bottom">
                <th class="py-1 pr-3">House</th><th class="pr-3">Planet(s) in house</th><th class="pr-3">Drishti</th><th class="pr-3">Rashi</th>
                <th class="pr-3">AV</th>
                <th class="pr-3 text-right">Bhava&nbsp;Bala</th><th>Lord</th>
            </tr></thead>
            <tbody>
            <?php foreach (($chart['houses'] ?? []) as $hh => $H): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-semibold"><?= (int) $H['house'] ?></td>
                    <td class="pr-3">
                        <?php $occ = [];
                        foreach ($H['planets'] as $pn) {
                            $lh = $lordHouses((string) $pn);
                            $occ[] = '<span style="color:' . $pcolor($pn) . '" class="font-semibold">' . $h($pn) . '</span>'
                                . ($lh !== '' ? ' <span class="text-xs text-gray-400">(lord of ' . $h($lh) . ')</span>' : '');
                        }
                        echo implode(', ', $occ); ?>
                    </td>
                    <td class="pr-3"><?= $drishtiHtml($H['drishti'] ?? []) ?: '<span class="text-gray-300">—</span>' ?></td>
                    <td class="pr-3"><?= (int) $H['rashi_num'] ?> <?= $h($H['sign']) ?></td>
                    <td class="pr-3 font-semibold" style="color:#1d4ed8"><?= (int) $H['av'] ?></td>
                    <td class="pr-3 text-right font-semibold" style="color:#15803d"><?= $num($H['bb_virupa'] ?? $H['bb'] * 60) ?></td>
                    <td><span style="color:<?= $pcolor($H['lord']) ?>" class="font-semibold"><?= $h($H['lord']) ?></span>
                        <?php $llh = $lordHouses((string) $H['lord']); ?><?= $llh !== '' ? '<span class="text-xs text-gray-400">(' . $h($llh) . ')</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>


    <!-- D1 -->
    <div id="card-d1pos" class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">D1 (Rasi) — Planetary Positions</h2>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">Planet</th><th class="pr-3">Position</th><th class="pr-3">Placement</th><th class="pr-3">Lord</th>
                <th class="pr-3">Nakshatra (pada)</th><th class="pr-3">Navamsa</th><th class="pr-3">अस्त %</th><th>Retro</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chart['planets'] as $name => $p):
                // Combustion % from the shared service (single source of truth).
                $cmb = \AutoBusiness\Astro\Calc\PlanetCondition::combustion((string) $name, $chart['planets']);
            ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-semibold" style="color: <?= $pcolor($name) ?>"><?= $h($name) ?></td>
                    <td class="pr-3"><?= $h($p['formatted']) ?></td>
                    <td class="pr-3"><?= (int) $p['house'] ?></td>
                    <td class="pr-3"><?= $h($lordHouses((string) $name)) ?></td>
                    <td class="pr-3"><?= $h($p['nakshatra']['name']) ?> (<?= (int) $p['nakshatra']['pada'] ?>)</td>
                    <td class="pr-3"><?= $h($p['navamsa_sign']) ?></td>
                    <td class="pr-3"><?= $cmb !== null ? '<span style="color:#b45309;font-weight:600">' . (int) $cmb['pct'] . '%</span>' : '<span class="text-gray-300">—</span>' ?></td>
                    <td><?= $p['retro'] ? '<sup style="color:#b91c1c;font-size:0.9em">&#174;</sup>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Gochar Details — full transit table for a chosen date/time/place. The
         date/time inputs carry +/- steppers (day·week·month·year and
         minute·10min·hour·12hour); the table + Copy button update with them.
         Built lazily on first visit to Planet Positions. -->
    <div id="card-gochardetails" class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <div class="flex flex-wrap items-center gap-3 mb-3 pb-2 border-b">
            <h2 class="font-semibold text-gray-800">Gochar Details
                <span class="text-xs text-gray-400 font-normal">(गोचर विवरण — इस तिथि/समय पर सभी ग्रहों की गोचर स्थिति)</span></h2>
            <button type="button" id="gd-copy" class="ml-auto text-sm font-semibold border rounded px-3 py-1.5 bg-slate-50 hover:bg-slate-100" aria-label="Copy">📋 Copy</button>
        </div>
        <div id="gd-inputs"></div>
        <div id="gd-hidden" class="hidden"></div>
        <div id="gd-status" class="text-xs text-gray-500 mt-2"></div>
        <div id="gd-table" class="mt-3 overflow-x-auto"></div>
        <pre id="gd-copytext" class="hidden"></pre>
    </div>

        </div>

        <!-- ============ वर्ग कुंडली (full-width section) ============ -->
        <div id="sec-varga" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <!-- Remaining divisional charts — reflow: 1 / 2 / 3 per row by width -->
        <div>
            <h2 class="font-semibold mb-2 text-gray-700">Divisional Charts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <?php foreach (($vargas ?? []) as $vkey => $vinfo): ?>
                    <div class="bg-white rounded-lg shadow p-2" data-varga="<?= $h($vkey) ?>"></div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">North-Indian style: house 1 top-centre (As = Ascendant); black number at each inner corner = Rashi (sign) number; planet abbreviations colour-coded (Dasha palette), &#174; = retrograde.</p>
        </div>
        </div><!-- /sec-varga -->

        <!-- ============ गोचर (full-width section) ============ -->
        <div id="sec-gochar" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <!-- Gochar calculation details (defaults to now + IP location) -->
        <div id="card-gocharcalc" class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Gochar Calculation Details</h2>
            <div id="gochar-inputs"></div>
        </div>


        <!-- Two rows of two panes each. EVERY pane has a dropdown so the viewer
             chooses what fills that space: the two left panes are charts, the two
             right panes are predictions. Panes are equal height & aligned. -->
        <!-- ROW 1 -->
        <div id="card-gocharpair" class="gpair grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
            <div class="gpane" data-slot="chart" data-slot-id="c1">
                <div class="gp-head">
                    <select class="gp-sel gp-chart-sel" data-slot-id="c1" aria-label="चार्ट चुनें / choose chart"></select>
                    <span class="gp-sub" data-sub="c1"></span>
                </div>
                <div class="gp-body gp-body-chart"><div class="gp-chart-host" data-host="c1"></div></div>
            </div>
            <div class="gpane" data-slot="pred" data-slot-id="p1">
                <div class="gp-head">
                    <select class="gp-sel gp-pred-sel" data-slot-id="p1" aria-label="फल चुनें / choose prediction"></select>
                </div>
                <div class="gp-body gp-body-pred" data-predbody="p1"></div>
            </div>
        </div>

        <!-- ROW 2 -->
        <div id="card-gochardet" class="gpair grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
            <div class="gpane" data-slot="chart" data-slot-id="c2">
                <div class="gp-head">
                    <select class="gp-sel gp-chart-sel" data-slot-id="c2" aria-label="चार्ट चुनें / choose chart"></select>
                    <span class="gp-sub" data-sub="c2"></span>
                </div>
                <div class="gp-body gp-body-chart"><div class="gp-chart-host" data-host="c2"></div></div>
            </div>
            <div class="gpane" data-slot="pred" data-slot-id="p2">
                <div class="gp-head">
                    <select class="gp-sel gp-pred-sel" data-slot-id="p2" aria-label="फल चुनें / choose prediction"></select>
                </div>
                <div class="gp-body gp-body-pred" data-predbody="p2"></div>
            </div>
        </div>

        <!-- Prediction content blocks — JS moves these singleton nodes into the
             prediction slots above (swapped when both slots pick the same one). -->
        <div id="gp-pred-src" class="hidden">
            <div id="gpblock-phal" data-pred="phal">
                <div id="gochar-phal">
                    <div class="gochar-pred-soon">
                        <div class="gps-icon">🔮</div>
                        <div class="gps-title">गोचर फल की गणना हो रही है…</div>
                        <div class="gps-sub">तिथि/स्थान चुनते ही यहाँ चन्द्र-लग्न भाव-फल और जन्म-ग्रहों पर गोचर दिखेगा।</div>
                    </div>
                </div>
            </div>
            <div id="gpblock-upcoming" data-pred="upcoming">
                <?php $ug = $view['upcoming_gochar'] ?? null; require __DIR__ . '/_upcoming_gochar.php'; ?>
            </div>
        </div>

        <!-- gochar.js still fetches transits + injects #gochar-phal here; kept
             hidden since the visible transit chart is drawn into a chart slot. -->
        <div id="gochar-output" class="hidden"></div>
            <!-- आगामी 12 महीने की समय-रेखा (गोचर + दशा + संयोग merged) -->
            <?php require __DIR__ . '/_year_timeline.php'; ?>
        </div><!-- /sec-gochar -->

        <!-- ============ मुहूर्त (Mahurat) — dedicated transit+muhurat page ======= -->
        <div id="sec-muhurat" class="l2-section l2-full hidden space-y-4 md:space-y-6">
            <!-- Gochar calculation details (change the transit date/time/place;
                 the muhurat prediction below re-computes with it). -->
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold mb-3 text-gray-700">Gochar Calculation Details
                    <span class="text-xs text-gray-400 font-normal">(मुहूर्त हेतु तिथि / समय / स्थान बदलें)</span></h2>
                <div id="mah-inputs"></div>
            </div>

            <!-- ROW 1: transit (gochar) chart + मुहूर्त prediction -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                    <div id="mah-transit" class="w-full"></div>
                </div>
                <div class="bg-white rounded-lg shadow p-4 flex flex-col">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700">
                        <span class="font-semibold text-gray-800">मुहूर्त फल <span class="text-xs text-gray-400 font-normal">(राहु काल · दिशा शूल · तिथि · वारफल)</span></span>
                    </div>
                    <div id="mah-phal" class="flex-1">
                        <div class="gochar-pred-soon">
                            <div class="gps-icon">🕒</div>
                            <div class="gps-title">मुहूर्त फल की गणना हो रही है…</div>
                            <div class="gps-sub">ऊपर तिथि/समय/स्थान चुनते ही राहु काल, दिशा शूल व तिथि-फल यहाँ दिखेगा।</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ROW 2: natal Rasi (D1) chart + Varsha kundali chart -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
                <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700">
                        <span class="font-semibold text-gray-800">Rasi (D1) <span class="text-xs text-gray-400 font-normal">— जन्म कुंडली</span></span>
                    </div>
                    <div id="mah-d1" class="w-full"></div>
                </div>
                <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700">
                        <span class="font-semibold text-gray-800">Varsha Kundali <span class="text-xs text-gray-400 font-normal">(<?= (int) $in['forYear'] ?>) — वर्ष कुंडली</span></span>
                    </div>
                    <div id="mah-varsha" class="w-full"></div>
                </div>
            </div>
        </div><!-- /sec-muhurat -->

        <!-- ============ वर्ष कुंडली (full-width section) ============ -->
        <div id="sec-varsha" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <!-- Varshaphal year selection + summary details -->
        <div id="card-vpbox" class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Varshaphal
                <button type="button" id="vp-print" class="pred-expand" title="वर्ष रिपोर्ट Print करें" style="width:auto;padding:0 8px;font-size:.8rem;float:right">🖨 वर्ष रिपोर्ट</button></h2>
            <!-- Year selector on the LEFT, the colourful year summary fills the
                 empty space on the RIGHT (moved up here from below the box). -->
            <div class="flex flex-col lg:flex-row lg:items-center gap-4 lg:gap-10">
                <div id="vp-box"></div>
                <div id="vp-summary" class="flex-1"></div>
            </div>
        </div>

        <!-- वर्ष का सार — one-look year summary (varshesh · muntha · mudda ·
             tajik · saham + verdict); swapped by the year-change JSON too. -->
        <div id="vp-saar">
            <?php require __DIR__ . '/_varsha_saar.php'; ?>
        </div>

        <!-- ROW 1: Varsha (Annual) chart on the LEFT + Varshaphal prediction
             panel on the RIGHT (rules coming later). -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div id="vp-chart-cell"></div>
            <div class="bg-white rounded-lg shadow p-4 flex flex-col" id="varsha-pred-card">
                <div class="l2-picker" style="margin-bottom:10px">
                    <span class="pick-tag">Varshaphal Prediction ▾</span>
                    <select id="vp-pred-type" class="l2-select">
                        <option value="general" selected>General Overview</option>
                        <option value="muntha">Muntha (मुंथा)</option>
                        <option value="varshesh">Varshesh (वर्षेश)</option>
                        <option value="dasha">Mudda Dasha (दशा फल)</option>
                        <option value="tajik">Tajik Yog (ताजिक योग)</option>
                        <option value="saham">Saham (सहम)</option>
                        <option value="bhava">House (भाव वर्ष-फल)</option>
                    </select>
                </div>
                <div id="vp-pred-scroll" class="vp-pred-scroll">
                <!-- सामान्य (General) — default varshaphal summary of all layers. -->
                <div id="vp-pred-general">
                <?php require __DIR__ . '/_vp_general.php'; ?>
                </div><!-- /vp-pred-general -->
                <div id="vp-pred-saham" class="hidden">
                <?php require __DIR__ . '/_saham_pane.php'; ?>
                </div><!-- /vp-pred-saham -->
                <!-- ताजिक योग pane (16 Tajik yogas + sphuta drishti, migration 016) -->
                <div id="vp-pred-tajik" class="hidden">
                <?php require __DIR__ . '/_tajik_yoga.php'; ?>
                </div><!-- /vp-pred-tajik -->
                <!-- वर्षेश फल pane (year-lord selection + phal, migration 018) -->
                <div id="vp-pred-varshesh" class="hidden">
                <?php require __DIR__ . '/_varshesh_phal.php'; ?>
                </div><!-- /vp-pred-varshesh -->
                <!-- मुंथा फल pane (Muntha bhava/graha/Rahu + specials, migration 019) -->
                <div id="vp-pred-muntha" class="hidden">
                <?php require __DIR__ . '/_muntha_phal.php'; ?>
                </div><!-- /vp-pred-muntha -->
                <!-- भाव-फल pane (Tajik-Neelakanthi 262 bhava rules, migration 023) -->
                <div id="vp-pred-bhava" class="hidden">
                <?php require __DIR__ . '/_tajik_bhava.php'; ?>
                </div><!-- /vp-pred-bhava -->
                <!-- दशा-फल pane (Patyayini dasha + antardasha selector, migration 024) -->
                <div id="vp-pred-dasha" class="hidden">
                <?php require __DIR__ . '/_dasha_phal.php'; ?>
                </div><!-- /vp-pred-dasha -->
                </div><!-- /vp-pred-scroll -->
            </div>
        </div>

        <!-- ROW 2: Mudda Dasha on the LEFT + annual positions detail on the RIGHT. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div id="vp-mudda-cell"></div>
            <div id="card-varshadet" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
            <?php $forYear = (int) $in['forYear']; require __DIR__ . '/_varsha_positions.php'; ?>
            </div>
        </div>

        <!-- ROW 3: PL-style Panchavargeeya Bala table + Year Lord (Panchadhikari) card. -->
        <div id="vp-row3" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
        <?php require __DIR__ . '/_varsha_bala_cards.php'; ?>
        </div>
        </div>

        <!-- ============ आज का Consult (full-width section) ============ -->
        <div id="sec-today" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <?php require __DIR__ . '/_today_dashboard.php'; ?>
        </div><!-- /sec-today -->

        <!-- ============ लाल किताब (full-width section) ============ -->
        <div id="sec-lalkitab" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <?php $lk = $view['lalkitab'] ?? ['ok' => false]; require __DIR__ . '/_lalkitab.php'; ?>
        </div><!-- /sec-lalkitab -->

        <!-- ============ दशा (full-width section) ============ -->
        <div id="sec-dasha" class="l2-section l2-full hidden space-y-4 md:space-y-6">
    <!-- Current dasha chain (today) — shown first -->
    <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null):
        $tzv = (float) ($meta['tz'] ?? 0);
        // $depth indents each level (↳); $sep is the date-range separator.
        $cdRow = function (string $label, ?array $p, int $depth, string $sep) use ($pcolor, $h, $tzv): string {
            if (empty($p)) { return ''; }
            $dates = \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['start_jd'], $tzv)
                . ' ' . $sep . ' ' . \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['end_jd'], $tzv);
            $arrow = $depth > 0 ? '<span class="text-gray-400">↳</span> ' : '';
            return '<div style="padding-left:' . ($depth * 1.6) . 'rem">' . $arrow
                . '<span class="text-gray-600 font-semibold">' . $label . ':</span> '
                . '<b style="color:' . $pcolor($p['lord']) . '">' . $h($p['lord']) . '</b> '
                . '<span class="text-gray-500">(' . $dates . ')</span></div>';
        };
    ?>
    <div id="card-curdasha" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-2">Current Dasha — today (<?= $h(date('d-m-Y')) ?>)</h2>
        <div class="space-y-1 leading-snug">
            <?= $cdRow('MahaDasha', $dashaNow['maha'], 0, '–') ?>
            <?= $cdRow('AntarDasha', $dashaNow['antar'], 1, '–') ?>
            <?= $cdRow('Pratyantar', $dashaNow['pratyantar'], 2, '–') ?>
        </div>
        <div class="border-t border-gray-200 my-2"></div>
        <div class="leading-snug">
            <?= $cdRow('Next Antardasha', $dashaNow['next_antar'], 1, '→') ?>
        </div>
    </div>
    <?php endif; ?>


    <!-- Vimshottari — same expandable, colour-coded tree as the Chart view -->
    <div id="card-vimtree" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-2">Vimshottari Dasha <span class="text-xs text-gray-400 font-normal">(+ drills 5 levels)</span></h2>
        <div class="mb-2">Birth balance: <b style="color: <?= $pcolor($chart['dasha']['balance']['lord']) ?>"><?= $h($chart['dasha']['balance']['lord']) ?></b>
            for <?= sprintf('%.2f', $chart['dasha']['balance']['years']) ?> years.
            Running (at birth): <?= $h($chart['dasha']['running']['maha'] ?? '-') ?> /
            <?= $h($chart['dasha']['running']['antar'] ?? '-') ?></div>
        <div id="vim-dasha-detail"></div>
    </div>


        </div>

        <!-- ============ बल (full-width section) ============ -->
        <div id="sec-bal" class="l2-section l2-full hidden space-y-4 md:space-y-6">
            <!-- बल tabs: Shadbala / Bhava Bala / Ashtakavarga / Vimshopaka -->
            <div class="bal-tabbar" aria-label="बल">
                <button type="button" data-bal="shad" class="active">Shadbala</button>
                <button type="button" data-bal="bb">Bhava Bala</button>
                <button type="button" data-bal="av">Ashtakavarga (AV)</button>
                <button type="button" data-bal="vim">Vimshopaka Bala</button>
            </div>

            <div class="bal-tab space-y-4 md:space-y-6" data-bal="shad">
            <div class="bg-white rounded-lg shadow p-4 flex flex-col justify-center">
                <h2 class="font-semibold mb-1">Shadbala</h2>
                <p class="text-xs text-gray-500 mb-3">Strength ÷ minimum required (ratio). Dashed line = 1.00. Red &lt; 0.95, orange 0.95–1.01, green &gt; 1.01.</p>
                <div class="relative" style="height:330px">
                    <!-- 1.00 threshold line (1.60 fills the 270px track => 1.00 sits at 62.5%) -->
                    <div class="absolute left-0 right-0" style="bottom:calc(30px + 270px * 0.625); border-top:1px dashed #9ca3af"></div>
                    <div class="flex items-end justify-between gap-1 absolute inset-x-0 bottom-0" style="height:330px">
                        <?php foreach (($chart['shadbala'] ?? []) as $name => $b):
                            $ratio = (float) $b['ratio'];
                            $hpx = max(3.0, min(270.0, $ratio / 1.6 * 270.0));
                            $abbr = substr((string) $name, 0, 2);
                            $band = $shadColor($ratio);
                        ?>
                        <div class="flex flex-col items-center justify-end" style="height:330px; flex:1">
                            <div class="text-[16px] font-bold leading-tight" style="color:<?= $band ?>"><?= sprintf('%.2f', $ratio) ?></div>
                            <div class="w-full rounded-t" style="height:<?= sprintf('%.1f', $hpx) ?>px; background:<?= $band ?>"></div>
                            <div class="text-[16px] mt-1 font-bold leading-tight" style="color:<?= $pcolor($name) ?>"><?= $h($abbr) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
    <!-- Shadbala -->
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-1">Shadbala (Six-fold Strength)</h2>
        <p class="text-xs text-gray-500 mb-2">Sthana, Dig, Naisargika, Ayana and Ishta/Kashta match Parashara's Light. Kaala, Chesta and Drig follow the PL method — the Moon always takes the benefic Paksha share; Ayana/Paksha are counted once in Kaala and once in Chesta (not doubled); Ishta = (Uchcha + Chesta)/2, Kashta = 60 − Ishta; Drig treats the Moon and Mercury as benefics. The five star planets' Chesta uses the Seeghra-Kendra (Seeghrochcha − Madhyama); PL's internal anomaly model leaves a small residual there.</p>
        <table class="w-full">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-2">Planet</th><th class="pr-2">Sthana</th><th class="pr-2">Dig</th>
                <th class="pr-2">Kaala</th><th class="pr-2">Chesta</th><th class="pr-2">Naisarg</th><th class="pr-2">Drig</th>
                <th class="pr-2">Total</th><th class="pr-2 font-semibold">Rupas</th><th class="pr-2">Ratio</th><th class="pr-2">Ishta</th><th>Kashta</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chart['shadbala'] as $name => $b): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-2 font-semibold" style="color: <?= $pcolor($name) ?>"><?= $h($name) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['sthana']['total'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['dig'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['kaala'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['chesta'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['naisargika'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['drig'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['total_virupa'], 1)) ?></td>
                    <td class="pr-2 font-semibold"><?= $h(number_format((float) $b['total_rupa'], 2)) ?></td>
                    <td class="pr-2 font-semibold" style="color: <?= $shadColor((float) $b['ratio']) ?>"><?= $h(number_format((float) $b['ratio'], 2)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['ishta'], 1)) ?></td>
                    <td><?= $h(number_format((float) $b['kashta'], 1)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">Total = sum of all six balas (virupas); Rupas = Total ÷ 60; Ratio = Total ÷ minimum required. Ishta = √(Uchcha × Chesta), Kashta = √((60−Uchcha) × (60−Chesta)).</p>
    </div>


            </div><!-- /bal-tab shad -->

            <div class="bal-tab hidden" data-bal="bb">
    <!-- Bhava Bala — component breakdown per house -->
    <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">Bhava Bala</h2>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b align-bottom">
                <th class="py-1 pr-3">House</th><th class="pr-3">Rashi</th><th class="pr-3">Lord</th>
                <th class="pr-2 text-right">From&nbsp;Lord</th><th class="pr-2 text-right">Dig&nbsp;Bala</th>
                <th class="pr-2 text-right">Drishti</th><th class="pr-2 text-right">Planets&nbsp;in</th>
                <th class="pr-2 text-right">Day-Night</th>
                <th class="pr-3 text-right">Bhava&nbsp;Bala</th>
            </tr></thead>
            <tbody>
            <?php foreach (($chart['houses'] ?? []) as $hh => $H): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-semibold"><?= (int) $H['house'] ?></td>
                    <td class="pr-3"><?= (int) $H['rashi_num'] ?> <?= $h($H['sign']) ?></td>
                    <td class="pr-3"><span style="color:<?= $pcolor($H['lord']) ?>" class="font-semibold"><?= $h($H['lord']) ?></span></td>
                    <td class="pr-2 text-right"><?= $num($H['bb_adhipati'] ?? 0) ?></td>
                    <td class="pr-2 text-right"><?= $num($H['bb_digbala'] ?? 0) ?></td>
                    <td class="pr-2 text-right"><?= $num($H['bb_drishti'] ?? 0) ?></td>
                    <td class="pr-2 text-right"><?= $num($H['bb_planets_in'] ?? 0) ?></td>
                    <td class="pr-2 text-right"><?= $num($H['bb_day_night'] ?? 0) ?></td>
                    <td class="pr-3 text-right font-semibold" style="color:#15803d"><?= $num($H['bb_virupa'] ?? $H['bb'] * 60) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">Bhava Bala (virupas) = From&nbsp;Lord (bhava lord's Shadbala) + Dig&nbsp;Bala + Drishti + Planets&nbsp;in (benefic/malefic occupants) + Day-Night (Bhava Kaala). Drishti follows Parashara's Light (Sphuta-drishti curve at the whole-sign cusp, each planet weighted by benefic/malefic and Ishta/Kashta; nodes excluded). From&nbsp;Lord, Planets&nbsp;in, Day-Night and Drishti track Parashara's Light; Dig&nbsp;Bala uses the standard BPHS directional figure.</p>
    </div>


            </div><!-- /bal-tab bb -->

            <!-- अष्टकवर्ग: per-house Sarva-Ashtakavarga scores (same values as the
                 chart ring / House Details column — presentation only). -->
            <div class="bal-tab hidden" data-bal="av">
                <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
                    <h2 class="font-semibold mb-2">Ashtakavarga <span class="text-xs text-gray-400 font-normal">(अष्टकवर्ग — सर्व)</span></h2>
                    <table class="w-full text-sm">
                        <thead><tr class="text-left border-b">
                            <th class="py-1 pr-3">House</th>
                            <?php for ($avh = 1; $avh <= 12; $avh++): ?><th class="pr-2 text-center"><?= $avh ?></th><?php endfor; ?>
                        </tr></thead>
                        <tbody>
                            <tr>
                                <td class="py-1 pr-3 font-semibold">AV</td>
                                <?php for ($avh = 1; $avh <= 12; $avh++): ?>
                                    <td class="pr-2 text-center font-semibold" style="color:#1d4ed8"><?= (int) ($chart['houses'][$avh]['av'] ?? 0) ?></td>
                                <?php endfor; ?>
                            </tr>
                            <tr class="border-t border-gray-100">
                                <td class="py-1 pr-3 font-semibold">Rashi</td>
                                <?php for ($avh = 1; $avh <= 12; $avh++): ?>
                                    <td class="pr-2 text-center text-gray-600"><?= (int) ($chart['houses'][$avh]['rashi_num'] ?? 0) ?></td>
                                <?php endfor; ?>
                            </tr>
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-400 mt-2">Sarva-Ashtakavarga per house — the same AV values shown on the chart ring (blue) and in House Details.</p>
                </div>
            </div><!-- /bal-tab av -->

            <div class="bal-tab hidden" data-bal="vim">
    <!-- Vimshopaka Bala — divisional strength (out of 20) in four varga groups -->
    <?php if (!empty($chart['vimshopaka'])): $vb = $chart['vimshopaka']; ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-1">Vimshopaka Bala</h2>
        <p class="text-xs text-gray-500 mb-2">Strength across the divisional charts, scored out of 20, from each planet's dignity in every varga (Own/Moolatrikona/Exaltation = full, down to Debilitation). Groups: Shadvarga (6), Saptavarga (7), Dashavarga (10), Shodashavarga (16).</p>
        <table class="w-full">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">Group</th>
                <?php foreach ($vb['planets'] as $pn): ?>
                    <th class="pr-3 text-center font-semibold" style="color: <?= $pcolor($pn) ?>"><?= $h($pn) ?></th>
                <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php foreach ($vb['groups'] as $grp): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-semibold text-gray-700"><?= $h($grp) ?></td>
                    <?php foreach ($vb['planets'] as $pn): ?>
                        <td class="pr-3 text-center"><?= (int) ($vb['scores'][$grp][$pn] ?? 0) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">Higher = stronger (max 20). Uses the same divisional placements as the charts; dignity by natural (Naisargika) friendship with each divisional sign's lord.</p>
    </div>
    <?php endif; ?>
            </div><!-- /bal-tab vim -->

        </div>

    </div><!-- /sec-home grid -->
    <?php endif; ?>

    <p class="text-xs text-gray-400">Auto Business — Calculation Engine test page. For arc-second accuracy set SWETEST_PATH (Swiss Ephemeris).</p>
</main>

<?php if ($chart !== null): ?>
<?php // Hidden D1 print report (opened by #d1-print via ABPrintDoc). ?>
<?php require __DIR__ . '/_d1_report.php'; ?>

<script>
  window.AB_VARGAS = <?= json_encode($vargas ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
  window.AB_DASHA  = <?= json_encode($chart['dasha']['mahadashas'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  window.AB_MUDDA  = <?= json_encode($vp['mudda_dasha'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
  window.AB_BIRTH  = <?= json_encode($birthJs ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
  window.AB_TZ     = <?= json_encode((float) ($meta['tz'] ?? 0)) ?>;
  window.AB_YEAR   = <?= json_encode((int) $in['forYear']) ?>;
  window.AB_HOUSES = <?= json_encode($chart['houses'] ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
  window.AB_GOCHAR = <?= json_encode($gochar ?? new stdClass(), JSON_UNESCAPED_UNICODE) ?>;
  window.AB_VARSHAN = <?= json_encode($view['varshaNorth'] ?? null, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php
    $asset = static fn(string $p): string => \AutoBusiness\Core\Asset::url($p);
    // Current logged-in user for the Save/Open feature. Null until the login
    // system (future) populates $_SESSION['user_id']; while null, Save/Open show
    // the "please register" message instead of storing charts.
    $abUser = !empty($_SESSION['user_id'])
        ? ['id' => (string) $_SESSION['user_id'], 'name' => (string) ($_SESSION['user_name'] ?? '')]
        : null;
?>
<script>window.AB_USER = <?= json_encode($abUser, JSON_UNESCAPED_UNICODE) ?>;</script>
<!-- Dasha Details popup: the full Vimshottari dasha tree (expand + scroll),
     with a close ✕ that stays visible; works on phone / tablet / laptop. -->
<div id="dasha-modal" class="dm-overlay hidden" role="dialog" aria-modal="true" aria-label="Dasha details">
    <div class="dm-box">
        <div class="dm-head">
            <span class="dm-title">दशा विवरण <span style="font-weight:400;color:#9ca3af;font-size:.8rem">/ Dasha Details</span></span>
            <button type="button" class="dm-close" id="dasha-modal-close" aria-label="Close / बंद करें" title="Close">✕</button>
        </div>
        <div class="dm-body" id="dasha-modal-body">
            <div id="dasha-modal-tree" class="dm-tree"></div>
        </div>
    </div>
</div>

<script src="<?= $h($asset('/assets/js/datefmt.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/northchart.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/dasha.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/citysearch.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/gochar.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/varshaphal.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/saved_charts.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/translate.js')) ?>"></script>
<script>
(function () {
  // Auto-correct the date/time fields to canonical form when the user leaves
  // the box (blur). Accepts dash/slash/dot/space separators for the date and
  // colon/space for the time; e.g. "1 12 1980" -> "01-12-1980", "12 31" -> "12:31".
  var pad2 = function (n) { return (n < 10 ? '0' : '') + n; };

  var normDate = function (raw) {
    var p = String(raw).trim().split(/[-\/.\s]+/).filter(Boolean);
    if (p.length !== 3 || p.some(function (x) { return !/^\d+$/.test(x); })) { return raw; }
    var a = parseInt(p[0], 10), b = parseInt(p[1], 10), c = parseInt(p[2], 10);
    // 4-digit (>31) first field means YYYY-MM-DD; otherwise DD-MM-YYYY.
    var d, m, y;
    if (a > 31) { y = a; m = b; d = c; } else { d = a; m = b; y = c; }
    if (d < 1 || d > 31 || m < 1 || m > 12) { return raw; }
    return pad2(d) + '-' + pad2(m) + '-' + y;
  };

  var normTime = function (raw) {
    var p = String(raw).trim().split(/[:\s.]+/).filter(Boolean);
    if (!p.length || p.some(function (x) { return !/^\d+$/.test(x); })) { return raw; }
    var h = parseInt(p[0], 10), mi = parseInt(p[1] || '0', 10);
    if (h > 23 || mi > 59) { return raw; }
    return pad2(h) + ':' + pad2(mi);
  };

  // Date/time: auto-fix loosely-typed values (incl. month names like "jan") and
  // show a clear error + block Calculate when a value can't be understood.
  var bForm = document.getElementById('birth-form');
  var bDate = bForm && bForm.querySelector('[name="date"]');
  var bTime = bForm && bForm.querySelector('[name="time"]');
  if (window.ABDate && bDate && bTime) {
    window.ABDate.attach(bDate, 'date');
    window.ABDate.attach(bTime, 'time');
    window.ABDate.guardForm(bForm, [{ el: bDate, kind: 'date' }, { el: bTime, kind: 'time' }]);
  } else {
    // Fallback (ABDate not loaded): keep the old blur-only numeric formatter.
    var bindFmt = function (el, fn) { if (el) { el.addEventListener('blur', function () { if (el.value.trim()) { el.value = fn(el.value); } }); } };
    bindFmt(bDate, normDate); bindFmt(bTime, normTime);
  }

  // Dasha Prediction: reload the Positive/Negative/Remedy summary when either
  // dropdown changes. Defaults are server-rendered to the running Maha/Antar.
  (function () {
    var card = document.getElementById('dasha-phala-card');
    if (!card) { return; }
    var mSel = document.getElementById('phala-maha');
    var aSel = document.getElementById('phala-antar');
    var sec = document.getElementById('phala-sections');
    var empty = document.getElementById('phala-empty');
    var pos = document.getElementById('phala-pos');
    var neg = document.getElementById('phala-neg');
    var rem = document.getElementById('phala-rem');
    var lang = card.getAttribute('data-lang') || 'hi';
    var load = function () {
      var q = '?maha=' + encodeURIComponent(mSel.value) +
              '&antar=' + encodeURIComponent(aSel.value) +
              '&lang=' + encodeURIComponent(lang);
      fetch('/calc/dashaPhala' + q, { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.available) {
            pos.textContent = d.positive || '';
            neg.textContent = d.negative || '';
            rem.textContent = d.remedy || '';
            sec.classList.remove('hidden');
            empty.classList.add('hidden');
          } else {
            sec.classList.add('hidden');
            empty.classList.remove('hidden');
          }
        })
        .catch(function () { sec.classList.add('hidden'); empty.classList.remove('hidden'); });
    };

    // Calculated engine: rebuild the chart-specific cards for the selected pair.
    var cardsBox = document.getElementById('dasha-cards');
    var loadingBox = document.getElementById('dasha-cards-loading');
    var loadEngine = function () {
      if (!cardsBox) { return; }
      var b = window.AB_BIRTH || {};
      if (b.date == null) { return; }
      var q = new URLSearchParams({
        bdate: b.date || '', btime: b.time || '12:00',
        blat: b.lat != null ? b.lat : '', blon: b.lon != null ? b.lon : '',
        btz: b.tz != null ? b.tz : '', ayanamsa: b.ayanamsa || 'lahiri',
        maha: mSel.value, antar: aSel.value, lang: lang
      });
      if (loadingBox) { loadingBox.classList.remove('hidden'); }
      fetch('/calc/dashaEngine?' + q.toString(), { headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (loadingBox) { loadingBox.classList.add('hidden'); }
          if (d && d.html != null) { cardsBox.innerHTML = d.html; }
        })
        .catch(function () { if (loadingBox) { loadingBox.classList.add('hidden'); } });
    };

    // ---- Order both dropdowns like the dasha actually runs ----
    // Mahadasha: chronological (the order they occur, from AB_DASHA — first
    // comes first). Antardasha: for the selected Mahadasha, the 9 lords starting
    // from the Mahadasha lord in Vimshottari order (so it changes with Maha).
    var VIM = ['Ketu', 'Venus', 'Sun', 'Moon', 'Mars', 'Rahu', 'Jupiter', 'Saturn', 'Mercury'];
    var labelOf = {};
    [].forEach.call(mSel.options, function (o) { labelOf[o.value] = o.textContent; });
    function reorderMaha() {
      var order = [];
      (window.AB_DASHA || []).forEach(function (p) {
        if (p && p.lord && labelOf[p.lord] != null && order.indexOf(p.lord) === -1) { order.push(p.lord); }
      });
      VIM.forEach(function (l) { if (labelOf[l] != null && order.indexOf(l) === -1) { order.push(l); } });
      if (!order.length) { return; }
      var cur = mSel.value;
      mSel.innerHTML = order.map(function (l) {
        return '<option value="' + l + '"' + (l === cur ? ' selected' : '') + '>' + labelOf[l] + '</option>';
      }).join('');
      mSel.value = cur;
    }
    function rebuildAntar(keepSel) {
      var start = VIM.indexOf(mSel.value); if (start < 0) { start = 0; }
      var seq = []; for (var k = 0; k < 9; k++) { seq.push(VIM[(start + k) % 9]); }
      var want = keepSel ? aSel.value : mSel.value;      // default = Maha lord (first antar)
      if (seq.indexOf(want) === -1) { want = mSel.value; }
      aSel.innerHTML = seq.map(function (l) {
        return '<option value="' + l + '"' + (l === want ? ' selected' : '') + '>' + (labelOf[l] || l) + '</option>';
      }).join('');
      aSel.value = want;
    }
    reorderMaha();
    rebuildAntar(true);   // keep the running antardasha selected on first load

    var onChange = function () { load(); loadEngine(); };
    mSel.addEventListener('change', function () { rebuildAntar(false); onChange(); });
    aSel.addEventListener('change', onChange);
  })();

  // शास्त्रीय संदर्भ (BPHS) collapse toggle.
  (function () {
    var btn = document.getElementById('classical-toggle');
    var body = document.getElementById('classical-body');
    if (!btn || !body) { return; }
    btn.addEventListener('click', function () {
      var open = body.classList.toggle('hidden') === false;
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      btn.textContent = (open ? '▾' : '▸') + ' शास्त्रीय संदर्भ (BPHS 81 योग)';
    });
  })();

  // Collapse / expand the prediction cards (Dasha & Planet rows).
  (function () {
    document.querySelectorAll('.phala-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var body = document.getElementById(btn.getAttribute('data-target'));
        if (!body) { return; }
        var open = body.classList.toggle('hidden') === false;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.textContent = open ? 'Collapse ▴' : 'Expand ▾';
      });
    });
  })();

  // Dasha Details popup: renders the full Vimshottari dasha tree (expand a
  // period to see its children; scroll forward/backward). Opened by the "Dasha
  // Details" button OR by clicking the running-dasha strip. Close ✕ / backdrop /
  // Esc. Works on phone/tablet/laptop; body scroll is locked while open.
  (function () {
    var overlay = document.getElementById('dasha-modal');
    if (!overlay) { return; }
    var tree = document.getElementById('dasha-modal-tree');
    var body = document.getElementById('dasha-modal-body');
    var built = false;
    function open() {
      if (!built && window.ABDasha && window.AB_DASHA && window.AB_DASHA.length) {
        try { window.ABDasha.render(tree, window.AB_DASHA, { tz: window.AB_TZ, datesInline: true }); built = true; }
        catch (e) { tree.innerHTML = '<div class="text-sm text-gray-500">दशा उपलब्ध नहीं / Dasha not available.</div>'; }
      } else if (!built) {
        tree.innerHTML = '<div class="text-sm text-gray-500">दशा उपलब्ध नहीं / Dasha not available.</div>';
      }
      overlay.classList.remove('hidden');
      document.body.style.overflow = 'hidden';           // lock background scroll
      if (body) { body.scrollTop = 0; }
    }
    function close() { overlay.classList.add('hidden'); document.body.style.overflow = ''; }
    var btn = document.getElementById('dasha-detail-btn');
    if (btn) { btn.addEventListener('click', open); }
    document.querySelectorAll('.dasha-strip').forEach(function (s) {
      s.setAttribute('role', 'button'); s.setAttribute('tabindex', '0');
      s.addEventListener('click', open);
      s.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); } });
    });
    var x = document.getElementById('dasha-modal-close');
    if (x) { x.addEventListener('click', close); }
    overlay.addEventListener('click', function (e) { if (e.target === overlay) { close(); } });  // backdrop
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !overlay.classList.contains('hidden')) { close(); } });
  })();

  // Planet Prediction: pick a planet (left) -> show only its detail (right).
  // Prediction pickers are dropdowns: on change, show the matching detail.
  // dataAttr is the detail's data-* key; 'all' (House) reveals every detail.
  function bindPredSelect(selectId, detailSel, dataAttr, paneId) {
    var sel = document.getElementById(selectId);
    if (!sel) { return; }
    var details = document.querySelectorAll(detailSel);
    var pane = paneId ? document.getElementById(paneId) : null;
    sel.addEventListener('change', function () {
      var v = sel.value;
      details.forEach(function (d) {
        d.classList.toggle('hidden', v !== 'all' && d.getAttribute(dataAttr) !== v);
      });
      if (pane) { pane.scrollTop = 0; }
    });
  }
  bindPredSelect('planet-select', '#planet-phala-card .planet-detail', 'data-planet', 'planet-detail-pane');
  bindPredSelect('house-select', '#house-pred-card .house-detail', 'data-house', 'house-detail-pane');
  bindPredSelect('karaka-select', '#karaka-pred-card .karaka-detail', 'data-karaka', 'karaka-detail-pane');
  // Varshaphal prediction panels — bound through ONE re-runnable function
  // because a year change (ABVarsha fetch) replaces the pane contents and the
  // fresh elements need fresh handlers. Handlers are assigned (onchange /
  // onclick), not addEventListener, so re-binding never stacks duplicates.
  function bindVarshaPredPanels() {
    // Dropdown: switch between the सहम and ताजिक योग panes.
    var vpt = document.getElementById('vp-pred-type');
    if (vpt) {
      var applyPane = function () {
        var v = vpt.value;
        var gn = document.getElementById('vp-pred-general');
        if (gn) { gn.classList.toggle('hidden', v !== 'general'); }
        var s = document.getElementById('vp-pred-saham');
        var t = document.getElementById('vp-pred-tajik');
        var w = document.getElementById('vp-pred-varshesh');
        var m = document.getElementById('vp-pred-muntha');
        if (s) { s.classList.toggle('hidden', v !== 'saham'); }
        if (t) { t.classList.toggle('hidden', v !== 'tajik'); }
        if (w) { w.classList.toggle('hidden', v !== 'varshesh'); }
        if (m) { m.classList.toggle('hidden', v !== 'muntha'); }
        var b = document.getElementById('vp-pred-bhava');
        if (b) { b.classList.toggle('hidden', v !== 'bhava'); }
        var dd = document.getElementById('vp-pred-dasha');
        if (dd) { dd.classList.toggle('hidden', v !== 'dasha'); }
      };
      vpt.onchange = applyPane;
      applyPane();
    }

    // दशा-फल (Patyayini): दशा dropdown switches the visible dasha block; each
    // block has its own अन्तर्दशा dropdown; timeline rows jump to a dasha.
    (function () {
      var ds = document.getElementById('dp-dasha');
      if (!ds) { return; }
      var pane = document.getElementById('dp-detail-pane');
      function showDasha(v) {
        document.querySelectorAll('#dp-detail-pane .dp-block').forEach(function (bl) {
          bl.classList.toggle('hidden', bl.getAttribute('data-dasha') !== v);
        });
        if (pane) { pane.scrollTop = 0; }
      }
      ds.onchange = function () { showDasha(ds.value); };
      document.querySelectorAll('.dp-trow').forEach(function (row) {
        row.onclick = function () {
          ds.value = row.getAttribute('data-goto'); showDasha(ds.value);
          // The list now sits at the bottom — scroll back up to the दशा picker
          // so the newly selected dasha's phal is in view.
          var top = ds.closest('.pred-picker') || pane;
          if (top && top.scrollIntoView) { top.scrollIntoView({ block: 'start', behavior: 'smooth' }); }
        };
      });
      showDasha(ds.value);
    })();

    // भाव-फल (Tajik-Neelakanthi) filters: भाव dropdown · श्रेणी dropdown ·
    // "सभी दिखाएँ" checkbox. By DEFAULT only the rules applicable this year
    // (data-matched="1") show — under every भाव and श्रेणी. Ticking "सभी दिखाएँ"
    // reveals the non-applicable + reference rules too. House group titles hide
    // when they have no visible card.
    (function () {
      var hs = document.getElementById('tb-house');
      var cs = document.getElementById('tb-cat');
      var mo = document.getElementById('tb-showall');
      if (!hs && !cs && !mo) { return; }
      var pane = document.getElementById('tb-detail-pane');
      var empty = document.getElementById('tb-empty');
      function apply() {
        var hv = hs ? hs.value : 'all';
        var cv = cs ? cs.value : 'all';
        var showAll = mo ? mo.checked : false;   // default off → only applicable
        var shown = 0;
        document.querySelectorAll('#tb-detail-pane .tb-card').forEach(function (d) {
          var ok = (hv === 'all' || d.getAttribute('data-house') === hv)
                && (cv === 'all' || d.getAttribute('data-cat') === cv)
                && (showAll || d.getAttribute('data-matched') === '1');
          d.classList.toggle('hidden', !ok);
          if (ok) { shown++; }
        });
        document.querySelectorAll('#tb-detail-pane .tb-hgroup').forEach(function (g) {
          var any = g.querySelector('.tb-card:not(.hidden)');
          g.classList.toggle('hidden', !any);
        });
        if (empty) { empty.classList.toggle('hidden', shown !== 0); }
        if (pane) { pane.scrollTop = 0; }
      }
      if (hs) { hs.onchange = apply; }
      if (cs) { cs.onchange = apply; }
      if (mo) { mo.onchange = apply; }
      apply();
    })();

    // Saham selectors: सहम dropdown ("bydasha" = the mudda-dasha dropdown's
    // sahams, "all" = every saham, else one saham by key) + a मुद्दा-दशा dropdown
    // (in sequence) that picks WHICH dasha's active sahams the "bydasha" view
    // shows. Changing the mudda-dasha switches back to the bydasha view.
    (function () {
      var sel = document.getElementById('saham-select');
      if (!sel) { return; }
      var mud = document.getElementById('saham-mudda');
      var cards = document.querySelectorAll('#saham-detail-pane .saham-card');
      var pane = document.getElementById('saham-detail-pane');
      var empty = document.getElementById('saham-empty');
      function apply() {
        var v = sel.value, byDasha = v === 'bydasha';
        var lord = mud ? mud.value : '';
        var shown = 0;
        cards.forEach(function (d) {
          var show = v === 'all'
            || (byDasha ? d.getAttribute('data-sahamesh') === lord
                        : d.getAttribute('data-saham') === v);
          d.classList.toggle('hidden', !show);
          if (show) { shown++; }
        });
        if (empty) { empty.classList.toggle('hidden', !(byDasha && shown === 0)); }
        if (pane) { pane.scrollTop = 0; }
      }
      sel.onchange = apply;
      // Picking a mudda-dasha implies the "मुद्दा-दशा अनुसार" view.
      if (mud) { mud.onchange = function () { sel.value = 'bydasha'; apply(); }; }
      // Related-saham chips jump straight to that single saham.
      document.querySelectorAll('.saham-chip[data-goto]').forEach(function (b) {
        b.onclick = function () { sel.value = b.getAttribute('data-goto'); apply(); };
      });
      apply();
    })();

    // ताजिक योग view selector: "mudda" = the active Mudda-dasha lord's yogas
    // (default), "main" = munthesh + varsha-lagnesh, "all" = every record,
    // otherwise a single planet. Chart-level yogas (Ikkabal/Induvar) always
    // show; the per-planet drishti rows show only in single-planet contexts.
    (function () {
      var sel = document.getElementById('tajik-select');
      if (!sel) { return; }
      var pane = document.getElementById('tajik-detail-pane');
      var cards = document.querySelectorAll('#tajik-detail-pane .tajik-card');
      var rows = document.querySelectorAll('#tajik-detail-pane .tajik-drow');
      var empty = document.getElementById('tajik-empty');
      function wanted(v) {
        if (v === 'all') { return null; }
        if (v === 'mudda') { return [pane.getAttribute('data-mudda')]; }
        if (v === 'main') { return [pane.getAttribute('data-munthesh'), pane.getAttribute('data-lagnesh'), pane.getAttribute('data-varshesh')]; }
        return [v];
      }
      function apply() {
        var v = sel.value, w = wanted(v), shown = 0;
        cards.forEach(function (d) {
          var isChart = d.getAttribute('data-chart') === '1';
          var parts = (d.getAttribute('data-planets') || '').split(',');
          var show = w === null || isChart || parts.some(function (p) { return w.indexOf(p) >= 0; });
          d.classList.toggle('hidden', !show);
          if (show && !isChart) { shown++; }
        });
        rows.forEach(function (r) {
          var show = w !== null && w.indexOf(r.getAttribute('data-drow')) >= 0;
          r.classList.toggle('hidden', !show);
        });
        if (empty) { empty.classList.toggle('hidden', !(w !== null && shown === 0)); }
        if (pane) { pane.scrollTop = 0; }
      }
      sel.onchange = apply;
      // Office-bearer chips jump to that planet's view.
      document.querySelectorAll('.saham-chip[data-goto-planet]').forEach(function (b) {
        b.onclick = function () { sel.value = b.getAttribute('data-goto-planet'); apply(); };
      });
      apply();
    })();
  }
  window.ABBindVarshaPred = bindVarshaPredPanels;
  bindVarshaPredPanels();

  // गोचर फल filter — the panel HTML is (re)injected by gochar.js on every
  // transit fetch, so gochar.js calls this after each inject. "all" shows
  // every planet's Layer-1/Layer-3 card; otherwise a single planet.
  window.ABBindGocharPhal = function () {
    var sel = document.getElementById('gochar-select');   // planet filter
    var cat = document.getElementById('gochar-cat');      // category filter
    if (!sel && !cat) { return; }
    var pane = document.getElementById('gochar-detail-pane');
    var sections = document.querySelectorAll('#gochar-detail-pane .gochar-cat');
    var cards = document.querySelectorAll('#gochar-detail-pane .gochar-card');
    var empty = document.getElementById('gochar-empty');
    var planetPick = document.getElementById('gochar-planet-pick');
    function apply() {
      var cv = cat ? cat.value : 'all';        // selected category
      var pv = sel ? sel.value : 'all';        // selected planet
      // The "ग्रह चुनें" (planet) filter only applies to per-planet categories.
      // Hide it for साढ़े साती, general summary and मुहूर्त (no planet to pick).
      var usesPlanet = (cv === 'bhava' || cv === 'ashtak' || cv === 'natal');
      if (planetPick) { planetPick.classList.toggle('hidden', !usesPlanet); }
      if (!usesPlanet && sel) { pv = 'all'; sel.value = 'all'; }
      // Category: show only the chosen section(s).
      sections.forEach(function (s) {
        s.classList.toggle('hidden', cv !== 'all' && s.getAttribute('data-cat') !== cv);
      });
      // Planet: filter cards inside the visible section(s).
      var shown = 0;
      cards.forEach(function (d) {
        var secOk = cv === 'all' || (d.closest('.gochar-cat') && d.closest('.gochar-cat').getAttribute('data-cat') === cv);
        var dp = d.getAttribute('data-planet');
        var planetOk = pv === 'all' || dp === 'all' || dp === pv;
        var show = secOk && planetOk;
        d.classList.toggle('hidden', !show);
        if (show) { shown++; }
      });
      // The साढ़े साती timeline is not a .gochar-card, so treat a visible
      // .sade-wrap in the active section as content (don't show the empty note).
      var hasSade = !!document.querySelector('#gochar-detail-pane .gochar-cat:not(.hidden) .sade-wrap');
      if (empty) { empty.classList.toggle('hidden', shown !== 0 || hasSade); }
      if (pane) { pane.scrollTop = 0; }
    }
    if (sel) { sel.onchange = apply; }
    if (cat) { cat.onchange = apply; }
    // General Overview summary cards → jump to that श्रेणी (category).
    document.querySelectorAll('#gochar-detail-pane [data-gochar-jump]').forEach(function (el) {
      el.onclick = function () {
        if (cat) { cat.value = el.getAttribute('data-gochar-jump'); apply(); }
        if (pane) { pane.scrollTop = 0; }
      };
    });
    apply();
  };

  // साढ़े साती / ढैया timeline — basis (चन्द्र/लग्न) toggle + view-mode filter.
  // The Gochar panel HTML is re-injected on each transit fetch, so gochar.js
  // calls this after every inject.
  window.ABBindSadeTimeline = function () {
    var mode = document.getElementById('sade-mode');
    var cards = document.querySelectorAll('.sade-card');
    var empty = document.getElementById('sade-empty');
    if (!cards.length && !mode) { return; }
    function apply() {
      var mv = mode ? mode.value : 'current';   // current | all | past
      var shown = 0;
      cards.forEach(function (c) {
        var st = c.getAttribute('data-status');
        var modeOk = mv === 'all'
          || (mv === 'current' && (st === 'ACTIVE' || st === 'FUTURE'))
          || (mv === 'past' && (st === 'PAST' || st === 'ACTIVE'));
        c.classList.toggle('hidden', !modeOk);
        if (modeOk) { shown++; }
      });
      if (empty) { empty.classList.toggle('hidden', shown !== 0); }
    }
    if (mode) { mode.onchange = apply; }
    apply();
  };

  // आगामी गोचर — copy the summary as readable sentences (two panels may exist).
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.ug-copy') : null;
    if (!btn) { return; }
    var panel = btn.closest('.ug-panel');
    var pre = panel ? panel.querySelector('.ug-copytext') : null;
    var text = pre ? pre.textContent : '';
    var done = function () { var o = btn.textContent; btn.textContent = '✓ Copied'; setTimeout(function () { btn.textContent = o; }, 1500); };
    function fb() { var ta = document.createElement('textarea'); ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (x) {} document.body.removeChild(ta); }
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(text).then(done, function () { fb(); done(); }); }
    else { fb(); done(); }
  });

  // फलदीपिका योग catalogue filters — श्रेणी (default "सक्रिय" = detected only) · प्रकार.
  (function () {
    var cat = document.getElementById('py-cat');
    var typ = document.getElementById('py-type');
    if (!cat && !typ) { return; }
    var empty = document.getElementById('py-empty');
    function apply() {
      var cv = cat ? cat.value : 'active';
      var tv = typ ? typ.value : 'all';
      var activeOnly = cv === 'active';
      var shown = 0;
      document.querySelectorAll('#py-detail-pane .py-card').forEach(function (d) {
        var catOk = (cv === 'all' || cv === 'active') || d.getAttribute('data-cat') === cv;
        var ok = catOk
              && (tv === 'all' || d.getAttribute('data-type') === tv)
              && (!activeOnly || d.getAttribute('data-detected') === '1');
        d.classList.toggle('hidden', !ok);
        if (ok) { shown++; }
      });
      document.querySelectorAll('#py-detail-pane .py-group').forEach(function (g) {
        g.classList.toggle('hidden', !g.querySelector('.py-card:not(.hidden)'));
      });
      if (empty) {
        empty.classList.toggle('hidden', shown !== 0);
        empty.textContent = activeOnly ? 'इस कुंडली में कोई प्रमुख फलदीपिका योग संगणित नहीं हुआ।' : 'इस चयन के लिए कोई योग नहीं।';
      }
    }
    if (cat) { cat.onchange = apply; }
    if (typ) { typ.onchange = apply; }
    apply();
  })();

  // शाप-दोष / सन्तान योग catalogue filters — श्रेणी (default "सक्रिय" = detected only) · प्रकार.
  (function () {
    var cat = document.getElementById('sh-cat');
    var typ = document.getElementById('sh-type');
    if (!cat && !typ) { return; }
    var empty = document.getElementById('sh-empty');
    function apply() {
      var cv = cat ? cat.value : 'active';
      var tv = typ ? typ.value : 'all';
      var activeOnly = cv === 'active';
      var shown = 0;
      document.querySelectorAll('#sh-detail-pane .sh-card').forEach(function (d) {
        var catOk = (cv === 'all' || cv === 'active') || d.getAttribute('data-cat') === cv;
        var ok = catOk
              && (tv === 'all' || d.getAttribute('data-type') === tv)
              && (!activeOnly || d.getAttribute('data-detected') === '1');
        d.classList.toggle('hidden', !ok);
        if (ok) { shown++; }
      });
      document.querySelectorAll('#sh-detail-pane .sh-group').forEach(function (g) {
        g.classList.toggle('hidden', !g.querySelector('.sh-card:not(.hidden)'));
      });
      if (empty) {
        empty.classList.toggle('hidden', shown !== 0);
        empty.textContent = activeOnly ? 'इस कुंडली में कोई शाप-दोष / सन्तान योग संगणित नहीं हुआ।' : 'इस चयन के लिए कोई नियम नहीं।';
      }
    }
    if (cat) { cat.onchange = apply; }
    if (typ) { typ.onchange = apply; }
    apply();
  })();

  // Karaka copy button (Devanagari-safe).
  (function () {
    var kc = document.getElementById('karaka-copy');
    if (kc) {
      kc.addEventListener('click', function () {
        var src = document.getElementById('karaka-copy-text');
        var text = src ? src.textContent : '';
        var done = function () { var o = kc.textContent; kc.textContent = 'Copied!'; setTimeout(function () { kc.textContent = o; }, 1500); };
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
        } else { fallbackCopy(text); done(); }
      });
    }
  })();

  // Birth-form city search -> fills lat/lon/tz (worldwide, Open-Meteo).
  if (window.ABCitySearch) {
    ABCitySearch.init({
      input: '#b-place', results: '#b-place-results',
      lat: '#b-lat', lon: '#b-lon', tz: '#b-tz',
      // Compute the place's timezone offset on the entered birth date.
      getDate: function () {
        var d = (document.querySelector('[name="date"]') || {}).value;
        var t = (document.querySelector('[name="time"]') || {}).value || '12:00';
        var dt = d ? new Date(d + 'T' + (t.length === 5 ? t : '12:00') + ':00') : new Date();
        return isNaN(dt) ? new Date() : dt;
      }
    });
  }

  // Place of Birth: if no city name was searched (the field is showing lat/lon),
  // reverse-geocode the birth coordinates to "City, State, Country" for display.
  (function () {
    var el = document.getElementById('pob-value');
    if (!el || (el.getAttribute('data-place') || '').trim()) { return; }
    var b = window.AB_BIRTH || {};
    if (b.lat == null || b.lon == null) { return; }
    fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=' + b.lat + '&longitude=' + b.lon + '&localityLanguage=en')
      .then(function (r) { return r.json(); })
      .then(function (d) {
        var parts = [d.city || d.locality, d.principalSubdivision, d.countryName].filter(Boolean);
        if (parts.length) { el.textContent = parts.join(', '); }
      })
      .catch(function () { /* keep the lat/lon fallback */ });
  })();

  // ---- Layout v2: everything is built once; the side menu shows/hides sections ----
  function buildAllV2() {
    if (window.ABChart && window.AB_VARGAS) { ABChart.renderAll(window.AB_VARGAS, window.AB_HOUSES); }
    if (window.ABDasha) {
      var vdd = document.getElementById('vim-dasha-detail');
      if (vdd) ABDasha.render(vdd, window.AB_DASHA, { tz: window.AB_TZ, datesInline: true, maxRows: 10 });
      var mdd = document.getElementById('mudda-dasha-detail');
      if (mdd && window.AB_MUDDA) ABDasha.render(mdd, window.AB_MUDDA, { tz: window.AB_TZ, datesInline: true, maxRows: 10 });
    }
    if (window.ABGochar) {
      ABGochar.init({
        inputs: '#gochar-inputs', output: '#gochar-output',
        birth: window.AB_BIRTH,
        fallback: { lat: (window.AB_BIRTH && window.AB_BIRTH.lat) || 28.61, lon: (window.AB_BIRTH && window.AB_BIRTH.lon) || 77.21, tz: window.AB_TZ },
        steppers: true,
        // Keep the live transit result in AB_GOCHAR so any chart slot showing
        // "Gochar (Transit)" re-renders with the new date/place, and remember
        // the date/place for the transit slot subtitle.
        onResult: function (g, meta) {
          window.AB_GOCHAR = g;
          window.AB_GOCHAR_META = meta || null;
          if (typeof gpRefreshTransit === 'function') { gpRefreshTransit(); }
        }
      });
    }
    initGocharPanes();
    if (window.ABVarsha) {
      ABVarsha.init({
        box: '#vp-box', summary: '#vp-summary',
        chart: '#vp-chart-cell', dasha: '#vp-mudda-cell',   // split: chart row 1, Mudda dasha row 2
        birth: window.AB_BIRTH, tz: window.AB_TZ, year: window.AB_YEAR
      });
    }
    renderChartFrame('D1');
    setTimeout(setPanelHeights, 120);
    // Keyboard users can scroll the overflow regions (a11y).
    document.querySelectorAll('.overflow-x-auto, #pred-scroll, .overflow-y-auto').forEach(function (el) {
      if (!el.hasAttribute('tabindex')) { el.setAttribute('tabindex', '0'); }
    });
  }

  // ---- Gochar page: four configurable panes (2 charts + 2 predictions) ----
  // Each pane carries a dropdown so the viewer chooses what fills that space.
  // Chart panes render any varga / गोचर / वर्ष कुंडली; prediction panes swap the
  // Gochar Phal and Upcoming Gochar blocks (singleton nodes, so picking the same
  // one in both panes swaps them). Charts scale to fit, predictions scroll.
  var GP_ABBR = { Sun:'Su', Moon:'Mo', Mars:'Ma', Mercury:'Me', Jupiter:'Ju', Venus:'Ve', Saturn:'Sa', Rahu:'Ra', Ketu:'Ke' };
  var gpChartState = { c1: 'gochar', c2: 'D1' };
  var gpPredState  = { p1: 'phal',   p2: 'upcoming' };

  function gpChartCatalog() {
    var out = [], V = window.AB_VARGAS || {};
    out.push({ key: 'gochar', label: 'गोचर (Transit)' });
    if (V.D1 && V.D1.planets) { out.push({ key: 'D1', label: 'Rasi (D1) — जन्म कुंडली' }); }
    Object.keys(V).forEach(function (k) {
      if (k !== 'D1' && V[k] && V[k].planets) { out.push({ key: k, label: k + ' — ' + (V[k].label || k) }); }
    });
    if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { out.push({ key: 'varsha', label: 'Varsha Kundali (वर्ष कुंडली)' }); }
    return out;
  }
  function gpPredCatalog() {
    return [
      { key: 'phal',     label: 'Gochar Phal (गोचर फल)' },
      { key: 'upcoming', label: 'Upcoming Gochar / Transit (आगामी गोचर)' }
    ];
  }

  function gpRenderChart(hostId, key) {
    var host = document.querySelector('.gp-chart-host[data-host="' + hostId + '"]');
    if (!host || !window.ABChart) { return; }
    if (key === 'gochar') {
      var g = window.AB_GOCHAR || {};
      if (!g.transits || !g.ascendant) { host.innerHTML = '<div class="text-gray-400 italic text-sm">गोचर की गणना हो रही है…</div>'; return; }
      var pls = Object.keys(g.transits).map(function (n) {
        var t = g.transits[n];
        return { abbr: GP_ABBR[n] || n.slice(0, 2), sign: t.sign_index, deg: Math.floor(t.deg), retro: !!t.retro };
      });
      window.ABChart.renderNorth(host, { asc_sign: g.ascendant.sign_index, planets: pls }, { showDeg: true, fit: true });
    } else if (key === 'varsha') {
      if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { window.ABChart.renderNorth(host, window.AB_VARSHAN, { showDeg: true, fit: true }); }
    } else {
      var V = window.AB_VARGAS || {};
      if (V[key]) { window.ABChart.renderNorth(host, V[key], { showDeg: true, fit: true }); }
    }
  }
  function gpChartSub(key) {
    if (key === 'gochar') {
      var m = window.AB_GOCHAR_META;
      return m ? [m.date, m.time, m.place].filter(Boolean).join(' · ') : '';
    }
    if (key === 'varsha') { return String(window.AB_YEAR || ''); }
    return '<?= $h($in['date']) ?>';
  }
  function gpApplyChart(slotId) {
    var key = gpChartState[slotId];
    gpRenderChart(slotId, key);
    var sub = document.querySelector('.gp-sub[data-sub="' + slotId + '"]');
    if (sub) { sub.textContent = gpChartSub(key); }
  }
  // Live transit updated (date/place changed) — refresh any chart slot on gochar.
  function gpRefreshTransit() {
    ['c1', 'c2'].forEach(function (s) { if (gpChartState[s] === 'gochar') { gpApplyChart(s); } });
  }

  // Move the prediction blocks into their slots per gpPredState.
  function gpPlacePred() {
    ['p1', 'p2'].forEach(function (slotId) {
      var body = document.querySelector('.gp-body-pred[data-predbody="' + slotId + '"]');
      var block = document.querySelector('[data-pred="' + gpPredState[slotId] + '"]');
      if (body && block && block.parentNode !== body) { body.appendChild(block); }
    });
  }
  function gpSetPred(slotId, key) {
    var other = slotId === 'p1' ? 'p2' : 'p1';
    if (gpPredState[other] === key) { gpPredState[other] = gpPredState[slotId]; } // swap
    gpPredState[slotId] = key;
    // Sync both dropdowns to the new assignment, then move the blocks.
    document.querySelectorAll('.gp-pred-sel').forEach(function (sel) {
      sel.value = gpPredState[sel.getAttribute('data-slot-id')];
    });
    gpPlacePred();
  }

  var gocharPanesReady = false;
  function initGocharPanes() {
    if (gocharPanesReady) { return; }
    gocharPanesReady = true;
    var charts = gpChartCatalog(), preds = gpPredCatalog();
    // Populate + wire the two chart dropdowns.
    document.querySelectorAll('.gp-chart-sel').forEach(function (sel) {
      var slotId = sel.getAttribute('data-slot-id');
      sel.innerHTML = charts.map(function (c) { return '<option value="' + c.key + '">' + c.label + '</option>'; }).join('');
      if (!charts.some(function (c) { return c.key === gpChartState[slotId]; })) { gpChartState[slotId] = charts[0].key; }
      sel.value = gpChartState[slotId];
      sel.addEventListener('change', function () { gpChartState[slotId] = sel.value; gpApplyChart(slotId); });
      gpApplyChart(slotId);
    });
    // Populate + wire the two prediction dropdowns.
    document.querySelectorAll('.gp-pred-sel').forEach(function (sel) {
      var slotId = sel.getAttribute('data-slot-id');
      sel.innerHTML = preds.map(function (p) { return '<option value="' + p.key + '">' + p.label + '</option>'; }).join('');
      sel.value = gpPredState[slotId];
      sel.addEventListener('change', function () { gpSetPred(slotId, sel.value); });
    });
    gpPlacePred();
  }

  // Rotation: value 1..12 = which house is drawn at position 1 (1 = lagna).
  var HSIGN = ['मेष', 'वृषभ', 'मिथुन', 'कर्क', 'सिंह', 'कन्या', 'तुला', 'वृश्चिक', 'धनु', 'मकर', 'कुंभ', 'मीन'];
  function rotateVal() {
    var rs = document.getElementById('chart-rotate');
    var v = rs ? parseInt(rs.value, 10) : 1;
    return (v >= 1 && v <= 12) ? v : 1;
  }
  // Show each house's actual rashi in the Rotate dropdown for the current chart.
  function updateRotateLabels(ascSign) {
    var rs = document.getElementById('chart-rotate');
    if (!rs || ascSign == null) { return; }
    for (var hh = 1; hh <= 12; hh++) {
      var opt = rs.querySelector('option[value="' + hh + '"]');
      if (!opt) { continue; }
      var rashi = HSIGN[(((ascSign + hh - 1) % 12) + 12) % 12];
      opt.textContent = 'भाव ' + hh + ' — ' + rashi + (hh === 1 ? ' (लग्न)' : '');
    }
  }

  // Chart panel frame: same renderer + payloads as the section charts (protected).
  function renderChartFrame(key) {
    var frame = document.getElementById('chart-frame');
    if (!frame || !window.ABChart) { return; }
    var rot = rotateVal();
    if (key === 'gochar') {
      var g = window.AB_GOCHAR || {};
      if (!g.transits || !g.ascendant) { return; }
      var ABBR = { Sun:'Su', Moon:'Mo', Mars:'Ma', Mercury:'Me', Jupiter:'Ju', Venus:'Ve', Saturn:'Sa', Rahu:'Ra', Ketu:'Ke' };
      var planets = Object.keys(g.transits).map(function (n) {
        var t = g.transits[n];
        return { abbr: ABBR[n] || n.slice(0, 2), sign: t.sign_index, deg: Math.floor(t.deg), retro: !!t.retro };
      });
      ABChart.renderNorth(frame, { asc_sign: g.ascendant.sign_index, planets: planets }, { showDeg: true, rotate: rot });
      updateRotateLabels(g.ascendant.sign_index);
      return;
    }
    if (key === 'varsha') {
      if (window.AB_VARSHAN && window.AB_VARSHAN.planets) {
        ABChart.renderNorth(frame, window.AB_VARSHAN, { showDeg: true, rotate: rot });
        updateRotateLabels(window.AB_VARSHAN.asc_sign);
      }
      return;
    }
    if (window.AB_VARGAS && window.AB_VARGAS[key]) {
      ABChart.renderNorth(frame, window.AB_VARGAS[key], {
        title: null, showDeg: true, big: key === 'D1',
        outer: key === 'D1' ? (window.AB_HOUSES || null) : null,
        rotate: rot
      });
      updateRotateLabels(window.AB_VARGAS[key].asc_sign);
    }
  }
  var chartSel = document.getElementById('chart-select');
  if (chartSel) {
    chartSel.addEventListener('change', function () {
      renderChartFrame(this.value);
      setPanelHeights();
    });
  }
  var rotSel = document.getElementById('chart-rotate');
  if (rotSel) {
    rotSel.addEventListener('change', function () {
      renderChartFrame(chartSel ? chartSel.value : 'D1');
      setPanelHeights();
    });
  }

  // Prediction selector: swap which prediction layer shows in the scroll area.
  var predSel = document.getElementById('pred-select');
  if (predSel) {
    predSel.addEventListener('change', function () {
      var v = this.value;
      document.querySelectorAll('#pred-scroll .pred-view').forEach(function (el) {
        el.classList.toggle('hidden', el.getAttribute('data-pred') !== v);
      });
      var ps = document.getElementById('pred-scroll');
      if (ps) { ps.scrollTop = 0; }
    });
  }

  // General Overview cards → click jumps to the matching detailed prediction.
  // 'pred:<value>' switches the D1 prediction dropdown; 'gochar-sadesati' opens
  // the Gochar section and selects its Sade-Sati (शनि विशेष) category.
  (function () {
    function jump(target) {
      if (target.indexOf('pred:') === 0) {
        var sel = document.getElementById('pred-select');
        if (sel) { sel.value = target.slice(5); sel.dispatchEvent(new Event('change')); }
        var sc = document.getElementById('pred-scroll'); if (sc) { sc.scrollTop = 0; }
      } else if (target.indexOf('vp:') === 0) {
        // Varshaphal overview card → switch the Varshaphal prediction dropdown.
        var vsel = document.getElementById('vp-pred-type');
        if (vsel) { vsel.value = target.slice(3); vsel.dispatchEvent(new Event('change')); }
        var vsc = document.getElementById('vp-pred-scroll'); if (vsc) { vsc.scrollTop = 0; }
      } else if (target === 'gochar-sadesati') {
        var g = document.querySelector('#side-menu [data-sec="gochar"]');
        if (g) { g.click(); }
        var tries = 0, iv = setInterval(function () {
          var c = document.getElementById('gochar-cat');
          if (c && c.querySelector('option[value="shani"]')) {
            c.value = 'shani'; c.dispatchEvent(new Event('change')); clearInterval(iv);
          } else if (++tries > 50) { clearInterval(iv); }
        }, 150);
      }
    }
    document.addEventListener('click', function (e) {
      var el = e.target.closest && e.target.closest('.gen-card[data-genjump]');
      if (el) { jump(el.getAttribute('data-genjump')); }
    });
    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' && e.key !== ' ') { return; }
      var el = e.target.closest && e.target.closest('.gen-card[data-genjump]');
      if (el) { e.preventDefault(); jump(el.getAttribute('data-genjump')); }
    });
  })();

  // बल tabs: one strength table at a time.
  document.querySelectorAll('.bal-tabbar button').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var v = btn.getAttribute('data-bal');
      document.querySelectorAll('.bal-tabbar button').forEach(function (b) { b.classList.toggle('active', b === btn); });
      document.querySelectorAll('.bal-tab').forEach(function (el) {
        el.classList.toggle('hidden', el.getAttribute('data-bal') !== v);
      });
    });
  });

  // Expand / collapse (required): expanded = chart column hidden, prediction
  // panel spans both columns in two-column reading mode. Collapse restores the
  // exact three-column state. Persisted per session.
  (function () {
    var btn = document.getElementById('pred-expand');
    var home = document.getElementById('sec-home');
    if (!btn || !home) { return; }
    function apply(expanded) {
      home.classList.toggle('pred-expanded', expanded);
      btn.textContent = expanded ? '⤡' : '⤢';
      var label = expanded ? 'Collapse' : 'Expand';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('title', label);
      btn.setAttribute('aria-expanded', expanded ? 'true' : 'false');
      try { sessionStorage.setItem('l2predexp', expanded ? '1' : '0'); } catch (e) {}
      setPanelHeights();
    }
    btn.addEventListener('click', function () {
      apply(!home.classList.contains('pred-expanded'));
    });
    var saved = null;
    try { saved = sessionStorage.getItem('l2predexp'); } catch (e) {}
    if (saved === '1') { apply(true); }
  })();

  // मुहूर्त (Mahurat) page — built lazily on first visit. Reuses ABGochar for the
  // date form + transit chart (skipping the shared phal panel) and renders just
  // the मुहूर्त cards from the same result; D1 + Varsha charts come from globals.
  var mahuratBuilt = false;
  function buildMahuratPage() {
    if (mahuratBuilt) { return; }
    mahuratBuilt = true;
    var d1 = document.getElementById('mah-d1');
    if (d1 && window.ABChart && window.AB_VARGAS && window.AB_VARGAS.D1) {
      ABChart.renderNorth(d1, window.AB_VARGAS.D1, { showDeg: true });
    }
    var vc = document.getElementById('mah-varsha');
    if (vc && window.ABChart && window.AB_VARSHAN && window.AB_VARSHAN.planets) {
      ABChart.renderNorth(vc, window.AB_VARSHAN, { showDeg: true });
    } else if (vc) {
      vc.innerHTML = '<div class="text-sm text-gray-400 italic p-4">वर्ष कुंडली उपलब्ध नहीं।</div>';
    }
    if (window.ABGochar) {
      ABGochar.init({
        inputs: '#mah-inputs', output: '#mah-transit',
        birth: window.AB_BIRTH,
        fallback: { lat: (window.AB_BIRTH && window.AB_BIRTH.lat) || 28.61, lon: (window.AB_BIRTH && window.AB_BIRTH.lon) || 77.21, tz: window.AB_TZ },
        injectPhal: false,
        steppers: true,   // date/time +/- buttons; each step re-fetches → muhurat फल updates
        onResult: function (g) {
          var box = document.getElementById('mah-phal');
          if (!box || g.phal_html == null) { return; }
          var tmp = document.createElement('div');
          tmp.innerHTML = g.phal_html;
          var mu = tmp.querySelector('.gochar-cat[data-cat="muhurat"]');
          box.innerHTML = mu ? mu.innerHTML : '<div class="text-sm text-gray-500 p-2">इस तिथि हेतु मुहूर्त विवरण उपलब्ध नहीं।</div>';
          setTimeout(syncMahPhalHeight, 60);
        }
      });
    }
    window.addEventListener('resize', syncMahPhalHeight);
    setTimeout(function () { window.dispatchEvent(new Event('resize')); syncMahPhalHeight(); }, 250);
  }

  // Match the मुहूर्त prediction box height to the transit chart card so the two
  // ROW-1 cards line up; the prediction then scrolls inside that height. On
  // narrow (stacked) layouts the CSS fallback max-height applies instead.
  function syncMahPhalHeight() {
    var box = document.getElementById('mah-phal');
    var transit = document.getElementById('mah-transit');
    if (!box || !transit) { return; }
    var transitCard = transit.closest('.rounded-lg');
    var phalCard = box.closest('.rounded-lg');
    if (!transitCard || !phalCard) { return; }
    // Only align when the cards sit side-by-side (same row top).
    if (Math.abs(transitCard.getBoundingClientRect().top - phalCard.getBoundingClientRect().top) > 4) {
      box.style.maxHeight = '';
      return;
    }
    var overhead = phalCard.offsetHeight - box.offsetHeight; // padding + header
    var target = transitCard.offsetHeight - overhead;
    box.style.maxHeight = (target > 160 ? target : 160) + 'px';
  }

  // ---- Gochar Details (Planet Positions → Gochar Details) ----
  // A full transit table for a chosen date/time/place. Reuses ABGochar for the
  // date/time/place form (with +/- steppers) and IP location, but skips the
  // chart + phal and renders a Nakshatra/Pada/Degree/Rashi/Retro/Combust table.
  var GD_PCOL = { Sun:'#dc2626', Moon:'#0891b2', Mars:'#ea580c', Mercury:'#16a34a', Jupiter:'#b45309', Venus:'#db2777', Saturn:'#1d4ed8', Rahu:'#3d4554', Ketu:'#3d4554' };
  var GD_HI = { Sun:'सूर्य', Moon:'चन्द्र', Mars:'मंगल', Mercury:'बुध', Jupiter:'गुरु', Venus:'शुक्र', Saturn:'शनि', Rahu:'राहु', Ketu:'केतु' };
  function gdEsc(s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c]; }); }
  function gdDegMin(degInSign) {
    var d = Math.floor(degInSign), m = Math.round((degInSign - d) * 60);
    if (m === 60) { d += 1; m = 0; }
    return d + '°' + (m < 10 ? '0' : '') + m + "'";
  }

  function renderGocharDetailsTable(g, meta) {
    var host = document.getElementById('gd-table');
    if (!host || !g || !g.transits) { return; }
    var order = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'];
    var head = ['ग्रह / Planet', 'राशि / Rashi', 'अंश / Degree', 'नक्षत्र / Nakshatra', 'पद / Pada', 'वक्री / Retro', 'अस्त / Combust', 'अस्त % / Combust %'];
    var rows = '', copyRows = [head.join('\t')];
    order.forEach(function (name) {
      var t = g.transits[name];
      if (!t) { return; }
      var deg = gdDegMin(t.deg_in_sign != null ? t.deg_in_sign : t.deg);
      var nak = (t.nakshatra && t.nakshatra.name) || '—';
      var pada = (t.nakshatra && t.nakshatra.pada) || '—';
      var retro = t.retro ? 'वक्री ®' : '—';
      var combust = t.combust ? 'अस्त (Yes)' : '—';
      var pct = t.combust ? (t.combust.pct + '%') : '—';
      var pName = GD_HI[name] ? (name + ' (' + GD_HI[name] + ')') : name;
      rows +=
        '<tr class="border-b border-gray-100">' +
        '<td class="py-1 pr-3 font-semibold" style="color:' + GD_PCOL[name] + '">' + gdEsc(pName) + '</td>' +
        '<td class="pr-3">' + gdEsc(t.sign) + '</td>' +
        '<td class="pr-3">' + gdEsc(deg) + '</td>' +
        '<td class="pr-3">' + gdEsc(nak) + '</td>' +
        '<td class="pr-3">' + gdEsc(pada) + '</td>' +
        '<td class="pr-3">' + (t.retro ? '<span style="color:#b91c1c;font-weight:600">' + retro + '</span>' : '<span class="text-gray-300">—</span>') + '</td>' +
        '<td class="pr-3">' + (t.combust ? '<span style="color:#b45309;font-weight:600">अस्त</span>' : '<span class="text-gray-300">—</span>') + '</td>' +
        '<td class="pr-3">' + (t.combust ? '<span style="color:#b45309;font-weight:600">' + t.combust.pct + '%</span>' : '<span class="text-gray-300">—</span>') + '</td>' +
        '</tr>';
      copyRows.push([pName, t.sign, deg, nak, pada, (t.retro ? 'वक्री' : '—'), (t.combust ? 'अस्त' : '—'), pct].join('\t'));
    });
    var when = meta ? [meta.date, meta.time, meta.place].filter(Boolean).join(' · ') : '';
    var asc = g.ascendant ? ('<div class="text-xs text-gray-500 mt-2">गोचर लग्न / Transit Lagna: <b>' + gdEsc(g.ascendant.formatted) + '</b></div>') : '';
    host.innerHTML =
      (when ? '<div class="text-sm font-semibold text-gray-700 mb-2">' + gdEsc(when) + '</div>' : '') +
      '<table class="w-full text-sm"><thead><tr class="text-left border-b">' +
      head.map(function (hh) { return '<th class="py-1 pr-3">' + gdEsc(hh) + '</th>'; }).join('') +
      '</tr></thead><tbody>' + rows + '</tbody></table>' + asc;
    var pre = document.getElementById('gd-copytext');
    if (pre) { pre.textContent = 'Gochar Details' + (when ? ' — ' + when : '') + '\n\n' + copyRows.join('\n'); }
    var st = document.getElementById('gd-status');
    if (st) { st.textContent = ''; }
  }

  var gocharDetailsBuilt = false;
  // ---- Shared print-document window (D1 report, Varshaphal report, Lal Kitab
  // report/checklist all use this) + the D1/Varshaphal print buttons + the
  // topic-search that scans every prediction block for a keyword.
  (function () {
    var LKR_CSS = 'body{font-family:"Noto Sans Devanagari","Mangal",Arial,sans-serif;color:#111;margin:24px;line-height:1.55;font-size:13px}' +
      '.lkr-title{font-size:20px;font-weight:800;border-bottom:3px solid #b91c1c;padding-bottom:6px;margin-bottom:4px}' +
      '.lkr-sub{color:#555;font-size:12px;margin-bottom:14px}' +
      '.lkr-h{font-size:15px;font-weight:800;margin:16px 0 6px;color:#7c2d12;border-bottom:1px solid #ddd;padding-bottom:3px}' +
      '.lkr-box{border:1px solid #ddd;border-radius:6px;padding:8px 11px;margin:6px 0;page-break-inside:avoid}' +
      '.lkr-box.lkr-bad{border-color:#fca5a5;background:#fef2f2}' +
      '.lkr-box.lkr-hot{border-color:#fdba74;background:#fff7ed}' +
      '.lkr-why{color:#92400e;font-size:11.5px;margin-top:3px}' +
      '.lkr-ul{margin:5px 0 2px;padding-left:20px}.lkr-ul li{margin:2px 0}' +
      '.lkr-days{margin-top:6px;font-size:10px;color:#333}' +
      '.lkr-day{display:inline-block;width:20px;height:16px;border:1px solid #999;border-radius:3px;text-align:center;margin:1px;font-size:8.5px;color:#777;vertical-align:middle}' +
      '.lkr-foot{margin-top:18px;padding-top:8px;border-top:1px solid #ddd;color:#666;font-size:11px;font-style:italic}' +
      'select,button{display:none!important}' +
      'table{border-collapse:collapse;width:100%}td,th{border:1px solid #ddd;padding:3px 6px;font-size:11.5px;text-align:left}' +
      '@media print{body{margin:10mm}}';
    window.ABPrintDoc = function (src, title) {
      var html = '';
      if (typeof src === 'string') {
        var el = document.getElementById(src);
        if (el) { html = el.innerHTML; }
      } else if (src && src.html) { html = src.html; }
      if (!html) { return; }
      var w = window.open('', '_blank');
      if (!w) { alert('Popup blocked — कृपया popup की अनुमति दें।'); return; }
      w.document.write('<!DOCTYPE html><html lang="hi"><head><meta charset="utf-8"><title>' + title +
        '</title><style>' + LKR_CSS + '</style></head><body>' + html + '</body></html>');
      w.document.close();
      w.focus();
      setTimeout(function () { w.print(); }, 500);
    };
    var dp = document.getElementById('d1-print');
    if (dp) { dp.addEventListener('click', function () { window.ABPrintDoc('d1-report', 'Birth Chart Report'); }); }
    var vpb = document.getElementById('vp-print');
    if (vpb) {
      vpb.addEventListener('click', function () {
        // Compose the Varshaphal report from the already-rendered panes.
        var html = '<div class="lkr-title">वर्षफल रिपोर्ट (Varshaphal Report)</div>';
        ['vp-saar', 'vp-pred-varshesh', 'vp-pred-muntha'].forEach(function (id) {
          var e = document.getElementById(id);
          if (e) { html += '<div style="margin:12px 0">' + e.innerHTML + '</div>'; }
        });
        window.ABPrintDoc({ html: html }, 'Varshaphal Report');
      });
    }

    // ---- topic search across the D1 prediction blocks -------------------
    var TOPICS = {
      'धन':    ['धन', 'रुपया', 'पैसा', 'सम्पत्ति', 'संपत्ति', 'दौलत', 'आर्थिक', 'लक्ष्मी', 'धनी', 'व्यय', 'खर्च', 'लाभ'],
      'विवाह': ['विवाह', 'शादी', 'पति', 'पत्नी', 'दाम्पत्य', 'ससुराल', 'वैवाहिक', 'जीवनसाथी'],
      'संतान': ['संतान', 'सन्तान', 'पुत्र', 'औलाद', 'बच्च', 'गर्भ'],
      'रोग':   ['रोग', 'बीमार', 'स्वास्थ्य', 'दर्द', 'चोट', 'कष्ट', 'इलाज'],
      'नौकरी': ['नौकरी', 'व्यापार', 'कारोबार', 'धंधा', 'राज्य', 'सरकारी', 'पदोन्नति', 'तरक्की', 'उन्नति', 'रोज़गार', 'रोजगार', 'करियर'],
      'शिक्षा': ['शिक्षा', 'विद्या', 'पढ़ाई', 'बुद्धि', 'स्मरण'],
      'विदेश': ['विदेश', 'यात्रा', 'परदेस', 'प्रवास']
    };
    function scanBlocks(scopeSel, terms, cap) {
      var out = [];
      document.querySelectorAll(scopeSel).forEach(function (c) {
        if (out.length >= cap) { return; }
        var t = (c.textContent || '').trim();
        if (t.length < 18) { return; }
        if (!terms.some(function (k) { return k && t.indexOf(k) !== -1; })) { return; }
        // skip nested duplicates (an li inside an already-matched card)
        if (out.some(function (o) { return o.contains(c) || c.contains(o); })) { return; }
        out.push(c);
      });
      return out;
    }
    window.ABTopicSearch = function (label, terms) {
      var res = document.getElementById('pred-search-results');
      var note = document.getElementById('pred-search-note');
      if (!res) { return; }
      res.innerHTML = '';
      var PRED_HI = { general: 'सारांश', dasha: 'दशा फल', grah: 'ग्रह फल', bhav: 'भाव फल', bhavesh: 'भावेश', karak: 'कारक', yoga: 'योग', dosha: 'दोष', shaap: 'शाप' };
      var blocks = scanBlocks('#pred-scroll .pred-view:not([data-pred="search"]) li, ' +
        '#pred-scroll .pred-view:not([data-pred="search"]) .whitespace-pre-line, ' +
        '#pred-scroll .pred-view:not([data-pred="search"]) .yoga-card, ' +
        '#pred-scroll .pred-view:not([data-pred="search"]) .shaap-card, ' +
        '#pred-scroll .pred-view:not([data-pred="search"]) .gc-line', terms, 60);
      blocks.forEach(function (c) {
        var from = c.closest('.pred-view');
        var key = from ? from.getAttribute('data-pred') : '';
        var card = document.createElement('div');
        card.style.cssText = 'border:1px solid #e5e7eb;border-radius:8px;padding:7px 11px;margin-bottom:7px;font-size:.85rem;color:#334155;line-height:1.6';
        card.innerHTML = '<div style="font-size:.68rem;color:#0369a1;font-weight:700;margin-bottom:2px">📁 ' + (PRED_HI[key] || key) + '</div>';
        var body = document.createElement('div');
        body.textContent = (c.textContent || '').trim();
        card.appendChild(body);
        res.appendChild(card);
      });
      if (note) {
        note.textContent = blocks.length
          ? ('"' + label + '" — ' + blocks.length + ' परिणाम' + (blocks.length >= 60 ? ' (पहले 60)' : '') + '।')
          : ('"' + label + '" के लिए कोई परिणाम नहीं।');
      }
      var sel = document.getElementById('pred-select');
      if (sel) {
        var so = sel.querySelector('option[value="search"]');
        if (so) { so.hidden = false; }
        sel.value = 'search';
        sel.dispatchEvent(new Event('change'));
      }
    };
    document.querySelectorAll('#pred-topic-row .lk-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var topic = chip.getAttribute('data-topic');
        window.ABTopicSearch(chip.textContent.trim(), TOPICS[topic] || [topic]);
      });
    });
    var pq = document.getElementById('pred-q');
    if (pq) {
      pq.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        var v = pq.value.trim();
        if (v) { window.ABTopicSearch(v, TOPICS[v] || [v]); }
      });
    }
  })();

  // ---- Laal Kitab (लाल किताब): render the fixed-Aries chart + wire the
  // category dropdown and the "only ashubh / only applicable" filters. Built
  // lazily the first time the section is opened.
  var lalKitabBuilt = false;
  function applyLkFilters() {
    // "only ashubh" checkboxes (planet + house views): hide non-flagged cards.
    document.querySelectorAll('#sec-lalkitab .lk-onlybad').forEach(function (pb) {
      var scope = pb.getAttribute('data-scope');
      var onlyBad = pb.checked;
      document.querySelectorAll('#sec-lalkitab .lk-view[data-lk="' + scope + '"] .lk-card[data-bad]').forEach(function (c) {
        c.style.display = (onlyBad && c.getAttribute('data-bad') !== '1') ? 'none' : '';
      });
    });
    // Yoga + Shrap views: hide non-applicable cards when "only applicable" checked.
    document.querySelectorAll('#sec-lalkitab .lk-onlyapp').forEach(function (chk) {
      var scope = chk.getAttribute('data-scope');
      var onlyApp = chk.checked;
      document.querySelectorAll('#sec-lalkitab .lk-view[data-lk="' + scope + '"] .lk-card[data-app]').forEach(function (c) {
        c.style.display = (onlyApp && c.getAttribute('data-app') !== '1') ? 'none' : '';
      });
    });
  }
  function buildLalKitab() {
    if (lalKitabBuilt) { applyLkFilters(); return; }
    lalKitabBuilt = true;
    // Chart (fixed Aries lagna; planets placed by bhava).
    var host = document.getElementById('lk-chart');
    if (host && window.ABChart && window.AB_LALKITAB && window.AB_LALKITAB.planets) {
      window.ABChart.renderNorth(host, window.AB_LALKITAB, { showDeg: false, big: true });
    }
    var d1MiniDone = false;
    function showLkView(key) {
      document.querySelectorAll('#sec-lalkitab .lk-view').forEach(function (v) {
        v.classList.toggle('active', v.getAttribute('data-lk') === key);
      });
      // D1 mini chart for the comparison view — rendered lazily on first open
      // (a hidden container measures zero, so it can't be drawn at build time).
      if (key === 'compare' && !d1MiniDone && window.ABChart && window.AB_VARGAS && window.AB_VARGAS.D1) {
        var m = document.getElementById('lk-d1-mini');
        if (m) { window.ABChart.renderNorth(m, window.AB_VARGAS.D1, { showDeg: true, fit: true }); d1MiniDone = true; }
      }
      if (key === 'calendar') { buildLkCalendar(); }
      applyLkFilters();
    }

    // ---- उपाय-कैलेंडर: personalised, dated remedy plan. For each planet that
    // needs an उपाय, start on the next occurrence of that planet's वार and run
    // 43 days; list the weekly वार-dates as tick boxes.
    var LK_VAAR = { 'रविवार': 0, 'सोमवार': 1, 'मंगलवार': 2, 'बुधवार': 3, 'गुरुवार': 4, 'शुक्रवार': 5, 'शनिवार': 6 };
    var LK_MON = ['जनवरी','फरवरी','मार्च','अप्रैल','मई','जून','जुलाई','अगस्त','सितम्बर','अक्टूबर','नवम्बर','दिसम्बर'];
    function lkFmt(d) { return d.getDate() + ' ' + LK_MON[d.getMonth()] + ' ' + d.getFullYear(); }
    function lkVaarIndex(s) { var m = String(s || '').match(/रविवार|सोमवार|मंगलवार|बुधवार|गुरुवार|शुक्रवार|शनिवार/); return m ? LK_VAAR[m[0]] : null; }
    function lkCalendarHTML() {
      var cal = window.AB_LK_CAL || [];
      if (!cal.length) {
        return '<div class="lk-card good"><div class="lk-txt">✅ किसी ग्रह का उपाय आवश्यक नहीं — इस कुंडली में कोई ग्रह गंभीर अशुभ नहीं।</div></div>';
      }
      var today = new Date(); today.setHours(0, 0, 0, 0);
      var html = '';
      cal.forEach(function (p) {
        var di = lkVaarIndex(p.var);
        var start = new Date(today);
        if (di != null) { var add = (di - today.getDay() + 7) % 7; start.setDate(today.getDate() + add); }
        var end = new Date(start); end.setDate(start.getDate() + 42);   // 43-day window inclusive
        // weekly वार-dates within the window
        var dates = [], d = new Date(start);
        while (d <= end) { dates.push(new Date(d)); d.setDate(d.getDate() + 7); }
        var timeHint = /सायं/.test(p.var || '') ? ' (सायंकाल)' : (/प्रातः/.test(p.var || '') ? ' (प्रातःकाल)' : '');
        html += '<div class="lk-card ' + (p.verdict === 'अशुभ' ? 'bad' : '') + '">' +
          '<div class="lk-card-h">' + esc(p.hi) + ' — उपाय (' + esc(p.house_ord) + ' भाव)</div>' +
          '<div class="lk-sub"><b>वार:</b> ' + esc((p.var || '').replace(/\s*\(.*\)/, '')) + timeHint +
            ' · <b>आरंभ:</b> ' + lkFmt(start) + ' · <b>समाप्ति (43 दिन):</b> ' + lkFmt(end) + '</div>';
        if (p.remedies && p.remedies.length) {
          html += '<div class="lk-rem"><div class="lk-rem-h">🛠 करने योग्य उपाय</div><ul class="lk-rem-list">';
          p.remedies.forEach(function (r) { html += '<li>' + esc(r) + '</li>'; });
          html += '</ul></div>';
        }
        html += '<div class="lk-sub" style="margin-top:6px"><b>साप्ताहिक ' + esc((p.var || '').replace(/\s*\(.*\)/, '')) + ' तिथियाँ (टिक करें):</b></div>' +
          '<div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:4px">';
        dates.forEach(function (dt, i) {
          html += '<label style="border:1px solid #cbd5e1;border-radius:7px;padding:3px 8px;font-size:.76rem;cursor:pointer">' +
            '<input type="checkbox" style="vertical-align:middle;margin-right:4px">' + (i + 1) + '. ' + lkFmt(dt) + '</label>';
        });
        html += '</div></div>';
      });
      html += '<div class="lk-card"><div class="lk-card-h" style="font-size:.86rem">⚠ नियम</div>' +
        '<ul class="lk-rem-list" style="color:#475569">' +
        '<li>उपाय सूर्योदय से सूर्यास्त के बीच करें; बीच में नागा न हो — नागा हो तो पुनः आरंभ करें।</li>' +
        '<li>न्यूनतम 40 व अधिकतम 43 दिन निरंतर।</li>' +
        '<li>वर्जित उपाय हेतु "उपाय नियम" श्रेणी देखें।</li></ul></div>';
      return html;
    }
    function buildLkCalendar() {
      var host = document.getElementById('lk-cal-body');
      if (host) { host.innerHTML = lkCalendarHTML(); }
    }
    // esc helper (same as saved_charts) for safe HTML
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    // Category dropdown → toggle the matching .lk-view.
    var sel = document.getElementById('lk-select');
    if (sel && !sel._bound) {
      sel._bound = true;
      sel.addEventListener('change', function () { showLkView(sel.value); });
    }
    document.querySelectorAll('#sec-lalkitab .lk-onlybad, #sec-lalkitab .lk-onlyapp').forEach(function (chk) {
      chk.addEventListener('change', applyLkFilters);
    });

    // ---- Print (पूर्ण रिपोर्ट + उपाय checklist): shared print window helper.
    var pr = document.getElementById('lk-print-report');
    if (pr) { pr.addEventListener('click', function () { window.ABPrintDoc('lk-report', 'Lal Kitab Report'); }); }
    var pc = document.getElementById('lk-print-checklist');
    if (pc) { pc.addEventListener('click', function () { window.ABPrintDoc('lk-checklist', 'Lal Kitab Upay Checklist'); }); }
    var pcal = document.getElementById('lk-print-calendar');
    if (pcal) {
      pcal.addEventListener('click', function () {
        buildLkCalendar();
        var body = document.getElementById('lk-cal-body');
        var html = '<div class="lkr-title">उपाय-कैलेंडर (Remedy Calendar)</div>' +
          '<div class="lkr-sub">आज: ' + lkFmt(new Date()) + '</div>' + (body ? body.innerHTML : '');
        window.ABPrintDoc({ html: html }, 'Lal Kitab Remedy Calendar');
      });
    }

    // ---- Topic search across every category. A chip (or typed text) collects
    // its synonym set, matching cards are cloned into the results view with an
    // origin label, and the dropdown jumps to "खोज परिणाम".
    var LK_TOPICS = {
      'धन':     ['धन', 'रुपया', 'पैसा', 'सम्पत्ति', 'संपत्ति', 'दौलत', 'आर्थिक', 'लक्ष्मी', 'धनी', 'गरीब', 'कंगाल', 'व्यय', 'खर्च'],
      'विवाह':  ['विवाह', 'शादी', 'पति', 'पत्नी', 'कन्या', 'दाम्पत्य', 'ससुराल', 'वैवाहिक', 'विवाहोपरान्त'],
      'संतान':  ['संतान', 'सन्तान', 'पुत्र', 'औलाद', 'बच्च', 'लड़का', 'लड़के', 'गर्भ'],
      'रोग':    ['रोग', 'बीमार', 'स्वास्थ्य', 'दर्द', 'चोट', 'इलाज', 'दवा', 'कष्ट', 'मृत्यु'],
      'नौकरी':  ['नौकरी', 'व्यापार', 'कारोबार', 'धंधा', 'राज्य', 'सरकारी', 'पदोन्नति', 'तरक्की', 'उन्नति', 'रोज़गार', 'रोजगार', 'काम'],
      'शिक्षा': ['शिक्षा', 'विद्या', 'पढ़ाई', 'बुद्धि', 'स्मरण'],
      'मुकदमा': ['मुकदमा', 'कोर्ट', 'कचहरी', 'राज्यभय', 'दण्ड', 'जेल', 'कानून'],
      'विदेश':  ['विदेश', 'यात्रा', 'परदेस', 'प्रवास']
    };
    var LK_VIEW_NAMES = {
      overview: 'परिचय', planet: 'ग्रह फल', house: 'भाव फल', karak: 'कारक', yoga: 'योग',
      shrap: 'पैतृक ऋण', sadesati: 'साढ़े साती', manglik: 'मंगलीक', ayu: 'आयु योग',
      health: 'रोग/संतान', bhavan: 'गृह निर्माण', varsh: 'वर्ष चक्र', supt: 'सुप्त ग्रह',
      drishti: 'भाव दृष्टि', remedy: 'उपाय', rules: 'नियम', reference: 'संदर्भ', compare: 'तुलना'
    };
    function lkSearch(label, terms) {
      var res = document.getElementById('lk-search-results');
      var note = document.getElementById('lk-search-note');
      if (!res) { return; }
      res.innerHTML = '';
      var count = 0, MAX = 60;
      document.querySelectorAll('#sec-lalkitab .lk-view:not([data-lk="search"]) .lk-card').forEach(function (c) {
        if (count >= MAX) { return; }
        var t = c.textContent || '';
        var hit = terms.some(function (k) { return k && t.indexOf(k) !== -1; });
        if (!hit) { return; }
        var from = c.closest('.lk-view');
        var fromKey = from ? from.getAttribute('data-lk') : '';
        var cl = c.cloneNode(true);
        cl.style.display = '';
        var tag = document.createElement('div');
        tag.style.cssText = 'font-size:.68rem;color:#0369a1;margin-bottom:3px;font-weight:700';
        tag.textContent = '📁 ' + (LK_VIEW_NAMES[fromKey] || fromKey);
        cl.insertBefore(tag, cl.firstChild);
        res.appendChild(cl);
        count++;
      });
      if (note) {
        note.textContent = count
          ? ('"' + label + '" — ' + count + ' परिणाम मिले' + (count >= MAX ? ' (पहले ' + MAX + ' दिखाए गए)' : '') + '।')
          : ('"' + label + '" के लिए कोई परिणाम नहीं मिला।');
      }
      if (sel) {
        var so = sel.querySelector('option[value="search"]');
        if (so) { so.hidden = false; }
        sel.value = 'search';
      }
      showLkView('search');
    }
    document.querySelectorAll('#sec-lalkitab .lk-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var topic = chip.getAttribute('data-topic');
        lkSearch(chip.textContent.trim(), LK_TOPICS[topic] || [topic]);
      });
    });
    var q = document.getElementById('lk-q');
    if (q) {
      q.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter') { return; }
        var v = q.value.trim();
        if (!v) { return; }
        lkSearch(v, LK_TOPICS[v] || [v]);
      });
    }

    applyLkFilters();
  }

  function buildGocharDetails() {
    if (gocharDetailsBuilt || !window.ABGochar) { return; }
    if (!document.getElementById('gd-inputs')) { return; }
    gocharDetailsBuilt = true;
    var st = document.getElementById('gd-status');
    if (st) { st.textContent = 'गोचर की गणना हो रही है…'; }
    ABGochar.init({
      inputs: '#gd-inputs', output: '#gd-hidden',
      birth: window.AB_BIRTH,
      fallback: { lat: (window.AB_BIRTH && window.AB_BIRTH.lat) || 28.61, lon: (window.AB_BIRTH && window.AB_BIRTH.lon) || 77.21, tz: window.AB_TZ },
      injectPhal: false,
      steppers: true,
      onResult: function (g, meta) { renderGocharDetailsTable(g, meta); }
    });
    // Copy the table as tab-separated text (pastes cleanly into Sheets/Docs).
    var copyBtn = document.getElementById('gd-copy');
    if (copyBtn && !copyBtn._bound) {
      copyBtn._bound = true;
      copyBtn.addEventListener('click', function () {
        var pre = document.getElementById('gd-copytext');
        var txt = pre ? pre.textContent : '';
        if (!txt) { return; }
        var done = function () { var o = copyBtn.textContent; copyBtn.textContent = '✓ Copied'; setTimeout(function () { copyBtn.textContent = o; }, 1200); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, done); }
        else { var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} ta.remove(); done(); }
      });
    }
  }

  // Side-menu section switching: home = three-panel; others span the two panels.
  var FULL_SECTIONS = ['sec-profile', 'sec-custom', 'sec-grah', 'sec-varga', 'sec-dasha', 'sec-bal', 'sec-gochar', 'sec-muhurat', 'sec-varsha', 'sec-lalkitab', 'sec-today'];
  function showSection(key, focusPred) {
    var homeMode = key === 'home';
    var customMode = key === 'custom';
    // Hide the top overview strip (Name/DOB/Lagna/… tiles) on New / Profile and
    // Custom Screen — those pages don't want the summary header.
    var ov = document.getElementById('ov-strip');
    if (ov) { ov.classList.toggle('hidden', key === 'profile' || key === 'custom'); }
    var cp = document.getElementById('chart-panel');
    var pp = document.getElementById('pred-panel');
    if (cp) cp.classList.toggle('hidden', !homeMode);
    if (pp) pp.classList.toggle('hidden', !homeMode);
    // Custom Screen: full-width work area; the side menu becomes an off-canvas
    // drawer (reachable via the ☰ Menu button, which we force-show at all widths
    // through body.cs-mode) + show the "Birth Chart" jump.
    document.body.classList.toggle('cs-mode', customMode);
    // Selecting any section fully closes the mobile/tablet drawer AND its dim
    // backdrop. Without hiding the overlay here, a parent-menu tap (which keeps
    // the drawer logic from calling close()) would slide the drawer away but
    // leave the dark #menu-overlay hanging over the page — the "black shadow"
    // that only cleared when tapped. Hiding it here keeps the two in sync.
    document.body.classList.remove('menu-open');
    var _menuOv = document.getElementById('menu-overlay');
    if (_menuOv) { _menuOv.hidden = true; }
    var _menuBtn = document.getElementById('menu-btn');
    if (_menuBtn) { _menuBtn.setAttribute('aria-expanded', 'false'); }
    var grid = document.getElementById('sec-home');
    if (grid) { grid.classList.toggle('custom-active', customMode); }
    var back = document.getElementById('cs-back');
    if (back) { back.style.display = customMode ? '' : 'none'; }
    if (customMode && window.ABCustom) { window.ABCustom.ensure(); }
    FULL_SECTIONS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.toggle('hidden', id !== 'sec-' + key);
    });
    if (key === 'muhurat') { buildMahuratPage(); }
    if (key === 'grah') { buildGocharDetails(); }
    if (key === 'lalkitab') { buildLalKitab(); }
    if (homeMode) { setTimeout(setPanelHeights, 60); }
    // Cards rendered while their section was hidden (Mudda dasha, gochar pair)
    // measured zero heights — re-run their resize syncs now they are visible.
    setTimeout(function () { window.dispatchEvent(new Event('resize')); }, 200);
    if (focusPred && pp) { pp.scrollIntoView({ block: 'nearest' }); }
  }
  function activateBalTab(v) {
    document.querySelectorAll('.bal-tabbar button').forEach(function (b) {
      b.classList.toggle('active', b.getAttribute('data-bal') === v);
    });
    document.querySelectorAll('.bal-tab').forEach(function (el) {
      el.classList.toggle('hidden', el.getAttribute('data-bal') !== v);
    });
  }
  // Add a +/− caret to every menu item that has a sub-menu (so the user knows
  // which items expand). Kept in sync with the .open state.
  function syncCarets() {
    document.querySelectorAll('#side-menu .l2-mi').forEach(function (mi) {
      var top = mi.querySelector(':scope > button');
      if (!top || !mi.querySelector('.l2-sub')) { return; }
      var caret = top.querySelector('.l2-caret');
      if (!caret) {
        caret = document.createElement('span');
        caret.className = 'l2-caret';
        top.appendChild(caret);
      }
      caret.textContent = mi.classList.contains('open') ? '−' : '+';
    });
  }

  document.querySelectorAll('#side-menu [data-sec]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var isSub = !!btn.closest('.l2-sub');
      var sec = btn.getAttribute('data-sec');
      var tgt = btn.getAttribute('data-target');

      // If the prediction panel is expanded and the user navigates to the chart
      // (Kundali Chart / Birth Chart), leave reading mode so both panels show.
      var home = document.getElementById('sec-home');
      if (home && home.classList.contains('pred-expanded') && (tgt === 'chart-panel' || (sec === 'home' && !btn.hasAttribute('data-focus') && !tgt))) {
        var eb = document.getElementById('pred-expand');
        if (eb) { eb.click(); }   // reuse the collapse logic (state + heights)
      }

      // Top-level active + open state follows the section, whichever button was used.
      document.querySelectorAll('#side-menu .l2-mi').forEach(function (mi) {
        var top = mi.querySelector(':scope > button');
        if (!top) { return; }   // e.g. the Kundali Milan link item has no button
        var owns = top.getAttribute('data-sec') === sec && !top.hasAttribute('data-focus');
        if (btn.hasAttribute('data-focus')) { owns = top === btn; }
        top.classList.toggle('active', owns);
        mi.classList.toggle('open', owns);
      });
      // Sub-menu highlight: mark the clicked sub-item active (clear the rest).
      document.querySelectorAll('#side-menu .l2-sub button').forEach(function (s) {
        s.classList.toggle('active', s === btn && isSub);
      });
      syncCarets();

      showSection(sec, btn.hasAttribute('data-focus'));
      if (btn.hasAttribute('data-tab')) { activateBalTab(btn.getAttribute('data-tab')); }
      if (tgt) {
        var el = document.getElementById(tgt);
        if (el) { setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 80); }
      }
    });
  });

  // Overview tiles are shortcuts: Name/DOB/Place → New/Profile (edit); Lagna/
  // Moon/Sun → Birth (D1) chart; Current Dasha → Dasha; Yoga → Yoga prediction.
  // Each just clicks the matching side-menu button so all state/scroll logic is
  // reused.
  (function () {
    var menuBtn = function (sel) { return document.querySelector('#side-menu ' + sel); };
    var routes = {
      profile: function () { var b = menuBtn('[data-sec="profile"]'); if (b) { b.click(); } },
      chart: function () { var b = menuBtn('[data-sec="home"][data-target="chart-panel"]'); if (b) { b.click(); } },
      dasha: function () { var b = menuBtn('[data-sec="dasha"]'); if (b) { b.click(); } },
      yoga: function () {
        var b = menuBtn('[data-sec="home"][data-target="pred-panel"]'); if (b) { b.click(); }
        var ps = document.getElementById('pred-select');
        if (ps) { ps.value = 'yoga'; ps.dispatchEvent(new Event('change')); }
      }
    };
    document.querySelectorAll('#ov-strip .ov-tile[data-nav]').forEach(function (tile) {
      tile.style.cursor = 'pointer';
      var go = function () { var fn = routes[tile.getAttribute('data-nav')]; if (fn) { fn(); window.scrollTo({ top: 0, behavior: 'smooth' }); } };
      tile.addEventListener('click', go);
      tile.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
    });
  })();

  // ---- Mobile / tablet menu drawer (☰ Menu button) ----
  (function () {
    var btn = document.getElementById('menu-btn');
    var overlay = document.getElementById('menu-overlay');
    var menu = document.getElementById('side-menu');
    if (!btn || !overlay || !menu) { return; }
    function open() {
      document.body.classList.add('menu-open');
      overlay.hidden = false;
      btn.setAttribute('aria-expanded', 'true');
    }
    function close() {
      document.body.classList.remove('menu-open');
      overlay.hidden = true;
      btn.setAttribute('aria-expanded', 'false');
    }
    function toggle() { document.body.classList.contains('menu-open') ? close() : open(); }
    btn.addEventListener('click', toggle);
    overlay.addEventListener('click', close);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    // Selecting a menu item closes the drawer — except a top-level item that has
    // a sub-menu, which stays open so its just-revealed sub-items are tappable.
    menu.querySelectorAll('button, a').forEach(function (el) {
      el.addEventListener('click', function () {
        if (!window.matchMedia('(max-width: 1099px)').matches) { return; }
        var mi = el.closest('.l2-mi');
        var isParentWithSub = mi && el === mi.querySelector(':scope > button') && !!mi.querySelector('.l2-sub');
        if (!isParentWithSub) { close(); }
      });
    });
    // Never leave the drawer state hanging when resizing up to desktop.
    window.addEventListener('resize', function () {
      if (window.innerWidth >= 1100) { close(); }
    });
  })();
  // Open the sub-menu of the DEFAULT (active) section on load + draw carets.
  (function () {
    var active = document.querySelector('#side-menu .l2-mi > button.active');
    var mi = active ? active.closest('.l2-mi') : document.querySelector('#side-menu .l2-mi');
    if (mi) { mi.classList.add('open'); }
    syncCarets();
  })();

  // Equal heights (required): both panels match, sized by the chart at FULL
  // column width (the chart is square, so panel height follows column width).
  function setPanelHeights() {
    var cp = document.getElementById('chart-panel');
    var pp = document.getElementById('pred-panel');
    var frame = document.getElementById('chart-frame');
    if (!cp || !pp) { return; }
    if (!window.matchMedia('(min-width: 1100px)').matches) {
      cp.style.height = ''; pp.style.height = '';
      return;
    }
    if (cp.offsetParent === null) {
      // Expanded reading mode: prediction panel fills the viewport.
      var top = pp.getBoundingClientRect().top;
      pp.style.height = Math.max(560, window.innerHeight - top - 16) + 'px';
      return;
    }
    // Chart expands to the full column width; the panel wraps around it.
    var other = 0;
    Array.prototype.forEach.call(cp.children, function (ch) {
      if (ch !== frame) { other += ch.getBoundingClientRect().height; }
    });
    var chartH = frame ? frame.getBoundingClientRect().width : 0; // square chart
    var hpx = Math.max(560, Math.ceil(chartH + other + 32));
    cp.style.height = hpx + 'px';
    pp.style.height = hpx + 'px';
  }
  var resizeT2;
  window.addEventListener('resize', function () {
    clearTimeout(resizeT2);
    resizeT2 = setTimeout(setPanelHeights, 150);
  });

  // New Kundli: jump to the New / Profile section (birth-details form).
  var nk = document.getElementById('new-kundli');
  if (nk) {
    nk.addEventListener('click', function () {
      var mb = document.querySelector('#side-menu button[data-sec="profile"]');
      if (mb) { mb.click(); mb.scrollIntoView({ block: 'nearest' }); }
    });
  }

  // Default landing: a fresh visit opens on "New / Profile" (the New Kundali
  // entry form) so the visitor can add their details and start. Once they
  // Calculate — birth details are then in the URL — the page opens on the Birth
  // Chart instead, to show the result.
  (function () {
    var q = new URLSearchParams(window.location.search);
    var submitted = (q.get('date') || '').trim() !== '' || (q.get('name') || '').trim() !== '';
    if (!submitted) {
      var mb = document.querySelector('#side-menu button[data-sec="profile"]');
      if (mb) { mb.click(); window.scrollTo(0, 0); }
    }
  })();

  // ---- Custom Screen: user builds a free grid of chart / prediction panels ---
  (function () {
    var grid = document.getElementById('custom-grid');
    if (!grid) { return; }
    var START_SLOTS = 6;

    // Groups (display order in the picker + in-panel dropdown).
    var GROUPS = [
      { id: 'chart', label: '📊 Charts / कुंडली' },
      { id: 'd1',    label: '📜 D1 Birth-Chart Predictions / जन्म-कुंडली फल' },
      { id: 'vp',    label: '🎯 Varshaphal Predictions / वर्षफल' },
      { id: 'go',    label: '🌌 Gochar Predictions / गोचर फल' },
      { id: 'dasha', label: '⏳ Dasha / दशा' },
      { id: 'bala',  label: '💪 Bala / बल' },
      { id: 'other', label: '✨ Other / अन्य' }
    ];

    // Static (non-chart) panels. kind:'clone' copies a server-rendered source
    // element; kind:'dasha' draws a dasha tree from page data; kind:'link' opens
    // another page. Charts are added dynamically in chartPanels().
    var STATIC = [
      { g:'d1', key:'p_general',  label:'General (सारांश)',    kind:'clone', sel:'#pred-scroll .pred-view[data-pred="general"]' },
      { g:'d1', key:'p_dasha',    label:'Dasha Phal',          kind:'clone', sel:'#pred-scroll .pred-view[data-pred="dasha"]' },
      { g:'d1', key:'p_bhavesh',  label:'Bhavesh Phal',        kind:'clone', sel:'#pred-scroll .pred-view[data-pred="bhavesh"]' },
      { g:'d1', key:'p_grah',     label:'Graha Phal',          kind:'clone', sel:'#pred-scroll .pred-view[data-pred="grah"]' },
      { g:'d1', key:'p_bhav',     label:'Bhava Phaladesh',     kind:'clone', sel:'#pred-scroll .pred-view[data-pred="bhav"]' },
      { g:'d1', key:'p_karak',    label:'Karaka Phal',         kind:'clone', sel:'#pred-scroll .pred-view[data-pred="karak"]' },
      { g:'d1', key:'p_yoga',     label:'Yoga (योग)',          kind:'clone', sel:'#pred-scroll .pred-view[data-pred="yoga"]' },
      { g:'d1', key:'p_shaap',    label:'Shaap / Santaan',     kind:'clone', sel:'#pred-scroll .pred-view[data-pred="shaap"]' },
      { g:'vp', key:'vp_general', label:'Varshphal — General', kind:'clone', sel:'#vp-pred-general' },
      { g:'vp', key:'vp_saham',   label:'Saham (सहम)',         kind:'clone', sel:'#vp-pred-saham' },
      { g:'vp', key:'vp_tajik',   label:'Tajik Yoga',          kind:'clone', sel:'#vp-pred-tajik' },
      { g:'vp', key:'vp_varshesh',label:'Varshesh (वर्षेश)',   kind:'clone', sel:'#vp-pred-varshesh' },
      { g:'vp', key:'vp_muntha',  label:'Muntha (मुंथा)',      kind:'clone', sel:'#vp-pred-muntha' },
      { g:'vp', key:'vp_bhava',   label:'Bhava-Phal (भाव-फल)', kind:'clone', sel:'#vp-pred-bhava' },
      { g:'vp', key:'vp_dasha',   label:'Dasha-Phal (दशा-फल)', kind:'clone', sel:'#vp-pred-dasha' },
      { g:'go', key:'go_phal',    label:'Gochar Phal (गोचर फल)', kind:'clone', sel:'#gochar-phal', needs:'Gochar' },
      { g:'dasha', key:'vimshottari', label:'Vimshottari Dasha', kind:'dasha', data:'AB_DASHA' },
      { g:'dasha', key:'mudda',       label:'Mudda Dasha',       kind:'dasha', data:'AB_MUDDA' },
      { g:'bala', key:'shadbala',   label:'Shadbala',        kind:'clone', sel:'.bal-tab[data-bal="shad"]' },
      { g:'bala', key:'bhavabala',  label:'Bhava Bala',      kind:'clone', sel:'.bal-tab[data-bal="bb"]' },
      { g:'bala', key:'av',         label:'Ashtakavarga',    kind:'clone', sel:'.bal-tab[data-bal="av"]' },
      { g:'bala', key:'vimshopaka', label:'Vimshopaka Bala', kind:'clone', sel:'.bal-tab[data-bal="vim"]' },
      { g:'other', key:'milan', label:'Kundali Milan (मिलान)', kind:'link', url:'/milan' }
    ];

    function chartPanels() {
      var out = [], V = window.AB_VARGAS || {};
      Object.keys(V).forEach(function (k) { if (V[k] && V[k].planets) { out.push({ g:'chart', key:k, kind:'chart', label: k + ' — ' + (V[k].label || k) }); } });
      if (window.AB_GOCHAR && window.AB_GOCHAR.transits) { out.push({ g:'chart', key:'gochar', kind:'chart', label:'Gochar (Transit)' }); }
      if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { out.push({ g:'chart', key:'varsha', kind:'chart', label:'Varsha Kundali' }); }
      return out;
    }
    function available(p) {
      if (p.kind === 'dasha') { var d = window[p.data]; return !!(d && d.length); }
      if (p.kind === 'clone') { return !!document.querySelector(p.sel); }
      return true;
    }
    function catalog() { return chartPanels().concat(STATIC.filter(available)); }
    function panelByKey(key) { var c = catalog(); for (var i = 0; i < c.length; i++) { if (c[i].key === key) { return c[i]; } } return null; }

    function renderChart(host, key) {
      if (!window.ABChart) { return; }
      // fit:true → the chart scales to fit the panel (both width & height), so it
      // always fills a freely-resized box without overflowing or being clipped.
      if (key === 'gochar') {
        var g = window.AB_GOCHAR || {};
        if (!g.transits || !g.ascendant) { host.innerHTML = '<div class="text-gray-400 italic">Gochar not available.</div>'; return; }
        var AB = { Sun:'Su',Moon:'Mo',Mars:'Ma',Mercury:'Me',Jupiter:'Ju',Venus:'Ve',Saturn:'Sa',Rahu:'Ra',Ketu:'Ke' };
        var pls = Object.keys(g.transits).map(function (n) { var t=g.transits[n]; return { abbr:AB[n]||n.slice(0,2), sign:t.sign_index, deg:Math.floor(t.deg), retro:!!t.retro }; });
        window.ABChart.renderNorth(host, { asc_sign:g.ascendant.sign_index, planets:pls }, { showDeg:true, fit:true });
      } else if (key === 'varsha') {
        if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { window.ABChart.renderNorth(host, window.AB_VARSHAN, { showDeg:true, fit:true }); }
      } else {
        var V = window.AB_VARGAS || {};
        if (V[key]) { window.ABChart.renderNorth(host, V[key], { showDeg:true, fit:true, big:key==='D1', outer:key==='D1'?(window.AB_HOUSES||null):null }); }
      }
    }
    function renderPanel(body, key) {
      body.innerHTML = '';
      body.classList.remove('cs-body-chart');
      var p = panelByKey(key);
      if (!p) { body.innerHTML = '<div class="text-gray-400 italic">उपलब्ध नहीं / Not available.</div>'; return; }
      if (p.kind === 'chart') { body.classList.add('cs-body-chart'); var host = document.createElement('div'); host.className = 'cs-chart-host'; body.appendChild(host); renderChart(host, key); return; }
      if (p.kind === 'dasha') {
        var d = window[p.data];
        if (window.ABDasha && d && d.length) { ABDasha.render(body, d, { tz: window.AB_TZ, datesInline: true }); }
        else { body.innerHTML = '<div class="text-gray-400 italic">दशा उपलब्ध नहीं।</div>'; }
        return;
      }
      if (p.kind === 'link') {
        body.innerHTML = '<div style="text-align:center;padding:26px 14px">' +
          '<div style="font-size:2rem">💑</div>' +
          '<div style="font-weight:700;margin:6px 0 4px">' + p.label + '</div>' +
          '<div style="font-size:.82rem;color:#64748b;margin-bottom:12px">दो कुंडलियों का मिलान एक अलग पेज पर खुलता है।</div>' +
          '<a href="' + p.url + '" class="ab-btn" style="text-decoration:none;display:inline-block">खोलें / Open</a></div>';
        return;
      }
      var src = document.querySelector(p.sel);
      if (!src || !src.textContent.trim() || src.querySelector('.gochar-pred-soon')) {
        body.innerHTML = '<div class="text-gray-400 italic" style="padding:6px;line-height:1.5">' +
          'इस पैनल की सामग्री अभी तैयार नहीं है। कृपया ऊपर मेन्यू से एक बार <b>' + (p.needs || p.g) +
          '</b> सेक्शन खोलें, फिर यहाँ यह पैनल जोड़ें।</div>';
        return;
      }
      var clone = src.cloneNode(true);
      clone.classList.remove('hidden');
      clone.querySelectorAll('.pred-picker, .l2-picker, .pred-expand').forEach(function (e) { e.remove(); });
      clone.querySelectorAll('.hidden').forEach(function (e) { e.classList.remove('hidden'); });
      clone.querySelectorAll('[id]').forEach(function (e) { e.removeAttribute('id'); });
      body.appendChild(clone);
    }

    function emptySlot(slot) {
      slot.className = 'cs-slot cs-empty';
      slot.removeAttribute('data-key'); slot.style.width = ''; slot.style.height = '';
      slot.innerHTML = '<div style="text-align:center"><div class="cs-plus">+</div><div class="cs-plus-lbl">Add panel</div></div>';
      slot.onclick = function () { openPicker(slot); };
    }
    function fillSlot(slot, key, size) {
      slot.className = 'cs-slot'; slot.onclick = null; slot.innerHTML = '';
      if (size && size.w) { slot.style.width = size.w; }
      if (size && size.h) { slot.style.height = size.h; }
      var head = document.createElement('div'); head.className = 'cs-head';
      var sel = document.createElement('select'); sel.className = 'cs-sel';
      var cat = catalog();
      GROUPS.forEach(function (grp) {
        var items = cat.filter(function (p) { return p.g === grp.id; });
        if (!items.length) { return; }
        var og = document.createElement('optgroup'); og.label = grp.label;
        items.forEach(function (p) { var op = document.createElement('option'); op.value = p.key; op.textContent = p.label; if (p.key === key) { op.selected = true; } og.appendChild(op); });
        sel.appendChild(og);
      });
      var rep = document.createElement('button'); rep.className = 'cs-iconbtn'; rep.title = 'Replace panel'; rep.innerHTML = '⟳';
      var del = document.createElement('button'); del.className = 'cs-iconbtn'; del.title = 'Remove panel'; del.innerHTML = '✕';
      head.appendChild(sel); head.appendChild(rep); head.appendChild(del);
      var body = document.createElement('div'); body.className = 'cs-body';
      slot.appendChild(head); slot.appendChild(body);
      function draw() { slot.dataset.key = sel.value; renderPanel(body, sel.value); saveLayout(); }
      sel.addEventListener('change', draw);
      rep.addEventListener('click', function (e) { e.stopPropagation(); openPicker(slot); });
      del.addEventListener('click', function (e) { e.stopPropagation(); emptySlot(slot); saveLayout(); });
      observeResize(slot);
      draw();
    }

    var modal = document.getElementById('cs-picker'), targetSlot = null;
    function openPicker(slot) {
      targetSlot = slot; if (!modal) { return; }
      var host = document.getElementById('cs-opts-all'); if (!host) { return; }
      host.innerHTML = '';
      var cat = catalog();
      GROUPS.forEach(function (grp) {
        var items = cat.filter(function (p) { return p.g === grp.id; });
        if (!items.length) { return; }
        var t = document.createElement('div'); t.className = 'cs-group-title'; t.textContent = grp.label; host.appendChild(t);
        var wrap = document.createElement('div'); wrap.className = 'cs-opts';
        items.forEach(function (p) {
          var b = document.createElement('button'); b.className = 'cs-opt'; b.textContent = p.label;
          b.onclick = function () { fillSlot(targetSlot, p.key); closePicker(); saveLayout(); };
          wrap.appendChild(b);
        });
        host.appendChild(wrap);
      });
      modal.classList.remove('hidden');
    }
    function closePicker() { if (modal) { modal.classList.add('hidden'); } }
    if (modal) {
      modal.addEventListener('click', function (e) { if (e.target === modal) { closePicker(); } });
      var mx = modal.querySelector('.cs-modal-x'); if (mx) { mx.addEventListener('click', closePicker); }
    }

    // ---- Layout persistence — logged-in users only (else default screen) ----
    function layoutKey() { var u = window.AB_USER; return u ? 'ab_custom_layout_v1__' + u.id : null; }
    var saveT;
    function saveLayout() {
      var k = layoutKey(); if (!k) { return; }
      var slots = [].slice.call(grid.children).map(function (s) {
        return { key: s.dataset.key || '', w: s.style.width || '', h: s.style.height || '' };
      });
      clearTimeout(saveT);
      saveT = setTimeout(function () { try { localStorage.setItem(k, JSON.stringify(slots)); } catch (e) {} }, 250);
    }
    function loadLayout() {
      var k = layoutKey(); if (!k) { return null; }
      try { var a = JSON.parse(localStorage.getItem(k) || 'null'); return Array.isArray(a) ? a : null; } catch (e) { return null; }
    }
    var ro = window.ResizeObserver ? new ResizeObserver(function () { saveLayout(); }) : null;
    function observeResize(slot) { if (ro) { try { ro.observe(slot); } catch (e) {} } }

    function newSlot() { var s = document.createElement('div'); grid.appendChild(s); return s; }

    var built = false;
    function ensure() {
      if (built) { return; }
      built = true;
      var saved = loadLayout();
      if (saved && saved.length) {
        saved.forEach(function (rec) {
          var s = newSlot();
          if (rec.key && panelByKey(rec.key)) { fillSlot(s, rec.key, { w: rec.w, h: rec.h }); }
          else { emptySlot(s); }
        });
      } else {
        for (var i = 0; i < START_SLOTS; i++) { emptySlot(newSlot()); }
      }
    }
    var add = document.getElementById('cs-add'); if (add) { add.addEventListener('click', function () { emptySlot(newSlot()); saveLayout(); }); }
    var rst = document.getElementById('cs-reset'); if (rst) {
      rst.addEventListener('click', function () {
        grid.innerHTML = ''; built = false;
        var k = layoutKey(); if (k) { try { localStorage.removeItem(k); } catch (e) {} }
        ensure();
      });
    }
    window.ABCustom = { ensure: ensure };
  })();
  // "Birth Chart (D1)" top-bar button → leave the Custom Screen.
  var csBack = document.getElementById('cs-back');
  if (csBack) {
    csBack.addEventListener('click', function () {
      var mb = document.querySelector('#side-menu button[data-sec="home"]');
      if (mb) { mb.click(); }
    });
  }

  // House Details copy buttons → copy a plain-text summary of the houses.
  // "Long Details" copies the full per-house lines; "Short Details" copies only
  // the houses that hold planets ("In Nth House, Planet is A, B.").
  function bindHdCopy(btnId, srcId, label) {
    var btn = document.getElementById(btnId);
    if (!btn) { return; }
    btn.addEventListener('click', function () {
      var src = document.getElementById(srcId);
      var text = src ? src.textContent : '';
      var done = function () { btn.textContent = 'Copied!'; setTimeout(function () { btn.textContent = label; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
      } else { fallbackCopy(text); done(); }
    });
  }
  bindHdCopy('hd-copy', 'hd-copy-text', 'Long Details');
  bindHdCopy('hd-copy-short', 'hd-short-text', 'Short Details');
  function fallbackCopy(text) {
    var ta = document.createElement('textarea');
    ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
    document.body.appendChild(ta); ta.focus(); ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
  }

  // Boot: render charts/trees/panels once; menu default = जन्म कुंडली.
  buildAllV2();
})();
</script>
<?php endif; ?>

<?php $abVid = (string) ($view['accessVid'] ?? ''); if ($abVid !== ''): ?>
<script>
/* Access-log heartbeat: keeps this visit's "duration" in the server log current.
 * Pings on load, then every 30s while the tab is visible, and once more on
 * unload (via sendBeacon) so the final duration is recorded. Fully optional —
 * a failed ping never affects the page. */
(function () {
  'use strict';
  var vid = <?= json_encode($abVid) ?>;
  var url = '/calc/ping?vid=' + encodeURIComponent(vid);
  function ping() {
    try {
      if (navigator.sendBeacon) { navigator.sendBeacon(url); }
      else { fetch(url, { method: 'POST', keepalive: true }); }
    } catch (e) { /* ignore */ }
  }
  ping();
  setInterval(function () { if (!document.hidden) { ping(); } }, 30000);
  document.addEventListener('visibilitychange', function () { if (document.hidden) { ping(); } });
  window.addEventListener('pagehide', ping);
})();
</script>
<?php endif; ?>

<!-- ============ Save / Open charts: register-gate + search window ============ -->
<div id="ab-register-modal" class="ab-modal-overlay hidden" aria-hidden="true">
    <div class="ab-modal" role="dialog" aria-modal="true">
        <div class="ab-modal-head">🔒 केवल रजिस्टर्ड उपयोगकर्ता <button type="button" class="ab-modal-x" data-close aria-label="बंद करें">✕</button></div>
        <div class="ab-modal-body">
            <p style="margin:0 0 6px">चार्ट सहेजने की सुविधा केवल <b>रजिस्टर्ड उपयोगकर्ताओं</b> के लिए है। कृपया अपने प्रोफ़ाइल में चार्ट सहेजने हेतु <b>रजिस्टर</b> करें।</p>
            <p class="ab-modal-sub">Only registered users can save charts under their profile. Please register to save the charts.</p>
            <p class="ab-modal-note">रजिस्ट्रेशन/लॉगिन जल्द ही उपलब्ध होगा — तब सहेजे गए चार्ट यहीं दिखेंगे।</p>
        </div>
        <div class="ab-modal-foot"><button type="button" class="ab-btn" data-close>ठीक है / OK</button></div>
    </div>
</div>

<div id="ab-open-modal" class="ab-modal-overlay hidden" aria-hidden="true">
    <div class="ab-modal ab-modal-lg" role="dialog" aria-modal="true">
        <div class="ab-modal-head">📂 सहेजे गए चार्ट <span id="ab-open-count" class="ab-open-count"></span>
            <button type="button" class="ab-modal-x" data-close aria-label="बंद करें">✕</button></div>
        <div class="ab-open-search">
            <input id="ab-open-q" type="text" autocomplete="off" placeholder="🔎 नाम, फ़ोन, email, शहर, स्थान या तारीख़ से खोजें… (search)">
        </div>
        <div id="ab-open-list" class="ab-open-list"></div>
        <div class="ab-open-tip">कोई चार्ट खोलने पर वह D1 (जन्म-कुंडली) पेज पर खुलेगा। नया चार्ट बनाने हेतु ऊपर “New Kundli / New / Profile” पर जाएँ।</div>
    </div>
</div>

<!-- Save-chart profile popup: contact details asked at save time, prefilled
     from the chart form; नाम + जन्म-तिथि/समय/स्थान अनिवार्य, बाकी optional. -->
<div id="ab-save-modal" class="ab-modal-overlay hidden" aria-hidden="true">
    <div class="ab-modal ab-modal-lg" role="dialog" aria-modal="true" style="max-height:88vh;overflow-y:auto">
        <div class="ab-modal-head">💾 चार्ट सहेजें — Profile
            <button type="button" class="ab-modal-x" data-close aria-label="बंद करें">✕</button></div>
        <div class="ab-modal-body">
            <div id="ab-save-sum" class="ab-save-sum"></div>
            <div class="ab-save-grid">
                <label>नाम (Name) <span class="ab-req">*</span>
                    <input id="ab-sv-name" type="text" autocomplete="off"></label>
                <label>फ़ोन नंबर (Phone)
                    <input id="ab-sv-phone" type="text" inputmode="tel" autocomplete="off"></label>
                <label>Email ID
                    <input id="ab-sv-email" type="email" autocomplete="off"></label>
                <label>पता (Address)
                    <input id="ab-sv-address" type="text" autocomplete="off"></label>
                <label>शहर (City)
                    <input id="ab-sv-city" type="text" autocomplete="off"></label>
                <label>देश (Country)
                    <input id="ab-sv-country" type="text" autocomplete="off"></label>
            </div>
            <div id="ab-save-err" class="ab-save-err hidden"></div>
            <p class="ab-modal-note" style="margin-top:8px">* नाम तथा जन्म-तिथि/समय/स्थान अनिवार्य हैं; शेष विवरण optional।</p>
        </div>
        <div class="ab-modal-foot">
            <button type="button" class="ab-btn" id="ab-save-confirm">💾 सहेजें / Save</button>
            <button type="button" class="ab-btn ab-btn-ghost" data-close>रद्द करें / Cancel</button>
        </div>
    </div>
</div>

<div id="ab-toast" class="ab-toast hidden" role="status" aria-live="polite"></div>

<!-- Start-up notice (phone / tablet only) — the "under testing" banner is hidden
     on small screens, so it is shown once per browser session as a popup here. -->
<div id="ab-startup-modal" class="ab-modal-overlay hidden" aria-hidden="true">
    <div class="ab-modal" role="dialog" aria-modal="true" aria-labelledby="ab-startup-title">
        <div class="ab-modal-head" id="ab-startup-title">⚠️ Notice
            <button type="button" class="ab-modal-x" data-close aria-label="Close">✕</button></div>
        <div class="ab-modal-body">
            <p style="margin:0 0 8px;font-weight:700;color:#b45309">System is Under Testing — Not Finalized Yet.</p>
            <p class="ab-modal-note" style="color:#334155;background:#fff7ed;border-color:#fed7aa">Feedback: <a href="mailto:analysisofkarma@gmail.com" style="color:#b45309;font-weight:700">analysisofkarma@gmail.com</a></p>
        </div>
        <div class="ab-modal-foot"><button type="button" class="ab-btn" data-close>OK</button></div>
    </div>
</div>
<script>
// Show the "under testing" notice ONCE per browser session, on phone/tablet only
// (where the top-bar banner is hidden). sessionStorage keeps it from re-appearing
// while the client keeps working / navigates / reloads within the same tab, but
// it shows again when the tab or window is closed and the site is opened fresh.
(function () {
    var modal = document.getElementById('ab-startup-modal');
    if (!modal) { return; }
    function close() { modal.classList.add('hidden'); document.body.style.overflow = ''; }
    modal.addEventListener('click', function (e) {
        if (e.target === modal || (e.target.closest && e.target.closest('[data-close]'))) { close(); }
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.classList.contains('hidden')) { close(); } });
    try {
        var small = window.matchMedia('(max-width: 1099px)').matches;
        var seen = sessionStorage.getItem('ab_startup_notice_seen');
        if (small && !seen) {
            sessionStorage.setItem('ab_startup_notice_seen', '1');   // set immediately → once per session
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    } catch (e) { /* storage blocked → just don't show */ }
})();
</script>
</body>
</html>
