<?php

/**
 * NMS-07-E performance matrix: cross-OLT ONU list, global search and snapshot
 * decode on synthetic C300-shaped snapshots. Run through tests/perf/run-onu-list-bench.sh
 * (throwaway PostgreSQL container). Each mode runs in its own process so the peak
 * memory figure belongs to that mode alone.
 *
 *   php tests/perf/onu_list_bench.php seed <olts> <onus_per_olt>
 *   php tests/perf/onu_list_bench.php list|search-miss|search-hit|decode
 */

use App\Models\SnmpOlt;
use App\Services\GlobalSearchService;
use App\Services\OnuInventoryService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mode = $argv[1] ?? '';

function snapshotFor(int $olt, int $onus): array
{
    mt_srand($olt);
    $perPort = 64;
    $portOnus = [];
    for ($i = 0; $i < $onus; $i++) {
        $slot = 1 + intdiv($i, $perPort * 16);
        $port = 1 + intdiv($i, $perPort) % 16;
        $id = 1 + $i % $perPort;
        $key = "{$slot}_{$port}";
        $serial = sprintf('ZTEG%08X', mt_rand());
        $portOnus[$key]['slot'] = $slot;
        $portOnus[$key]['port'] = $port;
        $portOnus[$key]['refreshed_at'] = '2026-10-22T10:00:00+07:00';
        $portOnus[$key]['onus'][] = [
            'onu_id' => $id, 'slot' => $slot, 'port' => $port,
            'if_index' => 285278208 + $slot * 65536 + $port * 256 + $id,
            'interface' => "gpon-onu_1/{$slot}/{$port}:{$id}",
            'serial_number' => $serial, 'type_name' => 'F670L',
            'name' => "{$serial}_Pelanggan Sintetis {$i}", 'description' => "Jl. Contoh No. {$i}",
            'admin_state' => 'enable', 'phase_state' => mt_rand(0, 9) ? 'working' : 'LOS',
            'online' => (bool) mt_rand(0, 9), 'last_down_cause' => 'dying-gasp',
            'rx_power_dbm' => -(mt_rand(1500, 2900) / 100), 'rx_power_label' => 'normal',
            // Snapshot-only detail the list never shows (keeps the snapshot size realistic).
            'distance_m' => mt_rand(100, 9000), 'firmware' => 'V9.0.10P1N12', 'uptime' => '12d 03:04:05',
        ];
    }
    foreach ($portOnus as &$entry) {
        $entry['count'] = count($entry['onus']);
    }

    return ['ok' => true, 'system' => ['sys_descr' => 'ZTE ZXA10 C300'], 'port_onus' => $portOnus];
}

function report(string $name, float $start, array $extra = []): void
{
    $ms = (microtime(true) - $start) * 1000;
    $mb = memory_get_peak_usage(true) / 1048576;
    $kv = implode(' ', array_map(fn ($k, $v) => "{$k}={$v}", array_keys($extra), $extra));
    printf("BENCH %s ms=%.1f peak_mb=%.1f %s\n", $name, $ms, $mb, $kv);
}

switch ($mode) {
    case 'seed':
        [$olts, $onus] = [(int) $argv[2], (int) $argv[3]];
        for ($o = 1; $o <= $olts; $o++) {
            $olt = SnmpOlt::query()->withoutGlobalScopes()->create([
                'name' => "OLT-BENCH-{$o}", 'vendor' => 'ZTE C300', 'ip' => "10.99.0.{$o}",
                'snmp_port' => 161, 'snmp_read_community' => 'public', 'snmp_version' => 'v2c',
                'last_test_result' => snapshotFor($o, $onus),
            ]);
            echo "PROGRESS {$o}/{$olts} snapshot_kb=".intdiv(strlen((string) $olt->getRawOriginal('last_test_result')), 1024)."\n";
        }
        echo 'SEED olts='.$olts.' onus='.($olts * $onus)."\n";
        break;

    case 'list':
        // As the ONU monitor page and the API load it: full OLT rows, then collect().
        $t = microtime(true);
        $olts = SnmpOlt::query()->withoutGlobalScopes()->orderBy('name')->get();
        $result = app(OnuInventoryService::class)->collect($olts);
        $json = strlen((string) json_encode($result['onus']));
        report($mode, $t, ['onus' => count($result['onus']), 'payload_kb' => intdiv($json, 1024)]);
        break;

    case 'search-miss':
        // Worst case for the global search: a needle that matches nothing scans every snapshot.
        $t = microtime(true);
        $n = count(app(GlobalSearchService::class)->search('zzqq-tidak-ada', 10));
        report($mode, $t, ['hits' => $n]);
        break;

    case 'search-hit':
        // Typical case: a serial prefix that matches early (stops at the limit).
        $t = microtime(true);
        $n = count(app(GlobalSearchService::class)->search('zteg', 10));
        report($mode, $t, ['hits' => $n]);
        break;

    case 'decode':
        // Cost of loading and decoding every snapshot, without building the list.
        $t = microtime(true);
        foreach (SnmpOlt::query()->withoutGlobalScopes()->get() as $olt) {
            $olt->last_test_result;
        }
        report($mode, $t);
        break;

    default:
        fwrite(STDERR, "unknown mode\n");
        exit(2);
}
