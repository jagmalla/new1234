<?php
/**
 * सहम pane of the Varshaphal prediction panel (inner of #vp-pred-saham).
 * Extracted from calc-v2.php so the varshaphal JSON endpoint can re-render it
 * for a newly selected year. Scope: $view['saham'], $h, $pcolor, $grahaHi.
 */
?>
                <?php
                    $sah = $view['saham'] ?? null;
                    $sahams = ($sah && !empty($sah['sahams'])) ? $sah['sahams'] : [];
                    if ($sahams !== []):
                        $vpTz = (float) ($sah['tz'] ?? 0.0);
                        $activeLord = (string) ($sah['active_lord'] ?? '');
                        $sTone = static fn(string $t): string => $t === 'pos' ? 'gc-shubh' : ($t === 'neg' ? 'gc-ashubh' : 'gc-mishrit');
                        $fmtJd = static fn($jd) => \AutoBusiness\Astro\Time\JulianDay::toDmy((float) $jd, $vpTz);
                        // duplicate-formula detection: same longitude -> "समान सूत्र"
                        $lonCount = [];
                        foreach ($sahams as $s) { $k = (string) $s['lon']; $lonCount[$k] = ($lonCount[$k] ?? 0) + 1; }
                        $related = array_values(array_filter($sahams, static fn($s) => !empty($s['related'])));
                        // Default view: "Active Saham" (all sahams tied to the running Mudda-dasha).
                        // Falls back to "All Saham" only when no saham is active this year.
                        $defaultOpt = $related !== [] ? 'active' : 'all';
                ?>
                <!-- Active Mudda mahadasha + its related sahams -->
                <div class="saham-active">
                    <div><b>सक्रिय मुद्दा-दशा:</b> <span style="color:<?= $pcolor($activeLord) ?>;font-weight:700"><?= $h($grahaHi[$activeLord] ?? $activeLord) ?></span>
                        <span class="text-xs text-gray-500">(<?= ($sah['is_day'] ?? true) ? 'दिन-वर्षप्रवेश' : 'रात्रि-वर्षप्रवेश' ?>)</span></div>
                    <?php if ($related !== []): ?>
                    <div class="saham-related">इससे संबंधित सहम:
                        <?php foreach ($related as $r): ?>
                            <button type="button" class="saham-chip <?= $sTone($r['tone']) ?>" data-goto="<?= $h($r['key']) ?>"><?= (int) $r['seq'] ?>. <?= $h($r['name_hi']) ?> <span class="saham-why">(<?= $h($r['related_why']) ?>)</span></button>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-xs text-gray-500">इस ग्रह से सीधे संबंधित कोई सहम नहीं — नीचे से कोई भी सहम चुनें।</div>
                    <?php endif; ?>
                </div>

                <?php
                    // Mudda-dasha dropdown = the year's mudda sequence, limited to
                    // lords that actually lord a saham (+ the active one), in order.
                    $sahameshSet = [];
                    foreach ($sahams as $s) { $sahameshSet[(string) $s['sahamesh']] = true; }
                    $muddaOpts = [];
                    foreach (($sah['mudda_seq'] ?? []) as $md) {
                        $ml = (string) $md['lord'];
                        if (isset($sahameshSet[$ml]) || $ml === $activeLord) { $muddaOpts[] = $ml; }
                    }
                    if ($muddaOpts === []) { $muddaOpts = array_keys($sahameshSet); }
                ?>
                <!-- सहम चुनें + मुद्दा-दशा चुनें on one line. The mudda-dasha dropdown
                     (in sequence, default = active) drives the "मुद्दा-दशा अनुसार" view;
                     pick any dasha to see the sahams active in that period. -->
                <div class="pred-picker saham-pickrow" style="margin-top:8px;gap:8px;flex-wrap:wrap">
                    <label class="pred-picker-label" for="saham-select">सहम चुनें</label>
                    <select id="saham-select" class="pred-inline-select saham-sel-main" size="1">
                        <option value="bydasha" selected>● मुद्दा-दशा अनुसार सहम</option>
                        <option value="all">सभी सहम (All Saham)</option>
                        <?php foreach ($sahams as $s): ?>
                            <option value="<?= $h($s['key']) ?>"><?= (int) $s['seq'] ?>. <?= $h($s['name_hi']) ?> — <?= $h($s['verdict_hi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="pred-picker-label" for="saham-mudda">मुद्दा-दशा</label>
                    <select id="saham-mudda" class="pred-inline-select saham-sel-mudda" size="1">
                        <?php foreach ($muddaOpts as $ml): ?>
                            <option value="<?= $h($ml) ?>"<?= $ml === $activeLord ? ' selected' : '' ?>><?= $h($grahaHi[$ml] ?? $ml) ?><?= $ml === $activeLord ? ' (सक्रिय)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Saham detail cards (only the selected set shows); no inner
                     scroll — content flows into the single outer scrollbar. -->
                <div id="saham-detail-pane" class="pr-1" style="margin-top:8px">
                    <?php foreach ($sahams as $s): $isDup = ($lonCount[(string) $s['lon']] ?? 0) > 1;
                        $isActive = !empty($s['related']);
                        // Default view = "मुद्दा-दशा अनुसार": show sahams whose सहमेश
                        // is the (active) selected mudda-dasha lord.
                        $showInit = (string) $s['sahamesh'] === $activeLord;
                    ?>
                    <div class="saham-card<?= $showInit ? '' : ' hidden' ?>" data-saham="<?= $h($s['key']) ?>" data-active="<?= $isActive ? '1' : '0' ?>" data-sahamesh="<?= $h($s['sahamesh']) ?>">
                        <div class="saham-card-head">
                            <span class="saham-name"><?= (int) $s['seq'] ?>. <?= $h($s['name_hi']) ?></span>
                            <span class="gc-chip <?= $sTone($s['tone']) ?>"><?= $h($s['verdict_hi']) ?></span>
                            <?php if (!empty($s['related'])): ?><span class="saham-tag rel">सक्रिय</span><?php endif; ?>
                            <?php if ($isDup): ?><span class="saham-tag dup">समान सूत्र</span><?php endif; ?>
                        </div>
                        <?php if (!empty($s['signifies'])): ?><div class="saham-signifies"><?= $h($s['signifies']) ?></div><?php endif; ?>
                        <?php if (!empty($s['explain'])): ?><div class="saham-explain"><b>यह सहम क्या है?</b> <?= $h($s['explain']) ?></div><?php endif; ?>
                        <div class="saham-pos">राशि-अंश: <b><?= $h($s['rashi_hi']) ?> <?= $h($s['deg']) ?></b> · वर्ष-भाव: <b><?= (int) $s['house'] ?></b> · सहमेश: <b style="color:<?= $pcolor($s['sahamesh']) ?>"><?= $h($s['sahamesh_hi']) ?></b></div>
                        <?php $f = $s['facts']; ?>
                        <div class="saham-facts">सहमेश <?= $h($s['sahamesh_hi']) ?> — षड्बल <?= $h((string) $f['shadbala']) ?> (<?= $f['pass'] ? 'पूर्ण' : 'अपूर्ण' ?>)<?= $f['debil'] ? ' · नीच' : '' ?><?= $f['combust'] >= 40 ? ' · अस्त ' . (int) $f['combust'] . '%' : '' ?></div>
                        <?php foreach (($s['phal'] ?? []) as $ph): ?>
                            <div class="saham-phal">● <?= $h($ph) ?></div>
                        <?php endforeach; ?>
                        <?php if (!empty($s['timing_mudda'])): $tm = $s['timing_mudda']; ?>
                        <div class="saham-timing">🕒 समय: सहमेश <?= $h($s['sahamesh_hi']) ?> की मुद्दा-दशा — <b><?= $h($fmtJd($tm['start_jd'])) ?></b> से <b><?= $h($fmtJd($tm['end_jd'])) ?></b></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div id="saham-empty" class="hidden text-sm text-gray-500" style="padding:10px 2px">इस मुद्दा-दशा से संबंधित कोई सहम नहीं — दूसरी मुद्दा-दशा या "सभी सहम" चुनें।</div>
                </div>
                <?php else: ?>
                <div class="gochar-pred-soon">
                    <div class="gps-icon">🔮</div>
                    <div class="gps-title">सहम उपलब्ध नहीं</div>
                    <div class="gps-sub"><?= $h((string) ($sah['error'] ?? 'वर्ष कुंडली गणना के बाद 50 सहम यहाँ दिखेंगे।')) ?></div>
                </div>
                <?php endif; ?>
