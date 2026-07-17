<?php
/**
 * मंगल दोष + राजयोग (BPHS 32/36) rendered as filterable yoga CARDS inside the
 * योग catalogue (#py-detail-pane), alongside the Phaladeepika yogas — so the
 * श्रेणी / प्रकार dropdowns filter them like any other yoga. Scope (from
 * _phala_yoga.php): $view, $h, $typeChip, $typeHi, $rashiHi.
 *
 * Each card carries data-cat / data-type (शुभ/अशुभ/मिश्र) / data-detected (1/0)
 * so the existing catalogue filter JS handles it with no special-casing.
 */
$mng = $view['manglik'] ?? null;
$ry = $view['phala_yoga']['rajayoga'] ?? null;
if ($mng === null && $ry === null) {
    return;
}
$catName = 'राजयोग व मंगल दोष';
$signs = \AutoBusiness\Astro\Calc\Charts::SIGNS;
$pct = static fn (float $s): int => (int) round($s * 100);
?>
<div class="py-group" data-cat="<?= $h($catName) ?>">
    <div class="yoga-cat-head"><?= $h($catName) ?> <span class="text-xs text-gray-400 font-normal">(बृ.पा.हो.शा. अध्याय 32/36)</span></div>
    <?php if ($ry !== null && !empty($ry['karakas'])): $kk = $ry['karakas']; ?>
    <div class="text-xs text-gray-500" style="margin:-2px 0 6px">चर-कारक — आत्मकारक <b><?= $h($kk['AK'] ?? '—') ?></b> · अमात्यकारक <b><?= $h($kk['AmK'] ?? '—') ?></b> · पुत्रकारक <b><?= $h($kk['PK'] ?? '—') ?></b></div>
    <?php endif; ?>

    <?php // ---- मंगल दोष (मांगलिक) card ----
    if ($mng !== null):
        // "Active/इस कुंडली में बने" = person is truly Manglik. गैर-मांगलिक — whether
        // no dosha at all OR dosha cancelled (दोष-भंग) — is NOT active (data-detected=0),
        // so it stays out of the default सक्रिय filter (visible only under सभी/category).
        if (!empty($mng['manglik'])) { $mt = 'ashubh'; $mLabel = 'मांगलिक'; $mDet = '1'; }
        elseif (!empty($mng['partial'])) { $mt = 'mishrit'; $mLabel = 'गैर-मांगलिक (दोष-भंग)'; $mDet = '0'; }
        else { $mt = 'shubh'; $mLabel = 'गैर-मांगलिक'; $mDet = '0'; }
        $marsSignHi = $rashiHi[$signs[(int) ($mng['mars_sign_index'] ?? 0)] ?? ''] ?? '';
        $mCancel = [];
        if (!empty($mng['cancel']['own_or_exalt'])) { $mCancel[] = 'मंगल स्वराशि/उच्च'; }
        if (!empty($mng['cancel']['jupiter_or_lagna'])) { $mCancel[] = 'गुरु-दृष्टि/लग्न में गुरु-शुक्र'; }
    ?>
    <div class="yoga-card py-card py-<?= $mt ?><?= $mt === 'ashubh' ? ' yoga-bad' : '' ?><?= $mDet === '1' ? ' py-on' : '' ?>"
         data-cat="<?= $h($catName) ?>" data-type="<?= $mt ?>" data-detected="<?= $mDet ?>">
        <div class="yoga-title" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span>मंगल दोष (मांगलिक) — Manglik</span>
            <span class="gc-chip <?= $typeChip($mt) ?>"><?= $h($mLabel) ?></span>
            <span class="text-xs text-gray-400 font-normal">कुण्डली-मिलान सम</span>
        </div>
        <div class="yoga-why"><b>नियम:</b> मंगल जन्म-कुंडली में लग्न, चन्द्र (या शुक्र) से 1, 2, 4, 7, 8 या 12वें भाव में हो तो कुंडली मांगलिक कहलाती है।</div>
        <div class="yoga-res"><b>फल:</b> मंगल <b><?= $h($marsSignHi) ?></b> राशि में।
            <?php if (!empty($mng['raw'])): ?>दोष-स्थिति — <?= $h(implode(', ', $mng['hits'] ?? [])) ?>।
                <?php if ($mCancel !== []): ?> <b>दोष-भंग:</b> <?= $h(implode(' · ', $mCancel)) ?> — प्रभाव बहुत कम।
                <?php else: ?> विवाह/दाम्पत्य में विचारणीय; मंगल-उपाय (हनुमान उपासना, मंगल-शान्ति) लाभकारी।<?php endif; ?>
            <?php else: ?>किसी दोष-भाव (1·2·4·7·8·12) में नहीं — विवाह हेतु इस दृष्टि से बाधा नहीं।<?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php // ---- राजयोग cards (active + भंग) ----
    if ($ry !== null):
        foreach (array_merge($ry['active'] ?? [], $ry['bhanga'] ?? []) as $y):
            $isBhanga = ($y['bhanga'] ?? []) !== [];
            $rt = $isBhanga ? 'mishrit' : 'shubh';
    ?>
    <div class="yoga-card py-card py-<?= $rt ?> py-on" data-cat="<?= $h($catName) ?>" data-type="<?= $rt ?>" data-detected="1">
        <div class="yoga-title" style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
            <span><?= $h($y['name']) ?></span>
            <span class="gc-chip <?= $typeChip($rt) ?>"><?= $isBhanga ? 'भंग/दुर्बल' : 'राजयोग (शुभ)' ?></span>
            <span class="gc-chip <?= $typeChip($rt) ?>">✓ इस कुंडली में</span>
            <span class="text-xs text-gray-400 font-normal">BPHS 36.<?= (int) $y['shloka'] ?> · बल <?= $pct((float) $y['strength']) ?>%<?= (!empty($y['cat']) && $y['cat'] !== 'RY') ? ' · ' . $h($y['cat']) : '' ?></span>
        </div>
        <div class="yoga-why"><b>नियम:</b> <?= $h($y['matched']) ?><?= !empty($y['sambandha']) ? ' — <i style="font-style:normal;color:#1d4ed8">' . $h($y['sambandha']) . '</i>' : '' ?></div>
        <div class="yoga-res"><b>फल:</b> <?= $h($y['result']) ?></div>
        <?php foreach (($y['bhanga'] ?? []) as $bn): ?><div class="yoga-why" style="color:#b45309;margin-top:2px"><?= $h($bn) ?></div><?php endforeach; ?>
        <?php if ($isBhanga): ?><div class="text-xs text-gray-400" style="margin-top:2px">उपस्थित किन्तु भंग/दुर्बल — फल पूर्ण न मानें।</div><?php endif; ?>
    </div>
    <?php endforeach; endif; ?>

    <?php if ($ry !== null && !empty($ry['caveat'])): ?>
    <div class="text-xs text-gray-400" style="margin-top:4px;line-height:1.5">⚠ <?= $h($ry['caveat']) ?></div>
    <?php endif; ?>
</div>
