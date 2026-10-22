<?php

namespace App\Console\Commands;

use App\Models\PonTrafficSample;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TRF-1 retention. Raw rows are only dropped below the last summarised hour and hourly rows only
 * below the last summarised day, so a stalled rollup makes the tables grow instead of losing
 * history (same rule as optical:prune-rx).
 */
class PrunePonTrafficCommand extends Command
{
    protected $signature = 'traffic:prune';

    protected $description = 'Prune riwayat trafik PON (mentah, per jam, per hari) yang melewati masa retensi';

    public function handle(): int
    {
        $config = (array) config('services.snmp_poller', []);
        $rawDays = max(1, (int) ($config['traffic_sample_retention_days'] ?? 8));
        $hourlyDays = max(1, (int) ($config['traffic_hourly_retention_days'] ?? 100));
        $dailyDays = max(1, (int) ($config['traffic_daily_retention_days'] ?? 1095));

        $lastHour = DB::table('pon_traffic_hourly')->max('bucket_hour');
        $lastDay = DB::table('pon_traffic_daily')->max('bucket_day');

        $raw = $lastHour === null ? 0 : PonTrafficSample::query()
            ->where('polled_at', '<', min(now()->subDays($rawDays), Carbon::parse($lastHour)))
            ->delete();
        $hourly = $lastDay === null ? 0 : DB::table('pon_traffic_hourly')
            ->where('bucket_hour', '<', min(now()->subDays($hourlyDays), Carbon::parse($lastDay)))
            ->delete();
        $daily = DB::table('pon_traffic_daily')
            ->where('bucket_day', '<', now()->subDays($dailyDays)->toDateString())
            ->delete();

        $this->info("TRAFFIC prune raw={$raw} hourly={$hourly} daily={$daily}");

        return self::SUCCESS;
    }
}
