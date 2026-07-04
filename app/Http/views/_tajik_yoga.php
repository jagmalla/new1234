<?php
/**
 * ताजिक योग pane of the Varshaphal prediction panel (calc-v2). Rendered inside
 * #vp-pred-tajik with the calc view's scope: $view, $h, $pcolor, $grahaHi.
 * Data: $view['tajik'] = CalcController::tajik() payload (TajikYogaEngine).
 * Layout mirrors the सहम pane (header strip → selector → filtered cards) so
 * the panel keeps the site's existing look.
 */
$tj = $view['tajik'] ?? null;
$tjPlanets = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
if ($tj !== null && (!empty($tj['yogas']) || !empty($tj['chart_yogas']) || !empty($tj['drishti']))):
    $tjTz = (float) ($tj['tz'] ?? 0.0);
    $tjFmtJd = static fn($jd) => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $jd, $tjTz);
    $tjTone = static fn(int $score): string => $score > 0 ? 'gc-shubh' : ($score < 0 ? 'gc-ashubh' : 'gc-mishrit');
    $tjVerdict = static fn(int $score): string => $score > 0 ? 'शुभ' : ($score < 0 ? 'अशुभ' : 'मिश्रित');
    $roles = $tj['roles'] ?? [];
    $chips = $tj['chips'] ?? [];
    $muddaLord = (string) ($roles['mudda'] ?? '');
    $munthesh = (string) ($roles['munthesh'] ?? '');
    $lagnesh = (string) ($roles['lagnesh'] ?? '');
    $varshesh = (string) ($roles['varshesh'] ?? '');
    $ap = $tj['active_period'] ?? null;
    // Small कुत्थ/दुरफ chip after a planet's name.
    $tjChip = static function (string $p) use ($chips, $h): string {
        $c = $chips[$p] ?? null;
        if ($c === null) { return ''; }
        $cls = $c['tone'] === 'pos' ? 'gc-shubh' : ($c['tone'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
        return ' <span class="gc-chip ' . $cls . '" title="' . $h(implode(', ', $c['why'])) . '">' . $h($c['word']) . '</span>';
    };
    $tjName = static fn(string $p): string => \AutoBusiness\Astro\Tajik\TajikYogaEngine::hi($p);
    $defaultView = $muddaLord !== '' ? 'mudda' : 'all';
?>
    <!-- Active Mudda mahadasha + the year's office-bearers -->
    <div class="saham-active">
        <div><b>सक्रिय मुद्दा-दशा:</b>
            <span style="color:<?= $pcolor($muddaLord) ?>;font-weight:700"><?= $h($grahaHi[$muddaLord] ?? $muddaLord) ?></span>
            <?php if ($ap !== null): ?><span class="text-xs text-gray-500">(<?= $h($tjFmtJd($ap['start_jd'])) ?> – <?= $h($tjFmtJd($ap['end_jd'])) ?>)</span><?php endif; ?>
            <?= $muddaLord !== '' ? $tjChip($muddaLord) : '' ?>
            <span class="text-xs text-gray-500">(<?= ($tj['is_day'] ?? true) ? 'दिन-वर्षप्रवेश' : 'रात्रि-वर्षप्रवेश' ?>)</span></div>
        <div class="saham-related">वर्ष-अधिकारी:
            <?php foreach ([['मुंथेश', $munthesh], ['लग्नेश', $lagnesh], ['वर्षेश', $varshesh]] as [$lbl, $pl]): if ($pl === '') { continue; } ?>
                <button type="button" class="saham-chip gc-mishrit" data-goto-planet="<?= $h($pl) ?>"><?= $lbl ?>
                    <b style="color:<?= $pcolor($pl) ?>"><?= $h($grahaHi[$pl] ?? $pl) ?></b></button><?= $tjChip($pl) ?>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- View selector: मुद्दा दशेश (default) / मुख्य योग / सभी ग्रह / per planet -->
    <div class="pred-picker" style="margin-top:8px">
        <label class="pred-picker-label" for="tajik-select">दृश्य चुनें</label>
        <select id="tajik-select" class="pred-inline-select" size="1">
            <?php if ($muddaLord !== ''): ?>
            <option value="mudda"<?= $defaultView === 'mudda' ? ' selected' : '' ?>>● मुद्दा दशेश — <?= $h($grahaHi[$muddaLord] ?? $muddaLord) ?> (सक्रिय)</option>
            <?php endif; ?>
            <option value="main">मुख्य योग — मुंथेश व लग्नेश</option>
            <option value="all"<?= $defaultView === 'all' ? ' selected' : '' ?>>सभी ग्रह (All)</option>
            <?php foreach ($tjPlanets as $pl): ?>
                <option value="<?= $h($pl) ?>"><?= $h($grahaHi[$pl] ?? $pl) ?> — <?= $h(($chips[$pl]['word'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div id="tajik-detail-pane" class="overflow-y-auto pr-1" style="max-height:420px"
         data-mudda="<?= $h($muddaLord) ?>" data-munthesh="<?= $h($munthesh) ?>" data-lagnesh="<?= $h($lagnesh) ?>">

        <?php foreach (($tj['chart_yogas'] ?? []) as $y): ?>
        <div class="saham-card tajik-card" data-chart="1" data-planets="<?= $h(implode(',', $y['participants'])) ?>">
            <div class="saham-card-head">
                <span class="saham-name"><?= $h($y['name_hi']) ?></span>
                <span class="gc-chip <?= $tjTone((int) $y['score']) ?>"><?= $h($y['nature']) ?></span>
                <span class="saham-tag dup">चक्र-स्तरीय</span>
            </div>
            <?php if (!empty($y['lakshan'])): ?><div class="saham-signifies"><?= $h($y['lakshan']) ?></div><?php endif; ?>
            <div class="saham-phal">● <?= $h($y['phal']) ?></div>
        </div>
        <?php endforeach; ?>

        <?php foreach (($tj['yogas'] ?? []) as $y):
            $parts = $y['participants'];
            $all = $parts;
            if (!empty($y['mediator'])) { $all[] = $y['mediator']; }
        ?>
        <div class="saham-card tajik-card" data-planets="<?= $h(implode(',', $all)) ?>" data-yoga="<?= $h($y['yoga_key']) ?>">
            <div class="saham-card-head">
                <span class="saham-name"><?= $h($y['name_hi']) ?></span>
                <span class="gc-chip <?= $tjTone((int) $y['score']) ?>"><?= $h($tjVerdict((int) $y['score'])) ?></span>
                <?php if (!empty($y['subtype'])): ?><span class="saham-tag dup"><?= $h($y['subtype']) ?></span><?php endif; ?>
                <?php foreach (($y['tags'] ?? []) as $t): ?>
                    <span class="gc-chip <?= $t['tone'] === 'pos' ? 'gc-shubh' : ($t['tone'] === 'neg' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h($t['name_hi']) ?></span>
                <?php endforeach; ?>
            </div>
            <div class="saham-pos">
                <?php foreach ($parts as $k => $pl): ?><?= $k > 0 ? ' + ' : '' ?><b style="color:<?= $pcolor($pl) ?>"><?= $h($grahaHi[$pl] ?? $pl) ?></b><?php endforeach; ?>
                <?php if (!empty($y['mediator'])): ?> · मध्यस्थ: <b style="color:<?= $pcolor($y['mediator']) ?>"><?= $h($grahaHi[$y['mediator']] ?? $y['mediator']) ?></b><?php endif; ?>
            </div>
            <?php if (!empty($y['detail'])): ?><div class="saham-facts"><?= $h($y['detail']) ?></div><?php endif; ?>
            <div class="saham-phal">● <?= $h($y['phal']) ?></div>
            <?php foreach (($y['tags'] ?? []) as $t): if (empty($t['text'])) { continue; } ?>
                <div class="saham-phal">◆ <b><?= $h($t['name_hi']) ?>:</b> <?= $h($t['text']) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>

        <div id="tajik-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">
            इस दृश्य के ग्रह से जुड़ा कोई ताजिक योग इस वर्ष-कुंडली में नहीं बन रहा — "सभी ग्रह" चुनकर पूरी सूची देखें।
        </div>

        <!-- Per-planet sphuta-drishti row (shown for single-planet contexts) -->
        <?php foreach ($tjPlanets as $pa): $row = $tj['drishti'][$pa] ?? []; if ($row === []) { continue; } ?>
        <div class="saham-card tajik-drow hidden" data-drow="<?= $h($pa) ?>">
            <div class="saham-card-head"><span class="saham-name" style="font-size:.95rem"><?= $h($grahaHi[$pa] ?? $pa) ?> की दृष्टियाँ</span><?= $tjChip($pa) ?></div>
            <div class="saham-facts">
                <?php foreach ($row as $pb => $c): ?>
                    <div>→ <b style="color:<?= $pcolor($pb) ?>"><?= $h($grahaHi[$pb] ?? $pb) ?></b> —
                        <?= $h((string) $c['kala']) ?> कला · <?= $h($c['type_hi']) ?> · <?= $h($c['maitri']) ?> · <?= $c['vaam'] ? 'वाम' : 'दक्षिण' ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- दृष्टि सारणी — the full 7×7 sphuta matrix (PL-verification table) -->
        <details class="tajik-matrix-wrap" style="margin-top:8px">
            <summary style="cursor:pointer;font-weight:700;font-size:.85rem">दृष्टि सारणी (7×7 स्फुट कला)</summary>
            <div class="overflow-x-auto" style="margin-top:6px">
                <table class="tajik-matrix">
                    <thead><tr><th>द्रष्टा ↓</th>
                        <?php foreach ($tjPlanets as $pb): ?><th style="color:<?= $pcolor($pb) ?>"><?= $h($grahaHi[$pb] ?? $pb) ?></th><?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($tjPlanets as $pa): ?>
                        <tr><th style="color:<?= $pcolor($pa) ?>;text-align:left"><?= $h($grahaHi[$pa] ?? $pa) ?></th>
                        <?php foreach ($tjPlanets as $pb):
                            if ($pa === $pb) { echo '<td class="tm-self">—</td>'; continue; }
                            $c = $tj['drishti'][$pa][$pb] ?? null;
                            if ($c === null) { echo '<td>?</td>'; continue; }
                            $cls = $c['type'] === 'sneha' ? 'tm-sneha' : ($c['type'] === 'vair' ? 'tm-vair' : 'tm-none');
                        ?>
                            <td class="<?= $cls ?>" title="<?= $h($c['type_hi'] . ' · ' . $c['maitri'] . ' · ' . ($c['vaam'] ? 'वाम' : 'दक्षिण')) ?>"><?= $h((string) $c['kala']) ?></td>
                        <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="text-xs text-gray-400" style="margin-top:4px">हरा = स्नेह-दृष्टि · लाल = वैर-दृष्टि · धूसर = दृष्टि-रहित क्षेत्र (ध्रुवांक-अन्तर्वेशन से कला)</div>
        </details>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🔮</div>
        <div class="gps-title">ताजिक योग उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($tj['error'] ?? 'वर्ष कुंडली गणना के बाद ताजिक दृष्टि व 16 योग यहाँ दिखेंगे।')) ?></div>
    </div>
<?php endif; ?>
