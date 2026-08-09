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
$lkCal = [];   // remedy-calendar payload: planets that need उपाय, with their वार
if ($ok) {
    foreach ($lk['planets'] as $p) {
        $lkNorth['planets'][] = [
            'abbr'  => $abbr[$p['planet']] ?? $p['planet'],
            'sign'  => (int) $p['house'] - 1,   // house N ⇒ Aries+(N-1)
            'deg'   => 0,
            'retro' => !empty($p['retro']),
        ];
        if (!empty($p['need_remedy'])) {
            $rem = array_slice($p['remedies'], 0, 4);
            if (trim((string) ($p['sheeghra'] ?? '')) !== '') { $rem[] = '⚡ शीघ्र: ' . $p['sheeghra']; }
            $lkCal[] = [
                'hi'       => $p['hi'],
                'house_ord'=> $p['house_ord'],
                'var'      => $p['var'],          // e.g. "रविवार" / "गुरुवार (सायं)"
                'verdict'  => $p['verdict'],
                'remedies' => array_values($rem),
            ];
        }
    }
}

/** small helper: colour pill for a verdict / status. */
$pill = static function (string $txt, string $kind) use ($h): string {
    $map = [
        'शुभ' => 'background:#dcfce7;color:#166534', 'उच्च' => 'background:#dcfce7;color:#166534',
        'स्वगृही' => 'background:#dbeafe;color:#1e40af', 'सम' => 'background:#f1f5f9;color:#475569',
        'मध्यम' => 'background:#fef9c3;color:#854d0e', 'मिश्रित' => 'background:#fef9c3;color:#854d0e',
        'शुभ फल' => 'background:#dcfce7;color:#166534',
        'अशुभ फल' => 'background:#fee2e2;color:#991b1b',
        'अशुभ' => 'background:#fee2e2;color:#991b1b', 'नीच' => 'background:#fee2e2;color:#991b1b',
    ];
    $st = $map[$txt] ?? 'background:#f1f5f9;color:#475569';
    return '<span class="lk-pill" style="' . $st . '">' . $h($txt) . '</span>';
};
/**
 * एक ग्रह का निचोड़ — "अभी क्या मानें"।
 *
 * विस्तृत पन्नों पर तथ्य पहले भी सब थे; कमी यह थी कि कोई उन्हें मिलाकर एक वाक्य
 * नहीं कहता था। इसलिए एक ही कार्ड "मृत — कारकत्व अनुपस्थित मानें" और "लाभ
 * मिलेगा" दोनों कह देता था, और पढ़ने वाला यही समझता था कि गणना भरोसे लायक़ नहीं।
 * यह पट्टी हर कार्ड के सिरे पर वही मेल दिखाती है, और उपाय वही बताती है जो निचोड़
 * ने तय किया — ताकि दोनों पन्ने कभी दो बातें न कहें।
 */
$briefBox = static function (?array $b) use ($h): string {
    if (!is_array($b) || trim((string) ($b['line'] ?? '')) === '') { return ''; }
    $tone = [
        'रुका हुआ' => ['#f8fafc', '#cbd5e1', '#334155', '⏸'],
        'सोया'     => ['#f8fafc', '#cbd5e1', '#334155', '😴'],
        'जाग चुका' => ['#eff6ff', '#bfdbfe', '#1e3a8a', '🌅'],
        'चालू'     => ['#f0fdf4', '#bbf7d0', '#14532d', '⚡'],
    ][$b['state']] ?? ['#f8fafc', '#e2e8f0', '#334155', '•'];
    if (($b['state'] ?? '') === 'चालू' && ($b['verdict'] ?? '') === 'अशुभ') {
        $tone = ['#fef2f2', '#fecaca', '#7f1d1d', '⚠️'];
    }
    [$bg, $bd, $fg, $ic] = $tone;
    $o  = '<div style="background:' . $bg . ';border:1px solid ' . $bd . ';border-radius:10px;padding:9px 12px;margin:2px 0 9px">';
    $o .= '<div style="font-weight:800;font-size:.8rem;color:' . $fg . ';margin-bottom:3px">'
        . $ic . ' अभी क्या मानें'
        . ($b['in_dasha'] ? ' <span class="lk-pill" style="background:#dc2626;color:#fff">🔥 इसी की दशा चल रही है</span>' : '')
        . '</div>';
    $o .= '<div style="font-size:.85rem;line-height:1.65;color:' . $fg . '">' . $h((string) $b['line']) . '</div>';
    if (trim((string) ($b['kshamata_hi'] ?? '')) !== '') {
        $o .= '<div style="font-size:.8rem;margin-top:3px;color:' . $fg . '">💪 ' . $h((string) $b['kshamata_hi']) . '</div>';
    }
    $kaun = (array) ($b['kaun'] ?? []);
    if ($kaun !== []) {
        $bits = [];
        if (!empty($kaun['sulane_wala'])) { $bits[] = 'सुलाने वाला: <b>' . $h((string) $kaun['sulane_wala']) . '</b>'; }
        if (!empty($kaun['chaabi']))      { $bits[] = 'जगाने की चाबी: <b>' . $h((string) $kaun['chaabi']) . '</b>'; }
        if (!empty($kaun['shart']))       { $bits[] = 'शर्त: ' . $h((string) $kaun['shart']); }
        $o .= '<div style="font-size:.8rem;margin-top:3px;color:' . $fg . '">🔑 ' . implode(' · ', $bits) . '</div>';
    }
    if (trim((string) ($b['qualifier'] ?? '')) !== '') {
        $o .= '<div style="font-size:.78rem;margin-top:3px;color:#92400e">⚖ ' . $h((string) $b['qualifier'])
            . ' <span style="color:#94a3b8">(दोनों बातें सच हैं — औसत नहीं निकाला गया)</span></div>';
    }
    $a = (array) ($b['action'] ?? []);
    $st = (string) ($a['status'] ?? '');
    $sty = ['जारी' => ['#166534', '🛠'], 'कतार में' => ['#92400e', '🔒'],
            'रोका गया' => ['#7f1d1d', '🚫'], 'ज़रूरत नहीं' => ['#475569', '✔']][$st] ?? ['#475569', '•'];
    $txt = trim((string) ($a['text'] ?? ''));
    $why = trim((string) ($a['why'] ?? ''));
    $dir = trim((string) ($a['dir'] ?? ''));
    $tgt = trim((string) ($a['target'] ?? ''));
    $o .= '<div style="margin-top:6px;padding-top:5px;border-top:1px dashed ' . $bd . ';font-size:.82rem;color:' . $sty[0] . '">';
    $o .= '<b>' . $sty[1] . ' अब क्या करें — ' . $h($st) . '</b>';
    if ($dir !== '') { $o .= ' <span class="lk-pill" style="background:#fff;color:' . $sty[0] . ';border:1px solid ' . $bd . '">दिशा: ' . $h($dir) . '</span>'; }
    if ($tgt !== '' && $tgt !== (string) $b['hi']) { $o .= ' <span class="lk-pill" style="background:#fff;color:' . $sty[0] . ';border:1px solid ' . $bd . '">निशाना: ' . $h($tgt) . '</span>'; }
    if ($txt !== '') { $o .= '<div style="margin-top:2px">' . $h($txt) . '</div>'; }
    if ($why !== '') { $o .= '<div style="margin-top:2px;font-size:.78rem">' . $h($why) . '</div>'; }
    $o .= '</div></div>';
    return $o;
};
/** एक भाव का निचोड़ — दर्जा, चेतावनी और करने योग्य काम, एक जगह। */
$houseBriefBox = static function (?array $b) use ($h): string {
    if (!is_array($b) || trim((string) ($b['line'] ?? '')) === '') { return ''; }
    $warn = (array) ($b['warn'] ?? []);
    $bad  = $warn !== [] || in_array((string) $b['verdict'], ['मंदा', 'अशुभ', 'अस्थिर'], true);
    [$bg, $bd, $fg] = $bad ? ['#fffbeb', '#fde68a', '#78350f']
        : (((string) $b['verdict']) === 'सुप्त' ? ['#f8fafc', '#cbd5e1', '#334155'] : ['#f0fdf4', '#bbf7d0', '#14532d']);
    $o  = '<div style="background:' . $bg . ';border:1px solid ' . $bd . ';border-radius:10px;padding:9px 12px;margin:2px 0 9px">';
    $o .= '<div style="font-weight:800;font-size:.8rem;color:' . $fg . ';margin-bottom:3px">🏠 अभी क्या मानें</div>';
    $o .= '<div style="font-size:.85rem;line-height:1.65;color:' . $fg . '">' . $h((string) $b['line']) . '</div>';
    foreach ($warn as $w) {
        $o .= '<div style="font-size:.81rem;margin-top:3px;color:#991b1b">⚠️ ' . $h((string) $w) . '</div>';
    }
    foreach ((array) ($b['roles'] ?? []) as $r) {
        $o .= '<div style="font-size:.78rem;margin-top:2px;color:#475569">👑 ' . $h((string) $r) . '</div>';
    }
    $a  = (array) ($b['action'] ?? []);
    $st = (string) ($a['status'] ?? '');
    if ($st !== '') {
        $o .= '<div style="margin-top:6px;padding-top:5px;border-top:1px dashed ' . $bd . ';font-size:.82rem;color:'
            . ($st === 'जारी' ? '#166534' : '#92400e') . '">'
            . '<b>' . ($st === 'जारी' ? '🛠' : '🔒') . ' अब क्या करें — ' . $h($st) . '</b>'
            . ($a['planet'] !== '' ? ' <span class="lk-pill" style="background:#fff;border:1px solid ' . $bd . '">' . $h((string) $a['planet']) . ' के रास्ते</span>' : '')
            . (trim((string) $a['text']) !== '' ? '<div style="margin-top:2px">' . $h((string) $a['text']) . '</div>' : '')
            . '</div>';
    }
    return $o . '</div>';
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
  #sec-lalkitab .lk-dd{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px}
  @media(max-width:560px){#sec-lalkitab .lk-dd{grid-template-columns:1fr}}
  /* दो-ढंग टॉगल: सरल (ग्राहक) बनाम विस्तृत (ज्योतिषी) */
  #sec-lalkitab .lk-moderow{display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:9px}
  #sec-lalkitab .lk-modebar{display:flex;gap:6px;flex:1;min-width:230px;background:#f1f5f9;padding:4px;border-radius:10px}
  #sec-lalkitab .lk-mode{flex:1;border:0;background:transparent;border-radius:8px;padding:6px 10px;cursor:pointer;
    font-size:.85rem;font-weight:700;color:#475569;line-height:1.3}
  #sec-lalkitab .lk-mode span{font-weight:400;font-size:.76rem;color:#94a3b8}
  #sec-lalkitab .lk-mode.active{background:#fff;color:#0f172a;box-shadow:0 1px 3px rgba(0,0,0,.12)}
  #sec-lalkitab .lk-mode.active span{color:#64748b}
  #sec-lalkitab.lk-simple #lk-detail-bar{display:none}
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
  #sec-lalkitab .lk-note{font-size:.82rem;color:#334155;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:8px 11px;line-height:1.7}
  #sec-lalkitab .lk-cmp{width:100%;border-collapse:collapse;font-size:.8rem}
  #sec-lalkitab .lk-cmp th{background:#f8fafc;text-align:left;padding:5px 7px;border-bottom:1px solid #e5e7eb}
  #sec-lalkitab .lk-cmp td{padding:5px 7px;border-bottom:1px solid #f1f5f9;vertical-align:top}
  #sec-lalkitab .lk-anlz{border:1px solid #e2e8f0;border-radius:8px;background:#f8fafc;padding:7px 10px;margin:6px 0;font-size:.8rem;color:#334155}
  #sec-lalkitab .lk-anlz-row{display:flex;gap:8px;align-items:flex-start;padding:2px 0;flex-wrap:wrap}
  #sec-lalkitab .lk-anlz-k{flex:none;min-width:76px;font-weight:700;color:#64748b;font-size:.74rem;padding-top:1px}
  #sec-lalkitab .lk-anlz-final{border-top:1px dashed #cbd5e1;margin-top:3px;padding-top:5px}
  /* Full-width mode for the Varsh Kundali tool — span the whole section like the
     Vedic Varshaphal page instead of the narrow right prediction column. */
  #sec-lalkitab.lk-wide .lk-sec-grid{grid-template-columns:1fr}
  #sec-lalkitab.lk-wide #lk-teva-col{display:none}
  /* Varsh inner layout: annual chart (left) + prediction (right), like Varshaphal. */
  #sec-lalkitab .lkv-main{display:grid;grid-template-columns:1fr;gap:14px;align-items:start;margin-top:6px}
  @media(min-width:980px){#sec-lalkitab.lk-wide .lkv-main{grid-template-columns:minmax(300px,360px) minmax(0,1fr)}}
  #sec-lalkitab .lkv-main>#lkv-body{min-width:0}
</style>

<?php if (!$ok): ?>
  <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">
    लाल किताब गणना उपलब्ध नहीं है<?= !empty($lk['error']) ? ' — ' . $h((string) $lk['error']) : '' ?>।
    पहले जन्म विवरण भरकर <b>Calculate</b> करें।
  </div>
<?php else: ?>

<?php /* ══════ 👪 पारिवारिक स्थिति — लाल किताब पन्ने के सिरे पर ══════
     यह जन्म-फ़ॉर्म से यहाँ लाया गया है, क्योंकि इसका काम सिर्फ़ लाल किताब से है
     (वैदिक पक्ष इसे नहीं माँगता) — और उपाय पढ़ने से *पहले* भरा जाना चाहिए।
     कई लाल किताब उपाय पिता/माता के जीवित होने या न होने पर अपना असर उलट देते
     हैं; इसलिए हालत अज्ञात हो तो वैसा उपाय रोक दिया जाता है, अंदाज़ा नहीं
     लगाया जाता। बदलते ही पन्ना दोबारा गणना करता है।
     मौजूदा सब पैरामीटर hidden में साथ जाते हैं ताकि कुंडली वही रहे। */ ?>
<?php $LKNF = \AutoBusiness\Astro\LalKitab\LalKitabProcess::NATIVE_FIELDS; ?>
<form method="get" action="" id="lk-native-form"
      style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:9px 13px;margin-bottom:12px">
  <?php foreach ($_GET as $gk => $gv):
        if (isset($LKNF[$gk]) || in_array($gk, ['sec', 'lkview'], true) || is_array($gv)) { continue; } ?>
    <input type="hidden" name="<?= $h((string) $gk) ?>" value="<?= $h((string) $gv) ?>">
  <?php endforeach; ?>
  <?php /* पन्ना दोबारा लोड होने पर सेक्शन JS से चुना जाता है — इसलिए लौटने का पता
           साथ भेजते हैं, वरना जवाब भरते ही उपयोगकर्ता जन्म-कुंडली पर जा गिरता। */ ?>
  <input type="hidden" name="sec" value="lalkitab">
  <input type="hidden" name="lkview" id="lk-native-view" value="<?= $h((string) ($_GET['lkview'] ?? 'nichod')) ?>">
  <div style="display:flex;align-items:center;gap:9px;flex-wrap:wrap">
    <div style="font-weight:800;font-size:.87rem;color:#78350f;white-space:nowrap">👪 उपाय हेतु आपकी स्थिति</div>
    <?php $nDone = 0; foreach ($LKNF as $nk => $nf): $nCur = (string) ($_GET[$nk] ?? '');
          if ($nCur !== '') { $nDone++; } ?>
      <label style="font-size:.8rem;color:#78350f"><?= $h($nf['q']) ?>
        <select name="<?= $h($nk) ?>" onchange="lkNativeGo(this)"
                style="border:1px solid #fcd34d;border-radius:6px;padding:2px 6px;font-size:.8rem">
          <option value="">— अज्ञात —</option>
          <?php foreach ($nf['values'] as $nv): ?>
            <option value="<?= $h($nv) ?>" <?= $nCur === $nv ? 'selected' : '' ?>><?= $h($nv) ?></option>
          <?php endforeach; ?>
        </select></label>
    <?php endforeach; ?>
    <span style="font-size:.76rem;color:<?= $nDone === count($LKNF) ? '#166534' : '#92400e' ?>">
      <?php if ($nDone === count($LKNF)): ?>
        ✅ उपाय आपकी हालत के अनुसार छाँटे जा रहे हैं
      <?php else: ?>
        <?= (int) $nDone ?>/<?= count($LKNF) ?> भरे — बाक़ी भरने पर और उपाय खुलेंगे। खाली छोड़ने पर भी रिपोर्ट पूरी बनती है।
      <?php endif; ?>
    </span>
  </div>
</form>
<script>
/* जो पढ़ाई अभी खुली है वही लौटने पर खुले — इसलिए मौजूदा दृश्य साथ भेजते हैं। */
function lkNativeGo(el) {
  var sel = document.getElementById('lk-select');
  var box = document.getElementById('lk-native-view');
  if (sel && box && sel.value) { box.value = sel.value; }
  el.form.submit();
}
</script>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start lk-sec-grid">

  <!-- LEFT: Lal Kitab (fixed-Aries) chart -->
  <div id="lk-teva-col" class="bg-white rounded-lg shadow p-3 flex flex-col">
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
    <?php if (!empty($act['dasha']) || !empty($act['sadesati']) || !empty($act['year_eff']) || !empty($act['year_bad'])): ?>
    <!-- 🔥 what is live right now: 35-year Lal Kitab dasha, sade-sati, this-year planets -->
    <div class="lk-active">
      <b>🔥 अभी सक्रिय:</b>
      <?php if (!empty($act['dasha'])): ?>
        लाल किताब दशा (35-साला चक्र) — <b><?= $h((string) $act['dasha']['hi']) ?></b>
        (<?= $h((string) $act['dasha']['house_ord']) ?> भाव · आयु <?= (int) ($act['dasha']['from'] ?? 0) ?>–<?= (int) ($act['dasha']['to'] ?? 0) ?>)<?= $pill((string) $act['dasha']['verdict'], 'v') ?>
        <?php /* अंत-तिथि के साथ कठिन दौर सँभाला जा सकता है; बिना अंत के वही दौर डर बन जाता है। */ ?>
        <?php if (trim((string) ($act['dasha']['ends_hi'] ?? '')) !== ''): ?>
          <span style="font-weight:700;color:#166534"> — <?= $h((string) $act['dasha']['ends_hi']) ?></span>
        <?php endif; ?>
        <?php if (($act['dasha']['period_type'] ?? '') === 'ठहराव'): ?>
          <div style="font-size:.8rem;color:#475569;margin-top:2px">⏸ <?= $h((string) $act['dasha']['phal_hi']) ?></div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (!empty($act['sadesati'])): ?>
        · <b><?= $h((string) $act['sadesati']['label']) ?> चल रही है</b><?= $act['sadesati']['phase'] ? ' (चरण ' . (int) $act['sadesati']['phase'] . ')' : '' ?>
        <i style="font-size:.75rem;color:#6d28d9">(<?= $h((string) ($act['sadesati']['source_label'] ?? 'वैदिक आधार पर')) ?>)</i>
      <?php endif; ?>
      <?php if (!empty($act['year_eff']) || !empty($act['year_bad'])): ?>
        <br><b>इस आयु-वर्ष (<?= (int) ($act['age'] ?? 0) ?>) में:</b>
        <?php if (!empty($act['year_eff'])): ?>प्रभावी ग्रह — <?= $h(implode(', ', $act['year_eff'])) ?><?php endif; ?>
        <?php if (!empty($act['year_bad'])): ?> · <span style="color:#b91c1c">अशुभ वर्ष — <?= $h(implode(', ', $act['year_bad'])) ?></span><?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php /* ══════ दो दस्तावेज़, एक पन्ना नहीं ══════
         एक ही पन्ना ग्राहक और ज्योतिषी दोनों का काम नहीं कर सकता: ग्राहक को
         निचोड़ चाहिए, ज्योतिषी को कच्चा माल। इसलिए ऊपर टॉगल है। सरल मोड में
         सिर्फ़ निचोड़ खुलता है और चुनाव-पट्टी छिप जाती है; विस्तृत मोड में सारे
         बाईस विभाग आठ समूहों में मिलते हैं (कुछ हटाया नहीं गया, समेटा गया है)।
         चुनाव localStorage में याद रहता है। */ ?>
    <div class="lk-moderow">
      <div class="lk-modebar" role="group" aria-label="पढ़ने का ढंग">
        <button type="button" class="lk-mode" data-lkmode="simple">📋 सरल रिपोर्ट <span>(ग्राहक)</span></button>
        <button type="button" class="lk-mode" data-lkmode="detail">🔬 विस्तृत जाँच <span>(ज्योतिषी)</span></button>
      </div>
      <?php /* छपाई दोनों ढंगों में चाहिए — सरल रिपोर्ट का असली रूप काग़ज़ ही है,
               इसलिए ये दो बटन समेटी जाने वाली पट्टी से बाहर हैं। */ ?>
      <button type="button" id="lk-print-report" class="lk-btn">🖨 पूर्ण रिपोर्ट</button>
      <button type="button" id="lk-print-checklist" class="lk-btn">📋 उपाय Checklist</button>
    </div>

    <div id="lk-detail-bar">
    <?php /* ══════ विवादित नियम — ज्योतिषी का फ़ैसला ══════
         लाल किताब की कुछ बातों पर घराने सहमत नहीं हैं (सोई दृष्टि कितनी बचे,
         मृत अवस्था मानें या नहीं…)। चुपचाप एक पक्ष चुनकर उसे नियम की तरह छाप
         देना सबसे बड़ी बेईमानी होगी — पढ़ने वाले को पता ही नहीं चलेगा कि उसका फल
         किस मत पर बना। इसलिए यहाँ खुला चुनाव है, चुनाव URL में जाता है (वही लिंक
         दोबारा खोलने पर वही फल), और चुनी हुई सेटिंग रिपोर्ट के नीचे छपती है।
         यह सवाल ग्राहक से नहीं पूछे जाते — इसीलिए यह पट्टी सिर्फ़ विस्तृत ढंग में
         दिखती है, और बंद रहती है। */ ?>
    <?php $LKSF = \AutoBusiness\Astro\LalKitab\LalKitabSettings::FIELDS;
          $LKSV = \AutoBusiness\Astro\LalKitab\LalKitabSettings::all();
          $LKSD = \AutoBusiness\Astro\LalKitab\LalKitabSettings::allDefault(); ?>
    <details id="lk-niyam" style="border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;margin-bottom:10px" <?= $LKSD ? '' : 'open' ?>>
      <summary style="cursor:pointer;padding:7px 12px;font-size:.83rem;font-weight:700;color:#334155">
        ⚖ विवादित नियमों की सेटिंग
        <span style="font-weight:500;font-size:.76rem;color:<?= $LKSD ? '#64748b' : '#b45309' ?>">
          — <?= $LKSD ? 'सब spec के सुझाए रूप पर' : 'बदली हुई — फल इन्हीं पर बना है' ?>
        </span>
      </summary>
      <form method="get" action="" id="lk-niyam-form" style="padding:2px 12px 11px">
        <?php foreach ($_GET as $gk => $gv):
              if (isset($LKSF[$gk]) || in_array($gk, ['sec', 'lkview'], true) || is_array($gv)) { continue; } ?>
          <input type="hidden" name="<?= $h((string) $gk) ?>" value="<?= $h((string) $gv) ?>">
        <?php endforeach; ?>
        <input type="hidden" name="sec" value="lalkitab">
        <input type="hidden" name="lkview" id="lk-niyam-view" value="<?= $h((string) ($_GET['lkview'] ?? 'nichod')) ?>">
        <div style="display:grid;gap:8px;grid-template-columns:repeat(auto-fit,minmax(275px,1fr))">
          <?php foreach ($LKSF as $sk => $sf): ?>
            <label style="font-size:.79rem;color:#475569;display:block">
              <b style="color:#334155"><?= $h($sf['q']) ?></b>
              <select name="<?= $h($sk) ?>" onchange="lkNiyamGo(this)"
                      style="width:100%;border:1px solid #cbd5e1;border-radius:6px;padding:3px 6px;font-size:.79rem;background:#fff">
                <?php foreach ($sf['options'] as $ov => $ol): ?>
                  <option value="<?= $h($ov) ?>" <?= ($LKSV[$sk] ?? '') === $ov ? 'selected' : '' ?>><?= $h($ol) ?></option>
                <?php endforeach; ?>
              </select>
              <span style="font-size:.73rem;color:#94a3b8"><?= $h($sf['help']) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <?php if (!$LKSD): ?>
          <div style="margin-top:8px;font-size:.78rem">
            <a href="?<?= $h(http_build_query(array_diff_key(array_filter($_GET, 'is_string'), $LKSF) + ['sec' => 'lalkitab'])) ?>"
               style="color:#b45309;font-weight:700">↺ सब वापस spec के सुझाए रूप पर</a>
          </div>
        <?php endif; ?>
      </form>
    </details>
    <script>
    /* जो पढ़ाई खुली है वही लौटने पर खुले। */
    function lkNiyamGo(el) {
      var sel = document.getElementById('lk-select');
      var box = document.getElementById('lk-niyam-view');
      if (sel && box && sel.value) { box.value = sel.value; }
      el.form.submit();
    }
    </script>
    <div class="flex items-center gap-2 mb-2" style="flex-wrap:wrap">
      <select id="lk-select" class="l2-select" aria-label="लाल किताब श्रेणी चुनें" style="flex:1;min-width:190px">
        <optgroup label="1 · 🔎 निचोड़ व प्राथमिकता">
          <option value="nichod">📋 निचोड़ — मुख्य बातें व उपाय (सबसे पहले यही पढ़ें)</option>
          <option value="overview">🔎 सामान्य परिचय / General Overview</option>
        </optgroup>
        <optgroup label="2 · 🪐 ग्रह फल">
          <option value="planet">🪐 ग्रह फल / Planet Prediction</option>
          <option value="supt">😴 सोया / अपंग ग्रह / Soya &amp; Impaired</option>
          <option value="inter">🔗 ग्रह अंतर्संबंध / Planet Inter-effects</option>
        </optgroup>
        <optgroup label="3 · 🏠 भाव फल">
          <option value="house">🏠 भाव (Bhav) फल / House Prediction</option>
          <option value="karak">🎯 कारक / Karak</option>
        </optgroup>
        <optgroup label="4 · 🔗 संबंध-जाल">
          <option value="yoga">✨ योग · मसनूई · टक्करें · बुनियाद / Yog &amp; Collisions</option>
          <option value="drishti">👁 दृष्टि व टक्कर (एकतरफ़ा) / Aspects &amp; Takkar</option>
        </optgroup>
        <optgroup label="5 · 🧬 ऋण · श्राप · दोष">
          <option value="shrap">🧬 श्राप / पैतृक ऋण / Shrap</option>
          <option value="manglik">🔴 मंगलीक दोष / Manglik</option>
        </optgroup>
        <optgroup label="6 · 📅 समय">
          <option value="agecycle">🕰️ आयु-दशा टाइमलाइन / Age Timeline (Dasha)</option>
          <option value="varsh">📅 वर्ष कुंडली / Varsh Kundali (Annual)</option>
          <option value="sadesati">🪐 साढ़े साती / ढैय्या / Sadde Satti</option>
        </optgroup>
        <optgroup label="7 · 🛠 उपाय">
          <option value="remedy">🛠 उपाय / Remedy</option>
          <option value="calendar">🗓 उपाय-कैलेंडर / Remedy Calendar</option>
          <option value="rules">⛔ वर्जित उपाय व नियम / Rules &amp; Don'ts</option>
        </optgroup>
        <optgroup label="8 · 📚 संदर्भ व तुलना">
          <option value="health">🩺 रोग / संतान / Health</option>
          <option value="ayu">⏳ आयु योग / Longevity</option>
          <option value="bhavan">🏗 गृह निर्माण / Vastu</option>
          <option value="compare">⚖ D1 ↔ लाल किताब तुलना / Compare</option>
          <option value="reference">📚 संदर्भ चक्र / Reference</option>
        </optgroup>
        <option value="search" hidden>🔍 खोज परिणाम / Search Results</option>
      </select>
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
    </div><!-- /#lk-detail-bar -->

    <div class="lk-scroll">

      <!-- ===== OVERVIEW ===== -->
      <?php /* ══════ 📋 निचोड़ — ग्राहक का पन्ना (चरण 8-10) ══════
           बाक़ी सब अनुभाग ज्योतिषी के लिए हैं; यह एक पन्ना उस आदमी के लिए है
           जिसकी कुंडली है। क्रम तय है और बदला नहीं जाता: पहले मिज़ाज, फिर
           ताक़त, फिर सावधानियाँ (हर एक अपने उपाय के साथ), फिर अभी का समय
           अंत-तिथि सहित, फिर उपाय, फिर करें/न-करें, फिर सीमा।
           (PHP टिप्पणी — यह ब्राउज़र तक नहीं जाती।) */ ?>
      <div class="lk-view active" data-lk="nichod">
        <?php $PR = $lk['process'] ?? null; if (!is_array($PR) || empty($PR['ok'])): ?>
          <h3 class="lk-h">📋 निचोड़</h3>
          <div class="lk-empty">निचोड़ अभी उपलब्ध नहीं<?= !empty($PR['error']) ? ' — ' . $h((string) $PR['error']) : '' ?>।
            नीचे के अनुभागों में पूरा विश्लेषण मौजूद है।</div>
        <?php else: $C = $PR['client']; ?>
          <h3 class="lk-h">📋 निचोड़ — मुख्य बातें व उपाय</h3>
          <div style="font-size:.82rem;color:#475569;margin-bottom:10px"><?= $h((string) $C['intro']) ?></div>

          <!-- 1. कुंडली का मिज़ाज -->
          <div style="border:1px solid #c7d2fe;background:#eef2ff;border-radius:10px;padding:10px 13px;margin-bottom:11px">
            <div style="font-weight:800;font-size:.9rem;margin-bottom:3px;color:#3730a3">🧭 कुंडली का मिज़ाज — <?= $h((string) $PR['temperament']['primary']) ?></div>
            <div style="font-size:.85rem;line-height:1.65;color:#1e1b4b"><?= $h((string) $C['mizaj']) ?></div>
          </div>

          <!-- 2. मज़बूती — हमेशा पहले -->
          <?php if (!empty($C['strengths'])): ?>
          <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;padding:10px 13px;margin-bottom:11px">
            <div style="font-weight:800;font-size:.9rem;margin-bottom:4px;color:#14532d">💪 आपकी मज़बूती</div>
            <?php foreach ($C['strengths'] as $s): ?>
              <?php /* data-src = यह बात किस तकनीकी नतीजे से आई। ग्राहक इसे नहीं
                       देखता, पर हर वाक्य का स्रोत होना ही चाहिए — बिना स्रोत का
                       वाक्य ज्योतिष नहीं, भराव है (Y1)। */ ?>
              <div data-lk-point="ताक़त" data-src="<?= $h((string) ($s['source'] ?? '')) ?>"
                   style="font-size:.85rem;line-height:1.65;color:#14532d;margin-bottom:4px">
                <b><?= $h((string) $s['title']) ?></b><br><?= $h((string) $s['text']) ?>
              </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <!-- 3. ध्यान देने की बातें — हर एक अपने उपाय के साथ, उसी जगह -->
          <?php if (!empty($C['issues'])): ?>
          <div style="font-weight:800;font-size:.9rem;margin:0 0 6px;color:#7f1d1d">⚠️ ध्यान देने की बातें</div>
          <?php foreach ($C['issues'] as $it): ?>
            <div data-lk-point="कठिनाई" data-src="<?= $h((string) ($it['source'] ?? '')) ?>"
                 style="border:1px solid #fecaca;background:#fef2f2;border-radius:10px;padding:9px 12px;margin-bottom:8px">
              <div style="font-weight:700;font-size:.86rem;color:#7f1d1d"><?= $h((string) $it['title']) ?></div>
              <div style="font-size:.84rem;line-height:1.65;color:#7f1d1d;margin-top:2px"><?= $h((string) $it['text']) ?></div>
              <?php if (trim((string) ($it['upay_inline'] ?? '')) !== ''): ?>
                <div style="margin-top:6px;padding:6px 9px;background:#fff;border-radius:7px;border:1px dashed #fca5a5;font-size:.83rem;color:#166534">
                  <b>🛠 उपाय:</b> <?= $h((string) $it['upay_inline']) ?>
                </div>
              <?php elseif (trim((string) ($it['hold_reason'] ?? '')) !== ''): ?>
                <?php /* उपाय के बिना कठिनाई कभी अकेली नहीं छूटती — या उपाय, या
                         उसके रुकने की वजह। ख़ाली जगह पढ़ने वाले को बेबस छोड़ती है। */ ?>
                <div style="margin-top:6px;padding:6px 9px;background:#fff;border-radius:7px;border:1px dashed #fcd34d;font-size:.83rem;color:#92400e">
                  🔒 <?= $h((string) $it['hold_reason']) ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php endif; ?>

          <!-- 4. अभी का समय — अंत-तिथि के साथ -->
          <?php if (trim((string) $C['samay']) !== ''): ?>
          <div style="border:1px solid #fde68a;background:#fffbeb;border-radius:10px;padding:10px 13px;margin-bottom:11px">
            <div style="font-weight:800;font-size:.9rem;margin-bottom:3px;color:#713f12">📅 अभी का समय</div>
            <div style="font-size:.85rem;line-height:1.65;color:#713f12"><?= $h((string) $C['samay']) ?></div>
            <?php $ssX = $lk['active']['sadesati'] ?? null; if (is_array($ssX)): ?>
              <div style="font-size:.8rem;margin-top:5px;color:#92400e">
                <b><?= $h((string) $ssX['label']) ?></b> चल रही है<?= $ssX['phase'] ? ' (चरण ' . (int) $ssX['phase'] . ')' : '' ?>
                — <i><?= $h((string) ($ssX['source_label'] ?? 'वैदिक आधार पर')) ?></i>। यह एक तय अवधि है और समाप्त होती है।
              </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- 5. उपाय — एक, ज़्यादा से ज़्यादा दो -->
          <div style="border:1px solid #86efac;background:#f0fdf4;border-radius:10px;padding:10px 13px;margin-bottom:11px">
            <div style="font-weight:800;font-size:.9rem;margin-bottom:5px;color:#14532d">🛠 अभी करने योग्य उपाय
              <span style="font-weight:600;font-size:.75rem">(सिर्फ़ इतना — बाक़ी बाद में)</span></div>
            <?php if (empty($C['upaay'])): ?>
              <div style="font-size:.84rem;color:#166534">इस समय कोई तात्कालिक उपाय अनिवार्य नहीं।</div>
            <?php else: foreach ($C['upaay'] as $i => $u): ?>
              <?php /* निशाना और दिशा मशीन-पठनीय रूप में भी — जाँच-कवच इसी से परखता
                       है कि कोई जारी उपाय किसी रक्षक ग्रह को शांत तो नहीं कर रहा
                       (X2)। यही इस पूरी परत की सबसे महँगी चूक होगी। */ ?>
              <div data-lk-upay="<?= $h((string) $u['target']) ?>" data-dir="<?= $h((string) $u['direction']) ?>"
                   style="background:#fff;border:1px solid #bbf7d0;border-radius:8px;padding:8px 11px;margin-bottom:7px">
                <div style="font-weight:700;font-size:.85rem;color:#14532d">
                  <?= (int) ($i + 1) ?>. <?= $h((string) $u['target']) ?> — दिशा: <?= $h((string) $u['direction']) ?>
                </div>
                <?php if (trim((string) ($u['target_note'] ?? '')) !== ''): ?>
                  <div style="font-size:.78rem;color:#475569;margin:2px 0 4px"><?= $h((string) $u['target_note']) ?></div>
                <?php endif; ?>
                <?php foreach ((array) $u['upay'] as $t): ?>
                  <div style="font-size:.84rem;line-height:1.6;color:#14532d">• <?= $h((string) $t) ?></div>
                <?php endforeach; ?>
                <div style="font-size:.77rem;color:#475569;margin-top:4px">
                  ⏱ <?= $h((string) $u['kind']) ?> · अवधि: <?= $h((string) $u['duration']) ?> ·
                  कब रोकें: <?= $h((string) $u['stop_when']) ?> · दोहराव: <?= $h((string) $u['repeat']) ?>
                </div>
              </div>
            <?php endforeach; endif; ?>
            <?php if (!empty($C['held'])): ?>
              <div style="font-size:.78rem;color:#475569;margin-top:4px">
                📌 बाक़ी <?= count($C['held']) ?> उपाय नोट कर लिए गए हैं — पहला पूरा होने के बाद उनकी बारी आएगी।
                (एक साथ कई उपाय शुरू करने से कोई पूरा नहीं होता।)
              </div>
            <?php endif; ?>
            <?php if (!empty($PR['upaay']['note'])): ?>
              <div style="font-size:.78rem;color:#92400e;margin-top:5px">ℹ️ <?= $h((string) $PR['upaay']['note']) ?></div>
            <?php endif; ?>
            <?php /* कौन-सा उपाय हालत के कारण रुका — यह दिखाना ज़रूरी है, वरना
                     पढ़ने वाले को लगता है कि उसके लिए कुछ है ही नहीं। */ ?>
            <?php foreach ((array) ($PR['upaay']['gate_notes'] ?? []) as $gn): ?>
              <div style="font-size:.77rem;color:#92400e;margin-top:4px">🔒 <?= $h((string) $gn) ?></div>
            <?php endforeach; ?>
            <?php if (!empty($PR['upaay']['excluded'])): ?>
              <?php foreach ($PR['upaay']['excluded'] as $ex): ?>
                <div style="font-size:.77rem;color:#7f1d1d;margin-top:4px">🚫 <?= $h((string) $ex['target']) ?> का शांति-उपाय रोका गया — <?= $h((string) $ex['reason']) ?>।</div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <!-- 6. करें / न करें -->
          <div class="lk-grid2" style="margin-bottom:11px">
            <div style="border:1px solid #bbf7d0;border-radius:9px;padding:8px 11px">
              <div style="font-weight:700;font-size:.84rem;color:#14532d;margin-bottom:3px">✅ करने योग्य</div>
              <?php foreach ((array) $C['karne'] as $d): ?><div style="font-size:.81rem;line-height:1.55">• <?= $h((string) $d) ?></div><?php endforeach; ?>
            </div>
            <div style="border:1px solid #fecaca;border-radius:9px;padding:8px 11px">
              <div style="font-weight:700;font-size:.84rem;color:#7f1d1d;margin-bottom:3px">⛔ न करने योग्य</div>
              <?php foreach ((array) $C['na_karne'] as $d): ?><div style="font-size:.81rem;line-height:1.55">• <?= $h((string) $d) ?></div><?php endforeach; ?>
            </div>
          </div>

          <!-- 7. सीमा व चिकित्सा-नोट -->
          <div style="border:1px solid #e2e8f0;background:#f8fafc;border-radius:10px;padding:9px 12px;font-size:.82rem;line-height:1.65;color:#334155">
            <div style="margin-bottom:5px">🕊 <?= $h((string) $C['boundary']) ?></div>
            <div>🩺 <?= $h((string) $C['medical']) ?></div>
          </div>
          <?php /* ज्योतिषी के लिए — विरोध कैसे सुलझे, और क्या तय नहीं हो सका।
                   ग्राहक को इसकी ज़रूरत नहीं; पर जो इस रिपोर्ट की ज़िम्मेदारी
                   लेता है उसे दिखना चाहिए कि इंजन किस आधार पर पहुँचा। */ ?>
          <?php if (!empty($PR['conflicts']) || !empty($PR['unresolved'])): ?>
          <details style="margin-top:10px;border:1px solid #e2e8f0;border-radius:9px;padding:7px 11px">
            <summary style="font-weight:700;font-size:.83rem;cursor:pointer;color:#334155">
              ⚖ विरोधाभास कैसे सुलझे (ज्योतिषी हेतु) —
              <?= count((array) $PR['conflicts']) ?> सुलझे<?= !empty($PR['unresolved']) ? ' · ' . count((array) $PR['unresolved']) . ' अनिर्णीत' : '' ?>
            </summary>
            <?php foreach ((array) $PR['conflicts'] as $cf): ?>
              <div style="font-size:.8rem;line-height:1.55;margin-top:5px;color:#334155">
                <b><?= $h((string) $cf['subject']) ?></b> — <?= $h((string) $cf['kind']) ?>
                <span style="color:#64748b">(<?= $h((string) $cf['resolution']) ?>)</span><br>
                <span style="color:#475569"><?= $h((string) $cf['resolve']) ?></span>
              </div>
            <?php endforeach; ?>
            <?php foreach ((array) $PR['unresolved'] as $cf): ?>
              <div style="font-size:.8rem;line-height:1.55;margin-top:5px;color:#7c2d12">
                <b><?= $h((string) $cf['subject']) ?></b> — <?= $h((string) $cf['kind']) ?> · <b>अनिर्णीत</b><br>
                <?= $h((string) $cf['resolve']) ?>
              </div>
            <?php endforeach; ?>
            <?php if (!empty($PR['data_warn'])): ?>
              <div style="font-size:.78rem;color:#b91c1c;margin-top:6px">
                ⚠️ अनिर्णीत विरोध बहुत ज़्यादा (<?= $h((string) $PR['density']) ?>) — यह प्रायः असामान्य कुंडली नहीं,
                किसी तालिका में गड़बड़ी का संकेत होता है। संदर्भ-डेटा एक बार जाँच लें।
              </div>
            <?php endif; ?>
          </details>
          <?php endif; ?>

          <?php /* मना-शब्द इस समय कहीं नहीं हैं। यह पट्टी तभी दिखेगी जब कोई घुस
                   आए — और तभी दिखना इसका पूरा काम है। */ ?>
          <?php if (!empty($PR['client']['word_warn'])): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:8px 11px;
                        margin-top:7px;font-size:.78rem;color:#7f1d1d">
              <b>⚠️ मना-शब्द मिला —</b> नीचे के वाक्य इंजन के अपने गढ़े हैं और इनमें ऐसा शब्द है जो
              फल को अटल बताता है। लाल किताब का पूरा उपाय-तंत्र इसी पर खड़ा है कि फल बदला जा सकता है,
              इसलिए वाक्य सुधारा जाना चाहिए:
              <?php foreach ($PR['client']['word_warn'] as $ww): ?>
                <div style="margin-top:3px">• <?= $h((string) $ww) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php /* किस मत पर यह फल बना — पूरा, न कि दो चुनी हुई पंक्तियाँ। दो
                   ज्योतिषी अलग सेटिंग पर अलग नतीजे देंगे; बिना इस पंक्ति के कोई
                   बता ही नहीं सकता कि फ़र्क़ कहाँ से आया। */ ?>
          <div data-lk-protectors="<?= $h(implode('|', (array) ($PR['upaay']['protectors'] ?? []))) ?>"
               data-lk-niyam="<?= $h(implode('|', array_map(static fn ($k, $v) => $k . '=' . $v,
                     array_keys((array) ($PR['niyam'] ?? [])), array_values((array) ($PR['niyam'] ?? []))))) ?>"
               style="font-size:.72rem;color:#94a3b8;margin-top:7px;line-height:1.6">
            <b>नियम-सेटिंग<?= empty($PR['niyam_default']) ? ' (बदली हुई)' : '' ?>:</b>
            <?= $h(implode(' · ', (array) ($PR['niyam_hi'] ?? []))) ?>
            · सोई दृष्टि = <?= $h((string) $PR['settings']['soya_drishti']) ?>
            · बैठक-क्रम = <?= $h((string) $PR['settings']['seat_precedence']) ?>
            · विरोध-घनत्व = <?= $h((string) $PR['density']) ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="lk-view" data-lk="overview">
        <h3 class="lk-h">सामान्य परिचय (Lal Kitab Overview)</h3>

        <!-- 📢 सम्पूर्ण-कुंडली सार + करें/न करें -->
        <?php $G = $lk['general'] ?? null; if ($G !== null):
            $gBg = $G['tone'] === 'neg' ? 'background:#fef2f2;border-color:#fecaca;color:#7f1d1d'
                : ($G['tone'] === 'pos' ? 'background:#f0fdf4;border-color:#bbf7d0;color:#14532d'
                : 'background:#fffbeb;border-color:#fde68a;color:#713f12'); ?>
        <div style="border:1px solid;border-radius:10px;padding:10px 13px;margin-bottom:11px;<?= $gBg ?>">
          <div style="font-weight:800;font-size:.9rem;margin-bottom:3px">📢 सम्पूर्ण कुंडली — सामान्य फल</div>
          <div style="font-size:.85rem;line-height:1.6"><?= $h((string) $G['summary']) ?></div>
          <?php if (!empty($G['shubh']) || !empty($G['ashubh'])): ?>
          <div style="font-size:.78rem;margin-top:5px">🟢 शुभ ग्रह: <b><?= $h(implode(', ', $G['shubh']) ?: '—') ?></b> &nbsp; 🔴 ध्यान योग्य: <b><?= $h(implode(', ', $G['ashubh']) ?: '—') ?></b></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($G['dos']) || !empty($G['donts'])): ?>
        <div class="lk-dd" style="margin-bottom:11px">
          <?php if (!empty($G['dos'])): ?>
          <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:9px;padding:8px 11px">
            <div style="font-weight:700;font-size:.82rem;color:#166534;margin-bottom:3px">✅ करें (सामान्य Do's)</div>
            <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#14532d;line-height:1.5">
              <?php foreach ($G['dos'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
          <?php if (!empty($G['donts'])): ?>
          <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:9px;padding:8px 11px">
            <div style="font-weight:700;font-size:.82rem;color:#991b1b;margin-bottom:3px">⛔ न करें (सामान्य Don'ts)</div>
            <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#7f1d1d;line-height:1.5">
              <?php foreach ($G['donts'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
            </ul>
          </div>
          <?php endif; ?>
        </div>
        <?php endif; endif; ?>

        <?php $TK = $lk['teva_kisam'] ?? null; if ($TK !== null): ?>
        <!-- फरमान 14 — टेवे की किस्म (पूरे टेवे का वर्गीकरण) -->
        <div class="lk-card" style="border-color:#6366f1;background:#eef2ff">
          <div class="lk-card-h">📜 टेवे की किस्म — पूरे टेवे का स्वभाव <?= $srcTag('फरमान 14') ?></div>
          <?php if (!empty($TK['kinds'])): ?>
            <?php foreach ($TK['kinds'] as $tk): ?>
              <div style="margin:8px 0;padding:8px 11px;border:1px solid #c7d2fe;border-radius:9px;background:#fff">
                <div class="lk-sub" style="font-size:.88rem">
                  <b><?= $h((string) $tk['naam']) ?></b>
                  <?php if (($tk['darja'] ?? '') !== 'final'): ?><span class="lk-pill" style="background:#fef3c7;color:#92400e">अनुमानित</span><?php endif; ?>
                  <span class="lk-meta" style="margin-left:5px"><?= $h((string) $tk['by']) ?></span>
                </div>
                <div class="lk-txt" style="margin-top:3px"><?= $h((string) $tk['phal']) ?></div>
                <?php if (!empty($tk['upay'])): ?><?= $remBlock($tk['upay'], $tk['naam'] . ' — उपाय') ?><?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="lk-txt">इस टेवे में कोई विशेष किस्म (अंधा / बालिग / नाबालिग / धर्मी आदि) का योग नहीं बना — सामान्य टेवा।</div>
          <?php endif; ?>
          <?php if (!empty($TK['ling_niyam'])): ?>
          <div class="lk-note" style="margin-top:7px">👫 <b>मर्द/औरत का टेवा:</b> <?= $h((string) $TK['ling_niyam']) ?></div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php $KR = $lk['karak_rishtedar'] ?? null; if (!empty($KR['map'])): ?>
        <!-- ग्रह → रिश्तेदार कारक (फल में "मुतलका शनि/राहु" आदि का अर्थ) -->
        <div class="lk-card" style="border-color:#0d9488;background:#f0fdfa">
          <div class="lk-card-h">👪 ग्रह → रिश्तेदार कारक <?= $srcTag('फरमान 13') ?></div>
          <div class="lk-note" style="margin-bottom:5px"><?= $h((string) ($KR['title'] ?? '')) ?></div>
          <div style="display:flex;flex-wrap:wrap;gap:5px">
            <?php foreach ($KR['map'] as $pk => $rel): ?>
              <span class="lk-pill" style="background:#ccfbf1;color:#115e59"><b><?= $h(\AutoBusiness\Astro\LalKitab\LalKitabData::planetHi((string) $pk)) ?></b> = <?= $h((string) $rel) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

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

      <?php /* प्रक्रिया-परत के प्रति-ग्रह निचोड़ — तीनों ग्रह-पन्ने इन्हीं से बोलते हैं,
               इसलिए वे और निचोड़-पन्ना कभी दो अलग बातें नहीं कहते। */
            $LKB = (array) (($lk['process']['briefs']) ?? []);
            $LKH = (array) (($lk['process']['house_briefs']) ?? []);
            $LKK = (array) (($lk['process']['karak_briefs']) ?? []); ?>
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
              <?= $pill((string) $p['status'] . (($p['status_tier'] ?? '') === 'गौण' ? ' गौण' : ''), 'status') ?><?= $pill((string) $p['verdict'], 'verdict') ?><?php
                if (!empty($p['uch_bhang'])): ?><span class="lk-pill" style="background:#fef3c7;color:#92400e">उच्च पर भंग</span><?php endif;
                if (!empty($p['pukka'])): ?><span class="lk-pill" style="background:#ede9fe;color:#5b21b6">पक्का घर (तीव्र)</span><?php endif;
                if (!empty($p['kachcha'])): ?><span class="lk-pill" style="background:#f1f5f9;color:#475569">कच्चा घर (मंद)</span><?php endif;
                if (!empty($p['impair'])): ?><span class="lk-pill" style="background:#fee2e2;color:#991b1b">😴 <?= $h((string) $p['impair']) ?></span><?php endif;
                if (!empty($act['dasha']) && $act['dasha']['hi'] === $p['hi']): ?><span class="lk-pill" style="background:#dc2626;color:#fff">🔥 दशा चल रही</span><?php endif;
              ?><?= $scorePill((int) ($p['score'] ?? 0)) ?>
              <?= $srcTag('भाव-आधारित (टेवा)') ?>
            </div>
            <?php /* निचोड़ पहले, ब्योरा बाद में — पढ़ने वाला ऊपर से नीचे पढ़ता है, और
                     जो सबसे ऊपर है उसी को फ़ैसला मानता है। नीचे का तकनीकी ब्योरा
                     वैसा ही रहता है, वह ज्योतिषी के लिए है। */ ?>
            <?= $briefBox($LKB[(string) $p['hi']] ?? null) ?>
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
              <div class="lk-anlz-row"><span class="lk-anlz-k"><?= !empty($p['impair']) ? '😴' : '⚡' ?> अवस्था</span>
                <?php if (!empty($p['impair'])): ?>
                  <b style="color:#991b1b">😴 <?= $h((string) $p['impair']) ?></b> — यह जिस भाव को देखता है वह खाली है; कारकत्व दबा रहेगा
                <?php else: ?>
                  <b style="color:#166534">⚡ जागृत — फल सक्रिय</b>
                <?php endif; ?>
              </div>
              <?php if (!empty($p['masnui'])): ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k">🔀 मसनूई</span>
                <span>
                  <?php foreach ($p['masnui'] as $ms): ?>
                    <?= $h((string) $ms['with']) ?> के साथ मिलकर <b>मसनूई <?= $h((string) $ms['label']) ?></b>
                    बना रहा है (<?= (int) $ms['house'] ?>वाँ भाव, <?= $h((string) $ms['verdict']) ?>) —
                    इस भाव का मुख्य फल वहीं पढ़ें।<br>
                  <?php endforeach; ?>
                </span>
              </div>
              <?php endif; ?>
              <?php if (!empty($p['verdict_why'])): ?>
              <div class="lk-anlz-row lk-anlz-final"><span class="lk-anlz-k">⚖ निष्कर्ष</span>
                <b><?= $h((string) $p['verdict']) ?></b> — <?= $h(implode(' · ', $p['verdict_why'])) ?>
              </div>
              <?php endif; ?>
            </div>

            <!-- 📢 फल — आखिर क्या होगा (सब निष्कर्षों का सार, जीवन-क्षेत्र सहित) -->
            <?php if (trim((string) ($p['pred_head'] ?? '')) !== ''):
                $predBg = $p['verdict'] === 'अशुभ' ? 'background:#fef2f2;border-color:#fecaca;color:#7f1d1d'
                    : ($p['verdict'] === 'शुभ' ? 'background:#f0fdf4;border-color:#bbf7d0;color:#14532d'
                    : 'background:#fffbeb;border-color:#fde68a;color:#713f12'); ?>
              <div style="border:1px solid;border-radius:9px;padding:8px 11px;margin-top:8px;<?= $predBg ?>">
                <div style="font-weight:700;font-size:.84rem;margin-bottom:3px">📢 फल — क्या होगा</div>
                <div style="font-size:.85rem;line-height:1.6"><?= $h((string) $p['pred_head']) ?></div>
                <?php if (!empty($p['pred_effects'])): ?>
                  <ul style="margin:5px 0 0;padding-left:18px;font-size:.83rem;line-height:1.55">
                    <?php foreach ($p['pred_effects'] as $pe): ?><li><?= $h((string) $pe) ?></li><?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- 📖 भाव-अनुसार नेक/मंदी फल (फरमान 13) -->
            <?php $BP = $p['bhav_phal'] ?? null; if ($BP !== null):
                $showNek = ($BP['show'] ?? 'both') !== 'mandi' && trim((string) ($BP['nek'] ?? '')) !== '';
                $showMandi = ($BP['show'] ?? 'both') !== 'nek' && trim((string) ($BP['mandi'] ?? '')) !== ''; ?>
              <div style="border:1px solid #ddd6fe;background:#faf5ff;border-radius:9px;padding:8px 11px;margin-top:8px">
                <div style="font-weight:700;font-size:.84rem;color:#6b21a8;margin-bottom:4px">📖 इस भाव में फल <?= $srcTag('फरमान 13') ?></div>
                <?php if ($showNek): ?>
                  <div style="font-size:.83rem;line-height:1.6"><b style="color:#166534">🟢 नेक:</b> <?= $h((string) ($BP['nek'] ?? '')) ?><?php if (!empty($BP['nek_vistar'])): ?> — <?= $h((string) $BP['nek_vistar']) ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if ($showMandi): ?>
                  <div style="font-size:.83rem;line-height:1.6;margin-top:3px"><b style="color:#991b1b">🔴 मंदी:</b> <?= $h((string) ($BP['mandi'] ?? '')) ?><?php if (!empty($BP['mandi_vistar'])): ?> — <?= $h((string) $BP['mandi_vistar']) ?><?php endif; ?></div>
                <?php endif; ?>
                <?php if (!empty($BP['upay'])): ?><?= $remBlock($BP['upay'], 'इस भाव हेतु उपाय') ?><?php endif; ?>
                <?php if (!empty($BP['note'])): ?><div class="lk-note" style="margin-top:4px"><?= $h((string) $BP['note']) ?></div><?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($p['helps']) || !empty($p['special_niyam'])): ?>
              <div style="border:1px solid #fde68a;background:#fffbeb;border-radius:9px;padding:8px 11px;margin-top:8px">
                <?php if (!empty($p['helps'])): ?>
                  <div style="font-size:.83rem;line-height:1.6"><b style="color:#92400e">🤝 बल देता है:</b> यह भाव <?= $h((string) $p['hi']) ?> को <b><?= $h(implode(', ', $p['helps'])) ?></b> को बल देने योग्य बनाता है।</div>
                <?php endif; ?>
                <?php if (!empty($p['special_niyam'])): ?>
                  <ul style="margin:4px 0 0;padding-left:18px;font-size:.8rem;line-height:1.5;color:#713f12">
                    <?php foreach ($p['special_niyam'] as $nx): ?><li><?= $h((string) $nx) ?></li><?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- 📅 आयु / समय-प्रभाव — कब जागेगा, प्रभावशाली व सावधानी के वर्ष -->
            <?php if (!empty($p['age_timing'])): ?>
              <div style="border:1px solid #c7d2fe;background:#eef2ff;border-radius:9px;padding:8px 11px;margin-top:8px">
                <div style="font-weight:700;font-size:.82rem;color:#3730a3;margin-bottom:3px">📅 आयु / समय-प्रभाव</div>
                <div style="font-size:.82rem;line-height:1.6;color:#3730a3">
                  <?php foreach ($p['age_timing'] as $atx): ?><div><?= $h((string) $atx) ?></div><?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>

            <!-- ✅ करें / ⛔ न करें — इस ग्रह हेतु आचरण-मार्गदर्शन -->
            <?php if (!empty($p['dos']) || !empty($p['donts'])): ?>
              <div class="lk-dd">
                <?php if (!empty($p['dos'])): ?>
                <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#166534;margin-bottom:3px">✅ करें (Do's)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#14532d;line-height:1.5">
                    <?php foreach ($p['dos'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($p['donts'])): ?>
                <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#991b1b;margin-bottom:3px">⛔ न करें (Don'ts)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#7f1d1d;line-height:1.5">
                    <?php foreach ($p['donts'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- नियम-आधार (क्यों) — the applied टिप्पणी clauses with the reason
                 each one holds; folded, since the फल block already states them. -->
            <?php if (!empty($p['notes_applied'])): ?>
              <details style="margin-top:5px">
                <summary style="cursor:pointer;font-size:.76rem;color:#0f766e">📌 नियम-आधार — यह फल किन नियमों से बना (<?= count($p['notes_applied']) ?>)</summary>
                <?php foreach ($p['notes_applied'] as $na): ?>
                  <div class="lk-txt" style="margin:3px 0;padding-left:10px;border-left:3px solid #0f766e">
                    <?= $h((string) $na['text']) ?>
                    <span style="font-size:.72rem;color:#0f766e">(<?= $h((string) $na['why']) ?>)</span>
                  </div>
                <?php endforeach; ?>
              </details>
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
              <?php if (empty($H['awake'])): ?><span class="lk-pill" style="background:#fee2e2;color:#991b1b">😴 सुप्त</span><?php else: ?><span class="lk-pill" style="background:#dcfce7;color:#166534">⚡ जागृत</span><?php endif; ?>
              <?= $srcTag('भाव विचार + दृष्टि चक्र') ?>
            </div>
            <?php /* निचोड़ पहले, ब्योरा बाद में — और चेतावनी दर्जे में घुलने नहीं
                     दी जाती। "शुभ" भाव पर भी विश्वासघात की आशंका बनी रह सकती है। */ ?>
            <?= $houseBriefBox($LKH[(int) $H['house']] ?? null) ?>

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
              <?php $BF = $H['buniyad'] ?? null; if ($BF !== null):
                  $relPill = static function (string $r) use ($h): string {
                      $c = $r === 'मित्र' ? 'background:#dcfce7;color:#166534' : ($r === 'शत्रु' ? 'background:#fee2e2;color:#991b1b' : 'background:#e2e8f0;color:#334155');
                      return '<span class="lk-pill" style="' . $c . '">' . $h($r) . '</span>';
                  }; ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k">🏛 बुनियाद-असूल</span>
                <span title="भाव के मालिक की तासीर">नींव: <b><?= $h((string) $BF['neenv_hi']) ?></b></span> &nbsp;·&nbsp;
                <span title="जिस ग्रह का यह पक्का घर है">इमारत: <?php if (!empty($BF['imarat'])): foreach ($BF['imarat'] as $im): ?><b><?= $h((string) $im['hi']) ?></b> <?= $relPill((string) $im['rel']) ?> <?php endforeach; else: ?><span style="color:#94a3b8">—</span><?php endif; ?></span> &nbsp;·&nbsp;
                <span title="ग्रह-चाल से यहाँ बैठा/आया ग्रह">राज: <?php if (!empty($BF['raj'])): foreach ($BF['raj'] as $rj): ?><b><?= $h((string) $rj['hi']) ?></b> <span class="lk-meta">(नींव-<?= $h((string) $rj['rel_neenv']) ?><?= $rj['rel_imarat'] !== '' ? ', इमारत-' . $h((string) $rj['rel_imarat']) : '' ?>)</span> <?php endforeach; else: ?><span style="color:#94a3b8">खाली</span><?php endif; ?></span>
                <div class="lk-note" style="margin-top:2px">नींव व इमारत मित्र हों तो शुभ फल बढ़ता है, शत्रु हों तो बिगड़ता है; यहाँ बैठा ग्रह भी अपनी मित्रता-शत्रुता से फल बदलता है।</div>
              </div>
              <?php endif; ?>
              <div class="lk-anlz-row"><span class="lk-anlz-k"><?= !empty($H['awake']) ? '⚡' : '😴' ?> सुप्त / जागृत</span>
                <?php if (!empty($H['awake'])): ?>
                  <b style="color:#166534">⚡ जागृत</b> — <?= $h((string) $H['awake_by']) ?>
                <?php else: ?>
                  <b style="color:#991b1b">😴 सुप्त</b> — जगाने वाला ग्रह <b><?= $h((string) $H['waker_hi']) ?></b>; इस भाव के विषय दबे रहेंगे
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

            <!-- 📢 फल — क्या होगा (भाव) -->
            <?php if (trim((string) ($H['pred_head'] ?? '')) !== ''):
                $hpBg = $H['verdict'] === 'अशुभ' ? 'background:#fef2f2;border-color:#fecaca;color:#7f1d1d'
                    : ($H['verdict'] === 'शुभ' ? 'background:#f0fdf4;border-color:#bbf7d0;color:#14532d'
                    : 'background:#fffbeb;border-color:#fde68a;color:#713f12'); ?>
              <div style="border:1px solid;border-radius:9px;padding:8px 11px;margin-top:8px;<?= $hpBg ?>">
                <div style="font-weight:700;font-size:.84rem;margin-bottom:3px">📢 फल — क्या होगा</div>
                <div style="font-size:.85rem;line-height:1.6"><?= $h((string) $H['pred_head']) ?></div>
                <?php if (!empty($H['pred_effects'])): ?>
                  <ul style="margin:5px 0 0;padding-left:18px;font-size:.83rem;line-height:1.55">
                    <?php foreach ($H['pred_effects'] as $pe): ?><li><?= $h((string) $pe) ?></li><?php endforeach; ?>
                  </ul>
                <?php endif; ?>
                <?php if (trim((string) $H['maas']) !== ''): ?><div style="font-size:.76rem;margin-top:4px;opacity:.8">विशेष मास: <?= $h((string) $H['maas']) ?></div><?php endif; ?>
              </div>
            <?php endif; ?>

            <!-- ✅ करें / ⛔ न करें — भाव हेतु (स्थित ग्रह + स्वामी) -->
            <?php if (!empty($H['dos']) || !empty($H['donts'])): ?>
              <div class="lk-dd">
                <?php if (!empty($H['dos'])): ?>
                <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#166534;margin-bottom:3px">✅ करें (Do's)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#14532d;line-height:1.5">
                    <?php foreach ($H['dos'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($H['donts'])): ?>
                <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#991b1b;margin-bottom:3px">⛔ न करें (Don'ts)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#7f1d1d;line-height:1.5">
                    <?php foreach ($H['donts'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <details style="margin-top:5px">
              <summary style="cursor:pointer;font-size:.76rem;color:#94a3b8">भाव-विषय (पूरी सूची)</summary>
              <div class="lk-txt" style="margin-top:3px"><?= $h((string) $H['vishay']) ?></div>
            </details>

            <?php if (!empty($H['need_remedy']) && !empty($H['remedies'])): ?>
              <?= $remBlock($H['remedies'], $H['house_ord'] . ' भाव — बल हेतु उपाय') ?>
            <?php elseif ($H['verdict'] === 'शुभ'): ?>
              <?php /* यह पंक्ति पहले चेतावनियों के बावजूद छप जाती थी — उसी कार्ड पर
                       जिस पर ऊपर "विश्वासघात की आशंका" लिखा था। पढ़ने वाला अंत की
                       पंक्ति को फ़ैसला मानता है, इसलिए वह चेतावनी छोड़ देता। अब यह
                       तभी छपती है जब सचमुच कोई चेतावनी न हो। */ ?>
              <?php if (!empty(($LKH[(int) $H['house']]['safe']) ?? false)): ?>
                <div style="margin-top:6px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:5px 9px;font-size:.78rem;color:#166534">✅ भाव सबल — कोई उपाय आवश्यक नहीं।</div>
              <?php else: ?>
                <div style="margin-top:6px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:5px 9px;font-size:.78rem;color:#92400e">⚠️ इस भाव पर ऊपर दी चेतावनी बनी हुई है — दर्जा अच्छा होने भर से वह नहीं टलती।</div>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== KARAK ===== -->
      <div class="lk-view" data-lk="karak">
        <h3 class="lk-h">कारक (Natural Significators) — computed</h3>
        <div class="lk-txt" style="margin-bottom:9px">हर भाव का एक स्वाभाविक कारक ग्रह है। वह इस कुंडली में जहाँ बैठा है, वहाँ की हालत से तय होता है कि उस भाव के विषयों को <b>सहारा</b> मिल रहा है या नहीं।</div>
        <?php /* ══════ यहाँ शब्द ही ग़लत था ══════
             पहले कारक-पंक्ति पर *अशुभ* लिखा जाता था और ठीक नीचे उसी ग्रह पर *शुभ*
             का ठप्पा होता था। दोनों सही थे और साथ में बेतुके: ग्रह की दिशा नेक थी,
             पर सोया होने से उसकी **क्षमता** शून्य थी। नेक/बद और कितना-कर-सकता-है
             दो अलग धुरियाँ हैं, और कारक की बात हमेशा दूसरी धुरी की है — इसलिए अब
             बलवान / मध्यम / दुर्बल कहा जाता है। "दुर्बल" का मतलब बुरा नहीं;
             मतलब है कि यह विषय अपने-आप नहीं चलेगा, इसे सहारा चाहिए। */ ?>
        <div class="lk-note" style="margin-bottom:9px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:9px;padding:7px 10px;color:#3730a3">
          यहाँ <b>शुभ/अशुभ</b> नहीं लिखा जाता — <b>बलवान / मध्यम / दुर्बल</b> लिखा जाता है।
          कारक की बात "अच्छा या बुरा" की नहीं, "कितना कर सकता है" की है। दोनों अलग बातें हैं:
          कोई ग्रह नेक होकर भी सोया हो सकता है, और तब उसका सहारा मिलता ही नहीं।
        </div>
        <?php foreach ($lk['karak'] as $K): if (!$K['karaks']) { continue; }
            $KB = $LKK[(int) $K['house']] ?? null;
            $kBal = (string) (($KB['bal']) ?? 'मध्यम');
            $kCls = $kBal === 'दुर्बल' ? 'bad' : ($kBal === 'बलवान' ? 'good' : ''); ?>
          <div class="lk-card <?= $kCls ?>">
            <div class="lk-card-h"><?= (int) $K['house'] ?>. <?= $h((string) $K['house_ord']) ?> भाव के कारक
              <span class="lk-pill" style="<?= $kBal === 'दुर्बल' ? 'background:#fee2e2;color:#991b1b' : ($kBal === 'बलवान' ? 'background:#dcfce7;color:#166534' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h($kBal) ?></span></div>
            <?php if ($KB !== null): ?>
              <div style="font-size:.85rem;line-height:1.6;color:#334155;margin:2px 0 6px"><?= $h((string) $KB['line']) ?></div>
            <?php endif; ?>
            <?php /* पुराना "📢 फल" डिब्बा यहाँ से हटाया गया। वह अब भी नेक/बद वाली
                     पुरानी गिनती से बनता था, इसलिए ऊपर वाली नई पंक्ति से उलटा पड़
                     जाता — एक ही कार्ड पर "मध्यम" और "कारक बलवान है" साथ छपते।
                     ऊपर की पंक्ति वही बात ठीक धुरी पर कहती है। */ ?>
            <?php $wk = array_values(array_filter(array_map(
                    static fn ($r) => ($r['bal'] ?? '') === 'दुर्बल' ? (string) $r['hi'] : '',
                    (array) ($KB['rows'] ?? [])))); ?>
            <?php if ($wk !== []): ?>
              <div style="font-size:.79rem;margin:2px 0 6px;color:#991b1b">सहारे की ज़रूरत वाले कारक: <b><?= $h(implode(', ', $wk)) ?></b></div>
            <?php endif; ?>
            <?php foreach (($KB['rows'] ?? []) as $kk): ?>
              <div class="lk-sub">
                <b><?= $h((string) $kk['hi']) ?></b>
                <?php if (trim((string) $kk['placed_ord']) !== ''): ?>
                  — <?= $h((string) $kk['placed_ord']) ?> भाव में
                  <span class="lk-pill" style="<?= $kk['bal'] === 'दुर्बल' ? 'background:#fee2e2;color:#991b1b' : ($kk['bal'] === 'बलवान' ? 'background:#dcfce7;color:#166534' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $kk['bal']) ?></span>
                  <?php if (!empty($kk['asleep'])): ?><span class="lk-pill" style="background:#f1f5f9;color:#475569">😴 <?= $h($kk['impair'] !== '' ? (string) $kk['impair'] : 'सुप्त') ?></span><?php endif; ?>
                <?php else: ?>
                  — स्थिति अज्ञात
                <?php endif; ?>
                <?php /* दिशा पहले, उपाय बाद में। सोए कारक को "शराब न पीएँ" जैसा शांति-उपाय
                         देना उसे और गहरी नींद सुलाता है — यही चूक निचोड़-पन्ने पर जाँच X3
                         रोकती है, पर यह पन्ना उसकी नज़र से बाहर था। */ ?>
                <?php if (trim((string) $kk['dir']) !== ''): ?>
                  <div style="margin-top:4px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:6px 9px;font-size:.82rem;color:#3730a3">
                    <b>दिशा: <?= $h((string) $kk['dir']) ?></b>
                    <?php if ($kk['dir'] === 'जगाना'): ?>
                      — यह कारक सोया है, इसलिए इसे <b>शांत नहीं करना</b>; जगाना है।
                      <?php if (trim((string) $kk['chaabi']) !== ''): ?>
                        चाबी <b><?= $h((string) $kk['chaabi']) ?></b> के पास है — उपाय उसी पर जाता है।
                      <?php endif; ?>
                      <div style="font-size:.78rem;margin-top:2px">नीचे दिए सामान्य उपाय शांति के हैं — इन्हें इस कारक पर न लगाएँ।</div>
                    <?php else: ?>
                      — इसे बल देना है।
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($kk['remedies']) && $kk['dir'] !== 'जगाना'): ?>
                  <?= $remBlock($kk['remedies'], $kk['hi'] . ' — कारक-बल हेतु उपाय') ?>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== YOGA ===== -->
      <div class="lk-view" data-lk="yoga">
        <?php if (!empty($lk['masnui'])): ?>
          <h3 class="lk-h">मसनूई (कृत्रिम) ग्रह</h3>
          <div class="lk-anlz" style="margin-bottom:9px;line-height:1.7">
            दो ग्रह एक ही भाव में बैठें तो वे मिलकर <b>तीसरे ग्रह</b> जैसा काम करते हैं — और उस भाव का
            मुख्य फल उसी तीसरे ग्रह का पढ़ा जाता है। मूल दोनों ग्रह मिटते नहीं; मसनूई ग्रह उनके ऊपर
            एक <b>हावी अतिरिक्त परत</b> बन जाता है। इसीलिए उपाय कभी मसनूई ग्रह का नहीं, हमेशा उन
            <b>दो मूल ग्रहों</b> का किया जाता है।
          </div>
          <?php foreach ($lk['masnui'] as $M): ?>
            <?php
              $cls = $M['verdict'] === 'अशुभ' ? 'bad' : ($M['verdict'] === 'शुभ' ? 'good' : '');
              $ico = $M['verdict'] === 'अशुभ' ? '⚠' : ($M['verdict'] === 'शुभ' ? '✨' : '◐');
            ?>
            <div class="lk-card <?= $cls ?>" data-app="1">
              <div class="lk-card-h"><?= $ico ?> मसनूई <?= $h((string) $M['label']) ?>
                <span class="lk-meta" style="margin:0 0 0 6px">
                  <?= $h((string) $M['pair_hi']) ?> — <?= $h((string) $M['house_ord']) ?> भाव में
                </span>
                <?php if ($M['status'] !== 'सामान्य'): ?>
                  <span class="lk-pill" style="<?= $M['status'] === 'नीच' ? 'background:#dc2626;color:#fff' : 'background:#16a34a;color:#fff' ?>"><?= $h((string) $M['status']) ?></span>
                <?php endif; ?>
                <?php if (!empty($M['pakka'])): ?><span class="lk-pill" style="background:#7c3aed;color:#fff">पक्का घर</span><?php endif; ?>
                <?php if (!empty($M['kachcha'])): ?><span class="lk-pill" style="background:#94a3b8;color:#fff">कच्चा घर</span><?php endif; ?>
                <?php if (!empty($M['active_now'])): ?><span class="lk-pill" style="background:#f59e0b;color:#fff">अभी सक्रिय</span><?php endif; ?>
                <?= $srcTag('मसनूई गणना') ?>
              </div>
              <div class="lk-txt"><b>🔀 बनता क्या है:</b> <?= $h((string) $M['head']) ?></div>
              <div class="lk-txt"><b>📢 फल:</b> <?= $h((string) $M['effect']) ?></div>
              <?php if (!empty($M['why'])): ?>
                <div class="lk-reason">क्यों — <?= $h(implode(' · ', $M['why'])) ?></div>
              <?php endif; ?>
              <?php if (trim((string) $M['clash']) !== ''): ?>
                <div class="lk-txt"><b>⚔ असली बनाम मसनूई:</b> <?= $h((string) $M['clash']) ?></div>
              <?php endif; ?>
              <?php if (!empty($M['windows'])): ?>
                <div class="lk-reason">🕰️ सबसे तेज़ असर — 35-साला चक्र में इन खंडों पर:
                  <?php foreach ($M['windows'] as $wi => $W): ?>
                    <?= $h((string) $W['hi']) ?> (आयु <?= (int) $W['from'] === (int) $W['to'] ? (int) $W['from'] : (int) $W['from'] . '–' . (int) $W['to'] ?>)<?= $wi === count($M['windows']) - 1 ? '' : ' · ' ?>
                  <?php endforeach; ?>
                  — और फिर हर 35 वर्ष पर दोहराव
                </div>
              <?php endif; ?>
              <?= $remBlock($M['remedies'], 'मसनूई ' . $M['makes_hi'] . ' — ' . $M['mode']) ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($lk['yuti_dosha'])): ?>
          <h3 class="lk-h">विशेष युति / ग्रहण दोष</h3>
          <?php foreach ($lk['yuti_dosha'] as $D): ?>
            <div class="lk-card bad" data-app="1">
              <div class="lk-card-h">⚠ <?= $h((string) $D['name']) ?>
                <span class="lk-meta" style="margin:0 0 0 6px"><?= $h((string) $D['pair_hi']) ?> — <?= $h((string) $D['house_ord']) ?> भाव में</span>
                <?= $srcTag('युति दोष गणना') ?>
              </div>
              <div class="lk-txt"><b>📢 फल:</b> <?= $h((string) $D['desc']) ?></div>
              <?php if (!empty($D['dos']) || !empty($D['donts'])): ?>
              <div class="lk-dd">
                <?php if (!empty($D['dos'])): ?>
                <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#166534;margin-bottom:3px">✅ करें (Do's)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#14532d;line-height:1.5">
                    <?php foreach ($D['dos'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
                <?php if (!empty($D['donts'])): ?>
                <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:9px;padding:7px 10px">
                  <div style="font-weight:700;font-size:.8rem;color:#991b1b;margin-bottom:3px">⛔ न करें (Don'ts)</div>
                  <ul style="margin:0;padding-left:16px;font-size:.8rem;color:#7f1d1d;line-height:1.5">
                    <?php foreach ($D['donts'] as $dx): ?><li><?= $h((string) $dx) ?></li><?php endforeach; ?>
                  </ul>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              <?= $remBlock($D['remedies'], $D['name'] . ' — उपाय') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <h3 class="lk-h">योग — भविष्यवाणी सूत्र</h3>
        <?php /* ══════ पूरी किताब बनाम आपकी कुंडली ══════
             यहाँ पुस्तक के सारे सूत्र मौजूद हैं, पर लागू सिर्फ़ मुट्ठी भर होते हैं।
             चेक हटाते ही बाक़ी सैकड़ों सूत्र भी खुल जाते हैं — और उनमें ऐसी पंक्तियाँ
             हैं जो किसी की भी आँख में पड़ें तो डरा दें ("मंगल को विष देगा",
             "भाई-भतीजे को मरवा देगा")। वे इस कुंडली की बात नहीं हैं, संदर्भ हैं।
             इसलिए गिनती ऊपर लिखी जाती है, और चेक हटाने पर चेतावनी दिखती है। */ ?>
        <?php $yApp = 0; $yTot = 0; $yBad = [];
              foreach ((array) ($lk['yoga'] ?? []) as $Yc) { $yTot++;
                  if (!empty($Yc['applicable'])) { $yApp++;
                      if (($Yc['tone'] ?? '') === 'neg') { $yBad[] = (string) ($Yc['sthiti'] ?? $Yc['name'] ?? ''); } } } ?>
        <div class="lk-card" style="border-color:#c7d2fe;background:#eef2ff">
          <div class="lk-card-h" style="color:#3730a3">📘 पुस्तक के <?= (int) $yTot ?> सूत्रों में से इस कुंडली पर <?= (int) $yApp ?> लागू</div>
          <div class="lk-txt" style="color:#3730a3">
            <?php if ($yApp === 0): ?>
              इस कुंडली पर कोई नामी सूत्र नहीं बैठता — यह अपने-आप में ठीक बात है।
            <?php else: ?>
              नीचे सिर्फ़ वही <?= (int) $yApp ?> दिख रहे हैं।
              <?php if ($yBad !== []): ?>इनमें <b><?= count($yBad) ?></b> सावधानी वाले हैं।<?php endif; ?>
              बाक़ी <?= (int) max(0, $yTot - $yApp) ?> सूत्र आपकी कुंडली की बात नहीं हैं।
            <?php endif; ?>
          </div>
        </div>
        <div class="lk-flt">
          <label><input type="checkbox" class="lk-onlyapp" data-scope="yoga" checked> केवल लागू योग दिखाएँ</label>
          <span style="color:#94a3b8">(चेक हटाने पर पूरी पुस्तक-सूची खुलती है — संदर्भ हेतु)</span>
        </div>
        <div class="lk-note" id="lk-yoga-allwarn" style="display:none;background:#fef2f2;border:1px solid #fecaca;border-radius:9px;padding:8px 11px;margin-bottom:8px;color:#7f1d1d">
          <b>ध्यान दें —</b> अब पूरी पुस्तक-सूची खुली है। जिन पर <b>लागू</b> का ठप्पा नहीं है,
          वे <b>इस कुंडली पर लागू नहीं होते</b> — उनका फल यहाँ नहीं पढ़ा जाता। वे केवल संदर्भ के लिए हैं।
        </div>
        <?php foreach ($lk['yoga'] as $Y):
            // Card colour follows the RESULT (शुभ/अशुभ), not mere applicability;
            // a non-applicable card stays neutral (grey).
            $yt = $Y['tone'] ?? 'mix';
            $yCls = !$Y['applicable'] ? '' : ($yt === 'pos' ? 'good' : ($yt === 'neg' ? 'bad' : ''));
            $tonePill = $Y['applicable']
                ? ($yt === 'pos' ? $pill('शुभ फल', 'v') : ($yt === 'neg' ? $pill('अशुभ फल', 'v') : $pill('मिश्रित', 'v')))
                : ''; ?>
          <div class="lk-card <?= $yCls ?>" data-app="<?= $Y['applicable'] ? '1' : '0' ?>">
            <div class="lk-card-h" style="font-size:.88rem">
              <?= $h((string) $Y['sthiti']) ?>
              <?php if ($Y['applicable']): ?><span class="lk-pill" style="background:#e0e7ff;color:#3730a3">लागू</span><?= $tonePill ?><?php endif; ?>
              <?= $srcTag(($Y['mode'] ?? '') === 'exact' ? 'सटीक गणना' : 'पाठ-मिलान') ?>
            </div>
            <?php if (trim((string) $Y['prabhavit']) !== ''): ?><div class="lk-sub"><b>प्रभावित ग्रह:</b> <?= $h((string) $Y['prabhavit']) ?></div><?php endif; ?>
            <div class="lk-txt"><?= $h((string) $Y['phal']) ?></div>
          </div>
        <?php endforeach; ?>

        <?php if (!empty($lk['mukabla'])): $MK = $lk['mukabla']; ?>
          <h3 class="lk-h" style="margin-top:14px">मुकाबले के ग्रह — सांझी गद्दी की दुश्मनी</h3>
          <div class="lk-card bad" data-app="1">
            <div class="lk-card-h">⚔ भाव 8 — मंगल व शनि की स्थायी टक्कर <?= $srcTag('कालपुरुष नियम') ?></div>
            <div class="lk-txt"><b>📢 फल:</b> <?= $h((string) $MK['phal']) ?></div>
            <?= $remBlock($MK['upay'] ?? [], 'मुकाबला — उपाय') ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($lk['collisions'])): ?>
          <h3 class="lk-h" style="margin-top:14px">तीन टक्करें — विश्वासघात · साझी चोट · अचानक चोट</h3>
          <div class="lk-note" style="margin-bottom:9px">
            लाल किताब में भाव आपस में <b>धोखा</b> देते या <b>अचानक चोट</b> मारते हैं। चोट तभी लगती है जब
            दोनों सिरों पर ग्रह मौजूद हों — खाली घर न किसी को मारता है, न किसी से मार खाता है।
          </div>
          <?php
            $colGroup = [];
            foreach ($lk['collisions'] as $C) { $colGroup[$C['house']][] = $C; }
          ?>
          <?php foreach ($colGroup as $ch => $items): ?>
            <div class="lk-card bad" data-app="1">
              <div class="lk-card-h">⚠ <?= $h((string) $items[0]['house_ord']) ?> भाव (<?= $h(implode(', ', $items[0]['target_hi'])) ?>) पर</div>
              <?php foreach ($items as $C): ?>
                <div class="lk-sub"><b><?= $h((string) $C['kind']) ?>:</b> <?= $h(implode(' · ', $C['from_hi'])) ?> से</div>
                <div class="lk-txt" style="margin:2px 0 6px"><?= $h((string) $C['phal']) ?></div>
                <?= $remBlock($C['upay'] ?? [], $C['kind'] . ' — उपाय') ?>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <?php if (!empty($lk['buniyad'])): ?>
          <h3 class="lk-h" style="margin-top:14px">बुनियाद (जड़) — जड़ ख़राब तो शाखा का फल सड़े</h3>
          <?php foreach ($lk['buniyad'] as $B): ?>
            <div class="lk-card bad" data-app="1">
              <div class="lk-card-h">🌱 <?= $h((string) $B['house_ord']) ?> भाव की जड़ — <?= $h((string) $B['root_ord']) ?> भाव में <?= $h(implode(', ', $B['root_hi'])) ?></div>
              <div class="lk-txt"><b>📢 फल:</b> <?= $h((string) $B['phal']) ?></div>
              <?= $remBlock($B['upay'] ?? [], 'बुनियाद — उपाय') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ===== SHRAP / PAITRIK RIN ===== -->
      <div class="lk-view" data-lk="shrap">
        <?php
          $rinPresent = array_values(array_filter($lk['shrap'], static fn ($x) => !empty($x['present'])));
          $rinAbsent  = array_values(array_filter($lk['shrap'], static fn ($x) => empty($x['present'])));
        ?>
        <h3 class="lk-h">श्राप / पैतृक ऋण — इस कुंडली में जाँचे हुए</h3>
        <?php /* हर अड़चन ऋण नहीं होती। ऋण का ज़्यादा निदान इस विद्या की सबसे आम
                 चूक है — नतीजा एक भारी रिपोर्ट, डरा हुआ आदमी, और माँग भरे उपायों
                 का ढेर जिनमें से ज़्यादातर किसी चीज़ का इलाज नहीं करते। इसलिए
                 पुष्टि-दर्जा दिखाया जाता है, और "कमज़ोर संकेत" को ऋण कहकर
                 बताया ही नहीं जाता — वह साधारण ग्रह-दोष है। */
              $PRr = ($lk['process']['rin'] ?? null);
              if (is_array($PRr) && !empty($PRr['reportable'])): ?>
        <div style="border:1px solid #fed7aa;background:#fff7ed;border-radius:10px;padding:9px 12px;margin-bottom:10px">
          <div style="font-weight:800;font-size:.87rem;color:#7c2d12;margin-bottom:4px">🔎 जाँच का नतीजा — पुष्टि व हालत सहित</div>
          <?php foreach ($PRr['reportable'] as $re): ?>
            <div style="font-size:.83rem;line-height:1.6;color:#7c2d12;margin-bottom:5px">
              <b><?= $h((string) $re['rin']) ?></b>
              <span style="font-size:.75rem;background:#fed7aa;border-radius:6px;padding:1px 6px"><?= $h((string) $re['kind']) ?></span>
              <span style="font-size:.75rem;background:#fde68a;border-radius:6px;padding:1px 6px">पुष्टि: <?= $h((string) $re['confidence']) ?></span>
              <span style="font-size:.75rem;background:#e2e8f0;border-radius:6px;padding:1px 6px">हालत: <?= $h((string) $re['status']) ?></span>
              <span style="font-size:.75rem;background:#e2e8f0;border-radius:6px;padding:1px 6px">तीव्रता: <?= $h((string) $re['severity']) ?></span>
              <br><?= $h((string) $re['line_hi']) ?>
              <div style="font-size:.8rem;color:#166534;margin-top:2px">🛠 उपाय (<?= $h((string) $re['direction']) ?>): <?= $h((string) $re['upay']) ?></div>
              <?php if ($re['protector'] !== ''): ?>
                <div style="font-size:.78rem;color:#b91c1c;margin-top:2px">
                  🛡 <?= $h((string) $re['protector']) ?> इसे अभी रोके हुए है — इस ग्रह को शांत करने वाला कोई उपाय न करें,
                  वरना कवच हट जाएगा।
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
          <?php if (!empty($PRr['excluded'])): ?>
            <div style="font-size:.78rem;color:#78350f;margin-top:4px">
              ℹ️ <?= count((array) $PRr['excluded']) ?> और योग सिर्फ़ कमज़ोर संकेत हैं — इन्हें ऋण नहीं माना गया,
              इनका इलाज साधारण ग्रह-उपाय ही है।
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($rinPresent === []): ?>
          <div class="lk-card good"><div class="lk-card-h">✅ कोई पैतृक ऋण नहीं</div>
            <div class="lk-txt">इस कुंडली में नौ में से कोई पैतृक-ऋण योग नहीं बनता — शुभ संकेत।</div></div>
        <?php else: ?>
          <div class="lk-txt" style="margin-bottom:9px">नीचे वे ऋण हैं जो <b>इस कुंडली में वास्तव में बनते हैं</b> (ग्रह-भाव योग सिद्ध) — इनका अशुभ फल व मुक्ति-उपाय दिया गया है।</div>
          <?php foreach ($rinPresent as $S): ?>
            <div class="lk-card bad" data-app="1">
              <div class="lk-card-h">⚠ <?= $h((string) $S['rin']) ?> <?= $pill('बनता है', 'p') ?>
                <?php if (($S['mode'] ?? 'any') === 'all'): ?><span class="lk-pill" style="background:#dcfce7;color:#166534">पूरा नियम मिला</span><?php endif; ?>
              </div>
              <div class="lk-sub" style="color:#991b1b"><b>योग सिद्ध:</b> <?= $h(implode('; ', $S['matched'])) ?></div>
              <?php /* यह पंक्ति कभी नहीं छपनी चाहिए: "व" वाला नियम अधूरा हो तो ऋण
                       बनता ही नहीं। छपे तो मिलान में कहीं गड़बड़ है — और यही जाँच
                       RN-1 पकड़ती है। इसे छिपाना आसान था, पर तब ग़लती चुपचाप चलती। */ ?>
              <?php /* सिर्फ़ "व" वाले नियमों पर — "या" वाले नियम में बाक़ी ग्रहों का
                       न मिलना सामान्य है, वहाँ एक ही काफ़ी है। */ ?>
              <?php if (($S['mode'] ?? 'any') === 'all' && !empty($S['missing'])): ?>
                <div class="lk-sub" style="color:#7f1d1d"><b>⚠ नहीं मिले:</b> <?= $h(implode(', ', (array) $S['missing'])) ?>
                  <span style="font-size:.76rem">(नियम अधूरा है — फिर भी "बनता है" कहा गया, यह जाँचने योग्य है)</span></div>
              <?php endif; ?>
              <div class="lk-sub"><b>पहचान-नियम:</b> <?= $h((string) $S['pehchan']) ?></div>
              <?php if (trim((string) $S['ashubh_grah']) !== ''): ?><div class="lk-sub"><b>अशुभ होने वाला ग्रह:</b> <?= $h((string) $S['ashubh_grah']) ?></div><?php endif; ?>

              <?php /* ══════ यह हिस्सा सबसे ज़्यादा नुक़सान करने वाला था ══════
                   पुस्तक "संकेत" के नीचे पहचानने के चिह्न देती है — पर वे भूतकाल में
                   लिखे हैं ("हत्या की होगी", "धोखा किया हो", "घर धोखे से लिया होगा")।
                   उन्हें सीधे छाप देना पढ़ने वाले पर **अपराध का आरोप** है, वह भी एक
                   ग्रह की बैठक के आधार पर। ये असल में मिलान के सवाल हैं: ज्योतिषी
                   पूछता है कि ऐसा कुछ हुआ है या नहीं, और तभी ऋण की पुष्टि होती है।
                   इसलिए अब यह हिस्सा सवाल के रूप में, साफ़ लेबल के साथ आता है। */ ?>
              <?php if (trim((string) $S['sanket']) !== ''): ?>
                <div style="border:1px solid #c7d2fe;background:#eef2ff;border-radius:9px;padding:8px 11px;margin-top:6px">
                  <div style="font-weight:700;font-size:.82rem;color:#3730a3">❓ पहचान के चिह्न — जातक से मिलान करें</div>
                  <div style="font-size:.78rem;color:#3730a3;margin:2px 0 4px">
                    ये <b>आरोप नहीं</b> हैं। पुस्तक इन्हें पहचान के लिए गिनाती है — इनमें से कुछ
                    सचमुच हुआ हो, तभी ऋण की पुष्टि मानी जाती है। कुछ न मिले तो यह योग
                    रहते हुए भी ऋण नहीं माना जाता।
                  </div>
                  <div style="font-size:.83rem;line-height:1.6;color:#312e81"><?= $h((string) $S['sanket']) ?></div>
                </div>
              <?php endif; ?>

              <?php /* अशुभ फल शर्त के साथ — किताब ख़ुद कहती है कि यह चुकाया जा सकता
                       है; इसी आधार पर नीचे उपाय दिया गया है। उसे अटल बताकर छापना उसी
                       किताब का खंडन है। */ ?>
              <?php if (trim((string) $S['ashubh_phal']) !== ''): ?>
                <div class="lk-txt" style="color:#991b1b;margin-top:6px">
                  <b>अगर चिह्न मिलें और उपाय न किया जाए — किताब यह कहती है:</b>
                  <div style="margin-top:2px"><?= $h((string) $S['ashubh_phal']) ?></div>
                  <div style="font-size:.78rem;color:#78350f;margin-top:3px">
                    यह अटल नहीं है। लाल किताब का पूरा ढाँचा इसी पर खड़ा है कि ऋण <b>चुकाया जा सकता है</b> —
                    इसीलिए नीचे उपाय है।
                  </div>
                </div>
              <?php endif; ?>
              <?= $remBlock([(string) $S['upay']], $S['rin'] . ' — ऋण-मुक्ति उपाय') ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php /* "व" वाले नियम में कुछ ग्रह मिले पर सब नहीं — ऐसा योग छिपाया नहीं
                 जाता, पर उसे "बनता है" भी नहीं कहा जाता। ज्योतिषी के लिए यह जानना
                 काम का है कि नियम कितना पास से चूका। */ ?>
        <?php $rinPartial = array_values(array_filter($rinAbsent,
                static fn ($x) => ($x['mode'] ?? 'any') === 'all' && !empty($x['matched']))); ?>
        <?php if ($rinPartial !== []): ?>
          <div class="lk-card" style="border-color:#fde68a;background:#fffbeb">
            <div class="lk-card-h" style="color:#92400e">◐ अधूरा मिलान — नियम पूरा नहीं हुआ (<?= count($rinPartial) ?>)</div>
            <div class="lk-txt" style="font-size:.8rem;color:#78350f">
              इन नियमों में पुस्तक <b>सभी</b> ग्रह माँगती है ("व" = और), और यहाँ कुछ ही मिले।
              इसलिए ये ऋण <b>नहीं बनते</b> — इनका फल इस कुंडली पर नहीं पढ़ा जाता।
            </div>
            <?php foreach ($rinPartial as $S): ?>
              <div class="lk-txt" style="font-size:.81rem;margin-top:4px;padding-left:10px;border-left:3px solid #fcd34d">
                <b><?= $h((string) $S['rin']) ?></b> — मिले: <?= $h(implode('; ', (array) $S['matched'])) ?>
                · नहीं मिले: <b><?= $h(implode(', ', (array) ($S['missing'] ?? []))) ?></b>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($rinAbsent !== []): ?>
          <details style="margin-top:8px">
            <summary style="cursor:pointer;font-size:.8rem;color:#94a3b8">शेष ऋण — इस कुंडली में नहीं बनते (<?= count($rinAbsent) ?>)</summary>
            <?php foreach ($rinAbsent as $S): ?>
              <div style="border:1px solid #e5e7eb;border-radius:8px;padding:6px 11px;margin:6px 0;font-size:.8rem;color:#475569">
                ✓ <b><?= $h((string) $S['rin']) ?></b> — <?= $h((string) $S['pehchan']) ?>
              </div>
            <?php endforeach; ?>
          </details>
        <?php endif; ?>
      </div>

      <!-- ===== SADE SATI / DHAIYA ===== -->
      <div class="lk-view" data-lk="sadesati">
        <?php $ss = $lk['sadesati']; $ssPhase = (int) ($ss['running_phase'] ?? 0); ?>
        <h3 class="lk-h">साढ़े साती / ढैय्या — चन्द्र राशि <?= $h((string) $ss['rashi_hi']) ?></h3>
        <!-- यह पूरा तंत्र लाल किताब की अपनी व्याकरण पर चलता है; साढ़े साती इकलौता
             अपवाद है — इसकी पहचान वैदिक गोचर से होती है। इसलिए स्रोत का लेबल
             ज़रूरी है, ताकि पढ़ने वाला जाने कि यह बात कहाँ से आई। उपाय फिर भी
             लाल किताब का ही रहता है, और यह किसी भाव-आधारित फल को काट नहीं सकती। -->
        <div style="border:1px solid #ddd6fe;background:#f5f3ff;border-radius:9px;padding:8px 11px;margin-bottom:9px;font-size:.82rem;line-height:1.6;color:#4c1d95">
          <b>वैदिक आधार पर</b> — साढ़े साती/ढैय्या की पहचान जन्म-चन्द्र राशि पर शनि के गोचर से होती है,
          जो वैदिक विधि है; उपाय लाल किताब के ही हैं। यह एक <b>तय अवधि</b> है और समाप्त होती है —
          इसका असर कितना भारी पड़ेगा, यह आपकी कुंडली में शनि की अपनी हालत पर निर्भर है।
        </div>

        <?php if (!empty($ss['running'])): ?>
          <div class="lk-card bad">
            <div class="lk-card-h">🔴 अभी <?= $h((string) $ss['running_kind']) ?> चल रही है<?= $ssPhase ? ' — चरण ' . $ssPhase : '' ?></div>
            <div class="lk-txt">नीचे <?= $ssPhase ? '<b>चालू चरण</b> लाल में चिह्नित है; उसके' : 'चरण-वार' ?> उपाय प्राथमिकता से करें।</div>
          </div>
        <?php else: ?>
          <div class="lk-card good">
            <div class="lk-card-h">✅ इस समय साढ़े साती / ढैय्या नहीं चल रही</div>
            <div class="lk-txt">नीचे भविष्य/संदर्भ हेतु चरण-वार पहचान व उपाय दिए हैं।</div>
          </div>
        <?php endif; ?>

        <div class="lk-card">
          <div class="lk-card-h">साढ़े साती पहचान</div>
          <div class="lk-txt"><b>शनि इन राशियों पर:</b> <?= $h((string) $ss['pehchan']) ?></div>
          <?php if (trim((string) $ss['note']) !== ''): ?><div class="lk-sub"><?= $h((string) $ss['note']) ?></div><?php endif; ?>
        </div>
        <?php foreach ($ss['upay'] as $i => $u): $cur = ($ss['running_kind'] === 'साढ़े साती' && $ssPhase === $i + 1); ?>
          <div class="lk-card <?= $cur ? 'bad' : '' ?>">
            <div class="lk-card-h" style="font-size:.86rem"><?= $cur ? '🔴 (चालू) ' : '' ?><?= $h((string) $u['charan']) ?></div>
            <?= $remBlock([(string) $u['upay']], 'चरण उपाय') ?>
          </div>
        <?php endforeach; ?>
        <div class="lk-card">
          <div class="lk-card-h">ढैय्या पहचान</div>
          <div class="lk-txt"><b>शनि इन राशियों पर:</b> <?= $h((string) $ss['dhaiya_pehchan']) ?></div>
        </div>
        <?php foreach ($ss['dhaiya_upay'] as $u): ?>
          <div class="lk-card <?= $ss['running_kind'] === 'ढैय्या' ? 'bad' : '' ?>">
            <div class="lk-card-h" style="font-size:.86rem"><?= $ss['running_kind'] === 'ढैय्या' ? '🔴 ' : '' ?><?= $h((string) $u['dhaiya']) ?></div>
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
          <?php if ($mg['is'] && trim((string) $mg['mars_effect']) !== ''): ?>
            <div class="lk-txt" style="color:#991b1b;margin-top:3px"><b>प्रभाव:</b> मंगल का यह स्थान <?= $h((string) $mg['mars_effect']) ?> असर डालता है।</div>
          <?php endif; ?>
          <?php /* ══════ "दोष है" अकेला कुछ नहीं बताता ══════
               तीव्रता का क्रम इसी पन्ने पर नीचे लिखा है — "सप्तम भाव में प्रभाव
               सबसे अधिक, बारहवें में सबसे कम" — पर बरता नहीं जाता था। नतीजा:
               बारहवें का मंगल भी उतना ही डरावना पढ़ा जाता था जितना सातवें का,
               जबकि पुस्तक ख़ुद उन्हें बराबर नहीं मानती। */ ?>
          <?php if ($mg['is']): ?>
            <?php $tv = (string) ($mg['tivrata'] ?? '');
                  $tvCol = $tv === 'सबसे अधिक' ? '#991b1b' : ($tv === 'सबसे कम' ? '#166534' : '#92400e'); ?>
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:8px 11px;margin-top:6px">
              <div style="font-weight:800;font-size:.82rem;color:<?= $tvCol ?>">
                📏 तीव्रता — <?= $h($tv) ?>
                <span style="font-weight:500;font-size:.76rem;color:#64748b">(पुस्तक का अपना क्रम: 7 &gt; 1 &gt; 4 · 8 &gt; 12)</span>
              </div>
              <div style="font-size:.83rem;line-height:1.6;color:#334155;margin-top:2px">
                <?php if ($tv === 'सबसे कम'): ?>
                  मंगल बारहवें भाव में है — पुस्तक इसी स्थान को <b>सबसे हल्का</b> मानती है।
                  "मंगलीक" शब्द से घबराने की ज़रूरत नहीं; यह वही भारी दोष नहीं है जो सातवें भाव का होता है।
                <?php elseif ($tv === 'सबसे अधिक'): ?>
                  मंगल सातवें भाव में है — यही वह स्थान है जिसे पुस्तक <b>सबसे भारी</b> कहती है,
                  और असर मुख्यतः दाम्पत्य पर पढ़ा जाता है।
                <?php else: ?>
                  यह मध्यम श्रेणी का है — न सबसे भारी, न सबसे हल्का।
                <?php endif; ?>
              </div>
              <?php /* परिहार की जो शर्तें इसी कुंडली से जाँची जा सकती हैं, वे जाँच
                       ली जाती हैं — सूची छापकर छोड़ देना पढ़ने वाले पर काम डालना है। */ ?>
              <?php if (!empty($mg['parihar_hit'])): ?>
                <div style="margin-top:5px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:6px 9px;font-size:.83rem;color:#14532d">
                  ✅ <b>परिहार लागू:</b> <?= $h(implode(' · ', (array) $mg['parihar_hit'])) ?> —
                  पुस्तक के अनुसार ऐसी स्थिति में दोष <b>प्रायः समाप्त</b> माना जाता है।
                </div>
              <?php else: ?>
                <div style="margin-top:5px;font-size:.8rem;color:#475569">
                  इस कुंडली की अपनी परिहार-शर्तें (मंगल की राशि + भाव) यहाँ पूरी नहीं होतीं।
                  बाक़ी परिहार दूसरी कुंडली के हैं — वे <b>मिलान के समय</b> ही देखे जाते हैं।
                </div>
              <?php endif; ?>
              <div style="margin-top:5px;font-size:.79rem;color:#3730a3;background:#eef2ff;border:1px solid #c7d2fe;border-radius:8px;padding:6px 9px">
                ⚖ यह विचार <b>वैदिक आधार पर</b> है, लाल किताब की अपनी व्याकरण से नहीं।
                और पुस्तक स्वयं कहती है कि केवल कन्या को दोषी ठहराना <b>पक्षपात है</b> —
                यह दोष लड़का-लड़की दोनों पर एक-सा पढ़ा जाता है।
              </div>
            </div>
          <?php endif; ?>
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

      <!-- ===== AGE-CYCLE (Lal Kitab's own timing system) ===== -->
      <div class="lk-view" data-lk="agecycle">
        <?php
        $AC = $lk['age_cycle'] ?? null;
        $tcol = static fn (string $t): string => $t === 'pos' ? '#16a34a' : ($t === 'neg' ? '#dc2626' : ($t === 'info' ? '#2563eb' : '#d97706'));
        $tbg  = static fn (string $t): string => $t === 'pos' ? '#f0fdf4;border-color:#86efac' : ($t === 'neg' ? '#fef2f2;border-color:#fca5a5' : ($t === 'info' ? '#eff6ff;border-color:#93c5fd' : '#fffbeb;border-color:#fcd34d'));
        $vToneOf = static fn (string $v): string => $v === 'शुभ' ? 'pos' : ($v === 'अशुभ' ? 'neg' : 'mix');
        ?>
        <h3 class="lk-h">🕰️ आयु-दशा (Lal Kitab Age Timeline / Dasha)</h3>
        <div class="lk-txt" style="margin-bottom:10px">लाल किताब में विंशोत्तरी दशा नहीं चलती। इसकी अपनी <b>35-साला ग्रह-चक्र दशा</b> है — एक <b>स्थिर व सार्वभौमिक</b> क्रम जो हर व्यक्ति में जन्म से एक जैसा शुरू होता है और जीवन-भर 35-35 वर्ष पर दोहराता है (1–35, 36–70, 71–105…)।</div>
        <?php if ($AC === null): ?>
          <div class="lk-txt">आयु उपलब्ध न होने से दशा-चक्र सीमित है।</div>
        <?php else: ?>

        <?php $dn = $AC['dasha_now'] ?? null; ?>
        <?php if ($dn !== null): ?>
          <!-- ==== अभी चल रही दशा ==== -->
          <div class="lk-card" style="border-color:#f59e0b;background:#fffbeb">
            <div class="lk-card-h" style="font-size:1rem">▶ अभी चल रही दशा — <span style="color:<?= $tcol($dn['tone']) ?>"><?= $h((string) $dn['hi']) ?></span>
              <span class="lk-pill" style="background:#eef2ff;color:#3730a3">आयु <?= (int) $dn['from'] ?>–<?= (int) $dn['to'] ?> वर्ष</span>
              <?php if ($dn['house_ord'] !== ''): ?><span class="lk-pill" style="background:#f1f5f9;color:#475569"><?= $h((string) $dn['house_ord']) ?> भाव में</span><?php endif; ?>
              <span class="lk-pill" style="<?= $dn['verdict'] === 'शुभ' ? 'background:#dcfce7;color:#166534' : ($dn['verdict'] === 'अशुभ' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $dn['verdict']) ?></span>
              <?php if (!empty($dn['asleep'])): ?><span class="lk-pill" style="background:#f1f5f9;color:#64748b">😴 सुप्त</span><?php endif; ?>
              <span class="lk-pill" style="background:#e0e7ff;color:#3730a3">चक्र <?= (int) $dn['cycle'] ?></span>
            </div>
            <div class="lk-sub">इस दशा के <b><?= (int) $dn['elapsed'] ?></b> वर्ष बीत चुके · <b><?= (int) $dn['remaining'] ?></b> वर्ष शेष</div>
            <div class="lk-txt" style="margin-top:4px;line-height:1.55">📖 <b>भविष्यवाणी:</b> <?= $h((string) $dn['pred']) ?></div>
            <?php if (trim((string) $dn['do']) !== '' || trim((string) $dn['dont']) !== ''): ?>
              <div style="margin-top:4px;font-size:.8rem;line-height:1.55">
                <?php if (trim((string) $dn['do']) !== ''): ?><span style="color:#166534;font-weight:700">✅ करें:</span> <?= $h((string) $dn['do']) ?><?php endif; ?>
                <?php if (trim((string) $dn['dont']) !== ''): ?><br><span style="color:#991b1b;font-weight:700">⛔ न करें:</span> <?= $h((string) $dn['dont']) ?><?php endif; ?>
              </div>
            <?php endif; ?>
            <?php if (!empty($dn['remedies'])): ?>
              <div style="margin-top:6px;background:#fff;border:1px solid #fde68a;border-radius:7px;padding:5px 9px;font-size:.8rem;line-height:1.55"><span style="color:#92400e;font-weight:700">🪔 उपाय:</span> <?= $h(implode(' · ', $dn['remedies'])) ?></div>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- ==== वर्तमान 35-वर्षीय चक्र ==== -->
        <h3 class="lk-h" style="font-size:.9rem;margin-top:12px">🎡 वर्तमान 35-वर्षीय चक्र — पूरा पहिया</h3>
        <div class="lk-txt" style="margin-bottom:7px;color:#64748b">क्रम स्थिर है: शनि → राहु → केतु → गुरु → सूर्य → चन्द्र → शुक्र → मंगल → बुध (6·6·3·6·2·1·3·6·2 = 35 वर्ष)</div>
        <?php foreach (($AC['dasha_cycle'] ?? []) as $pd): $pc = $tcol($pd['tone']); ?>
          <div style="margin-bottom:8px;background:<?= $tbg($pd['tone']) ?>;border:1px solid;border-radius:8px;padding:7px 10px<?= $pd['active'] ? ';box-shadow:0 0 0 2px #f59e0b' : '' ?>">
            <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center">
              <span style="width:9px;height:9px;border-radius:50%;background:<?= $pc ?>"></span>
              <b style="font-size:.88rem;color:<?= $pc ?>"><?= $h((string) $pd['hi']) ?> दशा</b>
              <span class="lk-pill" style="background:#fff;border:1px solid #e2e8f0;color:#334155">आयु <?= (int) $pd['from'] ?>–<?= (int) $pd['to'] ?> (<?= (int) $pd['years'] ?> वर्ष)</span>
              <?php if ($pd['house_ord'] !== ''): ?><span class="lk-pill" style="background:#fff;border:1px solid #e2e8f0;color:#475569"><?= $h((string) $pd['house_ord']) ?> भाव</span><?php endif; ?>
              <span class="lk-pill" style="<?= $pd['verdict'] === 'शुभ' ? 'background:#dcfce7;color:#166534' : ($pd['verdict'] === 'अशुभ' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $pd['verdict']) ?></span>
              <?php if (!empty($pd['asleep'])): ?><span class="lk-pill" style="background:#f1f5f9;color:#64748b">😴 सुप्त</span><?php endif; ?>
              <?php if ($pd['active']): ?><span class="lk-pill" style="background:#fde68a;color:#92400e">▶ अभी</span><?php endif; ?>
            </div>
            <div class="lk-txt" style="margin-top:4px;line-height:1.55">📖 <?= $h((string) $pd['pred']) ?></div>
            <?php if (!empty($pd['remedies'])): ?>
              <div style="margin-top:5px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:4px 8px;font-size:.78rem;line-height:1.5"><span style="color:#92400e;font-weight:700">🪔 उपाय:</span> <?= $h(implode(' · ', $pd['remedies'])) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <!-- ==== अवस्था — कुण्डली के 4 टाइम-ज़ोन ==== -->
        <h3 class="lk-h" style="font-size:.9rem;margin-top:12px">🧭 अवस्था चक्र — कुण्डली की चार घड़ियाँ (Time-Zones)</h3>
        <div class="lk-txt" style="margin-bottom:7px;color:#64748b">भाव केवल "जीवन के विषय" नहीं, "जीवन की घड़ियाँ" भी हैं — अपनी तय उम्र आने पर ही पूरी तरह सक्रिय होती हैं। जिस अवस्था में सर्वाधिक ग्रह हों, वही जातक का <b>peak time</b>।</div>
        <?php if (!empty($AC['peak'])): ?>
          <div class="lk-card good" style="padding:8px 11px"><div class="lk-txt">⭐ <b>कुण्डली का केंद्र-बिंदु (Centre of Gravity):</b> <?= $h((string) $AC['peak']) ?> — जीवन की सबसे बड़ी घटनाएँ इसी खंड में।</div></div>
        <?php endif; ?>
        <?php foreach ($AC['stages'] as $st): ?>
          <div class="lk-card <?= $st['active'] ? 'good' : '' ?>" style="<?= $st['active'] ? 'border-color:#f59e0b;background:#fffbeb' : ($st['peak'] ? 'border-color:#86efac' : '') ?>">
            <div class="lk-card-h" style="font-size:.92rem">🌓 <?= $h((string) $st['name']) ?>
              <span class="lk-pill" style="background:#eef2ff;color:#3730a3"><?= (int) $st['from'] ?>–<?= (int) $st['to'] ?> वर्ष</span>
              <span class="lk-pill" style="background:#f1f5f9;color:#475569">भाव <?= $h(implode(', ', $st['houses'])) ?></span>
              <span class="lk-pill" style="background:#f8fafc;color:#64748b"><?= (int) $st['count'] ?> ग्रह</span>
              <?php if (!empty($st['peak'])): ?><span class="lk-pill" style="background:#dcfce7;color:#166534">⭐ peak</span><?php endif; ?>
              <?php if ($st['active']): ?><span class="lk-pill" style="background:#fde68a;color:#92400e">▶ वर्तमान</span><?php endif; ?>
            </div>
            <div class="lk-sub" style="font-weight:600;color:#7c2d12"><?= $h((string) $st['label']) ?></div>
            <div class="lk-txt"><?= $h((string) $st['theme']) ?></div>
            <?php if (!empty($st['planets'])): ?>
              <div class="lk-sub" style="margin-top:5px"><b>इस अवस्था के ग्रह:</b>
                <?php foreach ($st['planets'] as $pp): ?>
                  <span class="lk-pill" style="<?= $pp['verdict'] === 'शुभ' ? 'background:#dcfce7;color:#166534' : ($pp['verdict'] === 'अशुभ' ? 'background:#fee2e2;color:#991b1b' : 'background:#fef9c3;color:#854d0e') ?>"><?= $h((string) $pp['hi']) ?> (<?= (int) $pp['house'] ?>वें)</span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php if (trim((string) $st['note']) !== ''): ?>
              <div class="lk-reason" style="margin-top:6px"><?= $h((string) $st['note']) ?></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <!-- ==== आगामी आयु-रेखा (chronological events, कब क्या होगा) ==== -->
        <h3 class="lk-h" style="font-size:.9rem;margin-top:12px">📅 आगामी आयु-रेखा (<?= (int) $AC['age'] ?> → <?= (int) $AC['age'] + 25 ?> वर्ष) — कब क्या होगा</h3>
        <?php if (empty($AC['events'])): ?>
          <div class="lk-txt" style="color:#94a3b8">इस अवधि में कोई विशेष ग्रह-चक्र वर्ष नहीं।</div>
        <?php else: ?>
          <?php foreach ($AC['events'] as $ev):
            $evc = $tcol($ev['tone']); ?>
            <div style="display:flex;gap:10px;align-items:flex-start;padding:7px 0;border-bottom:1px dashed #eef0f3">
              <span style="min-width:70px;font-size:.78rem;font-weight:700;color:#475569;background:#f8fafc;border:1px solid #eef0f3;border-radius:7px;padding:3px 8px;text-align:center">आयु <?= (int) $ev['age'] ?></span>
              <span style="width:9px;height:9px;border-radius:50%;margin-top:6px;flex:none;background:<?= $evc ?>"></span>
              <span style="font-size:.83rem;color:#334155;line-height:1.45">
                <b style="color:<?= $evc ?>"><?= $h((string) $ev['kind']) ?>:</b> <?= $h((string) $ev['text']) ?>
                <?php if (!empty($ev['remedy'])): ?>
                  <span style="display:block;margin-top:2px;background:#fffbeb;border:1px solid #fde68a;border-radius:6px;padding:3px 7px;font-size:.76rem"><span style="color:#92400e;font-weight:700">🪔 उपाय:</span> <?= $h(implode(' · ', $ev['remedy'])) ?></span>
                <?php endif; ?>
              </span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>

        <!-- ग्रह-चक्र संदर्भ -->
        <details style="margin-top:12px">
          <summary style="cursor:pointer;font-weight:700;color:#9a3412;font-size:.85rem">🪐 ग्रह संदर्भ — दशा-खंड, सावधानी-वर्ष व जागृति</summary>
          <table class="lk-cmp" style="margin-top:8px">
            <tr><th>ग्रह</th><th>दशा-खंड (चक्र में)</th><th>सावधानी-वर्ष</th><th>जागृति व फल</th></tr>
            <?php foreach ($AC['planet_years'] as $py): ?>
              <tr><td><b><?= $h((string) $py['hi']) ?></b></td><td><?= $h((string) $py['dasha']) ?></td><td style="color:#991b1b"><?= $h((string) $py['ashubh']) ?></td><td style="font-size:.76rem"><?= $h((string) $py['jagega']) ?><br><span style="color:#64748b"><?= $h((string) $py['effect']) ?></span></td></tr>
            <?php endforeach; ?>
          </table>
        </details>
        <?php endif; ?>
      </div>

      <!-- ===== AYU / LONGEVITY ===== -->
      <div class="lk-view" data-lk="ayu">
        <?php $ay = $lk['ayu'];
          $ayOn = array_values(array_filter($ay['yoga'], static fn ($x) => !empty($x['applies'])));
          $ayOff = array_values(array_filter($ay['yoga'], static fn ($x) => empty($x['applies']))); ?>
        <h3 class="lk-h">आयु योग (Longevity) — इस कुंडली में लागू</h3>
        <div class="lk-note" style="margin-bottom:9px">लाल किताब में आयु का विचार ग्रहों की युति से होता है। इसे यहाँ <b>पाँच वर्गों</b> में दिखाया जाता है
          — बालारिष्ट · अल्प · मध्यम · दीर्घ · पूर्ण। (किसी भी अंक या "मृत्यु" के रूप में नहीं — यह केवल एक
          योग है, जिसका उपाय साथ में दिया गया है।)</div>
        <?php if ($ayOn === []): ?>
          <div class="lk-card"><div class="lk-txt">इस कुंडली में कोई विशेष आयु-योग नहीं बनता (कोई ग्रह-युति नहीं)।</div></div>
        <?php else: foreach ($ayOn as $y): $isBala = mb_strpos((string) $y['band'], 'बालारिष्ट') !== false; ?>
          <div class="lk-card <?= $isBala ? 'bad' : '' ?>" data-app="1">
            <div class="lk-card-h" style="font-size:.9rem"><?= $h((string) $y['band']) ?> <?= $pill('लागू', 'a') ?></div>
            <div class="lk-sub"><?= $h((string) $y['yog']) ?><?php if (trim((string) $y['why']) !== ''): ?> <span style="color:#166534">(<?= $h((string) $y['why']) ?>)</span><?php endif; ?></div>
            <?php if (trim((string) $y['band_note']) !== ''): ?><div class="lk-reason"><?= $h((string) $y['band_note']) ?></div><?php endif; ?>
            <?php if ($isBala): ?><div class="lk-sub" style="color:#991b1b">⚠️ सावधानी व उपाय आवश्यक — घबराने की बात नहीं, समाधान उपलब्ध है।</div><?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
        <?php if ($ayOff !== []): ?>
          <details style="margin-top:6px"><summary style="cursor:pointer;font-size:.8rem;color:#94a3b8">अन्य आयु-योग नियम — इस कुंडली में लागू नहीं (<?= count($ayOff) ?>)</summary>
            <?php foreach ($ayOff as $y): ?><div style="font-size:.78rem;color:#94a3b8;margin:2px 0"><?= $h((string) $y['band'] ?: '—') ?> — <?= $h((string) $y['yog']) ?></div><?php endforeach; ?>
          </details>
        <?php endif; ?>
        <div class="lk-card">
          <div class="lk-card-h">ग्रह चक्र — जीवन में प्रभावशाली वर्ष</div>
          <?php foreach ($ay['chakra'] as $c): ?>
            <div class="lk-sub" style="margin-bottom:5px">
              <b><?= $h((string) $c['hi']) ?>:</b> दशा-खंड <?= $h((string) $c['prabhav']) ?>
              <?php if (trim((string) $c['ashubh']) !== ''): ?> · <span style="color:#991b1b">अशुभ वर्ष: <?= $h((string) $c['ashubh']) ?></span><?php endif; ?>
              <?php if (trim((string) $c['vishesh']) !== ''): ?><br><span style="color:#64748b">विशेष: <?= $h((string) $c['vishesh']) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- ===== HEALTH / PROGENY ===== -->
      <div class="lk-view" data-lk="health">
        <?php $hl = $lk['health']; ?>
        <h3 class="lk-h">रोग / संतान उपाय (Health &amp; Progeny)</h3>
        <!-- यही एक जगह है जहाँ आत्मविश्वास से भरा इंजन असली नुक़सान कर सकता है।
             इसलिए यह पंक्ति स्थायी है, शैली की पसंद नहीं। -->
        <div style="border:1px solid #bfdbfe;background:#eff6ff;border-radius:9px;padding:8px 11px;margin-bottom:9px;font-size:.82rem;line-height:1.6;color:#1e3a8a">
          🩺 <b>ध्यान दें:</b> यहाँ दी बातें सिर्फ़ सावधानी के संकेत हैं — किसी रोग की पहचान या भविष्यवाणी नहीं।
          जाँच और इलाज डॉक्टर से ही कराएँ, और चल रहा इलाज किसी उपाय के भरोसे बंद न करें।
        </div>
        <?php if (!empty($hl['afflicted'])): ?>
          <div class="lk-card bad">
            <div class="lk-card-h">⚠ इस कुंडली में ध्यान देने योग्य ग्रह</div>
            <div class="lk-txt">इन अशुभ ग्रहों से सम्बन्धित रोग/विषयों पर विशेष सावधानी रखें — इनका उपाय "ग्रह फल" में देखें:</div>
            <div style="margin-top:5px;display:flex;flex-wrap:wrap;gap:4px">
              <?php foreach ($hl['afflicted'] as $af): ?>
                <span class="lk-pill" style="background:#fee2e2;color:#991b1b"><?= $h((string) $af['hi']) ?> (<?= $h((string) $af['house_ord']) ?> भाव)</span>
              <?php endforeach; ?>
            </div>
          </div>
        <?php else: ?>
          <div class="lk-card good"><div class="lk-txt">✅ कोई ग्रह विशेष अशुभ नहीं — स्वास्थ्य की दृष्टि से इस कुंडली में बड़ी चेतावनी नहीं।</div></div>
        <?php endif; ?>
        <div class="lk-txt" style="margin:8px 0 4px;color:#64748b">सामान्य रोग-निवारण व संतान-सम्बंधी उपाय:</div>
        <?php foreach (($hl['groups'] ?? []) as $varg => $items): ?>
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

      <!-- ===== VARSH KUNDALI (annual chart + prediction) ===== -->
      <div class="lk-view" data-lk="varsh">
        <?php $lkAge = (int) ($lk['age'] ?? 0); ?>
        <h3 class="lk-h">📅 वर्ष कुंडली (Lal Kitab Varsh Kundali)</h3>
        <div class="lk-txt" style="margin-bottom:9px">
          यह जन्म-कुंडली नहीं — <b>चुने हुए वर्ष की लाल-किताब वर्ष-कुंडली</b> है। वर्ष कुंडली ज्ञान चक्र के अनुसार
          हर आयु-वर्ष में जन्म-भावों का फल भिन्न भावों में सक्रिय होता है; नीचे उसी घूर्णित कुंडली पर लाल-किताब
          नियम, भविष्यवाणी, उपाय व करें/न करें दिए गए हैं। आयु बदलकर किसी भी वर्ष की वर्ष-कुंडली देखें।
        </div>

        <!-- age / year selector (mirrors the Vedic Varshaphal picker) -->
        <div class="lkv-picker" style="display:flex;flex-wrap:wrap;align-items:end;gap:10px;margin-bottom:10px">
          <label style="display:flex;flex-direction:column;gap:3px;font-size:.8rem;color:#475569">
            <span>आयु / Age (वर्ष)</span>
            <span style="display:flex;align-items:center;gap:4px">
              <button type="button" id="lkv-prev" class="lkv-step" aria-label="पिछला वर्ष" style="width:32px;height:32px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;font-size:1.1rem;font-weight:700;cursor:pointer">−</button>
              <input type="number" id="lkv-age" min="1" max="96" value="<?= $lkAge > 0 ? $lkAge : 1 ?>" style="width:74px;border:1px solid #cbd5e1;border-radius:7px;padding:6px 8px;text-align:center;font-weight:700">
              <button type="button" id="lkv-next" class="lkv-step" aria-label="अगला वर्ष" style="width:32px;height:32px;border:1px solid #cbd5e1;border-radius:7px;background:#f8fafc;font-size:1.1rem;font-weight:700;cursor:pointer">+</button>
            </span>
          </label>
          <button type="button" id="lkv-show" style="background:#7c3aed;color:#fff;border:none;border-radius:8px;padding:8px 16px;font-weight:700;cursor:pointer">वर्ष कुंडली देखें</button>
          <span id="lkv-yearlab" style="font-size:.82rem;color:#0f766e;font-weight:600"></span>
          <span id="lkv-status" style="font-size:.78rem;color:#64748b"></span>
        </div>

        <!-- top small row: basic info chips + red→green शुभता signal bar
             (filled from the JSON response — mirrors the Vedic Varshaphal row) -->
        <div id="lkv-summary" style="margin-bottom:6px"></div>
        <div id="lkv-bar" style="margin-bottom:10px"></div>

        <!-- main row: LEFT = annual (varsh) chart · RIGHT = prediction
             (like the Vedic Varshaphal chart | prediction layout) -->
        <div class="lkv-main">
          <div class="lk-card" style="padding:8px;align-self:start">
            <div class="lk-card-h" style="font-size:.85rem">वर्ष कुंडली (Aries-fixed) — आयु <span id="lkv-agelab"><?= $lkAge > 0 ? $lkAge : 1 ?></span> वर्ष</div>
            <div id="lkv-chart" style="max-width:340px;margin:0 auto"></div>
            <div class="lk-sub" style="margin-top:6px;color:#64748b;font-size:.76rem">इस वर्ष प्रत्येक भाव में जन्म-कुंडली का जो भाव-फल सक्रिय है, ग्रह उसी अनुसार यहाँ स्थापित हैं।</div>
          </div>
          <!-- server-rendered prediction/remedy/do-dont fragment -->
          <div id="lkv-body"></div>
        </div>

        <!-- next row: the Lal Kitab (janam) teva chart — for reference, below -->
        <div class="lk-card" style="padding:8px;margin-top:12px">
          <div class="lk-card-h" style="font-size:.85rem">📕 लाल किताब जन्म कुंडली (Teva) — संदर्भ हेतु</div>
          <div id="lkv-janam" style="max-width:340px;margin:0 auto"></div>
          <div class="lk-sub" style="margin-top:6px;color:#64748b;font-size:.76rem">जन्म की स्थिर-मेष लाल किताब कुंडली — वर्ष-कुंडली से तुलना के लिए।</div>
        </div>
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

      <!-- ===== INTER-EFFECTS (कौन ग्रह किसको प्रभावित करता है) ===== -->
      <div class="lk-view" data-lk="inter">
        <h3 class="lk-h">ग्रह अंतर्संबंध — कौन ग्रह किसको प्रभावित कर रहा है</h3>
        <div class="lk-txt" style="margin-bottom:9px">लाल किताब में ग्रह एक-दूसरे का फल बदलते हैं। नीचे इस कुंडली के लागू सूत्रों से बना प्रभाव-नक्शा है — <span style="color:#166534">हरा = शुभ प्रभाव</span>, <span style="color:#991b1b">लाल = अशुभ प्रभाव</span>।</div>
        <?php /* ══════ असली रिश्ते, सिर्फ़ नामी सूत्र नहीं ══════
             पहले यह पन्ना केवल नाम-दर्ज सूत्रों की तालिका दिखाता था, इसलिए
             ज़्यादातर कुंडलियों पर लगभग ख़ाली रहता था — जबकि ग्रहों के बीच का असली
             लेन-देन (एक कमरे में बैठना, एकतरफ़ा दृष्टि, टक्कर) पहले से गिना जा
             चुका होता है। वही यहाँ खोलकर रखा जाता है, नुक़सान पहुँचाने वाले रिश्ते
             पहले। हर पंक्ति के आगे लिखा है कि इसका मतलब क्या है, और जहाँ इसी रिश्ते
             ने ग्रह का फ़ैसला बदला वहाँ वह भी दर्ज है — यही "कौन किसको" का जवाब है। */ ?>
        <?php
          $edges = [];
          foreach ((array) ($lk['planets'] ?? []) as $pi) {
              $me = (string) $pi['hi'];
              foreach ((array) ($pi['mates'] ?? []) as $mt) {
                  $rel = (string) ($mt['rel'] ?? 'सम');
                  $edges[] = [
                      'rank' => $rel === 'शत्रु' ? 0 : ($rel === 'मित्र' ? 2 : 3),
                      'kind' => 'युति', 'from' => (string) $mt['hi'], 'to' => $me,
                      'tone' => $rel === 'शत्रु' ? 'neg' : ($rel === 'मित्र' ? 'pos' : 'mix'),
                      'tag'  => $rel . ' — एक ही भाव में',
                      'what' => $rel === 'शत्रु'
                          ? $mt['hi'] . ' और ' . $me . ' एक ही कमरे में शत्रु की तरह बैठे हैं — दोनों के मामले आपस में उलझते हैं।'
                          : ($rel === 'मित्र'
                              ? $mt['hi'] . ' साथ बैठकर ' . $me . ' का काम आसान करता है।'
                              : $mt['hi'] . ' और ' . $me . ' साथ हैं, पर एक-दूसरे को न बढ़ाते हैं न घटाते।'),
                  ];
              }
              foreach ((array) ($pi['in_hits'] ?? []) as $ih) {
                  $kind = (string) ($ih['kind'] ?? '');
                  $from = implode(', ', (array) ($ih['planets_hi'] ?? []));
                  if ($from === '') { continue; }
                  $edges[] = [
                      'rank' => $kind === 'टकराव' ? 1 : 4,
                      'kind' => $kind, 'from' => $from, 'to' => $me,
                      'tone' => $kind === 'टकराव' ? 'neg' : ($kind === 'सहायता' ? 'pos' : 'mix'),
                      'tag'  => $kind . ' — ' . \AutoBusiness\Astro\LalKitab\LalKitabData::houseOrdinalHi((int) $ih['house']) . ' भाव से'
                          . (!empty($ih['weak']) ? ' (मारने वाला ख़ुद सोया — चोट आधी)' : ''),
                      'what' => $kind === 'टकराव'
                          ? $from . ' की मार ' . $me . ' पर पड़ती है — ' . $me . ' के मामले इसी से बिगड़ते हैं।'
                          : $from . ' की नज़र ' . $me . ' पर है — असर दूर से पड़ता है, सीधा नहीं।',
                  ];
              }
          }
          usort($edges, static fn ($a, $b) => [$a['rank'], $a['to']] <=> [$b['rank'], $b['to']]);
        ?>
        <?php if ($edges !== []): ?>
          <div class="lk-card" style="border-color:#c7d2fe;background:#eef2ff">
            <div class="lk-card-h" style="color:#3730a3">🕸 इस कुंडली के असली रिश्ते (<?= count($edges) ?>) <?= $srcTag('युति · एकतरफ़ा दृष्टि · टक्कर') ?></div>
            <div class="lk-note" style="margin-bottom:5px">नुक़सान पहुँचाने वाले रिश्ते पहले। ऊपर के दो-तीन ही असल में मायने रखते हैं।</div>
            <?php foreach ($edges as $e): $tc = $e['tone'] === 'pos' ? '#166534' : ($e['tone'] === 'neg' ? '#991b1b' : '#854d0e'); ?>
              <div class="lk-txt" style="margin:3px 0;padding-left:10px;border-left:3px solid <?= $tc ?>">
                <b style="color:<?= $tc ?>"><?= $h((string) $e['from']) ?> → <?= $h((string) $e['to']) ?></b>
                <span class="lk-pill" style="background:#fff;color:<?= $tc ?>;border:1px solid <?= $tc ?>33"><?= $h((string) $e['tag']) ?></span>
                <div style="font-size:.83rem;color:#334155"><?= $h((string) $e['what']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (empty($lk['inter'])): ?>
          <div class="lk-card"><div class="lk-txt">नाम-दर्ज विशेष सूत्रों में से इस कुंडली पर कोई लागू नहीं होता — ऊपर वाले रिश्ते ही चल रहे हैं।</div></div>
        <?php else: foreach ($lk['inter'] as $I): ?>
          <div class="lk-card">
            <div class="lk-card-h"><?= $h((string) $I['hi']) ?></div>
            <?php if (!empty($I['affects'])): ?>
              <div class="lk-sub" style="margin-top:2px"><b>➡ यह ग्रह इन पर प्रभाव डालता है:</b></div>
              <?php foreach ($I['affects'] as $a): $tc = $a['tone'] === 'pos' ? '#166534' : ($a['tone'] === 'neg' ? '#991b1b' : '#854d0e'); ?>
                <div class="lk-txt" style="margin:2px 0;padding-left:10px;border-left:3px solid <?= $tc ?>">
                  <b style="color:<?= $tc ?>"><?= $h((string) $I['hi']) ?> → <?= $h((string) $a['other']) ?></b> — <?= $h((string) $a['phal']) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if (!empty($I['affected_by'])): ?>
              <div class="lk-sub" style="margin-top:5px"><b>⬅ इस ग्रह पर इनका प्रभाव है:</b></div>
              <?php foreach ($I['affected_by'] as $a): $tc = $a['tone'] === 'pos' ? '#166534' : ($a['tone'] === 'neg' ? '#991b1b' : '#854d0e'); ?>
                <div class="lk-txt" style="margin:2px 0;padding-left:10px;border-left:3px dashed <?= $tc ?>">
                  <b style="color:<?= $tc ?>"><?= $h((string) $a['other']) ?> → <?= $h((string) $I['hi']) ?></b> — <?= $h((string) $a['phal']) ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- ===== SUPT (soya / impaired) + special weak states ===== -->
      <div class="lk-view" data-lk="supt">
        <?php $sp = $lk['special'] ?? ['ratandh' => false, 'neech' => []]; ?>
        <h3 class="lk-h">सोया / अपंग ग्रह व विशेष अवस्थाएँ</h3>
        <div class="lk-note" style="margin-bottom:9px">
          लाल किताब में ग्रह तब "<b>सोया</b>" होता है जब वह जिस भाव को (एकतरफ़ा दृष्टि से) देखता है वह
          पूरी तरह खाली हो। इससे गहरी अवस्थाएँ — गूंगा · बहरा · लंगड़ा · अंधा · मृत — एक-दूसरे पर चढ़ती हैं।
          <span style="color:#92400e">(सोया आपकी दी परिभाषा है; बाकी अवस्थाएँ अनुमानित नियम पर हैं।)</span>
        </div>

        <?php if (!empty($sp['ratandh'])): ?>
          <div class="lk-card bad" data-app="1">
            <div class="lk-card-h">🌑 रतौंध कुंडली</div>
            <div class="lk-txt"><b>📢 फल:</b> <?= $h((string) ($sp['ratandh_phal'] ?? '')) ?></div>
            <?= $remBlock($sp['ratandh_upay'] ?? [], 'रतौंध — उपाय') ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($sp['neech'])): ?>
          <div class="lk-card bad">
            <div class="lk-card-h">⬇ नीच ग्रह (भाव से)</div>
            <div class="lk-txt">भाव-आधारित नीच — फल दुर्बल; उपाय "ग्रह फल" में देखें:</div>
            <?php foreach ($sp['neech'] as $n): ?>
              <div class="lk-sub"><b><?= $h((string) $n['hi']) ?></b> — <?= $h((string) $n['house_ord']) ?> भाव में (नीच<?= ($n['tier'] ?? '') === 'गौण' ? ', गौण' : '' ?>)</div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <h3 class="lk-h" style="margin-top:14px">सोया / अपंग ग्रह</h3>
        <?php if (empty($lk['supt'])): ?>
          <div class="lk-card good"><div class="lk-txt">✅ कोई ग्रह सोया/अपंग नहीं — सभी ग्रह अपना फल दे रहे हैं।</div></div>
        <?php endif; ?>
        <?php /* ══════ कितने ग्रह अपंग हैं, यह ख़ुद एक ख़बर है ══════
             एकतरफ़ा व आगे-को-चलने वाली दृष्टि में "कोई भरा भाव इसे नहीं देखता"
             असाधारण हालत नहीं, आम हालत है — इसीलिए आधे से ज़्यादा ग्रह प्रायः किसी
             न किसी अवस्था में निकल आते हैं। जब नौ में से सात पर एक ही भारी लेबल
             लगा हो, तो लेबल कुछ बताता नहीं, सिर्फ़ डराता है। इसलिए ऊपर ही साफ़ लिख
             दिया जाता है कि इस कुंडली में यह कितना आम है, और कसौटी बदलने का रास्ता
             भी बता दिया जाता है। */ ?>
        <?php $suptN = count((array) ($lk['supt'] ?? [])); $totalP = count((array) ($lk['planets'] ?? [])) ?: 9;
              $kasauti = (string) (($lk['process']['niyam']['apang_kasauti']) ?? 'dheeli'); ?>
        <?php if ($suptN > 0): ?>
          <div class="lk-note" style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:9px;padding:8px 11px;margin-bottom:9px">
            इस कुंडली में <b><?= (int) $suptN ?></b> / <?= (int) $totalP ?> ग्रह किसी न किसी अवस्था में हैं।
            <?php if ($suptN >= 5): ?>
              इतना होना असामान्य नहीं है — लाल किताब की दृष्टि एकतरफ़ा है, इसलिए ज़्यादातर ग्रहों को
              कोई नहीं देखता। <b>इसे आफ़त न पढ़ें</b>; नीचे वही ग्रह पहले देखें जिनकी दशा चल रही है या
              जिनका उपाय ऊपर निचोड़ में आया है।
              <?php if ($kasauti !== 'sakht'): ?>
                <br><span style="color:#92400e">चाहें तो सेटिंग में <b>अपंग अवस्था की कसौटी = सख़्त</b> करके देखें —
                तब वही ग्रह अपंग गिने जाते हैं जिनका कोई साथी भी नहीं।</span>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <?php foreach ($lk['supt'] as $s): $bS = $LKB[(string) $s['hi']] ?? null; ?>
          <div class="lk-card bad" data-app="1">
            <div class="lk-card-h">
              <?= $h((string) $s['hi']) ?> — <?= $h((string) $s['house_ord']) ?> भाव में
              <span class="lk-pill" style="background:#fee2e2;color:#991b1b">😴 <?= $h((string) $s['state']) ?></span>
              <?php if (($s['darja'] ?? '') !== 'final'): ?><span class="lk-pill" style="background:#fef3c7;color:#92400e">अनुमानित</span><?php endif; ?>
            </div>
            <?php /* पहले यहाँ सिर्फ़ अवस्था का जड़ा हुआ पाठ छपता था — वही वाक्य पाँच
                     ग्रहों पर ज्यों का त्यों, "सबसे दुर्लभ श्रेणी" एक ही पन्ने पर दो
                     बार। निचोड़ इसकी जगह इस कुंडली की बात कहता है: जागा या नहीं,
                     किसने सुलाया, चाबी किसके पास, और अब करना क्या है। */ ?>
            <?= $briefBox($bS) ?>
            <?php /* यह पाठ पुस्तक का है, इसलिए बदला नहीं जाता। पर उसमें कुछ अवस्थाओं
                     को "सबसे दुर्लभ" कहा गया है, और वही पंक्ति एक ही पन्ने पर दो बार
                     छप सकती है — तब वह ख़ुद अपना खंडन करती है। इसलिए जहाँ अवस्था
                     दोहराई गई हो, वहीं गिनती लिख दी जाती है। */ ?>
            <?php $sameState = 0; foreach ((array) ($lk['supt'] ?? []) as $s2) { if (($s2['state'] ?? '') === ($s['state'] ?? '')) { $sameState++; } } ?>
            <div class="lk-txt" style="font-size:.8rem;color:#64748b"><b>अवस्था का सामान्य अर्थ:</b> <?= $h((string) $s['phal']) ?>
              <?php if ($sameState > 1): ?>
                <span style="color:#92400e">(इस कुंडली में <?= (int) $sameState ?> ग्रह इसी अवस्था में हैं — इसलिए इसे "दुर्लभ" न पढ़ें।)</span>
              <?php endif; ?>
            </div>
            <?php if (!empty($s['empty_aspected'])): ?>
              <div class="lk-reason">यह जिन खाली भावों को देखता है:
                <?php foreach ($s['empty_aspected'] as $ea): ?>
                  <?= $h((string) $ea['ord']) ?> भाव<?= trim((string) $ea['chaabi']) !== '' ? ' (चाबी: ' . $h((string) $ea['chaabi']) . ')' : '' ?><?= $ea === end($s['empty_aspected']) ? '' : ' · ' ?>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <?php /* "22 वर्ष के उपरान्त जागेगा" 45 साल के आदमी को दिखाना ग़लत है —
                     वह उम्र निकल चुकी है। उम्र निकल चुकी हो तो सवाल भविष्य का नहीं,
                     अतीत का है: वह घटना हुई थी या नहीं। */ ?>
            <?php if (trim((string) $s['jagega']) !== ''):
                  $wA = $bS['kaun'] ?? null; $gone = ($bS['state'] ?? '') === 'जाग चुका'; ?>
              <div class="lk-sub">
                <b><?= $gone ? 'जागने की उम्र निकल चुकी:' : 'अपने-आप कब जागेगा:' ?></b>
                <?= $h((string) $s['aayu']) ?> — <?= $h((string) $s['jagega']) ?>
                <?php if ($gone): ?>
                  <span style="color:#1e3a8a">→ यह बात हो चुकी है या नहीं, यह जातक से पूछकर ही तय होगा।</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
            <?= $remBlock($s['upay'] ?? [], $s['state'] . ' — उपाय') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- ===== DRISHTI (one-way house aspects) ===== -->
      <div class="lk-view" data-lk="drishti">
        <h3 class="lk-h">भाव दृष्टि व टक्कर (एकतरफ़ा)</h3>
        <div class="lk-note" style="margin-bottom:9px">
          लाल किताब की दृष्टि <b>एकतरफ़ा व आगे की ओर</b> होती है (वैदिक जैसी नहीं): भाव 1→7 · 2→6 · 3→9,11 ·
          4→10 · 5→9 · 6→12। भाव 7–12 आगे किसी को नहीं देखते। एकमात्र अपवाद भाव 8 की <b>उल्टी टक्कर</b> 8→2।
          साथ ही हर ग्रह अपने से <b>आठवें</b> भाव के ग्रह को टक्कर मारकर खराब करता है।
        </div>
        <?php /* ══════ जो सचमुच लग रही है, वही पहले ══════
             यह पन्ना पहले बारहों भाव की मशीनी सूची था, और उसमें ज़्यादातर पंक्तियाँ
             *अभाव* की थीं — "देखता है: —" और "वहाँ कोई ग्रह नहीं"। खाली पंक्तियों
             के बीच वह एक टक्कर दब जाती थी जो सचमुच लग रही है। अब जीवित संबंध ऊपर,
             अर्थ के साथ; और जिन भावों में कुछ हो ही नहीं रहा, वे एक पंक्ति में
             समेट दिए जाते हैं। */ ?>
        <?php
          $live = []; $quiet = [];
          foreach ((array) ($lk['drishti'] ?? []) as $d) {
              $seesP  = (array) ($d['drishti_p'] ?? []);
              $hitsP  = (array) ($d['takkar_p'] ?? []);
              if ($seesP === [] && $hitsP === []) { $quiet[] = $d; continue; }
              $live[] = $d;
          }
          // मारने वाले पहले — टक्कर दृष्टि से भारी है
          usort($live, static fn ($a, $b) => (empty($b['takkar_p']) ? 0 : 1) <=> (empty($a['takkar_p']) ? 0 : 1));
          $coll = (array) ($lk['collisions'] ?? []);
        ?>
        <div class="lk-card" style="border-color:#c7d2fe;background:#eef2ff">
          <div class="lk-card-h" style="color:#3730a3">🎯 इस कुंडली में असल में क्या लग रहा है</div>
          <div class="lk-txt" style="color:#3730a3">
            <b><?= count($live) ?></b> भाव सचमुच किसी को देख या मार रहे हैं;
            <b><?= count($quiet) ?></b> भावों से कुछ नहीं जा रहा।
            <?php if ($coll !== []): ?>साथ ही <b><?= count($coll) ?></b> विशेष टक्करें बनी हुई हैं — नीचे देखें।<?php endif; ?>
            <?php if ($live === [] && $coll === []): ?>यानी ग्रह एक-दूसरे को छेड़ नहीं रहे — यह अपने-आप में अच्छी ख़बर है।<?php endif; ?>
          </div>
        </div>

        <?php foreach ($live as $d): ?>
          <div class="lk-card<?= !empty($d['takkar_p']) ? ' bad' : '' ?>">
            <div class="lk-card-h"><?= $h((string) $d['house_ord']) ?> भाव — <?= $h(implode(', ', $d['planets_hi'])) ?></div>
            <?php if (!empty($d['drishti_p'])): $bits = []; foreach ((array) $d['drishti_pct'] as $th => $pc) { $bits[] = $th . ' भाव (' . $pc . '%)'; } ?>
              <div class="lk-sub"><b>👁 देखता है:</b> <?= $h(implode(' · ', $bits)) ?> → <b><?= $h(implode(', ', $d['drishti_p'])) ?></b>
                <div style="font-size:.82rem;color:#475569">असर दूर से पड़ता है — सीधा नहीं, पर लगातार।</div>
              </div>
            <?php endif; ?>
            <?php if (!empty($d['takkar_p'])): ?>
              <div class="lk-sub" style="color:#991b1b"><b>⚔ टक्कर मारता है:</b>
                <?= $h(implode(', ', array_map('strval', (array) $d['takkar']))) ?> भाव → <b><?= $h(implode(', ', $d['takkar_p'])) ?></b>
                <div style="font-size:.82rem">यही वह ग्रह है जो इससे बिगड़ता है — इसके मामलों में बार-बार अड़चन इसी चोट से आती है।
                  उपाय <b><?= $h(implode(', ', $d['planets_hi'])) ?></b> पर जाता है, मार खाने वाले पर नहीं।</div>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>

        <?php if ($quiet !== []): ?>
          <div class="lk-card" style="background:#f8fafc">
            <div class="lk-sub" style="color:#64748b"><b>इनसे कुछ नहीं जा रहा:</b>
              <?php $qb = []; foreach ($quiet as $q) { $qb[] = $q['house_ord'] . ' भाव (' . implode(', ', $q['planets_hi']) . ')'; } ?>
              <?= $h(implode(' · ', $qb)) ?>
              — भाव 7–12 आगे किसी को नहीं देखते, और इनकी टक्कर जिस भाव पर पड़ती है वह ख़ाली है।
            </div>
          </div>
        <?php endif; ?>

        <?php /* तीन टक्करें इसी पन्ने की चीज़ हैं — पन्ने का नाम ही "दृष्टि व टक्कर"
                 है — पर अब तक ये यहाँ आती ही नहीं थीं। */ ?>
        <?php /* ══════ टक्करें क़िस्म के हिसाब से, गिनती के साथ ══════
             पहले हर टक्कर का अपना पूरा कार्ड बनता था — एक ही कुंडली पर सत्रह कार्ड,
             और हर कार्ड में वही फल और वही उपाय दोहराया हुआ, क्योंकि पाठ क़िस्म का
             है, जगह का नहीं।
             3000 बेतरतीब कुंडलियों पर नापा: **औसतन 14.7 विशेष टक्करें प्रति
             कुंडली**, और सिर्फ़ 0.1% कुंडलियों में एक भी नहीं। यानी "विश्वासघात की
             आशंका" हर किसी की कुंडली में पाँच बार बनती है। जो चेतावनी सब पर लगे,
             वह चेतावनी नहीं रह जाती — इसलिए यहाँ पहले सच लिखा जाता है कि यह कितनी
             आम है, फिर क़िस्म के हिसाब से एक बार फल-उपाय, और नीचे वे जगहें जहाँ यह
             बनी है। जिन पर सचमुच ध्यान देना है (चालू दशा का ग्रह, या निचोड़ में
             आया ग्रह) वे ऊपर अलग से दिखाए जाते हैं। */ ?>
        <?php if ($coll !== []): ?>
          <?php
            $byKind = [];
            foreach ($coll as $c) { $byKind[(string) $c['kind']][] = $c; }
            // कौन-से निशाने सचमुच मायने रखते हैं
            $dashaHi = (string) (($lk['active']['dasha']['hi']) ?? '');
            $corePl  = [];
            foreach ((array) (($lk['process']['core_points']) ?? []) as $cp) {
                if (trim((string) ($cp['planet'] ?? '')) !== '') { $corePl[] = (string) $cp['planet']; }
            }
            $matters = [];
            foreach ($coll as $c) {
                foreach ((array) $c['target_hi'] as $tg) {
                    if ($tg === $dashaHi || in_array($tg, $corePl, true)) {
                        $matters[$tg][] = (string) $c['kind'];
                    }
                }
            }
          ?>
          <h3 class="lk-h" style="margin-top:14px">⚠️ विशेष टक्करें (विश्वासघात · साझी · अचानक)</h3>
          <div class="lk-card" style="border-color:#fde68a;background:#fffbeb">
            <div class="lk-txt" style="color:#78350f">
              इस कुंडली में <b><?= count($coll) ?></b> जगह ये टक्करें बनती हैं।
              <b>यह असामान्य नहीं है</b> — लाल किताब की गणना में ये प्रायः हर कुंडली में
              दस से पंद्रह बार बनती हैं (औसत लगभग 15)। इसलिए इन्हें गिनकर न घबराएँ;
              देखने लायक़ वही हैं जो उस ग्रह पर पड़ रही हैं जो अभी चल रहा है या जिसका
              नाम निचोड़ में आया है।
              <?php if ($matters !== []): ?>
                <div style="margin-top:5px;color:#991b1b"><b>👉 ध्यान देने लायक़:</b>
                  <?php $mb = []; foreach ($matters as $tg => $ks) { $mb[] = $tg . ' (' . implode(', ', array_unique($ks)) . ')'; } ?>
                  <?= $h(implode(' · ', $mb)) ?>
                </div>
              <?php else: ?>
                <div style="margin-top:5px;color:#166534">👉 इनमें से कोई भी उस ग्रह पर नहीं पड़ रही जो अभी चल रहा है या निचोड़ में आया है।</div>
              <?php endif; ?>
            </div>
          </div>
          <?php foreach ($byKind as $kindName => $list): $c0 = $list[0]; ?>
            <div class="lk-card bad">
              <div class="lk-card-h" style="color:#991b1b"><?= $h((string) $kindName) ?>
                <span class="lk-pill" style="background:#fee2e2;color:#991b1b"><?= count($list) ?> जगह</span></div>
              <?php if (trim((string) $c0['phal']) !== ''): ?>
                <div class="lk-txt" style="margin-top:2px"><b>📢 फल:</b> <?= $h((string) $c0['phal']) ?></div>
              <?php endif; ?>
              <div class="lk-sub" style="margin-top:4px"><b>कहाँ-कहाँ बनी है:</b></div>
              <?php foreach ($list as $c): $hot = false;
                    foreach ((array) $c['target_hi'] as $tg) { if (isset($matters[$tg])) { $hot = true; } } ?>
                <div class="lk-txt" style="font-size:.82rem;padding-left:10px;border-left:3px solid <?= $hot ? '#991b1b' : '#e2e8f0' ?>;margin:2px 0">
                  <?= $hot ? '👉 ' : '' ?><b><?= $h(implode(', ', (array) $c['target_hi'])) ?></b>
                  (<?= $h((string) $c['house_ord']) ?> भाव) ← <?= $h(implode(' · ', (array) $c['from_hi'])) ?>
                </div>
              <?php endforeach; ?>
              <?php /* उपाय क़िस्म का है, जगह का नहीं — इसलिए एक बार। */ ?>
              <?php if (!empty($c0['upay'])): ?><?= $remBlock((array) $c0['upay'], (string) $kindName . ' — उपाय (सब जगहों पर यही)') ?><?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- ===== REMEDY ===== -->
      <div class="lk-view" data-lk="remedy">
        <?php $rm = $lk['remedy']; $rp = $lk['remedy_plan'] ?? []; ?>
        <h3 class="lk-h">उपाय — श्रेणी-अनुसार योजना (सरल पहले)</h3>
        <?php
          $rpTiers = [
            'sthiti'  => ['🎯 स्थिति-आधारित उपाय (सबसे ज़रूरी)', 'टक्कर · बुनियाद · रतौंध · सोया — नाम लेकर', '#991b1b', '#fef2f2', '#fecaca'],
            'masnui'  => ['🔀 मसनूई ग्रह के उपाय', 'दो मूल ग्रहों को एडजस्ट करके — मसनूई का सीधा उपाय कभी नहीं', '#5b21b6', '#f5f3ff', '#ddd6fe'],
            'quick'   => ['⚡ तुरंत / सरल उपाय', 'आज से शुरू करें — निःशुल्क व शीघ्र फल', '#166534', '#f0fdf4', '#bbf7d0'],
            'main'    => ['🎯 मुख्य उपाय (नियमित)', '40–43 दिन निरंतर — भावगत टोटके', '#854d0e', '#fffbeb', '#fde68a'],
            'worship' => ['🛕 पूजा / उपासना व दान', 'श्रद्धा-अनुसार', '#1e40af', '#eff6ff', '#bfdbfe'],
            'big'     => ['🏺 बड़े / स्थापना उपाय', 'एक-बार — कुछ खर्च संभव, सोच-समझकर', '#7c2d12', '#fff7ed', '#fed7aa'],
          ];
          $anyPlan = false; foreach ($rpTiers as $tk => $x) { if (!empty($rp[$tk])) { $anyPlan = true; break; } }
        ?>
        <?php /* ══════ पहले सिर्फ़ इतना ══════
             यह पन्ना पूरा भंडार है — ज्योतिषी के लिए। पर आठ उपायों की सूची दूसरे
             हफ़्ते छूट जाती है, और छूटा हुआ उपाय शुरू न किए गए उपाय से बुरा है:
             आदमी के पास तब मूल समस्या भी होती है और नाकाम रहने का बोझ भी। इसलिए
             सूची के सिरे पर वही एक-दो उपाय दोहराए जाते हैं जो प्रक्रिया-परत ने
             सचमुच जारी किए हैं, और बाक़ी साफ़-साफ़ "भंडार" कहलाते हैं। */ ?>
        <?php $PRr = $lk['process'] ?? null; $issuedR = (is_array($PRr) && !empty($PRr['ok'])) ? (array) ($PRr['upaay']['issued'] ?? []) : []; ?>
        <?php if ($issuedR !== []): ?>
          <div class="lk-card good" style="border-color:#86efac;background:#f0fdf4">
            <div class="lk-card-h" style="color:#14532d">✅ पहले सिर्फ़ इतना करें
              <span class="lk-pill" style="background:#dcfce7;color:#166534"><?= count($issuedR) ?> उपाय</span></div>
            <ul class="lk-rem-list" style="color:#14532d">
              <?php foreach ($issuedR as $ur): ?>
                <li><b><?= $h((string) $ur['target']) ?> (<?= $h((string) $ur['direction']) ?>):</b>
                  <?= $h((string) ($ur['upay'][0] ?? '')) ?>
                  <span style="color:#475569;font-size:.78rem">— <?= $h((string) $ur['duration']) ?>, <?= $h((string) $ur['stop_when']) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <div class="lk-txt" style="font-size:.78rem;color:#475569;margin-top:4px">
              नीचे की पूरी सूची <b>भंडार</b> है — उसमें से कुछ भी अपने-आप शुरू न करें।
              एक साथ कई उपाय शुरू करने से कोई पूरा नहीं होता।
            </div>
          </div>
        <?php endif; ?>
        <?php if ($anyPlan): ?>
          <?php foreach ($rpTiers as $tk => $meta): if (empty($rp[$tk])) { continue; } ?>
            <div class="lk-card" style="border-color:<?= $meta[4] ?>;background:<?= $meta[3] ?>">
              <div class="lk-card-h" style="color:<?= $meta[2] ?>"><?= $meta[0] ?>
                <span class="lk-pill" style="background:#fff;color:<?= $meta[2] ?>;border:1px solid <?= $meta[4] ?>"><?= count((array) $rp[$tk]) ?></span>
                <span style="font-weight:400;font-size:.74rem;color:#64748b">— <?= $meta[1] ?></span></div>
              <ul class="lk-rem-list" style="color:<?= $meta[2] ?>">
                <?php foreach ($rp[$tk] as $it): ?><li><b><?= $h((string) $it['hi']) ?>:</b> <?= $h((string) $it['text']) ?><?php if (($it['darja'] ?? '') === 'lambit'): ?> <span class="lk-pill" style="background:#dbeafe;color:#1e40af">अंतरिम</span><?php elseif (($it['darja'] ?? '') === 'anumanit'): ?> <span class="lk-pill" style="background:#fef3c7;color:#92400e">अनुमानित</span><?php endif; ?></li><?php endforeach; ?>
              </ul>
            </div>
          <?php endforeach; ?>
          <div class="lk-txt" style="color:#64748b;font-size:.78rem;margin:4px 0 10px">क्रम: पहले ⚡ सरल उपाय शुरू करें, फिर 🎯 मुख्य; 🏺 बड़े उपाय सोच-समझकर। "उपाय-कैलेंडर" में तिथियाँ देखें।</div>
        <?php else: ?>
          <div class="lk-card good"><div class="lk-txt">✅ कोई ग्रह गंभीर अशुभ नहीं — विशेष उपाय आवश्यक नहीं; नीचे सामान्य उपाय पर्याप्त।</div></div>
        <?php endif; ?>

        <h3 class="lk-h" style="margin-top:14px">अन्य उपाय (संदर्भ)</h3>
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

      <!-- ===== REMEDY CALENDAR (personalised, dated — filled by JS) ===== -->
      <div class="lk-view" data-lk="calendar">
        <h3 class="lk-h">🗓 उपाय-कैलेंडर — व्यक्तिगत योजना</h3>
        <div class="lk-txt" style="margin-bottom:9px">जिन ग्रहों के उपाय आवश्यक हैं, उनका उपाय उसी ग्रह के <b>वार</b> को आरंभ कर <b>40–43 दिन निरंतर</b> करें। नीचे आज से आरंभ-तिथियाँ व साप्ताहिक तिथियाँ दी हैं।</div>
        <?php $vjF = $lk['varjit']['forbidden'] ?? []; if (!empty($vjF)): ?>
          <div class="lk-card bad" style="margin-bottom:8px">
            <div class="lk-card-h">⛔ सावधान — इस कुंडली में कुछ उपाय वर्जित हैं</div>
            <ul class="lk-rem-list" style="color:#991b1b">
              <?php foreach ($vjF as $f): ?><li><?= $h((string) $f['varjit']) ?></li><?php endforeach; ?>
            </ul>
            <div class="lk-sub" style="color:#64748b">पूरा विवरण "वर्जित उपाय व नियम" श्रेणी में देखें — नीचे की योजना करते समय इन्हें अवश्य छोड़ें।</div>
          </div>
        <?php endif; ?>
        <div class="lk-flt" style="margin-bottom:8px">
          <button type="button" id="lk-print-calendar" class="lk-btn">🖨 कैलेंडर Print करें</button>
        </div>
        <div id="lk-cal-body"></div>
      </div>

      <!-- ===== RULES (do / don't) ===== -->
      <div class="lk-view" data-lk="rules">
        <?php $ru = $lk['rules']; $vj = $lk['varjit'] ?? ['forbidden' => [], 'general' => []]; ?>
        <h3 class="lk-h">उपाय के नियम व सावधानियाँ (Rules)</h3>

        <!-- ⛔ इस कुंडली में वर्जित उपाय — chart-specific, सबसे ऊपर -->
        <?php if (!empty($vj['forbidden'])): ?>
          <div class="lk-card bad" style="border-width:2px">
            <div class="lk-card-h">⛔ इस कुंडली में वर्जित उपाय — ये कदापि न करें</div>
            <div class="lk-txt" style="color:#991b1b;margin-bottom:5px">नीचे वे उपाय हैं जो <b>आपकी ग्रह-स्थिति के कारण हानिकारक</b> हैं — इन्हें भूलकर भी न करें:</div>
            <?php foreach ($vj['forbidden'] as $f): ?>
              <div style="border:1px solid #fecaca;background:#fff;border-radius:8px;padding:7px 10px;margin:5px 0">
                <div style="font-weight:700;color:#7f1d1d">🚫 <?= $h((string) $f['varjit']) ?></div>
                <div class="lk-sub" style="color:#64748b">आधार: <?= $h((string) $f['sthiti']) ?> <span style="opacity:.7">(<?= $h((string) $f['src']) ?>)</span></div>
                <?php if (trim((string) $f['parinam']) !== ''): ?><div class="lk-sub" style="color:#991b1b"><b>न मानने पर:</b> <?= $h((string) $f['parinam']) ?></div><?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="lk-card good"><div class="lk-txt">✅ इस कुंडली में कोई विशेष उपाय वर्जित नहीं — फिर भी नीचे सामान्य निषेध-नियम पढ़ें।</div></div>
        <?php endif; ?>
        <?php if (!empty($vj['general'])): ?>
          <div class="lk-card">
            <div class="lk-card-h">सामान्य दान-निषेध (सबके लिए)</div>
            <ul class="lk-rem-list" style="color:#334155"><?php foreach ($vj['general'] as $g): ?><li><?= $h((string) $g) ?></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>

        <div class="lk-card">
          <div class="lk-card-h">उपाय के सामान्य नियम</div>
          <ul class="lk-rem-list" style="color:#334155">
            <?php foreach ($ru['upay_niyam'] as $n): ?><li><?= $h((string) $n) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <details style="margin-bottom:11px">
          <summary style="cursor:pointer;font-size:.8rem;color:#94a3b8">सभी वर्जित-उपाय व दान-निषेध नियम (संदर्भ)</summary>
          <?php if ($ru['varjit']): ?>
            <div class="lk-card" style="margin-top:6px">
              <div class="lk-card-h">वर्जित उपाय (शर्त-अनुसार)</div>
              <?php foreach ($ru['varjit'] as $v): ?>
                <div class="lk-sub"><b><?= $h((string) $v['sthiti']) ?>:</b> <?= $h((string) $v['varjit']) ?><?php if (trim((string) $v['parinam']) !== ''): ?> <span style="color:#991b1b">— <?= $h((string) $v['parinam']) ?></span><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <?php if ($ru['daan_nishedh']): ?>
            <div class="lk-card">
              <div class="lk-card-h">दान-निषेध (शर्त-अनुसार)</div>
              <?php foreach ($ru['daan_nishedh'] as $d): ?>
                <div class="lk-sub"><b><?= $h((string) $d['sthiti']) ?>:</b> <?= $h((string) $d['varjit']) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </details>
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
$possShrap = array_values(array_filter($lk['shrap'], static fn ($s) => !empty($s['present'])));
$ssAct = $act['sadesati'] ?? null;
?>
<div id="lk-report" hidden>
  <div class="lkr-title">लाल किताब रिपोर्ट (Lal Kitab Report)</div>
  <div class="lkr-sub"><?= $h($birthLine) ?> · लग्न: <?= $h((string) $lk['lagna_hi']) ?> · चन्द्र राशि: <?= $h((string) $lk['moon_hi']) ?><?= $lk['age'] !== null ? ' · वर्तमान आयु: ' . (int) $lk['age'] . ' वर्ष' : '' ?></div>

  <?php /* निचोड़ छपी रिपोर्ट में भी सबसे ऊपर — वही क्रम, वही अनुशासन:
           मिज़ाज → मज़बूती → ध्यान की बातें (उपाय साथ) → उपाय → सीमा। */
        $PRp = $lk['process'] ?? null;
        if (is_array($PRp) && !empty($PRp['ok'])): $Cp = $PRp['client']; ?>
  <div class="lkr-h">📋 निचोड़ — सबसे ज़रूरी बातें</div>
  <div class="lkr-box">
    <b>कुंडली का मिज़ाज — <?= $h((string) $PRp['temperament']['primary']) ?></b>
    <div class="lkr-why"><?= $h((string) $Cp['mizaj']) ?></div>
  </div>
  <?php foreach ((array) $Cp['strengths'] as $s): ?>
    <div class="lkr-box"><b>💪 <?= $h((string) $s['title']) ?></b><div class="lkr-why"><?= $h((string) $s['text']) ?></div></div>
  <?php endforeach; ?>
  <?php foreach ((array) $Cp['issues'] as $it): ?>
    <div class="lkr-box"><b>⚠️ <?= $h((string) $it['title']) ?></b>
      <div class="lkr-why"><?= $h((string) $it['text']) ?></div>
      <?php if (trim((string) ($it['upay_inline'] ?? '')) !== ''): ?>
        <div class="lkr-why"><b>🛠 उपाय:</b> <?= $h((string) $it['upay_inline']) ?></div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if (trim((string) $Cp['samay']) !== ''): ?>
    <div class="lkr-box"><b>📅 अभी का समय</b><div class="lkr-why"><?= $h((string) $Cp['samay']) ?></div></div>
  <?php endif; ?>
  <?php foreach ((array) $Cp['upaay'] as $i => $u): ?>
    <div class="lkr-box"><b>🛠 उपाय <?= (int) ($i + 1) ?> — <?= $h((string) $u['target']) ?> (दिशा: <?= $h((string) $u['direction']) ?>)</b>
      <ul class="lkr-ul"><?php foreach ((array) $u['upay'] as $t): ?><li><?= $h((string) $t) ?></li><?php endforeach; ?></ul>
      <div class="lkr-why">अवधि: <?= $h((string) $u['duration']) ?> · कब रोकें: <?= $h((string) $u['stop_when']) ?></div>
    </div>
  <?php endforeach; ?>
  <div class="lkr-box"><div class="lkr-why">🕊 <?= $h((string) $Cp['boundary']) ?></div>
    <div class="lkr-why">🩺 <?= $h((string) $Cp['medical']) ?></div></div>
  <?php endif; ?>

  <?php if (!empty($act['dasha']) || $ssAct): ?>
  <div class="lkr-box lkr-hot">
    <b>🔥 अभी सक्रिय:</b>
    <?= !empty($act['dasha']) ? 'लाल किताब दशा (35-साला) — ' . $h((string) $act['dasha']['hi']) . ' (' . $h((string) $act['dasha']['house_ord']) . ' भाव · आयु ' . (int) ($act['dasha']['from'] ?? 0) . '–' . (int) ($act['dasha']['to'] ?? 0) . ')' : '' ?>
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

  <?php if (!empty($lk['masnui'])): ?>
  <div class="lkr-h">🔀 मसनूई (कृत्रिम) ग्रह</div>
  <?php foreach ($lk['masnui'] as $M): ?>
    <div class="lkr-box <?= $M['verdict'] === 'अशुभ' ? 'lkr-bad' : '' ?>">
      <b>मसनूई <?= $h((string) $M['label']) ?></b> — <?= $h((string) $M['pair_hi']) ?>,
      <?= $h((string) $M['house_ord']) ?> भाव · <?= $h((string) $M['verdict']) ?><br>
      <?= $h((string) $M['effect']) ?>
      <ul class="lkr-ul"><?php foreach ($M['remedies'] as $r): ?><li><?= $h((string) $r) ?></li><?php endforeach; ?></ul>
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
  <div class="lkr-h">🧬 पैतृक ऋण (इस कुंडली में बनते हैं)</div>
  <?php foreach ($possShrap as $S): ?>
    <div class="lkr-box lkr-bad"><b><?= $h((string) $S['rin']) ?></b> — <?= $h(implode('; ', $S['matched'] ?? [])) ?><br>
      <?php if (trim((string) $S['ashubh_phal']) !== ''): ?>अशुभ फल: <?= $h((string) $S['ashubh_phal']) ?><br><?php endif; ?>
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
  <?php /* छपी रिपोर्ट किसी के हाथ में महीनों रहती है, और वही आगे दूसरे ज्योतिषी
           को दिखाई जाती है। उस पर यह लिखा होना ज़रूरी है कि फल किस मत पर बना —
           वरना दो रिपोर्टों का फ़र्क़ कोई समझा ही नहीं सकेगा। */ ?>
  <?php $PRn = $lk['process'] ?? null; if (is_array($PRn) && !empty($PRn['niyam_hi'])): ?>
    <div class="lkr-foot" style="font-size:.72rem">
      <b>नियम-सेटिंग<?= empty($PRn['niyam_default']) ? ' (spec के सुझाए रूप से बदली हुई)' : '' ?>:</b>
      <?= $h(implode(' · ', (array) $PRn['niyam_hi'])) ?>
    </div>
  <?php endif; ?>
  <div class="lkr-foot" style="font-size:.72rem">कुंडली रास्ता दिखाती है, बाँधती नहीं — जो लिखा है वह बदला जा सकता है, इसीलिए उपाय हैं।</div>
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
  window.AB_LK_CAL   = <?= json_encode($lkCal, JSON_UNESCAPED_UNICODE) ?>;
  window.AB_LK_AGE   = <?= json_encode((int) ($lk['age'] ?? 0)) ?>;
</script>
<?php endif; ?>
