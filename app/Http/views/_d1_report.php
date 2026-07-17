<?php
/**
 * 🖨 D1 जन्म-कुंडली रिपोर्ट — hidden print document (opened by the "D1 रिपोर्ट"
 * button through the shared ABPrintDoc window; styled by the lkr-* print CSS).
 * Composes the computed layers: birth details, current dasha chain, per-planet
 * फल-बल + functional nature + फल-काल, detected doshas with remedies, detected
 * yogas, manglik and Sade-Sati status.
 * Scope (calc-v2): $view, $chart, $dashaNow, $in, $meta, $h.
 */
$tzR = (float) ($meta['tz'] ?? 5.5);
$dmyR = static fn (float $jd): string => \AutoBusiness\Astro\Time\JulianDay::toDmy($jd, $tzR);
$ghR = \AutoBusiness\Astro\Phala\DashaPhalaRepository::LORDS_HI;
$stR = $view['strength']['planets'] ?? [];
$doshaF = array_values(array_filter((array) ($view['doshas'] ?? []), static fn ($d) => !empty($d['detected'])));
$yogasR = (array) ($view['yogas'] ?? []);
$mgR = $view['manglik'] ?? null;
$ssR = $view['sade_sati'] ?? null;
$birthR = trim((string) ($in['name'] ?? ''));
$birthLine = ($birthR !== '' ? $birthR . ' · ' : '') . (string) ($in['date'] ?? '') . ' ' . (string) ($in['time'] ?? '')
    . (trim((string) ($in['place'] ?? '')) !== '' ? ' · ' . (string) $in['place'] : '');
?>
<div id="d1-report" hidden>
  <div class="lkr-title">जन्म-कुंडली रिपोर्ट (Birth Chart Report)</div>
  <div class="lkr-sub"><?= $h($birthLine) ?><?php if ($chart !== null): ?> · लग्न: <?= $h((string) $chart['ascendant']['sign']) ?> · चन्द्र राशि: <?= $h((string) ($chart['planets']['Moon']['sign'] ?? '')) ?><?php endif; ?></div>

  <?php if ($dashaNow !== null && ($dashaNow['maha'] ?? null) !== null): ?>
  <div class="lkr-box lkr-hot"><b>⏳ चालू दशा:</b>
    <?= $h($ghR[$dashaNow['maha']['lord']] ?? $dashaNow['maha']['lord']) ?> महादशा (<?= $h($dmyR((float) $dashaNow['maha']['end_jd'])) ?> तक)
    <?php if (($dashaNow['antar'] ?? null) !== null): ?> → <?= $h($ghR[$dashaNow['antar']['lord']] ?? $dashaNow['antar']['lord']) ?> अंतर्दशा (<?= $h($dmyR((float) $dashaNow['antar']['end_jd'])) ?> तक)<?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="lkr-h">🪐 ग्रह फल-बल एवं फल-काल</div>
  <?php foreach ($stR as $plR => $smR): ?>
    <div class="lkr-box<?= $smR['tier'] === 'neg' ? ' lkr-bad' : '' ?>">
      <b><?= $h($ghR[$plR] ?? $plR) ?></b> — <?= $h((string) $smR['word']) ?> (स्कोर <?= (int) $smR['score'] ?>)
      <?php $fnR = $smR['functional'] ?? null; if ($fnR !== null): ?> · इस लग्न में <?= $h((string) $fnR['word']) ?><?= !empty($fnR['maraka']) ? ' + मारक' : '' ?><?php endif; ?>
      <?php if (!empty($smR['reasons'])): ?><div class="lkr-why"><?= $h(implode(' · ', $smR['reasons'])) ?></div><?php endif; ?>
      <?php $tmR = $smR['timing'] ?? null; if ($tmR !== null && $tmR['maha'] !== null): ?>
        <div class="lkr-why">फल-काल: महादशा <?= $h($tmR['maha']['from']) ?> – <?= $h($tmR['maha']['to']) ?><?= $tmR['maha']['status'] === 'running' ? ' (चालू)' : '' ?><?php foreach ($tmR['antar'] as $adR): ?> · अंतर्दशा <?= $h($adR['from']) ?> – <?= $h($adR['to']) ?><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php if ($doshaF !== []): ?>
  <div class="lkr-h">⚠ दोष एवं परिहार</div>
  <?php foreach ($doshaF as $dR): ?>
    <div class="lkr-box lkr-bad">
      <b><?= $h((string) $dR['name']) ?></b> — <?= $h((string) $dR['why']) ?><br><?= $h((string) $dR['desc']) ?>
      <ul class="lkr-ul"><?php foreach ($dR['remedies'] as $rr): ?><li><?= $h((string) $rr) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($yogasR !== []): ?>
  <div class="lkr-h">✨ प्रमुख योग</div>
  <?php foreach ($yogasR as $yR): ?>
    <div class="lkr-box"><b><?= $h((string) ($yR['name'] ?? '')) ?></b> — <?= $h((string) ($yR['why'] ?? '')) ?><br><?= $h((string) ($yR['result'] ?? '')) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($mgR !== null): ?>
  <div class="lkr-h">🔴 मंगलीक</div>
  <div class="lkr-box<?= !empty($mgR['manglik']) ? ' lkr-bad' : '' ?>">
    <?= !empty($mgR['manglik']) ? 'मंगलीक दोष है' : (!empty($mgR['partial']) ? 'दोष-भंग (दोष बनकर निरस्त)' : 'मंगलीक दोष नहीं') ?>
    — मंगल <?= (int) ($mgR['mars_house_lagna'] ?? 0) ?>वें भाव में
  </div>
  <?php endif; ?>

  <?php if (is_array($ssR) && !empty($ssR['found'])): ?>
  <div class="lkr-h">🪐 साढ़े साती / ढैय्या</div>
  <div class="lkr-box<?= !empty($ssR['active']) ? ' lkr-bad' : '' ?>">
    <?= $h($ssR['kind'] === 'sadesati' ? 'साढ़े साती' : 'ढैय्या') ?> — <?= !empty($ssR['active']) ? 'चल रही है' : 'आगामी' ?>
    (<?= $h($dmyR((float) $ssR['start_jd'])) ?> – <?= $h($dmyR((float) $ssR['end_jd'])) ?>)<?= !empty($ssR['phase']) ? ' · चरण ' . (int) $ssR['phase'] : '' ?>
  </div>
  <?php endif; ?>

  <div class="lkr-foot">यह रिपोर्ट शास्त्रीय गणनाओं (षड्बल · अष्टकवर्ग · नवांश · विंशोत्तरी) पर आधारित है।</div>
</div>
