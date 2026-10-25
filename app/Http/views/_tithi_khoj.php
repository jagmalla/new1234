<?php
/**
 * 🔍 तिथि खोज — मुहूर्त पन्ने का दूसरा दृश्य (मेन्यू: Mahurat › Tithi Khoj)।
 *
 * ① कार्य चुनें — मुख्य श्रेणी, फिर उप-श्रेणी (12 श्रेणियाँ, TithiKhojCatalog से)
 * ② अवधि चुनें — 1 / 3 / 6 माह / 1 वर्ष के टैब, या दोनों तिथियाँ ख़ुद भरें
 * ③ खोजें — उस अवधि के हर दिन की जाँच (calc/muhuratScan) और शुभ दिनों की सूची
 *    व कैलेंडर। व्यवहार public_html/assets/js/tithikhoj.js में है।
 *
 * यह पन्ना कोई नई गणना नहीं करता — खोज वही मौजूदा मुहूर्त-स्कैनर करता है। हर
 * कार्य के अपने नियम आगे जोड़े जाएँगे; तब तक पन्ना साफ़ बताता है कि खोज किस
 * नियम से हो रही है।
 */

use AutoBusiness\Astro\Muhurat\TithiKhojCatalog;

$tkCats = TithiKhojCatalog::forView();

/* तिथियाँ पहले से भरी हुई — प्रारम्भ आज, अन्त छह महीने बाद (विवाह-तिथि खोजक
   जैसा ही)। महीना पहले जोड़ा जाता है, फिर दिन उस महीने की लम्बाई पर काटा जाता
   है — 31-01 से एक माह = 28/29-02, PHP के "+1 month" वाली 3 मार्च नहीं। */
$tkPlus = static function (int $months): string {
    $t = new \DateTimeImmutable('today');
    $day = (int) $t->format('j');
    $m = $t->modify('first day of this month')->modify('+' . $months . ' months');
    return $m->setDate((int) $m->format('Y'), (int) $m->format('n'),
        min($day, (int) $m->format('t')))->format('d-m-Y');
};
$tkFrom = date('d-m-Y');
$tkTo = $tkPlus(6);

/* कार्य-स्थान का समय-क्षेत्र। दिन का पंचांग उसी जगह के सूर्योदय-समय पर देखा
   जाता है जहाँ कार्य होना है — विदेश में रहने वाले के लिए भारतीय समय से खोज
   करने पर तिथि/नक्षत्र खिसक जाते। पहले से चुना रहता है जन्म-स्थान का। */
$tkBirthTz = (float) ($meta['tz'] ?? 5.5);
$tkZones = [
    '5.5' => 'भारत (IST · UTC+5:30)',
    '5.75' => 'नेपाल (UTC+5:45)',
    '6' => 'बांग्लादेश (UTC+6)',
    '5' => 'पाकिस्तान (UTC+5)',
    '4' => 'दुबई / UAE · ओमान (UTC+4)',
    '3' => 'सऊदी · कुवैत · क़तर (UTC+3)',
    '8' => 'सिंगापुर · मलेशिया (UTC+8)',
    '10' => 'ऑस्ट्रेलिया — पूर्व (UTC+10)',
    '12' => 'न्यूज़ीलैंड (UTC+12)',
    '1' => 'यूरोप — मध्य (UTC+1)',
    '0' => 'ब्रिटेन (UTC+0)',
    '-5' => 'अमेरिका / कनाडा — पूर्व (UTC−5)',
    '-6' => 'अमेरिका — मध्य (UTC−6)',
    '-7' => 'अमेरिका — पर्वतीय (UTC−7)',
    '-8' => 'अमेरिका / कनाडा — पश्चिम (UTC−8)',
];
$tkBirthKey = rtrim(rtrim(number_format($tkBirthTz, 2, '.', ''), '0'), '.');
if (!isset($tkZones[$tkBirthKey])) {
    $mins = (int) round(abs($tkBirthTz) * 60);
    $tkZones = [$tkBirthKey => sprintf('जन्म-स्थान (UTC%s%d:%02d)', $tkBirthTz < 0 ? '−' : '+', intdiv($mins, 60), $mins % 60)] + $tkZones;
} else {
    $tkZones[$tkBirthKey] .= ' · जन्म-स्थान';
}
$tkSpans = [1 => '1 माह', 3 => '3 माह', 6 => '6 माह', 12 => '1 वर्ष'];
?>
<style>
/* ── तिथि खोज ── अपनी ही शैली, ताकि Tailwind न भी हो (ऑफ़लाइन) तो पन्ना वैसा ही दिखे */
.tk-card{background:#fff;border-radius:10px;box-shadow:0 1px 3px rgba(0,0,0,.1),0 1px 2px rgba(0,0,0,.06);padding:16px 18px}
.tk-card h2{margin:0;font-size:1.12rem;font-weight:800;color:#9a3412}
.tk-subt{font-size:.82rem;color:#6b7280;margin:3px 0 12px}
.tk-steps{display:grid;grid-template-columns:minmax(0,1fr);gap:12px}
@media (min-width:1100px){.tk-steps{grid-template-columns:minmax(0,1.05fr) minmax(0,1fr)}}
.tk-step{background:#fffaf5;border:1px solid #fde4cc;border-radius:12px;padding:12px 14px;min-width:0}
.tk-step-h{display:flex;align-items:center;gap:8px;font-weight:800;color:#9a3412;font-size:.92rem;margin-bottom:9px}
.tk-num{display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:#ea580c;color:#fff;font-size:.78rem;flex:0 0 auto}
.tk-row{display:flex;flex-wrap:wrap;gap:10px 12px;align-items:flex-start}
.tk-fld{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 150px}
.tk-fld.tk-wide{flex:1 1 230px}
.tk-fld label{font-size:.74rem;font-weight:700;color:#9a3412}
.tk-fld select,.tk-fld input[type=text]{width:100%;border:1.5px solid #fdba74;border-radius:8px;padding:8px 10px;font-size:.9rem;font-family:inherit;background:#fff;color:#1f2937;min-width:0}
.tk-fld select:disabled{background:#f9fafb;color:#9ca3af;border-color:#e5e7eb}
.tk-fld select:focus,.tk-fld input:focus{outline:2px solid #fb923c;outline-offset:1px}
.tk-date{position:relative;display:flex}
.tk-date input[type=text]{padding-right:40px}
.tk-pick{position:absolute;right:3px;top:50%;transform:translateY(-50%);width:34px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:6px;font-size:1.05rem;cursor:pointer}
.tk-pick:hover{background:#ffedd5}
/* पारदर्शी date-input चिह्न के ऊपर — iPad/फ़ोन पर छूते ही अपना कैलेंडर खुलता है */
.tk-pick input[type=date]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;border:0;padding:0;margin:0}
.tk-status{margin-top:10px;border-radius:10px;padding:9px 12px;font-size:.84rem;line-height:1.55;display:none}
.tk-status.on{display:block}
.tk-status.rule{background:#f0fdf4;border:1px solid #bbf7d0;color:#166534}
.tk-status.general{background:#fefce8;border:1px solid #fde68a;color:#854d0e}
.tk-status.pending{background:#f5f3ff;border:1px solid #ddd6fe;color:#5b21b6}
.tk-legend{font-size:.72rem;color:#9ca3af;margin-top:7px}
.tk-tabs{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:10px}
.tk-tabs-h{font-size:.74rem;font-weight:700;color:#9a3412}
.tk-tabs-n{font-size:.72rem;color:#9ca3af}
.tk-tab{border:1.5px solid #fdba74;background:#fff;color:#9a3412;border-radius:999px;padding:4px 13px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit}
.tk-tab:hover{background:#fff7ed}
.tk-tab.on{background:#ea580c;border-color:#ea580c;color:#fff}
.tk-tzn{font-size:.7rem;color:#9ca3af}
.tk-go-row{display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap;margin-top:14px}
.tk-btn{background:#c2410c;color:#fff;border:0;border-radius:10px;padding:11px 26px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 2px 6px rgba(194,65,12,.25)}
.tk-btn:hover:not(:disabled){background:#9a3412}
.tk-btn:disabled{background:#d6d3d1;color:#78716c;box-shadow:none;cursor:default}
.tk-go-hint{font-size:.8rem;color:#78716c}
.tk-go-hint.err{color:#b91c1c;font-weight:700}
@media (max-width:640px){.tk-card{padding:13px 12px}.tk-btn{width:100%}.tk-go-hint{width:100%;text-align:center}}

/* ── परिणाम ── */
.tk-results:empty{display:none}
/* खोज के बाद पन्ना परिणाम तक सरकता है — ऊपर की चिपकी पट्टी उसका शीर्षक न ढके */
.tk-results{margin-top:16px;scroll-margin-top:84px}
.tk-res{margin-top:4px}
.tk-res-h{font-weight:800;font-size:1.02rem;color:#1f2937}
.tk-res-m{font-size:.8rem;color:#6b7280;margin-top:2px;line-height:1.55}
.tk-warn{margin-top:10px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:9px 12px;color:#854d0e;font-size:.82rem;line-height:1.55}
.tk-tally{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 4px}
.tk-pill{border-radius:999px;padding:4px 13px;font-size:.8rem;font-weight:700}
.tk-pill.g{background:#dcfce7;color:#166534}.tk-pill.y{background:#fef9c3;color:#854d0e}.tk-pill.r{background:#fee2e2;color:#991b1b}.tk-pill.n{background:#eef2ff;color:#3730a3}
.tk-bar{display:flex;align-items:center;justify-content:space-between;gap:8px 14px;flex-wrap:wrap;margin:12px 0 10px;padding-top:10px;border-top:1px solid #f1f5f9}
.tk-seg{display:inline-flex;border:1.5px solid #fdba74;border-radius:10px;overflow:hidden}
.tk-seg button{border:0;background:#fff;color:#9a3412;padding:6px 13px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit}
.tk-seg button+button{border-left:1.5px solid #fdba74}
.tk-seg button.on{background:#ea580c;color:#fff}
.tk-seg-l{font-size:.74rem;font-weight:700;color:#9a3412;margin-right:6px}
.tk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(270px,1fr));gap:10px}
.tk-mh{grid-column:1/-1;font-weight:800;color:#9a3412;font-size:.9rem;margin:8px 0 -2px;padding-bottom:4px;border-bottom:1.5px dashed #fed7aa}
.tk-mh span{font-weight:600;color:#6b7280;font-size:.78rem;margin-left:6px}
.tk-day{border:1px solid #e5e7eb;border-left:5px solid #9ca3af;border-radius:11px;padding:9px 12px;background:#fff;min-width:0}
.tk-day.g{border-left-color:#16a34a;background:linear-gradient(180deg,#f6fef9,#f0fdf4)}
.tk-day.y{border-left-color:#d97706;background:linear-gradient(180deg,#fffdf5,#fefce8)}
.tk-dh{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-weight:800;font-size:.95rem;color:#1f2937}
.tk-wd{font-size:.78rem;color:#6b7280;font-weight:600}
.tk-gr{font-size:.72rem;border-radius:999px;padding:2px 10px;font-weight:800}
.tk-gr.g{background:#dcfce7;color:#166534}.tk-gr.y{background:#fef9c3;color:#854d0e}.tk-gr.r{background:#fee2e2;color:#991b1b}
.tk-rs{font-size:.78rem;color:#6b7280;margin-top:1px}
.tk-none{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:11px 13px;color:#991b1b;font-size:.85rem}
.tk-note{font-size:.75rem;color:#6b7280;margin-top:12px;line-height:1.55}
.tk-wait{color:#6b7280;font-size:.88rem;padding:14px 4px}
/* कैलेंडर */
.tk-cal{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:12px}
.tk-mon{border:1px solid #fde4cc;border-radius:12px;padding:9px 10px 10px;background:#fffdfb;min-width:0}
.tk-mon-h{display:flex;justify-content:space-between;align-items:baseline;gap:6px;font-weight:800;color:#9a3412;font-size:.9rem;margin-bottom:6px}
.tk-mon-h span{font-size:.72rem;color:#6b7280;font-weight:600;white-space:nowrap}
.tk-cg{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px}
.tk-cw{font-size:.66rem;font-weight:700;color:#9ca3af;text-align:center;padding:2px 0}
.tk-c{aspect-ratio:1/1;min-height:28px;border-radius:7px;border:0;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;font-family:inherit;padding:0;background:transparent;color:#d1d5db}
.tk-c.g,.tk-c.y,.tk-c.r{cursor:pointer}
.tk-c.g{background:#22c55e;color:#fff}
.tk-c.y{background:#facc15;color:#422006}
.tk-c.r{background:#fee2e2;color:#b91c1c}
.tk-c.out{background:#f9fafb;color:#d1d5db}
.tk-c.today{box-shadow:inset 0 0 0 2px #1d4ed8}
.tk-c.sel{outline:2.5px solid #9a3412;outline-offset:1px}
.tk-cal-d{margin-top:8px}
.tk-cal-d:empty{display:none}
.tk-cal-k{display:flex;gap:10px;flex-wrap:wrap;font-size:.72rem;color:#6b7280;margin-bottom:10px}
.tk-cal-k i{display:inline-block;width:11px;height:11px;border-radius:3px;vertical-align:-1px;margin-right:4px}
.tk-meter{height:7px;border-radius:999px;background:#f1f5f9;overflow:hidden;margin:6px 0 3px}
.tk-meter b{display:block;height:100%;border-radius:999px}
/* पूरी सूची */
.tk-more{margin-top:12px}.tk-more summary{cursor:pointer;font-size:.84rem;font-weight:700;color:#9a3412}
.tk-tblw{overflow-x:auto;margin-top:8px}
.tk-tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.tk-tbl th,.tk-tbl td{border:1px solid #eee;padding:4px 7px;text-align:left;white-space:nowrap}
.tk-tbl td:last-child{white-space:normal}
.tk-tbl th{background:#fff7ed;color:#9a3412}
.tk-tbl tr.g td{background:#f0fdf4}.tk-tbl tr.y td{background:#fefce8}.tk-tbl tr.r td{background:#fef2f2}
</style>

<div id="card-tithikhoj" class="tk-card">
    <h2>🔍 तिथि खोज — शुभ मुहूर्त-तिथि खोजें</h2>
    <div class="tk-subt">कार्य चुनें, फिर अवधि — सिस्टम उस अवधि के हर दिन का पंचांग जाँचकर शुभ दिन बताएगा।</div>

    <div class="tk-steps">
        <div class="tk-step">
            <div class="tk-step-h"><span class="tk-num">1</span>कार्य चुनें</div>
            <div class="tk-row">
                <div class="tk-fld tk-wide">
                    <label for="tk-cat">मुख्य श्रेणी</label>
                    <select id="tk-cat">
                        <option value="">— मुख्य श्रेणी चुनें —</option>
                        <?php foreach ($tkCats as $i => $c): ?>
                            <option value="<?= $h($c['key']) ?>"><?= (int) ($i + 1) ?>. <?= $h($c['emoji'] . ' ' . $c['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tk-fld tk-wide">
                    <label for="tk-sub">उप-श्रेणी (कौन-सा कार्य)</label>
                    <select id="tk-sub" disabled>
                        <option value="">— पहले मुख्य श्रेणी चुनें —</option>
                    </select>
                </div>
            </div>
            <div id="tk-status" class="tk-status" aria-live="polite"></div>
            <div class="tk-legend">✅ शास्त्रीय नियम उपलब्ध · 🟡 अभी सामान्य पंचांग-शुद्धि से · ⏳ विशेष नियम शीघ्र</div>
        </div>

        <div class="tk-step">
            <div class="tk-step-h"><span class="tk-num">2</span>अवधि चुनें</div>
            <div class="tk-tabs" role="group" aria-label="अवधि चुनें">
                <span class="tk-tabs-h">अवधि:</span>
                <?php foreach ($tkSpans as $mn => $lbl): ?>
                    <button type="button" class="tk-tab<?= $mn === 6 ? ' on' : '' ?>" data-months="<?= (int) $mn ?>"
                            aria-pressed="<?= $mn === 6 ? 'true' : 'false' ?>"><?= $h($lbl) ?></button>
                <?php endforeach; ?>
                <span class="tk-tabs-n">आज से गिनकर भर जाएगी</span>
            </div>
            <div class="tk-row">
                <div class="tk-fld">
                    <label for="tk-from">प्रारम्भ तिथि (DD-MM-YYYY)</label>
                    <div class="tk-date">
                        <input id="tk-from" type="text" inputmode="numeric" maxlength="10" placeholder="DD-MM-YYYY"
                               value="<?= $h($tkFrom) ?>" autocomplete="off">
                        <span class="tk-pick" title="कैलेंडर से चुनें" aria-hidden="true">📅<input type="date" tabindex="-1" data-for="tk-from"></span>
                    </div>
                </div>
                <div class="tk-fld">
                    <label for="tk-to">अन्तिम तिथि (DD-MM-YYYY)</label>
                    <div class="tk-date">
                        <input id="tk-to" type="text" inputmode="numeric" maxlength="10" placeholder="DD-MM-YYYY"
                               value="<?= $h($tkTo) ?>" autocomplete="off">
                        <span class="tk-pick" title="कैलेंडर से चुनें" aria-hidden="true">📅<input type="date" tabindex="-1" data-for="tk-to"></span>
                    </div>
                </div>
                <div class="tk-fld tk-wide">
                    <label for="tk-tz">कार्य-स्थान का समय</label>
                    <select id="tk-tz">
                        <?php foreach ($tkZones as $zv => $zl): ?>
                            <option value="<?= $h((string) $zv) ?>"<?= (string) $zv === $tkBirthKey ? ' selected' : '' ?>><?= $h($zl) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="tk-tzn">जहाँ कार्य होगा वहाँ का — विदेश में गर्मी के समय (DST) एक घंटा आगे वाला चुनें।</span>
                </div>
            </div>
        </div>
    </div>

    <div class="tk-go-row">
        <button type="button" id="tk-go" class="tk-btn" disabled>🔍 शुभ मुहूर्त-तिथि खोजें</button>
        <span id="tk-go-hint" class="tk-go-hint">पहले ऊपर कार्य चुनें</span>
    </div>
</div>

<div id="tk-results" class="tk-results" aria-live="polite"></div>

<script type="application/json" id="tk-data"><?= json_encode(['cats' => $tkCats], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
