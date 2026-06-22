<?php
declare(strict_types=1);

namespace AutoBusiness\Queue;

/**
 * Minimal 5-field cron evaluator (minute hour day-of-month month day-of-week).
 *
 * Supports per field: "*", lists "a,b,c", ranges "a-b", and steps "*\/n" or
 * "a-b/n". Enough to drive workflow schedules; the runner uses it to decide
 * which schedules are due and to compute the next run time stored in
 * workflows.next_run_at.
 */
final class CronSchedule
{
    /** @var array{0:int[],1:int[],2:int[],3:int[],4:int[]} */
    private array $fields;

    public function __construct(string $expression)
    {
        $parts = preg_split('/\s+/', trim($expression)) ?: [];
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException('Cron expression must have 5 fields.');
        }
        $this->fields = [
            $this->parseField($parts[0], 0, 59),
            $this->parseField($parts[1], 0, 23),
            $this->parseField($parts[2], 1, 31),
            $this->parseField($parts[3], 1, 12),
            $this->parseField($parts[4], 0, 6),
        ];
    }

    /** Does this expression fire at the given minute? */
    public function matches(\DateTimeImmutable $time): bool
    {
        return in_array((int) $time->format('i'), $this->fields[0], true)
            && in_array((int) $time->format('G'), $this->fields[1], true)
            && in_array((int) $time->format('j'), $this->fields[2], true)
            && in_array((int) $time->format('n'), $this->fields[3], true)
            && in_array((int) $time->format('w'), $this->fields[4], true);
    }

    /**
     * First minute strictly after $from at which the schedule fires. Scans
     * forward minute-by-minute, capped at ~366 days to avoid runaway loops.
     */
    public function nextRun(\DateTimeImmutable $from): \DateTimeImmutable
    {
        $candidate = $from->setTime((int) $from->format('G'), (int) $from->format('i'), 0)
                          ->modify('+1 minute');
        $limit = 366 * 24 * 60;
        for ($i = 0; $i < $limit; $i++) {
            if ($this->matches($candidate)) {
                return $candidate;
            }
            $candidate = $candidate->modify('+1 minute');
        }
        throw new \RuntimeException('No matching cron time found within one year.');
    }

    /**
     * @return int[] the set of valid values for one field
     */
    private function parseField(string $field, int $min, int $max): array
    {
        $values = [];
        foreach (explode(',', $field) as $token) {
            $step = 1;
            $range = $token;
            if (str_contains($token, '/')) {
                [$range, $stepStr] = explode('/', $token, 2);
                $step = max(1, (int) $stepStr);
            }

            if ($range === '*') {
                $start = $min;
                $end = $max;
            } elseif (str_contains($range, '-')) {
                [$a, $b] = explode('-', $range, 2);
                $start = (int) $a;
                $end = (int) $b;
            } else {
                $start = $end = (int) $range;
            }

            if ($start < $min || $end > $max || $start > $end) {
                throw new \InvalidArgumentException("Cron field out of range: {$token}");
            }
            for ($v = $start; $v <= $end; $v += $step) {
                $values[$v] = true;
            }
        }
        ksort($values);
        return array_keys($values);
    }
}
