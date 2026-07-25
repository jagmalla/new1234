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
/** tile-head opener: accent colour + light tint + emoji + title + english + badge. */
$tgHead = static function (string $accent, string $bg, string $emoji, string $title, string $en, string $badge) use ($h): string {
    return '<div class="tg-head"><span class="tg-ic">' . $emoji . '</span>'
        . '<div class="tg-hh"><div class="tg-tt">' . $h($title) . '</div>'
        . ($en !== '' ? '<div class="tg-en">' . $h($en) . '</div>' : '') . '</div>'
        . ($badge !== '' ? '<span class="tg-badge">' . $h($badge) . '</span>' : '') . '</div>';
};
?>
<style>
.tg-dash{--r:#dc2626;}
.tg-dash .tg-top{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px}
.tg-dash .tg-logo{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:1.35rem;
  background:linear-gradient(135deg,#faf5ff,#eef2ff);border:1px solid #e9d5ff}
.tg-dash h2{font-size:1.18rem;font-weight:800;color:#1f2937;margin:0;line-height:1.2}
.tg-dash .tg-sub{font-size:.76rem;color:#94a3b8;margin-top:1px}
.tg-dash .tg-date{margin-left:auto;font-size:.8rem;font-weight:700;color:#6d28d9;background:#f5f3ff;
  border:1px solid #e9d5ff;border-radius:999px;padding:5px 14px;white-space:nowrap}
.tg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(272px,1fr));gap:14px}
.tg-card{background:#fff;border:1px solid #eef0f3;border-radius:14px;box-shadow:0 1px 2px rgba(16,24,40,.05);
  overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .15s,transform .15s}
.tg-card:hover{box-shadow:0 6px 18px rgba(16,24,40,.09);transform:translateY(-1px)}
.tg-head{display:flex;align-items:center;gap:9px;padding:10px 13px;border-bottom:1px solid #f1f3f6;background:var(--tg-bg)}
.tg-ic{width:31px;height:31px;border-radius:9px;display:grid;place-items:center;font-size:1.02rem;flex:none;
  background:#fff;border:1px solid var(--tg-accent)}
.tg-hh{min-width:0}
.tg-tt{font-weight:800;font-size:.92rem;color:var(--tg-accent);line-height:1.15}
.tg-en{font-size:.64rem;color:#94a3b8;font-weight:600;letter-spacing:.02em}
.tg-badge{margin-left:auto;font-size:.6rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;
  background:#fff;border:1px solid var(--tg-accent);color:var(--tg-accent);border-radius:999px;padding:3px 9px;flex:none}
.tg-body{padding:11px 13px;display:flex;flex-direction:column;gap:6px;flex:1}
.tg-row{font-size:.87rem;color:#374151;line-height:1.4}
.tg-row b{font-weight:700}
.tg-sm{font-size:.74rem;color:#64748b}
.tg-empty{color:#9ca3af;font-style:italic;font-size:.83rem}
.tg-pill{display:inline-block;font-size:.68rem;font-weight:700;border-radius:999px;padding:1px 9px}
.tg-pos{background:#dcfce7;color:#166534}.tg-neg{background:#fee2e2;color:#991b1b}.tg-mix{background:#fef9c3;color:#854d0e}
.tg-lead{font-size:.9rem;font-weight:700;color:#374151;line-height:1.4}
.tg-rem{font-size:.75rem;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:6px 9px;color:#7c2d12;margin-top:2px}
/* timeline */
.tg-tl{margin-top:14px;background:#fff;border:1px solid #eef0f3;border-radius:14px;box-shadow:0 1px 2px rgba(16,24,40,.05);overflow:hidden}
.tg-tl-body{padding:6px 14px 12px}
.tg-ev{display:flex;gap:11px;align-items:flex-start;padding:8px 0;border-bottom:1px dashed #eef0f3}
.tg-ev:last-child{border-bottom:0}
.tg-ev-date{min-width:86px;font-size:.74rem;font-weight:700;color:#475569;background:#f8fafc;border:1px solid #eef0f3;
  border-radius:7px;padding:3px 8px;text-align:center;flex:none}
.tg-ev-dot{width:9px;height:9px;border-radius:50%;margin-top:6px;flex:none}
.tg-ev-tx{font-size:.86rem;color:#334155;line-height:1.4}
</style>

<div class="tg-dash bg-white rounded-lg shadow p-4 md:p-5">
    <div class="tg-top">
        <span class="tg-logo">🗓️</span>
        <div>
            <h2>आज का Consult <span style="color:#94a3b8;font-weight:600;font-size:.9rem">— Today at a Glance</span></h2>
            <div class="tg-sub">सभी प्रणालियों में आज क्या सक्रिय है — एक नज़र में</div>
        </div>
        <span class="tg-date">📅 <?= $h(date('d-m-Y')) ?></span>
    </div>

    <div class="tg-grid">

        <!-- 1. चालू विंशोत्तरी दशा -->
        <div class="tg-card" style="--tg-accent:#6d28d9;--tg-bg:#f6f3fe">
            <?= $tgHead('#6d28d9', '#f6f3fe', '⏳', 'चालू विंशोत्तरी दशा', 'Running Dasha', 'dasha') ?>
            <div class="tg-body">
            <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null): ?>
                <?php $mD = $dashaNow['maha']; $aD = $dashaNow['antar'] ?? null; $pD = $dashaNow['pratyantar'] ?? null; ?>
                <div class="tg-row"><b style="color:<?= $pcolor((string) $mD['lord']) ?>"><?= $h($grahaHiT[$mD['lord']] ?? $mD['lord']) ?></b> महादशा
                    <span class="tg-sm">(<?= $h($dmyT((float) $mD['end_jd'])) ?> तक)</span></div>
                <?php if ($aD !== null): ?>
                <div class="tg-row">↳ <b style="color:<?= $pcolor((string) $aD['lord']) ?>"><?= $h($grahaHiT[$aD['lord']] ?? $aD['lord']) ?></b> अंतर्दशा
                    <span class="tg-sm">(<?= $h($dmyT((float) $aD['end_jd'])) ?> तक)</span></div>
                <?php endif; ?>
                <?php if ($pD !== null): ?>
                <div class="tg-row" style="padding-left:12px">↳ <b style="color:<?= $pcolor((string) $pD['lord']) ?>"><?= $h($grahaHiT[$pD['lord']] ?? $pD['lord']) ?></b> प्रत्यंतर
                    <span class="tg-sm">(<?= $h($dmyT((float) $pD['end_jd'])) ?> तक)</span></div>
                <?php endif; ?>
                <?php $sm = $view['strength']['planets'][$mD['lord']] ?? null; if ($sm !== null): ?>
                    <div class="tg-sm" style="margin-top:2px">महादशा-स्वामी का फल-बल:
                        <span class="tg-pill <?= $sm['tier'] === 'pos' ? 'tg-pos' : ($sm['tier'] === 'neg' ? 'tg-neg' : 'tg-mix') ?>"><?= $h((string) $sm['word']) ?></span></div>
                <?php endif; ?>
            <?php else: ?><div class="tg-empty">दशा उपलब्ध नहीं</div><?php endif; ?>
            </div>
        </div>

        <!-- 2. आज का गोचर -->
        <div class="tg-card" style="--tg-accent:#0d9488;--tg-bg:#effcfa">
            <?= $tgHead('#0d9488', '#effcfa', '🌌', 'आज का गोचर', "Today's Transit", 'gochar') ?>
            <div class="tg-body">
            <?php if ($ug !== null): ?>
                <?php
                $combNow = (array) ($ug['combust_parts']['current'] ?? []);
                $retroNow = (array) ($ug['retro_parts']['current'] ?? []);
                $topEv = array_slice((array) ($ug['top_events'] ?? []), 0, 3);
                $toneCol = ['good' => '#15803d', 'warn' => '#b45309', 'move' => '#1d4ed8'];
                ?>
                <?php $ssU = $ug['sade_sati'] ?? null; if ($ssU !== null): ?>
                    <div class="tg-row" style="color:<?= !empty($ssU['active']) ? '#b91c1c' : '#475569' ?>">
                        🪐 <b><?= $h((string) $ssU['kind']) ?></b> <?= !empty($ssU['active']) ? 'चल रही है' : 'आगामी' ?>
                        <span class="tg-sm">(<?= $h((string) $ssU['start']) ?> – <?= $h((string) $ssU['end']) ?>)</span></div>
                <?php endif; ?>
                <?php if ($combNow !== []): foreach ($combNow as $cN): ?>
                    <div class="tg-row" style="color:#b91c1c"><?= $h((string) $cN['emoji']) ?> <b><?= $h((string) $cN['planet']) ?></b> अस्त (<?= $h((string) $cN['sign_hi']) ?>)<?php if (!empty($cN['end'])): ?> — उदय <b><?= $h((string) $cN['end']) ?></b><?php endif; ?></div>
                <?php endforeach; endif; ?>
                <?php if ($retroNow !== []): ?>
                    <div class="tg-row" style="color:#6d28d9">↩️ अभी वक्री: <?= $h(implode(', ', array_map(static fn ($r) => (string) $r['emoji'] . ' ' . (string) $r['planet'] . (!empty($r['end']) ? ' (मार्गी ' . $r['end'] . ')' : ''), $retroNow))) ?></div>
                <?php endif; ?>
                <?php foreach ($topEv as $eU): ?>
                    <div class="tg-row" style="color:<?= $toneCol[$eU['tone']] ?? '#334155' ?>"><?= $h((string) $eU['emoji']) ?> <?= $h((string) $eU['text']) ?> — <b><?= $h((string) $eU['date']) ?></b> <span class="tg-sm">(<?= (int) $eU['days'] ?> दिन)</span></div>
                <?php endforeach; ?>
                <?php if ($combNow === [] && $retroNow === [] && $topEv === []): ?>
                    <div class="tg-empty">कोई प्रमुख गोचर घटना नहीं</div>
                <?php endif; ?>
            <?php else: ?><div class="tg-empty">गोचर सारांश उपलब्ध नहीं</div><?php endif; ?>
            </div>
        </div>

        <!-- 3. आज का मुहूर्त — शुभता संकेत-पट्टी -->
        <?php $tmu = $view['today_muhurat'] ?? null; if ($tmu !== null && !empty($tmu['ok'])):
            $tmuScore = \AutoBusiness\Astro\Muhurat\Auspiciousness::score((string) $tmu['tone'], $tmu['dosha'] ?? []); ?>
        <div class="tg-card" style="--tg-accent:#b45309;--tg-bg:#fff7ed">
            <?= $tgHead('#b45309', '#fff7ed', '🎯', 'आज का मुहूर्त', 'Panchang Shuddhi', 'muhurat') ?>
            <div class="tg-body">
                <div class="tg-lead"><?= $h((string) $tmu['verdict']) ?></div>
                <?= \AutoBusiness\Astro\Muhurat\Auspiciousness::barHtml($tmuScore, (string) $tmu['grade']) ?>
                <div class="tg-sm">वार <b><?= $h((string) ($tmu['panchang']['vaar'] ?? '')) ?></b> · नक्षत्र <b><?= $h((string) ($tmu['panchang']['nakshatra'] ?? '')) ?></b> · तिथि <b><?= $h((string) ($tmu['panchang']['tithi'] ?? '')) ?></b></div>
                <?php $tmuTop = $tmu['shubh'][0] ?? ($tmu['dosha'][0] ?? null); if ($tmuTop !== null): ?>
                    <div class="tg-sm" style="color:<?= empty($tmu['shubh']) ? '#b45309' : '#15803d' ?>"><?= empty($tmu['shubh']) ? '⚠️' : '✓' ?> <?= $h((string) $tmuTop['name']) ?></div>
                <?php endif; ?>
                <div class="tg-sm" style="color:#94a3b8">पूर्ण श्रेणी-वार मुहूर्त हेतु <b>मुहूर्त</b> मेनू देखें।</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 4. वर्षफल — आज -->
        <div class="tg-card" style="--tg-accent:#c026d3;--tg-bg:#fdf4ff">
            <?= $tgHead('#c026d3', '#fdf4ff', '🎯', 'वर्षफल — आज', 'Annual (Varshaphal)', 'annual') ?>
            <div class="tg-body">
            <?php if ($muddaNowT !== null): $mlT = (string) $muddaNowT['lord']; ?>
                <div class="tg-row">मुद्दा दशा: <b style="color:<?= $pcolor($mlT) ?>"><?= $h($grahaHiT[$mlT] ?? $mlT) ?></b>
                    <span class="tg-sm">(<?= $h($dmyT((float) $muddaNowT['start_jd'])) ?> – <?= $h($dmyT((float) $muddaNowT['end_jd'])) ?>)</span></div>
            <?php endif; ?>
            <?php $pvT = $vp['varshesh'] ?? null; if (!empty($pvT['lord'])): ?>
                <div class="tg-row">वर्षेश: <b style="color:<?= $pcolor((string) $pvT['lord']) ?>"><?= $h($grahaHiT[$pvT['lord']] ?? $pvT['lord']) ?></b>
                    <span class="tg-sm">(पंचवर्गीय <?= $h((string) ($pvT['bala20'] ?? '')) ?>/20)</span></div>
            <?php endif; ?>
            <?php if ($mn !== null && !empty($mn['verdict'])): ?>
                <div class="tg-row">मुंथा: <?= (int) ($mn['muntha_house'] ?? 0) ?>वाँ भाव
                    <span class="tg-pill <?= ($mn['verdict_tone'] ?? '') === 'pos' ? 'tg-pos' : ((($mn['verdict_tone'] ?? '') === 'neg') ? 'tg-neg' : 'tg-mix') ?>"><?= $h((string) $mn['verdict']) ?></span></div>
            <?php endif; ?>
            <?php if ($muddaNowT === null && empty($pvT['lord'])): ?><div class="tg-empty">वर्षफल उपलब्ध नहीं</div><?php endif; ?>
            </div>
        </div>

        <!-- 5. लाल किताब — प्राथमिकता उपाय -->
        <div class="tg-card" style="--tg-accent:#dc2626;--tg-bg:#fef2f2">
            <?= $tgHead('#dc2626', '#fef2f2', '📕', 'लाल किताब — उपाय', 'Priority Remedies', 'remedy') ?>
            <div class="tg-body">
            <?php if ($lkP !== []): ?>
                <?php foreach ($lkP as $i => $pp): ?>
                    <div class="tg-row"><b><?= $i + 1 ?>. <?= $h((string) $pp['hi']) ?></b>
                        <span class="tg-sm">(<?= $h((string) $pp['house_ord']) ?> भाव · स्कोर <?= (int) $pp['score'] ?>)</span></div>
                <?php endforeach; ?>
                <?php $r1 = $lkP[0]['remedies'][0] ?? ($lkP[0]['sheeghra'] ?? ''); if ($r1 !== ''): ?>
                    <div class="tg-rem">🛠 पहला उपाय: <?= $h((string) $r1) ?></div>
                <?php endif; ?>
            <?php else: ?><div class="tg-empty">कोई गंभीर अशुभता नहीं — सामान्य उपाय पर्याप्त</div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 6. अगली घटनाएँ (timeline) -->
    <?php if ($ytEvs !== []): ?>
    <div class="tg-tl">
        <?= $tgHead('#334155', '#f8fafc', '📅', 'अगली घटनाएँ', '12-Month Timeline', 'timeline') ?>
        <div class="tg-tl-body">
        <?php foreach ($ytEvs as $e): ?>
            <div class="tg-ev">
                <span class="tg-ev-date"><?= $h((string) $e['date']) ?></span>
                <span class="tg-ev-dot" style="background:<?= $e['tone'] === 'pos' ? '#22c55e' : ($e['tone'] === 'neg' ? '#ef4444' : '#3b82f6') ?>"></span>
                <span class="tg-ev-tx"><?= $h((string) $e['text']) ?></span>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
