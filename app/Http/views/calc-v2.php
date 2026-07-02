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
        /* PROTECTED: the chart SVGs keep their pre-redesign font stack so the
           rendered chart stays pixel-identical (rings, planets, markers). */
        svg { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        /* Controls: 6px radius + sindoor focus ring (token spec). */
        input, select, textarea { border-radius: 6px; }
        input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: none; border-color: var(--sindoor);
            box-shadow: 0 0 0 3px var(--sindoor-soft);
        }

        /* ---- Top bar (sticky) ---- */
        .topbar { position: sticky; top: 0; z-index: 50; background: var(--header-bg); color: #F7F3EA; }
        .topbar-inner { max-width: 1400px; margin: 0 auto; padding: 10px 16px;
            display: flex; align-items: center; gap: 12px 20px; flex-wrap: wrap; }
        .topbar .brand { font-family: 'Martel', serif; font-weight: 800; font-size: 1.25rem; line-height: 1.3; }
        .topbar .meta { margin-left: auto; display: flex; align-items: center; gap: 8px 16px;
            flex-wrap: wrap; font-size: .85rem; color: #C9C2B4; }
        .topbar .meta b { color: #FFFFFF; font-weight: 600; }
        .topbar select { background: #2A3742; color: #F7F3EA; border: 1px solid #3B4854;
            padding: 6px 10px; min-height: 44px; font-size: .85rem; }
        .btn-sindoor { background: var(--sindoor); color: #fff; font-weight: 600; font-size: .9rem;
            padding: 6px 16px; min-height: 44px; border-radius: 6px; display: inline-flex; align-items: center; }
        .btn-sindoor:hover { filter: brightness(1.1); }

        /* ---- Overview tiles ---- */
        .ov-tiles { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        @media (max-width: 699px) { .ov-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        .ov-tile { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); padding: 10px 14px; }
        .ov-label { font-size: 12px; text-transform: uppercase; letter-spacing: .5px; color: var(--ink-soft); }
        .ov-value { font-size: 1.15rem; font-weight: 700; color: var(--ink); }
        .ov-value.acc-dasha { color: var(--sindoor); }
        .ov-value.acc-yoga  { color: var(--shubh); }
        .ov-sub { font-size: .78rem; color: var(--ink-soft); }

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
        .l2-menu button.active { background: var(--sindoor-soft); border-left-color: var(--sindoor);
            color: var(--sindoor); font-weight: 700; }
        .chart-frame { width: 100%; margin: 0 auto; }
        .l2-select { width: 100%; border: 1px solid var(--line); background: var(--card);
            color: var(--ink); padding: 8px 10px; min-height: 44px; font-weight: 600; margin-bottom: 8px; }
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
        .pred-expand { width: 44px; height: 44px; min-height: 44px; flex: 0 0 auto;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid var(--line); border-radius: 6px; color: var(--sindoor);
            font-size: 1.15rem; font-weight: 700; background: var(--card); }
        .pred-expand:hover { background: var(--sindoor-soft); }
        /* Prediction body copy reads at 12px base (bumps to 14px when expanded). */
        .pred-view { font-size: 12px; }
        .pred-view .whitespace-pre-line, .pred-view li { line-height: 1.65; }
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
        .yoga-title { font-weight: 700; font-size: 13px; color: var(--ink); margin-bottom: 2px; }
        .yoga-why { color: var(--ink-soft); }
        .yoga-why b { color: var(--ink-soft); }
        .yoga-res b { color: var(--shubh); }
        .yoga-bad .yoga-res b { color: var(--ashubh); }
        /* भावेश फल cards */
        .bh-card { border: 1px solid var(--line); border-radius: 8px; padding: 10px 12px;
            margin-bottom: 12px; background: var(--card); }
        .bh-title { font-weight: 700; font-size: 13px; margin-bottom: 4px; }

        /* ---- Expand / collapse reading mode (Phase 5) ---- */
        #chart-panel, #pred-panel { transition: opacity .18s ease; }
        @media (prefers-reduced-motion: reduce) {
            #chart-panel, #pred-panel { transition: none; }
        }
        @media (min-width: 1100px) {
            .pred-expanded #chart-panel { display: none; }
            .pred-expanded #pred-panel { grid-column: 2 / 4; }
            /* Reading mode: bigger copy flowing in two columns. */
            .pred-expanded .pred-view { font-size: 14px; }
            .pred-expanded .pred-view:not(.hidden) { column-count: 2; column-gap: 32px; }
            .pred-expanded .pred-view:not(.hidden) > div { break-inside: avoid; }
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
        #planet-phala-card .pp-sec  { font-size: 1.15rem; font-weight: 700; }
        #planet-phala-card .pp-sub  { font-size: 1.1rem;  font-weight: 700; }
    </style>
</head>
<body class="text-gray-900">

<!-- ============ TOP BAR (layout v2, Phase 1) ============ -->
<header class="topbar">
    <div class="topbar-inner">
        <div class="brand">Analysis of Karma</div>
        <div class="meta">
            <span><b><?= $in['name'] !== '' ? $h($in['name']) : '—' ?></b></span>
            <span><?= $h($in['date']) ?>, <?= $h($in['time']) ?></span>
            <span><?= $h($pobTop) ?></span>
            <select id="topbar-lang" aria-label="भाषा / Language">
                <option value="hi" <?= $phalaLang === 'hi' ? 'selected' : '' ?>>हिन्दी</option>
                <option value="en" <?= $phalaLang === 'en' ? 'selected' : '' ?>>English</option>
            </select>
            <button type="button" id="new-kundli" class="btn-sindoor">नई कुंडली</button>
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

<div class="l2-wrap mx-auto space-y-4 p-3 sm:p-4">

    <!-- ============ OVERVIEW TILES (layout v2, Phase 1) ============ -->
    <?php if ($chart !== null): ?>
    <?php
        $ovLagna = (string) ($chart['ascendant']['sign'] ?? '');
        $ovMoon  = (string) ($chart['planets']['Moon']['sign'] ?? '');
        $ovMaha  = (string) ($dashaNow['maha']['lord'] ?? '');
        $ovAntar = (string) ($dashaNow['antar']['lord'] ?? '');
        $ovPrat  = (string) ($dashaNow['pratyantar']['lord'] ?? '');
    ?>
    <div class="ov-tiles">
        <div class="ov-tile">
            <div class="ov-label">लग्न</div>
            <div class="ov-value"><?= $h($rashiHi[$ovLagna] ?? $ovLagna) ?></div>
            <div class="ov-sub"><?= $h($ovLagna) ?> · <?= $h((string) ($chart['ascendant']['formatted'] ?? '')) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">राशि</div>
            <div class="ov-value"><?= $h($rashiHi[$ovMoon] ?? $ovMoon) ?></div>
            <div class="ov-sub">चंद्र राशि · <?= $h($ovMoon) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">चालू दशा</div>
            <div class="ov-value acc-dasha"><?= $h(($grahaHi[$ovMaha] ?? $ovMaha) . ($ovAntar !== '' ? ' – ' . ($grahaHi[$ovAntar] ?? $ovAntar) : '')) ?></div>
            <div class="ov-sub"><?= $ovPrat !== '' ? 'प्रत्यंतर: ' . $h($grahaHi[$ovPrat] ?? $ovPrat) : '&nbsp;' ?></div>
        </div>
        <?php
            $ovYogas = $view['yogas'] ?? [];
            $ovYGood = array_values(array_filter($ovYogas, static fn($y) => !empty($y['good'])));
        ?>
        <div class="ov-tile">
            <div class="ov-label">योग</div>
            <div class="ov-value acc-yoga"><?= $ovYGood !== [] ? count($ovYGood) . ' शुभ योग' : '—' ?></div>
            <div class="ov-sub"><?= $ovYGood !== []
                ? $h(implode(' · ', array_slice(array_map(static fn($y) => (string) $y['name'], $ovYGood), 0, 2)))
                : 'कोई प्रमुख योग नहीं' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Birth-details form: collapsed once a chart is shown; नई कुंडली reopens it. -->
    <form id="birth-form" method="get" action="/calc" class="l2-card p-4 text-sm<?= ($chart !== null && $view['error'] === null) ? ' hidden' : '' ?>">
        <input type="hidden" name="layout" value="new">
        <h2 class="font-semibold mb-3 text-gray-700">Chart Calculation Details</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <label class="flex flex-col gap-1"><span class="text-gray-500">Name</span>
                <input name="name" value="<?= $h($in['name']) ?>" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Gender</span>
                <select name="gender" class="border rounded px-2 py-1 bg-white">
                    <?php foreach (['' => '—', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'] as $gv => $gl): ?>
                        <option value="<?= $h($gv) ?>" <?= $in['gender'] === $gv ? 'selected' : '' ?>><?= $h($gl) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Date (DD-MM-YYYY)</span>
                <input name="date" value="<?= $h($in['date']) ?>" placeholder="DD-MM-YYYY or DD MM YYYY" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Time (HH:MM)</span>
                <input name="time" value="<?= $h($in['time']) ?>" placeholder="HH:MM or HH MM" class="border rounded px-2 py-1"></label>

            <label class="flex flex-col gap-1 relative sm:col-span-2 lg:col-span-3"><span class="text-gray-500">Place (search city, state or country — fills lat/lon/timezone)</span>
                <input id="b-place" name="place" value="<?= $h($in['place']) ?>" type="text" autocomplete="off" placeholder="Type a city, e.g. Moga or London…" class="border rounded px-2 py-1">
                <div id="b-place-results" class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border rounded shadow max-h-60 overflow-y-auto hidden"></div></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Ayanamsa</span>
                <select name="ayanamsa" class="border rounded px-2 py-1 bg-white">
                    <?php foreach (['lahiri' => 'Lahiri (Chitrapaksha)', 'raman' => 'B.V. Raman', 'kp' => 'KP (Krishnamurti)', 'fagan_bradley' => 'Fagan-Bradley'] as $av => $al): ?>
                        <option value="<?= $h($av) ?>" <?= $in['ayanamsa'] === $av ? 'selected' : '' ?>><?= $h($al) ?></option>
                    <?php endforeach; ?>
                </select></label>

            <label class="flex flex-col gap-1"><span class="text-gray-500">Latitude</span>
                <input id="b-lat" name="lat" value="<?= $h($in['latIn']) ?>" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Longitude</span>
                <input id="b-lon" name="lon" value="<?= $h($in['lonIn']) ?>" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Timezone (east +)</span>
                <input id="b-tz" name="tz" value="<?= $h($in['tzIn']) ?>" class="border rounded px-2 py-1"></label>
            <div class="flex items-end">
                <button class="bg-blue-600 text-white rounded px-4 py-2 font-semibold w-full">Calculate</button>
            </div>
        </div>
        <p class="text-xs text-gray-500 mt-2">Search any city worldwide to fill lat/lon/timezone, or type lat/lon directly (also accept DMS, e.g. 30N48'00). Timezone is the place's offset on the birth date.</p>
    </form>

    <?php if ($view['error'] !== null): ?>
        <div class="bg-red-100 text-red-800 rounded p-3 text-sm">Error: <?= $h($view['error']) ?></div>
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
        <nav id="side-menu" class="l2-menu l2-card" aria-label="मुख्य अनुभाग">
            <button type="button" data-sec="home" class="active">जन्म कुंडली</button>
            <button type="button" data-sec="grah">ग्रह स्थिति</button>
            <button type="button" data-sec="varga">वर्ग कुंडली</button>
            <button type="button" data-sec="dasha">दशा</button>
            <button type="button" data-sec="bal">बल</button>
            <button type="button" data-sec="home" data-focus="pred">फलादेश</button>
        </nav>

        <!-- Chart panel (middle column) -->
        <?php
            // Chart selector: every computed varga + गोचर + वर्ष कुंडली.
            $vargaHi = [
                'D1' => 'जन्म कुंडली', 'D2' => 'होरा', 'D3' => 'द्रेष्काण', 'D4' => 'चतुर्थांश',
                'D7' => 'सप्तमांश', 'D9' => 'नवमांश', 'D10' => 'दशमांश', 'D12' => 'द्वादशांश',
                'D16' => 'षोडशांश', 'D20' => 'विंशांश', 'D24' => 'चतुर्विंशांश', 'D27' => 'सप्तविंशांश',
                'D30' => 'त्रिंशांश', 'D40' => 'खवेदांश', 'D60' => 'षष्ट्यंश',
            ];
        ?>
        <section id="chart-panel" class="l2-card l2-panel" aria-label="कुंडली चार्ट">
            <select id="chart-select" class="l2-select" aria-label="कुंडली चुनें">
                <?php foreach ($vargaHi as $vk => $vlbl): if (!isset($vargas[$vk])) { continue; } ?>
                    <option value="<?= $h($vk) ?>"><?= $h($vk) ?> — <?= $h($vlbl) ?></option>
                <?php endforeach; ?>
                <?php if ($gochar !== null): ?><option value="gochar">गोचर</option><?php endif; ?>
                <?php if (($view['varshaNorth'] ?? null) !== null): ?><option value="varsha">वर्ष कुंडली (<?= (int) $in['forYear'] ?>)</option><?php endif; ?>
            </select>
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
                <select id="pred-select" class="l2-select" aria-label="फलादेश चुनें" style="margin-bottom:0; flex:1">
                    <option value="dasha">फलादेश — दशा फल</option>
                    <option value="bhavesh">फलादेश — भावेश फल</option>
                    <option value="grah">फलादेश — ग्रह फल</option>
                    <option value="bhav">फलादेश — भाव फलादेश</option>
                    <option value="karak">फलादेश — कारक फल</option>
                    <option value="yoga">फलादेश — योग</option>
                </select>
                <button type="button" id="pred-expand" class="pred-expand" aria-label="विस्तृत करें" title="विस्तृत करें">⤢</button>
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
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Mahadasha</span>
                <select id="phala-maha" class="border rounded px-2 py-1"><?= $pOpt((string) $phala['maha']) ?></select></label>
            <label class="flex flex-col gap-1"><span class="text-xs text-gray-500">Antardasha</span>
                <select id="phala-antar" class="border rounded px-2 py-1"><?= $pOpt((string) $phala['antar']) ?></select></label>
            <span class="text-xs text-gray-400">Running now: <b><?= $h((string) $phala['maha']) ?></b> / <b><?= $h((string) $phala['antar']) ?></b></span>
            <button type="button" class="phala-toggle ml-auto text-xs bg-gray-100 hover:bg-gray-200 border rounded px-2 py-1 font-semibold" data-target="dasha-body" aria-expanded="true">Collapse ▴</button>
        </div>
        <div id="dasha-body">
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
            <!-- Two columns: pick a planet (left) -> its prediction (right, scrolls). -->
            <div class="grid grid-cols-1 sm:grid-cols-[170px_1fr] gap-4" id="planet-grid">
                <div class="sm:border-r sm:pr-2 overflow-y-auto" style="max-height:460px">
                    <div class="flex sm:flex-col flex-wrap gap-1">
                    <?php foreach ($pp['planets'] as $i => $row): $pl = $row['planet']; ?>
                        <button type="button" class="planet-pick text-left px-2 py-1 rounded border border-transparent hover:bg-gray-100 <?= $i === 0 ? 'bg-blue-50 border-blue-200 font-semibold' : '' ?>" data-planet="<?= $h($pl) ?>">
                            <span style="color:<?= $pcolor($pl) ?>"><?= $h($pl) ?></span>
                            <span class="text-gray-400 text-xs">(<?= $h($ppHi[$pl] ?? '') ?>)</span>
                        </button>
                    <?php endforeach; ?>
                    </div>
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
            <div class="grid grid-cols-1 sm:grid-cols-[160px_1fr] gap-4" id="house-grid">
                <div class="sm:border-r sm:pr-2 overflow-y-auto" style="max-height:480px">
                    <div class="flex sm:flex-col flex-wrap gap-1">
                        <button type="button" class="house-pick text-left px-2 py-1 rounded border border-transparent hover:bg-gray-100" data-house="all">सभी भाव (All 12)</button>
                        <?php foreach ($hp['houses'] as $hh => $hd): ?>
                            <button type="button" class="house-pick text-left px-2 py-1 rounded border border-transparent hover:bg-gray-100 <?= $hh === 1 ? 'bg-blue-50 border-blue-200 font-semibold' : '' ?>" data-house="<?= (int) $hh ?>">
                                <?= $ord2((int) $hh) ?> House — <?= $h((string) $hd['rashi_hi']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="overflow-y-auto pr-1" style="max-height:480px" id="house-detail-pane">
                    <?php foreach ($hp['houses'] as $hh => $hd): ?>
                    <div class="house-detail<?= $hh === 1 ? '' : ' hidden' ?> mb-4" data-house="<?= (int) $hh ?>">
                        <div class="font-semibold text-gray-800 mb-1"><?= $ord2((int) $hh) ?> House — <?= $h((string) $hd['rashi_hi']) ?> (<?= $h((string) $hd['rashi']) ?>)</div>
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
                foreach ($k['paired_houses'] as $ph) {
                    if (!isset($hpHouses[$ph])) { continue; }
                    $kCopyLines[] = 'भाव फल — ' . $ord2((int) $ph) . ' House (' . $hpHouses[$ph]['rashi_hi'] . '):';
                    $kCopyLines[] = $hpHouses[$ph]['intro'];
                    foreach ($hpHouses[$ph]['lines'] as $ln) { $kCopyLines[] = '• ' . $ln; }
                }
                $kCopyLines[] = 'कारक विश्लेषण:';
                foreach ($k['karaka_lines'] as $l) { $kCopyLines[] = '• ' . $l['sentence']; }
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
            <div class="grid grid-cols-1 sm:grid-cols-[200px_1fr] gap-4" id="karaka-grid">
                <div class="sm:border-r sm:pr-2 overflow-y-auto" style="max-height:520px">
                    <div class="flex sm:flex-col flex-wrap gap-1">
                        <?php foreach ($kp['karakas'] as $i => $k): ?>
                            <button type="button" class="karaka-pick text-left px-2 py-1 rounded border border-transparent hover:bg-gray-100 <?= $i === 0 ? 'bg-blue-50 border-blue-200 font-semibold' : '' ?>" data-karaka="<?= $h((string) $k['planet']) ?>">
                                <?= $h((string) $k['title']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="overflow-y-auto pr-1" style="max-height:520px" id="karaka-detail-pane">
                    <?php foreach ($kp['karakas'] as $i => $k): ?>
                    <div class="karaka-detail<?= $i === 0 ? '' : ' hidden' ?>" data-karaka="<?= $h((string) $k['planet']) ?>">
                        <div class="font-bold text-gray-800" style="font-size:1.25rem"><?= $h((string) $k['title']) ?></div>
                        <div class="text-xs text-gray-500 mb-2">कारक: <?= $h((string) $k['signifies']) ?></div>

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
                        <ul class="list-disc pl-5 space-y-1 text-gray-800" style="font-size:1.02rem; line-height:1.6">
                            <?php foreach ($k['karaka_lines'] as $l): ?><li><?= $h((string) $l['sentence']) ?></li><?php endforeach; ?>
                        </ul>
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
    <div class="bg-white rounded-lg shadow p-4 text-sm">
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
    <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
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
    <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">D1 (Rasi) — Planetary Positions</h2>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">Planet</th><th class="pr-3">Position</th><th class="pr-3">Placement</th><th class="pr-3">Lord</th>
                <th class="pr-3">Nakshatra (pada)</th><th class="pr-3">Navamsa</th><th>Retro</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chart['planets'] as $name => $p): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-semibold" style="color: <?= $pcolor($name) ?>"><?= $h($name) ?></td>
                    <td class="pr-3"><?= $h($p['formatted']) ?></td>
                    <td class="pr-3"><?= (int) $p['house'] ?></td>
                    <td class="pr-3"><?= $h($lordHouses((string) $name)) ?></td>
                    <td class="pr-3"><?= $h($p['nakshatra']['name']) ?> (<?= (int) $p['nakshatra']['pada'] ?>)</td>
                    <td class="pr-3"><?= $h($p['navamsa_sign']) ?></td>
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


        <!-- ROW 3 — Gochar calculation details (defaults to now + IP location) -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Gochar Calculation Details</h2>
            <div id="gochar-inputs"></div>
        </div>


        <!-- ROW 4 — natal D1 (Rasi) vs current Gochar (transit). Both cards carry
             a matching header (title + date/time/place) so the charts line up. -->
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
            <div class="bg-white rounded-lg shadow p-2 flex flex-col">
                <div id="gochar-output" class="w-full"></div>
            </div>
        </div>


        <!-- ROW 5 — Varshaphal year selection + summary details -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Varshaphal</h2>
            <div id="vp-box" class="mb-3"></div>
            <div id="vp-summary" class="text-sm"></div>
        </div>


        <!-- ROW 6 — Varsha chart + Mudda dasha, side by side -->
        <div id="vp-output"></div>


    <!-- Varshaphal -->
    <?php if ($vp !== null): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
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

        <h3 class="font-semibold mt-4 mb-2">Mudda (Annual) Dasha <span class="text-xs text-gray-400 font-normal">(+ drills 5 levels)</span></h3>
        <div id="mudda-dasha-detail"></div>
    </div>
    <?php endif; ?>


    <!-- Gochar -->
    <?php if ($gochar !== null): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
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
    <?php endif; ?>


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
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
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
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
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


        </div>

    </div><!-- /sec-home grid -->
    <?php endif; ?>

    <p class="text-xs text-gray-400">Auto Business — Calculation Engine test page. For arc-second accuracy set SWETEST_PATH (Swiss Ephemeris).</p>
</div>

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
    mSel.addEventListener('change', load);
    aSel.addEventListener('change', load);
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
  (function () {
    var card = document.getElementById('planet-phala-card');
    if (!card) { return; }
    var picks = card.querySelectorAll('.planet-pick');
    var details = card.querySelectorAll('.planet-detail');
    var pane = document.getElementById('planet-detail-pane');
    picks.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var pl = btn.getAttribute('data-planet');
        details.forEach(function (d) {
          d.classList.toggle('hidden', d.getAttribute('data-planet') !== pl);
        });
        picks.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('bg-blue-50', on);
          b.classList.toggle('border-blue-200', on);
          b.classList.toggle('font-semibold', on);
        });
        if (pane) { pane.scrollTop = 0; }
      });
    });
  })();

  // House Prediction: pick a house (or "All 12") -> show its reading.
  (function () {
    var card = document.getElementById('house-pred-card');
    if (!card) { return; }
    var picks = card.querySelectorAll('.house-pick');
    var details = card.querySelectorAll('.house-detail');
    var pane = document.getElementById('house-detail-pane');
    picks.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var hv = btn.getAttribute('data-house');
        details.forEach(function (d) {
          d.classList.toggle('hidden', hv !== 'all' && d.getAttribute('data-house') !== hv);
        });
        picks.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('bg-blue-50', on);
          b.classList.toggle('border-blue-200', on);
          b.classList.toggle('font-semibold', on);
        });
        if (pane) { pane.scrollTop = 0; }
      });
    });
  })();

  // Karaka Prediction: pick a karaka -> show its paired reading.
  (function () {
    var card = document.getElementById('karaka-pred-card');
    if (!card) { return; }
    var picks = card.querySelectorAll('.karaka-pick');
    var details = card.querySelectorAll('.karaka-detail');
    var pane = document.getElementById('karaka-detail-pane');
    picks.forEach(function (btn) {
      btn.addEventListener('click', function () {
        var kv = btn.getAttribute('data-karaka');
        details.forEach(function (d) { d.classList.toggle('hidden', d.getAttribute('data-karaka') !== kv); });
        picks.forEach(function (b) {
          var on = b === btn;
          b.classList.toggle('bg-blue-50', on);
          b.classList.toggle('border-blue-200', on);
          b.classList.toggle('font-semibold', on);
        });
        if (pane) { pane.scrollTop = 0; }
      });
    });
    // Copy all karaka readings (Devanagari-safe).
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
        box: '#vp-box', summary: '#vp-summary', output: '#vp-output',
        birth: window.AB_BIRTH, tz: window.AB_TZ, year: window.AB_YEAR
      });
    }
    renderChartFrame('D1');
    setTimeout(setPanelHeights, 120);
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
      var label = expanded ? 'संकुचित करें' : 'विस्तृत करें';
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
  var FULL_SECTIONS = ['sec-grah', 'sec-varga', 'sec-dasha', 'sec-bal'];
  function showSection(key, focusPred) {
    var homeMode = key === 'home';
    var cp = document.getElementById('chart-panel');
    var pp = document.getElementById('pred-panel');
    if (cp) cp.classList.toggle('hidden', !homeMode);
    if (pp) pp.classList.toggle('hidden', !homeMode);
    FULL_SECTIONS.forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.classList.toggle('hidden', id !== 'sec-' + key);
    });
    if (homeMode) { setTimeout(setPanelHeights, 60); }
    if (focusPred && pp) { pp.scrollIntoView({ block: 'nearest' }); }
  }
  document.querySelectorAll('#side-menu [data-sec]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('#side-menu [data-sec]').forEach(function (b) { b.classList.toggle('active', b === btn); });
      showSection(btn.getAttribute('data-sec'), btn.hasAttribute('data-focus'));
    });
  });

  // Equal heights (required): chart panel height == prediction panel height,
  // filling the viewport below the tiles; min 560px. Desktop (≥1100px) only.
  function setPanelHeights() {
    var cp = document.getElementById('chart-panel');
    var pp = document.getElementById('pred-panel');
    if (!cp || !pp) { return; }
    if (!window.matchMedia('(min-width: 1100px)').matches) {
      cp.style.height = ''; pp.style.height = '';
      sizeChartFrame(null);
      return;
    }
    // Measure from whichever panel is on screen (chart panel is display:none
    // in the expanded reading mode).
    var ref = cp.offsetParent !== null ? cp : pp;
    var top = ref.getBoundingClientRect().top;
    var hpx = Math.max(560, window.innerHeight - top - 16);
    cp.style.height = hpx + 'px';
    pp.style.height = hpx + 'px';
    sizeChartFrame(hpx);
  }
  // The chart is square: cap its width so it fits the panel height (CSS
  // container scaling only — nothing inside the SVG changes).
  function sizeChartFrame(panelH) {
    var frame = document.getElementById('chart-frame');
    var cp = document.getElementById('chart-panel');
    if (!frame || !cp) { return; }
    if (panelH == null) { frame.style.maxWidth = ''; return; }
    var used = 0;
    Array.prototype.forEach.call(cp.children, function (ch) {
      if (ch !== frame) { used += ch.getBoundingClientRect().height; }
    });
    var avail = panelH - used - 40; // paddings/margins
    frame.style.maxWidth = Math.max(300, avail) + 'px';
  }
  var resizeT2;
  window.addEventListener('resize', function () {
    clearTimeout(resizeT2);
    resizeT2 = setTimeout(setPanelHeights, 150);
  });

  // नई कुंडली: reveal / hide the birth-details form.
  var nk = document.getElementById('new-kundli');
  if (nk) {
    nk.addEventListener('click', function () {
      var bf = document.getElementById('birth-form');
      if (!bf) { return; }
      var show = bf.classList.toggle('hidden') === false;
      if (show) { bf.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
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
