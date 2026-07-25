<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

use AutoBusiness\Astro\Calc\CalculationEngine;
use AutoBusiness\Astro\Time\JulianDay;

/**
 * 🔍 मुहूर्त तिथि-खोजक (सामान्यीकृत) — किसी भी मुहूर्त-प्रकार हेतु तिथि-सीमा
 * (from → to) में प्रत्येक दिन का शुभता-अंक व श्रेणी निकालता है।
 *
 * प्रत्येक दिन सूर्योदय-सन्निकट (~06:00) एक नमूने पर — सूर्य·चन्द्र·गुरु·शुक्र
 * निरयन देशान्तर से सम्बद्ध मुहूर्त-इंजन चलाकर {@see Auspiciousness} से 0–100
 * अंक देता है। पंचांग-आधारित समस्त प्रकार समर्थित (कुण्डली-निरपेक्ष)।
 */
final class MuhuratDateScanner
{
    private const MAX_DAYS = 120;

    /** समर्थित प्रकार → प्रदर्शन-नाम (drop-down)। */
    public const TYPES = [
        'general' => '🗓️ सामान्य मुहूर्त (पंचांग-शुद्धि)',
        'vivaha' => '💍 विवाह मुहूर्त',
        'agnyadhana' => '🔥 अग्न्याधान',
        'vadhupravesh' => '💑 वधूप्रवेश',
        'dviragamana' => '🔄 द्विरागमन',
        'vastu' => '🏗️ गृहारम्भ / वास्तु',
        'grihapravesh' => '🚪 गृहप्रवेश',
        'rajyabhishek' => '👑 राज्याभिषेक / शपथ-ग्रहण',
        'pratishtha' => '🛕 प्रतिष्ठा (देव-मूर्ति)',
        'yuddha' => '⚔️ युद्ध / प्रतिस्पर्धा',
        'krishi' => '🌾 कृषि (बीज-वपन)',
        'vanijya' => '💰 वाणिज्य / ऋण',
        'chikitsa' => '💊 रोग / चिकित्सा-आरम्भ',
        'namkaran' => '🍼 नामकरण',
        'seemantonnayan' => '🤰 सीमन्तोन्नयन',
        'annaprashan' => '🍚 अन्नप्राशन',
        'karnavedh' => '👂 कर्णवेध',
        'mundan' => '💈 मुण्डन / चूडाकर्म',
        'vidyarambh' => '📖 विद्यारम्भ',
        'upanayan' => '🧵 उपनयन / यज्ञोपवीत',
    ];

    private const D = MuhuratChintamaniData::class;

    /**
     * @param array{0:int,1:int,2:int} $from
     * @param array{0:int,1:int,2:int} $to
     */
    public static function scan(CalculationEngine $engine, string $type, array $from, array $to, float $tz): array
    {
        if (!isset(self::TYPES[$type])) {
            return ['ok' => false, 'error' => 'अज्ञात मुहूर्त-प्रकार।'];
        }
        $jdStart = JulianDay::fromGregorian($from[0], $from[1], $from[2], 6, 0, 0.0, $tz);
        $jdEnd = JulianDay::fromGregorian($to[0], $to[1], $to[2], 6, 0, 0.0, $tz);
        if ($jdEnd < $jdStart) {
            return ['ok' => false, 'error' => 'अन्तिम तिथि प्रारम्भ-तिथि से पहले है।'];
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
            $blk = self::evaluate($type, $sun, $moon, (int) $wd, $transits);
            $grade = (string) $blk['grade'];
            // श्रेणी को शुभ/मध्यम/अशुभ में सामान्य करें (युद्ध की 'जय' = शुभ)।
            $norm = in_array($grade, ['शुभ', 'जय'], true) ? 'शुभ' : ($grade === 'मध्यम' ? 'मध्यम' : 'अशुभ');
            $count[$norm]++;
            $score = Auspiciousness::score((string) $blk['tone'], $blk['bad']);
            [$gy, $gm, $gd] = JulianDay::toGregorian($jd, $tz);
            $rows[] = [
                'date' => sprintf('%02d-%02d-%04d', $gd, $gm, $gy),
                'weekday' => self::D::WEEKDAY_HI[$wd],
                'grade' => $grade,
                'tone' => (string) $blk['tone'],
                'score' => $score,
                'reason' => $blk['reason'],
            ];
        }
        $best = array_values(array_filter($rows, static fn($x) => !in_array($x['grade'], ['अशुभ'], true)));
        // शुभ दिन पहले (अंक-अवरोही), तिथि-क्रम भीतर बनाए रखते हुए स्थिर।
        usort($best, static fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'ok' => true, 'type' => $type, 'type_label' => self::TYPES[$type],
            'rows' => $rows, 'best' => $best, 'count' => $count,
            'capped' => $capped, 'total' => $total, 'scanned' => $days, 'max_days' => self::MAX_DAYS,
        ];
    }

    /**
     * एक दिन के लिए चुने प्रकार का मुहूर्त-निर्णय → ['grade','tone','bad','reason']।
     */
    private static function evaluate(string $type, float $sun, float $moon, int $wd, array $transits): array
    {
        switch ($type) {
            case 'general':
                $ms = (int) floor(fmod($moon + 360.0, 360.0) / 30.0) % 12;
                $r = GeneralMuhuratEngine::compute($sun, $moon, $wd, $ms);
                return self::norm($r['grade'], $r['tone'], $r['dosha'] ?? [], $r['shubh'] ?? []);
            case 'vivaha':
                $r = VivahaMuhuratEngine::muhurat($sun, $moon, $wd, $transits);
                return self::norm($r['grade'], $r['tone'], $r['bad'] ?? []);
            case 'agnyadhana':
            case 'vadhupravesh':
            case 'dviragamana':
                $r = AgniVivahaMuhuratEngine::computeAll($sun, $moon, $wd, $transits)[$type];
                return self::norm($r['grade'], $r['tone'], $r['bad'] ?? []);
            case 'vastu':
            case 'grihapravesh':
            case 'rajyabhishek':
                $key = $type === 'rajyabhishek' ? 'rajya' : $type;
                $r = VastuMuhuratEngine::computeAll($sun, $moon, $wd, $transits)[$key];
                return self::norm($r['grade'], $r['tone'], $r['bad'] ?? []);
            case 'pratishtha':
                $r = YatraPratishthaEngine::compute($sun, $moon, $wd, $transits)['pratishtha'];
                return self::norm($r['grade'], $r['tone'], $r['bad'] ?? []);
            case 'yuddha':
            case 'krishi':
            case 'vanijya':
            case 'chikitsa':
                $r = KaryaMuhuratEngine::computeAll($sun, $moon, $wd)[$type];
                return self::norm($r['grade'], $r['tone'], $r['bad'] ?? []);
            default: // sanskara rites
                $all = SanskaraMuhuratEngine::computeAll($sun, $moon, $wd);
                foreach ($all['rites'] as $rt) {
                    if ($rt['key'] === $type) {
                        $fails = 0; $bad = [];
                        foreach ($rt['checks'] as $c) { if (empty($c['ok'])) { $fails++; $bad[] = ['name' => $c['label'], 'sev' => 1]; } }
                        $reason = $bad === [] ? 'सभी अंग अनुकूल' : ('बाधा: ' . $bad[0]['name']);
                        return ['grade' => $rt['grade'], 'tone' => $rt['tone'], 'bad' => $bad, 'reason' => $reason];
                    }
                }
                return ['grade' => 'अशुभ', 'tone' => 'neg', 'bad' => [['sev' => 2]], 'reason' => '—'];
        }
    }

    /** निर्णय-सामान्यीकरण + शीर्ष-कारण। */
    private static function norm(string $grade, string $tone, array $bad, array $good = []): array
    {
        if ($bad !== []) {
            $reason = 'बाधा: ' . (string) ($bad[0]['name'] ?? '');
        } elseif ($good !== []) {
            $reason = '✓ ' . (string) ($good[0]['name'] ?? 'अनुकूल');
        } else {
            $reason = 'सभी अंग अनुकूल';
        }
        return ['grade' => $grade, 'tone' => $tone, 'bad' => $bad, 'reason' => $reason];
    }
}
