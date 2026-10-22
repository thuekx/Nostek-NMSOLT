<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Status honesty for cached device data (NMS-07-D-2).
 *
 * The UI reads OLT/ONU state from the last poll snapshot. A snapshot older than three poll
 * intervals is STALE, one that was never taken is UNKNOWN, and an OLT whose polling is off is
 * PAUSED. None of those may be shown green, whatever the last snapshot said.
 */
final class Freshness
{
    public const FRESH = 'fresh';

    public const STALE = 'stale';

    public const UNKNOWN = 'unknown';

    public const PAUSED = 'paused';

    /** Same rule as the Nostek AI Control Center: stale after 3 × the poll interval. */
    public const STALE_AFTER_INTERVALS = 3;

    /**
     * @return array{state: string, at: ?string, age_seconds: ?int, threshold_seconds: int}
     */
    public static function of(CarbonInterface|string|null $at, int $intervalMinutes, bool $pollingEnabled = true): array
    {
        $threshold = max(1, $intervalMinutes) * 60 * self::STALE_AFTER_INTERVALS;

        if ($at === null || $at === '') {
            return ['state' => self::UNKNOWN, 'at' => null, 'age_seconds' => null, 'threshold_seconds' => $threshold];
        }

        try {
            $time = $at instanceof CarbonInterface ? $at : Carbon::parse($at);
        } catch (\Throwable) {
            return ['state' => self::UNKNOWN, 'at' => null, 'age_seconds' => null, 'threshold_seconds' => $threshold];
        }

        $age = max(0, (int) $time->diffInSeconds(now(), false));
        $state = match (true) {
            ! $pollingEnabled => self::PAUSED,
            $age > $threshold => self::STALE,
            default => self::FRESH,
        };

        return ['state' => $state, 'at' => $time->toIso8601String(), 'age_seconds' => $age, 'threshold_seconds' => $threshold];
    }
}
