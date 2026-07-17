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
        <option value="ayu">⏳ आयु योग / Longevity</option>
        <option value="health">🩺 रोग / संतान / Health</option>
        <option value="bhavan">🏗 गृह निर्माण / Vastu</option>
        <option value="varsh">📅 वर्ष कुंडली ज्ञान / Annual</option>
        <option value="supt">😴 सुप्त ग्रह / Sleeping Planets</option>
        <option value="drishti">👁 भाव दृष्टि / House Aspects</option>
        <option value="remedy">🛠 उपाय / Remedy</option>
        <option value="rules">📜 उपाय नियम / Rules</option>
        <option value="reference">📚 संदर्भ चक्र / Reference</option>
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
            <?php if (trim((string) $p['mitra']) !== '' || trim((string) $p['shatru']) !== ''): ?>
              <div class="lk-sub"><b>मित्र:</b> <?= $h((string) $p['mitra']) ?> · <b>शत्रु:</b> <?= $h((string) $p['shatru']) ?><?= trim((string) $p['sam']) !== '' ? ' · <b>सम:</b> ' . $h((string) $p['sam']) : '' ?></div>
            <?php endif; ?>
            <?php if (trim((string) $p['note']) !== ''): ?>
              <div class="lk-txt" style="margin-top:4px"><?= $h((string) $p['note']) ?></div>
            <?php endif; ?>
            <?php if (trim((string) $p['ashubh_lakshan']) !== ''): ?>
              <div class="lk-txt" style="margin-top:5px;color:#991b1b"><b>⚠ अशुभ लक्षण:</b> <?= $h((string) $p['ashubh_lakshan']) ?></div>
            <?php endif; ?>
            <?= $remBlock($p['remedies'], $p['hi'] . ' — भावगत उपाय') ?>
            <?php if (trim((string) $p['sheeghra']) !== ''): ?>
              <div class="lk-sub" style="margin-top:5px"><b>⚡ शीघ्र उपाय:</b> <?= $h((string) $p['sheeghra']) ?></div>
            <?php endif; ?>
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

<script>
  window.AB_LALKITAB = <?= json_encode($lkNorth, JSON_UNESCAPED_UNICODE) ?>;
</script>
<?php endif; ?>
