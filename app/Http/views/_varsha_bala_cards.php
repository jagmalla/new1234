<?php
/**
 * ROW 3 of the Varshaphal section (inner of #vp-row3): the PL-style
 * "Varshaphala strengths" (Panchavargeeya Bala) table and the "Year lord"
 * (Panchadhikari) card. Rendered by calc-v2.php and re-rendered by the
 * varshaphal JSON endpoint when the year changes.
 * Scope: $vp, $chart (natal), $h, $pcolor, $grahaHi, $rashiHi.
 */
$pv = $vp['varshesh'] ?? null;
$pvTable = is_array($pv) ? ($pv['table'] ?? []) : [];
$pvOffices = is_array($pv) ? ($pv['offices'] ?? []) : [];
$fmt2 = static fn($n) => number_format((float) $n, 2);
// Muntha longitude = muntha sign + the NATAL ascendant's degree in sign.
$munthaSignIdx = null;
$munthaDegStr = '';
if ($vp !== null && $chart !== null) {
    $munthaSignIdx = ((int) $chart['ascendant']['sign_index'] + (int) $vp['age_completed']) % 12;
    $md = (float) $chart['ascendant']['deg_in_sign'];
    $mdD = (int) floor($md);
    $mdM = (int) round(($md - $mdD) * 60.0);
    if ($mdM === 60) { $mdD++; $mdM = 0; }
    $munthaDegStr = sprintf('%02d°%02d′', $mdD, $mdM);
}
?>
            <!-- पंचवर्गीय बल — PL "Varshaphala strengths" -->
            <div id="card-pvbala" class="bg-white rounded-lg shadow p-4 text-sm overflow-x-auto">
                <h2 class="font-semibold mb-2">पंचवर्गीय बल — Varshaphala Strengths (Panchavargeeya Bala)</h2>
                <?php if ($pvTable !== []): ?>
                <table class="w-full">
                    <thead><tr class="text-left border-b">
                        <th class="py-1 pr-3">ग्रह</th>
                        <th class="pr-3">क्षेत्र<div class="text-xs text-gray-400 font-normal">Griha (30)</div></th>
                        <th class="pr-3">उच्च<div class="text-xs text-gray-400 font-normal">Uchcha (20)</div></th>
                        <th class="pr-3">हद्दा<div class="text-xs text-gray-400 font-normal">Hudda (15)</div></th>
                        <th class="pr-3">द्रेष्काण<div class="text-xs text-gray-400 font-normal">Drekkana (10)</div></th>
                        <th class="pr-3">नवांश<div class="text-xs text-gray-400 font-normal">Navamsha (5)</div></th>
                        <th>Total<div class="text-xs text-gray-400 font-normal">(÷4, out of 20)</div></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($pvTable as $pl => $c): $isYl = ($pv['lord'] ?? '') === $pl; ?>
                        <tr class="border-b border-gray-100<?= $isYl ? ' font-semibold' : '' ?>"<?= $isYl ? ' style="background:#FBF6E9"' : '' ?>>
                            <td class="py-1 pr-3 font-semibold" style="color: <?= $pcolor($pl) ?>"><?= $h($grahaHi[$pl] ?? $pl) ?> <span class="text-xs text-gray-400"><?= $h(substr($pl, 0, 2)) ?></span></td>
                            <td class="pr-3"><?= $fmt2($c['kshetra']) ?></td>
                            <td class="pr-3"><?= $fmt2($c['uchcha']) ?></td>
                            <td class="pr-3"><?= $fmt2($c['hadda']) ?></td>
                            <td class="pr-3"><?= $fmt2($c['drekkana']) ?></td>
                            <td class="pr-3"><?= $fmt2($c['navamsa']) ?></td>
                            <td><b><?= $fmt2($c['total20']) ?></b><?= $isYl ? ' <span class="gc-chip gc-shubh">वर्षेश</span>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="text-xs text-gray-400 mt-2">क्षेत्र/हद्दा/द्रेष्काण/नवांश — ताजिक स्थान-मैत्री अंश (स्व 1 · मित्र ¾ · सम ½ · शत्रु ¼); उच्च = नीच-बिंदु से दूरी ÷ 9; Total = पाँचों का योग ÷ 4 (Parashara's Light रीति)।</p>
                <?php else: ?>
                <div class="text-gray-400 italic">Panchavargeeya Bala not available.</div>
                <?php endif; ?>
            </div>

            <!-- वर्षेश — PL "Year lord" -->
            <div id="card-yearlord" class="bg-white rounded-lg shadow p-4 text-sm">
                <h2 class="font-semibold mb-2">वर्षेश — Year Lord</h2>
                <?php if ($pv !== null && !empty($pv['lord'])): ?>
                <div class="space-y-1 leading-relaxed">
                    <div><span class="text-gray-600 font-semibold">Year lord (वर्षेश):</span>
                        <b style="color:<?= $pcolor($pv['lord']) ?>"><?= $h($grahaHi[$pv['lord']] ?? $pv['lord']) ?> (<?= $h($pv['lord']) ?>)</b>
                        <span class="text-gray-500">(<?= $h((string) ($pv['bala20'] ?? $pv['bala'])) ?>)</span></div>
                    <?php if ($munthaSignIdx !== null): $msName = \AutoBusiness\Astro\Calc\Charts::SIGNS[$munthaSignIdx]; ?>
                    <div><span class="text-gray-600 font-semibold">Muntha (मुन्था):</span>
                        <b><?= $h($rashiHi[$msName] ?? $msName) ?> (<?= $h(substr($msName, 0, 3)) ?>) <?= $h($munthaDegStr) ?></b></div>
                    <?php endif; ?>
                    <div class="text-gray-600 font-semibold pt-1 border-t mt-2">Panchadhikaris (पंचाधिकारी):</div>
                    <table class="w-full">
                        <tbody>
                        <?php foreach ($pvOffices as $o): ?>
                            <tr class="border-b border-gray-100<?= !empty($o['is_varshesh']) ? ' font-semibold' : '' ?>"<?= !empty($o['is_varshesh']) ? ' style="background:#FBF6E9"' : '' ?>>
                                <td class="py-1 pr-3"><?= $h($o['office_en']) ?><div class="text-xs text-gray-400 font-normal"><?= $h($o['office']) ?></div></td>
                                <td><b style="color:<?= $pcolor($o['planet']) ?>"><?= $h($grahaHi[$o['planet']] ?? $o['planet']) ?></b>
                                    <span class="text-gray-500">(<?= $h((string) $o['bala20']) ?>)</span>
                                    <?= !empty($o['is_varshesh']) ? ' <span class="gc-chip gc-shubh">वर्षेश</span>' : '' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="text-xs text-gray-400 mt-2">पाँच अधिकारियों में सर्वाधिक पंचवर्गीय बल वाला ग्रह वर्षेश; कोष्ठक का मान Total (÷4)। दिन-रात्रि पति = वर्ष-प्रवेश के वार का स्वामी।</p>
                </div>
                <?php else: ?>
                <div class="text-gray-400 italic">Year-lord details not available.</div>
                <?php endif; ?>
            </div>
