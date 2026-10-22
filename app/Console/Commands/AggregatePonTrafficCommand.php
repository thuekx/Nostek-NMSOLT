<?php

namespace App\Console\Commands;

use App\Models\PonTrafficSample;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TRF-1: roll PON traffic up per hour (from raw rows) and per day (from the hourly rows).
 *
 * Same rules as the RX rollups (optical:aggregate-rx): the running hour/day is never summarised,
 * the last summarised bucket is redone (late samples), hours without samples are skipped so a
 * polling gap cannot stall it, and prune (traffic:prune) only drops what is already summarised.
 */
class AggregatePonTrafficCommand extends Command
{
    protected $signature = 'traffic:aggregate {--max-hours=48 : Batas jam per eksekusi}';

    protected $description = 'Ringkas pon_traffic_samples per jam (rata-rata + puncak) dan per hari';

    public function handle(): int
    {
        $hours = $this->aggregateHours(max(1, (int) $this->option('max-hours')));
        $days = $this->aggregateDays();

        $this->info("TRAFFIC aggregate hours={$hours} days={$days}");

        return self::SUCCESS;
    }

    private function aggregateHours(int $maxHours): int
    {
        $end = now()->startOfHour();
        $last = DB::table('pon_traffic_hourly')->max('bucket_hour');
        $oldest = $last ?? PonTrafficSample::query()->min('polled_at');
        if ($oldest === null) {
            return 0;
        }

        $hour = Carbon::parse($oldest)->startOfHour();
        $done = 0;
        while ($hour < $end && $done < $maxHours) {
            $next = $hour->copy()->addHour();
            $this->upsert('pon_traffic_hourly', 'bucket_hour', $hour->toDateTimeString(), PonTrafficSample::query()
                ->selectRaw('snmp_olt_id, slot, port, avg(in_bps) as in_avg, max(in_bps) as in_max,'
                    .' avg(out_bps) as out_avg, max(out_bps) as out_max, count(*) as n')
                ->where('polled_at', '>=', $hour)
                ->where('polled_at', '<', $next)
                ->groupBy('snmp_olt_id', 'slot', 'port')
                ->get());
            $done++;

            $nextSample = PonTrafficSample::query()->where('polled_at', '>=', $next)->min('polled_at');
            if ($nextSample === null) {
                break;
            }
            $hour = max($next, Carbon::parse($nextSample)->startOfHour());
        }

        return $done;
    }

    private function aggregateDays(): int
    {
        $end = now()->startOfDay();
        $last = DB::table('pon_traffic_daily')->max('bucket_day');
        $oldest = $last ?? DB::table('pon_traffic_hourly')->min('bucket_hour');
        if ($oldest === null) {
            return 0;
        }

        $done = 0;
        for ($day = Carbon::parse($oldest)->startOfDay(); $day < $end && $done < 31; $day->addDay()) {
            $next = $day->copy()->addDay();
            // The daily average is weighted by samples, so a half-polled hour does not count
            // as much as a full one; the peak is the highest hourly peak.
            $this->upsert('pon_traffic_daily', 'bucket_day', $day->toDateString(), DB::table('pon_traffic_hourly')
                ->selectRaw('snmp_olt_id, slot, port, sum(in_avg_bps * sample_count) / sum(sample_count) as in_avg,'
                    .' max(in_max_bps) as in_max, sum(out_avg_bps * sample_count) / sum(sample_count) as out_avg,'
                    .' max(out_max_bps) as out_max, sum(sample_count) as n')
                ->where('bucket_hour', '>=', $day)
                ->where('bucket_hour', '<', $next)
                ->groupBy('snmp_olt_id', 'slot', 'port')
                ->get());
            $done++;
        }

        return $done;
    }

    /**
     * @param  iterable<object>  $groups
     */
    private function upsert(string $table, string $bucket, string $value, iterable $groups): void
    {
        $rows = [];
        foreach ($groups as $row) {
            $rows[] = [
                'snmp_olt_id' => (int) $row->snmp_olt_id,
                'slot' => (int) $row->slot,
                'port' => (int) $row->port,
                $bucket => $value,
                'in_avg_bps' => (int) round((float) $row->in_avg),
                'in_max_bps' => (int) $row->in_max,
                'out_avg_bps' => (int) round((float) $row->out_avg),
                'out_max_bps' => (int) $row->out_max,
                'sample_count' => (int) $row->n,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->upsert(
                $chunk,
                ['snmp_olt_id', 'slot', 'port', $bucket],
                ['in_avg_bps', 'in_max_bps', 'out_avg_bps', 'out_max_bps', 'sample_count'],
            );
        }
    }
}
