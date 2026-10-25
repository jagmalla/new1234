<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Muhurat;

/**
 * 🔍 तिथि खोज — कार्यों की सूची (12 मुख्य श्रेणियाँ → 87 कार्य)।
 *
 * सूची अब मालिक की नियम-पुस्तिका ({@see KaryaNiyamData}) से ही बनती है — वही
 * 87 कार्य, उसी क्रम व उन्हीं नामों से, और हर कार्य के अपने पूरे नियम हैं
 * (पहले 22 कार्य पुराने इंजनों से, बाक़ी सामान्य पंचांग-शुद्धि से खोजे जाते थे)।
 * कार्य की कुंजी पुस्तिका का ID है (जैसे "1.11" = शादी / विवाह) — नियम उसी
 * ID से जुड़े हैं, इसलिए इसे बदलें नहीं।
 */
final class TithiKhojCatalog
{
    /**
     * पन्ने के लिए: श्रेणियाँ → कार्य (ID, नाम, विकल्प-कूट), और विकल्पों की परिभाषा।
     *
     * @return array{cats: list<array{key: string, emoji: string, label: string,
     *   subs: list<array{key: string, label: string, opts: list<string>}>}>,
     *   options: array<string, array{label: string, choices: array<string, string>}>}
     */
    public static function forView(): array
    {
        $cats = [];
        foreach (KaryaNiyamData::CATEGORIES as $n => $c) {
            $cats[$n] = ['key' => (string) $n, 'emoji' => $c['emoji'], 'label' => $c['name'], 'subs' => []];
        }
        foreach (KaryaNiyamData::TASKS as $id => $t) {
            $cats[$t['c']]['subs'][] = [
                'key' => (string) $id,
                'label' => $t['name'],
                'opts' => array_values(KaryaNiyamVidhi::rule((string) $id)['options'] ?? []),
            ];
        }
        $opts = [];
        foreach (KaryaNiyamVidhi::OPTIONS as $k => [$label, $choices]) {
            $opts[$k] = ['label' => $label, 'choices' => $choices];
        }
        return ['cats' => array_values($cats), 'options' => $opts];
    }
}
