<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Jobs\PollOltJob;
use App\Models\PonTrafficSample;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\AlarmEvaluator;
use App\Services\Snmp\GoSnmpPoller;
use App\Services\Snmp\OltSnmpClient;
use App\Services\Traffic\PonTrafficRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\SequentialOltHost;
use Tests\TestCase;

/**
 * TRF-1: PON port traffic from IF-MIB octet counters (rate, poll job, rollups, prune, chart).
 */
class PonTrafficTest extends TestCase
{
    use RefreshDatabase;
    use SequentialOltHost;

    private const PORT = ['if_index' => 268501760, 'name' => 'gpon-olt_1/1/1', 'slot' => 1, 'port' => 1, 'oper_status_code' => 1, 'oper_status' => 'up'];

    private function makeOlt(array $overrides = []): SnmpOlt
    {
        return SnmpOlt::create(array_merge([
            'name' => 'OLT-TRAFFIC',
            'vendor' => 'ZTE C320',
            'ip' => '10.30.0.'.$this->nextOltHost(),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'polling_enabled' => true,
            'poll_interval_minutes' => 5,
        ], $overrides));
    }

    public function test_rate_is_bits_per_second_and_refuses_untrustworthy_pairs(): void
    {
        // 75,000,000 bytes in 300 s = 2 Mbit/s.
        $this->assertSame(2_000_000, PonTrafficRecorder::rate('1000000000', '1075000000', 300));
        $this->assertSame(0, PonTrafficRecorder::rate('5', '5', 300));
        $this->assertNull(PonTrafficRecorder::rate('1075000000', '1000', 300), 'counter went backwards (reboot)');
        $this->assertNull(PonTrafficRecorder::rate(null, '1000', 300), 'no previous counter');
        $this->assertNull(PonTrafficRecorder::rate('', '1000', 300));
        $this->assertNull(PonTrafficRecorder::rate('12a', '1000', 300));
        $this->assertNull(PonTrafficRecorder::rate(1000, '2000', 300), 'counters are decimal strings, never numbers');
        $this->assertSame(8, PonTrafficRecorder::rate('9223372036854775806', '9223372036854775807', 1), 'PHP_INT_MAX edge');
    }

    public function test_recorder_writes_one_row_per_port_with_both_counters_and_a_sane_gap(): void
    {
        $olt = $this->makeOlt();
        $recorder = new PonTrafficRecorder;
        $now = Carbon::parse('2026-10-22T12:05:00+00:00');
        $before = [
            [...self::PORT, 'in_octets' => '1000', 'out_octets' => '2000'],
            ['if_index' => 2, 'slot' => 1, 'port' => 2, 'in_octets' => '1', 'out_octets' => '1'],
            ['if_index' => 3, 'slot' => 1, 'port' => 3],
        ];
        $after = [
            [...self::PORT, 'in_octets' => '38500', 'out_octets' => '302000'],
            ['if_index' => 2, 'slot' => 1, 'port' => 2, 'in_octets' => '', 'out_octets' => '9'],   // noSuchInstance now
            ['if_index' => 3, 'slot' => 1, 'port' => 3, 'in_octets' => '9', 'out_octets' => '9'], // no previous counter
            ['if_index' => 4, 'slot' => 1, 'port' => 4, 'in_octets' => '9', 'out_octets' => '9'], // new port
        ];

        $this->assertSame(1, $recorder->record($olt->id, 5, $before, '2026-10-22T12:00:00+00:00', $after, $now));
        $row = PonTrafficSample::sole();
        $this->assertSame([1, 1, 1000, 8000], [$row->slot, $row->port, $row->in_bps, $row->out_bps]);

        // Gaps outside 30 s .. 3 x interval (the STALE limit) give no rate at all.
        $this->assertSame(0, $recorder->record($olt->id, 5, $before, '2026-10-22T12:04:50+00:00', $after, $now));
        $this->assertSame(0, $recorder->record($olt->id, 5, $before, '2026-10-22T11:49:59+00:00', $after, $now));
        $this->assertSame(0, $recorder->record($olt->id, 5, $before, null, $after, $now));
        $this->assertSame(1, PonTrafficSample::count());
    }

    public function test_poll_job_records_traffic_from_two_successive_go_polls(): void
    {
        $olt = $this->makeOlt();
        $poller = $this->fakeGoPoller();

        Carbon::setTestNow('2026-10-22 12:00:00');
        $poller->counters = ['in_octets' => '1000000', 'out_octets' => '5000000'];
        (new PollOltJob($olt->id))->handle($this->noOnuClient(), new AlarmEvaluator, $poller);
        $this->assertSame(0, PonTrafficSample::count(), 'first poll has nothing to compare with');
        $this->assertSame('1000000', data_get($olt->fresh()->last_test_result, 'ports.0.in_octets'));

        Carbon::setTestNow('2026-10-22 12:05:00');
        $poller->counters = ['in_octets' => '38500000', 'out_octets' => '380000000'];
        (new PollOltJob($olt->id))->handle($this->noOnuClient(), new AlarmEvaluator, $poller);

        $row = PonTrafficSample::sole();
        $this->assertSame(1_000_000, $row->in_bps);   // 37.5 MB / 300 s
        $this->assertSame(10_000_000, $row->out_bps); // 375 MB / 300 s
        Carbon::setTestNow();
    }

    public function test_aggregate_builds_hourly_and_daily_rollups_and_prune_keeps_unsummarised_rows(): void
    {
        $olt = $this->makeOlt();
        Carbon::setTestNow('2026-10-22 03:30:00');
        $day = Carbon::parse('2026-10-21');
        foreach ([[0, 100, 1000], [1, 300, 3000], [60, 900, 9000]] as [$minute, $in, $out]) {
            PonTrafficSample::create(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'in_bps' => $in, 'out_bps' => $out,
                'polled_at' => $day->copy()->addMinutes($minute)]);
        }

        $this->artisan('traffic:aggregate')->assertSuccessful();

        $hours = DB::table('pon_traffic_hourly')->orderBy('bucket_hour')->get();
        $this->assertCount(2, $hours);
        $this->assertSame([200, 300, 2000, 3000, 2], [(int) $hours[0]->in_avg_bps, (int) $hours[0]->in_max_bps, (int) $hours[0]->out_avg_bps, (int) $hours[0]->out_max_bps, (int) $hours[0]->sample_count]);

        $daily = DB::table('pon_traffic_daily')->sole();
        // Weighted by samples: (200*2 + 900*1) / 3 = 433, peak = highest hourly peak.
        $this->assertSame([433, 900, 4333, 9000, 3], [(int) $daily->in_avg_bps, (int) $daily->in_max_bps, (int) $daily->out_avg_bps, (int) $daily->out_max_bps, (int) $daily->sample_count]);

        // Re-running is idempotent.
        $this->artisan('traffic:aggregate')->assertSuccessful();
        $this->assertSame(2, DB::table('pon_traffic_hourly')->count());

        // Nine days later all raw rows are past retention, but only those below the last summarised
        // hour (01:00) go: the 01:00 row and a late row in a never-summarised hour stay.
        Carbon::setTestNow('2026-10-30 03:30:00');
        PonTrafficSample::create(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'in_bps' => 1, 'out_bps' => 1,
            'polled_at' => '2026-10-21 05:00:00']);
        $this->artisan('traffic:prune')->assertSuccessful();
        $this->assertSame(2, PonTrafficSample::count(), 'rows at/after the last summarised hour stay');
        $this->assertSame(1, DB::table('pon_traffic_daily')->count());
        Carbon::setTestNow();
    }

    public function test_series_reads_raw_hourly_or_daily_by_range(): void
    {
        $olt = $this->makeOlt();
        PonTrafficSample::create(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'in_bps' => 5, 'out_bps' => 7, 'polled_at' => now()->subHours(2)]);
        DB::table('pon_traffic_hourly')->insert(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'bucket_hour' => now()->subDays(20)->startOfHour(),
            'in_avg_bps' => 50, 'in_max_bps' => 80, 'out_avg_bps' => 70, 'out_max_bps' => 90, 'sample_count' => 12]);
        DB::table('pon_traffic_daily')->insert(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'bucket_day' => now()->subDays(200)->toDateString(),
            'in_avg_bps' => 500, 'in_max_bps' => 800, 'out_avg_bps' => 700, 'out_max_bps' => 900, 'sample_count' => 288]);

        $raw = PonTrafficSample::seriesFor($olt->id, 1, 1, now()->subDays(7));
        $this->assertSame([5, 7, null], [$raw[0]['in_bps'], $raw[0]['out_bps'], $raw[0]['in_max_bps']]);

        $hourly = PonTrafficSample::seriesFor($olt->id, 1, 1, now()->subDays(30));
        $this->assertSame([50, 80], [$hourly->sole()['in_bps'], $hourly->sole()['in_max_bps']]);

        $daily = PonTrafficSample::seriesFor($olt->id, 1, 1, now()->subYear());
        $this->assertSame([700, 900], [$daily->sole()['out_bps'], $daily->sole()['out_max_bps']]);

        $this->assertCount(0, PonTrafficSample::seriesFor($olt->id, 1, 2, now()->subDays(7)), 'another port');
    }

    public function test_port_page_carries_traffic_history_counters_flag_and_range(): void
    {
        $olt = $this->makeOlt();
        $olt->forceFill(['last_polled_at' => now(), 'last_test_result' => ['ok' => true, 'ports' => [
            [...self::PORT, 'in_octets' => '1', 'out_octets' => '2'],
            ['if_index' => 9, 'slot' => 1, 'port' => 2],
        ]]])->save();
        PonTrafficSample::create(['snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'in_bps' => 5, 'out_bps' => 7, 'polled_at' => now()->subHour()]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('smartolt.port.detail', ['olt' => $olt, 'interface' => 'gpon-olt_1/1/1', 'range' => '24h']))
            ->assertInertia(fn ($page) => $page
                ->where('traffic_range', '24h')
                ->where('traffic_counters', true)
                ->where('freshness.state', 'fresh')
                ->has('traffic_history', 1)
                ->where('traffic_history.0.out_bps', 7));

        $this->actingAs($admin)
            ->get(route('smartolt.port.detail', ['olt' => $olt, 'interface' => 'gpon-olt_1/1/2', 'range' => 'bogus']))
            ->assertInertia(fn ($page) => $page
                ->where('traffic_range', '7d')
                ->where('traffic_counters', false)
                ->has('traffic_history', 0));
    }

    public function test_partner_cannot_read_another_partners_port_traffic(): void
    {
        $owner = User::factory()->partner()->create();
        $other = User::factory()->partner()->create();
        $olt = $this->makeOlt();
        $olt->forceFill(['owner_user_id' => $owner->id])->save(); // guarded attribute

        $this->actingAs($other)
            ->get(route('smartolt.port.detail', ['olt' => $olt, 'interface' => 'gpon-olt_1/1/1']))
            ->assertNotFound();
        $this->actingAs($owner)
            ->get(route('smartolt.port.detail', ['olt' => $olt, 'interface' => 'gpon-olt_1/1/1']))
            ->assertOk();
    }

    private function noOnuClient(): OltSnmpClient
    {
        return new class extends OltSnmpClient
        {
            public function __construct() {}

            public function registeredOnus(SnmpOlt $olt, ?array $ports = null, ?string $scope = null): array
            {
                return [];
            }
        };
    }

    private function fakeGoPoller(): GoSnmpPoller
    {
        return new class extends GoSnmpPoller
        {
            /** @var array<string, string> */
            public array $counters = [];

            public function enabled(): bool
            {
                return true;
            }

            public function poll(SnmpOlt $olt, bool $includeRx): array
            {
                return [
                    'ok' => true, 'driver' => 'zte', 'latency_ms' => 5, 'system' => ['sys_name' => 'ZXAN'],
                    'ports' => [[...PonTrafficTest::PORT_ROW, ...$this->counters]],
                    'onus' => [], 'rx_power' => ['ok' => false, 'error' => null],
                    'traffic' => ['ok' => true, 'count' => 1, 'error' => null], 'error' => null,
                ];
            }
        };
    }

    public const PORT_ROW = self::PORT;
}
