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
// Tone → coloured-card class (green शुभ / red अशुभ / blue मिश्र·सूचना).
$cardCls = static fn(string $t): string => $t === 'pos' ? 'gcard-pos' : ($t === 'neg' ? 'gcard-neg' : 'gcard-mix');
$av = $gp['av'] ?? ['bindu' => [], 'kaksha' => [], 'sav' => []];
$ss = $gp['shani_special'] ?? null;
$mu = $gp['muhurat'] ?? null;
$gm = $gp['general_muhurat'] ?? null;   // 🗓️ Muhurta-Chintamani General Muhurat (Phase 1)
$pm = $gp['personal_muhurat'] ?? null;  // 🙋 Personal Muhurat (Phase 2)
$sk = $gp['sanskara_muhurat'] ?? null;  // 🧒 Sanskara Muhurat (Phase 3)
$vv = $gp['vivaha_muhurat'] ?? null;    // 💍 Vivaha Muhurat (Phase 4)
$mg = $gp['mangal_dosha'] ?? null;      // 💍 Mangal Dosha (Phase 4)
$ya = $gp['yatra_muhurat'] ?? null;     // 🧳 Yatra Muhurat (Phase 5)
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
            <div class="saham-card gochar-card gcard-mix" data-planet="all">
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

            <div class="saham-card gochar-card <?= $cardCls($gTone) ?>" data-planet="all" style="margin-top:6px">
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
            <?php if (!empty($ss['active'])): $ssTone = (int) $ss['severity'] >= 3 ? 'neg' : ((int) $ss['severity'] === 2 ? 'neutral' : 'pos'); ?>
            <div class="saham-card gochar-card <?= $cardCls($ssTone) ?>" data-planet="Saturn">
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
            <div class="saham-card gochar-card <?= $cardCls(!empty($py['shubh']) ? 'pos' : 'neg') ?>" data-planet="Saturn">
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
            <div class="saham-card gochar-card <?= $cardCls((string) ($e['tone'] ?? '')) ?>" data-planet="<?= $h($e['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($e['planet']) ?>"><?= $h($e['planet_hi']) ?></span>
                    <span class="gph-house">भाव <?= (int) $e['house'] ?></span>
                    <?= $toneChip($e['tone']) ?>
                    <span class="text-xs text-gray-500"><?= $h($e['deg']) ?><?= $e['retro'] ? ' <sup style="color:#b91c1c;font-size:1em;font-weight:700">®</sup>' : '' ?></span>
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
            <?php foreach ($avSeq as $p): $row = $avMerge[$p]; $b = $row['bindu']; $k = $row['kaksha']; $s = $row['sav'];
                $atones = array_filter([$b['tone'] ?? null, $k['tone'] ?? null, $s['tone'] ?? null]);
                $anp = count(array_filter($atones, static fn($t) => $t === 'pos'));
                $ann = count(array_filter($atones, static fn($t) => $t === 'neg'));
                $atone = $ann === 0 ? 'pos' : ($anp >= $ann ? 'neutral' : 'neg');
            ?>
            <div class="saham-card gochar-card <?= $cardCls($atone) ?>" data-planet="<?= $h($p) ?>">
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
            <div class="saham-card gochar-card <?= $cardCls(($grp['nature'] ?? '') === 'आसुरी' ? 'neg' : 'pos') ?>" data-planet="<?= $h($grp['transit']) ?>">
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

            <!-- ============ मुहूर्त-चिन्तामणि — कार्य/श्रेणी चयन (Phase 1) ============ -->
            <style>
            .mc-picker{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin:4px 0 10px}
            .mc-picker label{font-size:.74rem;font-weight:700;color:#6b21a8}
            .mc-cat-select{border:1px solid #e2c9f0;background:#faf5ff;border-radius:8px;padding:6px 11px;
              font-size:.86rem;font-weight:700;color:#6b21a8;max-width:100%}
            .mc-panel.hidden,.mc-rite.hidden{display:none}
            .mc-verdict{border:1px solid #e4dcce;border-left:5px solid #b45309;border-radius:12px;
              padding:12px 14px;margin-bottom:12px;background:linear-gradient(180deg,#fffdf8,#fdf6ec)}
            .mc-verdict h4{font-size:1.02rem;margin:0 0 6px;display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .mc-grade{font-weight:800;border-radius:999px;padding:2px 12px;font-size:.8rem}
            .mc-pos{background:#dcfce7;color:#166534}.mc-neg{background:#fee2e2;color:#991b1b}.mc-info{background:#fef9c3;color:#854d0e}
            .mc-pgrid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:5px 14px;margin:8px 0}
            .mc-pi{font-size:.82rem;color:#4b4433}.mc-pi b{color:#6b5d3e}
            .mc-line{font-size:.85rem;line-height:1.55;margin:3px 0;padding-left:16px;position:relative}
            .mc-line::before{content:'•';position:absolute;left:3px}
            .mc-line.g::before{content:'✓';color:#166534}.mc-line.d::before{content:'✕';color:#b91c1c}
            .mc-rem{font-size:.76rem;color:#8a5a1a;margin:1px 0 4px 16px}
            .mc-soon{border:1px dashed #cbb6e0;border-radius:12px;padding:16px;background:#faf7fd;text-align:center}
            .mc-soon .e{font-size:1.6rem}.mc-soon b{color:#6b21a8}
            .mc-chitta{font-size:.76rem;color:#6b6156;background:#f8fafc;border:1px solid #e4dcce;
              border-radius:8px;padding:8px 11px;margin-top:8px}
            </style>
            <div class="mc-picker">
                <label for="mc-cat-select">🕉️ कार्य / श्रेणी चुनें</label>
                <select id="mc-cat-select" class="mc-cat-select">
                    <option value="general">🗓️ सामान्य मुहूर्त (पंचांग-शुद्धि)</option>
                    <option value="personal">🙋 व्यक्तिगत मुहूर्त (आपकी कुण्डली से)</option>
                    <option value="vivaha">💍 विवाह मुहूर्त + गुण-मिलान</option>
                    <option value="sanskar">🧒 संस्कार (नामकरण·मुण्डन·उपनयन)</option>
                    <option value="vastu">🏗️ गृहारम्भ / वास्तु</option>
                    <option value="grihapravesh">🚪 गृहप्रवेश</option>
                    <option value="yatra">🧳 यात्रा</option>
                    <option value="rajyabhishek">👑 राज्याभिषेक / शपथ-ग्रहण</option>
                </select>
            </div>

            <?php // ---- 🗓️ General Muhurat verdict (Muhurta-Chintamani) ----
            if ($gm !== null && !empty($gm['ok'])): $P = $gm['panchang']; ?>
            <div class="mc-panel" data-mc="general">
                <div class="mc-verdict">
                    <h4>🗓️ सामान्य मुहूर्त — पंचांग-शुद्धि
                        <span class="mc-grade mc-<?= $h($gm['tone']) ?>"><?= $h($gm['grade']) ?></span></h4>
                    <div style="font-size:.9rem;font-weight:600"><?= $h($gm['verdict']) ?></div>
                    <div class="mc-pgrid">
                        <div class="mc-pi"><b>वार:</b> <?= $h($P['vaar']) ?></div>
                        <div class="mc-pi"><b>तिथि:</b> <?= $h($P['tithi']) ?> <span style="color:#854d0e">(<?= $h($P['tithi_grade']) ?>)</span></div>
                        <div class="mc-pi"><b>नक्षत्र:</b> <?= $h($P['nakshatra']) ?></div>
                        <div class="mc-pi"><b>संज्ञा·गण:</b> <?= $h($P['sanjna']) ?> · <?= $h($P['gana']) ?></div>
                        <div class="mc-pi"><b>नित्य-योग:</b> <?= $h($P['yoga']) ?></div>
                        <div class="mc-pi"><b>करण:</b> <?= $h($P['karana']) ?></div>
                    </div>
                    <div class="mc-pi" style="margin-bottom:6px"><b>उपयुक्त कार्य (नक्षत्र-संज्ञा):</b> <?= $h($P['sanjna_karya']) ?><?= $P['ananda'] ? ' · आनन्दादि योग: ' . $h($P['ananda']) : '' ?></div>
                    <?php foreach ($gm['shubh'] as $s): ?>
                        <div class="mc-line g"><b><?= $h($s['name']) ?></b> — <?= $h($s['why']) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($gm['dosha'] as $d): ?>
                        <div class="mc-line d"><b><?= $h($d['name']) ?></b> — <?= $h($d['why']) ?></div>
                        <div class="mc-rem">🛠 <?= $h($d['rem']) ?></div>
                    <?php endforeach; ?>
                    <?php if ($gm['shubh'] === [] && $gm['dosha'] === []): ?>
                        <div class="mc-line">कोई विशेष शुभ योग या दोष नहीं — सामान्य पंचांग।</div>
                    <?php endif; ?>
                    <div class="mc-chitta">🧘 <?= $h($gm['chitta']) ?></div>
                </div>
                <div class="text-xs text-gray-500" style="margin:2px 0 6px;font-weight:700">आधार पंचांग विवरण (राहु काल · दिशा शूल · तिथि · वारफल)</div>
            </div>
            <?php endif; ?>

            <?php // ---- 🙋 व्यक्तिगत मुहूर्त (Phase 2) — from the D1 chart ----
            if ($pm !== null && !empty($pm['ok'])): ?>
            <div class="mc-panel hidden" data-mc="personal">
                <div class="mc-verdict" style="border-left-color:#0891b2;background:linear-gradient(180deg,#f6fdff,#ecfbff)">
                    <h4>🙋 व्यक्तिगत मुहूर्त — आपकी कुण्डली से
                        <span class="mc-grade mc-<?= $h($pm['tone']) ?>"><?= $h($pm['grade']) ?></span></h4>
                    <div style="font-size:.9rem;font-weight:600"><?= $h($pm['verdict']) ?></div>
                    <div class="text-xs text-gray-500" style="margin:4px 0">जन्म-राशि: <b><?= $h($pm['janma_rashi']) ?></b> · जन्म-नक्षत्र: <b><?= $h($pm['janma_nak']) ?></b></div>
                    <div class="mc-pgrid">
                        <?php foreach ($pm['core'] as $c): ?>
                            <div class="mc-pi"><b><?= $h($c['label']) ?>:</b> <?= $h($c['value']) ?> <?= $c['ok'] ? '✓' : '' ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (!empty($pm['av'])): ?>
                    <div class="mc-pi" style="margin-bottom:4px"><b>अष्टकवर्ग गोचर-बिन्दु:</b>
                        <?php foreach ($pm['av'] as $a): ?><span style="display:inline-block;margin:1px 4px 1px 0;padding:1px 8px;border-radius:999px;font-size:.72rem;background:<?= $a['ok'] ? '#dcfce7' : '#fee2e2' ?>;color:<?= $a['ok'] ? '#166534' : '#991b1b' ?>"><?= $h($a['planet']) ?> <?= $h($a['sign']) ?> · <?= (int) $a['bindu'] ?></span><?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pm['slow'])): ?><div class="mc-pi" style="margin-bottom:4px"><b>गोचर-वेध:</b> <?= $h(implode(' · ', $pm['slow'])) ?></div><?php endif; ?>
                    <?php foreach ($pm['good'] as $s): ?>
                        <div class="mc-line g"><b><?= $h($s['name']) ?></b> — <?= $h($s['why']) ?></div>
                    <?php endforeach; ?>
                    <?php foreach ($pm['bad'] as $d): ?>
                        <div class="mc-line d"><b><?= $h($d['name']) ?></b> — <?= $h($d['why']) ?></div>
                        <div class="mc-rem">🛠 <?= $h($d['rem']) ?></div>
                    <?php endforeach; ?>
                    <?php if ($pm['good'] === [] && $pm['bad'] === []): ?><div class="mc-line">कोई विशेष व्यक्तिगत योग/दोष नहीं।</div><?php endif; ?>
                    <div class="mc-chitta">🧘 सामान्य पंचांग शुभ हो पर व्यक्तिगत (तारा/घात) दुर्बल हो — तो शुभ तारा/चन्द्र-बल वाला दिन श्रेष्ठ। <?= $h($pm['chitta']) ?></div>
                </div>
            </div>
            <?php endif; ?>

            <?php // ---- 🧒 संस्कार मुहूर्त (Phase 3) — per-rite viability ----
            if ($sk !== null && !empty($sk['ok'])): ?>
            <div class="mc-panel hidden" data-mc="sanskar">
                <div class="mc-picker" style="margin-bottom:6px">
                    <label for="mc-rite-select">🧒 संस्कार चुनें</label>
                    <select id="mc-rite-select" class="mc-cat-select">
                        <?php foreach ($sk['rites'] as $rt): ?>
                            <option value="<?= $h($rt['key']) ?>"><?= $rt['emoji'] ?> <?= $h($rt['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="text-xs text-gray-500" style="margin:2px 0 6px">आज: नक्षत्र <b><?= $h($sk['moon_nak']) ?></b> (<?= $h($sk['sanjna']) ?>) · तिथि <b><?= (int) $sk['tithi'] ?></b> · वार <b><?= $h($sk['vaar']) ?></b></div>
                <?php foreach ($sk['rites'] as $i => $rt): ?>
                <div class="mc-rite <?= $i === 0 ? '' : 'hidden' ?>" data-rite="<?= $h($rt['key']) ?>">
                    <div class="mc-verdict" style="border-left-color:#7c3aed;background:linear-gradient(180deg,#fdfaff,#f6f0ff)">
                        <h4><?= $rt['emoji'] ?> <?= $h($rt['label']) ?> <span style="font-size:.7rem;color:#a78bfa"><?= $h($rt['rule']) ?></span>
                            <span class="mc-grade mc-<?= $h($rt['tone']) ?>"><?= $h($rt['grade']) ?></span></h4>
                        <div style="font-size:.9rem;font-weight:600"><?= $h($rt['verdict']) ?></div>
                        <div class="mc-pgrid">
                            <?php foreach ($rt['checks'] as $c): ?>
                                <div class="mc-pi"><b><?= $h($c['label']) ?>:</b> <?= $h($c['value']) ?> <?= $c['ok'] ? '✅' : '❌' ?></div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mc-pi" style="margin-bottom:3px"><b>विहित:</b> <?= $h($rt['prescription']) ?></div>
                        <div class="mc-rem">📌 <?= $h($rt['note']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="mc-chitta">📖 <?= $h($sk['note']) ?></div>
            </div>
            <?php endif; ?>

            <?php // ---- 💍 विवाह मुहूर्त + मंगल-दोष (Phase 4) ----
            if ($vv !== null && !empty($vv['ok'])): ?>
            <div class="mc-panel hidden" data-mc="vivaha">
                <div class="mc-verdict" style="border-left-color:#db2777;background:linear-gradient(180deg,#fffafd,#fdf0f7)">
                    <h4>💍 विवाह मुहूर्त — तिथि-शुद्धि
                        <span class="mc-grade mc-<?= $h($vv['tone']) ?>"><?= $h($vv['grade']) ?></span></h4>
                    <div style="font-size:.9rem;font-weight:600"><?= $h($vv['verdict']) ?></div>
                    <div class="mc-pgrid">
                        <?php foreach ($vv['checks'] as $c): ?>
                            <div class="mc-pi"><b><?= $h($c['label']) ?>:</b> <?= $h($c['value']) ?> <?= $c['ok'] ? '✅' : '❌' ?></div>
                        <?php endforeach; ?>
                    </div>
                    <?php foreach ($vv['bad'] as $d): ?>
                        <div class="mc-line d"><b><?= $h($d['name']) ?></b> — <?= $h($d['why']) ?></div>
                    <?php endforeach; ?>
                    <div class="mc-rem">📌 <?= $h($vv['note']) ?></div>
                </div>

                <?php if ($mg !== null && !empty($mg['ok'])): ?>
                <div class="mc-verdict" style="border-left-color:#dc2626;background:linear-gradient(180deg,#fffaf9,#fdf0ee)">
                    <h4>🔴 मंगल (कुज) दोष — आपकी कुण्डली
                        <span class="mc-grade mc-<?= $h($mg['tone']) ?>"><?= $h($mg['status']) ?></span></h4>
                    <div style="font-size:.9rem;font-weight:600"><?= $h($mg['verdict']) ?></div>
                    <div class="mc-pgrid">
                        <?php foreach ($mg['bases'] as $lbl => $hh): $bad = in_array((int) $hh, [1, 2, 4, 7, 8, 12], true); ?>
                            <div class="mc-pi"><b><?= $h($lbl) ?> से:</b> मंगल <?= (int) $hh ?>वें भाव <?= $bad ? '⚠️' : '✅' ?></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mc-pi" style="margin-bottom:3px">मंगल राशि: <b><?= $h($mg['mars_sign']) ?></b><?= $mg['mars_retro'] ? ' · वक्री' : '' ?><?= $mg['mars_combust'] ? ' · अस्त' : '' ?></div>
                    <?php if (!empty($mg['parihara'])): ?>
                        <div class="mc-pi" style="font-weight:700;color:#166534;margin-bottom:2px">✓ लागू परिहार:</div>
                        <?php foreach ($mg['parihara'] as $p): ?><div class="mc-line g"><?= $h($p) ?></div><?php endforeach; ?>
                    <?php endif; ?>
                    <div class="mc-rem">📌 <?= $h($mg['note']) ?></div>
                </div>
                <?php endif; ?>

                <div class="mc-chitta" style="background:#fdf2f8;border-color:#f9c0dd">
                    💑 <b>36-गुण मिलान</b> (वर-कन्या की दो कुण्डलियों से) हेतु
                    <a href="<?= $h(\AutoBusiness\Core\Asset::url('/milan')) ?>" style="color:#be185d;font-weight:700;text-decoration:underline">कुण्डली-मिलान पृष्ठ</a> खोलें —
                    वहाँ अष्टकूट, गण/नाडी/भकूट व लाल-किताब अनुकूलता की पूर्ण जाँच है।
                </div>
            </div>
            <?php endif; ?>

            <?php // ---- 🧳 यात्रा मुहूर्त (Phase 5) — per-direction ----
            if ($ya !== null && !empty($ya['ok'])): ?>
            <div class="mc-panel hidden" data-mc="yatra">
                <div class="mc-picker" style="margin-bottom:6px">
                    <label for="mc-dir-select">🧳 दिशा चुनें</label>
                    <select id="mc-dir-select" class="mc-cat-select">
                        <?php foreach ($ya['dirs'] as $dd): $emo = ['शुभ' => '🟢', 'मध्यम' => '🟡', 'अशुभ' => '🔴'][$dd['grade']] ?? ''; ?>
                            <option value="<?= $h($dd['dir']) ?>"><?= $emo ?> <?= $h($dd['dir']) ?> — <?= $h($dd['grade']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="text-xs text-gray-500" style="margin:2px 0 6px">आज: नक्षत्र <b><?= $h($ya['nakshatra']) ?></b><?= $ya['sarvadig'] ? ' (सर्वदिग् — शूल-नाशक)' : ($ya['vihit_nak'] ? ' (यात्रा-विहित)' : '') ?> · तिथि <b><?= (int) $ya['tithi'] ?></b> · वार <b><?= $h($ya['vaar']) ?></b> · वार-शूल <b><?= $h($ya['vaar_shool_dir']) ?></b> · योगिनी <b><?= $h($ya['yogini_dir']) ?></b></div>
                <?php foreach ($ya['dirs'] as $i => $dd): ?>
                <div class="mc-rite <?= $i === 0 ? '' : 'hidden' ?>" data-rite="<?= $h($dd['dir']) ?>">
                    <div class="mc-verdict" style="border-left-color:#0d9488;background:linear-gradient(180deg,#f5fdfc,#ecfbf8)">
                        <h4>🧭 <?= $h($dd['dir']) ?> दिशा <span style="font-size:.72rem;color:#64748b">(स्वामी <?= $h($dd['swami']) ?><?= $dd['vahana'] ? ' · वाहन ' . $h($dd['vahana']) : '' ?>)</span>
                            <span class="mc-grade mc-<?= $h($dd['tone']) ?>"><?= $h($dd['grade']) ?></span></h4>
                        <?php foreach ($dd['issues'] as $x): ?>
                            <div class="mc-line d"><b><?= $h($x['name']) ?></b> — <?= $h($x['why']) ?></div>
                        <?php endforeach; ?>
                        <?php if (!empty($dd['ok_note'])): ?><div class="mc-line g"><?= $h($dd['ok_note']) ?></div><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="mc-chitta">📍 <?= $h($ya['note']) ?></div>
            </div>
            <?php endif; ?>

            <?php // ---- placeholders for categories arriving in later phases ----
            $mcSoon = [
                'vastu' => ['🏗️', 'गृहारम्भ / वास्तु', 'गृह-पिण्ड, आय-साधन, गृहारम्भ मास/नक्षत्र, राहुमुख, भूमि-परीक्षा। (Phase 6)'],
                'grihapravesh' => ['🚪', 'गृहप्रवेश', 'अपूर्व/सपूर्व प्रवेश, कुम्भ-चक्र, वास्तु-पूजन काल। (Phase 6)'],
                'rajyabhishek' => ['👑', 'राज्याभिषेक / शपथ', 'काल-शुद्धि, ग्रह-भाव-बल, स्थिर-लग्न — राजनीति-मॉड्यूल से जुड़ेगा। (Phase 6)'],
            ];
            foreach ($mcSoon as $key => $info): ?>
            <div class="mc-panel hidden" data-mc="<?= $h($key) ?>">
                <div class="mc-soon">
                    <div class="e"><?= $info[0] ?></div>
                    <div style="margin:4px 0"><b><?= $h($info[1]) ?></b> — यह श्रेणी शीघ्र जोड़ी जाएगी।</div>
                    <div style="font-size:.82rem;color:#6b6156"><?= $h($info[2]) ?></div>
                    <div style="font-size:.78rem;color:#94a3b8;margin-top:6px">तब तक नीचे 🗓️ <b>सामान्य मुहूर्त</b> का पंचांग-शुद्धि फल देखें।</div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!empty($mu['rahu_kaal'])): $rk = $mu['rahu_kaal']; ?>
            <div class="saham-card gochar-card gcard-neg" data-planet="all">
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
            <div class="saham-card gochar-card gcard-neg" data-planet="all">
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
            <div class="saham-card gochar-card <?= $cardCls((string) ($ti['tone'] ?? '')) ?>" data-planet="all">
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
            <div class="saham-card gochar-card gcard-pos" data-planet="all">
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
            <div class="saham-card gochar-card gcard-neg" data-planet="<?= $h($cw['planet']) ?>">
                <div class="saham-card-head">
                    <span class="saham-name" style="color:<?= $pcolor($cw['planet']) ?>">☀ अस्त — <?= $h($cw['planet_hi']) ?></span>
                    <span class="gph-house">सूर्य से <?= $h(number_format((float) $cw['sep'], 1)) ?>°</span>
                    <span class="gc-chip gc-ashubh">विवाह में वर्ज्य</span>
                </div>
                <div class="saham-phal">● <?= $h($cw['warn']) ?></div>
            </div>
            <?php endforeach; endif; ?>
            <?php if ($mu['combust']['warns'] === []): ?>
            <div class="saham-card gochar-card gcard-pos" data-planet="all">
                <div class="saham-card-head"><span class="saham-name" style="color:#15803d">☀ अस्त-ग्रह</span><span class="gc-chip gc-shubh">कोई अस्त नहीं</span></div>
                <div class="saham-phal">● गोचर में गुरु व शुक्र अस्त नहीं — विवाह मुहूर्त हेतु इस दृष्टि से बाधा नहीं।</div>
                <div class="gph-note gph-info"><?= $h($mu['combust']['note']) ?></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($mu['kashta_rashi'])): $kr = $mu['kashta_rashi']; ?>
            <div class="saham-card gochar-card <?= $cardCls(!empty($kr['sun_here']) ? 'neg' : 'neutral') ?>" data-planet="all">
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
