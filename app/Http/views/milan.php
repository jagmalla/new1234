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
        /* === Global readability: larger, device-responsive base font size ===
           Root font-size scales every rem/em-based text up fluidly on all
           devices — phones, tablets, desktops. */
        html { font-size: clamp(17px, 15.8px + 0.5vw, 20.5px);
               -webkit-text-size-adjust: 100%; text-size-adjust: 100%; }
        /* === Device compatibility: keep content within the screen (no
           horizontal page-scroll) on phones, tablets and desktops. === */
        img, video { max-width: 100%; height: auto; }
        svg { max-width: 100%; }
        pre { white-space: pre-wrap; overflow-wrap: anywhere; max-width: 100%; }
        table { display: block; max-width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        :root {
            --bg: #FBF7F0; --paper: #FBF7F0; --card: #FFFFFF; --ink: #26221C; --ink-soft: #6B6156;
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
        /* "Under testing" banner — compact two-line version (see calc-v2). */
        .test-banner { flex: 0 1 auto; text-align: center; font-size: .72rem; font-weight: 800;
            color: #FFD84D; line-height: 1.3; min-width: 0; max-width: 360px; white-space: nowrap;
            letter-spacing: .01em; text-shadow: 0 1px 2px rgba(0,0,0,.45); padding: 3px 12px;
            border-radius: 8px; background: rgba(255,216,77,.12); border: 1px solid rgba(255,216,77,.35); }
        .test-banner a { color: #FFFFFF; text-decoration: underline; font-weight: 800; }
        .topbar .meta { margin-left: auto; display: flex; align-items: center; gap: 8px 16px;
            flex-wrap: nowrap; font-size: .85rem; color: #C9C2B4; min-width: 0; }
        .topbar .meta > span { white-space: nowrap; flex: 0 0 auto; }
        .topbar .meta > span:first-child { flex: 0 1 auto; min-width: 0; max-width: 24ch;
            overflow: hidden; text-overflow: ellipsis; }
        .topbar .meta b { color: #fff; font-weight: 600; }
        .btn-sindoor { background: var(--sindoor); color: #fff; font-weight: 600; font-size: .9rem;
            padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-sindoor:hover { filter: brightness(1.1); }
        /* ☰ Menu button + drawer overlay + language select — same chrome as /calc. */
        #menu-btn { display: none; align-items: center; gap: 8px; background: #2A3742;
            color: #F7F3EA; border: 1px solid #3B4854; border-radius: 8px; font-weight: 700;
            font-size: .9rem; padding: 8px 14px; min-height: 44px; cursor: pointer; }
        #menu-btn:hover { background: #34424F; }
        #menu-btn .menu-btn-bars { position: relative; width: 18px; height: 2px; background: currentColor;
            border-radius: 2px; box-shadow: 0 -6px 0 currentColor, 0 6px 0 currentColor; }
        #menu-overlay { position: fixed; inset: 0; background: rgba(20,16,10,.5); z-index: 55; }
        .topbar select { background: #2A3742; color: #F7F3EA; border: 1px solid #3B4854;
            padding: 6px 10px; min-height: 44px; font-size: .85rem; border-radius: 6px; }
        .btn-lbl-short { display: none; }
        /* Tablet + phone (≤1099px): compact one-row top bar; the side nav becomes
           an off-canvas drawer opened by the ☰ Menu button (matches /calc). */
        @media (max-width: 1099px) {
            #menu-btn { display: inline-flex; }
            .topbar .brand, .test-banner { display: none; }
            .topbar .meta > span { display: none; }
            .topbar-inner { flex-wrap: nowrap; gap: 10px; padding: 8px 14px; }
            .topbar .meta { margin-left: auto; gap: 10px; flex-wrap: nowrap; align-items: center; }
            .layout { grid-template-columns: 1fr; }
            #milan-side {
                position: fixed; top: 0; left: 0; z-index: 60;
                width: min(84vw, 300px); height: 100dvh; overflow-y: auto;
                margin: 0; border-radius: 0; padding: 8px 0;
                box-shadow: 2px 0 18px rgba(0,0,0,.28);
                transform: translateX(-100%); transition: transform .22s ease; display: block;
            }
            body.menu-open { overflow: hidden; }
            body.menu-open #milan-side { transform: translateX(0); }
        }
        @media (min-width: 1100px) { #menu-overlay { display: none !important; } }
        @media (prefers-reduced-motion: reduce) { #milan-side { transition: none; } }
        @media (max-width: 640px) {
            .topbar-inner { gap: 8px; padding: 8px 10px; }
            .topbar .meta { gap: 6px; }
            .topbar select { min-height: 40px; font-size: .8rem; }
            .btn-sindoor, #menu-btn { min-height: 40px; padding: 7px 11px; font-size: .82rem; }
            .btn-lbl-full { display: none; } .btn-lbl-short { display: inline; }
        }
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
        /* Left menu — identical look to the main calculator (#side-menu) so the
           Milan page feels like the same website. */
        .layout { display: grid; grid-template-columns: 202px minmax(0, 1fr); gap: 16px; align-items: start; }
        #milan-side { background: var(--card); border: 1px solid var(--line); border-radius: 10px;
            box-shadow: 0 1px 3px rgba(38,34,28,.08); padding: 6px 0; position: sticky; top: 16px; overflow: hidden; }
        /* Menu rows: keep every label on ONE line (matches the calc page). */
        .l2-mi-link { display: block; width: 100%; text-align: left; padding: 9px 10px;
            border-left: 3px solid transparent; color: var(--ink); font-weight: 500; font-size: .82rem;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; }
        .l2-mi-link:hover { background: var(--sindoor-soft); color: var(--sindoor); }
        .l2-mi-link.active { background: var(--sindoor-soft); border-left-color: var(--sindoor);
            color: var(--sindoor); font-weight: 700; }
        .l2-ic { display: inline-block; width: 1.25em; margin-right: 5px; text-align: center; font-style: normal; }
        .content { min-width: 0; }
        /* Re-assert the mobile drawer layout AFTER the base .layout / #milan-side
           rules above. Media queries add no specificity, so the earlier ≤1099
           overrides were being beaten by these later base rules — which kept the
           190px sidebar in-flow on phones and squeezed the content column until
           tables overflowed. Repeating them here (later source order) makes the
           single-column drawer layout win on tablets & phones. */
        @media (max-width: 1099px) {
            .layout { grid-template-columns: 1fr; }
            #milan-side {
                position: fixed; top: 0; left: 0; z-index: 60;
                width: min(84vw, 300px); height: 100dvh; overflow-y: auto;
                margin: 0; border-radius: 0; padding: 8px 0;
                box-shadow: 2px 0 18px rgba(0,0,0,.28);
                transform: translateX(-100%); transition: transform .22s ease; display: block;
            }
            body.menu-open #milan-side { transform: translateX(0); }
        }
        @media (prefers-reduced-motion: reduce) { #milan-side { transition: none; } }
        @media (max-width: 820px) {
            .forms, .res-head, .kgrid, .pair, .chartbox, .mangal-grid { grid-template-columns: 1fr; }
            .res-head { text-align: center; }
        }
        @media print {
            body { background: #fff; } .noprint { display: none !important; }
            .card { box-shadow: none; break-inside: avoid; }
        }
        /* --- per-side Save / Open (Kundali Milan) --- */
        .fcol-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 10px; flex-wrap: wrap; }
        .fcol-head h2 { margin-bottom: 0; }
        .fcol-acts { display: flex; gap: 6px; }
        .mlk-btn { background: var(--accent); color: #fff; border: 0; border-radius: 7px; padding: 6px 12px;
            font: inherit; font-weight: 700; font-size: .82rem; cursor: pointer; line-height: 1.2; }
        .mlk-btn:hover { filter: brightness(1.07); }
        .mlk-btn-ghost { background: #fff; color: var(--accent); border: 1px solid var(--accent); }
        .mlk-btn-ghost:hover { background: #faf5ef; }
        .mlk-btn-sm { padding: 5px 10px; font-size: .78rem; }
        /* modal overlays */
        .mlk-modal-overlay { position: fixed; inset: 0; z-index: 1200; background: rgba(20,16,10,.5);
            display: flex; align-items: center; justify-content: center; padding: 16px; }
        .mlk-modal-overlay.hidden { display: none; }
        .mlk-modal { background: #fff; border-radius: 12px; width: min(560px, 96vw); max-height: 88vh; overflow-y: auto;
            box-shadow: 0 12px 40px rgba(0,0,0,.28); }
        .mlk-modal-head { display: flex; align-items: center; justify-content: space-between; gap: 8px;
            padding: 12px 16px; border-bottom: 1px solid var(--line); font-weight: 700; font-size: 1.02rem; }
        .mlk-modal-x { background: none; border: 0; font-size: 1.1rem; cursor: pointer; color: var(--ink-soft); }
        .mlk-modal-body { padding: 14px 16px; }
        .mlk-modal-foot { padding: 12px 16px; border-top: 1px solid var(--line); display: flex; gap: 10px; }
        .mlk-open-count { color: var(--ink-soft); font-weight: 400; font-size: .82rem; }
        .mlk-open-search { padding: 10px 16px 0; }
        .mlk-open-search input { width: 100%; border: 1px solid var(--line); border-radius: 8px; padding: 8px 10px; font: inherit; }
        .mlk-open-list { padding: 8px 16px 14px; max-height: 56vh; overflow-y: auto; }
        .mlk-open-empty { color: var(--ink-soft); text-align: center; padding: 22px 8px; }
        .mlk-open-row { display: grid; grid-template-columns: 1fr auto; gap: 4px 10px; align-items: center;
            border: 1px solid var(--line); border-radius: 9px; padding: 9px 11px; margin-bottom: 8px; }
        .mlk-open-name { font-weight: 700; }
        .mlk-open-meta { font-size: .82rem; color: var(--ink-soft); }
        .mlk-open-acts { display: flex; gap: 6px; }
        .mlk-open-when { grid-column: 1 / -1; font-size: .72rem; color: #94a3b8; }
        .mlk-save-sum { background: #f8fafc; border: 1px solid var(--line); border-radius: 8px; padding: 9px 11px; margin-bottom: 12px; font-size: .9rem; }
        .mlk-save-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .mlk-save-grid label { display: block; font-size: .8rem; font-weight: 700; color: var(--ink-soft); }
        .mlk-save-grid input { width: 100%; margin-top: 3px; border: 1px solid var(--line); border-radius: 7px; padding: 7px 9px; font: inherit; }
        .mlk-req { color: #dc2626; }
        .mlk-save-err { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; border-radius: 7px; padding: 8px 10px; margin-top: 10px; font-size: .88rem; }
        .mlk-save-err.hidden { display: none; }
        .mlk-note { font-size: .78rem; color: var(--ink-soft); margin: 8px 0 0; }
        .mlk-toast { position: fixed; left: 50%; bottom: 26px; transform: translateX(-50%); z-index: 1300;
            padding: 10px 16px; border-radius: 9px; font-weight: 600; box-shadow: 0 6px 20px rgba(0,0,0,.18); }
        .mlk-toast.hidden { display: none; }
        .mlk-toast-ok { background: #ecfdf5; color: #15803d; border: 1px solid #a7f3d0; }
        .mlk-toast-err { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        @media (max-width: 520px) { .mlk-save-grid { grid-template-columns: 1fr; } }
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
        <button type="button" id="menu-btn" aria-label="मेन्यू / Menu" aria-expanded="false" aria-controls="milan-side">
            <span class="menu-btn-bars" aria-hidden="true"></span>Menu
        </button>
        <h1 class="brand">Analysis of Karma</h1>
        <div class="test-banner">System is Under Testing — Not Finalized Yet.<br>Feedback: <a href="mailto:analysisofkarma@gmail.com">analysisofkarma@gmail.com</a></div>
        <div class="meta">
            <span><b>कुंडली मिलान</b></span>
            <?php if ($milan !== null): ?>
                <span>वर <b><?= $h($milan['boy']['rashi_hi']) ?></b> · कन्या <b><?= $h($milan['girl']['rashi_hi']) ?></b></span>
                <span>गुण <b><?= $h($num((float) $milan['total'])) ?>/36</b></span>
            <?php endif; ?>
            <select id="topbar-lang" aria-label="भाषा / Language">
                <option value="hi">हिन्दी</option>
                <option value="en">English</option>
            </select>
            <a class="btn-sindoor" href="<?= $h($asset('/calc')) ?>"><span class="btn-lbl-full">New Kundli</span><span class="btn-lbl-short">New</span></a>
        </div>
    </div>
</header>
<div id="menu-overlay" hidden></div>

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
        <!-- Left menu — identical to the main calculator's #side-menu (same items,
             emojis and sindoor colours) so the client feels on the same website.
             Every item links back into /calc; Kundali Milan is the active page. -->
        <nav id="milan-side" class="l2-menu noprint" aria-label="Sections">
            <?php $calc = $h($asset('/calc')); ?>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">👤</span>New / Profile</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">📆</span>Today · आज</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🧩</span>Custom Screen</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🌟</span>Birth Chart</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🪐</span>Planet Positions</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🗂️</span>Varga Charts</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">⏳</span>Dasha</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">💪</span>Bala (Strength)</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🔭</span>Gochar (Transit)</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🕉️</span>Mahurat · मुहूर्त</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">🗓️</span>Varshaphal</a></div>
            <div class="l2-mi"><a class="l2-mi-link" href="<?= $calc ?>"><span class="l2-ic">📕</span>Laal Kitab</a></div>
            <div class="l2-mi"><a class="l2-mi-link active" href="<?= $h($asset('/milan')) ?>"><span class="l2-ic">💑</span>Kundali Milan</a></div>
        </nav>

        <div class="content" id="milan-content">

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
                    <div class="fcol-head">
                        <h2><?= $h($title) ?></h2>
                        <div class="fcol-acts">
                            <button type="button" class="mlk-btn mlk-btn-ghost" data-mlk-open="<?= $p ?>" title="सहेजा गया चार्ट इस ओर खोलें">📂 Open</button>
                            <button type="button" class="mlk-btn" data-mlk-save="<?= $p ?>" title="इस ओर का चार्ट सहेजें">💾 Save</button>
                        </div>
                    </div>
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

    <!-- ============ लाल किताब मिलान (Lal Kitab compatibility) ============ -->
    <?php $lk = $view['lkMilan'] ?? null; if ($lk !== null && !empty($lk['ok'])):
        // tone -> chip/border class + Hindi tag
        $toneChip = ['pos' => 'k-full', 'mix' => 'k-part', 'neg' => 'k-zero'];
        $toneBar  = ['pos' => '#22c55e', 'mix' => '#f59e0b', 'neg' => '#ef4444'];
        $toneTag  = ['pos' => 'शुभ', 'mix' => 'मिश्र', 'neg' => 'अशुभ'];
        $lkGauge  = 'g-' . ($lk['tier'] === 'shubh' ? 'shubh' : ($lk['tier'] === 'ashubh' ? 'ashubh' : 'mishrit'));
    ?>
    <style>
        .lkm-axes { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
        @media (max-width: 820px) { .lkm-axes { grid-template-columns: 1fr; } }
        .lkm-axis { border: 1px solid var(--line); border-left-width: 5px; border-radius: 10px; padding: 12px 14px; background: #fff; }
        .lkm-axis h3 { font-size: 1rem; margin-bottom: 6px; display: flex; align-items: center; justify-content: space-between; gap: 8px; }
        .lkm-axis .rsn { font-size: .9rem; color: var(--ink); }
        .lkm-axis ul { margin: 8px 0 0; padding-left: 18px; font-size: .85rem; color: var(--ink-soft); }
        .lkm-axis ul li { margin-bottom: 3px; }
        .lkm-tag { font-weight: 800; border-radius: 999px; padding: 2px 12px; font-size: .78rem; white-space: nowrap; }
        table.lkm { width: 100%; border-collapse: collapse; font-size: .85rem; margin-top: 6px; }
        table.lkm th, table.lkm td { border-bottom: 1px solid var(--line); padding: 6px 8px; text-align: left; vertical-align: top; }
        table.lkm th { color: var(--ink-soft); font-weight: 700; }
        table.lkm td.stat { white-space: nowrap; }
        .st-bad { color: #991b1b; font-weight: 700; }
        .st-good { color: #166534; font-weight: 700; }
        .lkm-rem { border-left: 4px solid var(--accent); background: #fff8ef; border-radius: 0 8px 8px 0; padding: 10px 14px; margin-top: 12px; }
        .lkm-rem li { margin-bottom: 5px; font-size: .9rem; }
        .lkm-rem .who { font-weight: 700; color: var(--accent); }
    </style>
    <div class="card mt">
        <h2 class="sec-title">📕 लाल किताब मिलान — Lal Kitab Compatibility</h2>
        <div class="res-head">
            <div class="mini">
                <div class="nm">वर — <?= $h($lk['nameA']) ?></div>
                <div class="dt">लाल किताब तेवा</div>
            </div>
            <div class="gauge <?= $lkGauge ?>">
                <div class="big"><?= (int) $lk['percent'] ?><span style="font-size:1.1rem">%</span></div>
                <div class="lbl">अनुकूलता</div>
            </div>
            <div class="mini">
                <div class="nm">कन्या — <?= $h($lk['nameB']) ?></div>
                <div class="dt">लाल किताब तेवा</div>
            </div>
        </div>
        <div class="warn" style="background:#f8fafc;border-color:var(--line);color:var(--ink);font-weight:600;margin-top:12px">
            <?= $h($lk['tier_hi']) ?> — <?= $h($lk['verdict']) ?>
        </div>

        <!-- three axes: मंगल · पितृ-ऋण · ग्रह-स्थिति -->
        <div class="lkm-axes" style="margin-top:14px">
            <?php foreach ($lk['axes'] as $ax): $t = (string) $ax['tone']; ?>
                <div class="lkm-axis" style="border-left-color:<?= $toneBar[$t] ?? '#94a3b8' ?>">
                    <h3><?= $h($ax['label']) ?>
                        <span class="lkm-tag <?= $toneChip[$t] ?? '' ?>"><?= $h($toneTag[$t] ?? '') ?></span>
                    </h3>
                    <div class="rsn"><?= $h($ax['reason']) ?></div>
                    <?php if (!empty($ax['detail'])): ?>
                        <ul><?php foreach ($ax['detail'] as $d): ?><li><?= $h($d) ?></li><?php endforeach; ?></ul>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- planet-by-planet harmony -->
        <?php if (!empty($lk['pairs'])): ?>
        <h3 style="font-size:1.02rem;margin:16px 0 4px">ग्रह-स्थिति तुलना</h3>
        <div style="overflow-x:auto">
        <table class="lkm">
            <thead><tr>
                <th>ग्रह</th>
                <th><?= $h($lk['nameA']) ?> (भाव · स्थिति)</th>
                <th><?= $h($lk['nameB']) ?> (भाव · स्थिति)</th>
                <th>निष्कर्ष</th>
            </tr></thead>
            <tbody>
            <?php foreach ($lk['pairs'] as $pr): ?>
                <tr>
                    <td><b><?= $h($pr['hi']) ?></b></td>
                    <td class="stat"><?= (int) $pr['a_house'] ?> · <span class="<?= $pr['a_bad'] ? 'st-bad' : 'st-good' ?>"><?= $h($pr['a_status']) ?></span></td>
                    <td class="stat"><?= (int) $pr['b_house'] ?> · <span class="<?= $pr['b_bad'] ? 'st-bad' : 'st-good' ?>"><?= $h($pr['b_status']) ?></span></td>
                    <td>
                        <span class="lkm-tag <?= $toneChip[$pr['tone']] ?? '' ?>" style="font-size:.7rem"><?= $h($toneTag[$pr['tone']] ?? '') ?></span>
                        <?= $h($pr['note']) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <!-- combined remedies for every negative finding -->
        <?php if (!empty($lk['remedies'])): ?>
        <div class="lkm-rem">
            <div style="font-weight:700;color:var(--accent);margin-bottom:6px">🛠 उपाय (मिलान के अनुसार)</div>
            <ul style="margin:0;padding-left:18px">
                <?php foreach ($lk['remedies'] as $rm): ?>
                    <li><span class="who"><?= $h($rm['who']) ?><?= trim((string) $rm['hi']) !== '' ? ' · ' . $h($rm['hi']) : '' ?>:</span> <?= $h($rm['text']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php else: ?>
        <div style="margin-top:12px;color:#166534;font-weight:600">✓ किसी दोष के लिए उपाय की आवश्यकता नहीं — मिलान शुभ है।</div>
        <?php endif; ?>
    </div>

    <?php endif; ?>

    <?php endif; ?>
        </div><!-- /.content -->
    </div><!-- /.layout -->
</main>

<script src="<?= $h($asset('/assets/js/northchart.js')) ?>"></script>
<script src="<?= $h($asset('/assets/js/chartzoom.js')) ?>"></script>
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
<script src="<?= $h($asset('/assets/js/datefmt.js')) ?>"></script>
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

  // Auto-fix date/time (incl. month names like "jan") on blur, and block मिलान
  // with a clear message if a value can't be understood — same as /calc.
  var fields = [];
  document.querySelectorAll('.fmt-date').forEach(function (el) { if (window.ABDate) { window.ABDate.attach(el, 'date'); fields.push({ el: el, kind: 'date' }); } });
  document.querySelectorAll('.fmt-time').forEach(function (el) { if (window.ABDate) { window.ABDate.attach(el, 'time'); fields.push({ el: el, kind: 'time' }); } });
  var mForm = document.querySelector('.fmt-date') && document.querySelector('.fmt-date').closest('form');
  if (window.ABDate && mForm) { window.ABDate.guardForm(mForm, fields); }
})();
</script>

<!-- ===== Per-side Save / Open (Kundali Milan) — shares storage with birth chart ===== -->
<div id="mlk-open-modal" class="mlk-modal-overlay hidden" aria-hidden="true">
    <div class="mlk-modal" role="dialog" aria-modal="true">
        <div class="mlk-modal-head"><span id="mlk-open-title">📂 सहेजे गए चार्ट खोलें</span>
            <span id="mlk-open-count" class="mlk-open-count"></span>
            <button type="button" class="mlk-modal-x" data-mlk-close aria-label="बंद करें">✕</button></div>
        <div class="mlk-open-search">
            <input id="mlk-open-q" type="text" autocomplete="off" placeholder="🔎 नाम, फ़ोन, email, शहर, स्थान या तारीख़ से खोजें…">
        </div>
        <div id="mlk-open-list" class="mlk-open-list"></div>
    </div>
</div>

<div id="mlk-save-modal" class="mlk-modal-overlay hidden" aria-hidden="true">
    <div class="mlk-modal" role="dialog" aria-modal="true">
        <div class="mlk-modal-head">💾 चार्ट सहेजें — Profile
            <button type="button" class="mlk-modal-x" data-mlk-close aria-label="बंद करें">✕</button></div>
        <div class="mlk-modal-body">
            <div id="mlk-save-sum" class="mlk-save-sum"></div>
            <div class="mlk-save-grid">
                <label>नाम (Name) <span class="mlk-req">*</span><input id="mlk-sv-name" type="text" autocomplete="off"></label>
                <label>फ़ोन नंबर (Phone)<input id="mlk-sv-phone" type="text" inputmode="tel" autocomplete="off"></label>
                <label>Email ID<input id="mlk-sv-email" type="email" autocomplete="off"></label>
                <label>पता (Address)<input id="mlk-sv-address" type="text" autocomplete="off"></label>
                <label>शहर (City)<input id="mlk-sv-city" type="text" autocomplete="off"></label>
                <label>देश (Country)<input id="mlk-sv-country" type="text" autocomplete="off"></label>
            </div>
            <div id="mlk-save-err" class="mlk-save-err hidden"></div>
            <p class="mlk-note">* नाम तथा जन्म-तिथि/समय/स्थान अनिवार्य हैं; शेष विवरण optional। चार्ट जन्म-कुंडली पेज के समान स्थान पर सहेजा जाता है।</p>
        </div>
        <div class="mlk-modal-foot">
            <button type="button" class="mlk-btn" id="mlk-save-confirm">💾 सहेजें / Save</button>
            <button type="button" class="mlk-btn mlk-btn-ghost" data-mlk-close>रद्द करें / Cancel</button>
        </div>
    </div>
</div>
<div id="mlk-toast" class="mlk-toast hidden" role="status" aria-live="polite"></div>
<script src="<?= $h($asset('/assets/js/milan_charts.js')) ?>"></script>

<!-- Prediction-text translation (same as /calc) so the language toggle works here too. -->
<script src="<?= $h($asset('/assets/js/translate.js')) ?>"></script>
<script>
// Top-bar chrome shared with /calc: ☰ Menu opens the side nav as a drawer on
// phone/tablet, and the हिन्दी/English switch translates the Milan result text.
(function () {
    var btn = document.getElementById('menu-btn'), overlay = document.getElementById('menu-overlay'),
        menu = document.getElementById('milan-side');
    if (btn && overlay && menu) {
        function open() { document.body.classList.add('menu-open'); overlay.hidden = false; btn.setAttribute('aria-expanded', 'true'); }
        function close() { document.body.classList.remove('menu-open'); overlay.hidden = true; btn.setAttribute('aria-expanded', 'false'); }
        btn.addEventListener('click', function () { document.body.classList.contains('menu-open') ? close() : open(); });
        overlay.addEventListener('click', close);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
        menu.querySelectorAll('a').forEach(function (a) { a.addEventListener('click', close); });
    }
    var sel = document.getElementById('topbar-lang');
    if (sel) {
        try { var s = localStorage.getItem('ab_pred_lang'); if (s) { sel.value = s; } } catch (e) {}
        sel.addEventListener('change', function () { if (window.ABTranslate) { window.ABTranslate.setLang(this.value); } });
    }
})();
</script>
</body>
</html>
