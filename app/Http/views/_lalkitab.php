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
    <div class="flex items-center gap-2 mb-3">
      <select id="lk-select" class="l2-select" aria-label="लाल किताब श्रेणी चुनें" style="flex:1;min-width:0">
        <option value="overview">🔎 सामान्य परिचय / General Overview</option>
        <option value="planet">🪐 ग्रह फल / Planet Prediction</option>
        <option value="house">🏠 भाव (Bhav) फल / House Prediction</option>
        <option value="karak">🎯 कारक / Karak</option>
        <option value="yoga">✨ योग / Yog</option>
        <option value="shrap">🧬 श्राप / पैतृक ऋण / Shrap</option>
        <option value="sadesati">🪐 साढ़े साती / ढैय्या / Sadde Satti</option>
        <option value="manglik">🔴 मंगलीक दोष / Manglik</option>
        <option value="remedy">🛠 उपाय / Remedy</option>
      </select>
    </div>

    <div class="lk-scroll">

      <!-- ===== OVERVIEW ===== -->
      <div class="lk-view active" data-lk="overview">
        <h3 class="lk-h">सामान्य परिचय (Lal Kitab Overview)</h3>
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
              <?= $pill((string) $p['status'], 'status') ?><?= $pill((string) $p['verdict'], 'verdict') ?>
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
              <?= $pill((string) $p['status'], 'status') ?><?= $pill((string) $p['verdict'], 'verdict') ?>
            </div>
            <div class="lk-sub"><b>प्रकृति:</b> <?= $h((string) $p['prakriti']) ?> · <b>रंग:</b> <?= $h((string) $p['rang']) ?> · <b>कारक भाव:</b> <?= $h((string) $p['karak_bhav']) ?></div>
            <div class="lk-sub"><b>उच्च राशि:</b> <?= $h((string) $p['uch_rashi']) ?> · <b>नीच राशि:</b> <?= $h((string) $p['neech_rashi']) ?></div>
            <?php if (trim((string) $p['note']) !== ''): ?>
              <div class="lk-txt" style="margin-top:4px"><?= $h((string) $p['note']) ?></div>
            <?php endif; ?>
            <?php if (trim((string) $p['ashubh_lakshan']) !== ''): ?>
              <div class="lk-txt" style="margin-top:5px;color:#991b1b"><b>⚠ अशुभ लक्षण:</b> <?= $h((string) $p['ashubh_lakshan']) ?></div>
            <?php endif; ?>
            <?= $remBlock($p['remedies'], $p['hi'] . ' — भावगत उपाय') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== HOUSE PREDICTION ===== -->
      <div class="lk-view" data-lk="house">
        <h3 class="lk-h">भाव (Bhav) फल</h3>
        <?php foreach ($lk['houses'] as $H): ?>
          <div class="lk-card">
            <div class="lk-card-h"><?= (int) $H['house'] ?>. <?= $h((string) $H['house_ord']) ?> भाव — <?= $h((string) $H['rashi']) ?> (स्वामी <?= $h((string) $H['swami']) ?>)</div>
            <?php if ($H['planets_hi']): ?>
              <div class="lk-sub"><b>स्थित ग्रह:</b> <?= $h(implode(', ', $H['planets_hi'])) ?></div>
            <?php else: ?>
              <div class="lk-sub" style="color:#94a3b8">इस भाव में कोई ग्रह नहीं</div>
            <?php endif; ?>
            <?php if ($H['lord_house']): ?>
              <div class="lk-sub"><b>भाव स्वामी <?= $h((string) $H['lord_hi']) ?></b> — <?= $h(\AutoBusiness\Astro\LalKitab\LalKitabData::houseOrdinalHi((int) $H['lord_house'])) ?> भाव में बैठे हैं</div>
            <?php endif; ?>
            <div class="lk-txt" style="margin-top:4px"><b>विचार:</b> <?= $h((string) $H['vishay']) ?></div>
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
        <h3 class="lk-h">योग — भविष्यवाणी सूत्र</h3>
        <div class="lk-flt">
          <label><input type="checkbox" class="lk-onlyapp" data-scope="yoga" checked> केवल लागू योग दिखाएँ</label>
          <span style="color:#94a3b8">(सभी सूत्र देखने हेतु चेक हटाएँ)</span>
        </div>
        <?php foreach ($lk['yoga'] as $Y): ?>
          <div class="lk-card <?= $Y['applicable'] ? 'good' : '' ?>" data-app="<?= $Y['applicable'] ? '1' : '0' ?>">
            <div class="lk-card-h" style="font-size:.88rem">
              <?= $h((string) $Y['sthiti']) ?><?= $Y['applicable'] ? $pill('लागू', 'a') : '' ?>
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
      </div>

    </div><!-- /lk-scroll -->
  </div>
</div>

<script>
  window.AB_LALKITAB = <?= json_encode($lkNorth, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
