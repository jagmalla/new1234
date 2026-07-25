<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * 💍 विवाह-मुहूर्त तिथि-खोजक — Kundali Milan के अन्त में तिथि-सीमा (from → to)
 * में प्रत्येक दिन का विवाह-मुहूर्त फल निकालता है।
 *
 * प्रत्येक दिन (सूर्योदय-सन्निकट ~06:00 पर एक नमूना) के सूर्य·चन्द्र·गुरु·शुक्र
 * निरयन देशान्तर से {@see VivahaMuhuratEngine::muhurat} चलाकर शुभ/मध्यम/अशुभ
 * श्रेणी देता है। समयानुसार तिथि/नक्षत्र बदल सकते हैं — शुभ दिनों का सूक्ष्म
 * मुहूर्त पंचांग-समय से परिष्कृत करें।
 */
final class MarriageDateFinder
{
    private const D = MuhuratChintamaniData::class;
    private const MAX_DAYS = 120;

    /**
     * @param array{0:int,1:int,2:int} $from  प्रारम्भ [Y,M,D]
     * @param array{0:int,1:int,2:int} $to    अन्तिम  [Y,M,D]
     */
    public static function find(CalculationEngine $engine, array $from, array $to, float $tz): array
    {
        $jdStart = JulianDay::fromGregorian($from[0], $from[1], $from[2], 6, 0, 0.0, $tz);
        $jdEnd = JulianDay::fromGregorian($to[0], $to[1], $to[2], 6, 0, 0.0, $tz);
        if ($jdEnd < $jdStart) {
            return ['ok' => false, 'error' => 'अन्तिम तिथि प्रारम्भ-तिथि से पहले है — सही सीमा चुनें।'];
        }
        $total = (int) floor($jdEnd - $jdStart) + 1;
        $capped = $total > self::MAX_DAYS;
        $days = min($total, self::MAX_DAYS);

        $rows = [];
        $count = ['शुभ' => 0, 'मध्यम' => 0, 'अशुभ' => 0];
        for ($i = 0; $i < $days; $i++) {
            $jd = $jdStart + (float) $i;
            $sun = $engine->planetSiderealLon('Sun', $jd);
            $moon = $engine->planetSiderealLon('Moon', $jd);
            $jup = $engine->planetSiderealLon('Jupiter', $jd);
            $ven = $engine->planetSiderealLon('Venus', $jd);
            $wd = ((int) floor($jd + 0.5) + 1) % 7;
            $transits = [
                'Sun' => ['sidereal_lon' => $sun, 'retro' => false],
                'Moon' => ['sidereal_lon' => $moon, 'retro' => false],
                'Jupiter' => ['sidereal_lon' => $jup, 'retro' => false],
                'Venus' => ['sidereal_lon' => $ven, 'retro' => false],
            ];
            $r = VivahaMuhuratEngine::muhurat($sun, $moon, $wd, $transits);
            $grade = (string) $r['grade'];
            $count[$grade] = ($count[$grade] ?? 0) + 1;
            [$gy, $gm, $gd] = JulianDay::toGregorian($jd, $tz);
            $rows[] = [
                'date' => sprintf('%02d-%02d-%04d', $gd, $gm, $gy),
                'weekday' => self::D::WEEKDAY_HI[$wd],
                'grade' => $grade,
                'tone' => (string) $r['tone'],
                'verdict' => (string) $r['verdict'],
                'checks' => $r['checks'],
                'bad' => $r['bad'],
            ];
        }

        // शुभ पहले, फिर मध्यम, फिर अशुभ — तिथि-क्रम बनाए रखते हुए।
        $order = ['शुभ' => 0, 'मध्यम' => 1, 'अशुभ' => 2];
        $best = array_values(array_filter($rows, static fn($x) => $x['grade'] !== 'अशुभ'));

        return [
            'ok' => true,
            'rows' => $rows,
            'best' => $best,
            'count' => $count,
            'capped' => $capped,
            'total' => $total,
            'scanned' => $days,
            'max_days' => self::MAX_DAYS,
        ];
    }
}
