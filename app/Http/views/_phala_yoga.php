<?php
/**
 * फलदीपिका योग catalogue — shown under the "योग" prediction option below the
 * detected-yoga summary. All 106 yogas grouped by category, with the computable
 * subset auto-marked (✓ इस कुंडली में). Scope: $view['phala_yoga']
 * (PhaladeepikaYogaEngine::compute output), $h.
 *
 * Filters (bound in calc-v2 JS): श्रेणी dropdown · प्रकार dropdown · "केवल बने योग".
 */
$py = $view['phala_yoga'] ?? null;
$typeHi = ['shubh' => 'शुभ', 'ashubh' => 'अशुभ', 'mishrit' => 'मिश्र'];
$typeChip = static fn(string $t): string => $t === 'shubh' ? 'gc-shubh' : ($t === 'ashubh' ? 'gc-ashubh' : 'gc-mishrit');
if ($py !== null && !empty($py['groups'])):
    $cats = $py['categories'];   // hi => slug
?>
    <div class="yoga-sec-title" style="margin-top:14px">फलदीपिका योग <span class="text-xs text-gray-400 font-normal">(अध्याय 6–7 · कुल <?= (int) $py['total'] ?>)</span>
        <span class="gc-chip gc-shubh" style="margin-left:6px">इस कुंडली में बने: <?= (int) $py['detected_count'] ?></span></div>

    <div class="pred-picker" style="margin-top:8px;gap:8px;flex-wrap:wrap">
        <label class="pred-picker-label" for="py-cat">श्रेणी</label>
        <select id="py-cat" class="pred-inline-select" size="1">
            <option value="all">सभी श्रेणियाँ (All)</option>
            <?php foreach ($cats as $chi => $slug): ?><option value="<?= $h($chi) ?>"><?= $h($chi) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="pred-picker" style="margin-top:6px;gap:8px;flex-wrap:wrap">
        <label class="pred-picker-label" for="py-type">प्रकार</label>
        <select id="py-type" class="pred-inline-select" size="1">
            <option value="all">सभी</option>
            <option value="shubh">शुभ</option>
            <option value="ashubh">अशुभ</option>
            <option value="mishrit">मिश्र</option>
        </select>
        <label class="text-xs text-gray-600" style="display:flex;align-items:center;gap:5px;white-space:nowrap">
            <input type="checkbox" id="py-detected"> केवल बने योग
        </label>
    </div>

    <div id="py-detail-pane" style="margin-top:8px">
        <?php foreach ($py['groups'] as $cat => $ys): ?>
        <div class="py-group" data-cat="<?= $h($cat) ?>">
            <div class="yoga-cat-head"><?= $h($cat) ?> <span class="text-xs text-gray-400 font-normal">(<?= count($ys) ?>)</span></div>
            <?php foreach ($ys as $y): $d = $y['detected']; ?>
            <div class="yoga-card py-card<?= $y['type'] === 'ashubh' ? ' yoga-bad' : '' ?><?= $d === true ? ' py-on' : '' ?>"
                 data-cat="<?= $h($cat) ?>" data-type="<?= $h($y['type']) ?>" data-detected="<?= $d === true ? '1' : ($d === false ? '0' : 'ref') ?>">
                <div class="yoga-title" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span><?= $h($y['hi']) ?><?= $y['en'] !== '' ? ' — ' . $h($y['en']) : '' ?></span>
                    <span class="gc-chip <?= $typeChip($y['type']) ?>"><?= $h($typeHi[$y['type']] ?? $y['type']) ?></span>
                    <?php if ($d === true): ?><span class="gc-chip gc-shubh">✓ इस कुंडली में</span>
                    <?php elseif ($d === false): ?><span class="gc-chip tb-off">नहीं बना</span>
                    <?php else: ?><span class="gc-chip tb-ref">सन्दर्भ</span><?php endif; ?>
                    <span class="text-xs text-gray-400 font-normal"><?= $h($y['id']) ?> · <?= $h($y['ref']) ?></span>
                </div>
                <div class="yoga-why"><b>नियम:</b> <?= $h($y['rule']) ?></div>
                <div class="yoga-res"><b>फल:</b> <?= $h($y['result']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div id="py-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">इस चयन के लिए कोई योग नहीं।</div>
        <p class="text-xs text-gray-400" style="margin-top:8px">"इस कुंडली में" = D1 कुंडली पर संगणित नियम सिद्ध। "सन्दर्भ" = पूर्ण षड्बल/सूक्ष्म-दृष्टि आधारित योग — ज्योतिषी स्वयं विचार करें। स्रोत: फलदीपिका, अध्याय 6–7।</p>
    </div>
<?php endif; ?>
