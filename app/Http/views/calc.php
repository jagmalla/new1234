<?php
declare(strict_types=1);

/** @var array $view  (in, error, chart, vp, gochar, meta) */
use AutoBusiness\Core\Csrf;

$in = $view['in'];
$chart = $view['chart'];
$vp = $view['vp'];
$gochar = $view['gochar'];
$meta = $view['meta'];

$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Auto Business — Chart Calculator (test)</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-900 p-4 md:p-8">
<div class="max-w-5xl mx-auto space-y-6">

    <h1 class="text-2xl font-bold">Calculation Engine — Chart Test</h1>

    <!-- Birth details form (GET) -->
    <form method="get" action="/calc" class="bg-white rounded-lg shadow p-4 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
        <label class="flex flex-col">Date (YYYY-MM-DD)
            <input name="date" value="<?= $h($in['date']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Time (HH:MM)
            <input name="time" value="<?= $h($in['time']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Latitude
            <input name="lat" value="<?= $h($in['latIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Longitude
            <input name="lon" value="<?= $h($in['lonIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Timezone (east +)
            <input name="tz" value="<?= $h($in['tzIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Ayanamsa
            <input name="ayanamsa" value="<?= $h($in['ayanamsa']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Varshaphal year
            <input name="year" value="<?= $h($in['forYear']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Gochar date
            <input name="gochar" value="<?= $h($in['gocharIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col">Gochar time (HH:MM)
            <input name="gochar_time" value="<?= $h($in['gocharTimeIn']) ?>" class="border rounded px-2 py-1"></label>
        <div class="col-span-2 md:col-span-4">
            <button class="bg-blue-600 text-white rounded px-4 py-2 font-semibold">Calculate</button>
            <span class="text-xs text-gray-500 ml-2">Lat/Lon accept decimal or DMS (e.g. 30N48'00). India timezone = 5:30.</span>
        </div>
    </form>

    <?php if ($view['error'] !== null): ?>
        <div class="bg-red-100 text-red-800 rounded p-3 text-sm">Error: <?= $h($view['error']) ?></div>
    <?php endif; ?>

    <?php if ($chart !== null): ?>
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

    <!-- D1 -->
    <div class="bg-white rounded-lg shadow p-4 overflow-x-auto">
        <h2 class="font-semibold mb-2">D1 (Rasi) — Planetary Positions</h2>
        <table class="w-full text-sm">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">Planet</th><th class="pr-3">Position</th><th class="pr-3">House</th>
                <th class="pr-3">Nakshatra (pada)</th><th class="pr-3">Navamsa</th><th>Retro</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chart['planets'] as $name => $p): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-medium"><?= $h($name) ?></td>
                    <td class="pr-3"><?= $h($p['formatted']) ?></td>
                    <td class="pr-3"><?= (int) $p['house'] ?></td>
                    <td class="pr-3"><?= $h($p['nakshatra']['name']) ?> (<?= (int) $p['nakshatra']['pada'] ?>)</td>
                    <td class="pr-3"><?= $h($p['navamsa_sign']) ?></td>
                    <td><?= $p['retro'] ? 'R' : '' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Vimshottari -->
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-2">Vimshottari Dasha</h2>
        <div class="mb-2">Birth balance: <b><?= $h($chart['dasha']['balance']['lord']) ?></b>
            for <?= sprintf('%.2f', $chart['dasha']['balance']['years']) ?> years.
            Running (at birth): <?= $h($chart['dasha']['running']['maha'] ?? '-') ?> /
            <?= $h($chart['dasha']['running']['antar'] ?? '-') ?></div>
        <table class="w-full">
            <thead><tr class="text-left border-b"><th class="py-1 pr-3">Maha</th><th class="pr-3">Start (JD)</th><th class="pr-3">End (JD)</th><th>Years</th></tr></thead>
            <tbody>
            <?php foreach ($chart['dasha']['mahadashas'] as $md): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3"><?= $h($md['lord']) ?></td>
                    <td class="pr-3"><?= sprintf('%.2f', $md['start_jd']) ?></td>
                    <td class="pr-3"><?= sprintf('%.2f', $md['end_jd']) ?></td>
                    <td><?= sprintf('%.2f', $md['years']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Shadbala -->
    <div class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
        <h2 class="font-semibold mb-1">Shadbala (Six-fold Strength)</h2>
        <p class="text-xs text-gray-500 mb-2">Sthana, Dig and Naisargika Bala are validated against Parashara's Light (±0.01). Kaala, Chesta and Drig Bala are in progress — the Total/Rupas/Ratio will appear once all six are in.</p>
        <table class="w-full">
            <thead><tr class="text-left border-b">
                <th class="py-1 pr-3">Planet</th><th class="pr-3">1. Sthana</th><th class="pr-3">2. Dig</th>
                <th class="pr-3">3. Kaala</th><th class="pr-3">4. Chesta</th><th class="pr-3">5. Naisargika</th><th class="pr-3">6. Drig</th>
                <th>Subtotal</th>
            </tr></thead>
            <tbody>
            <?php foreach ($chart['shadbala'] as $name => $b): ?>
                <tr class="border-b border-gray-100">
                    <td class="py-1 pr-3 font-medium"><?= $h($name) ?></td>
                    <td class="pr-3"><?= $h(number_format((float) $b['sthana']['total'], 2)) ?></td>
                    <td class="pr-3"><?= $h(number_format((float) $b['dig'], 2)) ?></td>
                    <td class="pr-3 text-gray-400"><?= $b['kaala'] === null ? '—' : $h($b['kaala']) ?></td>
                    <td class="pr-3 text-gray-400"><?= $b['chesta'] === null ? '—' : $h($b['chesta']) ?></td>
                    <td class="pr-3"><?= $h(number_format((float) $b['naisargika'], 2)) ?></td>
                    <td class="pr-3 text-gray-400"><?= $b['drig'] === null ? '—' : $h($b['drig']) ?></td>
                    <td><?= $h(number_format((float) $b['computed_subtotal'], 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <p class="text-xs text-gray-400 mt-2">Sthana = Uchcha + Saptavargaja + Ojha-Yugma + Kendradi + Drekkana (all validated vs PL).</p>
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
                <tr class="border-b border-gray-100"><td class="py-1 pr-3 font-medium"><?= $h($name) ?></td>
                    <td class="pr-3"><?= $h($p['formatted']) ?></td><td><?= (int) $p['house'] ?><?= $p['retro'] ? ' R' : '' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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

    <?php endif; ?>

    <p class="text-xs text-gray-400">Auto Business — Calculation Engine test page. For arc-second accuracy set SWETEST_PATH (Swiss Ephemeris).</p>
</div>
</body>
</html>
