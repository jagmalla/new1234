<?php
/**
 * Annual positions card (inner of #card-varshadet). Rendered by calc-v2.php
 * and re-rendered by the varshaphal JSON endpoint when the year changes.
 * Scope: $vp, $forYear, $h, $pcolor.
 */
if ($vp !== null): ?>
    <h2 class="font-semibold mb-2">Varshaphal (Annual Chart) — year <?= (int) $forYear ?></h2>
    <div>Varsha Lagna: <b><?= $h($vp['varsha_chart']['ascendant']['formatted']) ?></b> (lord <?= $h($vp['varsha_lagna']['lord']) ?>)
        · Muntha: <?= $h($vp['muntha']['sign']) ?> (lord <?= $h($vp['muntha']['lord']) ?>)
        <?php if (!empty($vp['varshesh']['lord'])): ?>· Varshesh (Year Lord): <b><?= $h($vp['varshesh']['lord']) ?></b> (Panchavargeeya Bala <?= $h((string) ($vp['varshesh']['bala20'] ?? $vp['varshesh']['bala'])) ?>)<?php endif; ?>
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
<?php else: ?>
    <div class="text-gray-400 italic">Annual positions not available.</div>
<?php endif; ?>
