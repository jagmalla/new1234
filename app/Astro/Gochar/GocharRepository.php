<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Gochar;

use AutoBusiness\Core\Database;
use Throwable;

/**
 * Loads the Gochar (transit) prediction rules (migration 017): the Layer-1
 * house text bank (9 planets x 12 houses from Chandra Lagna) and the Layer-3
 * transit-over-natal combination bank. Resilient like the Saham/Tajik
 * repositories — on a DB error it returns the baked {@see GocharData} defaults
 * so the panel still works; lastError() surfaces the reason.
 *
 * The STRUCTURAL rules (shubh/ashubh houses, the vedha table, trigger
 * positions, degree-timing thirds, daivi/asuri classes) are code constants
 * here — they are fixed classical tables, not free text, and a config flag
 * governs the optional layers, mirroring how the Tajik module keeps its
 * dhruvanka/deeptamsha in code.
 */
final class GocharRepository
{
    private static ?string $lastError = null;

    /** Shubh (auspicious) houses from Chandra Lagna per planet (Ch.1 §1.3). */
    public const SHUBH = [
        'Sun' => [3, 6, 10, 11],
        'Moon' => [1, 3, 6, 7, 10, 11],
        'Mars' => [3, 6, 11],
        'Mercury' => [2, 4, 6, 8, 10, 11],
        'Jupiter' => [2, 5, 7, 9, 11],
        'Venus' => [1, 2, 3, 4, 5, 8, 9, 11, 12],
        'Saturn' => [3, 6, 11],
        'Rahu' => [1, 3, 5, 7, 8, 9, 10],
        'Ketu' => [1, 2, 3, 4, 5, 7, 9, 11],
    ];

    /** Vedha table: planet => [vedha house for bhava 1..12] (Ch.2 §2.4). */
    public const VEDHA = [
        'Sun' =>     [1, 2, 9, 3, 6, 12, 7, 8, 10, 4, 5, 11],
        'Moon' =>    [5, 1, 9, 3, 6, 12, 2, 7, 10, 4, 8, 11],
        'Mars' =>    [1, 2, 12, 3, 4, 9, 6, 7, 8, 10, 5, 11],
        'Mercury' => [2, 5, 4, 3, 7, 9, 6, 1, 8, 8, 12, 11],
        'Jupiter' => [1, 12, 2, 5, 4, 6, 3, 7, 10, 9, 8, 11],
        'Venus' =>   [8, 7, 1, 10, 9, 12, 2, 5, 11, 4, 3, 11],
        'Saturn' =>  [1, 2, 12, 3, 4, 9, 6, 7, 8, 10, 5, 11],
        'Rahu' =>    [1, 2, 12, 3, 4, 9, 6, 7, 8, 10, 5, 11],
        'Ketu' =>    [1, 2, 12, 3, 4, 9, 6, 7, 8, 10, 5, 11],
    ];
    /** Mutual vedha exceptions (no vedha between these — father/son pairs). */
    public const VEDHA_EXCEPT = [['Sun', 'Saturn'], ['Moon', 'Mercury']];

    /** Trigger positions of a transit planet FROM a natal planet (Ch.3). */
    public const TRIGGER = [
        'Sun' => [1, 7], 'Moon' => [1, 7], 'Mars' => [1, 6, 7, 10],
        'Mercury' => [1, 7], 'Jupiter' => [1, 5, 7, 9], 'Venus' => [1, 7],
        'Saturn' => [1, 4, 7, 11], 'Rahu' => [1, 5, 7, 9], 'Ketu' => [1, 5, 7, 9],
    ];
    /** Which planet's combination rows a transit planet borrows (Rahu=Shani, Ketu=Mars). */
    public const TEXT_SOURCE = ['Rahu' => 'Saturn', 'Ketu' => 'Mars'];

    /**
     * Degree-timing third per planet (Phaladeepika default, Ch.2 §2.7):
     * [from, to] degrees inside the sign where the transit is most intense.
     */
    public const DEGREE_TIMING = [
        'Sun' => [0, 10], 'Mars' => [0, 10],
        'Jupiter' => [10, 20], 'Venus' => [10, 20],
        'Moon' => [20, 30], 'Saturn' => [20, 30],
        'Mercury' => [0, 30], 'Rahu' => [0, 30], 'Ketu' => [0, 30],
    ];

    /** Daivi / Asuri classification (configurable, Ch.3). */
    public const ASURI = ['Mercury', 'Venus', 'Saturn', 'Rahu'];
    public const DAIVI = ['Sun', 'Moon', 'Jupiter', 'Mars', 'Ketu'];

    /** English <-> Hindi planet names for the panel. */
    public const PLANET_HI = [
        'Sun' => 'सूर्य', 'Moon' => 'चंद्र', 'Mars' => 'मंगल', 'Mercury' => 'बुध',
        'Jupiter' => 'गुरु', 'Venus' => 'शुक्र', 'Saturn' => 'शनि', 'Rahu' => 'राहु', 'Ketu' => 'केतु',
    ];
    /** Hindi labels used in the doc's combination table -> English key. */
    private const HI_KEY = [
        'Surya' => 'Sun', 'Chandra' => 'Moon', 'Mangal' => 'Mars', 'Budh' => 'Mercury',
        'Guru' => 'Jupiter', 'Shukra' => 'Venus', 'Shani' => 'Saturn', 'Rahu' => 'Rahu', 'Ketu' => 'Ketu',
    ];

    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * @return array{
     *   house_phal: array<string,array<int,array{shubh:bool,text:string}>>,
     *   natal_combo: array<string,array<string,list<array{cond:string,text:string}>>>,
     *   config: array<string,string>
     * }
     */
    public static function load(string $language = 'hi'): array
    {
        self::$lastError = null;
        $out = [
            'house_phal' => self::bakedHousePhal(),
            'natal_combo' => self::bakedNatalCombo(),
            'config' => self::defaultConfig(),
        ];
        try {
            $pdo = Database::pdo();

            $hp = [];
            $stmt = $pdo->prepare('SELECT planet, house, shubh, text_hi FROM gochar_house_phal WHERE language = ?');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $hp[(string) $r['planet']][(int) $r['house']] = [
                    'shubh' => (int) $r['shubh'] === 1, 'text' => (string) $r['text_hi'],
                ];
            }
            if ($hp !== []) {
                // Merge over baked so a partially-filled table still yields all 108.
                foreach ($hp as $p => $rows) {
                    foreach ($rows as $hh => $e) { $out['house_phal'][$p][$hh] = $e; }
                }
            }

            $nc = [];
            $stmt = $pdo->prepare('SELECT transit_planet, natal_planet, seq, cond_hi, text_hi FROM gochar_natal_phal WHERE language = ? ORDER BY transit_planet, natal_planet, seq');
            $stmt->execute([$language]);
            foreach ($stmt as $r) {
                $nc[(string) $r['transit_planet']][(string) $r['natal_planet']][] = [
                    'cond' => (string) $r['cond_hi'], 'text' => (string) $r['text_hi'],
                ];
            }
            if ($nc !== []) { $out['natal_combo'] = $nc; }

            try {
                foreach ($pdo->query('SELECT cfg_key, cfg_value FROM house_engine_config') as $r) {
                    if (str_starts_with((string) $r['cfg_key'], 'gochar_')) {
                        $out['config'][(string) $r['cfg_key']] = (string) $r['cfg_value'];
                    }
                }
            } catch (Throwable $e) { /* config optional */ }

            return $out;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            error_log('Gochar rules load failed (using baked fallback): ' . $e->getMessage());
            return $out;
        }
    }

    /** @return array<string,array<int,array{shubh:bool,text:string}>> */
    private static function bakedHousePhal(): array
    {
        $out = [];
        foreach (GocharData::HOUSE_PHAL as $p => $rows) {
            foreach ($rows as $hh => $e) {
                $out[$p][$hh] = ['shubh' => (bool) $e[0], 'text' => (string) $e[1]];
            }
        }
        return $out;
    }

    /**
     * Baked combination bank, keyed by ENGLISH planet names (the doc uses
     * Hindi transliterations like Surya/Budh — normalise them here so the
     * engine only ever deals with Sun/Mercury/...). Special "(All)" rows for
     * Rahu/Ketu/Mercury and the "Rahu/Ketu" row are preserved as-is.
     *
     * @return array<string,array<string,list<array{cond:string,text:string}>>>
     */
    private static function bakedNatalCombo(): array
    {
        $out = [];
        foreach (GocharData::NATAL_COMBO as $t => $byNatal) {
            $tk = self::HI_KEY[$t] ?? $t;   // "Rahu/Ketu" stays as-is
            foreach ($byNatal as $n => $rows) {
                $nk = self::HI_KEY[$n] ?? $n; // "(All)" stays as-is
                foreach ($rows as $r) {
                    $out[$tk][$nk][] = ['cond' => (string) $r[0], 'text' => (string) $r[1]];
                }
            }
        }
        return $out;
    }

    /** @return array<string,string> */
    private static function defaultConfig(): array
    {
        return [
            'gochar_degree_timing' => 'phaladeepika',
            'gochar_ksheen_orb' => '72',
            'gochar_vedha' => '1',
            'gochar_av_bindu' => '1',
            'gochar_natal_layer' => '1',
        ];
    }
}
