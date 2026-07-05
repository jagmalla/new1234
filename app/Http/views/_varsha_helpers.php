<?php
declare(strict_types=1);

/**
 * Shared helpers for the Varshaphal partials (_saham_pane, _tajik_yoga,
 * _varsha_bala_cards, _varsha_positions). calc-v2.php defines these itself;
 * the varshaphal JSON endpoint requires this file before rendering the same
 * partials so a year change reproduces identical markup. Every definition is
 * guarded — an already-defined helper is never overwritten.
 */

$h = $h ?? static fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);

$planetColors = $planetColors ?? [
    'Sun' => '#dc2626', 'Moon' => '#0891b2', 'Mars' => '#ea580c', 'Mercury' => '#16a34a',
    'Jupiter' => '#b45309', 'Venus' => '#db2777', 'Saturn' => '#1d4ed8',
    'Rahu' => '#3d4554', 'Ketu' => '#3d4554',
];
$pcolor = $pcolor ?? static fn($name) => $planetColors[$name] ?? '#111827';

$rashiHi = $rashiHi ?? [
    'Aries' => 'मेष', 'Taurus' => 'वृषभ', 'Gemini' => 'मिथुन', 'Cancer' => 'कर्क',
    'Leo' => 'सिंह', 'Virgo' => 'कन्या', 'Libra' => 'तुला', 'Scorpio' => 'वृश्चिक',
    'Sagittarius' => 'धनु', 'Capricorn' => 'मकर', 'Aquarius' => 'कुंभ', 'Pisces' => 'मीन',
];
$grahaHi = $grahaHi ?? [
    'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
    'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
];
