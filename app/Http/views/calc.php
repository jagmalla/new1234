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
            <label class="flex flex-col gap-1"><span class="text-gray-500">Date (YYYY-MM-DD)</span>
                <input name="date" value="<?= $h($in['date']) ?>" class="border rounded px-2 py-1"></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Time (HH:MM)</span>
                <input name="time" value="<?= $h($in['time']) ?>" class="border rounded px-2 py-1"></label>

            <label class="flex flex-col gap-1"><span class="text-gray-500">Country</span>
                <select id="b-country" class="border rounded px-2 py-1 bg-white"></select></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">State / Province</span>
                <select id="b-state" class="border rounded px-2 py-1 bg-white"></select></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">City (fills lat/lon/tz)</span>
                <select id="b-city" class="border rounded px-2 py-1 bg-white"></select></label>
            <label class="flex flex-col gap-1"><span class="text-gray-500">Ayanamsa</span>
                <input name="ayanamsa" value="<?= $h($in['ayanamsa']) ?>" class="border rounded px-2 py-1"></label>

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
        <p class="text-xs text-gray-500 mt-2">City picker fills lat/lon/timezone (full city search arrives in Module 5c); lat/lon also accept DMS, e.g. 30N48'00.</p>
    </form>

    <?php if ($view['error'] !== null): ?>
        <div class="bg-red-100 text-red-800 rounded p-3 text-sm">Error: <?= $h($view['error']) ?></div>
    <?php endif; ?>

    <?php if ($chart !== null): ?>

    <!-- View toggle (Charts / Details) — reusable pattern for Module 5d -->
    <div class="flex gap-2">
        <button id="btn-charts" type="button" class="px-4 py-2 rounded text-sm font-semibold bg-gray-200">View Charts</button>
        <button id="btn-details" type="button" class="px-4 py-2 rounded text-sm font-semibold bg-blue-600 text-white">View Details</button>
    </div>

    <!-- CHARTS VIEW (dashboard rows) -->
    <div id="charts-view" class="hidden space-y-6">

        <!-- ROW 2 — Gochar calculation details (defaults to now + IP location) -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Gochar Calculation Details</h2>
            <div id="gochar-inputs"></div>
        </div>

        <!-- ROW 3 — D1 chart (large) + Gochar chart -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="max-w-md mx-auto" data-varga="D1"></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div id="gochar-output"></div>
            </div>
        </div>

        <!-- ROW 4 — Vimshottari Dasha | Shadbala (vertical) | D9 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold mb-2">Vimshottari Dasha <span class="text-xs text-gray-400 font-normal">(+ drills 5 levels)</span></h2>
                <div id="vim-dasha" class="text-sm"></div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <h2 class="font-semibold mb-1">Shadbala</h2>
                <p class="text-xs text-gray-500 mb-3">Strength ÷ minimum required. Dashed line = 100%; % shows red when below.</p>
                <?php
                    $pcol = ['Sun' => '#dc2626','Moon' => '#0891b2','Mars' => '#ea580c','Mercury' => '#16a34a',
                        'Jupiter' => '#b45309','Venus' => '#db2777','Saturn' => '#1d4ed8','Rahu' => '#6b7280','Ketu' => '#6b7280'];
                ?>
                <div class="relative" style="height:170px">
                    <!-- 100% threshold line (160% fills the 150px track => 100% sits at 62.5%) -->
                    <div class="absolute left-0 right-0" style="bottom:calc(20px + 150px * 0.625); border-top:1px dashed #9ca3af"></div>
                    <div class="flex items-end justify-between gap-1 absolute inset-x-0 bottom-0" style="height:170px">
                        <?php foreach (($chart['shadbala'] ?? []) as $name => $b):
                            $ratio = (float) $b['ratio'];
                            $pct = $ratio * 100.0;
                            $hpx = max(3.0, min(150.0, $ratio / 1.6 * 150.0));
                            $abbr = substr((string) $name, 0, 2);
                        ?>
                        <div class="flex flex-col items-center justify-end" style="height:170px; flex:1">
                            <div class="text-[10px] font-semibold <?= $ratio < 1.0 ? 'text-red-600' : 'text-gray-600' ?>"><?= sprintf('%.0f%%', $pct) ?></div>
                            <div class="w-full rounded-t" style="height:<?= sprintf('%.1f', $hpx) ?>px; background:<?= $pcol[$name] ?? '#1d4ed8' ?>"></div>
                            <div class="text-[10px] mt-1 font-medium" style="color:<?= $pcol[$name] ?? '#111' ?>"><?= $h($abbr) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-4">
                <div class="max-w-xs mx-auto" data-varga="D9"></div>
            </div>
        </div>

        <!-- ROW 5 — Varshaphal question box -> Varsha chart + Mudda dasha -->
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3 text-gray-700">Varshaphal</h2>
            <div id="vp-box" class="mb-4"></div>
            <div id="vp-output"></div>
        </div>

        <!-- Remaining divisional charts -->
        <div>
            <h2 class="font-semibold mb-2 text-gray-700">Divisional Charts</h2>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                <?php foreach (($vargas ?? []) as $vkey => $vinfo): if ($vkey === 'D1' || $vkey === 'D9') { continue; } ?>
                    <div class="bg-white rounded-lg shadow p-2" data-varga="<?= $h($vkey) ?>"></div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-400 mt-2">North-Indian style: house 1 top-centre (As = Ascendant); black number at each inner corner = Rashi (sign) number; planet abbreviations colour-coded (Dasha palette), R = retrograde.</p>
        </div>

    </div>

    <!-- DETAILS VIEW (text tables) -->
    <div id="details-view">
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
                    <td class="py-1 pr-2 font-medium"><?= $h($name) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['sthana']['total'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['dig'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['kaala'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['chesta'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['naisargika'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['drig'], 1)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['total_virupa'], 1)) ?></td>
                    <td class="pr-2 font-semibold"><?= $h(number_format((float) $b['total_rupa'], 2)) ?></td>
                    <td class="pr-2"><?= $h(number_format((float) $b['ratio'], 2)) ?></td>
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
</script>
<script src="/assets/js/northchart.js"></script>
<script src="/assets/js/dasha.js"></script>
<script src="/assets/js/cities.js"></script>
<script src="/assets/js/gochar.js"></script>
<script src="/assets/js/varshaphal.js"></script>
<script>
(function () {
  // Birth-form city picker -> fills lat/lon/tz (same gazetteer as gochar).
  (function bindBirthCity() {
    var CITIES = window.AB_CITIES || {};
    var co = document.getElementById('b-country'), st = document.getElementById('b-state'),
        ci = document.getElementById('b-city'),
        la = document.getElementById('b-lat'), lo = document.getElementById('b-lon'), tz = document.getElementById('b-tz');
    if (!co) return;
    function opt(v, t) { var o = document.createElement('option'); o.value = v; o.textContent = t || v; return o; }
    co.appendChild(opt('', '— country —'));
    Object.keys(CITIES).forEach(function (c) { co.appendChild(opt(c)); });
    st.appendChild(opt('', '— state —')); ci.appendChild(opt('', '— city —'));
    co.addEventListener('change', function () {
      st.innerHTML = ''; ci.innerHTML = ''; st.appendChild(opt('', '— state —'));
      Object.keys(CITIES[co.value] || {}).forEach(function (s) { st.appendChild(opt(s)); });
    });
    st.addEventListener('change', function () {
      ci.innerHTML = ''; ci.appendChild(opt('', '— city —'));
      Object.keys((CITIES[co.value] || {})[st.value] || {}).forEach(function (c) { ci.appendChild(opt(c)); });
    });
    ci.addEventListener('change', function () {
      var rec = ((CITIES[co.value] || {})[st.value] || {})[ci.value];
      if (rec) { la.value = rec.lat; lo.value = rec.lon; tz.value = rec.tz; }
    });
  })();

  var charts = document.getElementById('charts-view');
  var details = document.getElementById('details-view');
  var bC = document.getElementById('btn-charts');
  var bD = document.getElementById('btn-details');
  var rendered = false;
  function activate(btn, on) {
    btn.classList.toggle('bg-blue-600', on);
    btn.classList.toggle('text-white', on);
    btn.classList.toggle('bg-gray-200', !on);
  }
  function buildCharts() {
    if (rendered) return;
    rendered = true;
    if (window.ABChart && window.AB_VARGAS) { ABChart.renderAll(window.AB_VARGAS); }
    if (window.ABDasha) {
      ABDasha.render(document.getElementById('vim-dasha'), window.AB_DASHA, { tz: window.AB_TZ });
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
        box: '#vp-box', output: '#vp-output',
        birth: window.AB_BIRTH, tz: window.AB_TZ, year: window.AB_YEAR
      });
    }
  }
  function showCharts() {
    buildCharts();
    charts.classList.remove('hidden'); details.classList.add('hidden');
    activate(bC, true); activate(bD, false);
  }
  function showDetails() {
    charts.classList.add('hidden'); details.classList.remove('hidden');
    activate(bD, true); activate(bC, false);
  }
  bC.addEventListener('click', showCharts);
  bD.addEventListener('click', showDetails);
})();
</script>
<?php endif; ?>
</body>
</html>
