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
$roleChip = static fn(string $r): string => $r === 'yogakaraka' ? 'yk-yoga' : ($r === 'benefic' ? 'gc-shubh' : ($r === 'malefic' ? 'gc-ashubh' : 'gc-mishrit'));
if ($py !== null && !empty($py['groups'])):
    $cats = $py['categories'];   // hi => slug
    $sum = $py['active_summary'] ?? ['shubh' => 0, 'ashubh' => 0, 'mishrit' => 0];
    $yk = $py['yogakaraka'] ?? null;
?>
    <?php $glossaryScope = 'birth'; require __DIR__ . '/_glossary.php'; ?>
    <div class="yoga-sec-title">कुंडली के सक्रिय योग
        <span class="gc-chip gc-shubh" style="margin-left:6px">कुल सक्रिय: <?= (int) $py['detected_count'] ?></span>
        <span class="text-xs text-gray-400 font-normal" style="margin-left:4px"><?= (int) ($sum['shubh'] ?? 0) ?> शुभ · <?= (int) ($sum['ashubh'] ?? 0) ?> अशुभ<?= ($sum['mishrit'] ?? 0) ? ' · ' . (int) $sum['mishrit'] . ' मिश्र' : '' ?></span>
    </div>

    <?php if ($yk !== null && !empty($yk['roles'])): ?>
    <!-- Yogakaraka classification for this lagna (BPHS Adhyaya 32). -->
    <div class="yk-box">
        <div class="yk-head">इस लग्न (<b><?= $h($yk['lagna_hi']) ?></b>) हेतु ग्रह-वर्गीकरण <span class="text-xs text-gray-400 font-normal">(बृ.पा.हो.शा. अध्याय 32)</span></div>
        <div class="yk-grid">
            <?php foreach ($yk['roles'] as $pl => $r): ?>
            <span class="yk-pill" title="<?= $h($r['note']) ?>"><b><?= $h($r['planet_hi']) ?></b>
                <span class="gc-chip <?= $roleChip($r['role']) ?>"><?= $h($r['role_hi']) ?></span><?= !empty($r['is_marak']) ? '<span class="gc-chip yk-marak">मारक</span>' : '' ?></span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($yk['table']['yogakaraka'])): ?><div class="yk-ref">ग्रन्थ-सन्दर्भ: योगकारक — <?= $h($yk['table']['yogakaraka']) ?>; मारक — <?= $h($yk['table']['marak'] ?? '—') ?> (श्लोक <?= $h($yk['table']['shloka'] ?? '') ?>)।</div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="pred-picker" style="margin-top:8px;gap:8px;flex-wrap:wrap">
        <label class="pred-picker-label" for="py-cat">श्रेणी</label>
        <select id="py-cat" class="pred-inline-select" size="1">
            <option value="active" selected>✓ सक्रिय — इस कुंडली में बने</option>
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
                <?php if (!empty($y['phal_dasha']['planets'])): ?>
                <div class="yoga-dasha"><b>फल-दशा:</b>
                    <?php foreach ($y['phal_dasha']['planets'] as $pd): ?>
                    <span class="yoga-dasha-pill"><?= $h($pd['planet_hi']) ?> <span class="gc-chip <?= $roleChip($pd['role']) ?>"><?= $h($pd['role_hi']) ?></span><?= $pd['dasha'] !== null ? ' <span class="yd-dates">' . $h($pd['dasha']) . '</span>' : '' ?></span>
                    <?php endforeach; ?>
                    <div class="text-xs text-gray-400" style="margin-top:2px">इन ग्रहों की महादशा/अन्तर्दशा में योग-फल प्रकट होने की सम्भावना (बलाबल-सापेक्ष)।</div>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <div id="py-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">इस चयन के लिए कोई योग नहीं।</div>
        <p class="text-xs text-gray-400" style="margin-top:8px">"इस कुंडली में" = D1 कुंडली पर संगणित नियम सिद्ध। "सन्दर्भ" = पूर्ण षड्बल/सूक्ष्म-दृष्टि आधारित योग — ज्योतिषी स्वयं विचार करें। स्रोत: फलदीपिका, अध्याय 6–7।</p>
    </div>
<?php endif; ?>
