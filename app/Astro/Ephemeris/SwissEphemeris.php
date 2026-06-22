<?php
declare(strict_types=1);

namespace AutoBusiness\Astro\Ephemeris;

/**
 * Swiss Ephemeris provider (PREFERRED for accuracy) via the bundled `swetest`
 * command-line binary.
 *
 * WHY A BINARY, NOT THE PHP EXTENSION: the Swiss Ephemeris PHP extension must be
 * compiled and installed at the server level — not possible on A2 shared hosting
 * without root. The precompiled `swetest` CLI, by contrast, can be uploaded and
 * marked executable from the hosting control panel (no SSH/root), then invoked
 * with a strictly-bounded argument list. This honours the "no root-only
 * features" Global Rule while still giving Swiss-Ephemeris-grade positions.
 *
 * It is used only when BOTH are true (checked by isAvailable()): a shell-exec
 * function is enabled, and SWETEST_PATH points at an executable binary.
 * Otherwise EphemerisFactory falls back to the pure-PHP AnalyticEphemeris.
 *
 * Returns TROPICAL geocentric longitudes (the engine applies the ayanamsa), so
 * swetest is invoked WITHOUT -sid.
 */
final class SwissEphemeris implements EphemerisProviderInterface
{
    /** swetest planet selectors -> our body names. Node selector set per nodeType. */
    private const BASE_BODIES = [
        '0' => 'Sun', '1' => 'Moon', '2' => 'Mercury', '3' => 'Venus',
        '4' => 'Mars', '5' => 'Jupiter', '6' => 'Saturn',
    ];

    /** @var array<string,string> */
    private array $bodyMap;

    public function __construct(
        private readonly string $binaryPath,
        private readonly ?string $ephePath = null,
        string $nodeType = 'true'
    ) {
        // swetest: 't' = true node, 'm' = mean node.
        $this->bodyMap = self::BASE_BODIES + [($nodeType === 'mean' ? 'm' : 't') => 'Rahu'];
    }

    public function name(): string
    {
        return 'Swiss Ephemeris (swetest)';
    }

    /** Is a usable swetest available in this environment? */
    public static function isAvailable(?string $binaryPath): bool
    {
        if ($binaryPath === null || $binaryPath === '' || !is_file($binaryPath)) {
            return false;
        }
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        return function_exists('shell_exec') && !in_array('shell_exec', $disabled, true);
    }

    public function positions(float $jdUt): array
    {
        $selectors = implode('', array_keys($this->bodyMap));

        // -bj<jd>  : begin date as Julian Day (UT)
        // -p...    : bodies; -fPls : fields = name, longitude, speed
        // -g,      : comma field separator; -head : no header rows
        $args = [
            '-bj' . sprintf('%.6f', $jdUt),
            '-p' . $selectors,
            '-fPls',
            '-g,',
            '-head',
        ];
        if ($this->ephePath !== null && $this->ephePath !== '') {
            $args[] = '-edir' . $this->ephePath;
        }

        $cmd = escapeshellcmd($this->binaryPath);
        foreach ($args as $a) {
            $cmd .= ' ' . escapeshellarg($a);
        }

        $output = @shell_exec($cmd . ' 2>/dev/null');
        if (!is_string($output) || trim($output) === '') {
            throw new \RuntimeException('swetest produced no output.');
        }

        $byName = [];
        foreach (preg_split('/\r?\n/', trim($output)) as $line) {
            $parts = array_map('trim', explode(',', $line));
            if (count($parts) < 2) {
                continue;
            }
            // parts: [name, longitude, speed?]
            $name = $this->matchBody($parts[0]);
            if ($name === null) {
                continue;
            }
            $lon = (float) $parts[1];
            $speed = isset($parts[2]) ? (float) $parts[2] : 0.0;
            $byName[$name] = [
                'lon' => $this->norm($lon),
                'lat' => 0.0,
                'speed' => $speed,
                'retro' => $speed < 0.0,
            ];
        }

        if (!isset($byName['Sun'], $byName['Moon'], $byName['Rahu'])) {
            throw new \RuntimeException('swetest output could not be parsed.');
        }

        // Ketu is exactly opposite Rahu.
        $byName['Ketu'] = [
            'lon' => $this->norm($byName['Rahu']['lon'] + 180.0),
            'lat' => 0.0,
            'speed' => $byName['Rahu']['speed'],
            'retro' => $byName['Rahu']['retro'],
        ];

        return $byName;
    }

    private function matchBody(string $label): ?string
    {
        foreach ($this->bodyMap as $name) {
            if (stripos($label, $name) === 0) {
                return $name;
            }
        }
        // swetest labels the mean node as "mean Node".
        if (stripos($label, 'node') !== false) {
            return 'Rahu';
        }
        return null;
    }

    private function norm(float $deg): float
    {
        $deg = fmod($deg, 360.0);
        return $deg < 0 ? $deg + 360.0 : $deg;
    }
}
