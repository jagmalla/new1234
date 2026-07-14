<?php
/**
 * आगामी गोचर (Upcoming Gochar) — reusable summary panel. Reads $ug
 * (UpcomingGochar::compute output). Scope: $ug, $h. Shown on the Gochar page
 * (below the chart) and the New/Profile screen (below the birth form).
 *
 * Simple Hindi, one line per event, date on every line (DD-MM-YYYY), empty
 * sections hidden. Colour: वक्री = orange badge, अस्त = red. Copy button copies
 * the whole summary as readable sentences.
 */
$ug = $ug ?? null;
if ($ug === null) {
    return;
}
// Build the plain-text copy version as we go.
$copy = ['आगामी गोचर — ' . $ug['now_dmy'] . ' से', ''];
$P = $ug['paksha'];
$nak = $ug['nakshatra'];
?>
<div class="ug-panel">
    <div class="ug-head">
        <span class="ug-title">🔭 आगामी गोचर <span class="ug-sub">(<?= $h($ug['now_dmy']) ?> से — सरल सारांश)</span></span>
        <button type="button" class="ug-copy" aria-label="Copy">📋 Copy</button>
    </div>

    <?php if (!empty($ug['nearest'])): $copy[] = 'अगली मुख्य घटना: ' . $ug['nearest']['text'] . ' (आज से ' . $ug['nearest']['days'] . ' दिन बाद)।'; ?>
    <div class="ug-highlight">अगली मुख्य गोचर घटना: <b><?= $h($ug['nearest']['text']) ?></b> (आज से <?= (int) $ug['nearest']['days'] ?> दिन बाद)।</div>
    <?php endif; ?>

    <?php if ($ug['retro_same'] !== []): $copy[] = ''; $copy[] = '॥ उसी राशि में वक्री ॥'; ?>
    <div class="ug-sec">
        <div class="ug-sec-h">वक्री होने वाले ग्रह (उसी राशि में)</div>
        <?php foreach ($ug['retro_same'] as $r):
            if (!empty($r['currently'])) {
                $s = !empty($r['back_date'])
                    ? $r['planet'] . ' वक्री होकर ' . $r['back_date'] . ' को पुनः ' . $r['back_sign_hi'] . ' राशि में लौटेगा।'
                    : $r['planet'] . ' इस समय ' . $r['sign_hi'] . ' राशि में वक्री है' . (!empty($r['direct_date']) ? ' (मार्गी ' . $r['direct_date'] . ')' : '') . '।';
            } else {
                $s = $r['planet'] . ' ' . ($r['start_date'] ?? '') . ' को ' . $r['sign_hi'] . ' राशि में ही वक्री होगा।';
            }
            $copy[] = $s;
        ?>
        <div class="ug-line"><span class="ug-badge ug-b-retro">वक्री</span> <?= $h($s) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ug-sec">
        <div class="ug-sec-h">पक्ष व चन्द्र</div>
        <?php
        $pl1 = 'वर्तमान पक्ष: ' . $P['name'] . ' (चन्द्रमा ' . ($P['waxing'] ? 'बढ़ रहा है' : 'घट रहा है') . ')।'
            . (!empty($P['moon_combust']) ? ' चन्द्रमा सूर्य के निकट (अस्त)' . ($P['near'] !== '' ? ' — ' . $P['near'] : '') . '।' : '');
        $copy[] = ''; $copy[] = $pl1;
        ?>
        <div class="ug-line"><?= $h($pl1) ?></div>
        <?php if (!empty($nak['name'])):
            $pl2 = 'चन्द्रमा इस समय ' . $nak['name'] . ' नक्षत्र (' . $nak['sign_hi'] . ') में है'
                . (!empty($nak['next_date']) ? '; ' . $nak['next_date'] . ' को ' . $nak['next_name'] . ' में जाएगा।' : '।');
            $copy[] = $pl2;
        ?>
        <div class="ug-line"><?= $h($pl2) ?></div>
        <?php endif; ?>
    </div>

    <div class="ug-sec">
        <div class="ug-sec-h">सूर्य से अस्त ग्रह</div>
        <?php if ($ug['combust'] !== []): $copy[] = ''; $copy[] = '॥ अस्त ग्रह ॥';
            foreach ($ug['combust'] as $c): $s = $c['planet'] . ' इस समय सूर्य से अस्त है (' . $c['sign_hi'] . ' राशि, सूर्य से ' . $c['sep'] . '° दूर)।'; $copy[] = $s; ?>
        <div class="ug-line"><span class="ug-badge ug-b-ast">अस्त</span> <?= $h($s) ?></div>
        <?php endforeach; else: $copy[] = 'इस समय कोई ग्रह सूर्य से अस्त नहीं है।'; ?>
        <div class="ug-line ug-muted">इस समय कोई ग्रह सूर्य से अस्त नहीं है।</div>
        <?php endif; ?>
    </div>

    <?php if ($ug['ingress'] !== []): $copy[] = ''; $copy[] = '॥ आगामी राशि-परिवर्तन ॥'; ?>
    <div class="ug-sec">
        <div class="ug-sec-h">आगामी राशि-परिवर्तन</div>
        <?php foreach ($ug['ingress'] as $ig): $s = $ig['planet'] . ' ' . $ig['date'] . ' को ' . $ig['sign_hi'] . ' राशि में प्रवेश करेगा।'; $copy[] = $s; ?>
        <div class="ug-line"><b class="ug-date"><?= $h($ig['date']) ?></b> — <?= $h($ig['planet']) ?> → <?= $h($ig['sign_hi']) ?> राशि।</div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php
    $rp = array_values(array_filter($ug['retro_periods'], static fn ($r) => $r['currently'] || $r['retro_start'] !== null));
    if ($rp !== []): $copy[] = ''; $copy[] = '॥ वक्री-अवधि ॥';
    ?>
    <div class="ug-sec">
        <div class="ug-sec-h">वक्री-अवधि (मंगल · बुध · गुरु · शुक्र · शनि)</div>
        <?php foreach ($rp as $r):
            $s = !empty($r['currently'])
                ? $r['planet'] . ' इस समय वक्री है' . ($r['direct'] ? ' — ' . $r['direct'] . ' को मार्गी होगा।' : '।')
                : $r['planet'] . ' ' . $r['retro_start'] . ($r['direct'] ? ' से ' . $r['direct'] . ' तक' : ' से') . ' वक्री रहेगा।';
            $copy[] = $s;
        ?>
        <div class="ug-line"><span class="ug-badge ug-b-retro">वक्री</span> <?= $h($s) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="ug-sec">
        <div class="ug-sec-h">धीमे ग्रह (गुरु · शनि) व साढ़े साती</div>
        <?php foreach ($ug['slow'] as $sl):
            $s = $sl['planet'] . ' इस समय ' . $sl['sign_hi'] . ' राशि में' . (!empty($sl['next_date']) ? '; अगला परिवर्तन ' . $sl['next_date'] . ' को ' . $sl['next_sign_hi'] . ' राशि में।' : '।');
            $copy[] = $s;
        ?>
        <div class="ug-line"><?= $h($s) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($ug['sade_sati'])): $ss = $ug['sade_sati'];
            $s = 'शनि ' . $ss['kind'] . ' — ' . ($ss['active'] ? 'इस समय चल रही है' : 'आगामी') . ' (' . $ss['start'] . ' से ' . $ss['end'] . ' · शनि ' . $ss['sign_hi'] . ' राशि)।';
            $copy[] = ''; $copy[] = $s;
        ?>
        <div class="ug-line <?= $ss['active'] ? 'ug-sade-active' : '' ?>"><span class="ug-badge <?= $ss['active'] ? 'ug-b-ast' : 'ug-b-retro' ?>">शनि</span> <?= $h($s) ?></div>
        <?php endif; ?>
    </div>

    <pre class="ug-copytext hidden"><?= $h(implode("\n", $copy)) ?></pre>
</div>
