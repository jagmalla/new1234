<?php
/**
 * आगामी गोचर (Upcoming Gochar) — colourful "chart" panel of cards.
 * Reads $ug (UpcomingGochar::compute output). Scope: $ug, $h.
 *
 * Sections (all as emoji cards, not paragraphs):
 *   • अगली गोचर घटनाएँ — top strip of the next few events
 *   • अस्त (Combustion) — वर्तमान / भूत / भविष्य, each with dates + rashi
 *   • वक्री (Retrograde) — वर्तमान / भूत / भविष्य, each with dates + rashi
 *   • राशि-परिवर्तन — timeline cards (from → to rashi with date)
 *   • चन्द्र-पक्ष — Shukla/Krishna paksha card with tithi, amavasya/purnima,
 *     nakshatra, rashi and Moon-combustion dates
 *   • साढ़े साती / धीमे ग्रह
 * Self-contained CSS (works with or without Tailwind).
 */
$ug = $ug ?? null;
if ($ug === null || !isset($ug['combust_parts'])) {
    return;
}
$h = $h ?? static fn ($x) => htmlspecialchars((string) $x, ENT_QUOTES, 'UTF-8');

/** Emit the shared stylesheet only once per page even if the partial repeats. */
static $ugxStyled = false;

// ---- plain-text copy (rebuilt from the structured data) ----
$copy = ['🔭 आगामी गोचर — ' . $ug['now_dmy'] . ' से', ''];
$fmtWin = static function (array $c, bool $withSign = true): string {
    $s = $c['emoji'] . ' ' . $c['planet'];
    if ($withSign) { $s .= ' (' . $c['sign_hi'] . ')'; }
    if (!empty($c['start']) && !empty($c['end'])) { $s .= ': ' . $c['start'] . ' → ' . $c['end']; }
    elseif (!empty($c['end'])) { $s .= ': ' . $c['end'] . ' तक'; }
    elseif (!empty($c['start'])) { $s .= ': ' . $c['start'] . ' से'; }
    return $s;
};

// A single combust/retro card renderer.
$renderWinCard = static function (array $c, string $tone, ?string $endLabel = null) use ($h): string {
    $out  = '<div class="ugx-card ugx-' . $tone . '">';
    $out .= '<div class="ugx-card-top"><span class="ugx-emo">' . $h($c['emoji']) . '</span>';
    $out .= '<span class="ugx-pl">' . $h($c['planet']) . '</span>';
    $out .= '<span class="ugx-rashi">' . $h($c['sign_emo'] ?? '') . ' ' . $h($c['sign_hi'] ?? '') . '</span></div>';
    if (isset($c['sep'])) {
        $out .= '<div class="ugx-mini">सूर्य से ' . $h($c['sep']) . '° दूर</div>';
    }
    $out .= '<div class="ugx-dates">';
    if (!empty($c['start'])) {
        $out .= '<span class="ugx-d"><b>आरम्भ</b> ' . $h($c['start']) . '</span>';
    }
    if (!empty($c['end'])) {
        $lbl = $endLabel ?? 'समाप्त';
        $out .= '<span class="ugx-d ugx-d-end"><b>' . $h($lbl) . '</b> ' . $h($c['end']);
        if (isset($c['end_days']) && $c['end_days'] !== null && (int) $c['end_days'] >= 0) {
            $out .= ' <em>(' . (int) $c['end_days'] . ' दिन)</em>';
        }
        $out .= '</span>';
    }
    if (empty($c['start']) && empty($c['end'])) {
        $out .= '<span class="ugx-d ugx-muted">तिथि अनुपलब्ध</span>';
    }
    $out .= '</div></div>';
    return $out;
};

$P = $ug['paksha_cal'];
?>
<?php if (!$ugxStyled): $ugxStyled = true; ?>
<style>
.ugx-panel{border:1px solid #e7dfce;border-radius:14px;background:linear-gradient(180deg,#fffdf8,#fbf6ec);
  padding:14px 14px 16px;margin:14px 0;font-family:inherit;color:#2a2419}
.ugx-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.ugx-h-title{font-weight:800;font-size:1.05rem;color:#3b2f18;flex:1;min-width:180px}
.ugx-h-title .ugx-h-sub{font-weight:500;font-size:.78rem;color:#9a8a68;display:block;margin-top:1px}
.ugx-copy{border:0;background:#eef2ff;color:#3730a3;border-radius:9px;padding:6px 12px;font-size:.82rem;
  font-weight:600;cursor:pointer}
.ugx-copy:hover{background:#e0e7ff}
.ugx-sec{margin:14px 0 0}
.ugx-sec-h{display:flex;align-items:center;gap:7px;font-weight:800;font-size:.92rem;color:#4a3c22;
  margin:0 0 8px;padding-bottom:5px;border-bottom:2px solid #efe6d3}
.ugx-sec-h .ugx-tag{margin-left:auto;font-size:.68rem;font-weight:700;color:#a08a5c;
  background:#f3ebd8;border-radius:20px;padding:2px 9px}
/* top events strip */
.ugx-strip{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 8px;-webkit-overflow-scrolling:touch}
.ugx-ev{flex:0 0 auto;min-width:118px;max-width:150px;border-radius:12px;padding:9px 10px;
  border:1px solid #e5e0d2;background:#fff;box-shadow:0 1px 3px rgba(80,60,20,.06)}
.ugx-ev-em{font-size:1.35rem;line-height:1}
.ugx-ev-tx{font-weight:700;font-size:.82rem;margin:5px 0 3px;color:#2c2415}
.ugx-ev-dt{font-size:.74rem;color:#6b5d3e;font-weight:600}
.ugx-ev-days{display:inline-block;margin-top:4px;font-size:.66rem;font-weight:700;border-radius:20px;padding:1px 8px}
.ugx-ev.t-good{border-color:#bbf7d0}.ugx-ev.t-good .ugx-ev-days{background:#dcfce7;color:#15803d}
.ugx-ev.t-warn{border-color:#fde68a}.ugx-ev.t-warn .ugx-ev-days{background:#fef3c7;color:#b45309}
.ugx-ev.t-move{border-color:#bfdbfe}.ugx-ev.t-move .ugx-ev-days{background:#dbeafe;color:#1d4ed8}
/* three-column grid */
.ugx-cols{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
@media(max-width:640px){.ugx-cols{grid-template-columns:1fr}}
.ugx-col{border-radius:12px;padding:9px;background:#fffdfa;border:1px solid #ece3d1}
.ugx-col-h{font-weight:800;font-size:.8rem;margin:0 0 8px;padding:4px 8px;border-radius:8px;text-align:center}
.ugx-col-now .ugx-col-h{background:#fee2e2;color:#b91c1c}
.ugx-col-past .ugx-col-h{background:#eef2f6;color:#64748b}
.ugx-col-fut .ugx-col-h{background:#fef3c7;color:#a16207}
.ugx-col-r-now .ugx-col-h{background:#ede9fe;color:#6d28d9}
.ugx-card{border-radius:10px;padding:8px 9px;margin-bottom:8px;background:#fff;border:1px solid #eee6d6}
.ugx-card:last-child{margin-bottom:0}
.ugx-card-top{display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.ugx-emo{font-size:1.1rem}
.ugx-pl{font-weight:800;font-size:.9rem;color:#2b2314}
.ugx-rashi{margin-left:auto;font-size:.76rem;font-weight:600;color:#6b5d3e;background:#f6efdf;
  border-radius:20px;padding:1px 8px}
.ugx-mini{font-size:.72rem;color:#94724a;margin-top:3px}
.ugx-dates{display:flex;flex-direction:column;gap:2px;margin-top:5px}
.ugx-d{font-size:.76rem;color:#4b4433}.ugx-d b{color:#8a7a55;font-weight:700;margin-right:3px}
.ugx-d em{color:#9a8a68;font-style:normal}
.ugx-d-end b{color:#15803d}
.ugx-now .ugx-d-end b{color:#b91c1c}
.ugx-muted{color:#b3a88f}
.ugx-empty{font-size:.8rem;color:#b3a88f;text-align:center;padding:10px 0}
/* combust tone accents */
.ugx-now{border-color:#fecaca;background:#fff6f6}
.ugx-past{border-color:#e5e7eb;background:#fafbfc}
.ugx-fut{border-color:#fde68a;background:#fffdf3}
.ugx-retro{border-color:#ddd6fe;background:#faf9ff}
/* rashi-change timeline cards */
.ugx-ing{display:flex;gap:9px;overflow-x:auto;padding:2px 2px 8px}
.ugx-ing-card{flex:0 0 auto;min-width:132px;border-radius:12px;padding:9px 10px;background:#fff;
  border:1px solid #cdebe0;box-shadow:0 1px 3px rgba(20,80,60,.06)}
.ugx-ing-top{display:flex;align-items:center;gap:6px;font-weight:800;font-size:.86rem;color:#2b2314}
.ugx-ing-move{font-size:.95rem;font-weight:700;color:#0f766e;margin:6px 0 2px}
.ugx-ing-dt{font-size:.74rem;color:#5b6b64;font-weight:600}
.ugx-ing-dt em{color:#14b8a6;font-style:normal;font-weight:700}
/* paksha card */
.ugx-paksha{display:flex;gap:14px;align-items:center;flex-wrap:wrap;border-radius:12px;padding:12px 14px;
  background:linear-gradient(135deg,#eef2ff,#f5f3ff);border:1px solid #ddd6fe}
.ugx-moon{width:60px;height:60px;border-radius:50%;flex:none;position:relative;
  box-shadow:0 2px 8px rgba(70,60,120,.25)}
.ugx-moon.waxing{background:linear-gradient(90deg,#3b355e 0 50%,#fde68a 50% 100%)}
.ugx-moon.waning{background:linear-gradient(90deg,#fde68a 0 50%,#3b355e 50% 100%)}
.ugx-pk-info{flex:1;min-width:180px}
.ugx-pk-name{font-weight:800;font-size:1rem;color:#5b21b6}
.ugx-pk-name .ugx-tithi{font-size:.76rem;font-weight:600;color:#7c6fa8;margin-left:6px}
.ugx-pk-row{font-size:.82rem;color:#4b4166;margin-top:4px}
.ugx-pk-row b{color:#6d28d9}
.ugx-pk-chips{display:flex;gap:7px;flex-wrap:wrap;margin-top:8px}
.ugx-chip{font-size:.74rem;font-weight:700;border-radius:20px;padding:3px 10px}
.ugx-chip.ama{background:#e5e7eb;color:#374151}
.ugx-chip.pur{background:#fef9c3;color:#a16207}
.ugx-chip.comb{background:#fee2e2;color:#b91c1c}
.ugx-copytext{display:none}
</style>
<?php endif; ?>

<div class="ugx-panel">
    <div class="ugx-head">
        <div class="ugx-h-title">🔭 आगामी गोचर / Upcoming Transit
            <span class="ugx-h-sub"><?= $h($ug['now_dmy']) ?> से · वर्तमान · भूत · भविष्य (तिथि व राशि सहित)</span>
        </div>
        <button type="button" class="ugx-copy" aria-label="Copy">📋 Copy</button>
    </div>

    <?php
    // ============================ TOP EVENTS ============================
    if (!empty($ug['top_events'])):
        $copy[] = '📌 अगली घटनाएँ:';
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">📌 अगली गोचर घटनाएँ <span class="ugx-tag">next events</span></div>
        <div class="ugx-strip">
            <?php foreach ($ug['top_events'] as $e):
                $copy[] = '  • ' . $e['emoji'] . ' ' . $e['text'] . ' — ' . $e['date'] . ' (' . (int) $e['days'] . ' दिन बाद)';
            ?>
            <div class="ugx-ev t-<?= $h($e['tone']) ?>">
                <div class="ugx-ev-em"><?= $h($e['emoji']) ?></div>
                <div class="ugx-ev-tx"><?= $h($e['text']) ?></div>
                <div class="ugx-ev-dt"><?= $h($e['date']) ?></div>
                <div class="ugx-ev-days"><?= (int) $e['days'] ?> दिन बाद</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // =========================== MOON PAKSHA ===========================
    // Placed high — right under the "next events" strip — so the day's Moon
    // paksha / tithi is visible without scrolling.
    $copy[] = '';
    $copy[] = '🌙 ' . $P['name'] . ' · तिथि ' . $P['tithi'] . ' · चन्द्रमा ' . $P['sign_hi'] . ' (' . $P['nak'] . ')';
    if (!empty($P['next_amavasya'])) { $copy[] = '  🌑 अगली अमावस्या: ' . $P['next_amavasya']; }
    if (!empty($P['next_purnima'])) { $copy[] = '  🌕 अगली पूर्णिमा: ' . $P['next_purnima']; }
    if (!empty($P['moon_combust_start'])) { $copy[] = '  ☀️ चन्द्र-अस्त: ' . $P['moon_combust_start'] . ' → ' . ($P['moon_combust_end'] ?? '?'); }
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">🌙 चन्द्र-पक्ष व तिथि <span class="ugx-tag">Moon paksha</span></div>
        <div class="ugx-paksha">
            <div class="ugx-moon <?= !empty($P['waxing']) ? 'waxing' : 'waning' ?>"></div>
            <div class="ugx-pk-info">
                <div class="ugx-pk-name"><?= $h($P['name']) ?>
                    <span class="ugx-tithi">तिथि <?= (int) $P['tithi'] ?>/30 · <?= !empty($P['waxing']) ? 'चन्द्र बढ़ रहा 🌔' : 'चन्द्र घट रहा 🌘' ?></span>
                </div>
                <div class="ugx-pk-row">चन्द्रमा <b><?= $h($P['sign_emo']) ?> <?= $h($P['sign_hi']) ?></b> राशि · <b><?= $h($P['nak']) ?></b> नक्षत्र</div>
                <div class="ugx-pk-chips">
                    <?php if (!empty($P['next_amavasya'])): ?><span class="ugx-chip ama">🌑 अमावस्या <?= $h($P['next_amavasya']) ?></span><?php endif; ?>
                    <?php if (!empty($P['next_purnima'])): ?><span class="ugx-chip pur">🌕 पूर्णिमा <?= $h($P['next_purnima']) ?></span><?php endif; ?>
                    <?php if (!empty($P['moon_combust_start'])): ?>
                        <span class="ugx-chip comb">☀️ चन्द्र-अस्त <?= $h($P['moon_combust_start']) ?><?= !empty($P['moon_combust_end']) ? ' → ' . $h($P['moon_combust_end']) : '' ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // ============================ COMBUSTION ============================
    $cp = $ug['combust_parts'];
    $copy[] = ''; $copy[] = '☀️ अस्त (सूर्य के निकट):';
    foreach (['current' => 'वर्तमान', 'past' => 'भूत', 'future' => 'भविष्य'] as $k => $lbl) {
        foreach ($cp[$k] as $c) { $copy[] = '  [' . $lbl . '] ' . $fmtWin($c); }
    }
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">☀️ अस्त — सूर्य के निकट (उदय-तिथि सहित) <span class="ugx-tag">combustion</span></div>
        <div class="ugx-cols">
            <div class="ugx-col ugx-col-now">
                <div class="ugx-col-h">🔴 वर्तमान में अस्त</div>
                <?php if ($cp['current'] === []): ?><div class="ugx-empty">कोई ग्रह अस्त नहीं ✅</div><?php endif; ?>
                <?php foreach ($cp['current'] as $c) { echo $renderWinCard(array_merge($c, []), 'now', 'उदय (सामान्य)'); } ?>
            </div>
            <div class="ugx-col ugx-col-past">
                <div class="ugx-col-h">🕘 पूर्व (अस्त रहा)</div>
                <?php if ($cp['past'] === []): ?><div class="ugx-empty">—</div><?php endif; ?>
                <?php foreach ($cp['past'] as $c) { echo $renderWinCard($c, 'past'); } ?>
            </div>
            <div class="ugx-col ugx-col-fut">
                <div class="ugx-col-h">🔜 आगामी (अस्त होगा)</div>
                <?php if ($cp['future'] === []): ?><div class="ugx-empty">—</div><?php endif; ?>
                <?php foreach ($cp['future'] as $c) { echo $renderWinCard($c, 'fut', 'उदय'); } ?>
            </div>
        </div>
    </div>

    <?php
    // ============================ RETROGRADE ============================
    $rp = $ug['retro_parts'];
    $copy[] = ''; $copy[] = '↩️ वक्री (Retrograde):';
    foreach (['current' => 'वर्तमान', 'past' => 'भूत', 'future' => 'भविष्य'] as $k => $lbl) {
        foreach ($rp[$k] as $c) { $copy[] = '  [' . $lbl . '] ' . $fmtWin($c); }
    }
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">↩️ वक्री — मार्गी-तिथि सहित <span class="ugx-tag">retrograde</span></div>
        <div class="ugx-cols">
            <div class="ugx-col ugx-col-r-now">
                <div class="ugx-col-h">🟣 वर्तमान में वक्री</div>
                <?php if ($rp['current'] === []): ?><div class="ugx-empty">कोई ग्रह वक्री नहीं</div><?php endif; ?>
                <?php foreach ($rp['current'] as $c) { echo $renderWinCard($c, 'retro', 'मार्गी'); } ?>
            </div>
            <div class="ugx-col ugx-col-past">
                <div class="ugx-col-h">🕘 पूर्व (वक्री रहा)</div>
                <?php if ($rp['past'] === []): ?><div class="ugx-empty">—</div><?php endif; ?>
                <?php foreach ($rp['past'] as $c) { echo $renderWinCard($c, 'past', 'मार्गी'); } ?>
            </div>
            <div class="ugx-col ugx-col-fut">
                <div class="ugx-col-h">🔜 आगामी (वक्री होगा)</div>
                <?php if ($rp['future'] === []): ?><div class="ugx-empty">—</div><?php endif; ?>
                <?php foreach ($rp['future'] as $c) { echo $renderWinCard($c, 'fut', 'मार्गी'); } ?>
            </div>
        </div>
    </div>

    <?php
    // ========================== RASHI CHANGE ==========================
    if (!empty($ug['ingress_cards'])):
        $copy[] = ''; $copy[] = '♻️ राशि-परिवर्तन:';
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">♻️ आगामी राशि-परिवर्तन <span class="ugx-tag">sign change</span></div>
        <div class="ugx-ing">
            <?php foreach ($ug['ingress_cards'] as $ig):
                $copy[] = '  • ' . $ig['emoji'] . ' ' . $ig['planet'] . ': ' . $ig['from_hi'] . ' → ' . $ig['to_hi'] . ' (' . $ig['date'] . ')';
            ?>
            <div class="ugx-ing-card">
                <div class="ugx-ing-top"><?= $h($ig['emoji']) ?> <?= $h($ig['planet']) ?></div>
                <div class="ugx-ing-move"><?= $h($ig['from_emo']) ?> <?= $h($ig['from_hi']) ?> → <?= $h($ig['to_emo']) ?> <?= $h($ig['to_hi']) ?></div>
                <div class="ugx-ing-dt"><?= $h($ig['date']) ?> · <em><?= (int) $ig['days'] ?> दिन बाद</em></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php
    // ===================== SLOW PLANETS / SADE SATI =====================
    if (!empty($ug['slow']) || !empty($ug['sade_sati'])):
        $copy[] = '';
    ?>
    <div class="ugx-sec">
        <div class="ugx-sec-h">🪐 धीमे ग्रह व साढ़े साती <span class="ugx-tag">Jupiter · Saturn</span></div>
        <div class="ugx-strip">
            <?php foreach (($ug['slow'] ?? []) as $sl):
                $s = $sl['planet'] . ' — ' . $sl['sign_hi'] . ' राशि' . (!empty($sl['next_date']) ? '; ' . $sl['next_date'] . ' को ' . $sl['next_sign_hi'] : '');
                $copy[] = '🪐 ' . $s;
            ?>
            <div class="ugx-ev t-move">
                <div class="ugx-ev-em">🪐</div>
                <div class="ugx-ev-tx"><?= $h($sl['planet']) ?> · <?= $h($sl['sign_hi']) ?></div>
                <?php if (!empty($sl['next_date'])): ?>
                <div class="ugx-ev-dt">→ <?= $h($sl['next_sign_hi']) ?></div>
                <div class="ugx-ev-days"><?= $h($sl['next_date']) ?></div>
                <?php else: ?><div class="ugx-ev-dt">इसी राशि में</div><?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if (!empty($ug['sade_sati'])): $ss = $ug['sade_sati'];
                $copy[] = 'शनि ' . $ss['kind'] . ' — ' . ($ss['active'] ? 'चल रही' : 'आगामी') . ' (' . $ss['start'] . ' → ' . $ss['end'] . ')';
            ?>
            <div class="ugx-ev <?= !empty($ss['active']) ? 't-warn' : 't-move' ?>" style="min-width:150px">
                <div class="ugx-ev-em">🪐</div>
                <div class="ugx-ev-tx">शनि <?= $h($ss['kind']) ?> <?= !empty($ss['active']) ? '⚠️' : '' ?></div>
                <div class="ugx-ev-dt"><?= !empty($ss['active']) ? 'इस समय चल रही' : 'आगामी' ?> · <?= $h($ss['sign_hi']) ?></div>
                <div class="ugx-ev-days"><?= $h($ss['start']) ?> → <?= $h($ss['end']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <pre class="ugx-copytext"><?= $h(implode("\n", $copy)) ?></pre>
</div>
<?php
// Copy button wiring (emitted once).
if (!isset($GLOBALS['__ugx_copy_js'])) { $GLOBALS['__ugx_copy_js'] = true; ?>
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.ugx-copy') : null;
    if (!btn) { return; }
    var panel = btn.closest('.ugx-panel');
    var pre = panel && panel.querySelector('.ugx-copytext');
    if (!pre) { return; }
    var txt = pre.textContent;
    var done = function () { var o = btn.textContent; btn.textContent = '✅ कॉपी हुआ'; setTimeout(function () { btn.textContent = o; }, 1500); };
    if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(txt).then(done, done); }
    else { var ta = document.createElement('textarea'); ta.value = txt; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (x) {} document.body.removeChild(ta); done(); }
});
</script>
<?php } ?>
