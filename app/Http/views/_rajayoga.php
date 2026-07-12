<?php
/**
 * राजयोग — BPHS Adhyaya 36. Rendered inside the योग (Kundali Yoga) prediction,
 * below the Adhyaya-32 yogakaraka classification. Reads $view['phala_yoga']
 * ['rajayoga'] (RajaYoga::compute). Scope: $view, $h.
 *
 * Colour code (same as other predictions): सक्रिय = green, भंग/दुर्बल = red.
 */
$ry = $view['phala_yoga']['rajayoga'] ?? null;
if ($ry === null) {
    return;
}
$active = $ry['active'] ?? [];
$bhanga = $ry['bhanga'] ?? [];
$unavail = $ry['unavailable'] ?? [];
$kk = $ry['karakas'] ?? [];
$pct = static fn (float $s): int => (int) round($s * 100);
?>
<div class="ry-box">
    <div class="yoga-sec-title" style="margin-top:12px">राजयोग
        <span class="text-xs text-gray-400 font-normal">(बृ.पा.हो.शा. अध्याय 36 — RY · कारक · मन्त्री; अध्याय-32 से dedup)</span>
    </div>
    <div class="ry-karakas">चर-कारक — आत्मकारक <b><?= $h($kk['AK'] ?? '—') ?></b> · अमात्यकारक <b><?= $h($kk['AmK'] ?? '—') ?></b> · पुत्रकारक <b><?= $h($kk['PK'] ?? '—') ?></b></div>

    <?php if ($active !== []): ?>
    <div class="ry-grp ry-grp-pos">✅ सक्रिय राजयोग (<?= count($active) ?>)</div>
    <?php foreach ($active as $y): ?>
    <div class="ry-card ry-c-pos">
        <div class="ry-card-h">
            <span class="ry-name"><?= $h($y['name']) ?></span>
            <span class="ry-src">[BPHS 36.<?= (int) $y['shloka'] ?>]</span>
            <span class="ry-badge ry-b-pos">बल <?= $pct((float) $y['strength']) ?>%</span>
            <?php if (!empty($y['cat']) && $y['cat'] !== 'RY'): ?><span class="ry-tag"><?= $h($y['cat']) ?></span><?php endif; ?>
        </div>
        <div class="ry-matched"><?= $h($y['matched']) ?><?= !empty($y['sambandha']) ? ' · <i>' . $h($y['sambandha']) . '</i>' : '' ?></div>
        <div class="ry-result"><?= $h($y['result']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($bhanga !== []): ?>
    <div class="ry-grp ry-grp-neg">⚠ भंग / दुर्बल योग (<?= count($bhanga) ?>)</div>
    <?php foreach ($bhanga as $y): ?>
    <div class="ry-card ry-c-neg">
        <div class="ry-card-h">
            <span class="ry-name"><?= $h($y['name']) ?></span>
            <span class="ry-src">[BPHS 36.<?= (int) $y['shloka'] ?>]</span>
            <span class="ry-badge ry-b-neg">बल <?= $pct((float) $y['strength']) ?>%</span>
        </div>
        <div class="ry-matched"><?= $h($y['matched']) ?></div>
        <?php foreach (($y['bhanga'] ?? []) as $bn): ?><div class="ry-bhanga-note"><?= $h($bn) ?></div><?php endforeach; ?>
        <div class="ry-result" style="color:#6b6459">उपस्थित किन्तु भंग/दुर्बल — फल पूर्ण न मानें।</div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($active === [] && $bhanga === []): ?>
    <div class="ry-none">इस कुंडली में अध्याय-36 का कोई प्रत्यक्ष राजयोग-सम्बन्ध संगणित नहीं — अध्याय-32 का योगकारक-निर्णय व दशा-बल देखें।</div>
    <?php endif; ?>

    <?php if ($unavail !== []): ?>
    <details class="ry-unavail">
        <summary>🔒 आवश्यक डेटा अनुपलब्ध (<?= count($unavail) ?>) — उन्नत जैमिनी/अरुढ़ नियम</summary>
        <?php foreach ($unavail as $u): ?><div class="ry-unavail-item">• <?= $h($u['name']) ?> <span class="text-gray-500">— चाहिए: <?= $h($u['need']) ?></span></div><?php endforeach; ?>
    </details>
    <?php endif; ?>

    <div class="ry-summary"><b>सारांश:</b> <?= $h($ry['summary'] ?? '') ?></div>
    <div class="ry-caveat">⚠ <?= $h($ry['caveat'] ?? '') ?></div>
</div>
