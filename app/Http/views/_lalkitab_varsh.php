<?php
/**
 * लाल किताब वर्ष कुंडली (Lal Kitab annual chart) prediction fragment.
 *
 * Rendered STANDALONE by CalcController::lalkitabVarshJson() and injected into
 * #lkv-body by the ABLalVarsh JS on every age/year change. Self-contained: only
 * needs $v = LalKitabEngine::varshReading() result. The annual (fixed-Aries)
 * chart itself is drawn client-side into #lkv-chart from the JSON `north`
 * payload — this fragment carries all the year's predictions, remedies and
 * करें / न करें.
 *
 * @var array<string,mixed> $v
 */
$e = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
/** tone → card colour band */
$toneCss = static function (string $t): string {
    return $t === 'pos' ? 'background:#f0fdf4;border-color:#86efac'
        : ($t === 'neg' ? 'background:#fef2f2;border-color:#fca5a5'
        : ($t === 'info' ? 'background:#eff6ff;border-color:#93c5fd'
        : 'background:#fffbeb;border-color:#fcd34d'));
};
$vpill = static function (string $verdict) use ($e): string {
    $map = [
        'शुभ'  => 'background:#dcfce7;color:#166534',
        'अशुभ' => 'background:#fee2e2;color:#991b1b',
        'मध्यम' => 'background:#fef9c3;color:#854d0e',
    ];
    $st = $map[$verdict] ?? 'background:#f1f5f9;color:#475569';
    $ic = $verdict === 'शुभ' ? '✅ ' : ($verdict === 'अशुभ' ? '⚠️ ' : '◐ ');
    return '<span style="display:inline-block;padding:1px 8px;border-radius:999px;font-size:.72rem;font-weight:700;' . $st . '">' . $ic . $e($verdict) . '</span>';
};
$saar = $v['saar'] ?? [];
?>
<div class="lkv-frag" style="margin-top:12px">

  <!-- ===== इस वर्ष का सार (year summary) ===== -->
  <div class="lk-card" style="border-width:1.5px;<?= $toneCss((string) ($saar['tone'] ?? 'mix')) ?>">
    <div class="lk-card-h" style="font-size:.95rem">🗓️ इस वर्ष का सार (आयु <?= (int) ($v['age'] ?? 0) ?> वर्ष)</div>
    <div class="lk-txt" style="line-height:1.7"><?= $e($saar['headline'] ?? '') ?></div>
    <?php if (!empty($saar['shubh']) || !empty($saar['ashubh'])): ?>
      <div style="margin-top:7px;display:flex;flex-wrap:wrap;gap:5px;align-items:center">
        <?php if (!empty($saar['shubh'])): ?>
          <span style="font-weight:600;color:#166534">✅ शुभ:</span>
          <?php foreach ($saar['shubh'] as $sp): ?><span style="background:#dcfce7;color:#166534;border-radius:999px;padding:1px 8px;font-size:.72rem;font-weight:600"><?= $e($sp) ?></span><?php endforeach; ?>
        <?php endif; ?>
        <?php if (!empty($saar['ashubh'])): ?>
          <span style="font-weight:600;color:#991b1b;margin-left:6px">⚠️ अशुभ:</span>
          <?php foreach ($saar['ashubh'] as $sp): ?><span style="background:#fee2e2;color:#991b1b;border-radius:999px;padding:1px 8px;font-size:.72rem;font-weight:600"><?= $e($sp) ?></span><?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="lk-dd" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px;margin-top:9px">
      <?php if (!empty($saar['good'])): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:7px 9px">
          <div style="font-weight:700;color:#166534;font-size:.8rem">🌿 अनुकूल क्षेत्र (इस वर्ष)</div>
          <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.6">
            <?php foreach ($saar['good'] as $g): ?><li><b><?= $e($g['ord']) ?> भाव</b> — <?= $e($g['topic']) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (!empty($saar['caution'])): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:7px 9px">
          <div style="font-weight:700;color:#991b1b;font-size:.8rem">🛡️ सावधानी क्षेत्र (इस वर्ष)</div>
          <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.6">
            <?php foreach ($saar['caution'] as $g): ?><li><b><?= $e($g['ord']) ?> भाव</b> — <?= $e($g['topic']) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ===== कब क्या होगा — इस वर्ष के ग्रह-चक्र संकेत ===== -->
  <div class="lk-card">
    <div class="lk-card-h">🔮 कब क्या होगा — आयु <?= (int) ($v['age'] ?? 0) ?> के संकेत</div>
    <?php if (empty($saar['events'])): ?>
      <div class="lk-txt" style="color:#64748b">इस आयु-वर्ष पर ग्रह-चक्र/सुप्त-ग्रह का कोई विशेष प्रभाव-वर्ष अंकित नहीं — फल मुख्यतः ऊपर की वर्ष-कुंडली स्थिति से पढ़ें।</div>
    <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:6px">
        <?php foreach ($saar['events'] as $ev):
            $bg = ($ev['tone'] ?? '') === 'pos' ? '#f0fdf4;border-color:#86efac'
                : (($ev['tone'] ?? '') === 'neg' ? '#fef2f2;border-color:#fca5a5' : '#eff6ff;border-color:#93c5fd'); ?>
          <div style="background:<?= $bg ?>;border:1px solid;border-radius:8px;padding:6px 10px;font-size:.83rem;line-height:1.55"><?= $e($ev['text']) ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if (!empty($saar['timeline'])): ?>
      <div style="margin-top:10px">
        <div style="font-weight:700;font-size:.82rem;color:#334155;margin-bottom:4px">📅 आगामी वर्ष-रेखा (आयु <?= (int) ($v['age'] ?? 0) ?> →)</div>
        <div style="display:flex;flex-wrap:wrap;gap:6px">
          <?php foreach ($saar['timeline'] as $tl): ?>
            <div style="border:1px solid #e2e8f0;border-radius:8px;padding:5px 8px;min-width:96px">
              <div style="font-weight:700;font-size:.78rem;color:#0f172a">आयु <?= (int) $tl['age'] ?></div>
              <?php foreach ($tl['bits'] as $b):
                  $c = ($b['tone'] === 'pos') ? '#166534' : (($b['tone'] === 'neg') ? '#991b1b' : '#1e40af'); ?>
                <div style="font-size:.72rem;color:<?= $c ?>;line-height:1.5"><?= $e($b['t']) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== इस वर्ष: करें / न करें + उपाय ===== -->
  <div class="lk-card">
    <div class="lk-card-h">✅⛔ इस वर्ष का आचरण व उपाय</div>
    <div class="lk-dd" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:8px">
      <?php if (!empty($saar['dos'])): ?>
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:7px 9px">
          <div style="font-weight:700;color:#166534;font-size:.8rem">✅ करें</div>
          <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.6">
            <?php foreach ($saar['dos'] as $d): ?><li><?= $e($d) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if (!empty($saar['donts'])): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:7px 9px">
          <div style="font-weight:700;color:#991b1b;font-size:.8rem">⛔ न करें</div>
          <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.6">
            <?php foreach ($saar['donts'] as $d): ?><li><?= $e($d) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
    <?php if (!empty($saar['remedies'])): ?>
      <div style="margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:7px 9px">
        <div style="font-weight:700;color:#92400e;font-size:.8rem">🪔 इस वर्ष प्राथमिकता के उपाय</div>
        <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.6">
          <?php foreach ($saar['remedies'] as $r): ?><li><?= $e($r) ?></li><?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <!-- ===== ग्रह-वार वर्ष फल (planet-wise annual reading) ===== -->
  <div class="lk-card">
    <div class="lk-card-h">🪐 ग्रह-वार वर्ष फल (लाल किताब)</div>
    <div class="lk-txt" style="color:#64748b;margin-bottom:6px;font-size:.8rem">प्रत्येक ग्रह इस वर्ष जिस भाव में सक्रिय है, उसी अनुसार लाल-किताब फल, करें/न करें व उपाय।</div>
    <?php foreach ($v['planets'] as $p): ?>
      <div class="lk-card" style="margin:0 0 8px;padding:8px 10px;<?= ($p['verdict'] === 'अशुभ') ? 'border-color:#fca5a5' : (($p['verdict'] === 'शुभ') ? 'border-color:#86efac' : '') ?>">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
          <b style="font-size:.9rem"><?= $e($p['hi']) ?></b>
          <span style="font-size:.76rem;color:#475569"><?= $e($p['house_ord']) ?> भाव में</span>
          <?= $vpill((string) $p['verdict']) ?>
          <?php if (!empty($p['asleep'])): ?><span style="font-size:.7rem;background:#f1f5f9;color:#64748b;border-radius:999px;padding:1px 7px">😴 सुप्त</span><?php endif; ?>
        </div>
        <div class="lk-txt" style="margin-top:4px;line-height:1.6"><?= $e($p['pred_head']) ?></div>
        <?php if (!empty($p['pred_effects'])): ?>
          <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.55;color:#334155">
            <?php foreach (array_slice($p['pred_effects'], 0, 3) as $pe): ?><li><?= $e($pe) ?></li><?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div class="lk-dd" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:6px;margin-top:6px">
          <?php if (!empty($p['dos'])): ?>
            <div style="font-size:.78rem"><span style="color:#166534;font-weight:700">✅ करें:</span> <?= $e(implode(' · ', array_slice($p['dos'], 0, 3))) ?></div>
          <?php endif; ?>
          <?php if (!empty($p['donts'])): ?>
            <div style="font-size:.78rem"><span style="color:#991b1b;font-weight:700">⛔ न करें:</span> <?= $e(implode(' · ', array_slice($p['donts'], 0, 3))) ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($p['need_remedy']) && !empty($p['remedies'])): ?>
          <div style="margin-top:6px;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:5px 8px;font-size:.78rem;line-height:1.55">
            <span style="color:#92400e;font-weight:700">🪔 उपाय:</span>
            <?= $e(implode(' · ', array_slice($p['remedies'], 0, 3))) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== भाव-वार वर्ष फल (house-wise annual reading) ===== -->
  <div class="lk-card">
    <div class="lk-card-h">🏠 भाव-वार वर्ष फल (लाल किताब)</div>
    <div class="lk-txt" style="color:#64748b;margin-bottom:6px;font-size:.8rem">इस वर्ष की कुंडली में प्रत्येक भाव की स्थिति, फल व आवश्यक उपाय।</div>
    <?php foreach ($v['houses'] as $hn => $hr):
        if (($hr['verdict'] ?? '') === 'मध्यम' && empty($hr['planets']) && empty($hr['need_remedy'])) { continue; } ?>
      <div class="lk-card" style="margin:0 0 8px;padding:8px 10px;<?= ($hr['verdict'] === 'अशुभ') ? 'border-color:#fca5a5' : (($hr['verdict'] === 'शुभ') ? 'border-color:#86efac' : '') ?>">
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
          <b style="font-size:.88rem"><?= $e($hr['house_ord']) ?> भाव</b>
          <span style="font-size:.74rem;color:#475569"><?= $e($hr['vishay'] ?? '') ?></span>
          <?= $vpill((string) $hr['verdict']) ?>
          <?php if (!empty($hr['planets_hi'])): ?><span style="font-size:.72rem;color:#334155">ग्रह: <?= $e(implode(', ', $hr['planets_hi'])) ?></span><?php endif; ?>
        </div>
        <div class="lk-txt" style="margin-top:4px;line-height:1.6"><?= $e($hr['pred_head']) ?></div>
        <?php if (!empty($hr['need_remedy']) && !empty($hr['remedies'])): ?>
          <div style="margin-top:6px;background:#fffbeb;border:1px solid #fde68a;border-radius:7px;padding:5px 8px;font-size:.78rem;line-height:1.55">
            <span style="color:#92400e;font-weight:700">🪔 उपाय:</span> <?= $e(implode(' · ', array_slice($hr['remedies'], 0, 2))) ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== योग व युति-दोष (annual) ===== -->
  <?php
  $appYoga = array_values(array_filter($v['yoga'] ?? [], static fn ($y) => !empty($y['applicable']) && trim((string) ($y['phal'] ?? '')) !== ''));
  ?>
  <?php if (!empty($appYoga) || !empty($v['remedy']['yuti'])): ?>
  <div class="lk-card">
    <div class="lk-card-h">🧩 इस वर्ष के योग व युति-उपाय</div>
    <?php if (!empty($appYoga)): ?>
      <div style="font-weight:700;font-size:.82rem;color:#334155;margin:2px 0 4px">लागू भविष्यवाणी-सूत्र</div>
      <ul style="margin:0;padding-left:18px;font-size:.8rem;line-height:1.6">
        <?php foreach (array_slice($appYoga, 0, 8) as $yg):
            $c = ($yg['tone'] ?? '') === 'pos' ? '#166534' : (($yg['tone'] ?? '') === 'neg' ? '#991b1b' : '#334155'); ?>
          <li style="color:<?= $c ?>"><?= $e($yg['phal']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
    <?php if (!empty($v['remedy']['yuti'])): ?>
      <div style="font-weight:700;font-size:.82rem;color:#334155;margin:8px 0 4px">युति-उपाय (इस वर्ष की स्थिति अनुसार)</div>
      <ul style="margin:0;padding-left:18px;font-size:.8rem;line-height:1.6">
        <?php foreach (array_slice($v['remedy']['yuti'], 0, 8) as $yu): ?>
          <li><b><?= $e($yu['yuti']) ?></b> (<?= $e($yu['house_ord']) ?> भाव): <?= $e($yu['upay']) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>
