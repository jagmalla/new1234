<?php
/**
 * शाप-दोष / सन्तान योग pane — Poorva Shaap (BPHS ch.86). All 105 santaan rules
 * grouped by category with the computable subset auto-detected, plus the
 * traditional remedies for any dosha category that fired. Scope:
 * $view['shaap'] (ShaapEngine::compute output), $h.
 *
 * Presented strictly as traditional/informational reference (disclaimer shown).
 * Filters bound in calc-v2 JS: श्रेणी dropdown · प्रकार dropdown · "केवल बने योग".
 */
$sh = $view['shaap'] ?? null;
$typeHi = ['ashubh' => 'अशुभ (दोष)', 'mishrit' => 'मिश्र', 'shubh' => 'शुभ'];
$typeChip = static fn(string $t): string => $t === 'shubh' ? 'gc-shubh' : ($t === 'ashubh' ? 'gc-ashubh' : 'gc-mishrit');
if ($sh !== null && !empty($sh['groups'])):
    $cats = $sh['categories'];
?>
    <div class="shaap-note">⚠ यह <b>बृहत्पाराशरहोराशास्त्र (अध्याय 86)</b> का शास्त्रीय/परम्परागत पाठ है — केवल <b>सूचनात्मक व सांस्कृतिक सन्दर्भ</b> हेतु। इसे चिकित्सा, प्रजनन या किसी व्यक्तिगत निर्णय का आधार <b>न बनाएँ</b>। उपाय धार्मिक-परम्परागत आस्था पर आधारित हैं।</div>

    <div class="yoga-sec-title" style="margin-top:10px">पूर्वशाप व सन्तान योग <span class="text-xs text-gray-400 font-normal">(अध्याय 86 · कुल <?= (int) $sh['total'] ?>)</span>
        <?php if ((int) $sh['detected_count'] > 0): ?><span class="gc-chip gc-ashubh" style="margin-left:6px">इस कुंडली में संगणित: <?= (int) $sh['detected_count'] ?></span>
        <?php else: ?><span class="gc-chip gc-shubh" style="margin-left:6px">इस कुंडली में कोई शाप-योग संगणित नहीं</span><?php endif; ?></div>

    <div class="pred-picker" style="margin-top:8px;gap:8px;flex-wrap:wrap">
        <label class="pred-picker-label" for="sh-cat">श्रेणी</label>
        <select id="sh-cat" class="pred-inline-select" size="1">
            <option value="all">सभी श्रेणियाँ (All)</option>
            <?php foreach ($cats as $chi => $slug): ?><option value="<?= $h($chi) ?>"><?= $h($chi) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="pred-picker" style="margin-top:6px;gap:8px;flex-wrap:wrap">
        <label class="pred-picker-label" for="sh-type">प्रकार</label>
        <select id="sh-type" class="pred-inline-select" size="1">
            <option value="all">सभी</option>
            <option value="ashubh">अशुभ (दोष)</option>
            <option value="mishrit">मिश्र (विलम्ब/दत्तक)</option>
            <option value="shubh">शुभ (बहुपुत्र)</option>
        </select>
        <label class="text-xs text-gray-600" style="display:flex;align-items:center;gap:5px;white-space:nowrap">
            <input type="checkbox" id="sh-detected"> केवल संगणित
        </label>
    </div>

    <?php if (!empty($sh['remedies'])): ?>
    <div class="shaap-remedy-box">
        <div class="yoga-cat-head" style="margin-top:4px">🛕 उपाय / शान्ति <span class="text-xs text-gray-400 font-normal">(संगणित शाप-श्रेणियों हेतु — परम्परागत)</span></div>
        <?php foreach ($sh['remedies'] as $rm): ?>
        <div class="shaap-remedy"><b><?= $h($rm['cat']) ?></b> <span class="text-xs text-gray-400">(<?= $h($rm['ref']) ?>)</span><br><?= $h($rm['upaay']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div id="sh-detail-pane" style="margin-top:8px">
        <?php foreach ($sh['groups'] as $cat => $rs): ?>
        <div class="sh-group" data-cat="<?= $h($cat) ?>">
            <div class="yoga-cat-head"><?= $h($cat) ?> <span class="text-xs text-gray-400 font-normal">(<?= count($rs) ?>)</span></div>
            <?php foreach ($rs as $r): $d = $r['detected']; ?>
            <div class="yoga-card sh-card<?= $r['type'] === 'ashubh' ? ' yoga-bad' : '' ?><?= $d === true ? ' py-on' : '' ?>"
                 data-cat="<?= $h($cat) ?>" data-type="<?= $h($r['type']) ?>" data-detected="<?= $d === true ? '1' : ($d === false ? '0' : 'ref') ?>">
                <div class="yoga-title" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span><?= $h($r['id']) ?></span>
                    <span class="gc-chip <?= $typeChip($r['type']) ?>"><?= $h($typeHi[$r['type']] ?? $r['type']) ?></span>
                    <?php if ($d === true): ?><span class="gc-chip <?= $r['type'] === 'shubh' ? 'gc-shubh' : 'gc-ashubh' ?>">✓ संगणित</span>
                    <?php elseif ($d === false): ?><span class="gc-chip tb-off">नहीं बना</span>
                    <?php else: ?><span class="gc-chip tb-ref">सन्दर्भ</span><?php endif; ?>
                    <span class="text-xs text-gray-400 font-normal"><?= $h($r['ref']) ?></span>
                </div>
                <div class="yoga-why"><b>नियम:</b> <?= $h($r['rule']) ?></div>
                <div class="yoga-res"><b>फल:</b> <?= $h($r['result']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div id="sh-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">इस चयन के लिए कोई नियम नहीं।</div>
        <p class="text-xs text-gray-400" style="margin-top:8px">"संगणित" = D1 कुंडली पर नियम की शर्त सिद्ध (न्यूनतम बल-प्रॉक्सी: उच्च/स्व/केन्द्र-त्रिकोण = बलवान, नीच/दुःस्थान = निर्बल)। "सन्दर्भ" = नवांश/षष्ट्यंश/गुलिक/पूर्ण-षड्बल आधारित सूक्ष्म नियम — ज्योतिषी स्वयं विचार करें। स्रोत: बृ.पा.हो.शा., अध्याय 86।</p>
    </div>
<?php endif; ?>
