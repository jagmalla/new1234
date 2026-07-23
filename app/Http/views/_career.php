<?php
declare(strict_types=1);
/**
 * करियर / नौकरी-व्यवसाय — computed report (topic under खोज-परिणाम, D1 prediction).
 * Consumes $view['career'] = CareerEngine::compute() output.
 * @var array $view
 */
$cr = $view['career'] ?? null;
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$toneCol = ['pos' => '#166534', 'neg' => '#991b1b', 'info' => '#854d0e'];
$toneBg  = ['pos' => '#dcfce7', 'neg' => '#fee2e2', 'info' => '#fef9c3'];
$chip = static function (string $txt, string $tone) use ($h, $toneCol, $toneBg): string {
    return '<span style="display:inline-block;font-weight:700;border-radius:999px;padding:1px 11px;font-size:.74rem;background:'
        . ($toneBg[$tone] ?? '#f1f5f9') . ';color:' . ($toneCol[$tone] ?? '#475569') . '">' . $h($txt) . '</span>';
};
?>
<div id="career-report" class="hidden">
<?php if ($cr === null || empty($cr['ok'])): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        करियर-विश्लेषण के लिए जन्म-कुंडली आवश्यक है। कृपया जन्म-विवरण भरकर गणना करें।
    </div>
<?php else: ?>
    <style>
        .cr-card { background:#fff; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
        .cr-card h3 { font-size:1rem; margin:0 0 6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .cr-lead { border-left:5px solid #0369a1; }
        .cr-sig { font-size:.86rem; line-height:1.6; margin:3px 0; padding-left:16px; position:relative; }
        .cr-sig::before { content:'•'; position:absolute; left:2px; }
        .cr-sig.pos::before { content:'✓'; color:#166534; }
        .cr-sig.neg::before { content:'✕'; color:#991b1b; }
        .cr-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:10px; }
        .cr-box { border:1px solid var(--line,#e4dcce); border-radius:9px; padding:9px 11px; font-size:.84rem; }
        .cr-box b { color:#0369a1; }
        .cr-tbl { width:100%; border-collapse:collapse; font-size:.83rem; }
        .cr-tbl th, .cr-tbl td { border-bottom:1px solid var(--line,#e4dcce); padding:5px 7px; text-align:left; }
        .cr-tbl th { color:#6b6156; font-weight:700; }
        .cr-win { border:1px solid var(--line,#e4dcce); border-left:4px solid #2E6E4E; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .cr-win.one { border-left-color:#854d0e; }
        .cr-warn { border:1px solid #fecaca; border-left:4px solid #ef4444; background:#fff7f7; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .cr-rem { border-left:4px solid #0369a1; background:#f0f9ff; border-radius:0 8px 8px 0; padding:9px 12px; margin-top:6px; }
        .cr-why { color:#6b6156; font-size:.8rem; margin-top:4px; }
        .cr-concl { background:#f8fafc; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; font-size:.9rem; line-height:1.7; }
    </style>

    <div class="cr-card cr-lead">
        <h3>💼 करियर — नौकरी · कार्य · व्यवसाय <span style="font-size:.72rem;color:#94a3b8">लग्न: <?= $h($cr['lagna_hi']) ?></span></h3>
        <div style="font-size:.9rem"><?= $h($cr['reference']['text']) ?></div>
        <div class="cr-why">लग्न-बल <?= $h($cr['reference']['lagna_r']) ?> · चन्द्र-बल <?= $h($cr['reference']['moon_r']) ?> · सूर्य-बल <?= $h($cr['reference']['sun_r']) ?></div>
    </div>

    <!-- job vs business — the headline answer -->
    <?php $jb = $cr['job_business']; ?>
    <div class="cr-card" style="border-left:5px solid <?= $jb['tone'] === 'pos' ? '#0369a1' : '#854d0e' ?>">
        <h3>⚖ नौकरी या व्यवसाय? <?= $chip($jb['verdict'], $jb['tone']) ?></h3>
        <div style="font-size:.9rem;margin-bottom:6px"><?= $h($jb['text']) ?></div>
        <div class="cr-grid">
            <div class="cr-box"><b>नौकरी-पक्ष (अंक <?= $h($jb['job_score']) ?>)</b>
                <?php foreach ($jb['job_bits'] as $x): ?><div class="cr-sig"><?= $h($x) ?></div><?php endforeach; ?>
                <?php if (empty($jb['job_bits'])): ?><div class="cr-why">विशेष सेवा-योग नहीं।</div><?php endif; ?>
            </div>
            <div class="cr-box"><b>व्यवसाय-पक्ष (अंक <?= $h($jb['biz_score']) ?>)</b>
                <?php foreach ($jb['biz_bits'] as $x): ?><div class="cr-sig"><?= $h($x) ?></div><?php endforeach; ?>
                <?php if (empty($jb['biz_bits'])): ?><div class="cr-why">विशेष व्यापार-योग नहीं।</div><?php endif; ?>
            </div>
        </div>
    </div>

    <!-- profession -->
    <div class="cr-card">
        <h3>🎯 उपयुक्त पेशा / क्षेत्र</h3>
        <div style="font-size:.88rem;margin-bottom:6px"><?= $h($cr['profession']['text']) ?></div>
        <?php foreach ($cr['profession']['fields'] as $f): ?>
            <div class="cr-sig"><b style="color:#0369a1"><?= $h($f['hi']) ?>:</b> <?= $h($f['field']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($cr['profession']['pair'])): ?>
            <div class="cr-sig pos"><b>मिश्र-क्षेत्र (दो प्रमुख ग्रह):</b> <?= $h($cr['profession']['pair']) ?></div>
        <?php endif; ?>
        <div class="cr-why">10वें की राशि-प्रवृत्ति: <?= $h($cr['profession']['sign_flavour']) ?> · दशमेश-भाव क्षेत्र: <?= $h($cr['profession']['house_field']) ?></div>
    </div>

    <!-- 10th house -->
    <?php $t = $cr['tenth']; ?>
    <div class="cr-card">
        <h3>🏛 10वाँ भाव (कर्म) व दशमेश</h3>
        <div class="cr-grid">
            <div class="cr-box"><b>10वाँ भाव</b> — <?= $h($t['sign_hi']) ?> (<?= $h($t['element']) ?>, <?= $h($t['modality']) ?>)<br>
                स्थित: <?= $t['occupants'] ? $h(implode(', ', $t['occupants'])) : '—' ?><br>
                दृष्टि: <?= $t['aspects'] ? $h(implode(', ', $t['aspects'])) : '—' ?> · अष्टकवर्ग <?= (int) $t['sav'] ?> (<?= $h($t['sav_band']) ?>)</div>
            <div class="cr-box"><b>दशमेश</b> — <?= $h($t['lord_hi']) ?>, <?= $h($t['lord_house_ord']) ?> भाव · <?= $h($t['lord_dignity']) ?><br>
                <?= $t['lord_combust'] ? 'अस्त · ' : '' ?><?= $t['lord_retro'] ? 'वक्री · ' : '' ?>नक्षत्र-स्वामी <?= $h($t['lord_nak_lord']) ?> · शड्बल <?= $h($t['lord_ratio']) ?> · विम्शो <?= (int) $t['lord_vim'] ?><br>
                क्षेत्र: <?= $h($t['lord_field']) ?></div>
        </div>
        <div class="cr-why" style="margin-top:6px"><?= $h($t['upachaya_note']) ?></div>
    </div>

    <!-- navamsha + D10 -->
    <div class="cr-card">
        <h3>🔎 नवांश (कर्मजीव) व दशांश (D-10)</h3>
        <div class="cr-sig"><b>नवांश:</b> <?= $h($cr['navamsha']['text']) ?></div>
        <div class="cr-sig" style="margin-top:4px"><b>आजीविका-स्रोत:</b> <?= $h($cr['navamsha']['dispositor_hi']) ?> — <?= $h($cr['navamsha']['source']) ?></div>
        <?php if (!empty($cr['dashamsha'])): ?>
            <div class="cr-sig" style="margin-top:6px"><?= $h($cr['dashamsha']['text']) ?></div>
            <div class="cr-why">D-10: दशमेश <?= $h($cr['dashamsha']['tenth_lord']) ?> · 6ठे में ग्रह <?= (int) $cr['dashamsha']['sixth_planets'] ?> (सेवा) · 7वें में <?= (int) $cr['dashamsha']['seventh_planets'] ?> (व्यापार) · सूर्य केंद्र में: <?= $cr['dashamsha']['sun_kendra'] ? 'हाँ (सरकारी)' : 'नहीं' ?></div>
        <?php endif; ?>
    </div>

    <!-- shadbala + vimshopaka -->
    <div class="cr-card">
        <h3>💪 शड्बल व षोडशवर्ग बल</h3>
        <div class="cr-sig"><?= $h($cr['shadbala']['text']) ?></div>
        <div style="overflow-x:auto;margin-top:6px">
        <table class="cr-tbl">
            <thead><tr><th>ग्रह</th><th>शड्बल</th><th>दिग्बल</th><th>बल</th></tr></thead>
            <tbody>
            <?php foreach ($cr['shadbala']['rows'] as $r): ?>
                <tr><td><?= $h($r['hi']) ?></td><td><?= $h($r['ratio']) ?></td><td><?= $h($r['dig']) ?></td><td><?= $r['strong'] ? $chip('बली', 'pos') : $chip('दुर्बल', 'info') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="cr-why" style="margin-top:6px"><?= $h($cr['vimshopaka']['text']) ?></div>
        <div style="margin-top:3px;font-size:.84rem">करियर-ग्रह विम्शोपक: <?php foreach ($cr['vimshopaka']['rows'] as $x): ?><b><?= $h($x['hi']) ?></b> <?= (int) $x['score'] ?>/20 (<?= $h($x['band']) ?>) &nbsp; <?php endforeach; ?></div>
    </div>

    <!-- bhavat bhavam + saturn/shatru -->
    <div class="cr-card">
        <h3>🔗 भावात् भावम् (7वाँ) व शनि/शत्रु-नियम</h3>
        <div class="cr-sig"><?= $h($cr['bhavat']['text']) ?></div>
        <?php foreach ($cr['saturn']['lines'] as $l): ?>
            <div class="cr-sig" style="margin-top:4px"><?= $h($l) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- career yogas (kendra-trikona etc.) -->
    <?php if (!empty($cr['yoga'])): ?>
    <div class="cr-card">
        <h3>✨ केंद्र-त्रिकोण व करियर-योग</h3>
        <?php foreach ($cr['yoga'] as $y): ?>
            <div class="cr-sig <?= $h($y['tone']) ?>"><?= $h($y['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- dasha -->
    <?php if (!empty($cr['dasha']['has_dasha'])): ?>
    <div class="cr-card">
        <h3>⏳ महादशा — वर्तमान व आगामी</h3>
        <div class="cr-sig"><b>वर्तमान:</b> <?= $h($cr['dasha']['current_md']) ?>–<?= $h($cr['dasha']['current_ad']) ?> — <?= $h($cr['dasha']['current_flavour']) ?></div>
        <?php if (!empty($cr['dasha']['next_md'])): ?>
            <div class="cr-sig" style="margin-top:4px"><b>आगामी:</b> <?= $h($cr['dasha']['next_md']) ?> महादशा (<?= $h($cr['dasha']['next_from']) ?> से) — <?= $h($cr['dasha']['next_flavour']) ?></div>
        <?php endif; ?>
        <div class="cr-why" style="margin-top:4px"><?= $h($cr['dasha']['text']) ?></div>
    </div>
    <?php endif; ?>

    <!-- promotion / downfall timing -->
    <div class="cr-card">
        <h3>🗓️ पदोन्नति / वृद्धि व सतर्कता-काल</h3>
        <?php if (!empty($cr['timing']['windows'])): ?>
            <div style="font-weight:700;font-size:.85rem;color:#166534;margin-bottom:4px">📈 उन्नति/विस्तार-खिड़कियाँ</div>
            <?php foreach ($cr['timing']['windows'] as $w): $v = $w['verdict']; ?>
                <div class="cr-win <?= $v['tone'] === 'pos' ? '' : 'one' ?>">
                    <b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?> <?= $chip($v['label'], $v['tone']) ?>
                    <div class="cr-why"><?= $h($w['why']) ?></div>
                    <?php if (!empty($w['gochar'])): ?><div class="cr-sig <?= $h($w['gochar']['tone']) ?>" style="margin-top:3px"><b>गोचर:</b> <?= $h($w['gochar']['text']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($cr['timing']['warnings'])): ?>
            <div style="font-weight:700;font-size:.85rem;color:#991b1b;margin:8px 0 4px">📉 सतर्कता-काल (संभावित गिरावट/ठहराव)</div>
            <?php foreach ($cr['timing']['warnings'] as $w): ?>
                <div class="cr-warn"><b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?><div class="cr-why"><?= $h($w['why']) ?></div></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($cr['timing']['sade_sati'])): ?>
            <div class="cr-warn" style="border-left-color:#f59e0b;background:#fffbeb">⏳ <?= $h($cr['timing']['sade_sati']) ?></div>
        <?php endif; ?>
        <?php if (empty($cr['timing']['windows']) && empty($cr['timing']['warnings'])): ?>
            <div style="font-size:.85rem;color:#6b6156">अगले ~12 वर्षों में करियर-कारक की कोई प्रबल दशा-खिड़की/चेतावनी नहीं।</div>
        <?php endif; ?>
        <div class="cr-why" style="margin-top:6px"><?= $h($cr['timing']['note']) ?></div>
    </div>

    <!-- remedies -->
    <?php if (!empty($cr['remedies']['rows'])): ?>
    <div class="cr-card">
        <h3>🛠 उपाय (पीड़ित करियर-ग्रह हेतु)</h3>
        <div class="cr-rem">
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($cr['remedies']['rows'] as $rm): ?><li style="margin-bottom:5px"><b><?= $h($rm['hi']) ?>:</b> <?= $h($rm['text']) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <div class="cr-why" style="margin-top:6px"><?= $h($cr['remedies']['note']) ?></div>
    </div>
    <?php endif; ?>

    <!-- conclusion -->
    <div class="cr-concl">
        <b style="color:#0369a1">📌 निष्कर्ष:</b> <?= $h($cr['conclusion']) ?>
        <div style="font-size:.74rem;color:#94a3b8;margin-top:8px">यह विश्लेषण शास्त्र-आधारित मार्गदर्शन है; अंतिम करियर-निर्णय अपने कौशल, अवसर व परिश्रम को भी साथ रखकर लें।</div>
    </div>
<?php endif; ?>
</div>
