<?php
/**
 * गोचर फल pane — the Gochar prediction panel (right of the transit chart).
 * Rendered by the calc/gochar JSON endpoint and injected by gochar.js each
 * time the transit date/place changes. Scope: $gp (GocharPhalEngine::compute
 * output), $h, $pcolor, $grahaHi, $rashiHi.
 */
$rashiNames = \AutoBusiness\Astro\Calc\Charts::SIGNS;
$toneChip = static function (string $t) use ($h): string {
    return $t === 'pos' ? '<span class="gc-chip gc-shubh">शुभ</span>'
        : ($t === 'neg' ? '<span class="gc-chip gc-ashubh">अशुभ</span>'
        : '<span class="gc-chip gc-mishrit">फल रुका</span>');
};
$noteCls = static fn(string $t): string => $t === 'pos' ? 'gph-pos' : ($t === 'neg' ? 'gph-neg' : 'gph-info');
if ($gp !== null && (!empty($gp['layer1']) || !empty($gp['layer3']))):
    $l1 = $gp['layer1'] ?? [];
    $l3 = $gp['layer3'] ?? [];
    $moonSignName = $rashiNames[(int) ($gp['moon_sign'] ?? 0)] ?? '';
    $moonSignHi = $rashiHi[$moonSignName] ?? $moonSignName;
?>
    <div class="saham-active">
        <div><b>गोचर आधार:</b> चन्द्र लग्न (जन्म राशि) <b style="color:<?= $pcolor('Moon') ?>"><?= $h($moonSignHi) ?></b> से गिना गया·
            <span class="text-xs text-gray-500"><?= ($gp['moon_ksheen'] ?? false) ? 'गोचर चन्द्र क्षीण' : 'गोचर चन्द्र बली' ?></span></div>
        <div class="text-xs text-gray-500" style="margin-top:3px">प्रत्येक ग्रह का फल जन्म-चन्द्र से उसके भाव अनुसार; साथ वेध, अष्टकवर्ग-बिन्दु व तृतीयांश-समय के संशोधन।</div>
    </div>

    <div class="pred-picker" style="margin-top:8px">
        <label class="pred-picker-label" for="gochar-select">ग्रह चुनें</label>
        <select id="gochar-select" class="pred-inline-select" size="1">
            <option value="all">सभी ग्रह (All)</option>
            <?php foreach ($l1 as $e): ?>
                <option value="<?= $h($e['planet']) ?>"><?= $h($e['planet_hi']) ?> — भाव <?= (int) $e['house'] ?> (<?= $e['tone'] === 'pos' ? 'शुभ' : ($e['tone'] === 'neg' ? 'अशुभ' : 'रुका') ?>)</option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="gochar-detail-pane" class="overflow-y-auto pr-1" style="max-height:460px">
        <!-- LAYER 1 + 2 -->
        <div class="gph-section-title">चन्द्र-लग्न भाव-फल <span class="text-xs text-gray-400 font-normal">(Layer 1 · भाव-गोचर)</span></div>
        <?php foreach ($l1 as $e): ?>
        <div class="saham-card gochar-card" data-planet="<?= $h($e['planet']) ?>">
            <div class="saham-card-head">
                <span class="saham-name" style="color:<?= $pcolor($e['planet']) ?>"><?= $h($e['planet_hi']) ?></span>
                <span class="gph-house">भाव <?= (int) $e['house'] ?></span>
                <?= $toneChip($e['tone']) ?>
                <span class="text-xs text-gray-500"><?= $h($e['deg']) ?><?= $e['retro'] ? ' <sup style="color:#b91c1c">®</sup>' : '' ?></span>
            </div>
            <div class="saham-phal">● <?= $h($e['text']) ?></div>
            <?php foreach (($e['notes'] ?? []) as $n): ?>
                <div class="gph-note <?= $noteCls($n['t']) ?>"><?= $h($n['text']) ?></div>
            <?php endforeach; ?>
            <div class="gph-shubh">शुभ भाव (इस ग्रह के): <?= $h(implode(', ', $e['shubh_houses'] ?? [])) ?></div>
        </div>
        <?php endforeach; ?>

        <!-- LAYER 3 -->
        <?php if ($l3 !== []): ?>
        <div class="gph-section-title" style="margin-top:10px">जन्म-ग्रहों पर गोचर <span class="text-xs text-gray-400 font-normal">(Layer 3 · गोचर × जन्म-ग्रह)</span></div>
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
            <?php foreach (($grp['all_notes'] ?? []) as $an): ?>
                <div class="gph-note gph-info"><?= $h($an) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div id="gochar-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">
            इस ग्रह का कोई गोचर-फल इस समय नहीं — "सभी ग्रह" चुनकर पूरी सूची देखें।
        </div>
        <p class="text-xs text-gray-400" style="margin-top:8px">फल-क्रम: भाव-फल → वेध → अष्टकवर्ग-बिन्दु → तृतीयांश-समय। राहु शनिवत्, केतु मंगलवत्। वार्षिक फल गुरु/शनि/राहु · मासिक सूर्य · दैनिक चन्द्र।</p>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🔮</div>
        <div class="gps-title">गोचर फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($gp['error'] ?? 'गोचर गणना के बाद यहाँ भाव-फल व जन्म-ग्रहों पर गोचर दिखेगा।')) ?></div>
    </div>
<?php endif; ?>
