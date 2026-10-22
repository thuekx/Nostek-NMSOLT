<?php

namespace App\Console\Commands;

use App\Models\OnuRxDaily;
use App\Models\OnuRxHourly;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Padatkan `onu_rx_hourly` menjadi satu baris per ONU per hari (NMS-07-B2).
 *
 * Rata-rata harian ditimbang jumlah sampel tiap jam (bukan rata-rata dari rata-rata),
 * min/max diambil dari min/max per jam. Hari berjalan dilewati; hari terakhir yang sudah
 * terangkum diproses ulang karena jam terakhirnya bisa baru masuk sesudahnya.
 */
class AggregateOnuRxDailyCommand extends Command
{
    protected $signature = 'optical:aggregate-rx-daily
        {--days= : Berapa hari ke belakang yang diproses (default: sejak hari terakhir yang sudah terangkum)}
        {--max-days= : Batas atas hari per eksekusi (default 31; saat --days diberikan, defaultnya mengikuti --days)}';

    protected $description = 'Ringkas onu_rx_hourly menjadi rata-rata/min/max per hari di onu_rx_daily';

    public function handle(): int
    {
        $end = now()->startOfDay();
        $start = $this->resolveStart($end);

        if ($start === null || $start >= $end) {
            $this->info('Tidak ada hari baru untuk diringkas.');

            return self::SUCCESS;
        }

        $maxDays = $this->option('max-days') !== null
            ? max(1, (int) $this->option('max-days'))
            : ($this->option('days') !== null ? max(1, (int) $this->option('days')) : 31);
        $days = 0;
        $rows = 0;

        $day = $start->copy();
        while ($day < $end && $days < $maxDays) {
            $rows += $this->aggregateDay($day);
            $days++;

            // Jump over days without hourly data (OLT offline, gap before the first poll):
            // otherwise a gap longer than --max-days would stall the scheduled run for ever,
            // because it resumes from the last day that produced a row.
            $nextHour = OnuRxHourly::query()->where('bucket_hour', '>=', $day->copy()->addDay())->min('bucket_hour');
            if ($nextHour === null) {
                break;
            }
            $day = max($day->copy()->addDay(), Carbon::parse($nextHour)->startOfDay());
        }

        $this->info("Meringkas {$days} hari → {$rows} baris di onu_rx_daily (mulai {$start->toDateString()}).");

        return self::SUCCESS;
    }

    private function resolveStart(Carbon $end): ?Carbon
    {
        if ($this->option('days') !== null) {
            return $end->copy()->subDays(max(1, (int) $this->option('days')));
        }

        $last = OnuRxDaily::query()->max('bucket_day');
        if ($last !== null) {
            return Carbon::parse($last)->startOfDay();
        }

        $oldest = OnuRxHourly::query()->min('bucket_hour');

        return $oldest === null ? null : Carbon::parse($oldest)->startOfDay();
    }

    private function aggregateDay(Carbon $day): int
    {
        $next = $day->copy()->addDay();
        $written = 0;

        OnuRxHourly::query()
            ->selectRaw('snmp_olt_id, slot, port, onu_id, max(serial_number) as serial_number,'
                .' min(rx_min_dbm) as rx_min, sum(rx_avg_dbm * sample_count) as rx_weighted,'
                .' max(rx_max_dbm) as rx_max, sum(sample_count) as sample_count')
            ->where('bucket_hour', '>=', $day)
            ->where('bucket_hour', '<', $next)
            ->groupBy('snmp_olt_id', 'slot', 'port', 'onu_id')
            ->orderBy('snmp_olt_id')
            ->orderBy('slot')
            ->orderBy('port')
            ->orderBy('onu_id')
            ->cursor()
            ->chunk(500)
            ->each(function ($group) use ($day, &$written) {
                $payload = $group
                    ->filter(fn ($row) => (int) $row->sample_count > 0)
                    ->map(fn ($row) => [
                        'snmp_olt_id' => (int) $row->snmp_olt_id,
                        'slot' => (int) $row->slot,
                        'port' => (int) $row->port,
                        'onu_id' => (int) $row->onu_id,
                        'serial_number' => $row->serial_number,
                        'bucket_day' => $day->toDateString(),
                        'rx_min_dbm' => round((float) $row->rx_min, 2),
                        'rx_avg_dbm' => round((float) $row->rx_weighted / (int) $row->sample_count, 2),
                        'rx_max_dbm' => round((float) $row->rx_max, 2),
                        'sample_count' => (int) $row->sample_count,
                    ])->values()->all();

                if ($payload === []) {
                    return;
                }

                DB::table('onu_rx_daily')->upsert(
                    $payload,
                    ['snmp_olt_id', 'slot', 'port', 'onu_id', 'bucket_day'],
                    ['serial_number', 'rx_min_dbm', 'rx_avg_dbm', 'rx_max_dbm', 'sample_count'],
                );

                $written += count($payload);
            });

        return $written;
    }
}
