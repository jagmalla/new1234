<?php
declare(strict_types=1);
/**
 * विदेश यात्रा व स्थायी निवास — computed report card (topic under खोज-परिणाम).
 * Consumes $view['videsh'] = VideshEngine::compute() output.
 *
 * @var array $view
 */
$vd = $view['videsh'] ?? null;
$h = static fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$toneCol = ['pos' => '#166534', 'neg' => '#991b1b', 'info' => '#854d0e'];
$toneBg  = ['pos' => '#dcfce7', 'neg' => '#fee2e2', 'info' => '#fef9c3'];
$chip = static function (string $txt, string $tone) use ($h, $toneCol, $toneBg): string {
    return '<span style="display:inline-block;font-weight:700;border-radius:999px;padding:1px 11px;font-size:.74rem;background:'
        . ($toneBg[$tone] ?? '#f1f5f9') . ';color:' . ($toneCol[$tone] ?? '#475569') . '">' . $h($txt) . '</span>';
};
?>
<div id="videsh-report" class="hidden">
<?php if ($vd === null || empty($vd['ok'])): ?>
    <div class="bg-white rounded-lg shadow p-4 text-sm text-gray-600">
        विदेश-विश्लेषण के लिए जन्म-कुंडली आवश्यक है। कृपया जन्म-विवरण भरकर गणना करें।
    </div>
<?php else: ?>
    <style>
        .vd-card { background:#fff; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; margin-bottom:12px; }
        .vd-card h3 { font-size:1rem; margin:0 0 6px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .vd-lead { border-left:5px solid #b45309; }
        .vd-sig { font-size:.86rem; line-height:1.6; margin:3px 0; padding-left:16px; position:relative; }
        .vd-sig::before { content:'•'; position:absolute; left:2px; }
        .vd-sig.pos::before { content:'✓'; color:#166534; }
        .vd-sig.neg::before { content:'✕'; color:#991b1b; }
        .vd-tbl { width:100%; border-collapse:collapse; font-size:.84rem; }
        .vd-tbl th, .vd-tbl td { border-bottom:1px solid var(--line,#e4dcce); padding:5px 7px; text-align:left; vertical-align:top; }
        .vd-tbl th { color:#6b6156; font-weight:700; }
        .vd-win { border:1px solid var(--line,#e4dcce); border-left:4px solid #2E6E4E; border-radius:8px; padding:7px 11px; margin-bottom:7px; font-size:.85rem; }
        .vd-win.one { border-left-color:#854d0e; }
        .vd-rem { border-left:4px solid #b45309; background:#fff8ef; border-radius:0 8px 8px 0; padding:9px 12px; margin-top:6px; }
        .vd-rem li { margin-bottom:6px; font-size:.85rem; }
        .vd-why { color:#6b6156; font-size:.8rem; margin-top:4px; }
        .vd-concl { background:#f8fafc; border:1px solid var(--line,#e4dcce); border-radius:10px; padding:12px 14px; font-size:.9rem; line-height:1.7; }
    </style>

    <div class="vd-card vd-lead">
        <h3>✈ विदेश यात्रा व स्थायी निवास
            <?= $chip('विदेश-योग: ' . $vd['promise']['level'], $vd['promise']['tone']) ?>
            <span style="font-size:.72rem;color:#94a3b8">लग्न: <?= $h($vd['lagna_hi']) ?></span>
        </h3>
        <div style="font-size:.9rem"><?= $h($vd['promise']['text']) ?></div>
    </div>

    <!-- वादा (evidence) -->
    <div class="vd-card">
        <h3>🧭 वादा — जन्म-कुंडली के संकेत</h3>
        <?php foreach ($vd['signals'] as $s): ?>
            <div class="vd-sig <?= $h($s['tone']) ?>"><?= $h($s['text']) ?></div>
        <?php endforeach; ?>
        <?php if (empty($vd['signals'])): ?>
            <div style="font-size:.85rem;color:#6b6156">इस कुंडली में विदेश-निवास के विशेष योग नहीं मिले।</div>
        <?php endif; ?>
    </div>

    <!-- कारण -->
    <div class="vd-card">
        <h3>🎯 विदेश जाने का कारण</h3>
        <?php foreach ($vd['reasons'] as $r): ?>
            <div style="margin-bottom:6px"><b style="color:#b45309"><?= $h($r['reason']) ?></b>
                <div class="vd-why"><?= $h($r['why']) ?></div></div>
        <?php endforeach; ?>
    </div>

    <!-- settle vs return -->
    <div class="vd-card">
        <h3>🏠 स्थायी निवास या घर वापसी? <?= $chip($vd['settle']['verdict'], $vd['settle']['tone']) ?></h3>
        <div style="font-size:.88rem"><?= $h($vd['settle']['text']) ?></div>
        <?php if (!empty($vd['settle']['why'])): ?>
            <?php foreach ($vd['settle']['why'] as $w): ?>
                <div class="vd-sig"><?= $h($w) ?></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- कार्येश table -->
    <div class="vd-card">
        <h3>📋 PR के कार्येश (कार्य-कारक ग्रह)</h3>
        <div style="overflow-x:auto">
        <table class="vd-tbl">
            <thead><tr><th>ग्रह</th><th>भूमिका</th><th>स्थिति</th><th>दशा-योग्यता</th></tr></thead>
            <tbody>
            <?php foreach ($vd['karyesh'] as $k): ?>
                <tr>
                    <td><b><?= $h($k['hi']) ?></b></td>
                    <td style="font-size:.8rem"><?= $h(implode(', ', $k['roles'])) ?></td>
                    <td><?= $h($k['house_ord']) ?> भाव · <?= $h($k['dignity']) ?></td>
                    <td><?= $k['weak'] ? $chip('पीड़ित', 'neg') : $chip('सक्षम', 'pos') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- PR timing -->
    <div class="vd-card">
        <h3>🗓️ PR / Green Card का समय (दशा-आधारित खिड़कियाँ)</h3>
        <?php if (!empty($vd['timing']['windows'])): ?>
            <?php foreach ($vd['timing']['windows'] as $w): ?>
                <div class="vd-win <?= $w['both'] ? '' : 'one' ?>">
                    <b><?= $h($w['label']) ?></b> &nbsp; <?= $h($w['from']) ?> → <?= $h($w['to']) ?>
                    <?= $chip($w['both'] ? 'प्रबल' : 'सामान्य', $w['both'] ? 'pos' : 'info') ?>
                    <div class="vd-why"><?= $h($w['why']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php elseif (empty($vd['timing']['has_dasha'])): ?>
            <div style="font-size:.85rem;color:#6b6156">दशा-गणना उपलब्ध नहीं — समय-खिड़कियाँ नहीं निकाली जा सकीं।</div>
        <?php else: ?>
            <div style="font-size:.85rem;color:#6b6156">अगले ~15 वर्षों में कार्येश-आधारित कोई प्रबल दशा-खिड़की नहीं; प्रतीक्षा-अवधि।</div>
        <?php endif; ?>
        <div class="vd-why" style="margin-top:6px"><?= $h($vd['timing']['note']) ?></div>
    </div>

    <!-- obstruction + remedy -->
    <div class="vd-card">
        <h3>🚧 बाधा व उपाय</h3>
        <div style="font-size:.88rem;margin-bottom:<?= $vd['obstruction']['present'] ? '8px' : '0' ?>"><?= $h($vd['obstruction']['summary']) ?></div>
        <?php foreach ($vd['obstruction']['problems'] as $p): ?>
            <div class="vd-sig neg"><b><?= $h($p['hi']) ?></b> (<?= $h(implode(', ', $p['roles'])) ?>) — <?= $h($p['reason']) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($vd['obstruction']['remedies'])): ?>
            <div class="vd-rem">
                <div style="font-weight:700;color:#b45309;margin-bottom:6px">🛠 उपाय</div>
                <ul style="margin:0;padding-left:18px">
                    <?php foreach ($vd['obstruction']['remedies'] as $rm): ?>
                        <li><b><?= $h($rm['hi']) ?>:</b> <?= $h($rm['text']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <!-- varga confirmation -->
    <?php if (!empty($vd['varga'])): ?>
    <div class="vd-card">
        <h3>🔎 वर्ग-कुंडली से पुष्टि</h3>
        <?php foreach ($vd['varga'] as $v): ?>
            <div class="vd-sig <?= $h($v['tone']) ?>"><b><?= $h($v['chart']) ?>:</b> <?= $h($v['text']) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- conclusion -->
    <div class="vd-concl">
        <b style="color:#b45309">📌 निष्कर्ष:</b> <?= $h($vd['conclusion']) ?>
        <div style="font-size:.74rem;color:#94a3b8;margin-top:8px">नोट: ज्योतिष संभावनाओं का शास्त्र है। PR/Green Card वास्तविक इमिग्रेशन-नीति, कोटा व व्यक्तिगत प्रयास पर भी निर्भर है — यह विश्लेषण दिशा-सूचक है।</div>
    </div>
<?php endif; ?>
</div>
