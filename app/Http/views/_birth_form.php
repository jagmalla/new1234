<?php
/**
 * Birth-details entry form (layout v2). Rendered in exactly ONE place per
 * request — standalone when there is no chart yet, or inside the "New / Profile"
 * side-menu section once a chart is shown — so its element IDs never duplicate.
 *
 * Expects $in (input values) and $h (escaper) from the including view.
 * @var array<string,string> $in
 * @var callable $h
 */
?>
<form id="birth-form" method="get" action="/calc" class="l2-card p-4 text-sm">
    <h2 class="font-semibold mb-3 text-gray-700">Chart Calculation Details</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <label class="flex flex-col gap-1"><span class="text-gray-500">Name</span>
            <input name="name" value="<?= $h($in['name']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Gender</span>
            <select name="gender" class="border rounded px-2 py-1 bg-white">
                <?php foreach (['' => '—', 'Male' => 'Male', 'Female' => 'Female', 'Other' => 'Other'] as $gv => $gl): ?>
                    <option value="<?= $h($gv) ?>" <?= $in['gender'] === $gv ? 'selected' : '' ?>><?= $h($gl) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Date (DD-MM-YYYY)</span>
            <input name="date" value="<?= $h($in['date']) ?>" placeholder="DD-MM-YYYY or DD MM YYYY" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Time (HH:MM)</span>
            <input name="time" value="<?= $h($in['time']) ?>" placeholder="HH:MM or HH MM" class="border rounded px-2 py-1"></label>

        <label class="flex flex-col gap-1 relative sm:col-span-2 lg:col-span-4"><span class="text-gray-500">Place (search city, state or country — fills lat/lon/timezone)</span>
            <input id="b-place" name="place" value="<?= $h($in['place']) ?>" type="text" autocomplete="off" placeholder="Type a city, e.g. Moga or London…" class="border rounded px-2 py-1">
            <div id="b-place-results" class="absolute z-20 left-0 right-0 top-full mt-1 bg-white border rounded shadow max-h-60 overflow-y-auto hidden"></div></label>
    </div>

    <!-- Advanced: normally auto-filled by the city search, so hidden by default. -->
    <div id="b-advanced" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-3 hidden">
        <label class="flex flex-col gap-1"><span class="text-gray-500">Ayanamsa</span>
            <select name="ayanamsa" class="border rounded px-2 py-1 bg-white">
                <?php foreach (['lahiri' => 'Lahiri (Chitrapaksha)', 'raman' => 'B.V. Raman', 'kp' => 'KP (Krishnamurti)', 'fagan_bradley' => 'Fagan-Bradley'] as $av => $al): ?>
                    <option value="<?= $h($av) ?>" <?= $in['ayanamsa'] === $av ? 'selected' : '' ?>><?= $h($al) ?></option>
                <?php endforeach; ?>
            </select></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Latitude</span>
            <input id="b-lat" name="lat" value="<?= $h($in['latIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Longitude</span>
            <input id="b-lon" name="lon" value="<?= $h($in['lonIn']) ?>" class="border rounded px-2 py-1"></label>
        <label class="flex flex-col gap-1"><span class="text-gray-500">Timezone (east +)</span>
            <input id="b-tz" name="tz" value="<?= $h($in['tzIn']) ?>" class="border rounded px-2 py-1"></label>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mt-3">
        <button type="button" id="b-adv-toggle" class="text-sm text-blue-700 font-semibold border border-blue-200 rounded px-3 py-2 hover:bg-blue-50" aria-expanded="false" aria-controls="b-advanced">⚙ Advanced options (Ayanamsa · Lat/Lon · Timezone)</button>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" data-ab-open class="text-sm font-semibold border border-gray-300 rounded px-3 py-2 hover:bg-gray-50" title="अपने सहेजे गए चार्ट खोलें">📂 Open Saved Charts</button>
            <button type="button" data-ab-save class="text-sm font-semibold text-white rounded px-3 py-2" style="background:#0f766e" title="इस चार्ट को सहेजें">💾 Save Chart</button>
            <button class="bg-blue-600 text-white rounded px-4 py-2 font-semibold">Calculate</button>
        </div>
    </div>
    <p class="text-xs text-gray-500 mt-2">Search any city worldwide to fill lat/lon/timezone automatically. To type lat/lon or timezone by hand (also DMS, e.g. 30N48'00), open <b>Advanced options</b>. Timezone is the place's offset on the birth date.</p>
</form>
<script>
(function () {
    var t = document.getElementById('b-adv-toggle'), a = document.getElementById('b-advanced');
    if (!t || !a) { return; }
    t.addEventListener('click', function () {
        var open = a.classList.toggle('hidden') === false;
        t.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
})();

/* ------------------------------------------------------------------------
 * Date / Time entry: auto-fix when possible, clear popup when not.
 *
 * The birth date drives the whole chart, so a mistyped date must never pass
 * through silently. On leaving a field we auto-correct common formats to the
 * canonical DD-MM-YYYY / HH:MM (accepting month names, AM/PM, and -, /, ., or
 * space separators). On Calculate we re-check: anything still unparseable or
 * impossible (e.g. 31-02-1983, a 2-digit year) stops submission and shows a
 * bilingual popup explaining the correct format.
 * --------------------------------------------------------------------- */
(function () {
    var form = document.getElementById('birth-form');
    if (!form) { return; }
    var dateEl = form.querySelector('[name="date"]');
    var timeEl = form.querySelector('[name="time"]');

    var pad2 = function (n) { return (n < 10 ? '0' : '') + n; };

    var MONTHS = { jan:1, feb:2, mar:3, apr:4, may:5, jun:6, jul:7, aug:8, sep:9, oct:10, nov:11, dec:12 };
    var monthFromName = function (tok) {
        var key = String(tok).toLowerCase().slice(0, 3);
        return MONTHS[key] || 0;
    };
    var daysInMonth = function (m, y) {
        var leap = (y % 4 === 0 && y % 100 !== 0) || (y % 400 === 0);
        return [31, leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][m - 1];
    };

    // Returns { empty:true } | { ok:true, value:'DD-MM-YYYY' } | { ok:false, reason:'…' }
    var parseDate = function (raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') { return { empty: true }; }
        var parts = s.split(/[\-\/.,\s]+/).filter(Boolean);
        if (parts.length !== 3) {
            return { ok: false, reason: 'तीन भाग होने चाहिए: दिन, महीना, वर्ष / Need three parts: day, month, year.' };
        }
        var d, m, y, monthIdx = -1, i;
        for (i = 0; i < 3; i++) { if (/[a-z]/i.test(parts[i])) { monthIdx = i; } }

        if (monthIdx !== -1) {
            m = monthFromName(parts[monthIdx]);
            if (!m) { return { ok: false, reason: 'महीने का नाम पहचान नहीं पाए / Could not recognise the month name.' }; }
            var others = [];
            for (i = 0; i < 3; i++) { if (i !== monthIdx) { others.push(parts[i]); } }
            if (others.some(function (x) { return !/^\d+$/.test(x); })) {
                return { ok: false, reason: 'दिन और वर्ष अंकों में लिखें / Day and year must be numbers.' };
            }
            var n0 = parseInt(others[0], 10), n1 = parseInt(others[1], 10);
            // The 4-digit (or >31) value is the year; the other is the day.
            if (others[0].length === 4 || n0 > 31) { y = n0; d = n1; }
            else { d = n0; y = n1; }
        } else {
            if (parts.some(function (x) { return !/^\d+$/.test(x); })) {
                return { ok: false, reason: 'केवल अंक और महीने का नाम चलेगा / Use numbers (or a month name).' };
            }
            var a = parseInt(parts[0], 10), b = parseInt(parts[1], 10), c = parseInt(parts[2], 10);
            // 4-digit first field => YYYY-MM-DD, otherwise DD-MM-YYYY.
            if (parts[0].length === 4 || a > 31) { y = a; m = b; d = c; }
            else { d = a; m = b; y = c; }
        }

        if (y < 100) { return { ok: false, reason: 'वर्ष पूरा (4 अंकों में) लिखें, जैसे 1983 / Write the year in full, e.g. 1983.' }; }
        if (m < 1 || m > 12) { return { ok: false, reason: 'महीना 1 से 12 के बीच होना चाहिए / Month must be between 1 and 12.' }; }
        if (d < 1 || d > daysInMonth(m, y)) { return { ok: false, reason: 'यह दिन इस महीने में मौजूद नहीं है / That day does not exist in that month.' }; }
        return { ok: true, value: pad2(d) + '-' + pad2(m) + '-' + y };
    };

    // Returns { empty:true } | { ok:true, value:'HH:MM' } | { ok:false, reason:'…' }
    var parseTime = function (raw) {
        var s = String(raw == null ? '' : raw).trim().toLowerCase();
        if (s === '') { return { empty: true }; }
        var ampm = null;
        if (/\bam\b|a\.?m\.?/.test(s)) { ampm = 'am'; }
        else if (/\bpm\b|p\.?m\.?/.test(s)) { ampm = 'pm'; }
        s = s.replace(/[ap]\.?m\.?/g, ' ').trim();
        var parts = s.split(/[:\s.]+/).filter(Boolean);
        if (!parts.length || parts.some(function (x) { return !/^\d+$/.test(x); })) {
            return { ok: false, reason: 'समय घंटा:मिनट के रूप में लिखें / Write the time as hour:minute.' };
        }
        var h = parseInt(parts[0], 10), mi = parseInt(parts[1] || '0', 10);
        if (ampm) {
            if (h < 1 || h > 12) { return { ok: false, reason: 'AM/PM के साथ घंटा 1 से 12 तक होता है / With AM/PM the hour is 1–12.' }; }
            h = (h % 12) + (ampm === 'pm' ? 12 : 0);
        }
        if (h > 23 || mi > 59) { return { ok: false, reason: 'घंटा 0–23 और मिनट 0–59 के बीच / Hour 0–23 and minute 0–59.' }; }
        return { ok: true, value: pad2(h) + ':' + pad2(mi) };
    };

    // --- Auto-fix on blur (only when it parses cleanly) ---
    var autoFix = function (el, parse) {
        if (!el) { return; }
        el.addEventListener('blur', function () {
            if (!el.value.trim()) { return; }
            var r = parse(el.value);
            if (r.ok) { el.value = r.value; }
        });
    };
    autoFix(dateEl, parseDate);
    autoFix(timeEl, parseTime);

    // --- Popup ---
    var showPopup = function (heading, rawValue, howto, reason, focusEl) {
        var old = document.getElementById('ab-fmt-modal');
        if (old) { old.parentNode.removeChild(old); }
        var esc = function (x) { return String(x).replace(/[&<>"]/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };

        var wrap = document.createElement('div');
        wrap.id = 'ab-fmt-modal';
        wrap.setAttribute('role', 'alertdialog');
        wrap.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;' +
            'justify-content:center;background:rgba(0,0,0,.45);padding:16px;';
        wrap.innerHTML =
            '<div style="background:#fff;max-width:440px;width:100%;border-radius:12px;overflow:hidden;' +
                'box-shadow:0 20px 50px rgba(0,0,0,.35);font-family:inherit;">' +
              '<div style="background:#b3541e;color:#fff;padding:12px 18px;font-weight:700;font-size:15px;">⚠ ' + esc(heading) + '</div>' +
              '<div style="padding:16px 18px;color:#333;font-size:14px;line-height:1.6;">' +
                (rawValue ? '<div style="margin-bottom:8px;">आपने लिखा / You entered: <b style="color:#b3541e;">«' + esc(rawValue) + '»</b></div>' : '') +
                (reason ? '<div style="margin-bottom:10px;">' + esc(reason) + '</div>' : '') +
                '<div style="background:#f6f1e7;border:1px solid #e6dcc6;border-radius:8px;padding:10px 12px;">' + howto + '</div>' +
              '</div>' +
              '<div style="padding:0 18px 16px;text-align:right;">' +
                '<button type="button" id="ab-fmt-ok" style="background:#b3541e;color:#fff;border:0;border-radius:8px;' +
                  'padding:8px 20px;font-weight:600;cursor:pointer;">ठीक है / OK</button>' +
              '</div>' +
            '</div>';
        document.body.appendChild(wrap);

        var close = function () {
            if (wrap.parentNode) { wrap.parentNode.removeChild(wrap); }
            if (focusEl) { focusEl.focus(); focusEl.select && focusEl.select(); }
        };
        wrap.querySelector('#ab-fmt-ok').addEventListener('click', close);
        wrap.addEventListener('click', function (e) { if (e.target === wrap) { close(); } });
        document.addEventListener('keydown', function onEsc(e) {
            if (e.key === 'Escape') { close(); document.removeEventListener('keydown', onEsc); }
        });
        wrap.querySelector('#ab-fmt-ok').focus();
    };

    var DATE_HOWTO = 'तारीख़ इस तरह लिखें — <b>दिन-महीना-वर्ष</b> (DD-MM-YYYY):<br>' +
        'उदाहरण / Examples: <b>20-01-1983</b>, <b>20-Jan-1983</b>, <b>20/01/1983</b>.';
    var TIME_HOWTO = 'समय इस तरह लिखें — <b>घंटा:मिनट</b> (24-घंटे / 24-hour HH:MM):<br>' +
        'उदाहरण / Examples: <b>09:05</b>, <b>21:30</b>, या <b>9:05 AM</b>, <b>9:30 PM</b>.';

    // --- Validate on Calculate ---
    form.addEventListener('submit', function (e) {
        if (dateEl && dateEl.value.trim()) {
            var rd = parseDate(dateEl.value);
            if (rd.empty) { /* allow */ }
            else if (!rd.ok) {
                e.preventDefault();
                showPopup('तारीख़ की जाँच करें / Check the date', dateEl.value, DATE_HOWTO, rd.reason, dateEl);
                return;
            } else { dateEl.value = rd.value; }  // normalise before it reaches the server
        }
        if (timeEl && timeEl.value.trim()) {
            var rt = parseTime(timeEl.value);
            if (rt.empty) { /* allow */ }
            else if (!rt.ok) {
                e.preventDefault();
                showPopup('समय की जाँच करें / Check the time', timeEl.value, TIME_HOWTO, rt.reason, timeEl);
                return;
            } else { timeEl.value = rt.value; }
        }
    });
})();
</script>
