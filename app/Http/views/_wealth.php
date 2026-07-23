<?php
declare(strict_types=1);
/**
 * धन-योग — computed report (topic under खोज-परिणाम, D1 prediction).
 * Consumes $view['wealth'] = WealthEngine::compute() output.
 * @var array $view
 */
$wl = $view['wealth'] ?? null;
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$toneCol = ['pos' => '#166534', 'neg' => '#991b1b', 'info' => '#854d0e'];
$toneBg  = ['pos' => '#dcfce7', 'neg' => '#fee2e2', 'info' => '#fef9c3'];
$chip = static function (string $txt, string $tone) use ($h, $toneCol, $toneBg): string {
    return '<span style="display:inline-block;font-weight:700;border-radius:999px;padding:1px 11px;font-size:.74rem;background:'
        . ($toneBg[$tone] ?? '#f1f5f9') . ';color:' . ($toneCol[$tone] ?? '#475569') . '">' . $h($txt) . '</span>';
};
?>
<div id="wealth-report" class="hidden">
<?php if ($wl === null || empty($wl['ok'])): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        धन-विश्लेषण के लिए जन्म-कुंडली आवश्यक है। कृपया जन्म-विवरण भरकर गणना करें।
    </div>
<?php else: ?>
    <style>
        .wl-card { background:#fff; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
        .wl-card h3 { font-size:1rem; margin:0 0 6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .wl-lead { border-left:5px solid #b45309; }
        .wl-sig { font-size:.86rem; line-height:1.6; margin:3px 0; padding-left:16px; position:relative; }
        .wl-sig::before { content:'•'; position:absolute; left:2px; }
        .wl-sig.pos::before { content:'✓'; color:#166534; }
        .wl-sig.neg::before { content:'✕'; color:#991b1b; }
        .wl-tbl { width:100%; border-collapse:collapse; font-size:.83rem; }
        .wl-tbl th, .wl-tbl td { border-bottom:1px solid var(--line,#e4dcce); padding:5px 7px; text-align:left; vertical-align:top; }
        .wl-tbl th { color:#6b6156; font-weight:700; }
        .wl-meter { height:14px; border-radius:8px; background:linear-gradient(90deg,#ef4444,#f59e0b,#22c55e); position:relative; margin:8px 0 4px; }
        .wl-meter .pin { position:absolute; top:-5px; width:4px; height:24px; background:#1e293b; border-radius:2px; }
        .wl-scalerow { display:flex; justify-content:space-between; font-size:.66rem; color:#94a3b8; }
        .wl-legend { font-size:.76rem; color:#6b6156; line-height:1.7; }
        .wl-win { border:1px solid var(--line,#e4dcce); border-left:4px solid #2E6E4E; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .wl-win.loss { border-left-color:#ef4444; background:#fff7f7; }
        .wl-win.mix { border-left-color:#f59e0b; }
        .wl-rem { border-left:4px solid #b45309; background:#fff8ef; border-radius:0 8px 8px 0; padding:9px 12px; margin-top:6px; }
        .wl-rem li { margin-bottom:5px; font-size:.85rem; }
        .wl-why { color:#6b6156; font-size:.8rem; margin-top:4px; }
        .wl-concl { background:#f8fafc; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; font-size:.9rem; line-height:1.7; }
    </style>

    <!-- 0-10 scale headline -->
    <?php $sc = $wl['scale']; ?>
    <div class="wl-card wl-lead">
        <h3>💰 धन-योग — आय · बचत · संपत्ति · भाग्य <span style="font-size:.72rem;color:#94a3b8">लग्न: <?= $h($wl['lagna_hi']) ?></span></h3>
        <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
            <div style="text-align:center;padding:6px 16px;border-radius:12px;background:<?= $toneBg[$sc['tone']] ?? '#f1f5f9' ?>;color:<?= $toneCol[$sc['tone']] ?? '#475569' ?>">
                <div style="font-size:1.6rem;font-weight:800;line-height:1"><?= $h($sc['score']) ?><span style="font-size:.9rem">/10</span></div>
                <div style="font-size:.68rem">धन-पैमाना</div>
            </div>
            <div style="flex:1;min-width:220px">
                <div style="font-size:.9rem;font-weight:700;color:<?= $toneCol[$sc['tone']] ?? '#475569' ?>"><?= $h($sc['band']) ?></div>
                <div class="wl-meter"><div class="pin" style="left:calc(<?= max(0, min(100, $sc['score'] * 10)) ?>% - 2px)"></div></div>
                <div class="wl-scalerow"><span>0 संघर्ष</span><span>5 औसत</span><span>10 शीर्ष धनाढ्य</span></div>
            </div>
        </div>
        <div class="wl-why" style="margin-top:6px"><?= $h($sc['text']) ?></div>
    </div>

    <!-- scale legend -->
    <details class="wl-card" style="padding:10px 14px">
        <summary style="cursor:pointer;font-weight:700;font-size:.9rem">📏 धन-पैमाना (0–10) की व्याख्या</summary>
        <div class="wl-legend" style="margin-top:8px">
            <?php foreach ($sc['legend'] as $lg): ?>
                <div style="<?= (int) $lg['n'] === (int) round($sc['score']) ? 'font-weight:700;color:#b45309' : '' ?>"><b><?= (int) $lg['n'] ?></b> — <?= $h($lg['text']) ?></div>
            <?php endforeach; ?>
        </div>
    </details>

    <!-- SCALE #2 — age/dasha "today" scale + 0-100 wealth curve -->
    <?php $aw = $wl['age_wealth'] ?? null; if ($aw !== null && !empty($aw['has_dasha'])): ?>
    <div class="wl-card" style="border-left:5px solid #7c3aed">
        <h3>📈 आयु-अनुसार धन (दूसरा पैमाना) <?= $chip('आज (' . (int) $aw['current_age'] . ' वर्ष): ' . $aw['today_score'] . '/10', $aw['today_tone']) ?></h3>
        <div style="display:flex;gap:16px;flex-wrap:wrap;font-size:.85rem;margin-bottom:6px">
            <div>🎯 <b>पहला पैमाना</b> (जीवन-भर की क्षमता): <b><?= $h($wl['scale']['score']) ?>/10</b></div>
            <div>📍 <b>दूसरा पैमाना</b> (आज, दशा-अनुसार): <b style="color:<?= $toneCol[$aw['today_tone']] ?? '#475569' ?>"><?= $h($aw['today_score']) ?>/10</b> — <?= $h($aw['today_band']) ?></div>
        </div>
        <div class="wl-sig"><b>अवस्था:</b> <?= $h($aw['phase']) ?> · काम-आरंभ ~<?= (int) $aw['career_start'] ?> वर्ष · धन-शिखर ~<?= (int) $aw['peak_age'] ?> वर्ष (~<?= $h($aw['peak_score']) ?>/10)</div>
        <div class="wl-sig"><b>अध्ययन:</b> <?= $h($aw['study']['profile']) ?></div>
        <div class="wl-sig"><b>माता-पिता का सहयोग:</b> <?= $h($aw['parents']['text']) ?></div>

        <?php
        // ---- inline SVG wealth-vs-age curve (0..100 yrs, 0..10 wealth) ----
        $padL = 30; $padT = 10; $plotW = 600; $plotH = 170; $vw = $padL + $plotW + 12; $vh = $padT + $plotH + 22;
        $xOf = static fn (int $age): float => $padL + ($age / 100.0) * $plotW;
        $yOf = static fn (float $w): float => $padT + (1.0 - $w / 10.0) * $plotH;
        $pts = [];
        foreach ($aw['curve'] as $c) {
            $pts[] = round($xOf((int) $c['age']), 1) . ',' . round($yOf((float) $c['w']), 1);
        }
        $cs = (int) $aw['career_start']; $ca = (int) $aw['current_age']; $pa = (int) $aw['peak_age'];
        ?>
        <div style="overflow-x:auto;margin-top:8px">
        <svg viewBox="0 0 <?= $vw ?> <?= $vh ?>" style="width:100%;min-width:340px;max-width:660px;font-family:inherit" role="img" aria-label="आयु-अनुसार धन-वक्र">
            <!-- Y gridlines 0..10 -->
            <?php for ($g = 0; $g <= 10; $g += 2): $gy = $yOf($g); ?>
                <line x1="<?= $padL ?>" y1="<?= round($gy, 1) ?>" x2="<?= $padL + $plotW ?>" y2="<?= round($gy, 1) ?>" stroke="#eee5d6" stroke-width="1"/>
                <text x="<?= $padL - 4 ?>" y="<?= round($gy + 3, 1) ?>" text-anchor="end" font-size="9" fill="#94a3b8"><?= $g ?></text>
            <?php endfor; ?>
            <!-- X ticks 0..100 -->
            <?php for ($x = 0; $x <= 100; $x += 20): ?>
                <text x="<?= round($xOf($x), 1) ?>" y="<?= $padT + $plotH + 14 ?>" text-anchor="middle" font-size="9" fill="#94a3b8"><?= $x ?></text>
            <?php endfor; ?>
            <text x="<?= $padL + $plotW / 2 ?>" y="<?= $vh - 1 ?>" text-anchor="middle" font-size="9" fill="#6b6156">आयु (वर्ष) →</text>
            <!-- childhood shade -->
            <rect x="<?= $padL ?>" y="<?= $padT ?>" width="<?= round($xOf($cs) - $padL, 1) ?>" height="<?= $plotH ?>" fill="#f5f3ff" opacity="0.7"/>
            <text x="<?= round(($padL + $xOf($cs)) / 2, 1) ?>" y="<?= $padT + 12 ?>" text-anchor="middle" font-size="8" fill="#a78bfa">बचपन/पढ़ाई</text>
            <!-- wealth curve -->
            <polyline points="<?= implode(' ', $pts) ?>" fill="none" stroke="#b45309" stroke-width="2.2"/>
            <!-- career-start marker (dashed) -->
            <line x1="<?= round($xOf($cs), 1) ?>" y1="<?= $padT ?>" x2="<?= round($xOf($cs), 1) ?>" y2="<?= $padT + $plotH ?>" stroke="#7c3aed" stroke-width="1" stroke-dasharray="3 3"/>
            <text x="<?= round($xOf($cs), 1) ?>" y="<?= $padT + $plotH - 3 ?>" text-anchor="middle" font-size="8" fill="#7c3aed">काम ~<?= $cs ?></text>
            <!-- peak marker -->
            <circle cx="<?= round($xOf($pa), 1) ?>" cy="<?= round($yOf((float) $aw['peak_score']), 1) ?>" r="3" fill="#166534"/>
            <text x="<?= round($xOf($pa), 1) ?>" y="<?= round($yOf((float) $aw['peak_score']) - 5, 1) ?>" text-anchor="middle" font-size="8" fill="#166534">शिखर ~<?= $pa ?></text>
            <!-- current age marker (solid red) -->
            <line x1="<?= round($xOf($ca), 1) ?>" y1="<?= $padT ?>" x2="<?= round($xOf($ca), 1) ?>" y2="<?= $padT + $plotH ?>" stroke="#dc2626" stroke-width="1.5"/>
            <circle cx="<?= round($xOf($ca), 1) ?>" cy="<?= round($yOf((float) $aw['today_score']), 1) ?>" r="3.5" fill="#dc2626"/>
            <text x="<?= round($xOf($ca), 1) ?>" y="<?= $padT + 9 ?>" text-anchor="middle" font-size="8" fill="#dc2626" font-weight="700">आज <?= $ca ?></text>
        </svg>
        </div>
        <div class="wl-why">वक्र दशा-क्रम पर आधारित है: बचपन/पढ़ाई में धन कम (कमाई नहीं), काम-आरंभ पर वृद्धि, प्रबल दशा में शिखर, कमज़ोर दशा में ठहराव/गिरावट (बचत से संभला)। यह सापेक्ष रुझान है, रुपये की मात्रा नहीं।</div>

        <?php if (!empty($aw['periods'])): ?>
        <details style="margin-top:8px"><summary style="cursor:pointer;font-weight:700;font-size:.85rem">📋 महादशा-वार धन-रुझान (आयु-सहित)</summary>
            <div style="overflow-x:auto;margin-top:6px">
            <table class="wl-tbl">
                <thead><tr><th>महादशा</th><th>आयु</th><th>धन-रुझान</th></tr></thead>
                <tbody>
                <?php foreach ($aw['periods'] as $p): ?>
                    <tr><td><?= $h($p['lord']) ?></td><td><?= (int) $p['from_age'] ?>–<?= (int) $p['to_age'] ?> वर्ष</td><td><?= $chip($p['kind'], $p['tone']) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </details>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- wealth sources -->
    <div class="wl-card">
        <h3>🧭 धन के स्रोत (कहाँ से कितना)</h3>
        <div style="overflow-x:auto">
        <table class="wl-tbl">
            <thead><tr><th>स्रोत</th><th>मुख्य भाव</th><th>कारक</th><th>बल</th></tr></thead>
            <tbody>
            <?php foreach ($wl['sources'] as $s): ?>
                <tr>
                    <td><?= $h($s['name']) ?></td>
                    <td><?= $h($s['main_ord']) ?></td>
                    <td style="font-size:.78rem"><?= $h($s['kar']) ?></td>
                    <td><?= $chip($s['level'], $s['tone']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- savings = income - expenses -->
    <?php $sv = $wl['savings']; ?>
    <div class="wl-card" style="border-left:5px solid <?= $sv['tone'] === 'pos' ? '#22c55e' : ($sv['tone'] === 'neg' ? '#ef4444' : '#f59e0b') ?>">
        <h3>🏦 बचत = आय − खर्च <?= $chip($sv['verdict'], $sv['tone']) ?></h3>
        <div style="font-size:.88rem"><?= $h($sv['text']) ?></div>
    </div>

    <!-- houses + bhavesh -->
    <div class="wl-card">
        <h3>🏛 धन-भाव व भावेश</h3>
        <div class="wl-sig"><?= $h($wl['houses']['arth_trikon']) ?></div>
        <?php foreach (['dhanesh', 'labhesh', 'dashamesh', 'chaturthesh'] as $k): $x = $wl['houses'][$k]; ?>
            <div class="wl-sig <?= $x['afflicted'] ? 'neg' : ($x['strong'] ? 'pos' : '') ?>"><b><?= $h($x['role']) ?>:</b> <?= $h($x['planet']) ?> <?= $h($x['house_ord']) ?> भाव — <?= $h($x['effect']) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- drishti -->
    <div class="wl-card">
        <h3>👁 दृष्टि (धन-भावों 2/11 पर)</h3>
        <?php foreach ($wl['drishti'] as $d): ?>
            <div class="wl-sig <?= $h($d['tone']) ?>"><?= $h($d['text']) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- yogas -->
    <?php if (!empty($wl['yoga'])): ?>
    <div class="wl-card">
        <h3>✨ धन/राज/विपरीत/दरिद्र-योग</h3>
        <?php foreach ($wl['yoga'] as $y): ?>
            <div class="wl-sig <?= $h($y['tone']) ?>"><b><?= $h($y['name']) ?>:</b> <?= $h($y['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- strength -->
    <div class="wl-card">
        <h3>💪 बल — शड्बल · भाव-बल · अष्टकवर्ग · नवांश</h3>
        <div style="overflow-x:auto;margin-bottom:6px">
        <table class="wl-tbl">
            <thead><tr><th>ग्रह</th><th>भूमिका</th><th>शड्बल</th><th>विम्शो</th><th>वर्गोत्तम</th></tr></thead>
            <tbody>
            <?php foreach ($wl['strength']['rows'] as $r): ?>
                <tr><td><?= $h($r['hi']) ?></td><td style="font-size:.78rem"><?= $h($r['role']) ?></td>
                    <td><?= $h($r['ratio']) ?> <?= $r['strong'] ? '✓' : '' ?></td><td><?= (int) $r['vim'] ?>/20</td><td><?= $r['vargottama'] ? '✓' : '—' ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <div class="wl-sig"><?= $h($wl['strength']['bhava_text']) ?></div>
        <div class="wl-why"><?= $h($wl['strength']['sav_text']) ?></div>
    </div>

    <!-- vargas -->
    <?php if (!empty($wl['varga'])): ?>
    <div class="wl-card">
        <h3>🔎 वर्ग-कुंडली (D2 बचत · D4 संपत्ति · D9 पुष्टि · D10 आजीविका)</h3>
        <?php foreach ($wl['varga'] as $v): ?>
            <div class="wl-sig <?= $h($v['tone']) ?>"><b><?= $h($v['chart']) ?>:</b> <?= $h($v['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- dasha -->
    <?php if (!empty($wl['dasha']['has_dasha'])): ?>
    <div class="wl-card">
        <h3>⏳ महादशा — वर्तमान व आगामी</h3>
        <div class="wl-sig"><b>वर्तमान:</b> <?= $h($wl['dasha']['current']) ?> — <?= $h($wl['dasha']['current_flavour']) ?></div>
        <?php if (!empty($wl['dasha']['next_md'])): ?>
            <div class="wl-sig"><b>आगामी:</b> <?= $h($wl['dasha']['next_md']) ?> महादशा (<?= $h($wl['dasha']['next_from']) ?> से) — <?= $h($wl['dasha']['next_flavour']) ?></div>
        <?php endif; ?>
        <div class="wl-why"><?= $h($wl['dasha']['text']) ?></div>
    </div>
    <?php endif; ?>

    <!-- timing: past + future profit/loss -->
    <div class="wl-card">
        <h3>🗓️ लाभ-हानि — बीता व आने वाला समय</h3>
        <?php if (!empty($wl['timing']['past'])): ?>
            <div style="font-weight:700;font-size:.85rem;color:#6b6156;margin-bottom:4px">बीते ~3 वर्ष</div>
            <?php foreach ($wl['timing']['past'] as $w): ?>
                <div class="wl-win <?= $w['kind'] === 'हानि/खर्च' ? 'loss' : ($w['kind'] === 'मिश्र' ? 'mix' : '') ?>">
                    <b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?> <?= $chip($w['kind'], $w['kind'] === 'लाभ' ? 'pos' : ($w['kind'] === 'हानि/खर्च' ? 'neg' : 'info')) ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($wl['timing']['future'])): ?>
            <div style="font-weight:700;font-size:.85rem;color:#166534;margin:8px 0 4px">आने वाले ~4 वर्ष</div>
            <?php foreach ($wl['timing']['future'] as $w): ?>
                <div class="wl-win <?= $w['kind'] === 'हानि/खर्च' ? 'loss' : ($w['kind'] === 'मिश्र' ? 'mix' : '') ?>">
                    <b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?> <?= $chip($w['kind'], $w['kind'] === 'लाभ' ? 'pos' : ($w['kind'] === 'हानि/खर्च' ? 'neg' : 'info')) ?>
                    <?php if (!empty($w['gochar'])): ?><div class="wl-sig <?= $h($w['gochar']['tone']) ?>" style="margin-top:3px"><b>गोचर:</b> <?= $h($w['gochar']['text']) ?></div><?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <?php if (empty($wl['timing']['past']) && empty($wl['timing']['future'])): ?>
            <div style="font-size:.85rem;color:#6b6156">दशा-गणना उपलब्ध नहीं।</div>
        <?php endif; ?>
        <div class="wl-why" style="margin-top:6px"><?= $h($wl['timing']['note']) ?></div>
    </div>

    <!-- remedies -->
    <?php if (!empty($wl['remedies']['rows'])): ?>
    <div class="wl-card">
        <h3>🛠 धन-उपाय</h3>
        <div class="wl-rem">
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($wl['remedies']['rows'] as $rm): ?><li><b><?= $h($rm['hi']) ?>:</b> <?= $h($rm['text']) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <div class="wl-why" style="margin-top:6px"><?= $h($wl['remedies']['note']) ?></div>
    </div>
    <?php endif; ?>

    <!-- conclusion -->
    <div class="wl-concl">
        <b style="color:#b45309">📌 निष्कर्ष:</b> <?= $h($wl['conclusion']) ?>
        <div style="font-size:.74rem;color:#94a3b8;margin-top:8px">यह विश्लेषण सापेक्ष स्थिति बताता है (रुपये में मात्रा नहीं)। बड़े वित्तीय निर्णय हेतु वित्तीय सलाहकार व अपनी समझ का उपयोग अवश्य करें।</div>
    </div>
<?php endif; ?>
</div>
