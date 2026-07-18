<?php
/**
 * Renders the calculated Dasha-engine cards (दशा फल v2). Included both on the
 * initial page and by the /calc/dashaEngine JSON endpoint (so the markup has a
 * single source). Expects $eng (DashaPhalEngine::compute output) and $h.
 * @var array<string,mixed> $eng
 * @var callable $h
 */
$dv = ['shubh' => 'gc-shubh', 'mishrit' => 'gc-mishrit', 'ashubh' => 'gc-ashubh'];
/** One card block: header + कारण facts + dot-labelled text + remedy. */
$card = static function (array $c) use ($h, $dv): void {
    $tier = $dv[$c['tier'] ?? 'mishrit'] ?? 'gc-mishrit';
    echo '<div class="de-card">';
    echo '<div class="de-head"><span class="de-title">' . $h((string) ($c['header'] ?? '')) . '</span>';
    if (!empty($c['verdict'])) {
        echo '<span class="gc-chip ' . $tier . '">' . $h((string) $c['verdict']) . '</span>';
    }
    echo '</div>';
    if (!empty($c['facts'])) {
        echo '<div class="de-facts">कारण: ' . $h((string) $c['facts']) . '</div>';
    }
    foreach (($c['text'] ?? []) as $t) {
        $cls = $t['kind'] === 'neg' ? 'de-neg' : 'de-pos';
        $lbl = $t['kind'] === 'neg' ? '● नकारात्मक' : '● सकारात्मक';
        echo '<div class="de-line ' . $cls . '"><b>' . $lbl . '</b> — ' . $h((string) $t['body']) . '</div>';
    }
    if (!empty($c['remedy'])) {
        echo '<div class="de-rembox"><b>🛠 उपाय</b> — ' . $h((string) $c['remedy']) . '</div>';
    }
    echo '</div>';
};

$ovTier = $dv[$eng['overall']['tier'] ?? 'mishrit'] ?? 'gc-mishrit';
?>
<!-- 1. समग्र निष्कर्ष -->
<div class="de-overall">
    <span class="gc-chip <?= $ovTier ?>" style="font-size:.8rem"><?= $h((string) ($eng['overall']['verdict'] ?? '')) ?></span>
    <span class="de-overall-txt"><?= $h((string) ($eng['overall']['sentence'] ?? '')) ?></span>
</div>

<!-- 2. महादशा स्वामी — लग्न से -->
<?php $card($eng['maha_card']); ?>
<!-- 3. अंतर्दशा स्वामी — लग्न से -->
<?php $card($eng['antar_lagna_card']); ?>
<!-- 4. अंतर्दशा स्वामी — महादशेश से -->
<?php $card($eng['antar_maha_card']); ?>

<!-- 5. भावेश प्रभाव -->
<?php foreach (($eng['bhavesh_cards'] ?? []) as $bc) { $card($bc); } ?>

<!-- 6. उपाय (संकलित) -->
<?php if (!empty($eng['remedies'])): ?>
<div class="de-card">
    <div class="de-head"><span class="de-title">🛠 उपाय (संकलित)</span></div>
    <div class="de-rembox" style="margin-top:4px">
        <ul class="de-remlist">
            <?php foreach ($eng['remedies'] as $rm): ?><li>● <?= $h((string) $rm) ?></li><?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>
