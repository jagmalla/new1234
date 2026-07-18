<?php
/**
 * Lal Kitab (लाल किताब) reading panel — included inside the full-width
 * #sec-lalkitab section of calc-v2. Renders the fixed-Aries Lal Kitab chart
 * (client-side from AB_LALKITAB) plus every prediction category as a hidden
 * view toggled by the #lk-select dropdown. Every problem carries its उपाय
 * (remedy) directly beneath it.
 *
 * Expects: $lk = LalKitabEngine::compute() result, $h = html escaper.
 *
 * @var array<string,mixed> $lk
 * @var callable $h
 */
$lk = $lk ?? ['ok' => false];
$ok = !empty($lk['ok']);

/** Chart payload for the north-chart renderer (fixed Aries lagna). */
$abbr = ['Sun' => 'Su', 'Moon' => 'Mo', 'Mars' => 'Ma', 'Mercury' => 'Me',
    'Jupiter' => 'Ju', 'Venus' => 'Ve', 'Saturn' => 'Sa', 'Rahu' => 'Ra', 'Ketu' => 'Ke'];
$lkNorth = ['asc_sign' => 0, 'planets' => []];
if ($ok) {
    foreach ($lk['planets'] as $p) {
        $lkNorth['planets'][] = [
            'abbr'  => $abbr[$p['planet']] ?? $p['planet'],
            'sign'  => (int) $p['house'] - 1,   // house N ⇒ Aries+(N-1)
            'deg'   => 0,
            'retro' => !empty($p['retro']),
        ];
    }
}

/** small helper: colour pill for a verdict / status. */
$pill = static function (string $txt, string $kind) use ($h): string {
    $map = [
        'शुभ' => 'background:#dcfce7;color:#166534', 'उच्च' => 'background:#dcfce7;color:#166534',
        'स्वगृही' => 'background:#dbeafe;color:#1e40af', 'सम' => 'background:#f1f5f9;color:#475569',
        'मध्यम' => 'background:#fef9c3;color:#854d0e',
        'अशुभ' => 'background:#fee2e2;color:#991b1b', 'नीच' => 'background:#fee2e2;color:#991b1b',
    ];
    $st = $map[$txt] ?? 'background:#f1f5f9;color:#475569';
    return '<span class="lk-pill" style="' . $st . '">' . $h($txt) . '</span>';
};
/** render a remedy (उपाय) block under a problem. */
$remBlock = static function (array $items, string $title = 'उपाय / टोटके') use ($h): string {
    $items = array_values(array_filter($items, static fn ($x) => trim((string) $x) !== ''));
    if ($items === []) { return ''; }
    $out = '<div class="lk-rem"><div class="lk-rem-h">🛠 ' . $h($title) . '</div><ul class="lk-rem-list">';
    foreach ($items as $it) { $out .= '<li>' . $h((string) $it) . '</li>'; }
    return $out . '</ul></div>';
};
/** small right-floated source tag naming the rule bank a card came from (#10). */
$srcTag = static function (string $s) use ($h): string {
    return '<span class="lk-src" title="स्रोत">' . $h($s) . '</span>';
};
/** priority-score pill — red ≥4, amber 2-3, gray 1 (hidden at 0). */
$scorePill = static function (int $score): string {
    if ($score <= 0) { return ''; }
    $st = $score >= 4 ? 'background:#dc2626;color:#fff'
        : ($score >= 2 ? 'background:#f59e0b;color:#fff' : 'background:#e2e8f0;color:#475569');
    return '<span class="lk-pill" style="' . $st . '">प्राथमिकता ' . $score . '</span>';
};
?>
<style>
  #sec-lalkitab .lk-pill{display:inline-block;padding:1px 8px;border-radius:999px;font-size:.72rem;font-weight:700;margin-left:4px;vertical-align:middle}
  #sec-lalkitab .lk-rem{margin-top:8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 10px}
  #sec-lalkitab .lk-rem-h{font-weight:700;color:#9a3412;font-size:.82rem;margin-bottom:4px}
  #sec-lalkitab .lk-rem-list{margin:0;padding-left:18px;font-size:.83rem;color:#7c2d12;line-height:1.55}
  #sec-lalkitab .lk-rem-list li{margin:2px 0}
  #sec-lalkitab .lk-card{border:1px solid #e5e7eb;border-radius:10px;padding:11px 13px;margin-bottom:11px;background:#fff}
  #sec-lalkitab .lk-card.bad{border-color:#fecaca;background:#fef2f2}
  #sec-lalkitab .lk-card.good{border-color:#bbf7d0;background:#f0fdf4}
  #sec-lalkitab .lk-card-h{font-weight:700;color:#1f2937;font-size:.95rem;margin-bottom:3px;display:flex;align-items:center;flex-wrap:wrap;gap:2px}
  #sec-lalkitab .lk-meta{font-size:.78rem;color:#64748b;margin:2px 0 5px}
  #sec-lalkitab .lk-txt{font-size:.85rem;color:#334155;line-height:1.6}
  #sec-lalkitab .lk-sub{font-size:.8rem;color:#475569;margin:3px 0}
  #sec-lalkitab .lk-sub b{color:#334155}
  #sec-lalkitab .lk-view{display:none}
  #sec-lalkitab .lk-view.active{display:block}
  #sec-lalkitab .lk-scroll{max-height:74vh;overflow-y:auto;padding-right:6px}
  #sec-lalkitab .lk-flt{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-size:.8rem;color:#475569;margin-bottom:9px}
  #sec-lalkitab .lk-legend{font-size:.74rem;color:#64748b;margin-top:9px;line-height:1.7}
  #sec-lalkitab h3.lk-h{font-size:.95rem;font-weight:700;color:#0f172a;margin:2px 0 8px;border-bottom:1px solid #e5e7eb;padding-bottom:5px}
  #sec-lalkitab .lk-src{float:right;font-size:.64rem;color:#94a3b8;background:#f8fafc;border:1px solid #e2e8f0;border-radius:4px;padding:0 5px;margin-left:6px;font-weight:400}
  #sec-lalkitab .lk-active{background:linear-gradient(90deg,#fff7ed,#fffbeb);border:1px solid #fdba74;border-radius:10px;padding:9px 12px;margin-bottom:10px;font-size:.8rem;color:#7c2d12;line-height:1.7}
  #sec-lalkitab .lk-active b{color:#9a3412}
  #sec-lalkitab .lk-active .lk-pill{margin-left:2px}
  #sec-lalkitab .lk-btn{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:5px 11px;font-size:.78rem;font-weight:600;color:#334155;cursor:pointer;white-space:nowrap}
  #sec-lalkitab .lk-btn:hover{background:#f1f5f9}
  #sec-lalkitab .lk-chip{border:1px solid #e2e8f0;background:#f8fafc;border-radius:999px;padding:2px 10px;font-size:.73rem;color:#475569;cursor:pointer}
  #sec-lalkitab .lk-chip:hover{background:#e0f2fe;border-color:#7dd3fc}
  #sec-lalkitab .lk-search-row{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-bottom:10px}
  #sec-lalkitab #lk-q{flex:1;min-width:130px;border:1px solid #cbd5e1;border-radius:8px;padding:5px 10px;font-size:.8rem}
  #sec-lalkitab .lk-reason{font-size:.74rem;color:#92400e;background:#fffbeb;border:1px dashed #fcd34d;border-radius:6px;padding:3px 8px;margin-top:5px}
  #sec-lalkitab .lk-cmp{width:100%;border-collapse:collapse;font-size:.8rem}
  #sec-lalkitab .lk-cmp th{background:#f8fafc;text-align:left;padding:5px 7px;border-bottom:1px solid #e5e7eb}
  #sec-lalkitab .lk-cmp td{padding:5px 7px;border-bottom:1px solid #f1f5f9;vertical-align:top}
  #sec-lalkitab .lk-anlz{border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;padding:7px 10px;margin:6px 0;font-size:.8rem;color:#334155}
  #sec-lalkitab .lk-anlz-row{display:flex;gap:8px;align-items:flex-start;padding:2px 0;flex-wrap:wrap}
  #sec-lalkitab .lk-anlz-k{flex:none;min-width:76px;font-weight:700;color:#64748b;font-size:.74rem;padding-top:1px}
  #sec-lalkitab .lk-anlz-final{border-top:1px dashed #cbd5e1;margin-top:3px;padding-top:5px}
</style>

<?php if (!$ok): ?>
  <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
    लाल किताब गणना उपलब्ध नहीं है<?= !empty($lk['error']) ? ' — ' . $h((string) $lk['error']) : '' ?>।
    पहले जन्म विवरण भरकर <b>Calculate</b> करें।
  </div>
<?php else: ?>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">

  <!-- LEFT: Lal Kitab (fixed-Aries) chart -->
  <div class="bg-white rounded-lg shadow p-3 flex flex-col">
    <div class="flex items-center justify-between mb-2 pb-2 border-b">
      <span class="font-semibold text-gray-800">लाल किताब कुंडली
        <span class="text-xs text-gray-400 font-normal">(स्थिर मेष लग्न — Lal Kitab Teva)</span></span>
    </div>
    <div id="lk-chart" class="w-full"></div>
    <div class="lk-legend">
      हर भाव का स्वामी स्थिर है — 1 मंगल, 2 शुक्र, 3 बुध, 4 चन्द्र, 5 सूर्य, 6 बुध,
      7 शुक्र, 8 मंगल, 9 गुरु, 10 शनि, 11 शनि, 12 गुरु। ग्रह अपने जन्म-भाव के अनुसार बैठते हैं।<br>
      <b>जन्म-लग्न:</b> <?= $h((string) $lk['lagna_hi']) ?> ·
      <b>चन्द्र राशि:</b> <?= $h((string) $lk['moon_hi']) ?>
    </div>
  </div>

  <!-- RIGHT: category predictions -->
  <div class="bg-white rounded-lg shadow p-4 flex flex-col">

    <?php $act = $lk['active'] ?? []; ?>
    <?php if (!empty($act['maha']) || !empty($act['antar']) || !empty($act['sadesati']) || !empty($act['year_eff']) || !empty($act['year_bad'])): ?>
    <!-- 🔥 what is live right now: dasha lords, sade-sati, this-year planets (#1) -->
    <div class="lk-active">
      <b>🔥 अभी सक्रिय:</b>
      <?php if (!empty($act['maha'])): ?>
        महादशा — <b><?= $h((string) $act['maha']['hi']) ?></b> (<?= $h((string) $act['maha']['house_ord']) ?> भाव)<?= $pill((string) $act['maha']['verdict'], 'v') ?>
      <?php endif; ?>
      <?php if (!empty($act['antar'])): ?>
        · अंतर्दशा — <b><?= $h((string) $act['antar']['hi']) ?></b> (<?= $h((string) $act['antar']['house_ord']) ?> भाव)<?= $pill((string) $act['antar']['verdict'], 'v') ?>
      <?php endif; ?>
      <?php if (!empty($act['sadesati'])): ?>
        · <b><?= $h((string) $act['sadesati']['label']) ?> चल रही है</b><?= $act['sadesati']['phase'] ? ' (चरण ' . (int) $act['sadesati']['phase'] . ')' : '' ?>
      <?php endif; ?>
      <?php if (!empty($act['year_eff']) || !empty($act['year_bad'])): ?>
        <br><b>इस आयु-वर्ष (<?= (int) ($act['age'] ?? 0) ?>) में:</b>
        <?php if (!empty($act['year_eff'])): ?>प्रभावी ग्रह — <?= $h(implode(', ', $act['year_eff'])) ?><?php endif; ?>
        <?php if (!empty($act['year_bad'])): ?> · <span style="color:#b91c1c">अशुभ वर्ष — <?= $h(implode(', ', $act['year_bad'])) ?></span><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="flex items-center gap-2 mb-2" style="flex-wrap:wrap">
      <select id="lk-select" class="l2-select" aria-label="लाल किताब श्रेणी चुनें" style="flex:1;min-width:190px">
        <option value="overview">🔎 सामान्य परिचय / General Overview</option>
        <option value="planet">🪐 ग्रह फल / Planet Prediction</option>
        <option value="house">🏠 भाव (Bhav) फल / House Prediction</option>
        <option value="karak">🎯 कारक / Karak</option>
        <option value="yoga">✨ योग / Yog</option>
        <option value="shrap">🧬 श्राप / पैतृक ऋण / Shrap</option>
        <option value="sadesati">🪐 साढ़े साती / ढैय्या / Sadde Satti</option>
        <option value="manglik">🔴 मंगलीक दोष / Manglik</option>
        <option value="ayu">⏳ आयु योग / Longevity</option>
        <option value="health">🩺 रोग / संतान / Health</option>
        <option value="bhavan">🏗 गृह निर्माण / Vastu</option>
        <option value="varsh">📅 वर्ष कुंडली ज्ञान / Annual</option>
        <option value="supt">😴 सुप्त ग्रह / Sleeping Planets</option>
        <option value="drishti">👁 भाव दृष्टि / House Aspects</option>
        <option value="compare">⚖ D1 ↔ लाल किताब तुलना / Compare</option>
        <option value="remedy">🛠 उपाय / Remedy</option>
        <option value="rules">📜 उपाय नियम / Rules</option>
        <option value="reference">📚 संदर्भ चक्र / Reference</option>
        <option value="search" hidden>🔍 खोज परिणाम / Search Results</option>
      </select>
      <button type="button" id="lk-print-report" class="lk-btn">🖨 पूर्ण रिपोर्ट</button>
      <button type="button" id="lk-print-checklist" class="lk-btn">📋 उपाय Checklist</button>
    </div>

    <!-- topic search across every category (#6) -->
    <div class="lk-search-row">
      <input type="text" id="lk-q" placeholder="🔍 विषय खोजें… (जैसे धन, विवाह, संतान)" aria-label="लाल किताब में खोजें">
      <button type="button" class="lk-chip" data-topic="धन">💰 धन</button>
      <button type="button" class="lk-chip" data-topic="विवाह">💑 विवाह</button>
      <button type="button" class="lk-chip" data-topic="संतान">👶 संतान</button>
      <button type="button" class="lk-chip" data-topic="रोग">🩺 रोग</button>
      <button type="button" class="lk-chip" data-topic="नौकरी">💼 नौकरी/व्यापार</button>
      <button type="button" class="lk-chip" data-topic="शिक्षा">🎓 शिक्षा</button>
      <button type="button" class="lk-chip" data-topic="मुकदमा">⚖ मुकदमा</button>
      <button type="button" class="lk-chip" data-topic="विदेश">✈ विदेश</button>
    </div>

    <div class="lk-scroll">

      <!-- ===== OVERVIEW ===== -->
      <div class="lk-view active" data-lk="overview">
        <h3 class="lk-h">सामान्य परिचय (Lal Kitab Overview)</h3>

        <?php if (!empty($lk['priority'])): ?>
        <!-- priority summary — which remedies to start with (#2) -->
        <div class="lk-card" style="border-color:#f59e0b;background:#fffbeb">
          <div class="lk-card-h">🎯 सबसे पहले इन उपायों से शुरू करें <?= $srcTag('प्राथमिकता स्कोर') ?></div>
          <?php foreach ($lk['priority'] as $i => $pp): ?>
            <div style="margin:7px 0 9px">
              <div class="lk-sub" style="font-size:.86rem"><b><?= $i + 1 ?>. <?= $h((string) $pp['hi']) ?></b> (<?= $h((string) $pp['house_ord']) ?> भाव) <?= $scorePill((int) $pp['score']) ?></div>
              <?php if ($pp['reasons']): ?><div class="lk-reason">कारण: <?= $h(implode(' · ', $pp['reasons'])) ?></div><?php endif; ?>
              <?= $remBlock(array_merge($pp['remedies'], $pp['sheeghra'] !== '' ? ['⚡ शीघ्र: ' . $pp['sheeghra']] : []), $pp['hi'] . ' — पहले ये उपाय' . ($pp['var'] !== '' ? ' (प्रारंभ: ' . $pp['var'] . ')' : '')) ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="lk-card good">
          <div class="lk-card-h">🎯 प्राथमिकता</div>
          <div class="lk-txt">कोई गंभीर अशुभता नहीं मिली — सामान्य उपाय पर्याप्त हैं।</div>
        </div>
        <?php endif; ?>

        <div class="lk-txt" style="margin-bottom:10px">
          लाल किताब में लग्न सदैव <b>मेष</b> माना जाता है और बारह भावों के स्वामी स्थिर रहते हैं।
          ग्रह अपने जन्म-कुंडली के भाव के अनुसार यहाँ बैठते हैं। नीचे प्रत्येक ग्रह की स्थिति व शुभ/अशुभ प्रवृत्ति का सार है।
        </div>
        <?php foreach ($lk['planets'] as $p):
            $cls = $p['is_ashubh'] ? 'bad' : ($p['verdict'] === 'शुभ' ? 'good' : ''); ?>
          <div class="lk-card <?= $cls ?>">
            <div class="lk-card-h">
              <?= $h((string) $p['hi']) ?>
              <span class="lk-meta" style="margin:0 0 0 6px"><?= $h((string) $p['house_ord']) ?> भाव · <?= $h((string) $p['sign_hi']) ?><?= $p['retro'] ? ' · वक्री' : '' ?></span>
              <?= $pill((string) $p['status'], 'status') ?><?= $pill((string) $p['verdict'], 'verdict') ?><?php if (!empty($p['pukka'])): ?><span class="lk-pill" style="background:#ede9fe;color:#5b21b6">पक्का घर</span><?php endif; ?><?= $scorePill((int) ($p['score'] ?? 0)) ?>
            </div>
            <div class="lk-txt"><b>कारक भाव:</b> <?= $h((string) $p['karak_bhav']) ?> · <b>शुभ भाव:</b> <?= $h((string) $p['shubh']) ?> · <b>अशुभ भाव:</b> <?= $h((string) $p['ashubh']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== PLANET PREDICTION ===== -->
      <div class="lk-view" data-lk="planet">
        <h3 class="lk-h">ग्रह फल एवं उपाय (Planet-wise)</h3>
        <div class="lk-flt">
          <label><input type="checkbox" class="lk-onlybad" data-scope="planet"> केवल अशुभ ग्रह दिखाएँ</label>
        </div>
        <?php foreach ($lk['planets'] as $p):
            $cls = $p['is_ashubh'] ? 'bad' : ($p['verdict'] === 'शुभ' ? 'good' : ''); ?>
          <div class="lk-card <?= $cls ?>" data-bad="<?= $p['is_ashubh'] ? '1' : '0' ?>">
            <div class="lk-card-h">
              <?= $h((string) $p['hi']) ?> — <?= $h((string) $p['house_ord']) ?> भाव में (<?= $h((string) $p['sign_hi']) ?>)
              <?= $pill((string) $p['status'], 'status') ?><?= $pill((string) $p['verdict'], 'verdict') ?><?php
                if (!empty($p['pukka'])): ?><span class="lk-pill" style="background:#ede9fe;color:#5b21b6">पक्का घर</span><?php endif;
                if (!empty($act['maha']) && $act['maha']['hi'] === $p['hi']): ?><span class="lk-pill" style="background:#dc2626;color:#fff">🔥 महादशा</span><?php endif;
                if (!empty($act['antar']) && $act['antar']['hi'] === $p['hi']): ?><span class="lk-pill" style="background:#f97316;color:#fff">🔥 अंतर्दशा</span><?php endif;
              ?><?= $scorePill((int) ($p['score'] ?? 0)) ?>
              <?= $srcTag('शुभ-अशुभ भाव चक्र') ?>
            </div>
            <?php if (!empty($p['reasons']) && ($p['score'] ?? 0) > 0): ?>
              <div class="lk-reason">प्राथमिकता-कारण: <?= $h(implode(' · ', $p['reasons'])) ?></div>
            <?php endif; ?>

            <!-- स्थिति-विश्लेषण: भाव → युति → दृष्टि → सुप्त — इस कुंडली पर computed -->
            <div class="lk-anlz">
              <div class="lk-anlz-row"><span class="lk-anlz-k">🏠 भाव</span>
                <?= $h((string) $p['house_ord']) ?> भाव —
                <?php $inS = in_array($p['house'], array_map('intval', preg_split('/[^0-9]+/', (string) $p['shubh'], -1, PREG_SPLIT_NO_EMPTY) ?: []), true); ?>
                <?= $inS ? '<b style="color:#166534">इस ग्रह का शुभ भाव</b>' : '<b style="color:#991b1b">इस ग्रह का अशुभ/सामान्य भाव</b>' ?>
                <span style="color:#94a3b8">(शुभ: <?= $h((string) $p['shubh']) ?> · अशुभ: <?= $h((string) $p['ashubh']) ?>)</span>
              </div>
              <div class="lk-anlz-row"><span class="lk-anlz-k">🤝 युति</span>
                <?php if (!empty($p['alone'])): ?>
                  अकेला बैठा है — "अदृष्ट अकेला" नियम लागू
                <?php else: ?>
                  साथ में:
                  <?php foreach ($p['mates'] as $mE): ?>
                    <b><?= $h((string) $mE['hi']) ?></b> <span class="lk-pill" style="<?= $mE['rel'] === 'मित्र' ? 'background:#dcfce7;color:#166534' : ($mE['rel'] === 'शत्रु' ? 'background:#fee2e2;color:#991b1b' : 'background:#f1f5f9;color:#475569') ?>"><?= $h((string) $mE['rel']) ?></span>
                  <?php endforeach; ?>
                  <?php foreach (($p['dosha'] ?? []) as $dn): ?>
                    <span class="lk-pill" style="background:#dc2626;color:#fff">⚠ <?= $h((string) $dn) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
              <?php if (!empty($p['in_hits']) || !empty($p['out_hits'])): ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k">👁 दृष्टि</span>
                <?php foreach (($p['in_hits'] ?? []) as $ih): ?>
                  <span style="color:<?= $ih['kind'] === 'टकराव' ? '#991b1b' : ($ih['kind'] === 'सहायता' ? '#166534' : '#475569') ?>">
                    ← <?= $h(implode(', ', $ih['planets_hi'])) ?> (<?= (int) $ih['house'] ?>वें) से <?= $h((string) $ih['kind']) ?></span> &nbsp;
                <?php endforeach; ?>
                <?php foreach (($p['out_hits'] ?? []) as $oh): ?>
                  <span style="color:<?= $oh['kind'] === 'टकराव' ? '#991b1b' : ($oh['kind'] === 'सहायता' ? '#166534' : '#475569') ?>">
                    → <?= (int) $oh['house'] ?>वें (<?= $h(implode(', ', $oh['planets_hi'])) ?>) पर <?= $h((string) $oh['kind']) ?></span> &nbsp;
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k">😴 सुप्त</span>
                <?= !empty($p['asleep']) ? '<b style="color:#991b1b">सुप्त — जगाने वाला ग्रह कुंडली में नहीं; फल दबा रहेगा</b>' : 'जागृत — फल सक्रिय' ?>
              </div>
              <?php if (!empty($p['verdict_why'])): ?>
              <div class="lk-anlz-row lk-anlz-final"><span class="lk-anlz-k">⚖ निष्कर्ष</span>
                <b><?= $h((string) $p['verdict']) ?></b> — <?= $h(implode(' · ', $p['verdict_why'])) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- इस कुंडली में लागू फल (टिप्पणी के जाँचे हुए नियम) -->
            <?php if (!empty($p['notes_applied'])): ?>
              <div class="lk-sub" style="margin-top:7px"><b>📌 इस कुंडली में लागू फल:</b></div>
              <?php foreach ($p['notes_applied'] as $na): ?>
                <div class="lk-txt" style="margin:3px 0;padding-left:10px;border-left:3px solid #0f766e">
                  <?= $h((string) $na['text']) ?>
                  <span style="font-size:.72rem;color:#0f766e">(<?= $h((string) $na['why']) ?>)</span>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($p['notes_ref'])): ?>
              <details style="margin-top:5px">
                <summary style="cursor:pointer;font-size:.76rem;color:#94a3b8">अन्य नियम — इस कुंडली में लागू नहीं (<?= count($p['notes_ref']) ?>)</summary>
                <?php foreach ($p['notes_ref'] as $nr): ?>
                  <div style="font-size:.78rem;color:#94a3b8;margin:2px 0;padding-left:10px">✗ <?= $h((string) $nr) ?></div>
                <?php endforeach; ?>
              </details>
            <?php endif; ?>

            <?php if (trim((string) $p['ashubh_lakshan']) !== ''): ?>
              <div class="lk-txt" style="margin-top:5px;color:#991b1b"><b>⚠ अशुभ लक्षण (मिलान करें):</b> <?= $h((string) $p['ashubh_lakshan']) ?></div>
            <?php endif; ?>

            <!-- उपाय — केवल तभी जब वास्तव में आवश्यकता हो -->
            <?php if (!empty($p['need_remedy'])): ?>
              <?= $remBlock($p['remedies'], $p['hi'] . ' — भावगत उपाय (आवश्यक)') ?>
              <?php if (trim((string) $p['sheeghra']) !== ''): ?>
                <div class="lk-sub" style="margin-top:5px"><b>⚡ शीघ्र उपाय:</b> <?= $h((string) $p['sheeghra']) ?></div>
              <?php endif; ?>
            <?php else: ?>
              <div style="margin-top:8px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:6px 10px;font-size:.8rem;color:#166534">
                ✅ यह ग्रह <?= $p['verdict'] === 'शुभ' ? 'शुभ' : 'सामान्य' ?> स्थिति में है — <b>कोई उपाय आवश्यक नहीं</b>।
              </div>
              <?php if (!empty($p['remedies'])): ?>
              <details style="margin-top:4px">
                <summary style="cursor:pointer;font-size:.76rem;color:#94a3b8">एहतियाती उपाय देखें (optional)</summary>
                <?= $remBlock($p['remedies'], $p['hi'] . ' — एहतियाती उपाय') ?>
              </details>
              <?php endif; ?>
            <?php endif; ?>

            <div class="lk-sub" style="margin-top:7px;color:#94a3b8"><b>परिचय:</b> प्रकृति <?= $h((string) $p['prakriti']) ?> · रंग <?= $h((string) $p['rang']) ?> · कारक भाव <?= $h((string) $p['karak_bhav']) ?> · उच्च <?= $h((string) $p['uch_rashi']) ?> · नीच <?= $h((string) $p['neech_rashi']) ?><?php if (trim((string) $p['mitra']) !== ''): ?> · मित्र: <?= $h((string) $p['mitra']) ?> · शत्रु: <?= $h((string) $p['shatru']) ?><?php endif; ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== HOUSE PREDICTION ===== -->
      <div class="lk-view" data-lk="house">
        <h3 class="lk-h">भाव (Bhav) फल — computed</h3>
        <div class="lk-flt">
          <label><input type="checkbox" class="lk-onlybad" data-scope="house"> केवल अशुभ/सुप्त भाव दिखाएँ</label>
        </div>
        <?php foreach ($lk['houses'] as $H):
            $hBad = $H['verdict'] === 'अशुभ' || empty($H['awake']);
            $hCls = $H['verdict'] === 'अशुभ' ? 'bad' : ($H['verdict'] === 'शुभ' ? 'good' : ''); ?>
          <div class="lk-card <?= $hCls ?>" data-bad="<?= $hBad ? '1' : '0' ?>">
            <div class="lk-card-h"><?= (int) $H['house'] ?>. <?= $h((string) $H['house_ord']) ?> भाव — <?= $h((string) $H['rashi']) ?>
              <?= $pill((string) $H['verdict'], 'v') ?>
              <?php if (empty($H['awake'])): ?><span class="lk-pill" style="background:#fee2e2;color:#991b1b">😴 सुप्त</span><?php else: ?><span class="lk-pill" style="background:#dcfce7;color:#166534">जागृत</span><?php endif; ?>
              <?= $srcTag('भाव विचार + दृष्टि चक्र') ?>
            </div>

            <div class="lk-anlz">
              <div class="lk-anlz-row"><span class="lk-anlz-k">🪐 स्थित ग्रह</span>
                <?php if (!empty($H['occ'])): foreach ($H['occ'] as $oe): ?>
                  <b><?= $h((string) $oe['hi']) ?></b> <span class="lk-pill" style="<?= $oe['verdict'] === 'शुभ' ? 'background:#dcfce7;color:#166534' : ($oe['verdict'] === 'अशुभ' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $oe['verdict']) ?></span>
                <?php endforeach; else: ?>
                  <span style="color:#94a3b8">कोई ग्रह नहीं — भाव खाली</span>
                <?php endif; ?>
              </div>
              <div class="lk-anlz-row"><span class="lk-anlz-k">👑 स्वामी</span>
                <b><?= $h((string) $H['lord_hi']) ?></b>
                <?php if ($H['lord_house']): ?>— <?= $h(\AutoBusiness\Astro\LalKitab\LalKitabData::houseOrdinalHi((int) $H['lord_house'])) ?> भाव में<?php endif; ?>
                <?php if (!empty($H['lord_verdict'])): ?><span class="lk-pill" style="<?= $H['lord_verdict'] === 'शुभ' ? 'background:#dcfce7;color:#166534' : ($H['lord_verdict'] === 'अशुभ' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $H['lord_verdict']) ?></span><?php endif; ?>
              </div>
              <div class="lk-anlz-row"><span class="lk-anlz-k">😴 जागृति</span>
                <?php if (!empty($H['awake'])): ?>
                  जागृत — <?= $h((string) $H['awake_by']) ?>
                <?php else: ?>
                  <b style="color:#991b1b">सुप्त</b> — जगाने वाला ग्रह <b><?= $h((string) $H['waker_hi']) ?></b>; इस भाव के विषय दबे रहेंगे
                <?php endif; ?>
              </div>
              <?php if (!empty($H['in_hits'])): ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k">👁 दृष्टि</span>
                <?php foreach ($H['in_hits'] as $ih): ?>
                  <span style="color:<?= $ih['kind'] === 'टकराव' ? '#991b1b' : ($ih['kind'] === 'सहायता' ? '#166534' : '#475569') ?>">← <?= $h(implode(', ', $ih['planets_hi'])) ?> (<?= (int) $ih['house'] ?>वें) से <?= $h((string) $ih['kind']) ?></span> &nbsp;
                <?php endforeach; ?>
              </div>
              <?php endif; ?>
              <?php foreach (($H['warn'] ?? []) as $wl): ?>
                <div class="lk-anlz-row"><span class="lk-anlz-k">⚠ चेतावनी</span><span style="color:#991b1b"><?= $h((string) $wl) ?></span></div>
              <?php endforeach; ?>
              <?php if (!empty($H['verdict_why'])): ?>
              <div class="lk-anlz-row lk-anlz-final"><span class="lk-anlz-k">⚖ निष्कर्ष</span>
                <b><?= $h((string) $H['verdict']) ?></b> — <?= $h(implode(' · ', $H['verdict_why'])) ?>
              </div>
              <?php endif; ?>
            </div>

            <div class="lk-txt" style="margin-top:5px">
              <b>इस भाव के विषय<?= $H['verdict'] === 'अशुभ' ? ' (इनमें बाधा/सावधानी)' : ($H['verdict'] === 'शुभ' ? ' (इनमें उन्नति)' : '') ?>:</b>
              <?= $h((string) $H['vishay']) ?>
              <?php if (trim((string) $H['maas']) !== ''): ?><span style="color:#94a3b8"> · विशेष मास: <?= $h((string) $H['maas']) ?></span><?php endif; ?>
            </div>

            <?php if (!empty($H['need_remedy']) && !empty($H['remedies'])): ?>
              <?= $remBlock($H['remedies'], $H['house_ord'] . ' भाव — बल हेतु उपाय') ?>
            <?php elseif ($H['verdict'] === 'शुभ'): ?>
              <div style="margin-top:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:5px 9px;font-size:.78rem;color:#166534">✅ भाव सबल — कोई उपाय आवश्यक नहीं।</div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== KARAK ===== -->
      <div class="lk-view" data-lk="karak">
        <h3 class="lk-h">कारक (Natural Significators)</h3>
        <div class="lk-txt" style="margin-bottom:9px">प्रत्येक भाव का कारक ग्रह तथा वह ग्रह इस कुंडली में कहाँ बैठा है — यदि कारक अशुभ भाव में हो तो उस भाव का फल दुर्बल होता है।</div>
        <?php foreach ($lk['karak'] as $K): if (!$K['karaks']) { continue; } ?>
          <div class="lk-card">
            <div class="lk-card-h"><?= (int) $K['house'] ?>. <?= $h((string) $K['house_ord']) ?> भाव के कारक</div>
            <?php foreach ($K['karaks'] as $kk): ?>
              <div class="lk-sub">
                <b><?= $h((string) $kk['hi']) ?></b>
                <?php if ($kk['placed']): ?>
                  — <?= $h((string) $kk['placed_ord']) ?> भाव में <?= $kk['ok'] ? $pill('शुभ', 's') : $pill('अशुभ', 's') ?>
                <?php else: ?>
                  — स्थिति अज्ञात
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== YOGA ===== -->
      <div class="lk-view" data-lk="yoga">
        <?php if (!empty($lk['yuti_dosha'])): ?>
          <h3 class="lk-h">विशेष युति / ग्रहण दोष</h3>
          <?php foreach ($lk['yuti_dosha'] as $D): ?>
            <div class="lk-card bad" data-app="1">
              <div class="lk-card-h">⚠ <?= $h((string) $D['name']) ?>
                <span class="lk-meta" style="margin:0 0 0 6px"><?= $h((string) $D['pair_hi']) ?> — <?= $h((string) $D['house_ord']) ?> भाव में</span>
                <?= $srcTag('युति दोष गणना') ?>
              </div>
              <div class="lk-txt"><?= $h((string) $D['desc']) ?></div>
              <?= $remBlock($D['remedies'], $D['name'] . ' — उपाय') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="lk-h">योग — भविष्यवाणी सूत्र</h3>
        <div class="lk-flt">
          <label><input type="checkbox" class="lk-onlyapp" data-scope="yoga" checked> केवल लागू योग दिखाएँ</label>
          <span style="color:#94a3b8">(सभी सूत्र देखने हेतु चेक हटाएँ)</span>
        </div>
        <?php foreach ($lk['yoga'] as $Y): ?>
          <div class="lk-card <?= $Y['applicable'] ? 'good' : '' ?>" data-app="<?= $Y['applicable'] ? '1' : '0' ?>">
            <div class="lk-card-h" style="font-size:.88rem">
              <?= $h((string) $Y['sthiti']) ?><?= $Y['applicable'] ? $pill('लागू', 'a') : '' ?>
              <?= $srcTag(($Y['mode'] ?? '') === 'exact' ? 'सटीक गणना' : 'पाठ-मिलान') ?>
            </div>
            <?php if (trim((string) $Y['prabhavit']) !== ''): ?><div class="lk-sub"><b>प्रभावित ग्रह:</b> <?= $h((string) $Y['prabhavit']) ?></div><?php endif; ?>
            <div class="lk-txt"><?= $h((string) $Y['phal']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== SHRAP / PAITRIK RIN ===== -->
      <div class="lk-view" data-lk="shrap">
        <h3 class="lk-h">श्राप / पैतृक ऋण (Ancestral Debts)</h3>
        <div class="lk-txt" style="margin-bottom:9px">लाल किताब के नौ पैतृक ऋण — पहचान, अशुभ फल तथा प्रत्येक का उपाय। (पहचान की शर्तें जटिल होने से "संभावित" चिह्न केवल संकेत है।)</div>
        <?php foreach ($lk['shrap'] as $S): ?>
          <div class="lk-card <?= $S['possible'] ? 'bad' : '' ?>" data-app="<?= $S['possible'] ? '1' : '0' ?>">
            <div class="lk-card-h"><?= $h((string) $S['rin']) ?><?= $S['possible'] ? $pill('संभावित', 'p') : '' ?></div>
            <div class="lk-sub"><b>पहचान:</b> <?= $h((string) $S['pehchan']) ?></div>
            <?php if (trim((string) $S['ashubh_grah']) !== ''): ?><div class="lk-sub"><b>अशुभ ग्रह:</b> <?= $h((string) $S['ashubh_grah']) ?></div><?php endif; ?>
            <?php if (trim((string) $S['ashubh_phal']) !== ''): ?><div class="lk-txt" style="color:#991b1b"><b>अशुभ फल:</b> <?= $h((string) $S['ashubh_phal']) ?></div><?php endif; ?>
            <?= $remBlock([(string) $S['upay']], 'ऋण-मुक्ति उपाय') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== SADE SATI / DHAIYA ===== -->
      <div class="lk-view" data-lk="sadesati">
        <?php $ss = $lk['sadesati']; ?>
        <h3 class="lk-h">साढ़े साती / ढैय्या — चन्द्र राशि <?= $h((string) $ss['rashi_hi']) ?></h3>
        <div class="lk-card">
          <div class="lk-card-h">साढ़े साती पहचान</div>
          <div class="lk-txt"><b>शनि इन राशियों पर:</b> <?= $h((string) $ss['pehchan']) ?></div>
          <?php if (trim((string) $ss['note']) !== ''): ?><div class="lk-sub"><?= $h((string) $ss['note']) ?></div><?php endif; ?>
        </div>
        <?php foreach ($ss['upay'] as $u): ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.86rem"><?= $h((string) $u['charan']) ?></div>
            <?= $remBlock([(string) $u['upay']], 'चरण उपाय') ?>
          </div>
        <?php endforeach; ?>
        <div class="lk-card">
          <div class="lk-card-h">ढैय्या पहचान</div>
          <div class="lk-txt"><b>शनि इन राशियों पर:</b> <?= $h((string) $ss['dhaiya_pehchan']) ?></div>
        </div>
        <?php foreach ($ss['dhaiya_upay'] as $u): ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.86rem"><?= $h((string) $u['dhaiya']) ?></div>
            <?= $remBlock([(string) $u['upay']], 'ढैय्या उपाय') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== MANGLIK ===== -->
      <div class="lk-view" data-lk="manglik">
        <?php $mg = $lk['manglik']; ?>
        <h3 class="lk-h">मंगलीक दोष (Manglik)</h3>
        <div class="lk-card <?= $mg['is'] ? 'bad' : 'good' ?>">
          <div class="lk-card-h">
            <?= $mg['is'] ? 'मंगलीक दोष है' : 'मंगलीक दोष नहीं है' ?>
            <?= $pill($mg['is'] ? 'दोष' : 'निर्दोष', 'm') ?>
          </div>
          <div class="lk-txt"><b>मंगल:</b> <?= $mg['mars_house'] ? $h((string) $mg['mars_ord']) . ' भाव में' : 'अज्ञात' ?> · <b>लग्न:</b> <?= $h((string) $mg['lagna_hi']) ?></div>
          <?php if ($mg['is'] && trim((string) $mg['upay']) !== ''): ?>
            <?= $remBlock([(string) $mg['upay']], 'मंगल दोष उपाय') ?>
          <?php endif; ?>
        </div>
        <?php if ($mg['vichar']): ?>
          <div class="lk-card">
            <div class="lk-card-h">मंगलीक विचार</div>
            <?php foreach ($mg['vichar'] as $v): ?>
              <div class="lk-sub"><b><?= $h((string) $v['vishay']) ?>:</b> <?= $h((string) $v['vivaran']) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($mg['parihar']): ?>
          <div class="lk-card good">
            <div class="lk-card-h">मंगली दोष परिहार (जिनमें दोष प्रायः समाप्त)</div>
            <ul class="lk-rem-list" style="color:#166534">
              <?php foreach ($mg['parihar'] as $pr): ?><li><?= $h((string) $pr) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <!-- ===== AYU / LONGEVITY ===== -->
      <div class="lk-view" data-lk="ayu">
        <?php $ay = $lk['ayu']; ?>
        <h3 class="lk-h">आयु योग (Longevity)</h3>
        <div class="lk-card">
          <div class="lk-card-h">योग-अनुसार अनुमानित आयु</div>
          <?php foreach ($ay['yoga'] as $y): ?>
            <div class="lk-sub"><b><?= $h((string) $y['ayu']) ?> वर्ष</b> — <?= $h((string) $y['yog']) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="lk-card">
          <div class="lk-card-h">ग्रह चक्र — जीवन में प्रभावशाली वर्ष</div>
          <?php foreach ($ay['chakra'] as $c): ?>
            <div class="lk-sub" style="margin-bottom:5px">
              <b><?= $h((string) $c['hi']) ?>:</b> प्रभाव वर्ष <?= $h((string) $c['prabhav']) ?>
              <?php if (trim((string) $c['ashubh']) !== ''): ?> · <span style="color:#991b1b">अशुभ वर्ष: <?= $h((string) $c['ashubh']) ?></span><?php endif; ?>
              <?php if (trim((string) $c['vishesh']) !== ''): ?><br><span style="color:#64748b">विशेष: <?= $h((string) $c['vishesh']) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ===== HEALTH / PROGENY ===== -->
      <div class="lk-view" data-lk="health">
        <h3 class="lk-h">रोग / संतान उपाय (Health &amp; Progeny)</h3>
        <?php foreach ($lk['health'] as $varg => $items): ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.9rem"><?= $h((string) $varg) ?></div>
            <?= $remBlock($items, 'उपाय') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== BHAVAN / VASTU ===== -->
      <div class="lk-view" data-lk="bhavan">
        <h3 class="lk-h">गृह / भवन निर्माण उपाय (Vastu)</h3>
        <?php foreach ($lk['bhavan'] as $b): ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.88rem"><?= $h((string) $b['sthiti']) ?></div>
            <div class="lk-txt"><?= $h((string) $b['upay']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== VARSH GYAN (annual) ===== -->
      <div class="lk-view" data-lk="varsh">
        <?php $vg = $lk['varsh_gyan']; ?>
        <h3 class="lk-h">वर्ष कुंडली ज्ञान चक्र (Annual)</h3>
        <div class="lk-txt" style="margin-bottom:9px">इस चक्र से किसी भी आयु-वर्ष में हर भाव में सक्रिय होने वाला भाव-अंक ज्ञात होता है — जिससे उस वर्ष का फल पढ़ा जाता है।</div>
        <?php if (!empty($vg['has'])): ?>
          <div class="lk-card good">
            <div class="lk-card-h">आपकी वर्तमान आयु — <?= (int) $vg['age'] ?> वर्ष</div>
            <div class="lk-txt" style="line-height:2">
              <?php for ($i = 0; $i < 12; $i++): ?>
                <span style="display:inline-block;min-width:82px"><b><?= $i + 1 ?>वाँ भाव</b>: <?= $h((string) ($vg['row'][$i] ?? '')) ?></span>
              <?php endfor; ?>
            </div>
            <div class="lk-sub" style="margin-top:6px;color:#64748b">अर्थ: इस आयु-वर्ष में जिस भाव में जो अंक है, उस भाव का फल उस अंक-वाले भाव से जुड़ता है।</div>
          </div>
        <?php else: ?>
          <div class="lk-card"><div class="lk-txt">आयु ज्ञात न होने से वर्ष-विशेष पंक्ति उपलब्ध नहीं।</div></div>
        <?php endif; ?>
      </div>

      <!-- ===== COMPARE: D1 vs Lal Kitab (#8) ===== -->
      <div class="lk-view" data-lk="compare">
        <h3 class="lk-h">D1 ↔ लाल किताब तुलना (Double Confirmation)</h3>
        <div class="lk-txt" style="margin-bottom:9px">
          बायीं ओर लाल किताब टेवा है; नीचे जन्म-कुंडली (D1)। जिस ग्रह की <b>राशि-स्थिति</b> (उच्च/नीच/स्वगृही)
          और <b>लाल किताब भाव-फल</b> दोनों एक ही दिशा में हों, वहाँ भविष्यवाणी की पुष्टि दोहरी मानी जाती है।
        </div>
        <div class="lk-card" style="padding:8px">
          <div class="lk-card-h" style="font-size:.85rem">जन्म कुंडली (D1)</div>
          <div id="lk-d1-mini" style="max-width:340px;margin:0 auto"></div>
        </div>
        <div class="lk-card">
          <div style="overflow-x:auto">
          <table class="lk-cmp">
            <thead><tr><th>ग्रह</th><th>D1 राशि-स्थिति</th><th>लाल किताब भाव-फल</th><th>निष्कर्ष</th></tr></thead>
            <tbody>
            <?php foreach ($lk['planets'] as $p):
                $dBad = $p['status'] === 'नीच';
                $dGood = in_array($p['status'], ['उच्च', 'स्वगृही'], true);
                $lBad = !empty($p['is_ashubh']);
                $lGood = $p['verdict'] === 'शुभ';
                if ($dBad && $lBad) { $concl = '<span style="color:#b91c1c;font-weight:700">❌ दोहरी अशुभ पुष्टि — उपाय प्राथमिकता से करें</span>'; }
                elseif (($dGood && $lGood) || (!empty($p['pukka']) && $lGood)) { $concl = '<span style="color:#166534;font-weight:700">✅ दोहरी शुभ पुष्टि</span>'; }
                elseif ($lBad || $dBad) { $concl = '<span style="color:#92400e">⚠ मिश्रित — एक प्रणाली में अशुभ</span>'; }
                else { $concl = '<span style="color:#475569">— सामान्य</span>'; }
            ?>
              <tr>
                <td><b><?= $h((string) $p['hi']) ?></b></td>
                <td><?= $h((string) $p['sign_hi']) ?> <?= $pill((string) $p['status'], 's') ?></td>
                <td><?= $h((string) $p['house_ord']) ?> भाव <?= $pill((string) $p['verdict'], 'v') ?><?= !empty($p['pukka']) ? ' <span class="lk-pill" style="background:#ede9fe;color:#5b21b6">पक्का घर</span>' : '' ?></td>
                <td><?= $concl ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>

      <!-- ===== SEARCH RESULTS (#6, filled by JS) ===== -->
      <div class="lk-view" data-lk="search">
        <h3 class="lk-h">🔍 खोज परिणाम</h3>
        <div id="lk-search-note" class="lk-txt" style="margin-bottom:9px;color:#64748b"></div>
        <div id="lk-search-results"></div>
      </div>

      <!-- ===== SUPT (sleeping planets) ===== -->
      <div class="lk-view" data-lk="supt">
        <h3 class="lk-h">सुप्त ग्रह (Sleeping Planets)</h3>
        <div class="lk-txt" style="margin-bottom:9px">लाल किताब में हर भाव में बैठा ग्रह तब तक "सुप्त" (सोया) रहता है जब तक उस भाव का <b>जगाने वाला ग्रह</b> कुंडली में उपस्थित न हो। नीचे प्रत्येक ग्रह की जागृत/सुप्त स्थिति है।</div>
        <?php foreach ($lk['supt'] as $s): ?>
          <div class="lk-card <?= $s['awake'] ? 'good' : 'bad' ?>">
            <div class="lk-card-h">
              <?= $h((string) $s['hi']) ?> — <?= $h((string) $s['house_ord']) ?> भाव में
              <?= $pill($s['awake'] ? 'जागृत' : 'सुप्त', 's') ?>
            </div>
            <div class="lk-sub"><b>जगाने वाला ग्रह:</b> <?= $h((string) $s['waker']) ?><?= $s['awake'] ? ' — कुंडली में उपस्थित (सक्रिय)' : ' — कुंडली में अनुपस्थित (सुप्त रहेगा)' ?></div>
            <?php if (trim((string) $s['jagega']) !== ''): ?><div class="lk-sub"><b>कब जागेगा:</b> <?= $h((string) $s['jagega']) ?><?= trim((string) $s['aayu']) !== '' ? ' (' . $h((string) $s['aayu']) . ')' : '' ?></div><?php endif; ?>
            <?php if (trim((string) $s['ashubh']) !== ''): ?><div class="lk-sub" style="color:#991b1b"><b>अशुभ वर्ष:</b> <?= $h((string) $s['ashubh']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== DRISHTI (house aspects) ===== -->
      <div class="lk-view" data-lk="drishti">
        <h3 class="lk-h">भाव दृष्टि (House Aspects)</h3>
        <div class="lk-txt" style="margin-bottom:9px">लाल किताब की भाव-दृष्टि के अनुसार जिन भावों में ग्रह बैठे हैं वे किन भावों को देखते हैं (दृष्टि), किनसे सहायता पाते हैं, और किनसे टकराव है — तथा उन भावों में कौन-से ग्रह हैं।</div>
        <?php foreach ($lk['drishti'] as $d):
            $fmtH = static function (array $hs) use ($h) { return $hs ? $h(implode(', ', $hs)) . ' भाव' : '—'; }; ?>
          <div class="lk-card">
            <div class="lk-card-h"><?= $h((string) $d['house_ord']) ?> भाव — <?= $h(implode(', ', $d['planets_hi'])) ?></div>
            <div class="lk-sub"><b>👁 दृष्टि (देखता है):</b> <?= $fmtH($d['drishti']) ?><?= $d['drishti_p'] ? ' → ' . $h(implode(', ', $d['drishti_p'])) : '' ?></div>
            <div class="lk-sub" style="color:#166534"><b>🤝 सहायक भाव:</b> <?= $fmtH($d['sahayak']) ?><?= $d['sahayak_p'] ? ' → ' . $h(implode(', ', $d['sahayak_p'])) : '' ?></div>
            <div class="lk-sub" style="color:#991b1b"><b>⚔ टकराव:</b> <?= $fmtH($d['takrav']) ?><?= $d['takrav_p'] ? ' → ' . $h(implode(', ', $d['takrav_p'])) : '' ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== REMEDY ===== -->
      <div class="lk-view" data-lk="remedy">
        <?php $rm = $lk['remedy']; ?>
        <h3 class="lk-h">उपाय / टोटके (Remedies)</h3>
        <?php if ($rm['yuti']): ?>
          <div class="lk-card">
            <div class="lk-card-h">ग्रह-युति उपाय (एक ही भाव में ग्रह)</div>
            <?php foreach ($rm['yuti'] as $y): ?>
              <div class="lk-sub"><b><?= $h((string) $y['yuti']) ?></b> — <?= $h((string) $y['house_ord']) ?> भाव<?php if (trim((string) $y['shart']) !== ''): ?> <span style="color:#94a3b8">(<?= $h((string) $y['shart']) ?>)</span><?php endif; ?></div>
              <?= $remBlock([(string) $y['upay']], 'युति उपाय') ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <div class="lk-card-h" style="margin:4px 0 6px">ग्रह-अनुसार सामान्य उपाय</div>
        <?php foreach ($rm['samanya'] as $s): ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.9rem"><?= $h((string) $s['hi']) ?></div>
            <?= $remBlock($s['upay'], $s['hi'] . ' — सामान्य उपाय') ?>
          </div>
        <?php endforeach; ?>
        <div class="lk-card-h" style="margin:10px 0 6px">पूजा / उपासना एवं दान (अशुभ ग्रह हेतु)</div>
        <?php foreach ($lk['planets'] as $p): if (trim((string) $p['upasana']) === '' && trim((string) $p['daan']) === '') { continue; } ?>
          <div class="lk-card">
            <div class="lk-card-h" style="font-size:.9rem"><?= $h((string) $p['hi']) ?></div>
            <?php if (trim((string) $p['upasana']) !== ''): ?><div class="lk-sub"><b>उपासना / पाठ:</b> <?= $h((string) $p['upasana']) ?></div><?php endif; ?>
            <?php if (trim((string) $p['daan']) !== ''): ?><div class="lk-sub"><b>दान:</b> <?= $h((string) $p['daan']) ?></div><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== RULES (do / don't) ===== -->
      <div class="lk-view" data-lk="rules">
        <?php $ru = $lk['rules']; ?>
        <h3 class="lk-h">उपाय के नियम व सावधानियाँ (Rules)</h3>
        <div class="lk-card">
          <div class="lk-card-h">उपाय के सामान्य नियम</div>
          <ul class="lk-rem-list" style="color:#334155">
            <?php foreach ($ru['upay_niyam'] as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php if ($ru['varjit']): ?>
          <div class="lk-card bad">
            <div class="lk-card-h">वर्जित उपाय (न करें)</div>
            <?php foreach ($ru['varjit'] as $v): ?>
              <div class="lk-sub"><b><?= $h((string) $v['sthiti']) ?>:</b> <?= $h((string) $v['varjit']) ?><?php if (trim((string) $v['parinam']) !== ''): ?> <span style="color:#991b1b">— <?= $h((string) $v['parinam']) ?></span><?php endif; ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($ru['daan_nishedh']): ?>
          <div class="lk-card">
            <div class="lk-card-h">दान-निषेध</div>
            <?php foreach ($ru['daan_nishedh'] as $d): ?>
              <div class="lk-sub"><b><?= $h((string) $d['sthiti']) ?>:</b> <?= $h((string) $d['varjit']) ?></div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($ru['paitrik_niyam']): ?>
          <div class="lk-card">
            <div class="lk-card-h">पैतृक ऋण — नियम</div>
            <ul class="lk-rem-list" style="color:#334155">
              <?php foreach ($ru['paitrik_niyam'] as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
      </div>

      <!-- ===== REFERENCE (chakras) ===== -->
      <div class="lk-view" data-lk="reference">
        <?php $rf = $lk['reference']; ?>
        <h3 class="lk-h">संदर्भ चक्र (Reference Charts)</h3>

        <div class="lk-card">
          <div class="lk-card-h">जीवन की अवस्थाएँ (Life Stages)</div>
          <?php foreach ($rf['avastha'] as $a): $cur = ($a['avastha'] === $rf['cur_stage']); ?>
            <div class="lk-sub" style="<?= $cur ? 'font-weight:700;color:#166534' : '' ?>">
              <?= $cur ? '➤ ' : '' ?><b><?= $h((string) $a['avastha']) ?>:</b> <?= $h((string) $a['bhav']) ?> · <?= $h((string) $a['aayu']) ?><?= $cur ? ' (आपकी वर्तमान अवस्था)' : '' ?>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="lk-card">
          <div class="lk-card-h">ग्रह वस्तुएँ एवं संबंधी (Significators)</div>
          <div class="lk-txt" style="margin-bottom:6px;color:#64748b">उपाय व दान में इन वस्तुओं का उपयोग होता है।</div>
          <?php foreach ($rf['planets'] as $p): ?>
            <div class="lk-sub" style="margin-bottom:5px"><b><?= $h((string) $p['hi']) ?>:</b> <?= $h((string) $p['vastu']) ?><?php if (trim((string) $p['sthapana']) !== ''): ?><br><span style="color:#9a3412"><b>स्थापना वस्तु:</b> <?= $h((string) $p['sthapana']) ?></span><?php endif; ?></div>
          <?php endforeach; ?>
        </div>

        <div class="lk-card">
          <div class="lk-card-h">ग्रह-राशि संबंध चक्र</div>
          <div style="overflow-x:auto">
          <table style="width:100%;border-collapse:collapse;font-size:.8rem">
            <thead><tr style="background:#f8fafc;text-align:left">
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">राशि</th>
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">स्वामी</th>
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">उच्च</th>
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">नीच</th>
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">कारक</th>
              <th style="padding:4px 6px;border-bottom:1px solid #e5e7eb">भाग्यकारी</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rf['grah_rashi'] as $g): ?>
              <tr>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['rashi']) ?></td>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['swami']) ?></td>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['uch']) ?></td>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['neech']) ?></td>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['karak']) ?></td>
                <td style="padding:4px 6px;border-bottom:1px solid #f1f5f9"><?= $h((string) $g['bhagya']) ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        </div>

        <div class="lk-card">
          <div class="lk-card-h">भाव-मास चक्र (House → Month)</div>
          <div class="lk-txt" style="line-height:2">
            <?php for ($i = 1; $i <= 12; $i++): ?>
              <span style="display:inline-block;min-width:120px"><b><?= $i ?>वाँ भाव:</b> <?= $h((string) ($rf['bhav_maas'][(string) $i] ?? '')) ?></span>
            <?php endfor; ?>
          </div>
        </div>

        <div class="lk-card">
          <div class="lk-card-h">भाव स्थापना उपाय (किसी भाव को बल देने हेतु)</div>
          <div class="lk-txt" style="margin-bottom:6px;color:#64748b">अभीष्ट भाव के स्वामी-ग्रह से सम्बन्धित वस्तुओं के साथ:</div>
          <?php for ($i = 1; $i <= 12; $i++): if (empty($rf['bhav_sthapana'][(string) $i])) { continue; } ?>
            <div class="lk-sub"><b><?= $i ?>वाँ भाव:</b> <?= $h((string) $rf['bhav_sthapana'][(string) $i]) ?></div>
          <?php endfor; ?>
        </div>

        <div class="lk-card">
          <div class="lk-card-h">अवयस्क कुंडली चक्र (बाल्यावस्था 1–12 वर्ष)</div>
          <div class="lk-txt" style="margin-bottom:6px;color:#64748b">छोटी आयु में भाग्य हेतु सहायक भाव व उसका स्वामी।</div>
          <?php foreach ($rf['avyask'] as $age => $a): $cur = ($rf['cur_avyask'] !== null && (string) $rf['age'] === (string) $age); ?>
            <div class="lk-sub" style="<?= $cur ? 'font-weight:700;color:#166534' : '' ?>"><?= $cur ? '➤ ' : '' ?><b><?= $h((string) $age) ?> वर्ष:</b> <?= $h((string) $a['bhav']) ?>वाँ भाव (<?= $h((string) $a['swami']) ?>)</div>
          <?php endforeach; ?>
        </div>
      </div>

    </div><!-- /lk-scroll -->
  </div>
</div>

<?php
// ===================================================================
// Hidden print documents (#5 पूर्ण रिपोर्ट, #7 उपाय checklist). Built
// server-side from ONLY the applicable findings; a button opens each in a
// print window (JS in calc-v2 buildLalKitab). All styling via lkr-* classes
// whose CSS lives in the print window.
// ===================================================================
$in = $in ?? [];
$native = trim((string) ($in['name'] ?? ''));
$birthLine = trim(
    ($native !== '' ? $native . ' · ' : '')
    . (string) ($in['date'] ?? '') . ' ' . (string) ($in['time'] ?? '')
    . (trim((string) ($in['place'] ?? '')) !== '' ? ' · ' . (string) $in['place'] : '')
);
$ashubhPlanets = array_values(array_filter($lk['planets'], static fn ($p) => !empty($p['is_ashubh'])));
$applYoga  = array_values(array_filter($lk['yoga'], static fn ($y) => !empty($y['applicable'])));
$possShrap = array_values(array_filter($lk['shrap'], static fn ($s) => !empty($s['possible'])));
$ssAct = $act['sadesati'] ?? null;
?>
<div id="lk-report" hidden>
  <div class="lkr-title">लाल किताब रिपोर्ट (Lal Kitab Report)</div>
  <div class="lkr-sub"><?= $h($birthLine) ?> · लग्न: <?= $h((string) $lk['lagna_hi']) ?> · चन्द्र राशि: <?= $h((string) $lk['moon_hi']) ?><?= $lk['age'] !== null ? ' · वर्तमान आयु: ' . (int) $lk['age'] . ' वर्ष' : '' ?></div>

  <?php if (!empty($act['maha']) || !empty($act['antar']) || $ssAct): ?>
  <div class="lkr-box lkr-hot">
    <b>🔥 अभी सक्रिय:</b>
    <?= !empty($act['maha']) ? 'महादशा — ' . $h((string) $act['maha']['hi']) . ' (' . $h((string) $act['maha']['house_ord']) . ' भाव, ' . $h((string) $act['maha']['verdict']) . ')' : '' ?>
    <?= !empty($act['antar']) ? ' · अंतर्दशा — ' . $h((string) $act['antar']['hi']) . ' (' . $h((string) $act['antar']['house_ord']) . ' भाव)' : '' ?>
    <?= $ssAct ? ' · ' . $h((string) $ssAct['label']) . ' चल रही है' : '' ?>
    <?= !empty($act['year_bad']) ? ' · इस आयु-वर्ष में अशुभ: ' . $h(implode(', ', $act['year_bad'])) : '' ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($lk['priority'])): ?>
  <div class="lkr-h">🎯 प्राथमिकता — सबसे पहले ये उपाय</div>
  <?php foreach ($lk['priority'] as $i => $pp): ?>
    <div class="lkr-box">
      <b><?= $i + 1 ?>. <?= $h((string) $pp['hi']) ?></b> (<?= $h((string) $pp['house_ord']) ?> भाव · स्कोर <?= (int) $pp['score'] ?>)
      <?php if ($pp['reasons']): ?><div class="lkr-why">कारण: <?= $h(implode(' · ', $pp['reasons'])) ?></div><?php endif; ?>
      <ul class="lkr-ul">
        <?php foreach ($pp['remedies'] as $r): ?><li><?= $h((string) $r) ?></li><?php endforeach; ?>
        <?php if ($pp['sheeghra'] !== ''): ?><li>⚡ शीघ्र उपाय: <?= $h((string) $pp['sheeghra']) ?></li><?php endif; ?>
      </ul>
      <?php if ($pp['var'] !== ''): ?><div class="lkr-why">प्रारंभ वार: <?= $h((string) $pp['var']) ?> · अवधि: 40–43 दिन निरंतर</div><?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($lk['yuti_dosha'])): ?>
  <div class="lkr-h">⚠ विशेष युति / ग्रहण दोष</div>
  <?php foreach ($lk['yuti_dosha'] as $D): ?>
    <div class="lkr-box lkr-bad">
      <b><?= $h((string) $D['name']) ?></b> — <?= $h((string) $D['pair_hi']) ?>, <?= $h((string) $D['house_ord']) ?> भाव<br>
      <?= $h((string) $D['desc']) ?>
      <ul class="lkr-ul"><?php foreach ($D['remedies'] as $r): ?><li><?= $h((string) $r) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($ashubhPlanets): ?>
  <div class="lkr-h">🪐 अशुभ ग्रह एवं उनके उपाय</div>
  <?php foreach ($ashubhPlanets as $p): ?>
    <div class="lkr-box lkr-bad">
      <b><?= $h((string) $p['hi']) ?></b> — <?= $h((string) $p['house_ord']) ?> भाव (<?= $h((string) $p['sign_hi']) ?>) · <?= $h((string) $p['status']) ?>
      <?php if (trim((string) $p['ashubh_lakshan']) !== ''): ?><div class="lkr-why">लक्षण: <?= $h((string) $p['ashubh_lakshan']) ?></div><?php endif; ?>
      <ul class="lkr-ul"><?php foreach (array_slice($p['remedies'], 0, 5) as $r): ?><li><?= $h((string) $r) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($applYoga): ?>
  <div class="lkr-h">✨ लागू योग</div>
  <?php foreach ($applYoga as $Y): ?>
    <div class="lkr-box"><b><?= $h((string) $Y['sthiti']) ?></b><br><?= $h((string) $Y['phal']) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($possShrap): ?>
  <div class="lkr-h">🧬 संभावित पैतृक ऋण</div>
  <?php foreach ($possShrap as $S): ?>
    <div class="lkr-box lkr-bad"><b><?= $h((string) $S['rin']) ?></b> — <?= $h((string) $S['pehchan']) ?><br>
      उपाय: <?= $h((string) $S['upay']) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php $mg = $lk['manglik']; if (!empty($mg['is'])): ?>
  <div class="lkr-h">🔴 मंगलीक दोष</div>
  <div class="lkr-box lkr-bad">मंगल <?= $h((string) $mg['mars_ord']) ?> भाव में — मंगलीक दोष है।
    <?php if (trim((string) $mg['upay']) !== ''): ?><br>उपाय: <?= $h((string) $mg['upay']) ?><?php endif; ?></div>
  <?php endif; ?>

  <?php if ($ssAct): ?>
  <div class="lkr-h">🪐 <?= $h((string) $ssAct['label']) ?> — उपाय</div>
  <?php $ssu = $lk['sadesati']['upay'] ?? []; $ph = (int) ($ssAct['phase'] ?? 0);
        $rows = ($ssAct['label'] === 'ढैय्या') ? ($lk['sadesati']['dhaiya_upay'] ?? []) : $ssu;
        $pick = ($ph >= 1 && isset($rows[$ph - 1])) ? [$rows[$ph - 1]] : $rows;
        foreach ($pick as $u): ?>
    <div class="lkr-box"><b><?= $h((string) ($u['charan'] ?? $u['dhaiya'] ?? '')) ?></b><br><?= $h((string) $u['upay']) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="lkr-h">📜 उपाय के नियम (सार)</div>
  <ul class="lkr-ul">
    <?php foreach (array_slice($lk['rules']['upay_niyam'] ?? [], 0, 6) as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?>
  </ul>
  <div class="lkr-foot">यह रिपोर्ट लाल किताब के शास्त्रीय नियमों पर आधारित है। उपाय श्रद्धा एवं नियम-पूर्वक 40–43 दिन निरंतर करें।</div>
</div>

<div id="lk-checklist" hidden>
  <div class="lkr-title">उपाय Checklist (40–43 दिन)</div>
  <div class="lkr-sub"><?= $h($birthLine) ?></div>
  <?php
    // planets to track: priority list first, else all ashubh planets
    $track = $lk['priority'];
    if ($track === []) {
        foreach ($ashubhPlanets as $p) {
            $track[] = ['hi' => $p['hi'], 'house_ord' => $p['house_ord'], 'score' => 0,
                'reasons' => [], 'remedies' => array_slice($p['remedies'], 0, 3),
                'sheeghra' => '', 'var' => $p['var']];
        }
    }
  ?>
  <?php foreach ($track as $pp): ?>
    <div class="lkr-box">
      <b><?= $h((string) $pp['hi']) ?></b> (<?= $h((string) $pp['house_ord']) ?> भाव)<?= $pp['var'] !== '' ? ' · प्रारंभ वार: <b>' . $h((string) $pp['var']) . '</b>' : '' ?>
      <ul class="lkr-ul">
        <?php foreach ($pp['remedies'] as $r): ?><li>☐ <?= $h((string) $r) ?></li><?php endforeach; ?>
      </ul>
      <div class="lkr-days">
        दिन:
        <?php for ($d = 1; $d <= 43; $d++): ?><span class="lkr-day"><?= $d ?></span><?php endfor; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="lkr-h">सावधानियाँ</div>
  <ul class="lkr-ul">
    <?php foreach (array_slice($lk['rules']['upay_niyam'] ?? [], 0, 4) as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?>
  </ul>
  <div class="lkr-foot">हर दिन उपाय पूर्ण होने पर उस दिन के अंक पर ✓ लगाएँ। बीच में नागा हो जाए तो पुनः प्रारंभ करें।</div>
</div>

<script>
  window.AB_LALKITAB = <?= json_encode($lkNorth, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
