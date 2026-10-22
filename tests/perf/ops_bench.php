<?php

/**
 * NMS-07-E performance matrix, second part: alarm page, alarm engine and optical RX charts
 * on synthetic data. Run through tests/perf/run-ops-bench.sh (throwaway PostgreSQL
 * container). Each mode runs in its own process so the peak memory figure belongs to it.
 *
 *   php tests/perf/ops_bench.php seed <onus> <rx_onus> <cleared_alarms>
 *   php tests/perf/ops_bench.php alarm-page|alarm-page-all|alarm-eval-steady|alarm-eval-burst
 *   php tests/perf/ops_bench.php rx-24h|rx-7d|rx-30d|rx-90d|rx-1y|rx-3y
 */

use App\Enums\UserRole;
use App\Models\OnuRxSample;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\AlarmEvaluator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';

// BENCH_SLOW_MS=n prints every query slower than n ms (where the time goes).
if (($slow = (float) getenv('BENCH_SLOW_MS')) > 0) {
    DB::listen(function ($query) use ($slow) {
        if ($query->time >= $slow) {
            printf("SLOW %.1fms %s\n", $query->time, substr(preg_replace('/\s+/', ' ', $query->sql), 0, 220));
        }
    });
}

/** C300-shaped snapshot: 16 ports per slot, 64 ONUs per port; $offline of them down (LOS). */
function opsSnapshot(int $onus, int $offline = 0): array
{
    mt_srand(7);
    $ports = [];
    $portOnus = [];
    for ($i = 0; $i < $onus; $i++) {
        $slot = 1 + intdiv($i, 64 * 16);
        $port = 1 + intdiv($i, 64) % 16;
        $id = 1 + $i % 64;
        $key = "{$slot}_{$port}";
        $down = $i < $offline;
        $ports[$key] = ['slot' => $slot, 'port' => $port, 'name' => "gpon_1/{$slot}/{$port}", 'oper_status' => 'up', 'admin_status' => 'up'];
        $portOnus[$key]['ok'] = true;
        $portOnus[$key]['slot'] = $slot;
        $portOnus[$key]['port'] = $port;
        $portOnus[$key]['refreshed_at'] = now()->toIso8601String();
        $portOnus[$key]['onus'][] = [
            'onu_id' => $id, 'slot' => $slot, 'port' => $port,
            'interface' => "gpon-onu_1/{$slot}/{$port}:{$id}",
            'serial_number' => sprintf('ZTEG%08X', mt_rand()), 'name' => "Pelanggan Sintetis {$i}",
            'admin_state' => 'enable', 'phase_state' => $down ? 'LOS' : 'working',
            'online' => ! $down, 'rx_power_dbm' => -(mt_rand(1500, 2600) / 100),
        ];
    }
    foreach ($portOnus as $key => $entry) {
        $portOnus[$key]['count'] = count($entry['onus']);
    }

    return ['ok' => true, 'ports' => array_values($ports), 'port_onus' => $portOnus];
}

function bench(string $mode, callable $fn, string $extra = ''): void
{
    gc_collect_cycles();
    $t = hrtime(true);
    $out = $fn();
    $ms = (hrtime(true) - $t) / 1e6;
    printf("BENCH %s ms=%.1f peak_mb=%.1f %s%s\n", $mode, $ms, memory_get_peak_usage(true) / 1048576, $extra, is_string($out) ? $out : '');
}

if ($mode === 'seed') {
    [$onus, $rxOnus, $cleared] = [(int) $argv[2], (int) $argv[3], (int) $argv[4]];
    $olt = SnmpOlt::query()->withoutGlobalScopes()->create([
        'name' => 'OLT-BENCH', 'vendor' => 'ZTE C320', 'ip' => '10.99.0.1', 'snmp_port' => 161,
        'snmp_read_community' => 'public', 'snmp_version' => 'v2c', 'last_polled_at' => now(),
        'last_test_result' => opsSnapshot($onus),
    ]);
    User::query()->create(['name' => 'Bench Admin', 'email' => 'bench@example.test', 'role' => UserRole::Admin,
        'password' => bcrypt(bin2hex(random_bytes(16))), 'email_verified_at' => now()]);

    // Cleared alarm history (what history:prune keeps for 180 days) plus 500 active alarms.
    DB::statement("insert into alarm_events (snmp_olt_id, signature, type, severity, status, scope, slot, port, onu_id, message, first_seen_at, last_seen_at, cleared_at, created_at, updated_at)
        select ?, 'onu:los:' || g, 'onu_los', 'major', 'cleared', 'onu', 1 + (g % 2), 1 + (g % 16), 1 + (g % 64), 'ONU LOS sintetis ' || g,
               now() - (g % 180) * interval '1 day', now() - (g % 180) * interval '1 day', now() - (g % 180) * interval '1 day' + interval '1 hour', now(), now()
        from generate_series(1, ?) g", [$olt->id, $cleared]);
    DB::statement("insert into alarm_events (snmp_olt_id, signature, type, severity, status, scope, slot, port, onu_id, message, first_seen_at, last_seen_at, created_at, updated_at)
        select ?, 'onu:offline:active' || g, 'onu_offline', 'minor', 'active', 'onu', 1, 1 + (g % 16), 1 + (g % 64), 'ONU offline sintetis ' || g, now(), now(), now(), now()
        from generate_series(1, 500) g", [$olt->id]);

    // Optical history for $rxOnus ONUs: raw 3 days at 5 min, hourly 400 days, daily 3 years.
    $onuSet = 'select 1 + (o / 1024) as slot, 1 + (o / 64) % 16 as port, 1 + o % 64 as onu_id from generate_series(0, ? - 1) o';
    DB::statement("insert into onu_rx_samples (snmp_olt_id, slot, port, onu_id, rx_power_dbm, polled_at)
        select ?, s.slot, s.port, s.onu_id, -20 - random() * 5, now() - m * interval '5 minutes'
        from ({$onuSet}) s, generate_series(0, 863) m", [$olt->id, $rxOnus]);
    echo 'PROGRESS seed raw done'.PHP_EOL;
    DB::statement("insert into onu_rx_hourly (snmp_olt_id, slot, port, onu_id, bucket_hour, rx_min_dbm, rx_avg_dbm, rx_max_dbm, sample_count)
        select ?, s.slot, s.port, s.onu_id, date_trunc('hour', now()) - h * interval '1 hour', -25, -22, -20, 12
        from ({$onuSet}) s, generate_series(1, 9600) h", [$olt->id, $rxOnus]);
    echo 'PROGRESS seed hourly done'.PHP_EOL;
    DB::statement("insert into onu_rx_daily (snmp_olt_id, slot, port, onu_id, bucket_day, rx_min_dbm, rx_avg_dbm, rx_max_dbm, sample_count)
        select ?, s.slot, s.port, s.onu_id, current_date - d, -25, -22, -20, 288
        from ({$onuSet}) s, generate_series(1, 1095) d", [$olt->id, $rxOnus]);
    DB::statement('analyze');
    echo 'PROGRESS seed daily done'.PHP_EOL;
    exit(0);
}

$olt = SnmpOlt::query()->withoutGlobalScopes()->where('name', 'OLT-BENCH')->firstOrFail();

if (str_starts_with($mode, 'alarm-page')) {
    $user = User::query()->where('email', 'bench@example.test')->firstOrFail();
    $app['auth']->guard('web')->setUser($user);
    $uri = $mode === 'alarm-page-all' ? '/alarms?status=all' : '/alarms';
    // One warm-up request first: the measured one is a warm worker (views, routes, config
    // loaded), as under PHP-FPM, not the process start-up.
    $app->make(HttpKernel::class)->handle(Request::create($uri, 'GET'));
    bench($mode, function () use ($app, $uri) {
        $response = $app->make(HttpKernel::class)->handle(Request::create($uri, 'GET'));

        return 'status='.$response->getStatusCode().' payload_kb='.intdiv(strlen((string) $response->getContent()), 1024);
    });
    exit(0);
}

if (str_starts_with($mode, 'alarm-eval')) {
    $onus = array_sum(array_map(fn ($e) => count($e['onus']), $olt->last_test_result['port_onus']));
    $previous = opsSnapshot($onus);
    // burst: 10 % of the ONUs go LOS in this poll (a feeder cut); steady: nothing changes.
    $current = $mode === 'alarm-eval-burst' ? opsSnapshot($onus, intdiv($onus, 10)) : $previous;
    $evaluator = $app->make(AlarmEvaluator::class);
    DB::beginTransaction();
    // Warm-up poll on the healthy snapshot: settles the seeded active alarms first, so the
    // measured poll is a real steady state (or a real burst), not a mass clear.
    $olt->forceFill(['last_test_result' => $previous])->saveQuietly();
    $evaluator->evaluate($olt, $previous);
    $olt->forceFill(['last_test_result' => $current])->saveQuietly();
    bench($mode, fn () => 'result='.json_encode($evaluator->evaluate($olt, $previous)), "onus={$onus} ");
    DB::rollBack();
    exit(0);
}

if (str_starts_with($mode, 'rx-')) {
    $since = match ($mode) {
        'rx-24h' => now()->subDay(), 'rx-7d' => now()->subDays(7), 'rx-30d' => now()->subDays(30),
        'rx-90d' => now()->subDays(90), 'rx-1y' => now()->subYear(), 'rx-3y' => now()->subYears(3),
    };
    bench($mode, fn () => 'points='.OnuRxSample::seriesFor($olt->id, 1, 7, 33, $since)->count());
    exit(0);
}

fwrite(STDERR, "unknown mode {$mode}\n");
exit(2);
