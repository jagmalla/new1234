<?php
/**
 * सामान्य (General) — the default Birth-Chart prediction: a plain-language
 * conclusion that pulls the important points from every prediction layer
 * (lagna basis, running dasha, yogakaraka, active yogas, shaap-doshas).
 * Scope (calc-v2): $view, $chart, $dashaNow, $ovLagna/$ovMoon/$ovSun,
 * $h, $pcolor, $grahaHi, $rashiHi.
 */
$py = $view['phala_yoga'] ?? null;
$sh = $view['shaap'] ?? null;
$yk = $py['yogakaraka'] ?? null;

// Collect active yoga names by type.
$actShubh = []; $actAshubh = []; $actMishrit = [];
foreach (($py['groups'] ?? []) as $ys) {
    foreach ($ys as $y) {
        if (($y['detected'] ?? null) !== true) { continue; }
        $nm = (string) $y['hi'];
        if ($y['type'] === 'shubh') { $actShubh[] = $nm; }
        elseif ($y['type'] === 'ashubh') { $actAshubh[] = $nm; }
        else { $actMishrit[] = $nm; }
    }
}
$ykPlanets = [];
foreach (($yk['roles'] ?? []) as $pl => $r) { if (($r['role'] ?? '') === 'yogakaraka') { $ykPlanets[] = $r['planet_hi']; } }
$shCount = (int) ($sh['detected_count'] ?? 0);

// Overall tone: benefic yogas vs malefic yogas + shaap presence.
$nS = count($actShubh); $nA = count($actAshubh);
$tone = $nS > $nA && $shCount === 0 ? 'pos' : ($nA > $nS || $shCount > 0 ? 'mix' : 'pos');
$concl = $tone === 'pos'
    ? 'कुल मिलाकर कुंडली में शुभ योगों की प्रधानता है — मेहनत के साथ अच्छे फल की सम्भावना। बलवान/योगकारक ग्रहों की दशा में विशेष उन्नति।'
    : 'कुंडली में शुभ के साथ कुछ अशुभ योग/दोष भी हैं — सावधानी व उचित उपाय से कठिनाइयाँ कम की जा सकती हैं। शुभ ग्रहों की दशा में अनुकूल समय।';
?>
<div class="gen-wrap">

    <div class="gen-title">सामान्य सारांश <span class="text-xs text-gray-400 font-normal">(जन्म-कुंडली के मुख्य निष्कर्ष — सरल भाषा में)</span></div>

    <div class="gen-card">
        <div class="gen-h">🧭 कुंडली का आधार</div>
        <div class="gen-line"><b>लग्न (जन्म राशि):</b> <b style="color:#7c3aed"><?= $h($rashiHi[$ovLagna] ?? $ovLagna) ?></b> — आपका स्वभाव व शरीर इसी से।</div>
        <div class="gen-line"><b>चन्द्र राशि (मन):</b> <b style="color:<?= $pcolor('Moon') ?>"><?= $h($rashiHi[$ovMoon] ?? $ovMoon) ?></b> · <b>सूर्य राशि:</b> <b style="color:<?= $pcolor('Sun') ?>"><?= $h($rashiHi[$ovSun] ?? $ovSun) ?></b></div>
        <?php if ($ykPlanets !== []): ?><div class="gen-line"><b>इस लग्न का योगकारक ग्रह:</b> <b style="color:#15803d"><?= $h(implode(', ', $ykPlanets)) ?></b> — इनकी महादशा/अन्तर्दशा प्रायः सबसे शुभ रहती है।</div><?php endif; ?>
    </div>

    <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null): ?>
    <div class="gen-card">
        <div class="gen-h">🗓️ अभी चल रही दशा</div>
        <div class="gen-line"><b style="color:<?= $pcolor((string) $dashaNow['maha']['lord']) ?>"><?= $h($grahaHi[$dashaNow['maha']['lord']] ?? $dashaNow['maha']['lord']) ?></b> महादशा<?php if (!empty($dashaNow['antar'])): ?> — <b style="color:<?= $pcolor((string) $dashaNow['antar']['lord']) ?>"><?= $h($grahaHi[$dashaNow['antar']['lord']] ?? $dashaNow['antar']['lord']) ?></b> अन्तर्दशा<?php endif; ?>। इसी ग्रह के स्वभाव अनुसार इस समय का फल मिलता है (विस्तार हेतु "दशा फल" चुनें)।</div>
    </div>
    <?php endif; ?>

    <div class="gen-card">
        <div class="gen-h">✨ योग (कुल सक्रिय: <?= (int) ($py['detected_count'] ?? 0) ?>)</div>
        <?php if ($actShubh !== []): ?><div class="gen-line gen-pos"><b>शुभ योग:</b> <?= $h(implode(' · ', array_slice($actShubh, 0, 6))) ?><?= count($actShubh) > 6 ? ' आदि' : '' ?>।</div><?php endif; ?>
        <?php if ($actAshubh !== []): ?><div class="gen-line gen-neg"><b>ध्यान देने योग्य (अशुभ) योग:</b> <?= $h(implode(' · ', array_slice($actAshubh, 0, 6))) ?><?= count($actAshubh) > 6 ? ' आदि' : '' ?>।</div><?php endif; ?>
        <?php if ($actMishrit !== []): ?><div class="gen-line"><b>मिश्र योग:</b> <?= $h(implode(' · ', $actMishrit)) ?>।</div><?php endif; ?>
        <?php if ($actShubh === [] && $actAshubh === [] && $actMishrit === []): ?><div class="gen-line text-gray-500">कोई प्रमुख योग संगणित नहीं।</div><?php endif; ?>
        <div class="gen-sub">पूरी सूची व फल-दशा के लिए ऊपर "योग" चुनें।</div>
    </div>

    <div class="gen-card">
        <div class="gen-h">🛕 शाप-दोष / सन्तान</div>
        <?php if ($shCount > 0): ?>
        <div class="gen-line gen-neg"><b><?= $shCount ?> शाप/सन्तान-योग</b> संगणित (<?= $h(implode(', ', array_slice($sh['detected_categories'] ?? [], 0, 4))) ?>)। परम्परागत उपाय हेतु ऊपर "शाप-दोष / सन्तान योग" चुनें।</div>
        <?php else: ?>
        <div class="gen-line gen-pos">इस दृष्टि से कोई प्रमुख शाप-दोष संगणित नहीं — शुभ संकेत।</div>
        <?php endif; ?>
    </div>

    <div class="gen-concl gen-<?= $tone === 'pos' ? 'pos' : 'mix' ?>">
        <b>निष्कर्ष:</b> <?= $h($concl) ?>
        <div class="gen-sub" style="margin-top:4px">विस्तृत फल के लिए ऊपर दिए विकल्प (दशा फल · भावेश · ग्रह · भाव · कारक · योग · शाप) चुनें।</div>
    </div>
</div>
