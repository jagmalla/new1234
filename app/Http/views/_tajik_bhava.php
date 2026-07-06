<?php
/**
 * ताजिक नीलकण्ठी भाव-फल pane — the "भाव-फल" option of the Varshaphal prediction
 * dropdown. Renders all 262 Neelakanthi bhava rules grouped by house, with the
 * computable subset auto-marked (✔ इस वर्ष लागू) against the varsha chart.
 * Scope: $view['tajik_bhava'] (TajikBhavaEngine::compute output), $h.
 *
 * Filters (bound in calc-v2 JS ABBindVarshaPred): भाव dropdown · श्रेणी dropdown ·
 * "केवल लागू" checkbox.
 */
$tb = $view['tajik_bhava'] ?? null;
$catHi = ['shubh' => 'शुभ', 'ashubh' => 'अशुभ', 'mrityu' => 'मृत्यु', 'mishrit' => 'मिश्रित', 'niyam' => 'नियम/विधि'];
$catChip = static function (string $c): string {
    return match ($c) {
        'shubh' => 'gc-shubh',
        'ashubh' => 'gc-ashubh',
        'mrityu' => 'tb-mrityu',
        'mishrit' => 'gc-mishrit',
        default => 'tb-niyam',
    };
};
if ($tb !== null && !empty($tb['groups'])):
    $ctx = $tb['context'];
    $bhavaHi = $tb['bhava_hi'];
    $panchaHi = array_map(static fn($p) => \AutoBusiness\Astro\Varshaphal\TajikBhavaEngine::PLANET_HI[$p] ?? $p, $ctx['pancha'] ?? []);
?>
    <div class="saham-active">
        <div><b>वर्षेश:</b> <b style="color:#b45309"><?= $h($ctx['varshesh_hi']) ?></b> ·
            <b>वर्ष-लग्न:</b> <?= $h($ctx['varsha_lagna_sign']) ?> ·
            <b>मुन्था:</b> <?= $h($ctx['muntha_sign']) ?> (भाव <?= (int) $ctx['muntha_house'] ?>) ·
            <b>लग्नेश:</b> <?= $h(\AutoBusiness\Astro\Varshaphal\TajikBhavaEngine::PLANET_HI[$ctx['lagnesh']] ?? $ctx['lagnesh']) ?> (<?= $h($ctx['lagnesh_tier']) ?>)</div>
        <div class="text-xs text-gray-500" style="margin-top:3px">पंचाधिकारी: <?= $h(implode(' · ', $panchaHi)) ?> ·
            कुल <?= (int) $tb['total'] ?> नियम · <b style="color:#15803d">इस वर्ष लागू: <?= (int) $tb['matched_count'] ?></b></div>
    </div>

    <div class="pred-picker" style="margin-top:8px;gap:8px">
        <label class="pred-picker-label" for="tb-house">भाव चुनें</label>
        <select id="tb-house" class="pred-inline-select" size="1">
            <option value="all">सभी भाव (All)</option>
            <?php foreach ($bhavaHi as $hn => $label): ?><option value="<?= (int) $hn ?>"><?= $h($label) ?></option><?php endforeach; ?>
        </select>
    </div>
    <div class="pred-picker" style="margin-top:6px;gap:8px">
        <label class="pred-picker-label" for="tb-cat">श्रेणी</label>
        <select id="tb-cat" class="pred-inline-select" size="1">
            <option value="all">सभी श्रेणियाँ</option>
            <?php foreach ($catHi as $ck => $cl): ?><option value="<?= $h($ck) ?>"><?= $h($cl) ?></option><?php endforeach; ?>
        </select>
        <label class="text-xs text-gray-600" style="display:flex;align-items:center;gap:5px;white-space:nowrap">
            <input type="checkbox" id="tb-matched"> केवल इस वर्ष लागू
        </label>
    </div>

    <div id="tb-detail-pane" class="overflow-y-auto pr-1" style="max-height:460px;margin-top:8px">
        <?php foreach ($tb['groups'] as $hn => $rows): ?>
        <div class="tb-hgroup" data-house="<?= (int) $hn ?>">
            <div class="gph-section-title"><?= $h($bhavaHi[$hn] ?? ('भाव ' . $hn)) ?>
                <span class="text-xs text-gray-400 font-normal">(<?= count($rows) ?> नियम)</span></div>
            <?php foreach ($rows as $r): $m = $r['matched']; ?>
            <div class="saham-card tb-card" data-house="<?= (int) $hn ?>" data-cat="<?= $h($r['cat']) ?>"
                 data-matched="<?= $m === true ? '1' : ($m === false ? '0' : 'ref') ?>"
                 <?= $m === true ? 'style="border-left:4px solid #15803d;background:#f6fef9"' : '' ?>>
                <div class="saham-card-head">
                    <span class="saham-name" style="font-size:12.5px;color:#334155"><?= $h($r['id']) ?>
                        <span class="text-xs text-gray-400 font-normal">श्लोक <?= $h($r['sh']) ?></span></span>
                    <span class="gc-chip <?= $catChip($r['cat']) ?>"><?= $h($catHi[$r['cat']] ?? $r['cat']) ?></span>
                    <?php if ($m === true): ?><span class="gc-chip gc-shubh">✔ इस वर्ष लागू</span>
                    <?php elseif ($m === false): ?><span class="gc-chip tb-off">संगणित · लागू नहीं</span>
                    <?php else: ?><span class="gc-chip tb-ref">शास्त्र-सन्दर्भ</span><?php endif; ?>
                </div>
                <div class="tb-cond"><b>शर्त:</b> <?= $h($r['cond']) ?></div>
                <div class="saham-phal">● <?= $h($r['phal']) ?></div>
                <?php if (!empty($r['note'])): ?><div class="gph-note gph-info"><?= $h($r['note']) ?></div><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div id="tb-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">इस चयन के लिए कोई नियम नहीं।</div>
        <p class="text-xs text-gray-400" style="margin-top:8px">
            "इस वर्ष लागू" = वर्ष-कुण्डली पर संगणित शर्त सिद्ध। "शास्त्र-सन्दर्भ" = इत्थशाल/हद्दा/सहम-आधारित सूक्ष्म नियम — ज्योतिषी स्वयं विचार करें।
            स्रोत: ताजिक नीलकण्ठी, भावविचाराध्याय।</p>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">📜</div>
        <div class="gps-title">भाव-फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($tb['error'] ?? 'वर्षफल गणना के बाद यहाँ ताजिक नीलकण्ठी के बारह भावों के नियम दिखेंगे।')) ?></div>
    </div>
<?php endif; ?>
