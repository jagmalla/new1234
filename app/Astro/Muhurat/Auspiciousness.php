<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🔴🟡🟢 मुहूर्त शुभता-मापक — एक 0–100 अंक व लाल→हरा संकेत-पट्टी (signal bar)।
 *
 * हर मुहूर्त-निर्णय (जिसमें tone व दोष-सूची हो) को एक अंक देता है और एक
 * स्व-निहित (inline-CSS) पट्टी-HTML लौटाता है — जिससे यह गोचर-पैनल, आज का
 * Consult व प्रोफ़ाइल — सर्वत्र बिना साझा-CSS के प्रयुक्त हो सके।
 */
final class Auspiciousness
{
    /**
     * 0–100 शुभता-अंक। tone: pos/info/neg; $doshas में प्रत्येक का 'sev' (1/2)।
     * @param array<int,array<string,mixed>> $doshas
     */
    public static function score(string $tone, array $doshas = []): int
    {
        $sev = 0; $strong = 0;
        foreach ($doshas as $d) {
            $s = (int) ($d['sev'] ?? 1);
            $sev += $s;
            if ($s >= 2) { $strong++; }
        }
        $val = match ($tone) {
            'pos' => 100 - $sev * 3,
            'neg' => 38 - $strong * 10 - $sev * 2,
            default => 68 - $sev * 6,
        };
        // बैंड-सीमा में रखें ताकि श्रेणी से मेल खाए।
        $val = match ($tone) {
            'pos' => max(80, $val),
            'neg' => max(6, min(36, $val)),
            default => max(40, min(70, $val)),
        };
        return max(0, min(100, $val));
    }

    /** check-आधारित निर्णय (सफल/असफल) हेतु — असफल-गणना से अंक। */
    public static function scoreByFails(string $tone, int $fails): int
    {
        return self::score($tone, array_fill(0, max(0, $fails), ['sev' => 1]));
    }

    /** अंक → [श्रेणी-शब्द, रंग]। */
    public static function band(int $s): array
    {
        if ($s >= 80) { return ['श्रेष्ठ', '#15803d']; }
        if ($s >= 60) { return ['शुभ', '#65a30d']; }
        if ($s >= 45) { return ['मध्यम', '#d97706']; }
        if ($s >= 30) { return ['निर्बल', '#ea580c']; }
        return ['अशुभ', '#dc2626'];
    }

    /**
     * लाल→अम्बर→हरा संकेत-पट्टी (स्व-निहित inline styles)।
     * $label खाली हो तो बैंड-शब्द दिखेगा; अन्यथा मुहूर्त की अपनी श्रेणी।
     */
    public static function barHtml(int $score, string $label = ''): string
    {
        $score = max(0, min(100, $score));
        [$bandWord, $col] = self::band($score);
        $word = $label !== '' ? $label : $bandWord;
        $wordEsc = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
        $left = 'calc(' . $score . '% - 7px)';
        return '<div style="margin:7px 0 3px">'
            . '<div style="display:flex;justify-content:space-between;font-size:.72rem;font-weight:700;margin-bottom:3px">'
            . '<span style="color:#6b7280">🎯 शुभता-मापक</span>'
            . '<span style="color:' . $col . '">' . $wordEsc . ' · ' . $score . '%</span></div>'
            . '<div style="position:relative;height:9px;border-radius:999px;'
            . 'background:linear-gradient(90deg,#dc2626 0%,#f59e0b 42%,#84cc16 68%,#16a34a 100%)">'
            . '<div style="position:absolute;top:-3px;left:' . $left . ';width:14px;height:14px;border-radius:50%;'
            . 'background:#fff;border:3px solid ' . $col . ';box-shadow:0 1px 3px rgba(0,0,0,.35)"></div>'
            . '</div></div>';
    }
}
