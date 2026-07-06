<?php
/**
 * गोचर फल pane — the Gochar prediction panel (right of the transit chart).
 * Rendered by the calc/gochar JSON endpoint and injected by gochar.js each
 * time the transit date/place changes. Scope: $gp (GocharPhalEngine::compute
 * output), $h, $pcolor, $grahaHi, $rashiHi.
 *
 * A "श्रेणी" (category) dropdown switches between the prediction categories:
 * चन्द्र-लग्न भाव-फल · अष्टकवर्ग बिन्दु-फल · कक्षा-फल · सर्वाष्टकवर्ग संकेत ·
 * जन्म-ग्रह पर गोचर · शनि विशेष. The "ग्रह" dropdown filters within.
 */
$rashiNames = \AutoBusiness\Astro\Calc\Charts::SIGNS;
$toneChip = static function (string $t) use ($h): string {
    return $t === 'pos' ? '<span class="gc-chip gc-shubh">शुभ</span>'
        : ($t === 'neg' ? '<span class="gc-chip gc-ashubh">अशुभ</span>'
        : '<span class="gc-chip gc-mishrit">फल रुका</span>');
};
$noteCls = static fn(string $t): string => $t === 'pos' ? 'gph-pos' : ($t === 'neg' ? 'gph-neg' : 'gph-info');
$av = $gp['av'] ?? ['bindu' => [], 'kaksha' => [], 'sav' => []];
$ss = $gp['shani_special'] ?? null;
$hasAny = $gp !== null && (!empty($gp['layer1']) || !empty($gp['layer3']) || !empty($av['bindu']) || $ss !== null);
if ($hasAny):
    $l1 = $gp['layer1'] ?? [];
    $l3 = $gp['layer3'] ?? [];
    $moonSignName = $rashiNames[(int) ($gp['moon_sign'] ?? 0)] ?? '';
    $moonSignHi = $rashiHi[$moonSignName] ?? $moonSignName;
    // Category list — only those actually present are offered.
    $cats = [];
    if ($l1 !== []) { $cats['bhava'] = 'चन्द्र-लग्न भाव-फल'; }
    if (!empty($av['bindu'])) { $cats['bindu'] = 'अष्टकवर्ग बिन्दु-फल'; }
    if (!empty($av['kaksha'])) { $cats['kaksha'] = 'कक्षा-फल'; }
    if (!empty($av['sav'])) { $cats['sav'] = 'सर्वाष्टकवर्ग संकेत'; }
    if ($l3 !== []) { $cats['natal'] = 'जन्म-ग्रह पर गोचर'; }
    if ($ss !== null) { $cats['shani'] = 'शनि विशेष (साढ़े साती)'; }
?>
    <div class="saham-active">
        <div><b>गोचर आधार:</b> चन्द्र लग्न (जन्म राशि) <b style="color:<?= $pcolor('Moon') ?>"><?= $h($moonSignHi) ?></b> से गिना गया·
            <span class="text-xs text-gray-500"><?= ($gp['moon_ksheen'] ?? false) ? 'गोचर चन्द्र क्षीण' : 'गोचर चन्द्र बली' ?></span></div>
        <div class="text-xs text-gray-500" style="margin-top:3px">भाव-फल · अष्टकवर्ग बिन्दु व कक्षा · सर्वाष्टकवर्ग · जन्म-ग्रहों पर गोचर · शनि साढ़े साती — नीचे "श्रेणी" से चुनें।</div>
    </div>

    <div class="pred-picker" style="margin-top:8px;gap:8px">
        <label class="pred-picker-label" for="gochar-cat">श्रेणी चुनें</label>
        <select id="gochar-cat" class="pred-inline-select" size="1">
            <option value="all">सभी श्रेणियाँ (All)</option>
            <?php foreach ($cats as $ck => $cl): ?><option value="<?= $h($ck) ?>"><?= $h($cl) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="pred-picker" style="margin-top:6px">
        <label class="pred-picker-label" for="gochar-select">ग्रह चुनें</label>
        <select id="gochar-select" class="pred-inline-select" size="1">
            <option value="all">सभी ग्रह (All)</option>
            <?php foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'] as $pk): ?>
                <option value="<?= $h($pk) ?>"><?= $h($grahaHi[$pk] ?? $pk) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="gochar-detail-pane" class="overflow-y-auto pr-1" style="max-height:460px">

        <!-- CATEGORY: शनि विशेष (Layer 4) -->
        <?php if ($ss !== null): $sevTone = static fn(int $s): string => $s <= 1 ? 'gc-shubh' : ($s === 2 ? 'gc-mishrit' : 'gc-ashubh'); ?>
        <div class="gochar-cat" data-cat="shani">
            <div class="gph-section-title">शनि विशेष <span class="text-xs text-gray-400 font-normal">(साढ़े साती / ढैय्या / पंचम + पाया)</span></div>
            <?php if (!empty($ss['active'])): ?>
            <div class="saham-card gochar-card" data-planet="Saturn" style="border-left:4px solid #1d4ed8">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#1d4ed8">🪐 <?= $h($ss['type_hi']) ?></span>
                    <span class="gc-chip <?= $sevTone((int) $ss['severity']) ?>">तीव्रता: <?= $h($ss['severity_hi']) ?> (<?= (int) $ss['severity'] ?>/3)</span>
                    <span class="text-xs text-gray-500">शनि चन्द्र-राशि से <?= (int) $ss['house'] ?>वें · <?= (int) $ss['occurrence'] ?>री बार · नक्षत्र-स्वामी <?= $h($ss['nak_lord']) ?></span>
                </div>
                <?php if (!empty($ss['phal'])): ?><div class="saham-phal">● <?= $h($ss['phal']) ?></div><?php endif; ?>
                <?php if (!empty($ss['general'])): ?><div class="saham-facts"><?= $h($ss['general']) ?></div><?php endif; ?>
                <?php foreach (($ss['severity_why'] ?? []) as $w): ?><div class="gph-note gph-info"><?= $h($w) ?></div><?php endforeach; ?>
                <?php if (!empty($ss['events'])): ?>
                <div class="saham-facts" style="margin-top:4px">प्रभावित जन्म-ग्रह (युति/दृष्टि):
                    <?php foreach ($ss['events'] as $ev): ?><b style="color:<?= $pcolor($ev['planet']) ?>"><?= $h($ev['planet_hi']) ?></b> (<?= $h($ev['how']) ?>) <?php endforeach; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($ss['paya'])): $py = $ss['paya']; ?>
            <div class="saham-card gochar-card" data-planet="Saturn">
                <div class="saham-card-head"><span class="saham-name">शनि पाया — <?= $h($py['metal']) ?></span>
                    <span class="gc-chip <?= $py['shubh'] ? 'gc-shubh' : 'gc-ashubh' ?>"><?= $py['shubh'] ? 'शुभ' : 'अशुभ' ?></span></div>
                <div class="saham-phal">● <?= $h($py['effect']) ?></div>
                <div class="gph-note gph-info">पाया शनि के राशि-प्रवेश का स्वतन्त्र संकेत — भाव-फल से अलग; अंक जोड़े नहीं जाते।</div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- CATEGORY: चन्द्र-लग्न भाव-फल (Layer 1) -->
        <?php if ($l1 !== []): ?>
        <div class="gochar-cat" data-cat="bhava">
            <div class="gph-section-title">चन्द्र-लग्न भाव-फल <span class="text-xs text-gray-400 font-normal">(भाव-गोचर + वेध / बिन्दु / तृतीयांश)</span></div>
            <?php foreach ($l1 as $e): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($e['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($e['planet']) ?>"><?= $h($e['planet_hi']) ?></span>
                    <span class="gph-house">भाव <?= (int) $e['house'] ?></span>
                    <?= $toneChip($e['tone']) ?>
                    <span class="text-xs text-gray-500"><?= $h($e['deg']) ?><?= $e['retro'] ? ' <sup style="color:#b91c1c">®</sup>' : '' ?></span>
                </div>
                <div class="saham-phal">● <?= $h($e['text']) ?></div>
                <?php foreach (($e['notes'] ?? []) as $n): ?><div class="gph-note <?= $noteCls($n['t']) ?>"><?= $h($n['text']) ?></div><?php endforeach; ?>
                <div class="gph-shubh">शुभ भाव (इस ग्रह के): <?= $h(implode(', ', $e['shubh_houses'] ?? [])) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CATEGORY: अष्टकवर्ग बिन्दु-फल (Part 1) -->
        <?php if (!empty($av['bindu'])): ?>
        <div class="gochar-cat" data-cat="bindu">
            <div class="gph-section-title">अष्टकवर्ग बिन्दु-फल <span class="text-xs text-gray-400 font-normal">(गोचर-राशि में ग्रह के अपने अष्टकवर्ग-बिन्दु 0–8)</span></div>
            <?php foreach ($av['bindu'] as $b): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($b['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($b['planet']) ?>"><?= $h($b['planet_hi']) ?></span>
                    <span class="gph-house"><?= $h($b['sign_hi']) ?> · <?= (int) $b['bindu'] ?>/8 बिन्दु</span>
                    <?= $toneChip($b['tone']) ?>
                </div>
                <div class="saham-phal">● <?= $h($b['phal']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CATEGORY: कक्षा-फल (Part 2) -->
        <?php if (!empty($av['kaksha'])): ?>
        <div class="gochar-cat" data-cat="kaksha">
            <div class="gph-section-title">कक्षा-फल <span class="text-xs text-gray-400 font-normal">(ग्रह जिस कक्षा में — स्वामी की बिन्दु/रेखा से)</span></div>
            <?php foreach ($av['kaksha'] as $k): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($k['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($k['planet']) ?>"><?= $h($k['planet_hi']) ?></span>
                    <span class="gph-house">कक्षा <?= (int) $k['kaksha_no'] ?> — <?= $h($k['lord_hi']) ?></span>
                    <span class="gc-chip <?= $k['tone'] === 'pos' ? 'gc-shubh' : 'gc-ashubh' ?>"><?= $k['kind'] === 'shubh' ? 'बिन्दु (शुभ)' : 'रेखा (अशुभ)' ?></span>
                </div>
                <div class="saham-phal">● <?= $h($k['phal']) ?></div>
                <?php if (empty($k['applicable'])): ?><div class="gph-note gph-info">⚠ गोचर ग्रह नीच/अस्त/शत्रु-राशि में — कक्षा-फल पूर्णतः लागू नहीं (नियम-शर्त)।</div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CATEGORY: सर्वाष्टकवर्ग संकेत (Part 3) -->
        <?php if (!empty($av['sav'])): ?>
        <div class="gochar-cat" data-cat="sav">
            <div class="gph-section-title">सर्वाष्टकवर्ग संकेत <span class="text-xs text-gray-400 font-normal">(गोचर-राशि का SAV; सीमा 28)</span></div>
            <?php foreach ($av['sav'] as $s): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($s['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($s['planet']) ?>"><?= $h($s['planet_hi']) ?></span>
                    <span class="gph-house"><?= $h($s['sign_hi']) ?> · भाव <?= (int) $s['house'] ?> · SAV <?= (int) $s['sav'] ?></span>
                    <span class="gc-chip <?= $s['tone'] === 'pos' ? 'gc-shubh' : 'gc-ashubh' ?>"><?= $s['sav'] >= 28 ? 'शुभ (≥28)' : 'अशुभ (<28)' ?></span>
                </div>
                <div class="saham-phal">● <?= $h($s['hint']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- CATEGORY: जन्म-ग्रह पर गोचर (Layer 3) -->
        <?php if ($l3 !== []): ?>
        <div class="gochar-cat" data-cat="natal">
            <div class="gph-section-title">जन्म-ग्रहों पर गोचर <span class="text-xs text-gray-400 font-normal">(गोचर × जन्म-ग्रह)</span></div>
            <?php foreach ($l3 as $grp): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($grp['transit']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($grp['transit']) ?>"><?= $h($grp['transit_hi']) ?></span>
                    <span class="gc-chip <?= $grp['nature'] === 'आसुरी' ? 'gc-ashubh' : 'gc-shubh' ?>"><?= $h($grp['nature']) ?></span>
                    <?php if (!empty($grp['reads_as'])): ?><span class="saham-tag dup"><?= $h($grp['reads_as']) ?>वत् फल</span><?php endif; ?>
                </div>
                <?php foreach ($grp['events'] as $ev): ?>
                <div class="gph-l3ev">
                    <div class="gph-l3head">जन्म <b style="color:<?= $pcolor($ev['natal']) ?>"><?= $h($ev['natal_hi']) ?></b> पर <b><?= $h($ev['geometry']) ?></b></div>
                    <?php foreach ($ev['rows'] as $i => $r): ?>
                        <div class="saham-phal"><?= $i === 0 ? '●' : '◦' ?>
                            <?php if (!empty($r['cond']) && !in_array(trim($r['cond']), ['—', 'सामान्य (default)'], true)): ?><b class="gph-cond"><?= $h($r['cond']) ?>:</b> <?php endif; ?>
                            <?= $h($r['text']) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endforeach; ?>
                <?php foreach (($grp['all_notes'] ?? []) as $an): ?><div class="gph-note gph-info"><?= $h($an) ?></div><?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div id="gochar-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">
            इस चयन के लिए कोई गोचर-फल नहीं — "सभी श्रेणियाँ" / "सभी ग्रह" चुनें।
        </div>
        <p class="text-xs text-gray-400" style="margin-top:8px">फल-क्रम: भाव-फल → वेध → बिन्दु → तृतीयांश। बिन्दु/कक्षा/SAV गोचर-राशि पर जन्म-अष्टकवर्ग से। राहु शनिवत्, केतु मंगलवत्।</p>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🔮</div>
        <div class="gps-title">गोचर फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($gp['error'] ?? 'गोचर गणना के बाद यहाँ भाव-फल, अष्टकवर्ग व जन्म-ग्रहों पर गोचर दिखेगा।')) ?></div>
    </div>
<?php endif; ?>
