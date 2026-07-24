<?php
/**
 * आज का Consult — one-screen "what is active today" dashboard combining every
 * system: the running Vimshottari chain, today's gochar highlights, the
 * running Varshaphal context, the Lal Kitab priority remedies and the next
 * events from the 12-month timeline. All composed from data the controller
 * already built — no extra computation.
 * Scope (calc-v2): $view, $vp, $chart, $dashaNow, $meta, $h, $pcolor.
 */
$tzT = (float) ($meta['tz'] ?? 5.5);
$dmyT = static fn (float $jd): string => \AutoBusiness\Astro\Time\JulianDay::toDmy($jd, $tzT);
$grahaHiT = \AutoBusiness\Astro\Phala\DashaPhalaRepository::LORDS_HI;
$ug = $view['upcoming_gochar'] ?? null;
$lkP = $view['lalkitab']['priority'] ?? [];
$mn = $view['muntha'] ?? null;
$ytEvs = array_slice((array) ($view['year_timeline']['events'] ?? []), 0, 8);
$nowJdT = \AutoBusiness\Astro\Time\JulianDay::fromGregorian(
    (int) date('Y'), (int) date('n'), (int) date('j'), (int) date('G'), (int) date('i'), 0.0, $tzT
);
$muddaNowT = null;
foreach (($vp['mudda_dasha'] ?? []) as $mdT) {
    if ($nowJdT >= (float) $mdT['start_jd'] && $nowJdT < (float) $mdT['end_jd']) { $muddaNowT = $mdT; break; }
}
$tileCss = 'border:1px solid #e5e7eb;border-radius:10px;padding:10px 13px;background:#fff';
$tHead = 'font-size:.72rem;font-weight:700;color:#94a3b8;letter-spacing:.03em;margin-bottom:5px';
?>
<div class="bg-white rounded-lg shadow p-4">
    <h2 class="font-semibold mb-1 text-gray-700">आज का Consult — Today at a Glance</h2>
    <div class="text-xs text-gray-400 mb-3"><?= $h(date('d-m-Y')) ?> · सभी प्रणालियों में आज क्या सक्रिय है — एक नज़र में</div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:11px">

        <!-- 1. चालू विंशोत्तरी दशा -->
        <div style="<?= $tileCss ?>">
            <div style="<?= $tHead ?>">⏳ चालू विंशोत्तरी दशा</div>
            <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null): ?>
                <?php $mD = $dashaNow['maha']; $aD = $dashaNow['antar'] ?? null; $pD = $dashaNow['pratyantar'] ?? null; ?>
                <div class="text-sm"><b style="color:<?= $pcolor((string) $mD['lord']) ?>"><?= $h($grahaHiT[$mD['lord']] ?? $mD['lord']) ?></b> महादशा
                    <span class="text-xs text-gray-500">(<?= $h($dmyT((float) $mD['end_jd'])) ?> तक)</span></div>
                <?php if ($aD !== null): ?>
                <div class="text-sm">↳ <b style="color:<?= $pcolor((string) $aD['lord']) ?>"><?= $h($grahaHiT[$aD['lord']] ?? $aD['lord']) ?></b> अंतर्दशा
                    <span class="text-xs text-gray-500">(<?= $h($dmyT((float) $aD['end_jd'])) ?> तक)</span></div>
                <?php endif; ?>
                <?php if ($pD !== null): ?>
                <div class="text-sm">&nbsp;&nbsp;↳ <b style="color:<?= $pcolor((string) $pD['lord']) ?>"><?= $h($grahaHiT[$pD['lord']] ?? $pD['lord']) ?></b> प्रत्यंतर
                    <span class="text-xs text-gray-500">(<?= $h($dmyT((float) $pD['end_jd'])) ?> तक)</span></div>
                <?php endif; ?>
                <?php $sm = $view['strength']['planets'][$mD['lord']] ?? null; if ($sm !== null): ?>
                    <div class="text-xs" style="margin-top:4px;color:#64748b">महादशा-स्वामी का फल-बल:
                        <span class="gc-chip <?= $sm['tier'] === 'pos' ? 'gc-shubh' : ($sm['tier'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h((string) $sm['word']) ?></span></div>
                <?php endif; ?>
            <?php else: ?><div class="text-gray-400 italic text-sm">दशा उपलब्ध नहीं</div><?php endif; ?>
        </div>

        <!-- 2. आज का गोचर -->
        <div style="<?= $tileCss ?>">
            <div style="<?= $tHead ?>">🌌 आज का गोचर</div>
            <?php if ($ug !== null): ?>
                <?php
                $combNow = (array) ($ug['combust_parts']['current'] ?? []);
                $retroNow = (array) ($ug['retro_parts']['current'] ?? []);
                $topEv = array_slice((array) ($ug['top_events'] ?? []), 0, 3);
                $toneCol = ['good' => '#15803d', 'warn' => '#b45309', 'move' => '#1d4ed8'];
                ?>
                <?php $ssU = $ug['sade_sati'] ?? null; if ($ssU !== null): ?>
                    <div class="text-sm" style="color:<?= !empty($ssU['active']) ? '#b91c1c' : '#475569' ?>">
                        🪐 <b><?= $h((string) $ssU['kind']) ?></b> <?= !empty($ssU['active']) ? 'चल रही है' : 'आगामी' ?>
                        <span class="text-xs text-gray-500">(<?= $h((string) $ssU['start']) ?> – <?= $h((string) $ssU['end']) ?>)</span>
                    </div>
                <?php endif; ?>
                <?php if ($combNow !== []): foreach ($combNow as $cN): ?>
                    <div class="text-sm" style="color:#b91c1c"><?= $h((string) $cN['emoji']) ?> <b><?= $h((string) $cN['planet']) ?></b> अस्त (<?= $h((string) $cN['sign_hi']) ?>)<?php if (!empty($cN['end'])): ?> — उदय <b><?= $h((string) $cN['end']) ?></b><?php endif; ?></div>
                <?php endforeach; endif; ?>
                <?php if ($retroNow !== []): ?>
                    <div class="text-sm" style="color:#6d28d9">↩️ अभी वक्री: <?= $h(implode(', ', array_map(static fn ($r) => (string) $r['emoji'] . ' ' . (string) $r['planet'] . (!empty($r['end']) ? ' (मार्गी ' . $r['end'] . ')' : ''), $retroNow))) ?></div>
                <?php endif; ?>
                <?php foreach ($topEv as $eU): ?>
                    <div class="text-sm" style="color:<?= $toneCol[$eU['tone']] ?? '#334155' ?>"><?= $h((string) $eU['emoji']) ?> <?= $h((string) $eU['text']) ?> — <?= $h((string) $eU['date']) ?> <span class="text-xs text-gray-500">(<?= (int) $eU['days'] ?> दिन)</span></div>
                <?php endforeach; ?>
                <?php if ($combNow === [] && $retroNow === [] && $topEv === []): ?>
                    <div class="text-gray-400 italic text-sm">कोई प्रमुख गोचर घटना नहीं</div>
                <?php endif; ?>
            <?php else: ?><div class="text-gray-400 italic text-sm">गोचर सारांश उपलब्ध नहीं</div><?php endif; ?>
        </div>

        <!-- 3. वर्षफल — आज -->
        <div style="<?= $tileCss ?>">
            <div style="<?= $tHead ?>">🎯 वर्षफल — आज</div>
            <?php if ($muddaNowT !== null): $mlT = (string) $muddaNowT['lord']; ?>
                <div class="text-sm">मुद्दा दशा: <b style="color:<?= $pcolor($mlT) ?>"><?= $h($grahaHiT[$mlT] ?? $mlT) ?></b>
                    <span class="text-xs text-gray-500">(<?= $h($dmyT((float) $muddaNowT['start_jd'])) ?> – <?= $h($dmyT((float) $muddaNowT['end_jd'])) ?>)</span></div>
            <?php endif; ?>
            <?php $pvT = $vp['varshesh'] ?? null; if (!empty($pvT['lord'])): ?>
                <div class="text-sm">वर्षेश: <b style="color:<?= $pcolor((string) $pvT['lord']) ?>"><?= $h($grahaHiT[$pvT['lord']] ?? $pvT['lord']) ?></b>
                    <span class="text-xs text-gray-500">(पंचवर्गीय <?= $h((string) ($pvT['bala20'] ?? '')) ?>/20)</span></div>
            <?php endif; ?>
            <?php if ($mn !== null && !empty($mn['verdict'])): ?>
                <div class="text-sm">मुंथा: <?= (int) ($mn['muntha_house'] ?? 0) ?>वाँ भाव
                    <span class="gc-chip <?= ($mn['verdict_tone'] ?? '') === 'pos' ? 'gc-shubh' : ((($mn['verdict_tone'] ?? '') === 'neg') ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h((string) $mn['verdict']) ?></span></div>
            <?php endif; ?>
            <?php if ($muddaNowT === null && empty($pvT['lord'])): ?><div class="text-gray-400 italic text-sm">वर्षफल उपलब्ध नहीं</div><?php endif; ?>
        </div>

        <!-- 4. लाल किताब — प्राथमिकता उपाय -->
        <div style="<?= $tileCss ?>">
            <div style="<?= $tHead ?>">📕 लाल किताब — प्राथमिकता उपाय</div>
            <?php if ($lkP !== []): ?>
                <?php foreach ($lkP as $i => $pp): ?>
                    <div class="text-sm"><b><?= $i + 1 ?>. <?= $h((string) $pp['hi']) ?></b>
                        <span class="text-xs text-gray-500">(<?= $h((string) $pp['house_ord']) ?> भाव · स्कोर <?= (int) $pp['score'] ?>)</span></div>
                <?php endforeach; ?>
                <?php $r1 = $lkP[0]['remedies'][0] ?? ($lkP[0]['sheeghra'] ?? ''); if ($r1 !== ''): ?>
                    <div class="text-xs" style="margin-top:4px;background:#fff7ed;border:1px solid #fed7aa;border-radius:7px;padding:5px 8px;color:#7c2d12">
                        🛠 पहला उपाय: <?= $h((string) $r1) ?></div>
                <?php endif; ?>
            <?php else: ?><div class="text-gray-400 italic text-sm">कोई गंभीर अशुभता नहीं — सामान्य उपाय पर्याप्त</div><?php endif; ?>
        </div>
    </div>

    <!-- 5. अगली घटनाएँ (timeline) -->
    <?php if ($ytEvs !== []): ?>
    <div style="margin-top:12px;border:1px solid #e5e7eb;border-radius:10px;padding:10px 13px">
        <div style="<?= $tHead ?>">📅 अगली घटनाएँ (12-Month Timeline से)</div>
        <?php foreach ($ytEvs as $e): ?>
            <div style="display:flex;gap:8px;align-items:flex-start;padding:2px 0">
                <span class="text-xs" style="min-width:74px;color:#64748b"><?= $h((string) $e['date']) ?></span>
                <span style="width:8px;height:8px;border-radius:50%;margin-top:5px;flex:none;background:<?= $e['tone'] === 'pos' ? '#22c55e' : ($e['tone'] === 'neg' ? '#ef4444' : '#3b82f6') ?>"></span>
                <span class="text-sm" style="color:#334155"><?= $h((string) $e['text']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
