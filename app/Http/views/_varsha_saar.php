<?php
/**
 * वर्ष का सार — one-look Varshaphal summary card shown at the top of the
 * Varshaphal section (and re-rendered by the varshaphal JSON endpoint when the
 * year changes). Composes what the engines already computed: the Varshesha
 * (with its Panchavargeeya strength), Muntha standing, the running Mudda
 * dasha, the strongest Tajik yoga tags and the active sahams — and closes with
 * a simple transparent verdict line.
 *
 * Scope (both render paths): $vp, $chart, $view, $h, $pcolor, $grahaHi, $rashiHi.
 */
$pvS = $vp['varshesh'] ?? null;                       // lord + bala20 + offices
$mnS = $view['muntha'] ?? null;                       // MunthaPhalEngine payload
$tjS = $view['tajik'] ?? null;                        // Tajik yoga records
$shS = $view['saham'] ?? null;                        // 50 sahams (related flags)
$tzS = (float) ($view['meta']['tz'] ?? $view['varshesh']['tz'] ?? 5.5);

if ($vp !== null):
    // Varshesha strength band from the Panchavargeeya total (out of 20).
    $ylLord = (string) ($pvS['lord'] ?? '');
    $ylBala = (float) ($pvS['bala20'] ?? 0.0);
    [$ylTier, $ylWord] = $ylBala >= 12.0 ? ['pos', 'बलवान'] : ($ylBala >= 8.0 ? ['info', 'मध्यम बल'] : ['neg', 'निर्बल']);

    // Running Mudda dasha (now).
    $nowJdS = \AutoBusiness\Astro\Time\JulianDay::fromGregorian(
        (int) date('Y'), (int) date('n'), (int) date('j'), (int) date('G'), (int) date('i'), 0.0, $tzS
    );
    $muddaNow = null;
    foreach (($vp['mudda_dasha'] ?? []) as $mdS) {
        if ($nowJdS >= (float) $mdS['start_jd'] && $nowJdS < (float) $mdS['end_jd']) { $muddaNow = $mdS; break; }
    }

    // Tajik tags: overall pos/neg count + up to four named tags.
    $tagPos = $tagNeg = 0;
    $tagShow = [];
    foreach (($tjS['yogas'] ?? []) as $rec) {
        foreach (($rec['tags'] ?? []) as $tg) {
            $tn = (string) ($tg['tone'] ?? 'info');
            if ($tn === 'pos') { $tagPos++; } elseif ($tn === 'neg') { $tagNeg++; }
            $nm = (string) ($tg['name_hi'] ?? '');
            if ($nm !== '' && count($tagShow) < 4 && !isset($tagShow[$nm])) { $tagShow[$nm] = $tn; }
        }
    }

    // Active sahams (related to the running Mudda lord).
    $sahamAct = [];
    foreach (($shS['sahams'] ?? []) as $sS) {
        if (!empty($sS['related']) && count($sahamAct) < 4) { $sahamAct[] = (string) ($sS['name_hi'] ?? ''); }
    }

    // Transparent verdict: Muntha tone + Varshesha strength + Tajik balance.
    $vScore = 0;
    $vWhy = [];
    $mTone = (string) ($mnS['verdict_tone'] ?? 'info');
    if ($mTone === 'pos') { $vScore++; $vWhy[] = 'मुंथा शुभ'; }
    elseif ($mTone === 'neg') { $vScore--; $vWhy[] = 'मुंथा अशुभ'; }
    if ($ylTier === 'pos') { $vScore++; $vWhy[] = 'वर्षेश बलवान'; }
    elseif ($ylTier === 'neg') { $vScore--; $vWhy[] = 'वर्षेश निर्बल'; }
    if ($tagPos > $tagNeg) { $vScore++; $vWhy[] = 'शुभ ताजिक योग अधिक (' . $tagPos . '/' . $tagNeg . ')'; }
    elseif ($tagNeg > $tagPos) { $vScore--; $vWhy[] = 'अशुभ ताजिक योग अधिक (' . $tagNeg . '/' . $tagPos . ')'; }
    [$vCls, $vWord] = $vScore >= 2 ? ['#166534;background:#f0fdf4;border-color:#bbf7d0', 'शुभ-प्रधान वर्ष']
        : ($vScore <= -1 ? ['#991b1b;background:#fef2f2;border-color:#fecaca', 'कष्ट-प्रधान वर्ष — उपाय आवश्यक']
        : ['#92400e;background:#fffbeb;border-color:#fde68a', 'मिश्रित फल का वर्ष']);
    $chipCls = static fn (string $t): string => $t === 'pos' ? 'gc-shubh' : ($t === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
?>
<div class="bg-white rounded-lg shadow p-4 text-sm">
    <h2 class="font-semibold mb-2 text-gray-700">वर्ष का सार — Year at a Glance
        <span class="text-xs text-gray-400 font-normal">(<?= (int) ($forYear ?? $view['in']['forYear'] ?? 0) ?>)</span></h2>

    <div style="border:1px solid;border-radius:8px;padding:7px 12px;margin-bottom:10px;font-weight:700;color:<?= $vCls ?>">
        <?= $h($vWord) ?>
        <?php if ($vWhy !== []): ?><span style="font-weight:400;font-size:.78rem"> — <?= $h(implode(' · ', $vWhy)) ?></span><?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:9px">
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 11px">
            <div class="text-xs text-gray-400 font-semibold">वर्षेश (Year Lord)</div>
            <?php if ($ylLord !== ''): ?>
                <div><b style="color:<?= $pcolor($ylLord) ?>"><?= $h($grahaHi[$ylLord] ?? $ylLord) ?></b>
                    <span class="gc-chip <?= $chipCls($ylTier) ?>"><?= $h($ylWord) ?></span></div>
                <div class="text-xs text-gray-500">पंचवर्गीय बल <?= number_format($ylBala, 2) ?> / 20</div>
            <?php else: ?><div class="text-gray-400 italic">—</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 11px">
            <div class="text-xs text-gray-400 font-semibold">मुंथा (Muntha)</div>
            <?php if ($mnS !== null && !empty($mnS['muntha_house'])): ?>
                <div><b><?= $h((string) ($mnS['muntha_sign_hi'] ?? '')) ?></b> — <?= (int) $mnS['muntha_house'] ?>वाँ भाव
                    <span class="gc-chip <?= $chipCls($mTone) ?>"><?= $h((string) ($mnS['verdict'] ?? '')) ?></span></div>
                <div class="text-xs text-gray-500"><?= $h((string) ($mnS['band'] ?? '')) ?> · मुंथेश <?= $h((string) ($mnS['munthesh_hi'] ?? '')) ?></div>
            <?php else: ?><div class="text-gray-400 italic">—</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 11px">
            <div class="text-xs text-gray-400 font-semibold">चालू मुद्दा दशा</div>
            <?php if ($muddaNow !== null): $ml = (string) $muddaNow['lord']; ?>
                <div><b style="color:<?= $pcolor($ml) ?>"><?= $h($grahaHi[$ml] ?? $ml) ?></b></div>
                <div class="text-xs text-gray-500"><?= $h(\AutoBusiness\Astro\Time\JulianDay::toDmy((float) $muddaNow['start_jd'], $tzS)) ?> – <?= $h(\AutoBusiness\Astro\Time\JulianDay::toDmy((float) $muddaNow['end_jd'], $tzS)) ?></div>
            <?php else: ?><div class="text-gray-400 italic">इस समय यह वर्ष सक्रिय नहीं</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 11px">
            <div class="text-xs text-gray-400 font-semibold">प्रमुख ताजिक योग</div>
            <?php if ($tagShow !== []): ?>
                <div style="display:flex;flex-wrap:wrap;gap:3px;margin-top:2px">
                    <?php foreach ($tagShow as $nm => $tn): ?><span class="gc-chip <?= $chipCls($tn) ?>"><?= $h($nm) ?></span><?php endforeach; ?>
                </div>
                <div class="text-xs text-gray-500" style="margin-top:3px">शुभ <?= $tagPos ?> · अशुभ <?= $tagNeg ?></div>
            <?php else: ?><div class="text-gray-400 italic">कोई विशेष योग नहीं</div><?php endif; ?>
        </div>
        <div style="border:1px solid #e5e7eb;border-radius:8px;padding:8px 11px">
            <div class="text-xs text-gray-400 font-semibold">सक्रिय सहम</div>
            <?php if ($sahamAct !== []): ?>
                <div class="text-xs" style="line-height:1.7"><?= $h(implode(' · ', $sahamAct)) ?></div>
            <?php else: ?><div class="text-gray-400 italic">चालू दशा से सम्बन्धित सहम नहीं</div><?php endif; ?>
        </div>
    </div>

    <?php
        // ---- महीना-दर-महीना पट्टी: the Mudda dasha laid out proportionally over
        // the year, coloured by each lord's standing in the varsha chart
        // (6/8/12 भाव = red; natural benefic = green; other malefic = amber).
        $muddaAll = $vp['mudda_dasha'] ?? [];
        $vpl = $vp['varsha_chart']['planets'] ?? [];
        $spanS = $muddaAll !== [] ? (float) $muddaAll[0]['start_jd'] : 0.0;
        $spanE = $muddaAll !== [] ? (float) end($muddaAll)['end_jd'] : 1.0;
        $spanT = max(1.0, $spanE - $spanS);
    ?>
    <?php if ($muddaAll !== []): ?>
    <div style="margin-top:11px">
        <div class="text-xs text-gray-400 font-semibold" style="margin-bottom:4px">मुद्दा-दशा वर्ष-पट्टी
            <span class="font-normal">(हरा = शुभ काल · पीला = मिश्रित · लाल = सावधानी)</span></div>
        <div style="display:flex;width:100%;border-radius:7px;overflow:hidden;border:1px solid #e2e8f0">
            <?php foreach ($muddaAll as $mSeg):
                $mlS = (string) $mSeg['lord'];
                $wPct = max(2.0, ((float) $mSeg['end_jd'] - (float) $mSeg['start_jd']) / $spanT * 100.0);
                $mHouse = (int) ($vpl[$mlS]['house'] ?? 0);
                $mBen = in_array($mlS, ['Jupiter', 'Venus', 'Mercury', 'Moon'], true);
                if (in_array($mHouse, [6, 8, 12], true)) { $bgc = '#fecaca'; $fgc = '#7f1d1d'; }
                elseif ($mBen) { $bgc = '#bbf7d0'; $fgc = '#14532d'; }
                else { $bgc = '#fde68a'; $fgc = '#713f12'; }
                $isNow = $muddaNow !== null && $mSeg['lord'] === $muddaNow['lord']
                    && (float) $mSeg['start_jd'] === (float) $muddaNow['start_jd'];
                $fromS = \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $mSeg['start_jd'], $tzS);
                $toS = \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $mSeg['end_jd'], $tzS);
            ?>
            <div title="<?= $h(($grahaHi[$mlS] ?? $mlS) . ': ' . $fromS . ' – ' . $toS . ($mHouse ? ' · वर्ष-कुंडली ' . $mHouse . 'वें भाव में' : '')) ?>"
                 style="width:<?= number_format($wPct, 2) ?>%;background:<?= $bgc ?>;color:<?= $fgc ?>;text-align:center;font-size:.68rem;font-weight:700;padding:4px 0;<?= $isNow ? 'outline:2px solid #1d4ed8;outline-offset:-2px;' : '' ?>">
                <?= $h(mb_substr($grahaHi[$mlS] ?? $mlS, 0, 2)) ?><?= $isNow ? ' ●' : '' ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-xs text-gray-400" style="display:flex;justify-content:space-between;margin-top:2px">
            <span><?= $h(\AutoBusiness\Astro\Time\JulianDay::toDmy($spanS, $tzS)) ?></span>
            <span>● = चालू दशा (कर्सर रखने पर तिथियाँ)</span>
            <span><?= $h(\AutoBusiness\Astro\Time\JulianDay::toDmy($spanE, $tzS)) ?></span>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
