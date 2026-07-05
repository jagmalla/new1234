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

                <!-- Numbered, scrollable saham selector -->
                <div class="pred-picker" style="margin-top:8px">
                    <label class="pred-picker-label" for="saham-select">सहम चुनें</label>
                    <select id="saham-select" class="pred-inline-select" size="1">
                        <option value="active"<?= $defaultOpt === 'active' ? ' selected' : '' ?>>● सक्रिय सहम (मुद्दा-दशा अनुसार)<?= $related !== [] ? ' — ' . count($related) : '' ?></option>
                        <option value="all"<?= $defaultOpt === 'all' ? ' selected' : '' ?>>सभी सहम (All Saham)</option>
                        <?php foreach ($sahams as $s): ?>
                            <option value="<?= $h($s['key']) ?>"><?= (int) $s['seq'] ?>. <?= $h($s['name_hi']) ?> — <?= $h($s['verdict_hi']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Saham detail cards (only the selected one shows) -->
                <div id="saham-detail-pane" class="overflow-y-auto pr-1" style="max-height:420px">
                    <?php foreach ($sahams as $s): $isDup = ($lonCount[(string) $s['lon']] ?? 0) > 1;
                        $isActive = !empty($s['related']);
                        $showInit = $defaultOpt === 'all' || ($defaultOpt === 'active' && $isActive);
                    ?>
                    <div class="saham-card<?= $showInit ? '' : ' hidden' ?>" data-saham="<?= $h($s['key']) ?>" data-active="<?= $isActive ? '1' : '0' ?>">
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
                </div>
                <?php else: ?>
                <div class="gochar-pred-soon">
                    <div class="gps-icon">🔮</div>
                    <div class="gps-title">सहम उपलब्ध नहीं</div>
                    <div class="gps-sub"><?= $h((string) ($sah['error'] ?? 'वर्ष कुंडली गणना के बाद 50 सहम यहाँ दिखेंगे।')) ?></div>
                </div>
                <?php endif; ?>
