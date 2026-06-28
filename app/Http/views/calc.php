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
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Business — Chart Calculator (test)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Soft page background so the white cards don't look flat. */
        body { background: linear-gradient(160deg, #eef2ff 0%, #f5f7fb 45%, #fdf2f8 100%); background-attachment: fixed; }
        /* Detail-view cards: gentle tint + definition; headers get a colour accent. */
        #details-view > div { background: linear-gradient(180deg, #ffffff 0%, #f6faff 100%); border: 1px solid #e6edf6; }
        #details-view h2 {
            background: linear-gradient(90deg, #dbeafe 0%, #eff5ff 55%, rgba(255,255,255,0) 100%);
            border-left: 4px solid #2563eb; padding: 5px 10px; border-radius: 4px;
        }
    </style>
</head>
<body class="text-gray-900 p-4 md:p-8">
<div class="max-w-5xl mx-auto space-y-6">

    <h1 class="text-2xl font-bold">Calculation Engine — Chart Test</h1>

    <!-- ROW 1 — Chart (birth) details -->
    <form method="get" action="/calc" class="bg-white rounded-lg shadow p-4 text-sm">
        <h2 class="font-semibold mb-3 text-gray-700">Chart Calculation Details</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <label class="flex flex-col gap-1"><span class="text-gray-500">Name</span>
                <input name="name" value="<?= $h($in['name']) ?>" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Gender</span>
                <select name="gender" class="border rounded px-2 py-1 bg-white">
                    <?php foreach (['' => '—', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'] as $gv => $gl): ?>
                        <option value="<?= $h($gv) ?>" <?= $in['gender'] === $gv ? 'selected' : '' ?>><?= $h($gl) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Date (DD-MM-YYYY)</span>
                <input name="date" value="<?= $h($in['date']) ?>" placeholder="DD-MM-YYYY" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Time (HH:MM)</span>
                <input name="time" value="<?= $h($in['time']) ?>" class="border rounded px-2 py-1"></label>

            <label class="flex flex-col gap-1 relative col-span-2 md:col-span-3"><span class="text-gray-500">Place (search city, state or country — fills lat/lon/timezone)</span>
                <input id="b-place" type="text" autocomplete="off" placeholder="Type a city, e.g. Moga or London…" class="border rounded px-2 py-1">
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

    <!-- View toggle (Charts / Details) — reusable pattern for Module 5d -->
    <div class="flex gap-2">
        <button id="btn-charts" type="button" class="px-4 py-2 rounded text-sm font-semibold bg-blue-600 text-white">View Charts</button>
        <button id="btn-details" type="button" class="px-4 py-2 rounded text-sm font-semibold bg-gray-200">View Details</button>
    </div>

    <!-- CHARTS VIEW (dashboard rows) -->
    <div id="charts-view" class="space-y-6">

        <!-- ROW 1 — D1 (Rasi) chart (wider) + Vimshottari Dasha (scrolls, height = D1) -->
        <div class="grid grid-cols-1 lg:grid-cols-[14fr_11fr] gap-4 items-start">
            <div id="d1-card" class="bg-white rounded-lg shadow p-4 flex items-center justify-center">
                <div class="w-full max-w-xl mx-auto" data-varga="D1" data-ring="1"></div>
            </div>
            <div id="vim-card" class="bg-white rounded-lg shadow p-4 flex flex-col" style="display:flex; flex-direction:column">
                <h2 class="font-semibold mb-2">Vimshottari Dasha <span class="text-xs text-gray-400 font-normal">(+ drills 5 levels)</span></h2>
                <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null):
                    $tzc = (float) ($meta['tz'] ?? 0);
                    $cd = static fn(array $p): string =>
                        \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['start_jd'], $tzc)
                        . ' – ' . \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['end_jd'], $tzc);
                    // $depth indents each level so AntarDasha nests under
                    // MahaDasha and Pratyantar under AntarDasha (with a ↳ marker).
                    $cdLine = static function (string $label, ?array $p, int $depth) use ($pcolor, $cd, $h): string {
                        if (empty($p)) { return ''; }
                        $arrow = $depth > 0 ? '<span class="text-gray-400">↳</span> ' : '';
                        return '<div style="padding-left:' . ($depth * 1.6) . 'rem">' . $arrow
                            . '<span class="text-gray-600 font-semibold">' . $label . ':</span> '
                            . '<b style="color:' . $pcolor($p['lord']) . '">' . $h($p['lord']) . '</b> '
                            . '<span class="text-gray-500">(' . $cd($p) . ')</span></div>';
                    };
                ?>
                <div class="mb-2 pb-2 border-b text-sm leading-snug space-y-0.5">
                    <?= $cdLine('MahaDasha', $dashaNow['maha'], 0) ?>
                    <?= $cdLine('AntarDasha', $dashaNow['antar'], 1) ?>
                    <?= $cdLine('Pratyantar', $dashaNow['pratyantar'], 2) ?>
                </div>
                <?php endif; ?>
                <div id="vim-dasha" class="text-sm flex-1 min-h-0 overflow-y-auto" style="flex:1 1 auto; min-height:0; overflow-y:auto"></div>
            </div>
        </div>

        <!-- ROW 2 — Navamsa (D9, 60%) + Shadbala (40%) -->
        <div class="grid grid-cols-1 lg:grid-cols-[3fr_2fr] gap-4 items-stretch">
            <div class="bg-white rounded-lg shadow p-4 flex items-center justify-center">
                <div class="w-full max-w-lg mx-auto" data-varga="D9"></div>
            </div>
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
        </div>

        <!-- ROW 3 — Gochar calculation details (defaults to now + IP location) -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Gochar Calculation Details</h2>
            <div id="gochar-inputs"></div>
        </div>

        <!-- ROW 4 — D1 (Rasi) chart + current Gochar (chart only); sized to match
             the divisional charts (fill the card, p-2 padding). -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-stretch">
            <div class="bg-white rounded-lg shadow p-2 flex items-center justify-center">
                <div class="w-full" data-varga="D1"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-2 flex items-center justify-center">
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

        <!-- Remaining divisional charts — 2 per row -->
        <div>
            <h2 class="font-semibold mb-2 text-gray-700">Divisional Charts</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <?php foreach (($vargas ?? []) as $vkey => $vinfo): if ($vkey === 'D1' || $vkey === 'D9') { continue; } ?>
                    <div class="bg-white rounded-lg shadow p-2" data-varga="<?= $h($vkey) ?>"></div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">North-Indian style: house 1 top-centre (As = Ascendant); black number at each inner corner = Rashi (sign) number; planet abbreviations colour-coded (Dasha palette), R = retrograde.</p>
        </div>

    </div>

    <!-- DETAILS VIEW (text tables) -->
    <div id="details-view" class="hidden">
    <!-- Header -->
    <div class="bg-white rounded-lg shadow p-4 text-sm grid grid-cols-2 md:grid-cols-3 gap-2">
        <div><span class="text-gray-500">Birth:</span> <?= $h($in['date'] . ' ' . $in['time']) ?> (UTC<?= sprintf('%+.2f', $meta['tz']) ?>)</div>
        <div><span class="text-gray-500">Place:</span> lat <?= sprintf('%.4f', $meta['lat']) ?>, lon <?= sprintf('%.4f', $meta['lon']) ?></div>
        <div><span class="text-gray-500">Ephemeris:</span> <?= $h($chart['meta']['ephemeris']) ?></div>
        <div><span class="text-gray-500">Ayanamsa:</span> <?= $h($chart['meta']['ayanamsa_name']) ?> = <?= sprintf('%.4f°', $chart['meta']['ayanamsa_deg']) ?></div>
        <div><span class="text-gray-500">JD (UT):</span> <?= sprintf('%.5f', $meta['jd']) ?></div>
    </div>

    <!-- Ascendant -->
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h2 class="font-semibold mb-2">Ascendant (Lagna) &amp; MC</h2>
        <div>Lagna: <b><?= $h($chart['ascendant']['formatted']) ?></b>
            — <?= $h($chart['ascendant']['nakshatra']['name']) ?> (pada <?= (int) $chart['ascendant']['nakshatra']['pada'] ?>),
            Navamsa Lagna <?= $h($chart['ascendant']['navamsa_sign']) ?></div>
        <div>MC: <?= $h($chart['mc']['formatted']) ?></div>
    </div>

    <!-- House details: planets, rashi, Ashtakavarga (AV), Bhava Bala (BB), lords -->
    <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">House Details — Ashtakavarga &amp; Bhava Bala</h2>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">House</th><th class="pr-3">Planet(s) in house</th><th class="pr-3">Rashi</th>
                <th class="pr-3">Ashtakavarga (AV)</th><th class="pr-3">Bhava Bala (BB, virupa)</th><th>Lord</th>
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
                    <td class="pr-3"><?= (int) $H['rashi_num'] ?> <?= $h($H['sign']) ?></td>
                    <td class="pr-3 font-semibold" style="color:#1d4ed8"><?= (int) $H['av'] ?></td>
                    <td class="pr-3 font-semibold" style="color:#15803d"><?= $h(number_format((float) ($H['bb_virupa'] ?? $H['bb'] * 60), 2)) ?></td>
                    <td><span style="color:<?= $pcolor($H['lord']) ?>" class="font-semibold"><?= $h($H['lord']) ?></span>
                        <?php $llh = $lordHouses((string) $H['lord']); ?><?= $llh !== '' ? '<span class="text-xs text-gray-400">(' . $h($llh) . ')</span>' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">AV = Sarvashtakavarga bindus for the sign (total 337). BB = Bhava Bala in virupas (Bhavadhipati + Drishti). "Lord of …" = houses, counted from the Lagna, that the planet rules.</p>
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
                    <td><?= $p['retro'] ? 'R' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Vimshottari — same expandable, colour-coded tree as the Chart view -->
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h2 class="font-semibold mb-2">Vimshottari Dasha <span class="text-xs text-gray-400 font-normal">(+ drills 5 levels)</span></h2>
        <div class="mb-2">Birth balance: <b style="color: <?= $pcolor($chart['dasha']['balance']['lord']) ?>"><?= $h($chart['dasha']['balance']['lord']) ?></b>
            for <?= sprintf('%.2f', $chart['dasha']['balance']['years']) ?> years.
            Running (at birth): <?= $h($chart['dasha']['running']['maha'] ?? '-') ?> /
            <?= $h($chart['dasha']['running']['antar'] ?? '-') ?></div>
        <div id="vim-dasha-detail"></div>
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
                    <td class="pr-3"><?= $h($p['formatted']) ?></td><td><?= (int) $p['house'] ?><?= $p['retro'] ? ' R' : '' ?></td></tr>
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
                <tr class="border-b border-gray-100"><td class="py-1 pr-3 font-medium"><?= $h($name) ?><?= $t['retro'] ? ' (R)' : '' ?></td>
                    <td class="pr-3"><?= $h($t['formatted']) ?></td><td class="pr-3"><?= (int) $t['house_from_lagna'] ?></td><td><?= (int) $t['house_from_moon'] ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Current dasha chain (today) -->
    <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null):
        $tzv = (float) ($meta['tz'] ?? 0);
        $fmt = static fn($p) => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['start_jd'], $tzv)
            . ' → ' . \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $p['end_jd'], $tzv);
        $line = function (string $label, ?array $p) use ($pcolor, $fmt, $h): string {
            if ($p === null) { return ''; }
            return '<div><span class="text-gray-500">' . $label . ':</span> '
                . '<b style="color:' . $pcolor($p['lord']) . '">' . $h($p['lord']) . '</b> '
                . '<span class="text-gray-700">(' . $fmt($p) . ')</span></div>';
        };
    ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm">
        <h2 class="font-semibold mb-2">Current Dasha — today (<?= $h(date('d-m-Y')) ?>)</h2>
        <div class="space-y-1">
            <?= $line('Running Mahadasha', $dashaNow['maha']) ?>
            <?= $line('Current Antardasha', $dashaNow['antar']) ?>
            <?= $line('Next Antardasha', $dashaNow['next_antar']) ?>
            <?= $line('Current Pratyantardasha', $dashaNow['pratyantar']) ?>
        </div>
    </div>
    <?php endif; ?>

    </div><!-- /details-view -->
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
</script>
<script src="/assets/js/northchart.js"></script>
<script src="/assets/js/dasha.js"></script>
<script src="/assets/js/citysearch.js"></script>
<script src="/assets/js/gochar.js"></script>
<script src="/assets/js/varshaphal.js"></script>
<script>
(function () {
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

  var charts = document.getElementById('charts-view');
  var details = document.getElementById('details-view');
  var bC = document.getElementById('btn-charts');
  var bD = document.getElementById('btn-details');

  var chartsBuilt = false, detailsBuilt = false;
  function activate(btn, on) {
    btn.classList.toggle('bg-blue-600', on);
    btn.classList.toggle('text-white', on);
    btn.classList.toggle('bg-gray-200', !on);
  }

  // Make the Vimshottari Dasha card exactly as tall as the D1 chart card so the
  // two cells in row 1 line up; the dasha list (flex-1) then scrolls inside it.
  // Measured after layout (and on resize) because the chart SVG is height:auto.
  function syncDashaHeight() {
    var d1 = document.getElementById('d1-card');
    var vc = document.getElementById('vim-card');
    if (!d1 || !vc) { return; }
    // Only match heights in the side-by-side (lg) layout; stacked on narrow screens.
    if (window.matchMedia('(min-width: 1024px)').matches) {
      vc.style.height = d1.getBoundingClientRect().height + 'px';
    } else {
      vc.style.height = '';
    }
  }
  var resizeT;
  window.addEventListener('resize', function () {
    clearTimeout(resizeT);
    resizeT = setTimeout(syncDashaHeight, 150);
  });
  function buildCharts() {
    if (chartsBuilt) return;
    chartsBuilt = true;
    if (window.ABChart && window.AB_VARGAS) { ABChart.renderAll(window.AB_VARGAS, window.AB_HOUSES); }
    if (window.ABDasha) {
      // No maxRows here: the Vimshottari card height is synced to the D1 chart
      // (syncDashaHeight) and the list scrolls inside that fixed height.
      ABDasha.render(document.getElementById('vim-dasha'), window.AB_DASHA, { tz: window.AB_TZ, datesInline: true });
    }
    // Defer so the D1 chart SVG (height:auto) has laid out before we measure it.
    setTimeout(syncDashaHeight, 160);
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
  }
  // Detail view shows the same colour-coded Vimshottari + Mudda trees. Built
  // lazily (when first opened) so row heights are measured while visible.
  function buildDetails() {
    if (detailsBuilt) return;
    detailsBuilt = true;
    if (window.ABDasha) {
      var vdd = document.getElementById('vim-dasha-detail');
      if (vdd) ABDasha.render(vdd, window.AB_DASHA, { tz: window.AB_TZ, datesInline: true, maxRows: 10 });
      var mdd = document.getElementById('mudda-dasha-detail');
      if (mdd && window.AB_MUDDA) ABDasha.render(mdd, window.AB_MUDDA, { tz: window.AB_TZ, datesInline: true, maxRows: 10 });
    }
  }
  function showCharts() {
    buildCharts();
    charts.classList.remove('hidden'); details.classList.add('hidden');
    activate(bC, true); activate(bD, false);
    // Re-measure once visible (a hidden tab reports zero height).
    setTimeout(syncDashaHeight, 60);
  }
  function showDetails() {
    buildDetails();
    charts.classList.add('hidden'); details.classList.remove('hidden');
    activate(bD, true); activate(bC, false);
  }
  bC.addEventListener('click', showCharts);
  bD.addEventListener('click', showDetails);

  // Default view when the page opens = Charts.
  showCharts();
})();
</script>
<?php endif; ?>
</body>
</html>
