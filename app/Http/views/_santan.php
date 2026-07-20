<?php
declare(strict_types=1);
/**
 * संतान-योग — computed report (topic under खोज-परिणाम, D1 prediction).
 * Consumes $view['santan'] = SantanEngine::compute() output.
 * @var array $view
 */
$sn = $view['santan'] ?? null;
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$toneCol = ['pos' => '#166534', 'neg' => '#991b1b', 'info' => '#854d0e'];
$toneBg  = ['pos' => '#dcfce7', 'neg' => '#fee2e2', 'info' => '#fef9c3'];
$chip = static function (string $txt, string $tone) use ($h, $toneCol, $toneBg): string {
    return '<span style="display:inline-block;font-weight:700;border-radius:999px;padding:1px 11px;font-size:.74rem;background:'
        . ($toneBg[$tone] ?? '#f1f5f9') . ';color:' . ($toneCol[$tone] ?? '#475569') . '">' . $h($txt) . '</span>';
};
?>
<div id="santan-report" class="hidden">
<?php if ($sn === null || empty($sn['ok'])): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        संतान-विश्लेषण के लिए जन्म-कुंडली आवश्यक है। कृपया जन्म-विवरण भरकर गणना करें।
    </div>
<?php else: ?>
    <style>
        .sn-card { background:#fff; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
        .sn-card h3 { font-size:1rem; margin:0 0 6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .sn-lead { border-left:5px solid #7c3aed; }
        .sn-sig { font-size:.86rem; line-height:1.6; margin:3px 0; padding-left:16px; position:relative; }
        .sn-sig::before { content:'•'; position:absolute; left:2px; }
        .sn-sig.pos::before { content:'✓'; color:#166534; }
        .sn-sig.neg::before { content:'✕'; color:#991b1b; }
        .sn-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; }
        .sn-pill-box { border:1px solid var(--line,#e4dcce); border-radius:9px; padding:9px 11px; font-size:.84rem; }
        .sn-pill-box b { color:#7c3aed; }
        .sn-win { border:1px solid var(--line,#e4dcce); border-left:4px solid #2E6E4E; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .sn-win.one { border-left-color:#854d0e; }
        .sn-rem { border-left:4px solid #7c3aed; background:#faf5ff; border-radius:0 8px 8px 0; padding:9px 12px; margin-top:8px; }
        .sn-rem li { margin-bottom:5px; font-size:.85rem; }
        .sn-why { color:#6b6156; font-size:.8rem; margin-top:4px; }
        .sn-warn { background:#fffbeb; border:1px solid #fde68a; border-radius:8px; padding:9px 12px; font-size:.82rem; color:#92400e; margin-top:6px; }
        .sn-concl { background:#f8fafc; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; font-size:.9rem; line-height:1.7; }
    </style>

    <div class="sn-card sn-lead">
        <h3>👶 संतान-योग (Progeny)
            <?= $chip('योग: ' . $sn['promise']['level'], $sn['promise']['tone']) ?>
            <span style="font-size:.72rem;color:#94a3b8">लग्न: <?= $h($sn['lagna_hi']) ?></span>
        </h3>
        <div style="font-size:.9rem"><?= $h($sn['promise']['text']) ?></div>
    </div>

    <!-- चार स्तंभ -->
    <div class="sn-card">
        <h3>🏛 चार स्तंभ (Four Pillars)</h3>
        <div class="sn-grid">
            <?php $f = $sn['pillars']['fifth']; ?>
            <div class="sn-pill-box">
                <b>5वाँ भाव</b> — <?= $h($f['sign_hi']) ?><?= $f['fertile'] ? ' (जल/बहु-प्रसव)' : ($f['barren'] ? ' (अल्प-संतान)' : '') ?><br>
                स्थित: <?= $f['occupants'] ? $h(implode(', ', $f['occupants'])) : '—' ?><br>
                अष्टकवर्ग: <?= (int) $f['sav'] ?> (<?= $h($f['sav_band']) ?>)<?= $f['guru_aspect'] ? ' · गुरु-दृष्टि ✓' : '' ?>
            </div>
            <?php $pm = $sn['pillars']['panchamesh']; ?>
            <div class="sn-pill-box">
                <b>पंचमेश</b> — <?= $h($pm['hi']) ?>, <?= $h($pm['house_ord']) ?> भाव · <?= $h($pm['dignity']) ?><br>
                <?= $pm['strong'] ? 'बली' : ($pm['afflicted'] ? 'पीड़ित' : 'सामान्य') ?><?= $pm['vargottama'] ? ' · वर्गोत्तम ✓' : '' ?><?= $pm['lagnesh_link'] ? ' · लग्नेश-संबंध ✓' : '' ?><br>
                नक्षत्र-स्वामी: <?= $h($pm['nak_lord']) ?>
            </div>
            <?php $g = $sn['pillars']['guru']; ?>
            <div class="sn-pill-box">
                <b>गुरु (पुत्र-कारक)</b> — <?= $h($g['house_ord']) ?> भाव · <?= $h($g['dignity']) ?><br>
                <?= $g['strong'] ? 'बली' : 'सामान्य' ?><?= $g['combust'] ? ' · अस्त' : '' ?><?= $g['debil'] ? ' · नीच' : '' ?><?= $g['retro'] ? ' · वक्री' : '' ?><br>
                5वें पर दृष्टि: <?= $g['aspect5'] ? '✓ (संतान-रक्षक)' : '—' ?>
            </div>
            <?php if (!empty($sn['pillars']['d7'])): $d = $sn['pillars']['d7']; ?>
            <div class="sn-pill-box">
                <b>D7 सप्तांश (मुख्य)</b> — लग्न <?= $h($d['lagna_hi']) ?><br>
                लग्नेश <?= $h($d['lagnesh']) ?><?= $d['lagnesh_house'] ? ' (' . (int) $d['lagnesh_house'] . 'वें)' : '' ?><br>
                गुरु: <?= $d['guru_house'] ? (int) $d['guru_house'] . 'वें भाव' : '—' ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- योग-संकेत -->
    <div class="sn-card">
        <h3>🧭 योग-संकेत (Evidence)</h3>
        <?php foreach ($sn['signals'] as $s): ?>
            <div class="sn-sig <?= $h($s['tone']) ?>"><?= $h($s['text']) ?></div>
        <?php endforeach; ?>
        <?php if (empty($sn['signals'])): ?><div style="font-size:.85rem;color:#6b6156">कोई विशेष संतान-योग/बाधक-योग नहीं मिला — पंचमेश व गुरु का बल निर्णायक।</div><?php endif; ?>
    </div>

    <!-- timing -->
    <div class="sn-card">
        <h3>🗓️ अगली संतान-संभावना (Timing)</h3>
        <?php if (!empty($sn['timing']['windows'])): ?>
            <?php foreach ($sn['timing']['windows'] as $w): $r = $w['rule'] ?? null; ?>
                <div class="sn-win <?= ($r['tone'] ?? 'info') === 'pos' ? '' : 'one' ?>">
                    <b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?>
                    <?php if ($r): ?><?= $chip($r['label'], $r['tone']) ?><?php endif; ?>
                    <div class="sn-why"><?= $h($w['why']) ?></div>
                    <?php if (!empty($w['gochar'])): ?><div class="sn-sig <?= $h($w['gochar']['tone']) ?>" style="margin-top:4px"><b>गोचर:</b> <?= $h($w['gochar']['text']) ?></div><?php endif; ?>
                    <?php if ($r): ?><div class="sn-why" style="color:<?= ($r['tone'] === 'pos') ? '#166534' : '#854d0e' ?>"><?= $h($r['text']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php elseif (empty($sn['timing']['has_dasha'])): ?>
            <div style="font-size:.85rem;color:#6b6156">दशा-गणना उपलब्ध नहीं।</div>
        <?php else: ?>
            <div style="font-size:.85rem;color:#6b6156">अगले ~12 वर्षों में संतान-कारक की कोई प्रबल दशा-खिड़की नहीं; प्रतीक्षा/उपाय-अवधि।</div>
        <?php endif; ?>
        <div class="sn-why" style="margin-top:6px"><?= $h($sn['timing']['note']) ?></div>
    </div>

    <!-- boy / girl (with strong caveat) -->
    <div class="sn-card">
        <h3>⚧ पुत्र या पुत्री? (पारंपरिक — अनिश्चित) <?= $chip($sn['gender']['lean'], 'info') ?></h3>
        <div style="font-size:.84rem;margin-bottom:6px">पारंपरिक सूत्रों का बहुमत — पुत्र-संकेत: <b><?= (int) $sn['gender']['putra'] ?></b> · पुत्री-संकेत: <b><?= (int) $sn['gender']['putri'] ?></b></div>
        <?php foreach ($sn['gender']['bits'] as $b): ?>
            <div class="sn-sig" style="color:<?= $b['side'] === 'putra' ? '#1d4ed8' : '#be185d' ?>"><?= $h($b['why']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($sn['gender']['boy_note'])): ?>
            <div class="sn-why" style="margin-top:6px"><b>पुत्र-संतान की अगली अवधि:</b> <?= $h($sn['gender']['boy_note']) ?></div>
        <?php endif; ?>
        <div class="sn-warn">⚠ <?= $h($sn['gender']['caveat']) ?></div>
    </div>

    <!-- shrap -->
    <?php if (!empty($sn['shrap'])): ?>
    <div class="sn-card">
        <h3>🪔 शाप-योग (पारंपरिक)</h3>
        <?php foreach ($sn['shrap'] as $s): ?>
            <div style="margin-bottom:7px">
                <b style="color:#991b1b"><?= $h($s['name']) ?></b> — <span style="font-size:.82rem;color:#6b6156"><?= $h($s['sanket']) ?></span>
                <div class="sn-why"><b>परिहार:</b> <?= $h($s['parihar']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- obstruction -->
    <div class="sn-card">
        <h3>🚧 बाधा का प्रकार</h3>
        <div style="font-size:.88rem;margin-bottom:<?= $sn['obstruction']['present'] ? '8px' : '0' ?>"><?= $h($sn['obstruction']['summary']) ?></div>
        <?php foreach ($sn['obstruction']['problems'] as $p): ?>
            <div class="sn-sig neg"><b><?= $h($p['hi']) ?></b> — <?= $h($p['kind']) ?>: <?= $h($p['text']) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- सूक्ष्म: बीज/क्षेत्र स्फुट -->
    <div class="sn-card">
        <h3>🌱 बीज / क्षेत्र स्फुट (सूक्ष्म)</h3>
        <div class="sn-grid">
            <div class="sn-pill-box"><b>बीज-स्फुट (पुरुष)</b> — <?= $h($sn['sphuta']['beeja_sign']) ?> राशि · <?= $sn['sphuta']['beeja_ok'] ? '✓ विषम (शुभ)' : 'विषम नहीं (सावधानी)' ?></div>
            <div class="sn-pill-box"><b>क्षेत्र-स्फुट (स्त्री)</b> — <?= $h($sn['sphuta']['kshetra_sign']) ?> राशि · <?= $sn['sphuta']['kshetra_ok'] ? '✓ सम (शुभ)' : 'सम नहीं (सावधानी)' ?></div>
        </div>
        <div class="sn-why" style="margin-top:6px"><?= $h($sn['sphuta']['note']) ?></div>
    </div>

    <!-- vargas -->
    <?php if (!empty($sn['varga'])): ?>
    <div class="sn-card">
        <h3>🔎 वर्ग-कुंडली विश्लेषण</h3>
        <?php foreach ($sn['varga'] as $v): ?>
            <div class="sn-sig <?= $h($v['tone']) ?>"><b><?= $h($v['chart']) ?>:</b> <?= $h($v['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- remedies -->
    <div class="sn-card">
        <h3>🛠 उपाय (Remedies)</h3>
        <div class="sn-rem">
            <div style="font-weight:700;color:#7c3aed;margin-bottom:6px">सामान्य संतान-उपाय</div>
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($sn['remedies']['general'] as $g): ?><li><?= $h($g) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php if (!empty($sn['remedies']['shrap'])): ?>
        <div class="sn-rem">
            <div style="font-weight:700;color:#7c3aed;margin-bottom:6px">शाप-विशेष परिहार</div>
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($sn['remedies']['shrap'] as $s): ?><li><b><?= $h($s['name']) ?>:</b> <?= $h($s['text']) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php if (!empty($sn['remedies']['planet'])): ?>
        <div class="sn-rem">
            <div style="font-weight:700;color:#7c3aed;margin-bottom:6px">ग्रह-बाधा उपाय</div>
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($sn['remedies']['planet'] as $p): ?><li><b><?= $h($p['hi']) ?>:</b> <?= $h($p['text']) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="sn-why" style="margin-top:8px"><?= $h($sn['remedies']['note']) ?></div>
    </div>

    <!-- conclusion -->
    <div class="sn-concl">
        <b style="color:#7c3aed">📌 निष्कर्ष:</b> <?= $h($sn['conclusion']) ?>
        <div style="font-size:.74rem;color:#94a3b8;margin-top:8px">यह विश्लेषण शास्त्र-अध्ययन हेतु है। संतान अत्यंत संवेदनशील विषय है — किसी भी निर्णय से पूर्व योग्य ज्योतिषी तथा आवश्यकता होने पर fertility-विशेषज्ञ दोनों से परामर्श अवश्य लें।</div>
    </div>
<?php endif; ?>
</div>
