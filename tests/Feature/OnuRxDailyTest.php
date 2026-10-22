<?php

namespace Tests\Feature;

use App\Models\OnuRxDaily;
use App\Models\OnuRxHourly;
use App\Models\OnuRxSample;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\ZteOnuDetailService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NMS-07-B2: daily RX rollup (owner NMS-AI-Q5 A: hourly 400 days, daily 3 years).
 */
class OnuRxDailyTest extends TestCase
{
    use RefreshDatabase;

    private function makeOlt(): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => 'OLT-RX-HARIAN',
            'vendor' => 'ZTE C320',
            'ip' => '10.21.0.5',
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
        ]);
    }

    private function hour(SnmpOlt $olt, $bucket, float $min, float $avg, float $max, int $count, int $onuId = 1): void
    {
        OnuRxHourly::create([
            'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => $onuId,
            'serial_number' => 'ZTEG'.$onuId, 'bucket_hour' => $bucket,
            'rx_min_dbm' => $min, 'rx_avg_dbm' => $avg, 'rx_max_dbm' => $max, 'sample_count' => $count,
        ]);
    }

    public function test_daily_rollup_weights_the_average_by_sample_count(): void
    {
        $olt = $this->makeOlt();
        $day = now()->subDays(3)->startOfDay();
        $this->hour($olt, $day->copy()->addHours(1), -22.0, -21.0, -20.0, 1);
        $this->hour($olt, $day->copy()->addHours(2), -25.0, -24.0, -23.0, 3);
        $this->hour($olt, $day->copy()->addHours(3), -19.0, -19.0, -19.0, 2, onuId: 2);
        // Today is still running: never summarised.
        $this->hour($olt, now()->startOfDay()->addMinutes(5), -30.0, -30.0, -30.0, 1);

        $this->artisan('optical:aggregate-rx-daily', ['--days' => 5])->assertSuccessful();

        $row = OnuRxDaily::where('onu_id', 1)->sole();
        $this->assertSame($day->toDateString(), $row->bucket_day->toDateString());
        $this->assertSame(4, $row->sample_count);
        $this->assertEqualsWithDelta(-25.0, $row->rx_min_dbm, 0.001);
        $this->assertEqualsWithDelta(-23.25, $row->rx_avg_dbm, 0.001, '(-21*1 + -24*3) / 4');
        $this->assertEqualsWithDelta(-20.0, $row->rx_max_dbm, 0.001);
        $this->assertSame(2, OnuRxDaily::count(), 'one row per ONU per day, today skipped');

        // Re-running is idempotent.
        $this->artisan('optical:aggregate-rx-daily', ['--days' => 5])->assertSuccessful();
        $this->assertSame(2, OnuRxDaily::count());
    }

    public function test_scheduled_run_resumes_from_the_last_summarised_day(): void
    {
        $olt = $this->makeOlt();
        $this->hour($olt, now()->subDays(40)->startOfDay()->addHour(), -20.0, -20.0, -20.0, 1);
        $this->hour($olt, now()->subDay()->startOfDay()->addHour(), -21.0, -21.0, -21.0, 1);

        // A 38-day gap without data is longer than the 31-day batch; the run jumps it.
        $this->artisan('optical:aggregate-rx-daily')->assertSuccessful();
        $this->assertSame(2, OnuRxDaily::count());
        $this->assertSame(now()->subDay()->toDateString(), OnuRxDaily::max('bucket_day'));

        // Batch limit: with --max-days=1 only the oldest day is (re)processed.
        OnuRxDaily::query()->delete();
        $this->artisan('optical:aggregate-rx-daily', ['--max-days' => 1])->assertSuccessful();
        $this->assertSame(1, OnuRxDaily::count());
    }

    public function test_hourly_aggregation_jumps_a_polling_gap_longer_than_its_batch(): void
    {
        $olt = $this->makeOlt();
        $sample = fn ($at) => OnuRxSample::create([
            'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 1,
            'serial_number' => 'ZTEG1', 'rx_power_dbm' => -20.0, 'polled_at' => $at,
        ]);
        // Poller down for five days (longer than the 48-hour batch), then back.
        $sample(now()->subDays(6)->startOfHour()->addMinutes(5));
        $this->artisan('optical:aggregate-rx', ['--hours' => 24 * 7])->assertSuccessful();
        $sample(now()->subHours(2)->startOfHour()->addMinutes(5));

        $this->artisan('optical:aggregate-rx')->assertSuccessful();

        $this->assertSame(2, OnuRxHourly::count(), 'the scheduled run reaches the hour after the gap');
    }

    public function test_hourly_prune_keeps_hours_not_yet_rolled_up_and_daily_has_its_own_retention(): void
    {
        config([
            'services.snmp_poller.rx_hourly_retention_days' => 45,
            'services.snmp_poller.rx_daily_retention_days' => 1095,
        ]);
        $olt = $this->makeOlt();
        $old = now()->subDays(60)->startOfDay()->addHour();
        $this->hour($olt, $old, -20.0, -20.0, -20.0, 1);
        $this->hour($olt, now()->subDay()->startOfDay()->addHour(), -20.0, -20.0, -20.0, 1);

        // Daily rollup has not run yet: the 60-day-old hour must survive.
        $this->artisan('optical:prune-rx')->assertSuccessful();
        $this->assertSame(2, OnuRxHourly::count());

        $this->artisan('optical:aggregate-rx-daily', ['--days' => 61])->assertSuccessful();
        OnuRxDaily::create([
            'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 9, 'bucket_day' => now()->subDays(1200)->toDateString(),
            'rx_min_dbm' => -20, 'rx_avg_dbm' => -20, 'rx_max_dbm' => -20, 'sample_count' => 1,
        ]);

        $this->artisan('optical:prune-rx')->assertSuccessful();
        $this->assertSame(1, OnuRxHourly::count(), 'rolled-up hour past 45 days is pruned');
        $this->assertSame(2, OnuRxDaily::where('onu_id', 1)->count());
        $this->assertSame(0, OnuRxDaily::where('onu_id', 9)->count(), 'daily rows past 3 years are pruned');
    }

    public function test_long_ranges_are_served_from_the_daily_rollup(): void
    {
        $olt = $this->makeOlt();
        OnuRxDaily::create([
            'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 1, 'bucket_day' => now()->subDays(500)->toDateString(),
            'rx_min_dbm' => -24, 'rx_avg_dbm' => -23, 'rx_max_dbm' => -22, 'sample_count' => 24,
        ]);
        $this->hour($olt, now()->subDays(20)->startOfHour(), -21.0, -21.0, -21.0, 1);

        $threeYears = OnuRxSample::seriesFor($olt->id, 1, 1, 1, now()->subYears(3));
        $this->assertCount(1, $threeYears);
        $this->assertEqualsWithDelta(-23.0, $threeYears[0]['rx_power_dbm'], 0.001);
        $this->assertEqualsWithDelta(-24.0, $threeYears[0]['rx_min_dbm'], 0.001);

        // 30 days stays on the hourly table.
        $this->assertEqualsWithDelta(-21.0, OnuRxSample::seriesFor($olt->id, 1, 1, 1, now()->subDays(30))[0]['rx_power_dbm'], 0.001);
        // 90 days stays on the hourly table too, although the caller computes $since a moment
        // before seriesFor() runs (TRF-1 found 90d tipping over to the daily rollup).
        $since = now()->subDays(90);
        usleep(2000);
        $this->assertEqualsWithDelta(-21.0, OnuRxSample::seriesFor($olt->id, 1, 1, 1, $since)[0]['rx_power_dbm'], 0.001);
    }

    public function test_onu_detail_accepts_the_long_ranges_and_rejects_unknown_ones(): void
    {
        $olt = $this->makeOlt();
        $user = User::factory()->admin()->create();
        // No live CLI session in tests.
        $this->app->instance(ZteOnuDetailService::class, new class extends ZteOnuDetailService
        {
            public function __construct() {}

            public function fetch(SnmpOlt $olt, int $slot, int $port, int $onuId): array
            {
                return ['ok' => false, 'groups' => ['identity' => [], 'state' => [], 'optical' => [], 'last_event' => [], 'all' => []], 'raw' => '', 'error' => null];
            }
        });

        foreach (['90d', '1y', '3y'] as $range) {
            $this->actingAs($user)
                ->get(route('smartolt.onu.detail', ['olt' => $olt->id, 'slot' => 1, 'port' => 1, 'onuId' => 1, 'range' => $range]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('range', $range));
        }

        $this->actingAs($user)
            ->get(route('smartolt.onu.detail', ['olt' => $olt->id, 'slot' => 1, 'port' => 1, 'onuId' => 1, 'range' => '10y']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('range', '7d'));
    }

    public function test_daily_rollup_is_scheduled_after_the_hourly_run(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'optical:aggregate-rx-daily'));

        $this->assertCount(1, $events);
        $this->assertSame('25 0 * * *', $events->first()->expression);
    }
}
