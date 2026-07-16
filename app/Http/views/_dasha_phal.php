<?php
/**
 * दशा-फल pane — the "दशा-फल" option of the Varshaphal prediction dropdown.
 * Patyayini annual dasha with a दशा + अन्तर्दशा selector so the client can pick
 * any period and see its phal (strength-tier text + उपचय upgrade + Vamanacharya
 * antardasha grade + the bhavastha-graha layer). Scope: $view['dasha_phal']
 * (DashaPhalEngine::compute output + 'tz'), $h, $pcolor.
 *
 * Filters bound in calc-v2 JS ABBindVarshaPred: #dp-dasha, per-block #dp-antar.
 */
$dp = $view['dasha_phal'] ?? null;
$tz = (float) ($dp['tz'] ?? 0.0);
$JD = \AutoBusiness\Astro\Time\JulianDay::class;
$catHi = ['shubh' => 'शुभ', 'ashubh' => 'अशुभ', 'mrityu' => 'मृत्यु', 'mishrit' => 'मिश्रित', 'niyam' => 'नियम'];
$catChip = static fn(string $c): string => $c === 'shubh' ? 'gc-shubh' : ($c === 'mishrit' ? 'gc-mishrit' : ($c === 'mrityu' ? 'tb-mrityu' : 'gc-ashubh'));
$toneChip = static function (?bool $shubh): string {
    if ($shubh === null) { return '<span class="gc-chip gc-mishrit">बल से विचारें</span>'; }
    return $shubh ? '<span class="gc-chip gc-shubh">शुभ अन्तर्दशा</span>' : '<span class="gc-chip gc-ashubh">अशुभ अन्तर्दशा</span>';
};
if ($dp !== null && !empty($dp['periods'])):
    $ctx = $dp['context'];
    $periods = $dp['periods'];
    $runD = (int) ($dp['running']['dasha'] ?? 0);
?>
    <div class="saham-active">
        <div><b>वर्षेश:</b> <b style="color:#b45309"><?= $h($ctx['varshesh_hi']) ?></b> ·
            <b>वर्ष-लग्न:</b> <?= $h($ctx['varsha_lagna_sign']) ?> ·
            <b>दशा-प्रणाली:</b> पात्यायिनी (अंश-क्रम) ·
            <b>चालू दशा:</b> <b style="color:#15803d"><?= $h($periods[$runD]['lord_hi']) ?></b></div>
        <div class="text-xs text-gray-500" style="margin-top:3px">बल-स्तर पंचवर्गीय बल से (पूर्ण ≥<?= $h((string) $ctx['thresholds']['purna']) ?> · मध्य ≥<?= $h((string) $ctx['thresholds']['madhya']) ?> · हीन ≥<?= $h((string) $ctx['thresholds']['hina']) ?> · नष्ट <)। दशा चुनकर फल देखें।</div>
    </div>

    <!-- दशा चुनें — options carry the mudda-dasha date range; in dasha series
         order (same as the clickable list shown at the bottom). -->
    <div class="pred-picker" style="margin-top:10px;gap:8px">
        <label class="pred-picker-label" for="dp-dasha">दशा चुनें</label>
        <select id="dp-dasha" class="pred-inline-select" size="1">
            <?php foreach ($periods as $i => $d): ?>
                <option value="<?= (int) $i ?>"<?= $i === $runD ? ' selected' : '' ?>><?= $h($d['lord_hi']) ?> दशा<?= $i === $runD ? ' (चालू)' : '' ?> · <?= $h($JD::toDmy((float) $d['start_jd'], $tz)) ?> – <?= $h($JD::toDmy((float) $d['end_jd'], $tz)) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- No inner scroll: content (incl. the दशा-क्रम list) flows into the single
         outer prediction scrollbar so there is only one scrollbar, not two. -->
    <div id="dp-detail-pane" class="pr-1" style="margin-top:8px">
        <?php foreach ($periods as $i => $d):
            $runA = $i === $runD ? (int) ($dp['running']['antar'] ?? 0) : 0; ?>
        <div class="dp-block<?= $i === $runD ? '' : ' hidden' ?>" data-dasha="<?= (int) $i ?>">
            <!-- Dasha phal card -->
            <div class="saham-card" style="border-left:4px solid <?= $d['tone'] === 'pos' ? '#15803d' : ($d['tone'] === 'neg' ? '#b91c1c' : '#1d4ed8') ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($d['lord'] === 'Lagna' ? 'Sun' : $d['lord']) ?>"><?= $h($d['lord_hi']) ?> दशा</span>
                    <span class="gph-house">बल <?= $h((string) $d['bala']) ?>/20 · <?= $h($d['tier_hi']) ?></span>
                    <span class="gc-chip <?= $catChip($d['cat']) ?>"><?= $h($catHi[$d['cat']] ?? $d['cat']) ?></span>
                    <span class="text-xs text-gray-500"><?= $h($JD::toDmy((float) $d['start_jd'], $tz)) ?> – <?= $h($JD::toDmy((float) $d['end_jd'], $tz)) ?> · श्लोक <?= $h($d['shloka']) ?></span>
                </div>
                <?php if (($d['lord'] ?? '') === 'Lagna'): ?>
                <div class="text-xs text-gray-500" style="margin-top:2px">लग्नेश <b><?= $h($d['lagnesh_hi']) ?></b> के बल से फल।</div>
                <?php endif; ?>
                <div class="saham-phal">● <?= $h($d['phal']) ?></div>
                <?php if (!empty($d['upgrade'])): ?><div class="gph-note gph-pos"><?= $h($d['upgrade']['text']) ?></div><?php endif; ?>
                <?php if (!empty($d['dreshkana'])): ?><div class="gph-note gph-info"><?= $h($d['dreshkana']) ?></div><?php endif; ?>
            </div>

            <!-- Antardasha selector + cards -->
            <div class="pred-picker" style="margin-top:8px;gap:8px">
                <label class="pred-picker-label">अन्तर्दशा</label>
                <select class="pred-inline-select dp-antar-sel" size="1" data-dasha="<?= (int) $i ?>">
                    <?php foreach ($d['antars'] as $ai => $a): ?>
                        <option value="<?= (int) $ai ?>"<?= $ai === $runA ? ' selected' : '' ?>><?= $h($a['lord_hi']) ?><?= $ai === $runA && $i === $runD ? ' (चालू)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php foreach ($d['antars'] as $ai => $a): ?>
            <div class="saham-card dp-antar-card<?= $ai === $runA ? '' : ' hidden' ?>" data-dasha="<?= (int) $i ?>" data-antar="<?= (int) $ai ?>" style="background:#fbfcfe">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($a['lord'] === 'Lagna' ? 'Sun' : $a['lord']) ?>"><?= $h($d['lord_hi']) ?> / <?= $h($a['lord_hi']) ?></span>
                    <?= $toneChip($a['shubh']) ?>
                    <?php if (!empty($a['bala_tier'])): ?><span class="gph-house"><?= $h($a['bala_tier']) ?></span><?php endif; ?>
                    <span class="text-xs text-gray-500"><?= $h($JD::toDmy((float) $a['start_jd'], $tz)) ?> – <?= $h($JD::toDmy((float) $a['end_jd'], $tz)) ?></span>
                </div>
                <div class="saham-phal">●
                    <?php if ($a['self']): ?>पाकपति (दशापति की प्रथम अन्तर्दशा) — फल दशापति के बल के अनुसार।
                    <?php elseif ($a['shubh'] === true): ?>वामनाचार्य-मत से यह शुभ अन्तर्दशा — <?= $h($d['lord_hi']) ?> दशा का शुभ फल प्रबल; अन्तर्दशेश के बल व शुभ-दृष्टि से मात्रा बढ़ती है।
                    <?php elseif ($a['shubh'] === false): ?>वामनाचार्य-मत से यह अशुभ अन्तर्दशा — इस अवधि में सावधानी; अन्तर्दशेश शुभग्रहों से दृष्ट/मैत्री-युक्त हो तो कष्ट घटता है।
                    <?php else: ?>लग्न-दशा की अन्तर्दशा — अन्तर्दशेश के बल व शुभ-दृष्टि से फल का विचार करें।<?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <!-- Bhavastha-graha layer (constant for the year, additive to every dasha). -->
        <?php if (!empty($dp['bhavastha'])): ?>
        <div class="gph-section-title" style="margin-top:12px">भावस्थ ग्रह फल <span class="text-xs text-gray-400 font-normal">(वर्ष-भर लागू · श्लोक ५०–६१; प्रत्येक दशा में जुड़ता है)</span></div>
        <?php foreach ($dp['bhavastha'] as $b): ?>
        <div class="saham-card">
            <div class="saham-card-head">
                <span class="saham-name" style="font-size:12.5px;color:#334155"><?= $h($b['id']) ?> · भाव <?= (int) $b['house'] ?></span>
                <span class="gc-chip <?= $catChip($b['cat']) ?>"><?= $h($catHi[$b['cat']] ?? $b['cat']) ?></span>
            </div>
            <div class="saham-phal">● <?= $h($b['phal']) ?></div>
            <?php if (!empty($b['note'])): ?><div class="gph-note gph-info"><?= $h($b['note']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <p class="text-xs text-gray-400" style="margin-top:8px">पात्यायिनी दशा = वर्ष-लग्न व ग्रहों के भुक्तांश से; बल-स्तर पंचवर्गीय बल से; उपचय/इतर-स्थान में फल एक स्तर ऊपर। अन्तर्दशा-श्रेणी वामनाचार्य-सारिणी से। स्रोत: ताजिक नीलकण्ठी, दशाफलाध्याय।</p>
    </div>

    <!-- सम्पूर्ण दशा-क्रम — clickable list (mudda-dasha series order) at the
         bottom of the prediction; click a row to open that dasha above. -->
    <div class="gph-section-title" style="margin-top:12px">सम्पूर्ण दशा-क्रम <span class="text-xs text-gray-400 font-normal">(मुद्दा दशा क्रम · क्लिक करें)</span></div>
    <div class="dp-timeline" style="margin-top:6px">
        <?php foreach ($periods as $i => $d): $tclr = $d['tone'] === 'pos' ? '#15803d' : ($d['tone'] === 'neg' ? '#b91c1c' : '#1d4ed8'); ?>
        <button type="button" class="dp-trow<?= $i === $runD ? ' dp-now' : '' ?>" data-goto="<?= (int) $i ?>">
            <span class="dp-dot" style="background:<?= $tclr ?>"></span>
            <b style="color:<?= $pcolor($d['lord'] === 'Lagna' ? 'Sun' : $d['lord']) ?>"><?= $h($d['lord_hi']) ?></b>
            <span class="dp-trow-dates"><?= $h($JD::toDmy((float) $d['start_jd'], $tz)) ?> – <?= $h($JD::toDmy((float) $d['end_jd'], $tz)) ?></span>
            <span class="dp-trow-days"><?= (int) round($d['days']) ?> दिन</span>
        </button>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🗓️</div>
        <div class="gps-title">दशा-फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($dp['error'] ?? 'वर्षफल गणना के बाद यहाँ पात्यायिनी दशा-अन्तर्दशा के फल दिखेंगे।')) ?></div>
    </div>
<?php endif; ?>
