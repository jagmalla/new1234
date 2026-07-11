<?php
/**
 * सामान्य (General Overview) — the default Birth-Chart summary. Card order (per
 * client spec): 1) Sade-Sati / Dhaiyya (running or upcoming, with dates) ·
 * 2) Manglik · 3) पूर्वशाप व सन्तान · 4) योग · 5) अभी चल रही दशा ·
 * 6) कुंडली का आधार · निष्कर्ष.
 *
 * Each card is colour-coded शुभ = green / अशुभ = red / मिश्र = blue and (where a
 * detailed view exists) is clickable to jump straight to that prediction.
 * Scope (calc-v2): $view, $chart, $dashaNow, $ovLagna/$ovMoon/$ovSun, $h,
 * $pcolor, $grahaHi, $rashiHi.
 */
$py = $view['phala_yoga'] ?? null;
$sh = $view['shaap'] ?? null;
$yk = $py['yogakaraka'] ?? null;
$sat = $view['sade_sati'] ?? null;
$mng = $view['manglik'] ?? null;
$tzH = (float) ($view['meta']['tz'] ?? 0.0);
$signs = \AutoBusiness\Astro\Calc\Charts::SIGNS;
$dmy = static fn ($jd) => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $jd, $tzH);
$signHi = function (int $idx) use ($signs, $rashiHi): string {
    $nm = $signs[$idx] ?? '';
    return $rashiHi[$nm] ?? $nm;
};

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
$nS = count($actShubh); $nA = count($actAshubh);

// Yoga card tone: only-shubh = green · shubh+ashubh = blue · only-ashubh = red.
$yogaTone = $nA === 0 ? 'pos' : ($nS > 0 ? 'mix' : 'neg');

// Overall conclusion tone.
$tone = $nS > $nA && $shCount === 0 && empty($sat['active']) ? 'pos' : 'mix';
$concl = $tone === 'pos'
    ? 'कुल मिलाकर कुंडली में शुभ योगों की प्रधानता है — मेहनत के साथ अच्छे फल की सम्भावना। बलवान/योगकारक ग्रहों की दशा में विशेष उन्नति।'
    : 'कुंडली में शुभ के साथ कुछ अशुभ योग/दोष भी हैं — सावधानी व उचित उपाय से कठिनाइयाँ कम की जा सकती हैं। शुभ ग्रहों की दशा में अनुकूल समय।';

// Short effect line per Sade-Sati phase / Dhaiyya.
$satPhaseHi = [1 => 'चरण 1 — आरम्भ', 2 => 'चरण 2 — शिखर', 3 => 'चरण 3 — उतरती'];
$satEffect = [
    1 => 'शुरूआती दबाव — खर्च, यात्रा व मानसिक बोझ बढ़ सकता है। धैर्य रखें, शनि-उपाय लाभकारी।',
    2 => 'सर्वाधिक प्रभाव — स्वास्थ्य, कार्य व सम्बन्धों में सतर्कता रखें। हनुमान/शनि उपासना शुभ।',
    3 => 'ढलती साढ़े साती — धीरे-धीरे राहत। पुराने अटके कार्य निपटाएँ।',
];
?>
<div class="gen-wrap">

    <div class="gen-title">सामान्य सारांश <span class="text-xs text-gray-400 font-normal">(जन्म-कुंडली के मुख्य निष्कर्ष — सरल भाषा में)</span></div>

    <?php
    // ===== 1) शनि साढ़े साती / ढैय्या =====
    if ($sat !== null && !empty($sat['found'])):
        $satActive = !empty($sat['active']);
        $satKindHi = $sat['kind'] === 'sadesati' ? 'साढ़े साती' : 'ढैय्या (लघु साढ़े साती)';
        $satTone = $satActive ? 'neg' : 'mix';
        $satHead = $satActive ? '⚠️ शनि ' . $satKindHi . ' — चल रही है' : '🪐 आगामी शनि ' . $satKindHi;
    ?>
    <div class="gen-card gen-c-<?= $satTone ?> gen-clickable" data-genjump="gochar-sadesati" role="button" tabindex="0">
        <div class="gen-h"><?= $h($satHead) ?></div>
        <div class="gen-line"><b>अवधि:</b> <b><?= $h($dmy($sat['start_jd'])) ?></b> से <b><?= $h($dmy($sat['end_jd'])) ?></b> तक · शनि <b style="color:<?= $pcolor('Saturn') ?>"><?= $h($signHi((int) $sat['sign_index'])) ?></b> राशि में (चन्द्र से <?= (int) $sat['house'] ?>वें)।</div>
        <?php if ($sat['kind'] === 'sadesati' && $sat['phase'] !== null): ?>
        <div class="gen-line"><b><?= $h($satPhaseHi[$sat['phase']] ?? '') ?>:</b> <?= $h($satEffect[$sat['phase']] ?? '') ?></div>
        <?php else: ?>
        <div class="gen-line">ढैय्या — मध्यम प्रभाव; संयम व शनि-उपाय (शनि/हनुमान उपासना, तेल-दान) लाभकारी।</div>
        <?php endif; ?>
        <div class="gen-jumphint">विस्तृत साढ़े साती फल देखें → (गोचर)</div>
    </div>
    <?php else: ?>
    <div class="gen-card gen-c-pos">
        <div class="gen-h">🪐 शनि साढ़े साती / ढैय्या</div>
        <div class="gen-line gen-pos">इस समय (व आगामी वर्षों में निकट) साढ़े साती/ढैय्या का विशेष योग नहीं — इस दृष्टि से राहत।</div>
    </div>
    <?php endif; ?>

    <?php
    // ===== 2) मांगलिक (मंगल दोष) =====
    if ($mng !== null):
        if (!empty($mng['manglik'])) { $mTone = 'neg'; $mHead = '🔴 मांगलिक (मंगल दोष)'; }
        elseif (!empty($mng['partial'])) { $mTone = 'mix'; $mHead = '🟦 आंशिक मांगलिक — दोष भंग'; }
        else { $mTone = 'pos'; $mHead = '🟢 मांगलिक नहीं'; }
    ?>
    <div class="gen-card gen-c-<?= $mTone ?>">
        <div class="gen-h"><?= $h($mHead) ?></div>
        <?php if (!empty($mng['raw'])): ?>
        <div class="gen-line">मंगल <?= $h(implode(', ', array_map(static fn ($k, $v) => $k . ' से भाव ' . $v, array_keys($mng['hits']), array_values($mng['hits'])))) ?> में स्थित।</div>
            <?php if (!empty($mng['cancel'])): ?>
            <div class="gen-line gen-pos"><b>दोष-भंग (परिहार):</b> <?= $h(implode(' · ', $mng['cancel'])) ?> — प्रभाव बहुत कम।</div>
            <?php else: ?>
            <div class="gen-line gen-neg">विवाह-मिलान में मंगल दोष की जाँच आवश्यक; मंगल-उपाय (हनुमान उपासना, मंगल-शान्ति) लाभकारी।</div>
            <?php endif; ?>
        <?php else: ?>
        <div class="gen-line gen-pos">मंगल किसी दोष-भाव (1·2·4·7·8·12) में नहीं — विवाह हेतु इस दृष्टि से बाधा नहीं।</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php // ===== 3) पूर्वशाप व सन्तान योग ===== ?>
    <div class="gen-card gen-c-<?= $shCount > 0 ? 'neg' : 'pos' ?> gen-clickable" data-genjump="pred:shaap" role="button" tabindex="0">
        <div class="gen-h">🛕 पूर्वशाप व सन्तान</div>
        <?php if ($shCount > 0): ?>
        <div class="gen-line gen-neg"><b><?= $shCount ?> शाप/सन्तान-योग</b> संगणित (<?= $h(implode(', ', array_slice($sh['detected_categories'] ?? [], 0, 4))) ?>)। परम्परागत उपाय ऊपर "Shrap (पूर्वशाप व सन्तान)" में देखें।</div>
        <?php else: ?>
        <div class="gen-line gen-pos">इस दृष्टि से कोई प्रमुख पूर्वशाप-दोष संगणित नहीं — शुभ संकेत।</div>
        <?php endif; ?>
        <div class="gen-jumphint">विस्तृत शाप-दोष / सन्तान फल देखें →</div>
    </div>

    <?php // ===== 4) योग (कुल सक्रिय) ===== ?>
    <div class="gen-card gen-c-<?= $yogaTone ?> gen-clickable" data-genjump="pred:yoga" role="button" tabindex="0">
        <div class="gen-h">✨ योग (कुल सक्रिय: <?= (int) ($py['detected_count'] ?? 0) ?>)</div>
        <?php if ($actShubh !== []): ?><div class="gen-line gen-pos"><b>शुभ योग:</b> <?= $h(implode(' · ', array_slice($actShubh, 0, 6))) ?><?= count($actShubh) > 6 ? ' आदि' : '' ?>।</div><?php endif; ?>
        <?php if ($actAshubh !== []): ?><div class="gen-line gen-neg"><b>ध्यान देने योग्य (अशुभ) योग:</b> <?= $h(implode(' · ', array_slice($actAshubh, 0, 6))) ?><?= count($actAshubh) > 6 ? ' आदि' : '' ?>।</div><?php endif; ?>
        <?php if ($actMishrit !== []): ?><div class="gen-line" style="color:#1d4ed8"><b>मिश्र योग:</b> <?= $h(implode(' · ', $actMishrit)) ?>।</div><?php endif; ?>
        <?php if ($actShubh === [] && $actAshubh === [] && $actMishrit === []): ?><div class="gen-line text-gray-500">कोई प्रमुख योग संगणित नहीं।</div><?php endif; ?>
        <div class="gen-jumphint">पूरी सूची व फल-दशा देखें →</div>
    </div>

    <?php // ===== 5) अभी चल रही दशा ===== ?>
    <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null): ?>
    <div class="gen-card gen-c-mix gen-clickable" data-genjump="pred:dasha" role="button" tabindex="0">
        <div class="gen-h">🗓️ अभी चल रही दशा</div>
        <div class="gen-line"><b style="color:<?= $pcolor((string) $dashaNow['maha']['lord']) ?>"><?= $h($grahaHi[$dashaNow['maha']['lord']] ?? $dashaNow['maha']['lord']) ?></b> महादशा<?php if (!empty($dashaNow['antar'])): ?> — <b style="color:<?= $pcolor((string) $dashaNow['antar']['lord']) ?>"><?= $h($grahaHi[$dashaNow['antar']['lord']] ?? $dashaNow['antar']['lord']) ?></b> अन्तर्दशा<?php endif; ?>। इसी ग्रह के स्वभाव अनुसार इस समय का फल मिलता है।</div>
        <div class="gen-jumphint">दशा फल विस्तार से देखें →</div>
    </div>
    <?php endif; ?>

    <?php // ===== 6) कुंडली का आधार ===== ?>
    <div class="gen-card">
        <div class="gen-h">🧭 कुंडली का आधार</div>
        <div class="gen-line"><b>लग्न (जन्म राशि):</b> <b style="color:#7c3aed"><?= $h($rashiHi[$ovLagna] ?? $ovLagna) ?></b> — आपका स्वभाव व शरीर इसी से।</div>
        <div class="gen-line"><b>चन्द्र राशि (मन):</b> <b style="color:<?= $pcolor('Moon') ?>"><?= $h($rashiHi[$ovMoon] ?? $ovMoon) ?></b> · <b>सूर्य राशि:</b> <b style="color:<?= $pcolor('Sun') ?>"><?= $h($rashiHi[$ovSun] ?? $ovSun) ?></b></div>
        <?php if ($ykPlanets !== []): ?><div class="gen-line"><b>इस लग्न का योगकारक ग्रह:</b> <b style="color:#15803d"><?= $h(implode(', ', $ykPlanets)) ?></b> — इनकी महादशा/अन्तर्दशा प्रायः सबसे शुभ रहती है।</div><?php endif; ?>
    </div>

    <div class="gen-concl gen-<?= $tone === 'pos' ? 'pos' : 'mix' ?>">
        <b>निष्कर्ष:</b> <?= $h($concl) ?>
        <div class="gen-sub" style="margin-top:4px">विस्तृत फल के लिए ऊपर दिए विकल्प (Dasha · Planet · House · Bhavesh · Karak · Kundali Yog · Shrap) चुनें।</div>
    </div>
</div>
