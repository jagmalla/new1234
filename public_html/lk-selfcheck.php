<?php

declare(strict_types=1);

/**
 * लाल किताब स्वयं-जाँच — ब्राउज़र से चलाने के लिए।
 *
 * लाइव सर्वर (cPanel आदि) पर टर्मिनल प्रायः नहीं होता, और tests/ फ़ोल्डर
 * webroot के बाहर है — इसलिए वहाँ से जाँच नहीं चलाई जा सकती। यह पन्ना वही
 * सोलह जाँचें ब्राउज़र में चलाता है।
 *
 *   खोलें:  https://आपकी-साइट/lk-selfcheck.php?key=आपकी-कुंजी
 *
 * कुंजी .env के LK_SELFCHECK_KEY से आती है। कुंजी सेट न हो तो पन्ना बंद रहता
 * है — यह जाँच हर बार छह पूरी कुंडलियाँ बनाती है, इसलिए इसे खुला छोड़ना
 * सर्वर पर बेवजह बोझ और जानकारी दोनों का रिसाव है।
 */

require dirname(__DIR__) . '/bootstrap.php';

use AutoBusiness\Astro\LalKitab\LalKitabSelfCheck;

$expected = (string) (getenv('LK_SELFCHECK_KEY') ?: ($_ENV['LK_SELFCHECK_KEY'] ?? ''));
$given    = (string) ($_GET['key'] ?? '');

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$h = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

if ($expected === '' || !hash_equals($expected, $given)) {
    http_response_code(403);
    echo '<!doctype html><meta charset="utf-8"><body style="font-family:system-ui;padding:24px;max-width:640px">';
    echo '<h2>🔒 जाँच-पन्ना बंद है</h2>';
    if ($expected === '') {
        echo '<p>चलाने के लिए पहले <code>.env</code> में एक कुंजी रखें:</p>';
        echo '<pre style="background:#f1f5f9;padding:10px;border-radius:8px">LK_SELFCHECK_KEY=कोई-लंबा-गुप्त-शब्द</pre>';
        echo '<p>फिर खोलें: <code>/lk-selfcheck.php?key=कोई-लंबा-गुप्त-शब्द</code></p>';
    } else {
        echo '<p>कुंजी ग़लत है।</p>';
    }
    echo '</body>';
    exit;
}

// अपनी ही साइट का पता — जिस पते से यह पन्ना खुला है, वही।
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base   = (string) ($_GET['base'] ?? ($scheme . '://' . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')));

@set_time_limit(180);
$r = LalKitabSelfCheck::run($base);

?><!doctype html>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>लाल किताब — स्वयं-जाँच</title>
<body style="font-family:system-ui,'Noto Sans Devanagari',sans-serif;background:#f8fafc;margin:0;padding:20px">
<div style="max-width:820px;margin:0 auto">
  <h1 style="font-size:1.3rem;margin:0 0 4px">🧪 लाल किताब — स्वयं-जाँच</h1>
  <div style="color:#64748b;font-size:.85rem;margin-bottom:14px">
    <?= $h($r['base']) ?> · <?= (int) $r['fetched'] ?> कुंडलियाँ जाँची गईं ·
    <?= $h(date('d-m-Y H:i')) ?>
  </div>

  <?php if ($r['error'] !== ''): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#7f1d1d;border-radius:10px;padding:12px 15px">
      <b>जाँच नहीं चल सकी।</b><br><?= $h($r['error']) ?>
      <div style="font-size:.85rem;margin-top:8px;color:#991b1b">
        कई होस्ट सर्वर को अपने ही पते पर अनुरोध करने से रोकते हैं। ऐसी हालत में पता हाथ से दें —
        <code>?key=…&amp;base=https://आपकी-साइट</code>
      </div>
    </div>
  <?php else: ?>
    <?php $okAll = $r['fail'] === 0; ?>
    <div style="border-radius:10px;padding:12px 15px;margin-bottom:14px;font-weight:700;
      background:<?= $okAll ? '#f0fdf4' : '#fef2f2' ?>;border:1px solid <?= $okAll ? '#86efac' : '#fecaca' ?>;
      color:<?= $okAll ? '#14532d' : '#7f1d1d' ?>">
      <?= $okAll ? '✅ सब ठीक — ' : '❌ ध्यान दें — ' ?>
      पास <?= (int) $r['pass'] ?> · फेल <?= (int) $r['fail'] ?>
    </div>

    <table style="width:100%;border-collapse:collapse;background:#fff;border-radius:10px;overflow:hidden;
      box-shadow:0 1px 3px rgba(0,0,0,.06);font-size:.88rem">
      <?php foreach ($r['rows'] as $row): ?>
        <tr style="border-bottom:1px solid #f1f5f9">
          <td style="padding:9px 12px;width:34px"><?= $row['ok'] ? '✅' : '❌' ?></td>
          <td style="padding:9px 6px;width:82px;color:#64748b;font-family:ui-monospace,monospace"><?= $h($row['id']) ?></td>
          <td style="padding:9px 12px;color:<?= $row['ok'] ? '#334155' : '#b91c1c' ?>"><?= $h($row['what']) ?></td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?php if (!$okAll): ?>
      <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:10px;padding:11px 14px;margin-top:14px;
        font-size:.87rem;line-height:1.6;color:#713f12">
        <b>फेल का मतलब:</b> कोई जाँच लाल हो तो पहले देखें कि फ़ाइलें पूरी अपलोड हुई हैं या नहीं
        (<code>app/Astro/LalKitab/</code> का पूरा फ़ोल्डर) और OPcache क्लियर किया है या नहीं।
        अधिकतर लाल जाँचें अधूरे अपलोड से आती हैं।
      </div>
    <?php endif; ?>
  <?php endif; ?>

  <div style="color:#94a3b8;font-size:.78rem;margin-top:16px">
    यह पन्ना हर बार छह पूरी कुंडलियाँ बनाता है — इसे बार-बार न चलाएँ। कुंजी किसी से साझा न करें।
  </div>
</div>
</body>
</html>
