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
$mu = $gp['muhurat'] ?? null;
$hasAny = $gp !== null && (!empty($gp['layer1']) || !empty($gp['layer3']) || !empty($av['bindu']) || $ss !== null || $mu !== null);
if ($hasAny):
    $l1 = $gp['layer1'] ?? [];
    $l3 = $gp['layer3'] ?? [];
    $moonSignName = $rashiNames[(int) ($gp['moon_sign'] ?? 0)] ?? '';
    $moonSignHi = $rashiHi[$moonSignName] ?? $moonSignName;
    // Category list — only those actually present are offered.
    $cats = [];
    if ($l1 !== []) { $cats['bhava'] = 'Moon Ascendent (चन्द्र-लग्न)'; }
    if (!empty($av['bindu']) || !empty($av['kaksha']) || !empty($av['sav'])) { $cats['ashtak'] = 'Ashtakvarga (अष्टकवर्ग)'; }
    if ($ss !== null) { $cats['shani'] = 'Sade-Sati (साढ़े साती)'; }
    if ($mu !== null) { $cats['muhurat'] = 'Mahurat (मुहूर्त)'; }
    if ($l3 !== []) { $cats['natal'] = 'Natal Transit (जन्म-ग्रह पर गोचर)'; }
?>
    <div class="saham-active">
        <div><b>गोचर आधार:</b> चन्द्र लग्न (जन्म राशि) <b style="color:<?= $pcolor('Moon') ?>"><?= $h($moonSignHi) ?></b> से गिना गया·
            <span class="text-xs text-gray-500"><?= ($gp['moon_ksheen'] ?? false) ? 'गोचर चन्द्र क्षीण' : 'गोचर चन्द्र बली' ?></span></div>
        <div class="text-xs text-gray-500" style="margin-top:3px">भाव-फल · अष्टकवर्ग बिन्दु व कक्षा · सर्वाष्टकवर्ग · जन्म-ग्रहों पर गोचर · शनि साढ़े साती · मुहूर्त (राहु काल/दिशा शूल/तिथि) — नीचे "श्रेणी" से चुनें।</div>
    </div>

    <div class="pred-picker" style="margin-top:8px;gap:8px">
        <label class="pred-picker-label" for="gochar-cat">श्रेणी चुनें</label>
        <select id="gochar-cat" class="pred-inline-select" size="1">
            <option value="general" selected>General Overview</option>
            <?php foreach ($cats as $ck => $cl): ?><option value="<?= $h($ck) ?>"><?= $h($cl) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="pred-picker" id="gochar-planet-pick" style="margin-top:6px">
        <label class="pred-picker-label" for="gochar-select">ग्रह चुनें</label>
        <select id="gochar-select" class="pred-inline-select" size="1">
            <option value="all">सभी ग्रह (All)</option>
            <?php foreach (['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn', 'Rahu', 'Ketu'] as $pk): ?>
                <option value="<?= $h($pk) ?>"><?= $h($grahaHi[$pk] ?? $pk) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="gochar-detail-pane" class="overflow-y-auto pr-1" style="max-height:460px">

        <!-- CATEGORY: सामान्य (General summary — default) -->
        <?php
            $goodT = []; $badT = [];
            foreach ($l1 as $e) {
                $nm = $h($e['planet_hi']) . ' (भाव ' . (int) $e['house'] . ')';
                if (($e['tone'] ?? '') === 'pos') { $goodT[] = $nm; }
                elseif (($e['tone'] ?? '') === 'neg') { $badT[] = $nm; }
            }
            $gTone = count($goodT) >= count($badT) && ($ss === null || empty($ss['active'])) ? 'pos' : 'mix';
        ?>
        <?php
            // Per-category tones for the overview (pos=green, neg=red, neutral=green, mix=blue).
            $nGood = count($goodT); $nBad = count($badT);
            $bhavaTone = $nBad === 0 ? 'pos' : ($nGood >= $nBad ? 'mix' : 'neg');
            $avPos = 0; $avNeg = 0;
            foreach (['bindu', 'kaksha', 'sav'] as $t) { foreach (($av[$t] ?? []) as $r) { if (($r['tone'] ?? '') === 'pos') { $avPos++; } elseif (($r['tone'] ?? '') === 'neg') { $avNeg++; } } }
            $avHas = ($avPos + $avNeg) > 0;
            $avTone = $avNeg === 0 ? 'pos' : ($avPos >= $avNeg ? 'mix' : 'neg');
            $sadeActive = ($ss !== null && !empty($ss['active']));
            if (!$sadeActive && !empty($gp['sade_timeline']['periods'])) { foreach ($gp['sade_timeline']['periods'] as $pp) { if (($pp['status'] ?? '') === 'ACTIVE') { $sadeActive = true; break; } } }
            $sadeTone = $sadeActive ? 'neg' : 'pos';
            $muTithiTone = $mu['tithi']['tone'] ?? 'neutral';
            $muTone = $muTithiTone === 'neg' ? 'neg' : ($muTithiTone === 'pos' ? 'pos' : 'mix');
        ?>
        <div class="gochar-cat" data-cat="general">
            <div class="gph-section-title">सामान्य सारांश <span class="text-xs text-gray-400 font-normal">(हर श्रेणी का सार — क्लिक करें विस्तार हेतु)</span></div>
            <div class="saham-card gochar-card" data-planet="all">
                <div class="saham-phal">● <b>आधार:</b> जन्म-राशि <b style="color:<?= $pcolor('Moon') ?>"><?= $h($moonSignHi) ?></b> से गोचर देखा गया। गोचर चन्द्रमा अभी <?= ($gp['moon_ksheen'] ?? false) ? '<b style="color:#b91c1c">क्षीण (कमजोर)</b>' : '<b style="color:#15803d">बली (मजबूत)</b>' ?>।</div>
            </div>

            <?php if ($l1 !== []): ?>
            <div class="gov-card gov-<?= $bhavaTone ?>" data-gochar-jump="bhava" role="button" tabindex="0">
                <div class="gov-h">🌙 चन्द्र-लग्न भाव-फल</div>
                <div class="gov-sum"><?= $nGood ?> अनुकूल ग्रह-गोचर<?= $nBad ? ' · ' . $nBad . ' सावधानी योग्य' : '' ?>।<?= $goodT !== [] ? ' अनुकूल: ' . $h(implode(', ', array_slice($goodT, 0, 3))) . '।' : '' ?></div>
                <div class="gov-jump">भाव-फल विस्तार से देखें →</div>
            </div>
            <?php endif; ?>

            <?php if ($avHas): ?>
            <div class="gov-card gov-<?= $avTone ?>" data-gochar-jump="ashtak" role="button" tabindex="0">
                <div class="gov-h">🔢 अष्टकवर्ग (Ashtakvarga)</div>
                <div class="gov-sum"><?= $avPos ?> शुभ · <?= $avNeg ?> अशुभ संकेत — बिन्दु/कक्षा/सर्वाष्टकवर्ग अनुसार।</div>
                <div class="gov-jump">अष्टकवर्ग विस्तार से देखें →</div>
            </div>
            <?php endif; ?>

            <?php if ($ss !== null || !empty($gp['sade_timeline'])): ?>
            <div class="gov-card gov-<?= $sadeTone ?>" data-gochar-jump="shani" role="button" tabindex="0">
                <div class="gov-h">🪐 साढ़े साती / शनि ढैया</div>
                <div class="gov-sum"><?= $sadeActive
                    ? 'इस समय <b>साढ़े साती/ढैया चल रही है</b> — धैर्य व शनि-उपाय लाभकारी।'
                    : 'इस समय साढ़े साती/ढैया नहीं — इस दृष्टि से राहत।' ?></div>
                <div class="gov-jump">सम्पूर्ण timeline देखें →</div>
            </div>
            <?php endif; ?>

            <?php if ($mu !== null): ?>
            <div class="gov-card gov-<?= $muTone ?>" data-gochar-jump="muhurat" role="button" tabindex="0">
                <div class="gov-h">🕒 मुहूर्त</div>
                <div class="gov-sum">आज <b><?= $h($mu['weekday_hi'] ?? '') ?></b><?= !empty($mu['tithi']['name']) ? ' · तिथि ' . $h($mu['tithi']['name']) : '' ?><?= !empty($mu['rahu_kaal']['start']) ? ' · राहु काल ' . $h($mu['rahu_kaal']['start']) . '–' . $h($mu['rahu_kaal']['end'] ?? '') : '' ?>।</div>
                <div class="gov-jump">राहु काल · दिशा शूल · तिथि देखें →</div>
            </div>
            <?php endif; ?>

            <div class="saham-card gochar-card" data-planet="all" style="margin-top:6px">
                <div class="saham-phal"><b>निष्कर्ष:</b>
                    <?= $gTone === 'pos'
                        ? 'कुल मिलाकर गोचर अनुकूल — शुभ ग्रहों का प्रभाव अधिक। महत्त्वपूर्ण कार्यों हेतु समय ठीक है।'
                        : 'गोचर मिश्रित — कुछ अनुकूल, कुछ प्रतिकूल प्रभाव। बड़े निर्णय सोच-समझकर लें।' ?>
                </div>
            </div>
        </div>


        <!-- CATEGORY: शनि विशेष (Layer 4) -->
        <?php $hasSadeTl = !empty($gp['sade_timeline']); if ($ss !== null || $hasSadeTl): $sevTone = static fn(int $s): string => $s <= 1 ? 'gc-shubh' : ($s === 2 ? 'gc-mishrit' : 'gc-ashubh'); ?>
        <div class="gochar-cat" data-cat="shani">
            <div class="gph-section-title">साढ़े साती व शनि ढैया <span class="text-xs text-gray-400 font-normal">(सम्पूर्ण timeline — जन्म से भविष्य तक, 5-परत फल)</span></div>
            <?php if ($hasSadeTl) { require __DIR__ . '/_sade_timeline.php'; } ?>
            <?php if ($ss !== null): ?>
            <div class="gph-section-title" style="margin-top:12px;font-size:.9rem">वर्तमान शनि-स्नैपशॉट <span class="text-xs text-gray-400 font-normal">(तीव्रता · प्रभावित ग्रह · पाया)</span></div>
            <?php endif; ?>
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

        <!-- CATEGORY: अष्टकवर्ग (Ashtakvarga) — बिन्दु-फल + कक्षा-फल + सर्वाष्टकवर्ग, प्रति ग्रह एक साथ -->
        <?php if (!empty($av['bindu']) || !empty($av['kaksha']) || !empty($av['sav'])):
            // Merge the three Ashtakvarga sub-predictions grouped by planet, so each
            // planet shows its bindu, kaksha and SAV lines together in one card.
            $avSeq = []; $avMerge = [];
            foreach (['bindu', 'kaksha', 'sav'] as $t) {
                foreach (($av[$t] ?? []) as $row) {
                    $p = $row['planet'] ?? '';
                    if ($p === '') { continue; }
                    if (!isset($avMerge[$p])) { $avMerge[$p] = ['bindu' => null, 'kaksha' => null, 'sav' => null, 'hi' => $row['planet_hi'] ?? $p]; $avSeq[] = $p; }
                    $avMerge[$p][$t] = $row;
                }
            }
        ?>
        <div class="gochar-cat" data-cat="ashtak">
            <div class="gph-section-title">अष्टकवर्ग (Ashtakvarga) <span class="text-xs text-gray-400 font-normal">(प्रति ग्रह: बिन्दु-फल · कक्षा-फल · सर्वाष्टकवर्ग — एक साथ)</span></div>
            <?php foreach ($avSeq as $p): $row = $avMerge[$p]; $b = $row['bindu']; $k = $row['kaksha']; $s = $row['sav']; ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($p) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($p) ?>"><?= $h($row['hi']) ?></span>
                </div>

                <?php if ($b !== null): ?>
                <div class="gph-avline">
                    <div class="gph-avhead"><b>बिन्दु-फल</b> · <?= $h($b['sign_hi']) ?> · <?= (int) $b['bindu'] ?>/8 बिन्दु <?= $toneChip($b['tone']) ?></div>
                    <div class="saham-phal">● <?= $h($b['phal']) ?></div>
                </div>
                <?php endif; ?>

                <?php if ($k !== null): ?>
                <div class="gph-avline">
                    <div class="gph-avhead"><b>कक्षा-फल</b> · कक्षा <?= (int) $k['kaksha_no'] ?> — <?= $h($k['lord_hi']) ?> <span class="gc-chip <?= $k['tone'] === 'pos' ? 'gc-shubh' : 'gc-ashubh' ?>"><?= $k['kind'] === 'shubh' ? 'बिन्दु (शुभ)' : 'रेखा (अशुभ)' ?></span></div>
                    <div class="saham-phal">● <?= $h($k['phal']) ?></div>
                    <?php if (empty($k['applicable'])): ?><div class="gph-note gph-info">⚠ गोचर ग्रह नीच/अस्त/शत्रु-राशि में — कक्षा-फल पूर्णतः लागू नहीं (नियम-शर्त)।</div><?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($s !== null): ?>
                <div class="gph-avline">
                    <div class="gph-avhead"><b>सर्वाष्टकवर्ग</b> · <?= $h($s['sign_hi']) ?> · भाव <?= (int) $s['house'] ?> · SAV <?= (int) $s['sav'] ?> <span class="gc-chip <?= $s['tone'] === 'pos' ? 'gc-shubh' : 'gc-ashubh' ?>"><?= $s['sav'] >= 28 ? 'शुभ (≥28)' : 'अशुभ (<28)' ?></span></div>
                    <div class="saham-phal">● <?= $h($s['hint']) ?></div>
                </div>
                <?php endif; ?>
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

        <!-- CATEGORY: मुहूर्त (Ch.8) -->
        <?php if ($mu !== null): ?>
        <div class="gochar-cat" data-cat="muhurat">
            <div class="gph-section-title">मुहूर्त <span class="text-xs text-gray-400 font-normal">(राहु काल · दिशा शूल · तिथि · जन्म-नक्षत्र वारफल · अस्त · कष्ट-राशि)</span></div>
            <div class="text-xs text-gray-500" style="margin:2px 0 6px">वार: <b><?= $h($mu['weekday_hi']) ?></b> · गोचर चन्द्र नक्षत्र: <b><?= $h($mu['nakshatra']['transit']['name']) ?></b> पाद <?= (int) $mu['nakshatra']['transit']['pada'] ?> · जन्म-नक्षत्र: <b><?= $h($mu['nakshatra']['janma']['name']) ?></b></div>

            <?php if (!empty($mu['rahu_kaal'])): $rk = $mu['rahu_kaal']; ?>
            <div class="saham-card gochar-card" data-planet="all" style="border-left:4px solid #6b21a8">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#6b21a8">🕒 राहु काल</span>
                    <span class="gph-house"><?= $h($rk['start']) ?> – <?= $h($rk['end']) ?></span>
                    <span class="gc-chip gc-ashubh">वर्ज्य काल</span>
                </div>
                <div class="saham-phal">● <?= $h($rk['note']) ?></div>
                <?php if (!empty($rk['approx'])): ?><div class="gph-note gph-info">समय लगभग (सूर्योदय 6:00 व सूर्यास्त 18:00 मानकर); सटीक हेतु स्थान का वास्तविक दिनमान ÷ 8।</div><?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['disha_shul'])): $ds = $mu['disha_shul']; ?>
            <div class="saham-card gochar-card" data-planet="all" style="border-left:4px solid #b45309">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#b45309">🧭 दिशा शूल</span>
                    <span class="gph-house">वर्जित दिशा: <?= $h($ds['dir']) ?></span>
                    <span class="gc-chip gc-ashubh">यात्रा वर्ज्य</span>
                </div>
                <div class="saham-phal">● <?= $h($ds['note']) ?></div>
                <div class="gph-note gph-info"><?= $h($ds['principle']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['tithi'])): $ti = $mu['tithi']; ?>
            <div class="saham-card gochar-card" data-planet="all" style="border-left:4px solid #0e7490">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#0e7490">🌙 तिथि — <?= $h($ti['name']) ?></span>
                    <span class="gph-house">तिथि <?= (int) $ti['num'] ?> · <?= $h($ti['paksha_hi']) ?> · स्वामी <?= $h($ti['lord']) ?></span>
                    <span class="gc-chip <?= $ti['tone'] === 'pos' ? 'gc-shubh' : ($ti['tone'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h($ti['group_label']) ?>: <?= $h($ti['grade']) ?></span>
                </div>
                <div class="saham-phal">● <?= $h($ti['meaning']) ?></div>
                <div class="gph-note gph-info"><?= $h($ti['note']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['janma_nak_phal'])): $jn = $mu['janma_nak_phal']; ?>
            <div class="saham-card gochar-card" data-planet="all" style="border-left:4px solid #15803d">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#15803d">⭐ जन्म-नक्षत्र वारफल</span>
                    <span class="gph-house"><?= $h($jn['janma_nak']) ?> · <?= $h($jn['weekday_hi']) ?></span>
                    <?php if (!empty($jn['active'])): ?><span class="gc-chip gc-shubh">इस समय सक्रिय</span><?php endif; ?>
                </div>
                <div class="saham-phal">● <?= $h($jn['phal']) ?></div>
                <?php if (!empty($jn['cond'])): ?><div class="gph-note gph-info">शर्त: <?= $h($jn['cond']) ?></div><?php endif; ?>
                <?php if (!empty($jn['bonus_note'])): ?><div class="gph-note gph-pos"><?= $h($jn['bonus_note']) ?></div><?php endif; ?>
                <div class="gph-note gph-info">नियम: जिस मास में जन्म-नक्षत्र इस वार को पड़े, उस मास पर यह प्रभाव। (गोचर चन्द्र इस समय जन्म-नक्षत्र में हो तो तत्काल प्रभावी।)</div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['combust']['warns'])): foreach ($mu['combust']['warns'] as $cw): ?>
            <div class="saham-card gochar-card" data-planet="<?= $h($cw['planet']) ?>" style="border-left:4px solid #b91c1c">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($cw['planet']) ?>">☀ अस्त — <?= $h($cw['planet_hi']) ?></span>
                    <span class="gph-house">सूर्य से <?= $h(number_format((float) $cw['sep'], 1)) ?>°</span>
                    <span class="gc-chip gc-ashubh">विवाह में वर्ज्य</span>
                </div>
                <div class="saham-phal">● <?= $h($cw['warn']) ?></div>
            </div>
            <?php endforeach; endif; ?>
            <?php if ($mu['combust']['warns'] === []): ?>
            <div class="saham-card gochar-card" data-planet="all">
                <div class="saham-card-head"><span class="saham-name" style="color:#15803d">☀ अस्त-ग्रह</span><span class="gc-chip gc-shubh">कोई अस्त नहीं</span></div>
                <div class="saham-phal">● गोचर में गुरु व शुक्र अस्त नहीं — विवाह मुहूर्त हेतु इस दृष्टि से बाधा नहीं।</div>
                <div class="gph-note gph-info"><?= $h($mu['combust']['note']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['kashta_rashi'])): $kr = $mu['kashta_rashi']; ?>
            <div class="saham-card gochar-card" data-planet="all" style="border-left:4px solid #1d4ed8">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:#1d4ed8">🪐 शनि-अष्टकवर्ग कष्ट-राशि</span>
                    <span class="gph-house"><?= $h(implode(', ', $kr['signs'])) ?> · <?= (int) $kr['bindu'] ?> बिन्दु</span>
                    <span class="gc-chip <?= !empty($kr['sun_here']) ? 'gc-ashubh' : 'gc-mishrit' ?>"><?= !empty($kr['sun_here']) ? 'सूर्य यहाँ — कष्ट मास' : 'सतर्कता' ?></span>
                </div>
                <div class="saham-phal">● <?= $h($kr['note']) ?></div>
                <div class="gph-note <?= !empty($kr['sun_here']) ? 'gph-neg' : 'gph-info' ?>"><?= $h($kr['sun_note']) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div id="gochar-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">
            इस चयन के लिए कोई गोचर-फल नहीं — दूसरी श्रेणी या "सभी ग्रह" चुनें।
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
