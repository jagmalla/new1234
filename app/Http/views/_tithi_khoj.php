<?php
/**
 * 🔍 तिथि खोज — मुहूर्त पन्ने का दूसरा दृश्य (मेन्यू: Mahurat › Tithi Khoj)।
 *
 * ① कार्य चुनें — मुख्य श्रेणी, फिर कार्य (12 श्रेणियाँ, 87 कार्य — मालिक की
 *    नियम-पुस्तिका से), और कार्य के विकल्प (दिशा, क्रय/विक्रय, नया/पुराना घर …)
 * ② अवधि व स्थान — 1 / 3 / 6 माह / 1 वर्ष, या दोनों तिथियाँ; कार्य-स्थान (लग्न,
 *    सूर्योदय व राहुकाल उसी स्थान से), और व्यक्तिगत चंद्र/तारा बल
 * ③ खोजें — calc/karyaScan: हर दिन की शुभ समय-खिड़कियाँ; किसी दिन का पूरा
 *    विवरण calc/karyaCheck से। व्यवहार public_html/assets/js/tithikhoj.js में।
 *
 * इन्हीं शैलियों (tk-*) से मुहूर्त फल का "कार्य-मुहूर्त जाँच" पैनल भी बनता है।
 */

use AutoBusiness\Astro\Muhurat\TithiKhojCatalog;

$tkView = TithiKhojCatalog::forView();
$tkCats = $tkView['cats'];

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
$tkSpans = [1 => '1 माह', 3 => '3 माह', 6 => '6 माह', 12 => '1 वर्ष'];

/* कार्य-स्थान — पहले से जन्म-स्थान। लग्न, सूर्योदय, राहुकाल सब इसी जगह के। */
$tkPlace = trim((string) ($pobTop ?? ($in['place'] ?? '')));
$tkLat = (float) ($meta['lat'] ?? 28.6139);
$tkLon = (float) ($meta['lon'] ?? 77.2090);
$tkTz = (float) ($meta['tz'] ?? 5.5);
$tkName = trim((string) ($in['name'] ?? ''));
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
.tk-fld{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1 1 150px;position:relative}
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
.tk-opts{display:flex;flex-wrap:wrap;gap:10px 12px;margin-top:10px}
.tk-opts:empty{display:none}
.tk-rules{margin-top:10px;border:1px solid #fde4cc;border-radius:10px;background:#fff;font-size:.82rem}
.tk-rules>summary{cursor:pointer;padding:8px 12px;font-weight:700;color:#9a3412}
.tk-rules[hidden]{display:none}
.tk-rules-b{padding:0 12px 10px}
.tk-rt{width:100%;border-collapse:collapse;margin-top:4px}
.tk-rt th,.tk-rt td{border-top:1px solid #f3e8dc;padding:5px 6px;text-align:left;vertical-align:top;line-height:1.5}
.tk-rt th{width:30%;color:#9a3412;font-weight:700;white-space:nowrap}
@media (max-width:640px){.tk-rt th{white-space:normal;width:36%}}
.tk-rnote{margin-top:8px;background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:7px 10px;color:#854d0e}
.tk-legend{font-size:.72rem;color:#9ca3af;margin-top:7px}
.tk-tabs{display:flex;align-items:center;gap:7px;flex-wrap:wrap;margin-bottom:10px}
.tk-tabs-h{font-size:.74rem;font-weight:700;color:#9a3412}
.tk-tabs-n{font-size:.72rem;color:#9ca3af}
.tk-tab{border:1.5px solid #fdba74;background:#fff;color:#9a3412;border-radius:999px;padding:4px 13px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit}
.tk-tab:hover{background:#fff7ed}
.tk-tab.on{background:#ea580c;border-color:#ea580c;color:#fff}
.tk-tzn{font-size:.7rem;color:#9ca3af}
.tk-place-res{position:absolute;left:0;right:0;top:100%;z-index:30;background:#fff;border:1px solid #fed7aa;border-radius:8px;box-shadow:0 6px 18px rgba(0,0,0,.12);max-height:240px;overflow-y:auto;display:none}
.tk-place-res>div{padding:7px 10px;font-size:.84rem;cursor:pointer}
.tk-place-res>div:hover{background:#fff7ed}
.tk-chk{display:flex;align-items:flex-start;gap:8px;font-size:.82rem;color:#374151;margin-top:10px;cursor:pointer}
.tk-chk input{margin-top:3px;accent-color:#ea580c}
.tk-go-row{display:flex;align-items:center;gap:10px 14px;flex-wrap:wrap;margin-top:14px}
.tk-btn{background:#c2410c;color:#fff;border:0;border-radius:10px;padding:11px 26px;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 2px 6px rgba(194,65,12,.25)}
.tk-btn:hover:not(:disabled){background:#9a3412}
.tk-btn:disabled{background:#d6d3d1;color:#78716c;box-shadow:none;cursor:default}
.tk-btn2{background:#fff;color:#9a3412;border:1.5px solid #fdba74;border-radius:9px;padding:6px 13px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit}
.tk-btn2:hover{background:#fff7ed}
.tk-go-hint{font-size:.8rem;color:#78716c}
.tk-go-hint.err{color:#b91c1c;font-weight:700}
@media (max-width:640px){.tk-card{padding:13px 12px}.tk-btn{width:100%}.tk-go-hint{width:100%;text-align:center}}

/* ── परिणाम ── */
.tk-results:empty{display:none}
/* खोज के बाद पन्ना परिणाम तक सरकता है — ऊपर की चिपकी पट्टी उसका शीर्षक न ढके */
.tk-results{margin-top:16px;scroll-margin-top:84px}
.tk-res-h{font-weight:800;font-size:1.02rem;color:#1f2937}
.tk-res-m{font-size:.8rem;color:#6b7280;margin-top:2px;line-height:1.55}
.tk-warn{margin-top:10px;background:#fefce8;border:1px solid #fde68a;border-radius:10px;padding:9px 12px;color:#854d0e;font-size:.82rem;line-height:1.55}
.tk-keyl{margin-top:8px;font-size:.76rem;color:#6b7280;line-height:1.6}
.tk-tally{display:flex;gap:8px;flex-wrap:wrap;margin:12px 0 4px}
.tk-pill{border-radius:999px;padding:4px 13px;font-size:.8rem;font-weight:700}
.tk-pill.g{background:#dcfce7;color:#166534}.tk-pill.y{background:#fef9c3;color:#854d0e}.tk-pill.r{background:#fee2e2;color:#991b1b}.tk-pill.n{background:#eef2ff;color:#3730a3}
.tk-bar{display:flex;align-items:center;justify-content:space-between;gap:8px 14px;flex-wrap:wrap;margin:12px 0 10px;padding-top:10px;border-top:1px solid #f1f5f9}
.tk-seg{display:inline-flex;border:1.5px solid #fdba74;border-radius:10px;overflow:hidden}
.tk-seg button{border:0;background:#fff;color:#9a3412;padding:6px 13px;font-size:.8rem;font-weight:700;cursor:pointer;font-family:inherit}
.tk-seg button+button{border-left:1.5px solid #fdba74}
.tk-seg button.on{background:#ea580c;color:#fff}
.tk-seg-l{font-size:.74rem;font-weight:700;color:#9a3412;margin-right:6px}
.tk-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:10px;align-items:start}
.tk-mh{grid-column:1/-1;font-weight:800;color:#9a3412;font-size:.9rem;margin:8px 0 -2px;padding-bottom:4px;border-bottom:1.5px dashed #fed7aa}
.tk-mh span{font-weight:600;color:#6b7280;font-size:.78rem;margin-left:6px}
.tk-day{border:1px solid #e5e7eb;border-left:5px solid #9ca3af;border-radius:11px;padding:10px 12px;background:#fff;min-width:0}
.tk-day.g{border-left-color:#16a34a;background:linear-gradient(180deg,#f6fef9,#f0fdf4)}
.tk-day.y{border-left-color:#d97706;background:linear-gradient(180deg,#fffdf5,#fefce8)}
.tk-day.r{border-left-color:#dc2626;background:#fffafa}
.tk-dh{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-weight:800;font-size:.95rem;color:#1f2937}
.tk-wd{font-size:.78rem;color:#6b7280;font-weight:600}
.tk-gr{font-size:.72rem;border-radius:999px;padding:2px 10px;font-weight:800}
.tk-gr.g{background:#dcfce7;color:#166534}.tk-gr.y{background:#fef9c3;color:#854d0e}.tk-gr.r{background:#fee2e2;color:#991b1b}
.tk-sc{margin-left:auto;font-size:.76rem;font-weight:800;color:#6b7280}
.tk-fest{display:inline-block;font-size:.7rem;font-weight:700;background:#ede9fe;color:#5b21b6;border-radius:999px;padding:1px 8px;margin:4px 4px 0 0}
.tk-win{margin-top:7px;padding:7px 9px;border-radius:9px;background:rgba(255,255,255,.75);border:1px solid #eef2f7}
.tk-win-t{font-weight:800;color:#1f2937;font-size:.88rem}
.tk-win-t small{font-weight:600;color:#6b7280}
.tk-win-m{font-size:.76rem;color:#4b5563;margin-top:2px;line-height:1.5}
.tk-tag{display:inline-block;font-size:.7rem;border-radius:6px;padding:1px 6px;margin:3px 3px 0 0}
.tk-tag.g{background:#ecfdf5;color:#047857}.tk-tag.w{background:#fff7ed;color:#c2410c}
.tk-alt{font-size:.74rem;color:#6b7280;margin-top:5px}
.tk-rs{font-size:.78rem;color:#6b7280;margin-top:3px}
.tk-more-btn{margin-top:8px}
.tk-detail:empty{display:none}
.tk-detail{margin-top:9px}
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
/* पूरी सूची */
.tk-more{margin-top:12px}.tk-more summary{cursor:pointer;font-size:.84rem;font-weight:700;color:#9a3412}
.tk-tblw{overflow-x:auto;margin-top:8px}
.tk-tbl{width:100%;border-collapse:collapse;font-size:.78rem}
.tk-tbl th,.tk-tbl td{border:1px solid #eee;padding:4px 7px;text-align:left;white-space:nowrap}
.tk-tbl td:last-child{white-space:normal}
.tk-tbl th{background:#fff7ed;color:#9a3412}
.tk-tbl tr.g td{background:#f0fdf4}.tk-tbl tr.y td{background:#fefce8}.tk-tbl tr.r td{background:#fef2f2}
/* एक क्षण की जाँच (यही मुहूर्त फल के पैनल में भी) */
.tk-v{border-radius:12px;padding:10px 12px;border:1px solid #e5e7eb;border-left:5px solid #9ca3af;background:#fff}
.tk-v.g{border-left-color:#16a34a;background:linear-gradient(180deg,#f6fef9,#f0fdf4)}
.tk-v.y{border-left-color:#d97706;background:linear-gradient(180deg,#fffdf5,#fefce8)}
.tk-v.r{border-left-color:#dc2626;background:linear-gradient(180deg,#fffafa,#fef2f2)}
.tk-v-h{display:flex;align-items:center;gap:8px;flex-wrap:wrap;font-weight:800;color:#1f2937}
.tk-v-l{font-size:.8rem;margin-top:6px;line-height:1.6}
.tk-v-l b{color:#374151}
.tk-cg2{margin-top:10px}
.tk-cgrp{margin-top:8px}
.tk-cgrp-h{font-size:.76rem;font-weight:800;color:#9a3412;border-bottom:1px solid #fde4cc;padding-bottom:2px;margin-bottom:3px}
.tk-ci{display:grid;grid-template-columns:20px minmax(0,1fr) auto;gap:6px;font-size:.8rem;padding:3px 0;border-bottom:1px dotted #f1f5f9;align-items:start}
.tk-ci .i{text-align:center;font-weight:800}
.tk-ci.ok .i{color:#16a34a}.tk-ci.good .i{color:#059669}.tk-ci.warn .i{color:#d97706}.tk-ci.bad .i{color:#dc2626}.tk-ci.info .i{color:#6b7280}
.tk-ci.bad{background:#fef2f2}
.tk-ci .l b{color:#1f2937}
.tk-ci .l span{color:#6b7280}
.tk-ci .p{font-size:.72rem;font-weight:800;color:#6b7280;white-space:nowrap}
.tk-ci.good .p{color:#059669}.tk-ci.warn .p{color:#d97706}
.tk-pt{width:100%;border-collapse:collapse;font-size:.78rem;margin-top:6px}
.tk-pt th,.tk-pt td{border-top:1px solid #f1f5f9;padding:4px 6px;text-align:left;vertical-align:top}
.tk-pt th{color:#6b7280;font-weight:700;width:34%;white-space:nowrap}
.tk-mp-pick{display:flex;flex-wrap:wrap;gap:8px 10px;align-items:flex-end;margin-bottom:10px}
.tk-mp-when{font-size:.78rem;color:#6b7280;margin-bottom:8px}
</style>

<div id="card-tithikhoj" class="tk-card">
    <h2>🔍 तिथि खोज — शुभ मुहूर्त-तिथि खोजें</h2>
    <div class="tk-subt">कार्य चुनें, फिर अवधि व स्थान — सिस्टम उस अवधि के हर दिन को सूर्योदय से अगले सूर्योदय तक जाँचकर शुभ दिन और उनके शुभ समय (लग्न सहित) बताएगा।</div>

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
                    <label for="tk-sub">कार्य</label>
                    <select id="tk-sub" disabled>
                        <option value="">— पहले मुख्य श्रेणी चुनें —</option>
                    </select>
                </div>
            </div>
            <div id="tk-opts" class="tk-opts"></div>
            <details id="tk-rules" class="tk-rules" hidden>
                <summary>📜 इस कार्य के नियम (नियम-पुस्तिका से)</summary>
                <div class="tk-rules-b" id="tk-rules-b"></div>
            </details>
        </div>

        <div class="tk-step">
            <div class="tk-step-h"><span class="tk-num">2</span>अवधि व स्थान चुनें</div>
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
                    <label for="tk-place">कार्य-स्थान (शहर खोजें)</label>
                    <input id="tk-place" type="text" value="<?= $h($tkPlace) ?>" placeholder="शहर का नाम लिखें…" autocomplete="off">
                    <div id="tk-place-res" class="tk-place-res"></div>
                    <input type="hidden" id="tk-lat" value="<?= $h((string) $tkLat) ?>">
                    <input type="hidden" id="tk-lon" value="<?= $h((string) $tkLon) ?>">
                    <input type="hidden" id="tk-tz" value="<?= $h((string) $tkTz) ?>">
                    <input type="hidden" id="tk-zone" value="">
                    <span class="tk-tzn">जहाँ कार्य होगा — लग्न, सूर्योदय व राहुकाल यहीं के। पहले से: जन्म-स्थान।</span>
                </div>
            </div>
            <label class="tk-chk"><input type="checkbox" id="tk-personal" checked>
                <span>👤 <?= $h($tkName !== '' ? $tkName : 'खुली कुंडली') ?> का चंद्र-बल, तारा-बल व जन्म-नक्षत्र भी जाँचें
                    <span class="tk-tzn">(कार्य किसी और का हो — जैसे बच्चे का — तो उसकी कुंडली खोलें या यह हटा दें)</span></span></label>
        </div>
    </div>

    <div class="tk-go-row">
        <button type="button" id="tk-go" class="tk-btn" disabled>🔍 शुभ मुहूर्त-तिथि खोजें</button>
        <span id="tk-go-hint" class="tk-go-hint">पहले ऊपर कार्य चुनें</span>
    </div>
</div>

<div id="tk-results" class="tk-results" aria-live="polite"></div>

<script type="application/json" id="tk-data"><?= json_encode($tkView + ['place' => ['label' => $tkPlace, 'lat' => $tkLat, 'lon' => $tkLon, 'tz' => $tkTz]], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
