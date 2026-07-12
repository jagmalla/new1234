<?php
/**
 * साढ़े साती + शनि ढैया — full period TIMELINE (feature spec खण्ड 7). Rendered
 * inside the Gochar "साढ़े साती (शनि विशेष)" category. Reads $gp['sade_timeline']
 * (SadeSatiTimeline::fullTimeline output). Scope: $gp, $h, $pcolor, $rashiHi.
 *
 * Two bases (Moon primary / Lagna secondary) and three view modes (Current
 * Active / All / Past) filter the cards client-side via ABBindSadeTimeline.
 */
$tl = $gp['sade_timeline'] ?? null;
if ($tl === null) {
    return;
}
$tz = (float) ($gp['sade_tz'] ?? 0.0);
$signs = \AutoBusiness\Astro\Calc\Charts::SIGNS;
$dmy = static fn ($jd): string => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $jd, $tz);
$signHi = static fn (int $idx): string => $rashiHi[$signs[$idx] ?? ''] ?? ($signs[$idx] ?? '');
$statusHi = ['ACTIVE' => 'चालू', 'PAST' => 'बीता', 'FUTURE' => 'आगे'];
$typeHi = [
    'SADE_SATI' => 'साढ़े साती',
    'DHAIYA_KANTAK' => 'कण्टक ढैया',
    'DHAIYA_ASHTAM' => 'अष्टम ढैया',
];

/** Render one period card. */
$card = static function (array $p, string $basis) use ($h, $pcolor, $dmy, $signHi, $statusHi, $typeHi): void {
    $st = $p['status'];
    $title = ($typeHi[$p['type']] ?? 'शनि') . ($p['type'] === 'SADE_SATI' ? ' — चक्र ' . (int) $p['cycle'] : '');
    // Current phase name (the ACTIVE phase) for the progress label.
    $curPhaseName = '';
    foreach ($p['phases'] as $ph) { if ($ph['status'] === 'ACTIVE') { $curPhaseName = $ph['name']; } }
    ?>
    <div class="sade-card sade-<?= strtolower($st) ?>" data-basis="<?= $h($basis) ?>" data-status="<?= $h($st) ?>">
        <div class="sade-head">
            <span class="sade-title">🪐 <?= $h($title) ?></span>
            <span class="sade-badge sade-b-<?= strtolower($st) ?>"><?= $h($statusHi[$st] ?? $st) ?></span>
        </div>
        <div class="sade-dates"><?= $h($dmy($p['start_jd'])) ?> → <?= $h($dmy($p['end_jd'])) ?></div>
        <?php if ($st === 'ACTIVE' && $p['percent'] !== null): ?>
        <div class="sade-progress"><div class="sade-bar" style="width:<?= (int) $p['percent'] ?>%"></div></div>
        <div class="sade-prog-note"><?= $curPhaseName !== '' ? $h($curPhaseName) . ' — ' : '' ?><?= (int) $p['percent'] ?>% पूर्ण</div>
        <?php endif; ?>

        <?php foreach ($p['phases'] as $ph): ?>
        <div class="sade-phase sade-ph-<?= strtolower($ph['status']) ?>">
            <span class="sade-ph-dot"></span>
            <b><?= $h($ph['name']) ?></b> · <b style="color:<?= $pcolor('Saturn') ?>"><?= $h($signHi((int) $ph['sign'])) ?></b> ·
            <?= $h($dmy($ph['start_jd'])) ?>–<?= $h($dmy($ph['end_jd'])) ?>
            <span class="sade-ph-tag sade-b-<?= strtolower($ph['status']) ?>">[<?= $h($statusHi[$ph['status']] ?? $ph['status']) ?>]</span>
            <span class="sade-ph-bindu">शनि AV <?= (int) $ph['bindu'] ?>/8</span>
        </div>
        <?php endforeach; ?>

        <details class="sade-detail">
            <summary>विस्तृत फल देखें (5 परतें) ▾</summary>
            <?php foreach ($p['phases'] as $ph): $L = $ph['layers']; ?>
            <div class="sade-layer">
                <div class="sade-layer-h"><?= $h($ph['name']) ?> — <?= $h($signHi((int) $ph['sign'])) ?> (<?= $h($L['area']) ?>)</div>
                <div class="sade-layer-line"><b>मूल फल:</b> <?= $h($L['base']) ?></div>
                <div class="sade-layer-line sade-t-<?= $h($L['bav'][0]) ?>"><b>अष्टकवर्ग:</b> <?= $h($L['bav'][1]) ?></div>
                <?php if (!empty($L['jupiter'])): ?><div class="sade-layer-line sade-t-<?= $h($L['jupiter'][0]) ?>"><b>सहवर्ती गुरु:</b> <?= $h($L['jupiter'][1]) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <div class="sade-layer-line"><b>चन्द्र/लग्न-राशि स्वभाव:</b> शनि सम्बन्ध <b><?= $h($p['moon_rel'][0]) ?></b> — <?= $h($p['moon_rel'][1]) ?></div>
            <div class="sade-remedy"><b>सुझाव (उपाय):</b> <?= $h(implode(' ', $p['remedy'])) ?></div>
        </details>
    </div>
    <?php
};

$moonPeriods = $tl['moon']['periods'] ?? [];
$lagnaPeriods = $tl['lagna']['periods'] ?? [];
?>
<div class="sade-wrap">
    <div class="sade-controls">
        <label class="pred-picker-label" for="sade-mode">दिखाएँ</label>
        <select id="sade-mode" class="pred-inline-select" size="1" style="flex:0 1 220px;min-width:150px">
            <option value="current" selected>वर्तमान + आगामी (Current Active)</option>
            <option value="all">सभी (All Periods)</option>
            <option value="past">बीती हुई (Past)</option>
        </select>
        <div class="sade-basis" role="group" aria-label="आधार">
            <button type="button" class="sade-basis-btn active" data-basis="moon">चन्द्र आधार</button>
            <button type="button" class="sade-basis-btn" data-basis="lagna">लग्न आधार</button>
        </div>
    </div>
    <div class="text-xs text-gray-500" style="margin:2px 0 8px">जन्म से भविष्य तक सभी साढ़े साती (12·1·2) व शनि ढैया (4·8) की timeline। चन्द्र-आधार शास्त्रीय रूप से प्रधान है; लग्न-आधार सहायक।</div>

    <div class="sade-list">
        <?php foreach ($moonPeriods as $p) { $card($p, 'moon'); } ?>
        <?php foreach ($lagnaPeriods as $p) { $card($p, 'lagna'); } ?>
    </div>
    <div id="sade-empty" class="hidden text-sm text-gray-500" style="padding:8px 2px">इस चयन के लिए कोई साढ़े साती/ढैया period नहीं।</div>
</div>
