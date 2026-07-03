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
            --shubh:        #2E6E4E;   /* positive / benefic */
            --ashubh:       #8A2F2F;   /* negative / malefic */
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

        /* ---- Overview tiles (compact; birth info included) ---- */
        .ov-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(146px, 1fr)); gap: 8px; }
        @media (max-width: 699px) { .ov-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .ov-tile { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); padding: 6px 10px; min-width: 0; }
        .ov-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--ink-soft);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-value { font-size: .98rem; font-weight: 700; color: var(--ink); line-height: 1.4;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-value.acc-dasha { color: var(--sindoor); }
        .ov-value.acc-yoga  { color: var(--shubh); }
        .ov-sub { font-size: .72rem; color: var(--ink-soft);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

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
        .saham-pos { font-size: .95rem; margin-bottom: 3px; }
        .saham-facts { font-size: .85rem; color: #453F37; margin-bottom: 4px; }
        .saham-phal { font-size: 1rem; line-height: 1.6; margin: 2px 0; }
        .saham-timing { font-size: .85rem; color: var(--haldi); margin-top: 5px; border-top: 1px dashed var(--line); padding-top: 5px; }
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
            .topbar select, #new-kundli, #side-menu, #birth-form, .pred-expand,
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
        /* 700–1099px: menu becomes a horizontal chip bar; panels stack. */
        @media (max-width: 1099px) {
            .l2-grid { display: block; }
            .l2-menu { position: static; display: flex; overflow-x: auto; padding: 4px; margin-bottom: 12px; }
            .l2-mi { flex: 0 0 auto; }
            .l2-sub, .l2-mi.open .l2-sub { display: none; } /* chip bar: top-level only */
            .l2-caret { display: none; } /* no expand affordance in the chip bar */
            .l2-menu button { width: auto; white-space: nowrap; border-left: none;
                border-bottom: 3px solid transparent; border-radius: 6px 6px 0 0; }
            .l2-menu button.active { border-left: none; border-bottom-color: var(--sindoor); }
            .l2-panel { min-height: 0; height: auto !important; margin-bottom: 16px; }
            #pred-scroll { max-height: 70vh; }
            .l2-section { margin-top: 12px; }
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
        .gc-vshubh { background: #cfe9d9; color: #1c5138; }
        .gc-shubh  { background: #e2f0e8; color: #1c5138; }
        .gc-mishrit{ background: #f2e6c9; color: #8a6412; }
        .gc-pratikul { background: #f4d9d4; color: #8A2F2F; }
        .gc-ati    { background: #e7b3ac; color: #5f1a1a; }
        .gc-ashubh { background: #f4d9d4; color: #8A2F2F; }

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
        .de-remlist { list-style: none; padding-left: 0; margin: 0; }
        .de-remlist li { color: var(--haldi); margin: 3px 0; }
        .de-classical { margin-top: 8px; border-top: 1px dashed var(--line); padding-top: 8px; }
        .de-classical-btn { font-size: 12px; font-weight: 600; color: var(--ink-soft); }
        .de-classical-btn:hover { color: var(--sindoor); }
        #planet-phala-card .pp-sec  { font-size: 1.15rem; font-weight: 700; }
        #planet-phala-card .pp-sub  { font-size: 1.1rem;  font-weight: 700; }

        /* ---- Custom Screen ---- */
        .l2-grid.custom-active > #side-menu { display: none; }
        .l2-grid.custom-active > #sec-custom { grid-column: 1 / 4; }
        .cs-bar { display: flex; align-items: center; gap: 10px 14px; flex-wrap: wrap; margin-bottom: 12px; }
        .cs-title { font-size: 1.15rem; font-weight: 800; color: var(--ink); }
        .cs-title-hi { color: var(--ink-soft); font-weight: 600; font-size: .95rem; }
        .cs-hint { font-size: .82rem; color: var(--ink-soft); }
        .cs-toolbtn { border: 1px solid var(--line); background: var(--card); color: var(--ink);
            font-weight: 700; font-size: .82rem; padding: 7px 12px; border-radius: 8px; }
        .cs-toolbtn:hover { background: var(--sindoor-soft); border-color: var(--sindoor); color: var(--sindoor); }
        .cs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 14px; align-items: start; }
        .cs-slot { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); min-height: 320px; display: flex; flex-direction: column;
            overflow: hidden; }
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
        .cs-body { padding: 10px; overflow: auto; flex: 1 1 auto; max-height: 560px; }
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
    </style>
</head>
<body class="text-gray-900">

<!-- ============ TOP BAR (layout v2, Phase 1) ============ -->
<header class="topbar">
    <div class="topbar-inner">
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
            <button type="button" id="new-kundli" class="btn-sindoor">New Kundli</button>
        </div>
    </div>
</header>
<script>
// Top-bar language switch: swap the phala_lang param and reload (keeps layout=new).
document.getElementById('topbar-lang').addEventListener('change', function () {
    var u = new URL(window.location.href);
    u.searchParams.set('phala_lang', this.value);
    u.searchParams.set('layout', 'new');
    window.location.href = u.toString();
});
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
    <div class="ov-tiles">
        <div class="ov-tile">
            <div class="ov-label">Name</div>
            <div class="ov-value"><?= $in['name'] !== '' ? $h($in['name']) : '—' ?></div>
            <div class="ov-sub"><?= $in['gender'] !== '' ? $h($in['gender']) : '&nbsp;' ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">DOB / Time</div>
            <div class="ov-value"><?= $h($in['date']) ?></div>
            <div class="ov-sub"><?= $h($in['time']) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">Birth Place</div>
            <div class="ov-value" title="<?= $h($pobTop) ?>"><?= $h($pobTop) ?></div>
            <div class="ov-sub"><?= $h($in['latIn']) ?>, <?= $h($in['lonIn']) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">Lagna (Asc)</div>
            <div class="ov-value"><?= $h($rashiHi[$ovLagna] ?? $ovLagna) ?></div>
            <div class="ov-sub"><?= $h($ovLagna) ?> · <?= $h((string) ($chart['ascendant']['formatted'] ?? '')) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">Moon Sign (राशि)</div>
            <div class="ov-value"><?= $h($rashiHi[$ovMoon] ?? $ovMoon) ?></div>
            <div class="ov-sub">चंद्र राशि · <?= $h($ovMoon) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">Sun Sign</div>
            <div class="ov-value"><?= $h($rashiHi[$ovSun] ?? $ovSun) ?></div>
            <div class="ov-sub">Sun Sign · <?= $h($ovSun) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">Current Dasha</div>
            <div class="ov-value acc-dasha"><?= $h(($grahaHi[$ovMaha] ?? $ovMaha) . ($ovAntar !== '' ? ' – ' . ($grahaHi[$ovAntar] ?? $ovAntar) : '')) ?></div>
            <div class="ov-sub"><?= $ovPrat !== '' ? 'प्रत्यंतर: ' . $h($grahaHi[$ovPrat] ?? $ovPrat) : '&nbsp;' ?></div>
        </div>
        <?php
            $ovYogas = $view['yogas'] ?? [];
            $ovYGood = array_values(array_filter($ovYogas, static fn($y) => !empty($y['good'])));
        ?>
        <div class="ov-tile">
            <div class="ov-label">Yoga (योग)</div>
            <div class="ov-value acc-yoga"><?= $ovYGood !== [] ? count($ovYGood) . ' शुभ योग' : '—' ?></div>
            <div class="ov-sub"><?= $ovYGood !== []
                ? $h(implode(' · ', array_slice(array_map(static fn($y) => (string) $y['name'], $ovYGood), 0, 2)))
                : 'कोई प्रमुख योग नहीं' ?></div>
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
                    <button type="button" data-sec="gochar" data-target="card-gocharpair">Gochar Chart + Phal</button>
                    <button type="button" data-sec="gochar" data-target="card-gochardet">D1 + Transit Table</button>
                </div>
            </div>
            <div class="l2-mi">
                <button type="button" data-sec="varsha">Varshaphal</button>
                <div class="l2-sub">
                    <button type="button" data-sec="varsha" data-target="card-vpbox">Year Selection</button>
                    <button type="button" data-sec="varsha" data-target="vp-output">Varsha Chart + Mudda Dasha</button>
                    <button type="button" data-sec="varsha" data-target="card-varshadet">Annual Positions</button>
                </div>
            </div>
            <div class="l2-mi">
                <a href="<?= $h(\AutoBusiness\Core\Asset::url('/milan')) ?>" class="l2-mi-link">Kundali Milan</a>
            </div>
        </nav>

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
            <div class="l2-picker">
            <span class="pick-tag">Select Chart ▾</span>
            <select id="chart-select" class="l2-select" aria-label="कुंडली चुनें">
                <?php foreach ($vargaHi as $vk => $vlbl): if (!isset($vargas[$vk])) { continue; } ?>
                    <option value="<?= $h($vk) ?>"><?= $h($vk) ?> — <?= $h($vlbl) ?></option>
                <?php endforeach; ?>
                <?php if ($gochar !== null): ?><option value="gochar">Gochar (Transit)</option><?php endif; ?>
                <?php if (($view['varshaNorth'] ?? null) !== null): ?><option value="varsha">Varsha Kundali (<?= (int) $in['forYear'] ?>)</option><?php endif; ?>
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
                <span class="pick-tag">Select Prediction ▾</span>
                <select id="pred-select" class="l2-select" aria-label="फलादेश चुनें" style="margin-bottom:0">
                    <option value="dasha">Dasha Phal (दशा फल)</option>
                    <option value="bhavesh">Bhavesh Phal (भावेश फल)</option>
                    <option value="grah">Graha Phal (ग्रह फल)</option>
                    <option value="bhav">Bhava Phaladesh (भाव फलादेश)</option>
                    <option value="karak">Karaka Phal (कारक फल)</option>
                    <option value="yoga">Yoga (योग)</option>
                </select>
                </div>
                <button type="button" id="pred-expand" class="pred-expand" aria-label="Expand" title="Expand">⤢</button>
            </div>
            <div id="pred-scroll">

            <div class="pred-view" data-pred="dasha">
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
            <button type="button" id="classical-toggle" class="de-classical-btn" aria-expanded="false">▸ शास्त्रीय संदर्भ (BPHS 81 योग)</button>
            <div id="classical-body" class="hidden mt-2">
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
        <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mb-3">
            <h2 class="font-semibold">Karaka Prediction <span class="text-xs text-gray-400 font-normal">(कारक फल)</span></h2>
            <span class="text-xs text-gray-400">प्रत्येक भाव — लग्न (बाहरी) व कारक (आंतरिक), भाव फल के साथ संयुक्त</span>
            <button id="karaka-copy" type="button" class="ml-auto text-xs bg-gray-100 hover:bg-gray-200 border rounded px-3 py-1 font-semibold">Copy</button>
            <button type="button" class="phala-toggle text-xs bg-gray-100 hover:bg-gray-200 border rounded px-2 py-1 font-semibold" data-target="karaka-body" aria-expanded="true">Collapse ▴</button>
        </div>
        <pre id="karaka-copy-text" class="hidden"><?= $h($kCopyText) ?></pre>
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

            <!-- योग — classical yogas detected from the computed placements. -->
            <div class="pred-view hidden" data-pred="yoga">
                <?php $yogas = $view['yogas'] ?? []; ?>
                <?php if (empty($yogas)): ?>
                    <div class="text-gray-500 italic p-3">इस कुंडली में कोई प्रमुख योग नहीं मिला।</div>
                <?php else: foreach ($yogas as $y): ?>
                    <div class="yoga-card<?= empty($y['good']) ? ' yoga-bad' : '' ?>">
                        <div class="yoga-title"><?= $h((string) $y['name']) ?><?= empty($y['good']) ? '' : ' ✓' ?></div>
                        <div class="yoga-why"><b>कारण:</b> <?= $h((string) $y['why']) ?></div>
                        <div class="yoga-res"><b>फल:</b> <?= $h((string) $y['result']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div><!-- /pred-view yoga -->

            </div>
        </section>

        <!-- ============ New / Profile (full-width section) — the birth-details
             form, shown beside the menu like the Gochar Calculation card ======= -->
        <div id="sec-profile" class="l2-section l2-full hidden space-y-4 md:space-y-6">
            <?php require __DIR__ . '/_birth_form.php'; ?>
        </div>

        <!-- ============ CUSTOM SCREEN (full-width, user-arranged panels) ======= -->
        <div id="sec-custom" class="l2-section l2-full hidden">
            <div class="cs-bar">
                <span class="cs-title">Custom Screen <span class="cs-title-hi">— अपनी स्क्रीन</span></span>
                <span class="cs-hint">Press <b>+</b> in any panel, then pick a chart or a prediction. Change or remove it any time.</span>
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
                <div class="cs-modal-body">
                    <div class="cs-group-title">📊 Charts / कुंडली</div>
                    <div class="cs-opts" id="cs-opts-chart"></div>
                    <div class="cs-group-title">📜 Predictions / फलादेश</div>
                    <div class="cs-opts" id="cs-opts-pred"></div>
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
    ?>


    <!-- House details: planets, rashi, Ashtakavarga (AV), Bhava Bala total, lord -->
    <div id="card-housedet" class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <div class="flex items-center justify-between mb-2">
            <h2 class="font-semibold">House Details</h2>
            <button id="hd-copy" type="button" class="text-xs bg-gray-100 hover:bg-gray-200 border rounded px-3 py-1 font-semibold">Copy</button>
        </div>
        <pre id="hd-copy-text" class="hidden"><?= $h($copyText) ?></pre>
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


        <!-- ROW 1: current Gochar (transit) chart on the LEFT + Gochar prediction
             panel on the RIGHT (rules coming later). -->
        <div id="card-gocharpair" class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                <div id="gochar-output" class="w-full"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4 flex flex-col">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700">
                    <span class="font-semibold text-gray-800">Gochar Phal <span class="text-xs text-gray-400 font-normal">(गोचर फल)</span></span>
                </div>
                <div class="gochar-pred-soon">
                    <div class="gps-icon">🔮</div>
                    <div class="gps-title">भविष्यफल शीघ्र आ रहा है — Predictions coming soon</div>
                    <div class="gps-sub">इस भाग में शीघ्र ही गोचर-आधारित भविष्यफल जोड़ा जाएगा।<br>Gochar-based predictions will be added here soon.</div>
                </div>
            </div>
        </div>

        <!-- ROW 2: natal Rasi (D1) chart on the LEFT + the transit detail table
             on the RIGHT. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mb-2 pb-2 border-b text-sm text-gray-700">
                    <span class="font-semibold text-gray-800">Rasi (D1)</span>
                    <span class="ml-auto flex flex-wrap items-center gap-x-4">
                        <span><?= $h($in['date']) ?></span>
                        <span><?= $h($in['time']) ?></span>
                    </span>
                </div>
                <div class="w-full" data-varga="D1" data-notitle="1"></div>
            </div>
    <?php if ($gochar !== null): ?>
            <div id="card-gochardet" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
                <h2 class="font-semibold mb-2">Gochar (Transits) — <?= $h($in['gocharIn'] . ' ' . $in['gocharTimeIn']) ?></h2>
                <?php if (isset($gochar['ascendant'])): ?>
                    <div class="mb-2">Transit Lagna: <b><?= $h($gochar['ascendant']['formatted']) ?></b></div>
                <?php endif; ?>
                <table class="w-full">
                    <thead><tr class="text-left border-b"><th class="py-1 pr-3">Planet</th><th class="pr-3">Transit</th><th class="pr-3">House/Lagna</th><th>House/Moon</th></tr></thead>
                    <tbody>
                    <?php foreach ($gochar['transits'] as $name => $t): ?>
                        <tr class="border-b border-gray-100"><td class="py-1 pr-3 font-medium"><?= $h($name) ?><?= $t['retro'] ? ' <sup style="color:#b91c1c;font-size:0.9em">&#174;</sup>' : '' ?></td>
                            <td class="pr-3"><?= $h($t['formatted']) ?></td><td class="pr-3"><?= (int) $t['house_from_lagna'] ?></td><td><?= (int) $t['house_from_moon'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php else: ?>
            <div id="card-gochardet" class="bg-white rounded-lg shadow p-4 text-sm text-gray-400 italic">Transit table not available.</div>
    <?php endif; ?>
        </div>
        </div><!-- /sec-gochar -->

        <!-- ============ वर्ष कुंडली (full-width section) ============ -->
        <div id="sec-varsha" class="l2-section l2-full hidden space-y-4 md:space-y-6">
        <!-- Varshaphal year selection + summary details -->
        <div id="card-vpbox" class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Varshaphal</h2>
            <div id="vp-box" class="mb-3"></div>
            <div id="vp-summary" class="text-sm"></div>
        </div>

        <!-- ROW 1: Varsha (Annual) chart on the LEFT + Varshaphal prediction
             panel on the RIGHT (rules coming later). -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div id="vp-chart-cell"></div>
            <div class="bg-white rounded-lg shadow p-4 flex flex-col" id="varsha-pred-card">
                <div class="l2-picker" style="margin-bottom:10px">
                    <span class="pick-tag">Varshaphal Prediction ▾</span>
                    <select id="vp-pred-type" class="l2-select">
                        <option value="saham">सहम — Sahams (50)</option>
                    </select>
                </div>
                <?php
                    $sah = $view['saham'] ?? null;
                    $sahams = ($sah && !empty($sah['sahams'])) ? $sah['sahams'] : [];
                    if ($sahams !== []):
                        $vpTz = (float) ($sah['tz'] ?? 0.0);
                        $activeLord = (string) ($sah['active_lord'] ?? '');
                        $sTone = static fn(string $t): string => $t === 'pos' ? 'gc-shubh' : ($t === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
                        $fmtJd = static fn($jd) => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $jd, $vpTz);
                        // duplicate-formula detection: same longitude -> "समान सूत्र"
                        $lonCount = [];
                        foreach ($sahams as $s) { $k = (string) $s['lon']; $lonCount[$k] = ($lonCount[$k] ?? 0) + 1; }
                        $related = array_values(array_filter($sahams, static fn($s) => !empty($s['related'])));
                        // Default view: "Active Saham" (all sahams tied to the running Mudda-dasha).
                        // Falls back to "All Saham" only when no saham is active this year.
                        $defaultOpt = $related !== [] ? 'active' : 'all';
                ?>
                <!-- Active Mudda mahadasha + its related sahams -->
                <div class="saham-active">
                    <div><b>सक्रिय मुद्दा-दशा:</b> <span style="color:<?= $pcolor($activeLord) ?>;font-weight:700"><?= $h($grahaHi[$activeLord] ?? $activeLord) ?></span>
                        <span class="text-xs text-gray-500">(<?= ($sah['is_day'] ?? true) ? 'दिन-वर्षप्रवेश' : 'रात्रि-वर्षप्रवेश' ?>)</span></div>
                    <?php if ($related !== []): ?>
                    <div class="saham-related">इससे संबंधित सहम:
                        <?php foreach ($related as $r): ?>
                            <button type="button" class="saham-chip <?= $sTone($r['tone']) ?>" data-goto="<?= $h($r['key']) ?>"><?= (int) $r['seq'] ?>. <?= $h($r['name_hi']) ?> <span class="saham-why">(<?= $h($r['related_why']) ?>)</span></button>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-xs text-gray-500">इस ग्रह से सीधे संबंधित कोई सहम नहीं — नीचे से कोई भी सहम चुनें।</div>
                    <?php endif; ?>
                </div>

                <!-- Numbered, scrollable saham selector -->
                <div class="pred-picker" style="margin-top:8px">
                    <label class="pred-picker-label" for="saham-select">सहम चुनें</label>
                    <select id="saham-select" class="pred-inline-select" size="1">
                        <option value="active"<?= $defaultOpt === 'active' ? ' selected' : '' ?>>● सक्रिय सहम (मुद्दा-दशा अनुसार)<?= $related !== [] ? ' — ' . count($related) : '' ?></option>
                        <option value="all"<?= $defaultOpt === 'all' ? ' selected' : '' ?>>सभी सहम (All Saham)</option>
                        <?php foreach ($sahams as $s): ?>
                            <option value="<?= $h($s['key']) ?>"><?= (int) $s['seq'] ?>. <?= $h($s['name_hi']) ?> — <?= $h($s['verdict_hi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Saham detail cards (only the selected one shows) -->
                <div id="saham-detail-pane" class="overflow-y-auto pr-1" style="max-height:420px">
                    <?php foreach ($sahams as $s): $isDup = ($lonCount[(string) $s['lon']] ?? 0) > 1;
                        $isActive = !empty($s['related']);
                        $showInit = $defaultOpt === 'all' || ($defaultOpt === 'active' && $isActive);
                    ?>
                    <div class="saham-card<?= $showInit ? '' : ' hidden' ?>" data-saham="<?= $h($s['key']) ?>" data-active="<?= $isActive ? '1' : '0' ?>">
                        <div class="saham-card-head">
                            <span class="saham-name"><?= (int) $s['seq'] ?>. <?= $h($s['name_hi']) ?></span>
                            <span class="gc-chip <?= $sTone($s['tone']) ?>"><?= $h($s['verdict_hi']) ?></span>
                            <?php if (!empty($s['related'])): ?><span class="saham-tag rel">सक्रिय</span><?php endif; ?>
                            <?php if ($isDup): ?><span class="saham-tag dup">समान सूत्र</span><?php endif; ?>
                        </div>
                        <?php if (!empty($s['signifies'])): ?><div class="saham-signifies"><?= $h($s['signifies']) ?></div><?php endif; ?>
                        <div class="saham-pos">राशि-अंश: <b><?= $h($s['rashi_hi']) ?> <?= $h($s['deg']) ?></b> · वर्ष-भाव: <b><?= (int) $s['house'] ?></b> · सहमेश: <b style="color:<?= $pcolor($s['sahamesh']) ?>"><?= $h($s['sahamesh_hi']) ?></b></div>
                        <?php $f = $s['facts']; ?>
                        <div class="saham-facts">सहमेश <?= $h($s['sahamesh_hi']) ?> — षड्बल <?= $h((string) $f['shadbala']) ?> (<?= $f['pass'] ? 'पूर्ण' : 'अपूर्ण' ?>)<?= $f['debil'] ? ' · नीच' : '' ?><?= $f['combust'] >= 40 ? ' · अस्त ' . (int) $f['combust'] . '%' : '' ?></div>
                        <?php foreach (($s['phal'] ?? []) as $ph): ?>
                            <div class="saham-phal">● <?= $h($ph) ?></div>
                        <?php endforeach; ?>
                        <?php if (!empty($s['timing_mudda'])): $tm = $s['timing_mudda']; ?>
                        <div class="saham-timing">🕒 समय: सहमेश <?= $h($s['sahamesh_hi']) ?> की मुद्दा-दशा — <b><?= $h($fmtJd($tm['start_jd'])) ?></b> से <b><?= $h($fmtJd($tm['end_jd'])) ?></b></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="gochar-pred-soon">
                    <div class="gps-icon">🔮</div>
                    <div class="gps-title">सहम उपलब्ध नहीं</div>
                    <div class="gps-sub"><?= $h((string) ($sah['error'] ?? 'वर्ष कुंडली गणना के बाद 50 सहम यहाँ दिखेंगे।')) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ROW 2: Mudda Dasha on the LEFT + annual positions detail on the RIGHT. -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
            <div id="vp-mudda-cell"></div>
    <?php if ($vp !== null): ?>
            <div id="card-varshadet" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
                <h2 class="font-semibold mb-2">Varshaphal (Annual Chart) — year <?= (int) $in['forYear'] ?></h2>
                <div>Varsha Lagna: <b><?= $h($vp['varsha_chart']['ascendant']['formatted']) ?></b> (lord <?= $h($vp['varsha_lagna']['lord']) ?>)
                    · Muntha: <?= $h($vp['muntha']['sign']) ?> (lord <?= $h($vp['muntha']['lord']) ?>)
                    · Age <?= (int) $vp['age_completed'] ?></div>
                <table class="w-full mt-2">
                    <thead><tr class="text-left border-b"><th class="py-1 pr-3">Planet</th><th class="pr-3">Annual position</th><th>House</th></tr></thead>
                    <tbody>
                    <?php foreach ($vp['varsha_chart']['planets'] as $name => $p): ?>
                        <tr class="border-b border-gray-100"><td class="py-1 pr-3 font-semibold" style="color: <?= $pcolor($name) ?>"><?= $h($name) ?></td>
                            <td class="pr-3"><?= $h($p['formatted']) ?></td><td><?= (int) $p['house'] ?><?= $p['retro'] ? ' <sup style="color:#b91c1c;font-size:0.9em">&#174;</sup>' : '' ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
    <?php else: ?>
            <div id="card-varshadet" class="bg-white rounded-lg shadow p-4 text-sm text-gray-400 italic">Annual positions not available.</div>
    <?php endif; ?>
        </div>
        </div>

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
        <p class="text-xs text-gray-500 mb-2">Sthana, Dig and Naisargika match Parashara's Light to ±0.01. Kaala, Chesta and Drig follow PL's method (Chesta/Drig are the most program-specific components); the per-planet Total/Rupas/Ratio are close to PL.</p>
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
<?php $asset = static fn(string $p): string => \AutoBusiness\Core\Asset::url($p); ?>
<script src="<?= $h($asset('/assets/js/northchart.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/dasha.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/citysearch.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/gochar.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/varshaphal.js')) ?>"></script>
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

  var bindFmt = function (sel, fn) {
    var el = document.querySelector(sel);
    if (!el) { return; }
    el.addEventListener('blur', function () {
      if (el.value.trim()) { el.value = fn(el.value); }
    });
  };
  bindFmt('[name="date"]', normDate);
  bindFmt('[name="time"]', normTime);

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

    var onChange = function () { load(); loadEngine(); };
    mSel.addEventListener('change', onChange);
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
  // Saham selector (Varshaphal panel): "active" shows every saham tied to the
  // running Mudda-dasha, "all" shows all 50, otherwise a single saham by key.
  (function () {
    var sel = document.getElementById('saham-select');
    if (!sel) { return; }
    var cards = document.querySelectorAll('#saham-detail-pane .saham-card');
    var pane = document.getElementById('saham-detail-pane');
    function apply() {
      var v = sel.value;
      cards.forEach(function (d) {
        var show = v === 'all'
          || (v === 'active' ? d.getAttribute('data-active') === '1'
                             : d.getAttribute('data-saham') === v);
        d.classList.toggle('hidden', !show);
      });
      if (pane) { pane.scrollTop = 0; }
    }
    sel.addEventListener('change', apply);
    // Related-saham chips jump straight to that single saham.
    document.querySelectorAll('.saham-chip[data-goto]').forEach(function (b) {
      b.addEventListener('click', function () {
        sel.value = b.getAttribute('data-goto');
        apply();
      });
    });
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
        fallback: { lat: (window.AB_BIRTH && window.AB_BIRTH.lat) || 28.61, lon: (window.AB_BIRTH && window.AB_BIRTH.lon) || 77.21, tz: window.AB_TZ }
      });
    }
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

  // Chart panel frame: same renderer + payloads as the section charts (protected).
  function renderChartFrame(key) {
    var frame = document.getElementById('chart-frame');
    if (!frame || !window.ABChart) { return; }
    if (key === 'gochar') {
      var g = window.AB_GOCHAR || {};
      if (!g.transits || !g.ascendant) { return; }
      var ABBR = { Sun:'Su', Moon:'Mo', Mars:'Ma', Mercury:'Me', Jupiter:'Ju', Venus:'Ve', Saturn:'Sa', Rahu:'Ra', Ketu:'Ke' };
      var planets = Object.keys(g.transits).map(function (n) {
        var t = g.transits[n];
        return { abbr: ABBR[n] || n.slice(0, 2), sign: t.sign_index, deg: Math.floor(t.deg), retro: !!t.retro };
      });
      ABChart.renderNorth(frame, { asc_sign: g.ascendant.sign_index, planets: planets }, { showDeg: true });
      return;
    }
    if (key === 'varsha') {
      if (window.AB_VARSHAN && window.AB_VARSHAN.planets) {
        ABChart.renderNorth(frame, window.AB_VARSHAN, { showDeg: true });
      }
      return;
    }
    if (window.AB_VARGAS && window.AB_VARGAS[key]) {
      ABChart.renderNorth(frame, window.AB_VARGAS[key], {
        title: null, showDeg: true, big: key === 'D1',
        outer: key === 'D1' ? (window.AB_HOUSES || null) : null
      });
    }
  }
  var chartSel = document.getElementById('chart-select');
  if (chartSel) {
    chartSel.addEventListener('change', function () {
      renderChartFrame(this.value);
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

  // Side-menu section switching: home = three-panel; others span the two panels.
  var FULL_SECTIONS = ['sec-profile', 'sec-custom', 'sec-grah', 'sec-varga', 'sec-dasha', 'sec-bal', 'sec-gochar', 'sec-varsha'];
  function showSection(key, focusPred) {
    var homeMode = key === 'home';
    var customMode = key === 'custom';
    var cp = document.getElementById('chart-panel');
    var pp = document.getElementById('pred-panel');
    if (cp) cp.classList.toggle('hidden', !homeMode);
    if (pp) pp.classList.toggle('hidden', !homeMode);
    // Custom Screen: full-width (hide the side menu) + show the "Birth Chart" jump.
    var grid = document.getElementById('sec-home');
    if (grid) { grid.classList.toggle('custom-active', customMode); }
    var back = document.getElementById('cs-back');
    if (back) { back.style.display = customMode ? '' : 'none'; }
    if (customMode && window.ABCustom) { window.ABCustom.ensure(); }
    FULL_SECTIONS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.toggle('hidden', id !== 'sec-' + key);
    });
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

  // ---- Custom Screen: user drops chart / prediction panels into a grid ----
  (function () {
    var grid = document.getElementById('custom-grid');
    if (!grid) { return; }
    var START_SLOTS = 6;
    var PRED_LABELS = { dasha: 'Dasha Phal', bhavesh: 'Bhavesh Phal', grah: 'Graha Phal',
      bhav: 'Bhava Phaladesh', karak: 'Karaka Phal', yoga: 'Yoga' };
    var PRED_ORDER = ['dasha', 'bhavesh', 'grah', 'bhav', 'karak', 'yoga'];

    function chartOptions() {
      var out = [], V = window.AB_VARGAS || {};
      Object.keys(V).forEach(function (k) { if (V[k] && V[k].planets) { out.push({ key: k, label: k + ' — ' + (V[k].label || k) }); } });
      if (window.AB_GOCHAR && window.AB_GOCHAR.transits) { out.push({ key: 'gochar', label: 'Gochar (Transit)' }); }
      if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { out.push({ key: 'varsha', label: 'Varsha Kundali' }); }
      return out;
    }
    function predOptions() {
      return PRED_ORDER.filter(function (p) { return document.querySelector('#pred-scroll .pred-view[data-pred="' + p + '"]'); })
        .map(function (p) { return { key: p, label: PRED_LABELS[p] }; });
    }
    function renderChart(body, key) {
      body.innerHTML = ''; var host = document.createElement('div'); host.className = 'w-full'; body.appendChild(host);
      if (!window.ABChart) { return; }
      if (key === 'gochar') {
        var g = window.AB_GOCHAR || {};
        if (!g.transits || !g.ascendant) { host.innerHTML = '<div class="text-gray-400 italic">Gochar not available.</div>'; return; }
        var AB = { Sun: 'Su', Moon: 'Mo', Mars: 'Ma', Mercury: 'Me', Jupiter: 'Ju', Venus: 'Ve', Saturn: 'Sa', Rahu: 'Ra', Ketu: 'Ke' };
        var pls = Object.keys(g.transits).map(function (n) { var t = g.transits[n]; return { abbr: AB[n] || n.slice(0, 2), sign: t.sign_index, deg: Math.floor(t.deg), retro: !!t.retro }; });
        window.ABChart.renderNorth(host, { asc_sign: g.ascendant.sign_index, planets: pls }, { showDeg: true });
      } else if (key === 'varsha') {
        if (window.AB_VARSHAN && window.AB_VARSHAN.planets) { window.ABChart.renderNorth(host, window.AB_VARSHAN, { showDeg: true }); }
      } else {
        var V = window.AB_VARGAS || {};
        if (V[key]) { window.ABChart.renderNorth(host, V[key], { showDeg: true, big: key === 'D1', outer: key === 'D1' ? (window.AB_HOUSES || null) : null }); }
      }
    }
    function renderPred(body, pred) {
      var src = document.querySelector('#pred-scroll .pred-view[data-pred="' + pred + '"]');
      if (!src) { body.innerHTML = '<div class="text-gray-400 italic">Not available.</div>'; return; }
      var clone = src.cloneNode(true);
      clone.classList.remove('hidden');
      clone.querySelectorAll('.pred-picker').forEach(function (e) { e.remove(); });      // inner dropdown not needed
      clone.querySelectorAll('.hidden').forEach(function (e) { e.classList.remove('hidden'); }); // show all details
      clone.querySelectorAll('[id]').forEach(function (e) { e.removeAttribute('id'); });   // avoid duplicate ids
      body.innerHTML = ''; body.appendChild(clone);
    }

    function emptySlot(slot) {
      slot.className = 'cs-slot cs-empty';
      slot.removeAttribute('data-kind'); slot.removeAttribute('data-key');
      slot.innerHTML = '<div style="text-align:center"><div class="cs-plus">+</div><div class="cs-plus-lbl">Add panel</div></div>';
      slot.onclick = function () { openPicker(slot); };
    }
    function fillSlot(slot, kind, key) {
      slot.className = 'cs-slot'; slot.onclick = null; slot.innerHTML = '';
      var head = document.createElement('div'); head.className = 'cs-head';
      var sel = document.createElement('select'); sel.className = 'cs-sel';
      (kind === 'chart' ? chartOptions() : predOptions()).forEach(function (o) {
        var op = document.createElement('option'); op.value = o.key; op.textContent = o.label;
        if (o.key === key) { op.selected = true; } sel.appendChild(op);
      });
      var rep = document.createElement('button'); rep.className = 'cs-iconbtn'; rep.title = 'Replace panel'; rep.innerHTML = '⟳';
      var del = document.createElement('button'); del.className = 'cs-iconbtn'; del.title = 'Remove panel'; del.innerHTML = '✕';
      head.appendChild(sel); head.appendChild(rep); head.appendChild(del);
      var body = document.createElement('div'); body.className = 'cs-body';
      slot.appendChild(head); slot.appendChild(body);
      slot.dataset.kind = kind;
      function draw() { slot.dataset.key = sel.value; if (kind === 'chart') { renderChart(body, sel.value); } else { renderPred(body, sel.value); } }
      sel.addEventListener('change', draw);
      rep.addEventListener('click', function (e) { e.stopPropagation(); openPicker(slot); });
      del.addEventListener('click', function (e) { e.stopPropagation(); emptySlot(slot); });
      draw();
    }

    var modal = document.getElementById('cs-picker'), targetSlot = null;
    function openPicker(slot) {
      targetSlot = slot; if (!modal) { return; }
      var oc = document.getElementById('cs-opts-chart'), op = document.getElementById('cs-opts-pred');
      oc.innerHTML = ''; op.innerHTML = '';
      chartOptions().forEach(function (o) { var b = document.createElement('button'); b.className = 'cs-opt'; b.textContent = o.label; b.onclick = function () { fillSlot(targetSlot, 'chart', o.key); closePicker(); }; oc.appendChild(b); });
      predOptions().forEach(function (o) { var b = document.createElement('button'); b.className = 'cs-opt'; b.textContent = o.label; b.onclick = function () { fillSlot(targetSlot, 'pred', o.key); closePicker(); }; op.appendChild(b); });
      modal.classList.remove('hidden');
    }
    function closePicker() { if (modal) { modal.classList.add('hidden'); } }
    if (modal) {
      modal.addEventListener('click', function (e) { if (e.target === modal) { closePicker(); } });
      var mx = modal.querySelector('.cs-modal-x'); if (mx) { mx.addEventListener('click', closePicker); }
    }

    var built = false;
    function ensure() { if (built) { return; } built = true; for (var i = 0; i < START_SLOTS; i++) { var s = document.createElement('div'); grid.appendChild(s); emptySlot(s); } }
    var add = document.getElementById('cs-add'); if (add) { add.addEventListener('click', function () { var s = document.createElement('div'); grid.appendChild(s); emptySlot(s); }); }
    var rst = document.getElementById('cs-reset'); if (rst) { rst.addEventListener('click', function () { grid.innerHTML = ''; built = false; ensure(); }); }
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

  // House Details "Copy" button → copies the plain-text summary of all houses.
  var hdCopy = document.getElementById('hd-copy');
  if (hdCopy) {
    hdCopy.addEventListener('click', function () {
      var src = document.getElementById('hd-copy-text');
      var text = src ? src.textContent : '';
      var done = function () { hdCopy.textContent = 'Copied!'; setTimeout(function () { hdCopy.textContent = 'Copy'; }, 1500); };
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text); done(); });
      } else { fallbackCopy(text); done(); }
    });
  }
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
</body>
</html>
