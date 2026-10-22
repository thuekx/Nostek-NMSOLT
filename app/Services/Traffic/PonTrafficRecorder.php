<?php

namespace App\Services\Traffic;

use App\Models\PonTrafficSample;
use Illuminate\Support\Carbon;

/**
 * TRF-1: turns two successive IF-MIB octet counter readings of each PON port into a rate.
 *
 * The previous reading is the port list of the previous poll (snmp_olts.last_test_result.ports),
 * so no extra query is needed. A rate is only written when it can be trusted:
 * - both readings have both counters (an agent without ifHCIn/OutOctets gives no row, not zero);
 * - the gap is between 30 s and 3 x the poll interval (the STALE limit, App\Support\Freshness):
 *   over a longer gap the average hides every peak, and the chart shows the gap instead;
 * - neither counter went backwards (OLT reboot or card reset): that poll is skipped.
 */
class PonTrafficRecorder
{
    public const MIN_GAP_SECONDS = 30;

    /**
     * @param  array<int, array<string, mixed>>  $previousPorts
     * @param  array<int, array<string, mixed>>  $ports
     * @return int rows written
     */
    public function record(int $oltId, int $pollIntervalMinutes, array $previousPorts, ?string $previousPolledAt, array $ports, Carbon $polledAt): int
    {
        if ($previousPolledAt === null || $previousPorts === []) {
            return 0;
        }

        $gap = Carbon::parse($previousPolledAt)->diffInSeconds($polledAt, false);
        if ($gap < self::MIN_GAP_SECONDS || $gap > max(1, $pollIntervalMinutes) * 3 * 60) {
            return 0;
        }

        $previous = [];
        foreach ($previousPorts as $port) {
            if (isset($port['if_index'])) {
                $previous[(int) $port['if_index']] = $port;
            }
        }

        $rows = [];
        foreach ($ports as $port) {
            $before = $previous[(int) ($port['if_index'] ?? -1)] ?? null;
            if ($before === null) {
                continue;
            }

            $in = self::rate($before['in_octets'] ?? null, $port['in_octets'] ?? null, $gap);
            $out = self::rate($before['out_octets'] ?? null, $port['out_octets'] ?? null, $gap);
            if ($in === null || $out === null) {
                continue;
            }

            $rows[] = [
                'snmp_olt_id' => $oltId,
                'slot' => (int) ($port['slot'] ?? 0),
                'port' => (int) ($port['port'] ?? 0),
                'in_bps' => $in,
                'out_bps' => $out,
                'polled_at' => $polledAt,
            ];
        }

        if ($rows !== []) {
            PonTrafficSample::insert($rows);
        }

        return count($rows);
    }

    /**
     * Bits per second between two Counter64 readings (decimal strings), or null when the pair
     * cannot give a trustworthy rate.
     */
    public static function rate(mixed $before, mixed $after, float $seconds): ?int
    {
        if (! self::isCounter($before) || ! self::isCounter($after) || $seconds <= 0) {
            return null;
        }

        $delta = self::delta((string) $before, (string) $after);
        if ($delta === null) {
            return null;
        }

        return (int) round($delta * 8 / $seconds);
    }

    private static function isCounter(mixed $value): bool
    {
        return is_string($value) && $value !== '' && ctype_digit($value) && strlen($value) <= 20;
    }

    /**
     * $after - $before as a float, or null when the counter went backwards (reset). Counters
     * above PHP_INT_MAX (9.2 EB, centuries of GPON traffic) are handled with bcmath when present.
     */
    private static function delta(string $before, string $after): ?float
    {
        $before = ltrim($before, '0') ?: '0';
        $after = ltrim($after, '0') ?: '0';

        if (self::fitsInt($before) && self::fitsInt($after)) {
            $delta = (int) $after - (int) $before;

            return $delta < 0 ? null : (float) $delta;
        }

        if (! function_exists('bcsub')) {
            return null;
        }

        $delta = bcsub($after, $before, 0);

        return str_starts_with($delta, '-') ? null : (float) $delta;
    }

    private static function fitsInt(string $digits): bool
    {
        $max = (string) PHP_INT_MAX;

        return strlen($digits) < strlen($max) || (strlen($digits) === strlen($max) && strcmp($digits, $max) <= 0);
    }
}
