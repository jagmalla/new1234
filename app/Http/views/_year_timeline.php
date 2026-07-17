<?php
/**
 * आगामी 12 महीने की समय-रेखा — merged calendar of ingresses, वक्री/मार्गी,
 * dasha changes and transit-hits (YearTimeline::compute), grouped by month.
 * Scope: $view['year_timeline'], $h.
 */
$tl = $view['year_timeline'] ?? null;
$evs = is_array($tl) ? ($tl['events'] ?? []) : [];
$hindiMonth = ['01' => 'जनवरी', '02' => 'फरवरी', '03' => 'मार्च', '04' => 'अप्रैल', '05' => 'मई', '06' => 'जून',
    '07' => 'जुलाई', '08' => 'अगस्त', '09' => 'सितम्बर', '10' => 'अक्टूबर', '11' => 'नवम्बर', '12' => 'दिसम्बर'];
$dotStyle = static fn (string $t): string => $t === 'pos' ? 'background:#22c55e' : ($t === 'neg' ? 'background:#ef4444' : 'background:#3b82f6');
$rowStyle = static fn (string $t): string => $t === 'pos' ? 'background:#f0fdf4' : ($t === 'neg' ? 'background:#fef2f2' : '');
?>
<div class="bg-white rounded-lg shadow p-4 text-sm" id="yt-card">
    <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mb-2">
        <h2 class="font-semibold">12-Month Timeline <span class="text-xs text-gray-400 font-normal">(आगामी 12 महीने — गोचर · दशा · संयोग)</span></h2>
        <?php if ($tl !== null): ?><span class="text-xs text-gray-400"><?= $h((string) $tl['from']) ?> से <?= $h((string) $tl['to']) ?> · <?= count($evs) ?> घटनाएँ</span><?php endif; ?>
    </div>
    <div class="text-xs text-gray-400" style="margin-bottom:8px">
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#22c55e;vertical-align:middle"></span> शुभ ·
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#ef4444;vertical-align:middle"></span> सावधानी ·
        <span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:#3b82f6;vertical-align:middle"></span> सूचना ·
        【दशा】= दशा-परिवर्तन · 【संयोग】= गोचर ग्रह जन्म-ग्रह पर (±3°)
    </div>
    <?php if ($evs === []): ?>
        <div class="text-gray-500 italic">समय-रेखा उपलब्ध नहीं।</div>
    <?php else: ?>
    <div style="max-height:430px;overflow-y:auto;padding-right:6px">
        <?php $lastMon = ''; foreach ($evs as $e):
            $mon = substr((string) $e['date'], 3, 7);   // MM-YYYY
            if ($mon !== $lastMon): $lastMon = $mon; ?>
                <div style="font-weight:700;color:#0f172a;border-bottom:1px solid #e5e7eb;padding:8px 0 3px;margin-bottom:4px">
                    <?= $h(($hindiMonth[substr($mon, 0, 2)] ?? '') . ' ' . substr($mon, 3)) ?>
                </div>
            <?php endif; ?>
            <div style="display:flex;align-items:flex-start;gap:8px;padding:3px 6px;border-radius:6px;<?= $rowStyle((string) $e['tone']) ?>">
                <span style="min-width:74px;font-size:.76rem;color:#64748b;padding-top:1px"><?= $h((string) $e['date']) ?></span>
                <span style="width:9px;height:9px;border-radius:50%;margin-top:5px;flex:none;<?= $dotStyle((string) $e['tone']) ?>"></span>
                <span style="font-size:.84rem;color:#334155;line-height:1.5"><?= $h((string) $e['text']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
