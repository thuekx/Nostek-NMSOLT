<?php

namespace App\Support;

/**
 * Pita redaman RX ONU (UI-B1). Ambang sama dengan `resources/js/Composables/useRxLevel.js`
 * dan alarm RX (-28 dBm): kritis ≤ -28 atau ≥ -8, peringatan ≤ -25 atau ≥ -10, selain itu baik.
 */
final class RxBand
{
    public const GOOD = 'good';

    public const WARNING = 'warning';

    public const CRITICAL = 'critical';

    public const NONE = 'none';

    /** Rentang histogram (dBm), tiap batang 1 dB; nilai di luar rentang masuk batang tepi. */
    public const MIN_DBM = -32;

    public const MAX_DBM = -6;

    /** Titik tengah rentang sehat, acuan jarak untuk daftar "RX terburuk". */
    public const HEALTHY_CENTER = -17.5;

    /**
     * TIA-598 urutan warna serat 1–12. PON port n memakai warna serat ke-n (berulang tiap 12),
     * sebagai penanda visual yang sama dengan warna tube/serat feeder di lapangan.
     */
    public const FIBER_COLORS = [
        'blue', 'orange', 'green', 'brown', 'slate', 'white',
        'red', 'black', 'yellow', 'violet', 'rose', 'aqua',
    ];

    public static function level(float|int|string|null $dbm): string
    {
        if (! is_numeric($dbm)) {
            return self::NONE;
        }

        $v = (float) $dbm;
        if ($v <= -28 || $v >= -8) {
            return self::CRITICAL;
        }
        if ($v <= -25 || $v >= -10) {
            return self::WARNING;
        }

        return self::GOOD;
    }

    public static function distance(float $dbm): float
    {
        return abs($dbm - self::HEALTHY_CENTER);
    }

    public static function fiberColor(int $port): string
    {
        return self::FIBER_COLORS[(max(1, $port) - 1) % 12];
    }

    /**
     * @return array{onus: int, online: int, offline: int, good: int, warning: int, critical: int, none: int}
     */
    public static function emptyTotals(): array
    {
        return ['onus' => 0, 'online' => 0, 'offline' => 0, 'good' => 0, 'warning' => 0, 'critical' => 0, 'none' => 0];
    }

    /**
     * @param  array<int, float|int|string>  $values
     * @return array{bins: list<array{dbm: int, count: int, level: string}>, total: int, good: int, warning: int, critical: int, median: ?float}
     */
    public static function histogram(array $values): array
    {
        $bins = [];
        for ($d = self::MIN_DBM; $d <= self::MAX_DBM; $d++) {
            $bins[$d] = 0;
        }

        $counts = [self::GOOD => 0, self::WARNING => 0, self::CRITICAL => 0];
        $sorted = [];
        foreach ($values as $value) {
            $v = (float) $value;
            $sorted[] = $v;
            $bin = (int) max(self::MIN_DBM, min(self::MAX_DBM, floor($v)));
            $bins[$bin]++;
            $counts[self::level($v)]++;
        }

        sort($sorted);
        $n = count($sorted);
        $median = $n === 0 ? null : round($n % 2 ? $sorted[intdiv($n, 2)] : ($sorted[$n / 2 - 1] + $sorted[$n / 2]) / 2, 1);

        return [
            // Level batang diambil dari titik tengahnya, supaya warnanya cocok dengan ambang.
            'bins' => array_map(
                fn (int $dbm, int $count) => ['dbm' => $dbm, 'count' => $count, 'level' => self::level($dbm + 0.5)],
                array_keys($bins),
                array_values($bins),
            ),
            'total' => $n,
            'good' => $counts[self::GOOD],
            'warning' => $counts[self::WARNING],
            'critical' => $counts[self::CRITICAL],
            'median' => $median,
        ];
    }
}
