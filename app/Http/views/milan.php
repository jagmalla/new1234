<?php
declare(strict_types=1);

/** @var array $view */
$boyIn = $view['boyIn'];
$girlIn = $view['girlIn'];
$milan = $view['milan'] ?? null;
$boy = $view['boy'] ?? null;
$girl = $view['girl'] ?? null;
$error = $view['error'] ?? null;

$h = static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$asset = static fn(string $p): string => \AutoBusiness\Core\Asset::url($p);

/** points chip tone: full = green, partial = amber, zero = red. */
$pchip = static function (float $pts, int $max): string {
    if ($pts >= $max) {
        return 'k-full';
    }
    if ($pts <= 0) {
        return 'k-zero';
    }
    return 'k-part';
};
$num = static fn(float $x): string => rtrim(rtrim(number_format($x, 1), '0'), '.');
?>
<!doctype html>
<html lang="hi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>कुंडली मिलान — Analysis of Karma</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Martel:wght@800&family=Mukta:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #F3EEE4; --card: #FFFFFF; --ink: #26221C; --ink-soft: #6B6156;
            --line: #E4DCCE; --accent: #b45309; --accent2: #7c3aed;
            --header-bg: #1F2A33; --sindoor: #B3341C; --sindoor-soft: #F6E3DD; --shubh: #2E6E4E;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink);
            font-family: 'Mukta', system-ui, 'Segoe UI', sans-serif; line-height: 1.5; }
        h1, h2, h3 { font-family: 'Martel', 'Mukta', serif; margin: 0; }
        a { color: var(--accent); }
        .wrap { max-width: 1400px; margin: 0 auto; padding: 14px 16px; }
        /* Dark top bar — same chrome as the main calculator. */
        .topbar { position: sticky; top: 0; z-index: 50; background: var(--header-bg); color: #F7F3EA; }
        .topbar-inner { max-width: 1400px; margin: 0 auto; padding: 10px 16px;
            display: flex; align-items: center; gap: 12px 20px; flex-wrap: wrap; }
        .topbar .brand { font-family: 'Martel', serif; font-weight: 800; font-size: 1.25rem; color: #F7F3EA; }
        .test-banner { flex: 1 1 240px; text-align: center; font-size: 1rem; font-weight: 800;
            color: #FFD84D; line-height: 1.4; min-width: 0; letter-spacing: .01em;
            text-shadow: 0 1px 2px rgba(0,0,0,.45); padding: 4px 10px; border-radius: 8px;
            background: rgba(255,216,77,.12); border: 1px solid rgba(255,216,77,.35); }
        .test-banner a { color: #FFFFFF; text-decoration: underline; font-weight: 800; }
        .topbar .meta { margin-left: auto; display: flex; align-items: center; gap: 8px 16px;
            flex-wrap: wrap; font-size: .85rem; color: #C9C2B4; }
        .topbar .meta b { color: #fff; font-weight: 600; }
        .btn-sindoor { background: var(--sindoor); color: #fff; font-weight: 600; font-size: .9rem;
            padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-sindoor:hover { filter: brightness(1.1); }
        /* Overview tiles (same format as the main site) — Milan info. */
        .ov-tiles { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 8px; margin-bottom: 16px; }
        .ov-tile { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); padding: 6px 10px; min-width: 0; }
        .ov-label { font-size: 11px; text-transform: uppercase; letter-spacing: .5px; color: var(--ink-soft);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-value { font-size: .98rem; font-weight: 700; color: var(--ink); line-height: 1.35;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ov-value.acc { color: var(--sindoor); }
        .ov-value.good { color: var(--shubh); }
        .ov-sub { font-size: .72rem; color: var(--ink-soft);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (max-width: 699px) { .ov-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        /* Place autocomplete dropdown. */
        .place-wrap { position: relative; }
        .place-results { position: absolute; left: 0; right: 0; top: 100%; margin-top: 2px; z-index: 30;
            background: #fff; border: 1px solid var(--line); border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,.12); max-height: 240px; overflow-y: auto; display: none; }
        .place-results > div { padding: 7px 10px; font-size: .9rem; cursor: pointer; }
        .place-results > div:hover { background: var(--sindoor-soft); }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,.06); padding: 16px; }
        .mt { margin-top: 16px; }
        /* --- forms --- */
        .forms { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .forms h2 { font-size: 1.1rem; margin-bottom: 10px; }
        .fld { margin-bottom: 8px; }
        .fld label { display: block; font-size: .72rem; font-weight: 700; color: var(--ink-soft); margin-bottom: 2px; }
        .fld input { width: 100%; border: 1px solid var(--line); border-radius: 6px; padding: 7px 9px;
            font: inherit; background: #fff; color: var(--ink); }
        .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .actions { display: flex; align-items: center; gap: 12px; margin-top: 12px; }
        .btn { background: var(--accent); color: #fff; border: 0; border-radius: 8px;
            padding: 10px 22px; font: inherit; font-weight: 700; cursor: pointer; }
        .btn:hover { filter: brightness(1.06); }
        .btn-adv { background: #fff; color: var(--accent); border: 1px solid var(--accent);
            border-radius: 8px; padding: 9px 16px; font: inherit; font-weight: 700; cursor: pointer; }
        .btn-adv:hover { background: #faf5ef; }
        .adv-fields[hidden] { display: none; }
        /* --- result header --- */
        .res-head { display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; }
        .mini { text-align: center; }
        .mini .nm { font-weight: 700; font-size: 1.05rem; }
        .mini .dt { color: var(--ink-soft); font-size: .9rem; }
        .gauge { text-align: center; padding: 10px 22px; border-radius: 14px; min-width: 150px; }
        .gauge .big { font-size: 2.4rem; font-weight: 800; font-family: 'Martel', serif; line-height: 1; }
        .gauge .lbl { font-size: .8rem; font-weight: 700; }
        .g-shubh { background: #DCFCE7; color: #166534; }
        .g-mishrit { background: #FEF3C7; color: #92400e; }
        .g-ashubh { background: #FEE2E2; color: #991b1b; }
        /* --- override warnings --- */
        .warn { background: #FEF2F2; border: 1px solid #FECACA; color: #991b1b;
            border-radius: 8px; padding: 10px 12px; margin-top: 10px; font-weight: 600; }
        /* --- koota cards --- */
        .kgrid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .kcard { border: 1px solid var(--line); border-radius: 10px; padding: 12px 14px; background: #fff; }
        .khead { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 4px; }
        .ktitle { font-weight: 700; font-size: 1.02rem; }
        .kpts { font-weight: 800; font-size: .9rem; border-radius: 999px; padding: 3px 12px; white-space: nowrap; }
        .k-full { background: #DCFCE7; color: #166534; }
        .k-part { background: #FEF3C7; color: #92400e; }
        .k-zero { background: #FEE2E2; color: #991b1b; }
        .kreason { color: var(--ink-soft); font-size: .88rem; margin-bottom: 3px; }
        .kphal { font-size: 1rem; }
        /* --- dosha / mangal / summary --- */
        .sec-title { font-size: 1.15rem; margin-bottom: 8px; color: var(--accent); }
        .dosha { border-left: 4px solid #ef4444; padding: 8px 12px; margin-bottom: 10px; background: #fff7f7; border-radius: 0 8px 8px 0; }
        .dosha.ok { border-left-color: #22c55e; background: #f2fdf5; }
        .dosha .dt { font-weight: 700; }
        .checks { list-style: none; padding: 0; margin: 6px 0 0; font-size: .9rem; }
        .checks li::before { content: '✗ '; color: #dc2626; font-weight: 700; }
        .checks li.ok::before { content: '✓ '; color: #16a34a; }
        .mangal-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 10px; }
        .mtag { font-weight: 700; border-radius: 999px; padding: 2px 10px; font-size: .8rem; }
        .m-yes { background: #FEE2E2; color: #991b1b; } .m-no { background: #DCFCE7; color: #166534; }
        /* --- charts + planet tables --- */
        .pair { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .chartbox { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .chartbox .cell { text-align: center; }
        .chartbox .cap { font-size: .8rem; font-weight: 700; color: var(--ink-soft); margin-bottom: 4px; }
        table.pl { width: 100%; border-collapse: collapse; font-size: .82rem; }
        table.pl th, table.pl td { border-bottom: 1px solid var(--line); padding: 4px 6px; text-align: left; }
        table.pl th { color: var(--ink-soft); font-weight: 700; }
        .banner { color: var(--ink-soft); font-size: .85rem; }
        /* Left menu (mirrors the main calculator) so Milan sits beside it. */
        .layout { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 16px; align-items: start; }
        .side { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); padding: 6px 0; position: sticky; top: 16px; overflow: hidden; }
        .side a { display: block; padding: 10px 14px; border-left: 3px solid transparent;
            color: var(--ink); font-weight: 500; font-size: .95rem; text-decoration: none; }
        .side a:hover { background: #f6efe3; }
        .side a.active { background: #f6efe3; border-left-color: var(--accent); color: var(--accent); font-weight: 700; }
        .content { min-width: 0; }
        @media (max-width: 900px) {
            .layout { grid-template-columns: 1fr; }
            .side { position: static; display: flex; flex-wrap: wrap; }
            .side a { border-left: 0; }
        }
        @media (max-width: 820px) {
            .forms, .res-head, .kgrid, .pair, .chartbox, .mangal-grid { grid-template-columns: 1fr; }
            .res-head { text-align: center; }
        }
        @media print {
            body { background: #fff; } .noprint { display: none !important; }
            .card { box-shadow: none; break-inside: avoid; }
        }
    </style>
</head>
<body>
<?php
    // Koota lookup + small helpers for the top tiles.
    $koBy = [];
    if ($milan !== null) { foreach ($milan['kootas'] as $k) { $koBy[$k['koota']] = $k; } }
    $mangalShort = ['o_mangal_both' => 'दोनों मांगलिक', 'o_mangal_mismatch' => 'असंतुलन', 'o_mangal_none' => 'निर्दोष'];
?>
<!-- ============ DARK TOP BAR (same chrome as the main calculator) ============ -->
<header class="topbar">
    <div class="topbar-inner">
        <h1 class="brand">Analysis of Karma</h1>
        <div class="test-banner">System is Under Testing — Not Finalized Yet.<br>Feedback: <a href="mailto:analysisofkarma@gmail.com">analysisofkarma@gmail.com</a></div>
        <div class="meta">
            <span><b>कुंडली मिलान</b></span>
            <?php if ($milan !== null): ?>
                <span>वर <b><?= $h($milan['boy']['rashi_hi']) ?></b> · कन्या <b><?= $h($milan['girl']['rashi_hi']) ?></b></span>
                <span>गुण <b><?= $h($num((float) $milan['total'])) ?>/36</b></span>
            <?php endif; ?>
        </div>
        <a class="btn-sindoor" href="<?= $h($asset('/calc')) ?>">New Kundli</a>
    </div>
</header>

<main class="wrap">

    <?php if ($milan !== null): ?>
    <!-- ============ OVERVIEW TILES — Kundali Milan info ============ -->
    <div class="ov-tiles">
        <div class="ov-tile">
            <div class="ov-label">वर / Boy</div>
            <div class="ov-value"><?= $h($milan['boy']['name'] !== '' ? $milan['boy']['name'] : '—') ?></div>
            <div class="ov-sub"><?= $h($boyIn['date']) ?> · <?= $h($boyIn['time']) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">वर — चन्द्र राशि</div>
            <div class="ov-value"><?= $h($milan['boy']['rashi_hi']) ?></div>
            <div class="ov-sub"><?= $h($milan['boy']['nak_hi']) ?> पाद <?= (int) $milan['boy']['pada'] ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">कन्या / Girl</div>
            <div class="ov-value"><?= $h($milan['girl']['name'] !== '' ? $milan['girl']['name'] : '—') ?></div>
            <div class="ov-sub"><?= $h($girlIn['date']) ?> · <?= $h($girlIn['time']) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">कन्या — चन्द्र राशि</div>
            <div class="ov-value"><?= $h($milan['girl']['rashi_hi']) ?></div>
            <div class="ov-sub"><?= $h($milan['girl']['nak_hi']) ?> पाद <?= (int) $milan['girl']['pada'] ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">कुल गुण मिलान</div>
            <div class="ov-value <?= ($milan['band']['tier'] ?? '') === 'shubh' ? 'good' : (($milan['band']['tier'] ?? '') === 'ashubh' ? 'acc' : '') ?>"><?= $h($num((float) $milan['total'])) ?> / 36</div>
            <div class="ov-sub"><?= $h(mb_substr((string) $milan['band']['text'], 0, 14)) ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">मंगल दोष</div>
            <div class="ov-value <?= ($milan['mangal']['result']['key'] ?? '') === 'o_mangal_none' ? 'good' : 'acc' ?>"><?= $h($mangalShort[$milan['mangal']['result']['key']] ?? '—') ?></div>
            <div class="ov-sub">वर: <?= $milan['mangal']['boy']['manglik'] ? 'हाँ' : 'नहीं' ?> · कन्या: <?= $milan['mangal']['girl']['manglik'] ? 'हाँ' : 'नहीं' ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">नाड़ी कूट</div>
            <div class="ov-value <?= (($koBy['nadi']['points'] ?? 0) > 0) ? 'good' : 'acc' ?>"><?= $h($num((float) ($koBy['nadi']['points'] ?? 0))) ?> / 8</div>
            <div class="ov-sub"><?= ($koBy['nadi']['points'] ?? 0) > 0 ? 'भिन्न — शुभ' : 'नाड़ी दोष' ?></div>
        </div>
        <div class="ov-tile">
            <div class="ov-label">भकूट कूट</div>
            <div class="ov-value <?= (($koBy['bhakoot']['points'] ?? 0) > 0) ? 'good' : 'acc' ?>"><?= $h($num((float) ($koBy['bhakoot']['points'] ?? 0))) ?> / 7</div>
            <div class="ov-sub"><?= ($koBy['bhakoot']['points'] ?? 0) > 0 ? 'शुभ' : 'भकूट दोष' ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="layout">
        <!-- Left menu — mirrors the main calculator so Milan opens beside it. -->
        <nav class="side noprint" aria-label="Sections">
            <?php $calc = $h($asset('/calc')); ?>
            <a href="<?= $calc ?>">New / Profile</a>
            <a href="<?= $calc ?>">Birth Chart</a>
            <a href="<?= $calc ?>">Planet Positions</a>
            <a href="<?= $calc ?>">Varga Charts</a>
            <a href="<?= $calc ?>">Dasha</a>
            <a href="<?= $calc ?>">Bala (Strength)</a>
            <a href="<?= $calc ?>">Gochar (Transit)</a>
            <a href="<?= $calc ?>">Varshaphal</a>
            <a href="<?= $h($asset('/milan')) ?>" class="active">Kundali Milan</a>
        </nav>

        <div class="content">

    <?php if ($error !== null): ?>
        <div class="warn"><?= $h($error) ?></div>
    <?php endif; ?>

    <!-- ============ INPUT FORMS ============ -->
    <form class="card noprint" method="get" action="">
        <input type="hidden" name="r" value="milan">
        <div class="forms">
            <?php
            $formCol = static function (string $p, array $in, callable $h): void {
                $title = $p === 'boy' ? 'वर (Boy)' : 'कन्या (Girl)';
                ?>
                <div>
                    <h2><?= $h($title) ?></h2>
                    <div class="fld"><label>नाम / Name</label><input name="<?= $p ?>_name" value="<?= $h($in['name']) ?>"></div>
                    <div class="row2">
                        <div class="fld"><label>जन्म तिथि (DD-MM-YYYY)</label><input name="<?= $p ?>_date" class="fmt-date" value="<?= $h($in['date']) ?>" placeholder="DD-MM-YYYY"></div>
                        <div class="fld"><label>समय (HH:MM)</label><input name="<?= $p ?>_time" class="fmt-time" value="<?= $h($in['time']) ?>" placeholder="HH:MM"></div>
                    </div>
                    <div class="fld place-wrap"><label>जन्म स्थान / Place</label>
                        <input id="<?= $p ?>-place" name="<?= $p ?>_place" value="<?= $h($in['place']) ?>" placeholder="शहर खोजें / Search city…" autocomplete="off">
                        <div id="<?= $p ?>-place-results" class="place-results"></div>
                    </div>
                    <div class="adv-fields" hidden>
                        <div class="row2">
                            <div class="fld"><label>अक्षांश / Latitude</label><input id="<?= $p ?>-lat" name="<?= $p ?>_lat" value="<?= $h($in['lat']) ?>"></div>
                            <div class="fld"><label>देशांतर / Longitude</label><input id="<?= $p ?>-lon" name="<?= $p ?>_lon" value="<?= $h($in['lon']) ?>"></div>
                        </div>
                        <div class="fld" style="max-width:140px"><label>समय क्षेत्र / TZ</label><input id="<?= $p ?>-tz" name="<?= $p ?>_tz" value="<?= $h($in['tz']) ?>"></div>
                    </div>
                </div>
                <?php
            };
            $formCol('boy', $boyIn, $h);
            $formCol('girl', $girlIn, $h);
            ?>
        </div>
        <div class="actions">
            <button class="btn" type="submit">मिलान करें</button>
            <button class="btn-adv" type="button" id="milan-adv-toggle" aria-expanded="false">⚙ Advanced options (Lat/Lon · TZ)</button>
            <span class="banner">अयनांश: Lahiri</span>
        </div>
    </form>
    <script>
    (function () {
        var t = document.getElementById('milan-adv-toggle');
        if (!t) { return; }
        t.addEventListener('click', function () {
            var advs = document.querySelectorAll('.adv-fields');
            var willShow = advs.length && advs[0].hidden;
            advs.forEach(function (a) { a.hidden = !willShow; });
            t.setAttribute('aria-expanded', willShow ? 'true' : 'false');
        });
    })();
    </script>

    <?php if ($milan !== null): ?>
    <?php
        $ov = $milan['overrides'] ?? [];
        $band = $milan['band'];
        $gcls = 'g-' . ($band['tier'] ?? 'mishrit');
    ?>

    <!-- ============ RESULT HEADER ============ -->
    <div class="card mt">
        <div class="res-head">
            <div class="mini">
                <div class="nm">वर — <?= $h($milan['boy']['name']) ?></div>
                <div class="dt">चन्द्र-राशि <b><?= $h($milan['boy']['rashi_hi']) ?></b> · <?= $h($milan['boy']['nak_hi']) ?> पाद <?= (int) $milan['boy']['pada'] ?></div>
            </div>
            <div class="gauge <?= $gcls ?>">
                <div class="big"><?= $h($num((float) $milan['total'])) ?> <span style="font-size:1.1rem">/ <?= (int) $milan['max'] ?></span></div>
                <div class="lbl">गुण मिलान</div>
            </div>
            <div class="mini">
                <div class="nm">कन्या — <?= $h($milan['girl']['name']) ?></div>
                <div class="dt">चन्द्र-राशि <b><?= $h($milan['girl']['rashi_hi']) ?></b> · <?= $h($milan['girl']['nak_hi']) ?> पाद <?= (int) $milan['girl']['pada'] ?></div>
            </div>
        </div>
        <?php foreach ($ov as $w): ?>
            <div class="warn">⚠ <?= $h($w) ?></div>
        <?php endforeach; ?>
    </div>

    <!-- ============ EIGHT KOOTA CARDS ============ -->
    <div class="card mt">
        <h2 class="sec-title">अष्टकूट — आठ कूट</h2>
        <div class="kgrid">
            <?php foreach ($milan['kootas'] as $k): ?>
                <div class="kcard">
                    <div class="khead">
                        <span class="ktitle"><?= $h($k['label']) ?> <span style="color:var(--ink-soft);font-weight:400;font-size:.8rem">/ <?= (int) $k['max'] ?></span></span>
                        <span class="kpts <?= $pchip((float) $k['points'], (int) $k['max']) ?>"><?= $h($num((float) $k['points'])) ?> / <?= (int) $k['max'] ?></span>
                    </div>
                    <div class="kreason"><?= $h($k['reason']) ?></div>
                    <div class="kphal"><?= $h($k['phal']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ============ DOSHA & PARIHARA ============ -->
    <?php if (!empty($milan['doshas'])): ?>
    <div class="card mt">
        <h2 class="sec-title">दोष एवं परिहार</h2>
        <?php foreach ($milan['doshas'] as $d): ?>
            <div class="dosha <?= $d['resolved'] ? 'ok' : '' ?>">
                <div class="dt"><?= $h($d['dosha']) ?> दोष — <?= $d['resolved'] ? 'परिहार मिला — दोष शमित' : 'परिहार नहीं मिला' ?></div>
                <div style="font-size:.92rem;margin-top:2px"><?= $h($d['text']) ?></div>
                <ul class="checks">
                    <?php foreach ($d['checks'] as $c): ?>
                        <li class="<?= $c['ok'] ? 'ok' : '' ?>"><?= $h($c['label']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- ============ MANGAL DOSHA ============ -->
    <?php $mg = $milan['mangal']; ?>
    <div class="card mt">
        <h2 class="sec-title">मंगल दोष जाँच</h2>
        <div class="mangal-grid">
            <?php
            $mgCol = static function (string $who, array $m, callable $h): void {
                ?>
                <div>
                    <b><?= $h($who) ?></b>
                    <span class="mtag <?= $m['manglik'] ? 'm-yes' : 'm-no' ?>"><?= $m['manglik'] ? 'मांगलिक' : 'गैर-मांगलिक' ?></span>
                    <div style="font-size:.88rem;color:var(--ink-soft);margin-top:4px">
                        <?php if (!empty($m['hits'])): ?>
                            मंगल: <?= $h(implode(', ', $m['hits'])) ?>
                        <?php else: ?>
                            मंगल किसी दोष-भाव (1,4,7,8,12) में नहीं।
                        <?php endif; ?>
                        <?php if ($m['raw'] && !$m['manglik']): ?>
                            <br><span style="color:#166534">दोष-भंग: <?= $m['cancel']['own_or_exalt'] ? 'मंगल स्वराशि/उच्च' : '' ?><?= $m['cancel']['jupiter_or_lagna'] ? ' गुरु-दृष्टि/लग्न में गुरु-शुक्र' : '' ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            };
            $mgCol('वर', $mg['boy'], $h);
            $mgCol('कन्या', $mg['girl'], $h);
            ?>
        </div>
        <div style="font-weight:600"><?= $h($mg['result']['text']) ?></div>
    </div>

    <!-- ============ FINAL SUMMARY ============ -->
    <div class="card mt">
        <h2 class="sec-title">अंतिम सारांश</h2>
        <?php foreach ($ov as $w): ?>
            <div class="warn">⚠ <?= $h($w) ?></div>
        <?php endforeach; ?>
        <div style="margin-top:8px;font-size:1.05rem">
            <b><?= $h($num((float) $milan['total'])) ?> / <?= (int) $milan['max'] ?></b> — <?= $h($band['text']) ?>
        </div>
    </div>

    <!-- ============ CHARTS + PLANET DETAILS ============ -->
    <div class="card mt">
        <h2 class="sec-title">कुंडली एवं ग्रह विवरण</h2>
        <div class="pair">
            <?php
            $personBlock = static function (string $who, ?array $data, string $key, callable $h): void {
                if ($data === null) { return; }
                ?>
                <div>
                    <h3 style="font-size:1.05rem;margin-bottom:8px"><?= $h($who) ?></h3>
                    <div class="chartbox">
                        <div class="cell"><div class="cap">D1 (लग्न कुंडली)</div><div id="chart-<?= $key ?>-d1"></div></div>
                        <div class="cell"><div class="cap">D9 (नवांश)</div><div id="chart-<?= $key ?>-d9"></div></div>
                    </div>
                    <table class="pl" style="margin-top:10px">
                        <thead><tr><th>ग्रह</th><th>राशि</th><th>अंश</th><th>भाव</th><th>नक्षत्र-पाद</th></tr></thead>
                        <tbody>
                        <?php foreach ($data['planets'] as $pl): ?>
                            <tr>
                                <td><?= $h($pl['name_hi']) ?><?= $pl['retro'] ? ' (व)' : '' ?></td>
                                <td><?= $h($pl['sign']) ?></td>
                                <td><?= (int) $pl['deg'] ?>°</td>
                                <td><?= (int) $pl['house'] ?></td>
                                <td><?= $h($pl['nak']) ?> <?= (int) $pl['pada'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
            };
            $personBlock('वर — ' . ($milan['boy']['name'] ?? ''), $boy, 'boy', $h);
            $personBlock('कन्या — ' . ($milan['girl']['name'] ?? ''), $girl, 'girl', $h);
            ?>
        </div>
    </div>

    <?php endif; ?>
        </div><!-- /.content -->
    </div><!-- /.layout -->
</main>

<script src="<?= $h($asset('/assets/js/northchart.js')) ?>"></script>
<?php if ($milan !== null && $boy !== null && $girl !== null): ?>
<script>
  window.AB_MILAN = {
    boy: { d1: <?= json_encode($boy['d1'], JSON_UNESCAPED_UNICODE) ?>, d9: <?= json_encode($boy['d9'], JSON_UNESCAPED_UNICODE) ?> },
    girl: { d1: <?= json_encode($girl['d1'], JSON_UNESCAPED_UNICODE) ?>, d9: <?= json_encode($girl['d9'], JSON_UNESCAPED_UNICODE) ?> }
  };
  (function () {
    if (!window.ABChart) { return; }
    function draw(id, payload) {
      var el = document.getElementById(id);
      if (el && payload) { ABChart.renderNorth(el, payload, { showDeg: true }); }
    }
    draw('chart-boy-d1', AB_MILAN.boy.d1);
    draw('chart-boy-d9', AB_MILAN.boy.d9);
    draw('chart-girl-d1', AB_MILAN.girl.d1);
    draw('chart-girl-d9', AB_MILAN.girl.d9);
  })();
</script>
<?php endif; ?>

<!-- City search (place -> lat/lon/tz) + date/time auto-format, same as the main form. -->
<script src="<?= $h($asset('/assets/js/citysearch.js')) ?>"></script>
<script>
(function () {
  // Place -> lat/lon/tz for each person (worldwide, Open-Meteo), matching /calc.
  if (window.ABCitySearch) {
    ['boy', 'girl'].forEach(function (p) {
      if (!document.getElementById(p + '-place')) { return; }
      ABCitySearch.init({
        input: '#' + p + '-place', results: '#' + p + '-place-results',
        lat: '#' + p + '-lat', lon: '#' + p + '-lon', tz: '#' + p + '-tz',
        getDate: function () {
          var d = (document.querySelector('[name="' + p + '_date"]') || {}).value;
          var t = (document.querySelector('[name="' + p + '_time"]') || {}).value || '12:00';
          var dt = d ? new Date(d + 'T' + (t.length === 5 ? t : '12:00') + ':00') : new Date();
          return isNaN(dt) ? new Date() : dt;
        }
      });
    });
  }

  // Auto-correct date/time to canonical form on blur (e.g. "1 12 1980" -> "01-12-1980").
  var pad2 = function (n) { return (n < 10 ? '0' : '') + n; };
  var normDate = function (raw) {
    var q = String(raw).trim().split(/[-\/.\s]+/).filter(Boolean);
    if (q.length !== 3 || q.some(function (x) { return !/^\d+$/.test(x); })) { return raw; }
    var a = +q[0], b = +q[1], c = +q[2], d, m, y;
    if (a > 31) { y = a; m = b; d = c; } else { d = a; m = b; y = c; }
    if (d < 1 || d > 31 || m < 1 || m > 12) { return raw; }
    return pad2(d) + '-' + pad2(m) + '-' + y;
  };
  var normTime = function (raw) {
    var q = String(raw).trim().split(/[:\s.]+/).filter(Boolean);
    if (!q.length || q.some(function (x) { return !/^\d+$/.test(x); })) { return raw; }
    var hh = +q[0], mi = +(q[1] || 0);
    if (hh > 23 || mi > 59) { return raw; }
    return pad2(hh) + ':' + pad2(mi);
  };
  document.querySelectorAll('.fmt-date').forEach(function (el) {
    el.addEventListener('blur', function () { if (el.value.trim()) { el.value = normDate(el.value); } });
  });
  document.querySelectorAll('.fmt-time').forEach(function (el) {
    el.addEventListener('blur', function () { if (el.value.trim()) { el.value = normTime(el.value); } });
  });
})();
</script>
</body>
</html>
