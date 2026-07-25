<?php
declare(strict_types=1);
/**
 * राजनीति / Politics — computed report (topic under खोज-परिणाम, D1 prediction).
 * Consumes $view['politics'] = PoliticsEngine::compute() output.
 * Colourful, emoji-led, self-contained CSS, responsive on all devices.
 * @var array $view
 */
$po = $view['politics'] ?? null;
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$toneCol = ['pos' => '#166534', 'neg' => '#991b1b', 'mix' => '#854d0e', 'info' => '#854d0e'];
$toneBg  = ['pos' => '#dcfce7', 'neg' => '#fee2e2', 'mix' => '#fef9c3', 'info' => '#fef9c3'];
$chip = static function (string $txt, string $tone) use ($h, $toneCol, $toneBg): string {
    return '<span style="display:inline-block;font-weight:700;border-radius:999px;padding:1px 11px;font-size:.74rem;background:'
        . ($toneBg[$tone] ?? '#f1f5f9') . ';color:' . ($toneCol[$tone] ?? '#475569') . '">' . $h($txt) . '</span>';
};
// level band → colour + emoji
$bandInfo = [
    'worker' => ['#94a3b8', '🧍', 10], 'local' => ['#0891b2', '🏘️', 30], 'district' => ['#7c3aed', '🏛️', 50],
    'state' => ['#d97706', '🗳️', 70], 'national' => ['#dc2626', '🏵️', 88], 'supreme' => ['#b45309', '👑', 100],
];
?>
<div id="politics-report" class="hidden">
<?php if ($po === null || empty($po['ok'])): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        राजनीतिक-विश्लेषण के लिए जन्म-कुंडली आवश्यक है। कृपया जन्म-विवरण भरकर गणना करें।
    </div>
<?php else:
    $lv = $po['level']; $bi = $bandInfo[$lv['band']] ?? ['#94a3b8', '🧍', 10];
    $pct = $lv['max'] > 0 ? (int) round($lv['score'] / $lv['max'] * 100) : 0;
    $idn = $po['identify'];
?>
    <style>
        .pol-card { background:#fff; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
        .pol-card h3 { font-size:1rem; margin:0 0 7px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .pol-lead { border-left:5px solid #b45309; background:linear-gradient(180deg,#fffdf8,#fdf6ec); }
        .pol-sig { font-size:.86rem; line-height:1.6; margin:3px 0; padding-left:16px; position:relative; }
        .pol-sig::before { content:'•'; position:absolute; left:2px; color:#b45309; }
        .pol-sig.pos::before { content:'✓'; color:#166534; }
        .pol-sig.neg::before { content:'✕'; color:#991b1b; }
        .pol-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; }
        .pol-box { border:1px solid var(--line,#e4dcce); border-radius:9px; padding:9px 11px; font-size:.84rem; }
        .pol-box b { color:#b45309; }
        .pol-tbl { width:100%; border-collapse:collapse; font-size:.83rem; }
        .pol-tbl th, .pol-tbl td { border-bottom:1px solid var(--line,#e4dcce); padding:5px 7px; text-align:left; }
        .pol-tbl th { color:#6b6156; font-weight:700; }
        .pol-why { color:#6b6156; font-size:.8rem; margin-top:4px; }
        .pol-concl { background:#f8fafc; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; font-size:.9rem; line-height:1.7; }
        /* level meter */
        .pol-meter-wrap { border-radius:12px; padding:14px; background:linear-gradient(135deg,#fff7ed,#fef3c7); border:1px solid #fcd34d; }
        .pol-level-big { font-size:1.35rem; font-weight:900; line-height:1.2; }
        .pol-track { height:16px; border-radius:999px; background:#e5e7eb; overflow:hidden; margin:9px 0 4px; position:relative; }
        .pol-fill { height:100%; border-radius:999px; }
        .pol-ladder { display:flex; gap:4px; margin-top:8px; flex-wrap:wrap; }
        .pol-rung { flex:1 1 auto; min-width:74px; text-align:center; font-size:.66rem; font-weight:700; padding:4px 3px; border-radius:7px; background:#fff; border:1px solid #e5e7eb; color:#94a3b8; }
        .pol-rung.on { color:#fff; }
        .pol-factors { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:4px 12px; margin-top:8px; }
        .pol-fac { font-size:.8rem; }
        .pol-yg { border:1px solid #d1fae5; border-left:4px solid #10b981; background:#f0fdf4; border-radius:8px; padding:6px 10px; margin-bottom:6px; font-size:.85rem; }
        .pol-yg.warn { border-color:#fecaca; border-left-color:#ef4444; background:#fff7f7; }
        .pol-win { border:1px solid var(--line,#e4dcce); border-left:4px solid #2E6E4E; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .pol-win.one { border-left-color:#854d0e; }
        .pol-rem { border-left:4px solid #b45309; background:#fff7ed; border-radius:0 8px 8px 0; padding:9px 12px; margin-top:6px; }
        .pol-pill { display:inline-flex; align-items:center; gap:4px; font-size:.78rem; font-weight:700; border-radius:999px; padding:2px 10px; margin:2px 3px 2px 0; }
        /* capability trait meters */
        .pol-traits { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:9px 16px; }
        .pol-trait-top { display:flex; justify-content:space-between; align-items:baseline; font-size:.84rem; font-weight:700; gap:6px; }
        .pol-trait-top b { font-size:.95rem; }
        .pol-bar { height:9px; border-radius:999px; background:#eceafe; overflow:hidden; margin:3px 0 2px; }
        .pol-bar-fill { height:100%; border-radius:999px; }
        .pol-trait-why { font-size:.72rem; color:#8a7a55; }
    </style>

    <!-- HEADLINE: politician? -->
    <div class="pol-card pol-lead">
        <h3>🏛️ राजनीति / Political Career <span style="font-size:.72rem;color:#94a3b8">लग्न: <?= $h($po['lagna_hi']) ?></span></h3>
        <div style="font-size:.95rem;font-weight:700;color:<?= $toneCol[$idn['tone']] ?? '#475569' ?>">
            <?= $h($idn['verdict']) ?> <?= $chip($idn['met'] . '/' . $idn['total'] . ' नियम', $idn['tone']) ?>
        </div>
        <div class="pol-why"><?= $h($po['key_planets']['verdict']) ?></div>
    </div>

    <!-- INTEREST: is the person naturally drawn to politics? -->
    <?php $iv = $po['interest'] ?? null; if ($iv !== null):
        $ivBg = ['pos' => '#f0fdf4', 'info' => '#fffbeb', 'neg' => '#fef2f2'][$iv['tone']] ?? '#f8fafc';
        $ivBd = ['pos' => '#16a34a', 'info' => '#d97706', 'neg' => '#dc2626'][$iv['tone']] ?? '#94a3b8'; ?>
    <div class="pol-card" style="background:<?= $ivBg ?>;border-left:5px solid <?= $ivBd ?>">
        <h3 style="color:<?= $ivBd ?>">❤️ रुचि / झुकाव — क्या व्यक्ति को राजनीति पसन्द है?
            <span style="font-size:.72rem;color:#94a3b8">रुचि-अंक <?= (int) $iv['score'] ?>/<?= (int) $iv['max'] ?></span></h3>
        <div style="font-size:.9rem;font-weight:600;color:#374151;line-height:1.5"><?= $h($iv['verdict']) ?></div>
        <div class="pol-factors" style="margin-top:6px">
            <?php foreach ($iv['rows'] as $rw): ?>
                <div class="pol-fac"><?= $rw['met'] ? '✅' : '▫️' ?> <?= $h($rw['label']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- CAREER CROSS-CHECK: does the career profile point to politics? -->
    <?php $cf = $po['career_fit'] ?? null; if ($cf !== null && !empty($cf['known'])):
        $cfBg = ['pos' => '#f0fdf4', 'info' => '#fffbeb', 'neg' => '#fef2f2'][$cf['tone']] ?? '#f8fafc';
        $cfBd = ['pos' => '#16a34a', 'info' => '#d97706', 'neg' => '#dc2626'][$cf['tone']] ?? '#94a3b8'; ?>
    <div class="pol-card" style="background:<?= $cfBg ?>;border-left:5px solid <?= $cfBd ?>">
        <h3 style="color:<?= $cfBd ?>">🧭 करियर-दिशा जाँच — क्या करियर राजनीति सुझाता है?</h3>
        <div style="font-size:.9rem;font-weight:600;color:#374151;line-height:1.5"><?= $h($cf['verdict']) ?></div>
        <?php if ($cf['top_hi'] !== ''): ?>
        <div style="font-size:.8rem;color:#64748b;margin-top:5px">प्रमुख करियर-ग्रह: <b><?= $h($cf['top_hi']) ?></b><?= $cf['fields'] !== '' ? ' · क्षेत्र: ' . $h($cf['fields']) : '' ?></div>
        <?php endif; ?>
        <?php if (empty($cf['supports'])): ?>
        <div style="font-size:.78rem;color:#991b1b;margin-top:5px">📌 पूर्ण करियर-विश्लेषण हेतु <b>करियर — नौकरी · कार्य · व्यवसाय</b> टैब देखें। राजनीति-योग नीचे दिए हैं, पर करियर-दिशा उन्हें प्रबल समर्थन नहीं देती।</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- LEVEL METER: how far -->
    <div class="pol-card">
        <h3>📈 कितनी ऊँचाई तक? — सम्भावित स्तर</h3>
        <div class="pol-meter-wrap">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <div style="font-size:2rem"><?= $bi[1] ?></div>
                <div style="flex:1;min-width:180px">
                    <div class="pol-level-big" style="color:<?= $bi[0] ?>"><?= $h($lv['label']) ?></div>
                    <div style="font-size:.86rem;color:#78350f"><?= $h($lv['titles']) ?></div>
                </div>
                <div style="text-align:right"><div style="font-size:1.5rem;font-weight:900;color:<?= $bi[0] ?>"><?= (int) $lv['score'] ?></div><div style="font-size:.66rem;color:#94a3b8">/ <?= (int) $lv['max'] ?> अंक</div></div>
            </div>
            <div class="pol-track"><div class="pol-fill" style="width:<?= $pct ?>%;background:<?= $bi[0] ?>"></div></div>
            <div class="pol-ladder">
                <?php foreach (['worker' => 'कार्यकर्ता', 'local' => 'स्थानीय', 'district' => 'जिला/नगर', 'state' => 'राज्य', 'national' => 'राष्ट्रीय', 'supreme' => 'सर्वोच्च'] as $bk => $blbl):
                    $on = $bk === $lv['band']; $bc = $bandInfo[$bk][0]; ?>
                    <div class="pol-rung <?= $on ? 'on' : '' ?>" style="<?= $on ? 'background:' . $bc . ';border-color:' . $bc : '' ?>"><?= $h($blbl) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="pol-factors">
            <?php foreach ($lv['factors'] as $fx): ?>
                <div class="pol-fac"><?= $fx['met'] ? '✅' : '▫️' ?> <?= $h($fx['label']) ?> <span style="color:#b45309;font-weight:700">+<?= (int) $fx['pts'] ?></span></div>
            <?php endforeach; ?>
        </div>
        <div class="pol-why" style="margin-top:7px"><?= $h($lv['note']) ?></div>
        <div class="pol-why">🏢 <?= $h($po['field']['text']) ?></div>
    </div>

    <!-- identification rules -->
    <div class="pol-card">
        <h3>🔍 राजनीति-योग जाँच (क्या व्यक्ति राजनीति में जाएगा?)</h3>
        <div class="pol-grid">
            <?php foreach ($idn['rules'] as $i => $r): ?>
                <div class="pol-fac"><?= $r['met'] ? '✅' : '▫️' ?> नियम <?= $i + 1 ?>: <?= $h($r['text']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- key planets Sun/Mars/Saturn/Rahu -->
    <div class="pol-card">
        <h3>🪐 राजनीति के मुख्य ग्रह <?= $chip($po['key_planets']['strong_of_four'] . '/4 बली', $po['key_planets']['tone']) ?></h3>
        <div class="pol-grid">
            <?php foreach ($po['key_planets']['main'] as $p): ?>
                <div class="pol-box"><b><?= $h($p['planet']) ?></b> <?= $p['strong'] ? $chip('बली', 'pos') : $chip('दुर्बल', 'mix') ?><br>
                    <span style="font-size:.8rem"><?= $h($p['role']) ?></span><br>
                    <span class="pol-why"><?= $h($p['house_ord']) ?> भाव<?= $p['dignity'] ? ' · ' . $h($p['dignity']) : '' ?><?= $p['ratio'] !== null ? ' · शड्बल ' . $h($p['ratio']) : '' ?></span></div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:7px">
            <?php foreach ($po['key_planets']['support'] as $p): ?>
                <span class="pol-pill" style="background:<?= $p['strong'] ? '#dcfce7' : '#f1f5f9' ?>;color:<?= $p['strong'] ? '#166534' : '#64748b' ?>"><?= $h($p['planet']) ?> · <?= $h($p['house_ord']) ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- capability profile (speech/authority/leadership/… computed before verdict) -->
    <?php if (!empty($po['capability'])): $cap = $po['capability']; ?>
    <div class="pol-card">
        <h3>🧬 राजनीतिक क्षमता प्रोफ़ाइल <?= $chip('सूचकांक ' . (int) $cap['index'] . '/100 · ' . $cap['index_band'], (int) $cap['index'] >= 60 ? 'pos' : ((int) $cap['index'] >= 45 ? 'mix' : 'neg')) ?></h3>
        <div class="pol-why" style="margin:-2px 0 8px">प्रबल: <b style="color:#166534"><?= $h(implode(' · ', array_map(fn ($t) => $t['name'], $cap['top3']))) ?></b>
            &nbsp;|&nbsp; सुधार-योग्य: <b style="color:#991b1b"><?= $h(implode(' · ', array_map(fn ($t) => $t['name'], $cap['low3']))) ?></b></div>
        <div class="pol-traits">
            <?php foreach ($cap['traits'] as $t): ?>
                <div class="pol-trait">
                    <div class="pol-trait-top"><span><?= $h($t['emoji']) ?> <?= $h($t['name']) ?></span><b style="color:<?= $t['col'] ?>"><?= (int) $t['score'] ?></b></div>
                    <div class="pol-bar"><div class="pol-bar-fill" style="width:<?= (int) $t['score'] ?>%;background:<?= $t['col'] ?>"></div></div>
                    <div class="pol-trait-why"><?= $h($t['top']) ?><?= $t['note'] ? ' · ' . $h($t['note']) : '' ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="pol-why" style="margin-top:7px"><?= $h($cap['note']) ?></div>
    </div>
    <?php endif; ?>

    <!-- pillars 10/6/11 -->
    <?php $pl = $po['pillars']; ?>
    <div class="pol-card">
        <h3>🏛️ राजनीतिक त्रिकोण — 10 · 6 · 11 भाव</h3>
        <div class="pol-grid">
            <?php foreach (['tenth' => '🎖️ 10वाँ (पद)', 'sixth' => '⚔️ 6ठा (चुनाव/शत्रु)', 'eleventh' => '🎯 11वाँ (जन-समर्थन/लाभ)'] as $k => $lbl): $x = $pl[$k]; ?>
                <div class="pol-box"><b><?= $lbl ?></b><br>
                    स्वामी <?= $h($x['lord']) ?> (<?= $h($x['lord_house_ord']) ?>) <?= $x['lord_strong'] ? $chip('बली', 'pos') : $chip('औसत', 'mix') ?><br>
                    अष्टकवर्ग <?= (int) $x['sav'] ?> (<?= $h($x['sav_band']) ?>)<?= $x['bhava_rupa'] !== null ? ' · भाव-बल ' . $h($x['bhava_rupa']) . ' रूप' : '' ?><br>
                    <span class="pol-why">स्थित: <?= $x['occupants'] ? $h(implode(', ', $x['occupants'])) : '—' ?></span></div>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($pl['relations'])): ?>
            <div style="margin-top:7px"><?php foreach ($pl['relations'] as $rl): ?><div class="pol-sig pos"><?= $h($rl) ?></div><?php endforeach; ?></div>
        <?php endif; ?>
        <div class="pol-why" style="margin-top:5px"><?= $h($pl['note']) ?></div>
    </div>

    <!-- success yogas -->
    <?php $yg = $po['yoga']; ?>
    <div class="pol-card">
        <h3>✨ सफलता देने वाले योग</h3>
        <?php if ($yg['raj'] !== []): foreach ($yg['raj'] as $y): ?>
            <div class="pol-yg">👑 <b><?= $h($y['name']) ?></b> — <?= $h($y['via']) ?></div>
        <?php endforeach; endif; ?>
        <?php foreach ($yg['mahapurusha'] as $y): ?>
            <div class="pol-yg">🌟 <b><?= $h($y['name']) ?></b> — <?= $h($y['note']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($yg['gajakesari'])): ?><div class="pol-yg">🌙 <b>गजकेसरी योग</b> — प्रसिद्धि, सम्मान, जन-प्रियता</div><?php endif; ?>
        <?php foreach ($yg['viparita'] as $y): ?>
            <div class="pol-yg">♻️ <b><?= $h($y['name']) ?> (विपरीत राजयोग)</b> — <?= $h($y['via']) ?>; <?= $h($y['fruit']) ?></div>
        <?php endforeach; ?>
        <?php foreach ($yg['neechbhanga'] as $y): ?>
            <div class="pol-yg">⬆️ <b><?= $h($y['name']) ?></b> — <?= $h($y['note']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($yg['chatussagara'])): ?><div class="pol-yg">🌊 <b>चतुःसागर योग</b> — व्यापक प्रसिद्धि, दूर तक प्रभाव (बड़े नेताओं में)</div><?php endif; ?>
        <?php if (!empty($yg['amala'])): ?><div class="pol-yg">🧼 <b>अमला योग</b> — निष्कलंक, साफ-सुथरी छवि</div><?php endif; ?>
        <?php if (!empty($yg['adhi'])): ?><div class="pol-yg">🛡️ <b>अधि योग</b> — नेतृत्व, मंत्री पद, शत्रुओं पर विजय</div><?php endif; ?>
        <?php if ($yg['raj'] === [] && $yg['mahapurusha'] === [] && empty($yg['gajakesari']) && $yg['viparita'] === [] && $yg['neechbhanga'] === []): ?>
            <div class="pol-why">कोई प्रबल राजयोग नहीं मिला — राजनीतिक ऊँचाई सीमित।</div>
        <?php endif; ?>
    </div>

    <!-- failure yogas -->
    <?php if (!empty($yg['failure'])): ?>
    <div class="pol-card">
        <h3>⚠️ असफलता / पतन के योग (सावधानी)</h3>
        <?php foreach ($yg['failure'] as $y): ?>
            <div class="pol-yg warn">❌ <b><?= $h($y['name']) ?></b> — <?= $h($y['note']) ?></div>
        <?php endforeach; ?>
        <div class="pol-why">शास्त्र: अशुभ योग 'निश्चित विनाश' नहीं; यदि शुभ योग बलवान हैं तो ये केवल बाधा देते हैं। तुलना बल के आधार पर करें।</div>
    </div>
    <?php endif; ?>

    <!-- navamsa + D10 -->
    <div class="pol-card">
        <h3>🔎 नवांश (D-9) व दशांश (D-10)</h3>
        <div class="pol-sig"><b>नवांश:</b> <?= $h($po['navamsa']['text'] ?: 'नवांश डेटा उपलब्ध नहीं।') ?></div>
        <?php if (!empty($po['dashamsha'])): ?>
            <div class="pol-sig" style="margin-top:6px"><b>दशांश:</b> <?= $h($po['dashamsha']['text']) ?></div>
            <div class="pol-why">D-10 लग्नेश <?= $h($po['dashamsha']['l1']) ?> <?= !empty($po['dashamsha']['l1_strong']) ? '(बली)' : '' ?> · दशमेश <?= $h($po['dashamsha']['l10']) ?> <?= !empty($po['dashamsha']['l10_strong']) ? '(बली)' : '' ?> · सूर्य केन्द्र में: <?= !empty($po['dashamsha']['sun_kendra']) ? 'हाँ' : 'नहीं' ?></div>
        <?php else: ?><div class="pol-why">D-10 दशांश डेटा उपलब्ध नहीं।</div><?php endif; ?>
    </div>

    <!-- bala -->
    <?php $ba = $po['bala']; ?>
    <div class="pol-card">
        <h3>💪 षड्बल · अष्टकवर्ग · विंशोपक बल</h3>
        <div style="overflow-x:auto">
        <table class="pol-tbl">
            <thead><tr><th>स्वामी</th><th>ग्रह</th><th>षड्बल (रूप)</th><th>न्यूनतम</th><th>विंशोपक</th><th>स्थिति</th></tr></thead>
            <tbody>
            <?php foreach ($ba['lords'] as $l): ?>
                <tr><td><?= $h($l['label']) ?></td><td><?= $h($l['planet']) ?></td><td><?= $h($l['rupa']) ?></td><td><?= $h($l['min']) ?></td><td><?= (int) $l['vim'] ?>/20</td><td><?= $l['ok'] ? $chip('पर्याप्त', 'pos') : $chip('कम', 'mix') ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div style="margin-top:8px">
            <?php foreach ($ba['ashtakavarga'] as $a): ?>
                <span class="pol-pill" style="background:<?= $a['ok'] ? '#dcfce7' : ($a['bindu'] >= 25 ? '#fef9c3' : '#fee2e2') ?>;color:<?= $a['ok'] ? '#166534' : ($a['bindu'] >= 25 ? '#854d0e' : '#991b1b') ?>"><?= $h($a['house']) ?> <?= (int) $a['bindu'] ?> (<?= $h($a['band']) ?>)</span>
            <?php endforeach; ?>
        </div>
        <div class="pol-why" style="margin-top:6px"><?= $h($ba['note']) ?></div>
    </div>

    <!-- style -->
    <div class="pol-card">
        <h3>🎭 राजनीतिक शैली (राशि · तत्व · गुण)</h3>
        <div class="pol-sig"><?= $h($po['style']['text']) ?></div>
        <div class="pol-why">गुण-प्रधानता: <?= $h($po['style']['guna']) ?> — <?= $h($po['style']['guna_note']) ?></div>
    </div>

    <!-- dasha -->
    <?php if (!empty($po['dasha']['has'])): $ds = $po['dasha']; ?>
    <div class="pol-card">
        <h3>⏳ दशा से समय — पद-प्राप्ति के योग</h3>
        <div class="pol-sig"><b>वर्तमान:</b> <?= $h($ds['current_md']) ?> महादशा (<?= $h($ds['md_to']) ?> तक) – <?= $h($ds['current_ad']) ?> अंतर (<?= $h($ds['ad_to']) ?> तक)
            <?= $ds['cur_active'] ? $chip('राजनीतिक भावों से जुड़ी', 'pos') : $chip('सीमित जुड़ाव', 'mix') ?></div>
        <?php if (!empty($ds['cur_touch'])): ?><div class="pol-why">सक्रिय भाव: <?= $h(implode(' · ', $ds['cur_touch'])) ?></div><?php endif; ?>
        <?php if (!empty($ds['windows'])): ?>
            <div style="font-weight:700;font-size:.85rem;color:#166534;margin:8px 0 4px">📅 आगामी पद-प्राप्ति/करियर-खिड़कियाँ</div>
            <?php foreach ($ds['windows'] as $w): ?>
                <div class="pol-win <?= !empty($w['strong']) ? '' : 'one' ?>"><b><?= $h($w['md']) ?>–<?= $h($w['ad']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?> <?= $chip($w['note'], !empty($w['strong']) ? 'pos' : 'mix') ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
        <div class="pol-why" style="margin-top:5px"><?= $h($ds['text']) ?></div>
    </div>
    <?php endif; ?>

    <!-- varshaphal -->
    <?php if (!empty($po['varshaphal']['has'])): ?>
    <div class="pol-card">
        <h3>🗓️ वर्षफल (इस वर्ष)</h3>
        <div class="pol-sig"><?= $h($po['varshaphal']['text']) ?></div>
    </div>
    <?php endif; ?>

    <!-- gochar -->
    <?php if (!empty($po['gochar']['has'])): $gc = $po['gochar']; ?>
    <div class="pol-card">
        <h3>🔭 गोचर (ट्रांजिट) <?= $chip($gc['verdict'], $gc['tone']) ?></h3>
        <div class="pol-sig"><?= $h($gc['text']) ?></div>
        <div class="pol-why">शास्त्र: बड़ी घटना हेतु त्रिविध-पुष्टि — (1) कुण्डली में योग, (2) दशा 10/11/9 से जुड़ी, (3) गोचर गुरु/शनि का 10/11 पर प्रभाव।</div>
    </div>
    <?php endif; ?>

    <!-- promotion -->
    <?php $pr = $po['promotion']; ?>
    <div class="pol-card" style="border-left:5px solid <?= $toneCol[$pr['tone']] ?? '#94a3b8' ?>">
        <h3>🚀 क्या निकट भविष्य में पदोन्नति? <?= $chip($pr['met'] . '/' . $pr['total'], $pr['tone']) ?></h3>
        <div style="font-weight:700;font-size:.92rem;color:<?= $toneCol[$pr['tone']] ?? '#475569' ?>;margin-bottom:6px"><?= $h($pr['verdict']) ?></div>
        <div class="pol-grid">
            <?php foreach ($pr['checks'] as $c): ?>
                <div class="pol-fac"><?= $c['met'] ? '✅' : '▫️' ?> <?= $h($c['label']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- success check -->
    <?php $sc = $po['success']; ?>
    <div class="pol-card">
        <h3>🎯 सफलता की जाँच (क्या सफलता मिलेगी?) <?= $chip($sc['met'] . '/' . $sc['total'], $sc['met'] >= 4 ? 'pos' : ($sc['met'] >= 2 ? 'mix' : 'neg')) ?></h3>
        <div style="font-weight:700;font-size:.88rem;margin-bottom:5px"><?= $h($sc['verdict']) ?></div>
        <?php foreach ($sc['items'] as $it): ?>
            <div class="pol-sig <?= $it['met'] ? 'pos' : 'neg' ?>"><?= $h($it['text']) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- remedies -->
    <?php if (!empty($po['remedies'])): ?>
    <div class="pol-card">
        <h3>🛠 उपाय (पीड़ित राजनीति-ग्रह हेतु)</h3>
        <div class="pol-rem">
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($po['remedies'] as $rm): ?><li style="margin-bottom:5px"><b style="color:#b45309"><?= $h($rm['planet']) ?>:</b> <?= $h($rm['remedy']) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <div class="pol-why" style="margin-top:6px">शास्त्र: उपाय कर्म का विकल्प नहीं, सहायक हैं। ईमानदार जनसेवा से शुभ योग अधिक फलते हैं।</div>
    </div>
    <?php endif; ?>

    <!-- conclusion -->
    <div class="pol-concl">
        <b style="color:#b45309">📌 निष्कर्ष</b> — त्रिविध-पुष्टि <?= (int) $po['conclusion']['trividha'] ?>/3
        <ul style="margin:6px 0 0;padding-left:18px">
            <?php foreach ($po['conclusion']['lines'] as $ln): ?><li style="margin-bottom:3px"><?= $h($ln) ?></li><?php endforeach; ?>
        </ul>
        <div style="font-size:.76rem;color:#94a3b8;margin-top:8px">⚠️ <?= $h($po['conclusion']['caution']) ?></div>
    </div>
<?php endif; ?>
</div>
