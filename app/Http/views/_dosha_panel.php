<?php
/**
 * दोष-पैनल — the "Dosha (दोष)" option of the D1 prediction dropdown. Detected
 * doshas first (red cards, each with its परिहार/remedies right below), then a
 * compact "जाँचे गए — नहीं मिले" list so the astrologer sees the whole checklist.
 * Scope: $view['doshas'] (DoshaFinder::compute), $h.
 */
$doshas = $view['doshas'] ?? [];
$found = array_values(array_filter($doshas, static fn ($d) => !empty($d['detected'])));
$clear = array_values(array_filter($doshas, static fn ($d) => empty($d['detected'])));
?>
<div class="bg-white rounded-lg shadow p-4 text-sm">
    <div class="flex flex-wrap items-end gap-x-6 gap-y-2 mb-3">
        <h2 class="font-semibold">Dosha Panel <span class="text-xs text-gray-400 font-normal">(दोष एवं परिहार)</span></h2>
        <span class="text-xs text-gray-400">कालसर्प · ग्रहण · चांडाल · अंगारक · विष · केमद्रुम · शकट · पितृ (मंगलीक/साढ़े साती/श्राप अपने-अपने पैनल में)</span>
    </div>
    <?php if ($doshas === []): ?>
        <div class="text-gray-500 italic">Chart not available.</div>
    <?php else: ?>
        <?php if ($found === []): ?>
            <div style="border:1px solid #bbf7d0;background:#f0fdf4;border-radius:10px;padding:10px 14px;color:#166534;font-weight:700">
                ✅ इस कुंडली में उपरोक्त कोई भी दोष नहीं मिला।
            </div>
        <?php else: foreach ($found as $d): ?>
            <div style="border:1px solid #fecaca;background:#fef2f2;border-radius:10px;padding:11px 13px;margin-bottom:11px">
                <div style="font-weight:700;color:#1f2937;display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    ⚠ <?= $h((string) $d['name']) ?>
                    <?php if (!empty($d['severity'])): ?><span class="gc-chip gc-ashubh"><?= $h((string) $d['severity']) ?></span><?php endif; ?>
                </div>
                <div class="text-xs" style="color:#92400e;margin:3px 0"><b>आधार:</b> <?= $h((string) $d['why']) ?></div>
                <div style="font-size:.85rem;color:#334155;line-height:1.6"><?= $h((string) $d['desc']) ?></div>
                <?php if (!empty($d['remedies'])): ?>
                <div style="margin-top:8px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 10px">
                    <div style="font-weight:700;color:#9a3412;font-size:.82rem;margin-bottom:4px">🛠 परिहार / उपाय</div>
                    <ul style="margin:0;padding-left:18px;font-size:.83rem;color:#7c2d12;line-height:1.55">
                        <?php foreach ($d['remedies'] as $r): ?><li style="margin:2px 0"><?= $h((string) $r) ?></li><?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; endif; ?>

        <?php if ($clear !== []): ?>
        <div style="margin-top:12px">
            <div class="text-xs text-gray-400 font-semibold" style="margin-bottom:5px">जाँचे गए — नहीं मिले:</div>
            <?php foreach ($clear as $d): ?>
                <div style="border:1px solid #e5e7eb;border-radius:8px;padding:6px 11px;margin-bottom:6px;font-size:.8rem;color:#475569">
                    ✓ <b><?= $h((string) $d['name']) ?></b> — <?= $h((string) $d['why']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
