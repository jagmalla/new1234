<?php
/**
 * मुंथा फल pane of the Varshaphal prediction panel (inner of #vp-pred-muntha).
 * Rendered by calc-v2 and re-rendered by the varshaphal JSON endpoint on a year
 * change. Scope: $view['muntha'] (MunthaPhalEngine output), $h, $pcolor,
 * $grahaHi. Mirrors the सहम / ताजिक / वर्षेश card look.
 */
$mn = $view['muntha'] ?? null;
$vTone = static fn(string $t): string => $t === 'pos' ? 'gc-shubh' : ($t === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
$noteCls = static fn(string $t): string => $t === 'pos' ? 'gph-pos' : ($t === 'neg' ? 'gph-neg' : 'gph-info');
$natWord = static fn(string $n): string => $n;
if ($mn !== null && !empty($mn['munthesh'])):
?>
    <!-- Header -->
    <div class="saham-active">
        <div style="font-size:1.02rem"><b>मुंथा:</b>
            <b><?= $h($mn['muntha_sign_hi']) ?> <?= $h($mn['muntha_deg']) ?></b> — वर्ष-कुंडली के <b><?= (int) $mn['muntha_house'] ?>वें</b> भाव में
            <span class="gph-house"><?= $h($mn['band']) ?></span>
        </div>
        <div style="margin-top:3px"><b>मुंथेश:</b>
            <span style="color:<?= $pcolor($mn['munthesh']) ?>;font-weight:700"><?= $h($mn['munthesh_hi']) ?></span>
            <span class="text-xs text-gray-500"><?= (int) $mn['munthesh_house'] ?>वें भाव · पंचवर्गीय <?= $h($mn['munthesh_band']) ?></span>
            <?php if (($mn['munthesh_combust'] ?? 0) >= 40): ?><span class="gc-chip gc-ashubh">अस्त <?= (int) $mn['munthesh_combust'] ?>%</span><?php endif; ?>
            <?php if (!empty($mn['munthesh_retro'])): ?><span class="text-xs" style="color:#b91c1c">वक्री ®</span><?php endif; ?>
            <span class="gc-chip <?= $vTone($mn['verdict_tone']) ?>">मुंथा-वर्ष: <?= $h($mn['verdict']) ?></span>
        </div>
    </div>

    <div id="muntha-detail-pane" class="overflow-y-auto pr-1" style="max-height:460px">
        <?php if (!empty($mn['maran_yoga'])): ?>
        <div class="saham-card" style="border-color:#8A2F2F;background:#f9ecea">
            <div class="saham-card-head"><span class="saham-name" style="color:#8A2F2F">⚠ मरण-योग (श्लोक 34+35)</span></div>
            <div class="gph-note gph-neg">मुंथेश श्लोक 34 व 35 दोनों दोषों से पीड़ित — शास्त्र इसे मरण-योग कहता है। यह गंभीर संकेत है; अन्य बलों व उपायों सहित विचार करें।</div>
        </div>
        <?php endif; ?>

        <!-- A. BHAVA -->
        <div class="gph-section-title">मुंथा-भाव फल <span class="text-xs text-gray-400 font-normal">(श्लोक 5–16)</span></div>
        <div class="saham-card">
            <div class="saham-card-head">
                <span class="saham-name"><?= (int) $mn['muntha_house'] ?>वें भाव में मुंथा</span>
                <span class="gc-chip <?= str_contains($mn['band'], 'अति') ? 'gc-shubh' : (str_contains($mn['band'], 'अशुभ') ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h($mn['band']) ?></span>
            </div>
            <div class="saham-phal">● <?= $h($mn['bhava']['text']) ?></div>
        </div>

        <!-- B. GRAHA -->
        <?php if (!empty($mn['graha'])): ?>
        <div class="gph-section-title" style="margin-top:10px">ग्रह-योग / राशि <span class="text-xs text-gray-400 font-normal">(श्लोक 24–29)</span></div>
        <?php foreach ($mn['graha'] as $g): ?>
        <div class="saham-card">
            <div class="saham-card-head">
                <span class="saham-name" style="color:<?= $pcolor($g['planet']) ?>"><?= $h($g['planet_hi']) ?></span>
                <span class="gc-chip <?= $g['nature'] === 'शुभ' ? 'gc-shubh' : ($g['nature'] === 'अशुभ' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h($g['nature']) ?></span>
                <span class="text-xs text-gray-500"><?= $h($g['reasons']) ?></span>
            </div>
            <div class="saham-phal">● <?= $h($g['text']) ?></div>
            <?php if (!empty($g['clause'])): ?><div class="gph-note gph-info">◆ लागू खण्ड: <?= $h($g['clause']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- C. RAHU -->
        <?php if (!empty($mn['rahu'])): $r = $mn['rahu']; ?>
        <div class="gph-section-title" style="margin-top:10px">राहु मुख/पृष्ठ/पुच्छ <span class="text-xs text-gray-400 font-normal">(श्लोक 30–32)</span></div>
        <div class="saham-card">
            <div class="saham-card-head">
                <span class="saham-name">मुंथा राहु के <?= $h($r['zone_hi']) ?>-अंश में</span>
                <span class="gc-chip <?= $r['nature'] === 'शुभ' ? 'gc-shubh' : ($r['nature'] === 'अशुभ' ? 'gc-ashubh' : 'gc-mishrit') ?>"><?= $h($r['nature']) ?></span>
                <?php if (!empty($r['bonus'])): ?><span class="gc-chip gc-shubh">शुक्र/गुरु योग — पद-प्राप्ति</span><?php endif; ?>
            </div>
            <div class="saham-phal">● <?= $h($r['text']) ?></div>
            <?php if (!empty($r['pathabheda'])): ?>
            <div class="gph-note gph-info">पाठ-भेद: पृष्ठ-अंश का फल — कुन्तला "शुभप्रद नहीं", ज्योति-टीका "कल्याणकारी"। वर्तमान सेटिंग: <b><?= $r['verdict'] === 'shubh' ? 'शुभ' : 'अशुभ (मूल)' ?></b> (Admin से बदला जा सकता है)।</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- D. SPECIAL -->
        <?php if (!empty($mn['modifiers'])): ?>
        <div class="gph-section-title" style="margin-top:10px">विशेष / सेतु नियम <span class="text-xs text-gray-400 font-normal">(श्लोक 3 · 17–23 · 33–36)</span></div>
        <?php foreach ($mn['modifiers'] as $m): ?>
        <div class="saham-card" style="margin-bottom:6px">
            <div class="saham-card-head"><span class="saham-name" style="font-size:.86rem"><?= $h($m['situation']) ?></span>
                <span class="gc-chip <?= $vTone($m['tone']) ?>"><?= $m['tone'] === 'pos' ? 'शुभ' : ($m['tone'] === 'neg' ? 'अशुभ' : 'सूचना') ?></span></div>
            <div class="gph-note <?= $noteCls($m['tone']) ?>" style="border-left:none;background:none;padding:2px 0"><?= $h($m['text']) ?></div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <p class="text-xs text-gray-400" style="margin-top:6px">फल-क्रम: भाव → ग्रह-योग/राशि → राहु मुख/पृष्ठ/पुच्छ → विशेष नियम (जो सक्रिय हों)। मुंथेश के ताजिक योग "ताजिक योग → मुख्य योग" दृश्य में देखें। जन्म-वर्ष सेतु (श्लोक 19–23) config से।</p>
    </div>
<?php else: ?>
    <div class="gochar-pred-soon">
        <div class="gps-icon">🔮</div>
        <div class="gps-title">मुंथा फल उपलब्ध नहीं</div>
        <div class="gps-sub"><?= $h((string) ($mn['error'] ?? 'वर्ष कुंडली गणना के बाद मुंथा-भाव फल व मुंथेश-विचार यहाँ दिखेगा।')) ?></div>
    </div>
<?php endif; ?>
