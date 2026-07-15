<?php
/**
 * वर्षेश फल pane of the Varshaphal prediction panel (inner of #vp-pred-varshesh).
 * Rendered by calc-v2 and re-rendered by the varshaphal JSON endpoint on a year
 * change. Scope: $view['varshesh'] (VarsheshEngine output), $h, $pcolor,
 * $grahaHi. Mirrors the सहम / ताजिक card look.
 */
$vsh = $view['varshesh'] ?? null;
$bandCls = static fn(string $b): string => $b === 'full' ? 'gc-shubh' : ($b === 'heen' ? 'gc-ashubh' : 'gc-mishrit');
$noteCls = static fn(string $t): string => $t === 'pos' ? 'gph-pos' : ($t === 'neg' ? 'gph-neg' : 'gph-info');
if ($vsh !== null && !empty($vsh['winner'])):
?>
    <!-- Header: वर्षेश + band + combust/retro + method-diff -->
    <div class="saham-active">
        <div style="font-size:1.05rem"><b>वर्षेश:</b>
            <span style="color:<?= $pcolor($vsh['winner']) ?>;font-weight:800"><?= $h($vsh['winner_hi']) ?> (<?= $h($vsh['winner']) ?>)</span>
            <span class="gc-chip <?= $bandCls($vsh['band']) ?>"><?= $h($vsh['band_hi']) ?>-बली</span>
            <?php if (($vsh['combust_pct'] ?? 0) >= 40): ?><span class="gc-chip gc-ashubh">अस्त <?= (int) $vsh['combust_pct'] ?>%</span><?php endif; ?>
            <?php if (!empty($vsh['retro'])): ?><span class="text-xs" style="color:#b91c1c">वक्री ®</span><?php endif; ?>
        </div>
        <div class="text-xs text-gray-500" style="margin-top:3px">पंचवर्गीय बल <?= $h((string) $vsh['bala20']) ?>/20 (<?= $h((string) $vsh['v_bala']) ?>/80) · <?= ($vsh['is_day'] ?? true) ? 'दिन' : 'रात्रि' ?>-वर्षप्रवेश · <?= $h($vsh['method']) ?></div>
        <?php if (!empty($vsh['method_diff']) && ($vsh['display'] ?? 'both') === 'both'): ?>
        <div class="gph-note gph-info" style="margin-top:5px">पद्धति-भेद: ऊपर शीर्ष-पट्टी में सरल-बल विधि से वर्षेश <b><?= $h($grahaHi[$vsh['method_diff']] ?? $vsh['method_diff']) ?></b> दिखता है; यह पैनल शास्त्रीय लग्न-दृष्टि विधि से <b><?= $h($vsh['winner_hi']) ?></b> देता है। स्वामी अन्तिम चयन Admin से तय कर सकते हैं।</div>
        <?php endif; ?>
    </div>

    <!-- चयन-कारण: the five candidates' comparison -->
    <details class="tajik-matrix-wrap" style="margin-top:8px" open>
        <summary style="cursor:pointer;font-weight:800;font-size:.9rem;color:var(--sindoor)">चयन-कारण (पंचाधिकारी तुलना)</summary>
        <div class="overflow-x-auto" style="margin-top:6px">
            <table class="tajik-matrix" style="white-space:normal">
                <thead><tr>
                    <th>अधिकारी</th><th>ग्रह</th><th>बल (÷4)</th><th>बैंड</th><th>लग्न-दृष्टि</th><th>निर्णय</th>
                </tr></thead>
                <tbody>
                <?php foreach ($vsh['trail'] as $c): ?>
                    <tr<?= !empty($c['selected']) ? ' style="background:#FBF6E9;font-weight:700"' : '' ?>>
                        <td style="text-align:left;font-size:.72rem"><?= $h($c['office']) ?></td>
                        <td style="color:<?= $pcolor($c['planet']) ?>"><?= $h($c['planet_hi']) ?></td>
                        <td><?= $h((string) $c['bala20']) ?></td>
                        <td><?= $h($c['band_hi']) ?></td>
                        <td><?= $c['aspects'] ? '<span style="color:#1c5138">हाँ</span>' : '<span style="color:#8A2F2F">नहीं</span>' ?> <span class="text-xs text-gray-400">(<?= $h((string) ($c['drishti_note'] ?? $c['drishti_kala'] . ' कला')) ?>)</span></td>
                        <td style="text-align:left;font-size:.72rem"><?= $h($c['reason']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="text-xs text-gray-400" style="margin-top:4px">नियम: लग्न को ताजिक दृष्टि देने वालों में सर्वाधिक पंचवर्गीय बली = वर्षेश; बल-साम्य में वर्ष-लग्नेश; कोई न देखे/सब हीन → मुंथेश।</div>
    </details>

    <!-- Phal card (21-table text) -->
    <div class="saham-card" style="margin-top:8px">
        <div class="saham-card-head">
            <span class="saham-name" style="color:<?= $pcolor($vsh['winner']) ?>"><?= $h($vsh['winner_hi']) ?> वर्षेश — <?= $h($vsh['band_hi']) ?> फल</span>
        </div>
        <div class="saham-phal">● <?= $h($vsh['phal']) ?></div>
    </div>

    <!-- संशोधक (श्लोक 13, 37–44) -->
    <?php if (!empty($vsh['modifiers'])): ?>
    <div class="gph-section-title" style="margin-top:10px">संशोधक नियम <span class="text-xs text-gray-400 font-normal">(श्लोक 13 · 37–44)</span></div>
    <?php foreach ($vsh['modifiers'] as $m): ?>
        <div class="saham-card" style="margin-bottom:6px">
            <div class="saham-card-head"><span class="saham-name" style="font-size:.9rem"><?= $h($m['title']) ?></span>
                <span class="gc-chip <?= $m['tone'] === 'pos' ? 'gc-shubh' : ($m['tone'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $m['tone'] === 'pos' ? 'शुभ' : ($m['tone'] === 'neg' ? 'अशुभ' : 'सूचना') ?></span>
            </div>
            <?php if (!empty($m['text'])): ?><div class="gph-note <?= $noteCls($m['tone']) ?>" style="border-left:none;background:none;padding:2px 0"><?= $h($m['text']) ?></div><?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php endif; ?>
    <p class="text-xs text-gray-400" style="margin-top:6px">बल-बैंड जन्म व वर्ष दोनों में देखा गया (श्लोक 37): वर्ष <?= $h($vsh['band_hi']) ?> ← वर्ष-कुंडली <?= $h(['full'=>'पूर्ण','madhya'=>'मध्यम','heen'=>'हीन'][$vsh['v_band']] ?? '') ?> + जन्म-कुंडली <?= $h(['full'=>'पूर्ण','madhya'=>'मध्यम','heen'=>'हीन'][$vsh['n_band']] ?? '') ?>।</p>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🔮</div>
        <div class="gps-title">वर्षेश फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($vsh['error'] ?? 'वर्ष कुंडली गणना के बाद वर्षेश-चयन व फल यहाँ दिखेगा।')) ?></div>
    </div>
<?php endif; ?>
