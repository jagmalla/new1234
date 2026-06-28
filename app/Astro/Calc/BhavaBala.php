<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Bhava Bala — strength of the twelve houses, in the component model used by
 * Parashara's Light. Each component is in virupas; their sum is the Bhava Bala.
 *
 *   • From Lord  (Bhavadhipati Bala) — the bhava lord's Shadbala total.
 *   • Dig Bala   (Bhava Digbala)     — directional strength of the bhava madhya
 *                                       (full at the Lagna degree, falling to the
 *                                       7th). Standard BPHS-style figure.
 *   • Drishti    (Bhava Drishti Bala)— net benefic−malefic Sphuta Drishti cast on
 *                                       the bhava madhya by planets NOT occupying
 *                                       the bhava.
 *   • Planets in (occupants)         — sign(benefics − malefics in the bhava) × 60,
 *                                       Jup/Ven/Mer benefic, Sun/Mars/Sat malefic;
 *                                       the Moon and the nodes are ignored. This
 *                                       reproduces Parashara's Light exactly.
 *
 * From Lord and Planets-in match Parashara's Light; Dig Bala and Drishti use the
 * standard BPHS formulas (close to, but not identical with, PL's curves). PL's
 * small day/night term is not separately modelled.
 */
final class BhavaBala
{
    private const CLASSICAL = ['Sun', 'Moon', 'Mars', 'Mercury', 'Jupiter', 'Venus', 'Saturn'];
    private const BENEFIC   = ['Jupiter', 'Venus', 'Mercury'];   // for occupants + drishti
    private const MALEFIC   = ['Sun', 'Mars', 'Saturn'];         // for occupants

    /**
     * @param array<string, array<string,mixed>> $shadbala     planet => shadbala row
     * @param array<string, float>                $siderealLon  planet => sidereal lon
     * @param array<int, list<string>>            $occupants    house => planet names (incl. nodes)
     * @return array<int, array{house:int, sign:int, lord:string, adhipati:float,
     *               digbala:float, drishti:float, planets_in:float,
     *               total_virupa:float, rupa:float}>
     */
    public static function compute(float $ascLon, int $ascSign, array $shadbala, array $siderealLon, array $occupants = []): array
    {
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $sign = (($ascSign + $h - 1) % 12 + 12) % 12;
            $lord = Charts::signLord($sign);
            $adhipati = (float) ($shadbala[$lord]['total_virupa'] ?? 0.0);

            // Bhava madhya (equal-house cusp from the Lagna degree).
            $cusp = Charts::norm($ascLon + ($h - 1) * 30.0);
            $occ  = $occupants[$h] ?? [];

            // Dig Bala: bhava strongest at the Lagna degree (60), falling to 0 at
            // the 7th. (BPHS directional strength of the bhava madhya.)
            $sep = abs($cusp - $ascLon);
            if ($sep > 180.0) {
                $sep = 360.0 - $sep;
            }
            $digbala = 60.0 - $sep / 3.0;

            // Drishti: net benefic−malefic Sphuta Drishti from planets that are NOT
            // in the bhava (occupants are scored separately, as Planets-in).
            $drishti = 0.0;
            foreach (self::CLASSICAL as $pl) {
                if (in_array($pl, $occ, true) || !isset($siderealLon[$pl])) {
                    continue;
                }
                $v = Shadbala::drishtiOnPoint($pl, $siderealLon[$pl], $cusp);
                $isBenefic = ($pl === 'Moon') ? true : in_array($pl, self::BENEFIC, true);
                $drishti += ($isBenefic ? 1.0 : -1.0) * $v;
            }

            // Planets-in: net benefic−malefic occupants → sign × 60 (Moon & nodes ignored).
            $net = 0;
            foreach ($occ as $pl) {
                if (in_array($pl, self::BENEFIC, true)) {
                    $net++;
                } elseif (in_array($pl, self::MALEFIC, true)) {
                    $net--;
                }
            }
            $planetsIn = $net > 0 ? 60.0 : ($net < 0 ? -60.0 : 0.0);

            $total = $adhipati + $digbala + $drishti + $planetsIn;
            $out[$h] = [
                'house' => $h,
                'sign' => $sign,
                'lord' => $lord,
                'adhipati' => round($adhipati, 1),
                'digbala' => round($digbala, 1),
                'drishti' => round($drishti, 1),
                'planets_in' => round($planetsIn, 1),
                'total_virupa' => round($total, 1),
                'rupa' => round($total / 60.0, 2),
            ];
        }
        return $out;
    }
}
