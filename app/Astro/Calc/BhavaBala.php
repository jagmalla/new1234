<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Calc;

/**
 * Bhava Bala — the strength of each of the twelve houses, in Rupas.
 *
 * Two principal components are combined here:
 *   • Bhavadhipathi Bala — the total Shadbala of the bhava lord (the planet that
 *     rules the sign on the house), reused from the Shadbala result.
 *   • Bhava Drishti Bala — the net benefic-minus-malefic Sphuta Drishti cast on
 *     the bhava madhya (house cusp).
 *
 * (Bhava Digbala / Drekkana Bala are documented classical refinements; the two
 * components above are the dominant ones, so the Rupa figure is indicative
 * rather than an exact match to any one program — as with the harder Shadbala
 * sub-balas.)
 */
final class BhavaBala
{
    /**
     * @param array<string, array<string,mixed>> $shadbala     planet => shadbala row
     * @param array<string, float>                $siderealLon  planet => sidereal lon
     * @return array<int, array{house:int, sign:int, lord:string, adhipati:float,
     *               drishti:float, total_virupa:float, rupa:float}>
     */
    public static function compute(float $ascLon, int $ascSign, array $shadbala, array $siderealLon): array
    {
        $out = [];
        for ($h = 1; $h <= 12; $h++) {
            $sign = (($ascSign + $h - 1) % 12 + 12) % 12;
            $lord = Charts::signLord($sign);
            $adhipati = (float) ($shadbala[$lord]['total_virupa'] ?? 0.0);

            // Bhava madhya (equal-house cusp from the Lagna degree).
            $cusp = Charts::norm($ascLon + ($h - 1) * 30.0);
            $drishti = Shadbala::netDrishtiOnPoint($cusp, $siderealLon);

            $total = $adhipati + $drishti;
            $out[$h] = [
                'house' => $h,
                'sign' => $sign,
                'lord' => $lord,
                'adhipati' => round($adhipati, 1),
                'drishti' => round($drishti, 1),
                'total_virupa' => round($total, 1),
                'rupa' => round($total / 60.0, 2),
            ];
        }
        return $out;
    }
}
