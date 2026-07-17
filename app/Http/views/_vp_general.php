<?php
/**
 * सामान्य (General) — the default Varshaphal prediction: a plain-language
 * conclusion pulled from every varshaphal layer (year lord, varsha lagna,
 * muntha, running Patyayini dasha, active bhava-phal). Re-rendered on year
 * change like the other panes. Scope: $view['tajik_bhava'], $view['dasha_phal'],
 * $view['varshesh'], $h, $pcolor.
 */
$tb = $view['tajik_bhava'] ?? null;
$dp = $view['dasha_phal'] ?? null;
$ctx = $tb['context'] ?? null;
$PLHI = ['Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध', 'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि'];
$vpYear = '';
if ($dp !== null && !empty($dp['periods'])) {
    $vpYear = (string) (\AutoBusiness\Astro\Time\JulianDay::toGregorian((float) $dp['periods'][0]['start_jd'], (float) ($dp['tz'] ?? 0.0))[0]);
}
$run = ($dp !== null && !empty($dp['periods'])) ? $dp['periods'][(int) ($dp['running']['dasha'] ?? 0)] : null;
$matched = (int) ($tb['matched_count'] ?? 0);
?>
<div class="gen-wrap">
    <div class="gen-title">वर्षफल सारांश <?= $vpYear !== '' ? '<span class="text-xs text-gray-400 font-normal">(वर्ष ' . $h($vpYear) . ' — मुख्य निष्कर्ष)</span>' : '' ?></div>

    <?php
    // Tone (green/red/blue) per card: strong lagnesh / shubh dasha = green;
    // weak / caution = red; otherwise mixed (blue). Cards are clickable and jump
    // to the matching detailed prediction, exactly like the D1 overview.
    $c1tone = ($ctx !== null && ($ctx['lagnesh_tier'] ?? '') === 'बलवान') ? 'pos' : 'mix';
    $dCat = $run['cat'] ?? '';
    $c3tone = $dCat === 'shubh' ? 'pos' : ($dCat === 'mishrit' ? 'mix' : 'neg');
    $c4tone = $matched > 0 ? 'mix' : 'pos';
    ?>
    <?php if ($ctx !== null): ?>
    <div class="gen-card gen-clickable gen-c-<?= $c1tone ?>" data-genjump="vp:varshesh" role="button" tabindex="0">
        <div class="gen-h">👑 वर्ष का स्वामी व लग्न</div>
        <div class="gen-line"><b>वर्षेश (साल का मुख्य ग्रह):</b> <b style="color:#b45309"><?= $h($ctx['varshesh_hi']) ?></b> — इस वर्ष का फल मुख्यतः इसी ग्रह के स्वभाव अनुसार।</div>
        <div class="gen-line"><b>वर्ष-लग्न:</b> <?= $h($ctx['varsha_lagna_sign']) ?> · <b>लग्नेश:</b> <?= $h($PLHI[$ctx['lagnesh']] ?? $ctx['lagnesh']) ?> (<?= $h($ctx['lagnesh_tier']) ?>) — लग्नेश जितना बलवान, वर्ष उतना अनुकूल।</div>
        <div class="gen-jumphint">वर्षेश फल विस्तार से देखें →</div>
    </div>

    <div class="gen-card gen-clickable gen-c-mix" data-genjump="vp:muntha" role="button" tabindex="0">
        <div class="gen-h">🎯 मुन्था</div>
        <div class="gen-line"><b>मुन्था राशि:</b> <?= $h($ctx['muntha_sign']) ?> · <b>भाव:</b> <?= (int) $ctx['muntha_house'] ?> — इस वर्ष यह भाव (जीवन-क्षेत्र) विशेष रूप से सक्रिय रहेगा।</div>
        <div class="gen-jumphint">मुंथा फल विस्तार से देखें →</div>
    </div>
    <?php endif; ?>

    <?php if ($run !== null): ?>
    <div class="gen-card gen-clickable gen-c-<?= $c3tone ?>" data-genjump="vp:dasha" role="button" tabindex="0">
        <div class="gen-h">🗓️ इस समय चल रही वार्षिक दशा</div>
        <div class="gen-line"><b style="color:<?= $pcolor((string) $run['lord']) ?>"><?= $h($run['lord_hi']) ?></b> की पात्यायिनी दशा (बल: <?= $h($run['tier_hi'] ?? '') ?>) — <?= ($run['cat'] ?? '') === 'shubh' ? '<b style="color:#15803d">शुभ अवधि</b>' : (($run['cat'] ?? '') === 'mishrit' ? '<b style="color:#1d4ed8">मध्यम अवधि</b>' : '<b style="color:#b91c1c">सावधानी की अवधि</b>') ?>।</div>
        <div class="gen-jumphint">दशा-फल विस्तार से देखें →</div>
    </div>
    <?php endif; ?>

    <div class="gen-card gen-clickable gen-c-<?= $c4tone ?>" data-genjump="vp:bhava" role="button" tabindex="0">
        <div class="gen-h">📜 इस वर्ष लागू भाव-फल</div>
        <div class="gen-line"><?= $matched > 0
            ? '<b>' . $matched . ' भाव-नियम</b> इस वर्ष की कुंडली पर लागू हो रहे हैं।'
            : 'कोई विशेष भाव-नियम इस वर्ष संगणित नहीं।' ?></div>
        <div class="gen-jumphint">भाव वर्ष-फल विस्तार से देखें →</div>
        <div class="gen-sub">अन्य विवरण: सहम · ताजिक योग — ऊपर ड्रॉपडाउन से चुनें।</div>
    </div>

    <div class="gen-concl gen-<?= ($ctx !== null && ($ctx['lagnesh_tier'] ?? '') === 'बलवान') ? 'pos' : 'mix' ?>">
        <b>निष्कर्ष:</b> <?= ($ctx !== null && ($ctx['lagnesh_tier'] ?? '') === 'बलवान')
            ? 'वर्ष-लग्नेश बलवान है — कुल मिलाकर वर्ष अनुकूल रहने की सम्भावना; वर्षेश व शुभ ग्रहों की दशा में विशेष प्रगति।'
            : 'वर्ष मिश्रित रहने की सम्भावना — शुभ अवधियों में कार्य आगे बढ़ाएँ, कमजोर अवधियों में सावधानी रखें।' ?>
    </div>
</div>
