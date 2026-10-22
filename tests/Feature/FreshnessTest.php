<?php

namespace Tests\Feature;

use App\Models\OnuMapPin;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\Dashboard\DashboardStatsService;
use App\Services\Map\OnuMapPayloadService;
use App\Services\OnuInventoryService;
use App\Support\Freshness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NMS-07-D-2: status honesty. A snapshot older than 3 poll intervals is STALE, a never-polled
 * OLT is UNKNOWN, polling off is PAUSED; only FRESH may be shown green.
 */
class FreshnessTest extends TestCase
{
    use RefreshDatabase;

    private int $nextIp = 1;

    private function olt(array $attributes = []): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => 'OLT-SEGAR-'.$this->nextIp,
            'vendor' => 'ZTE C320',
            'ip' => '10.40.0.'.(++$this->nextIp),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'polling_enabled' => true,
            'poll_interval_minutes' => 5,
            'last_test_result' => ['ok' => true, 'port_onus' => ['1_1' => [
                'ok' => true, 'slot' => 1, 'port' => 1, 'count' => 1,
                'refreshed_at' => now()->subMinutes(2)->toIso8601String(),
                'onus' => [['onu_id' => 1, 'slot' => 1, 'port' => 1, 'online' => true]],
            ]]],
            ...$attributes,
        ]);
    }

    public function test_classification_at_the_three_interval_boundary(): void
    {
        $this->freezeTime();

        $this->assertSame(Freshness::FRESH, Freshness::of(now()->subMinutes(15), 5)['state']);
        $this->assertSame(Freshness::STALE, Freshness::of(now()->subMinutes(15)->subSecond(), 5)['state']);
        $this->assertSame(900, Freshness::of(now(), 5)['threshold_seconds']);
        $this->assertSame(Freshness::UNKNOWN, Freshness::of(null, 5)['state']);
        $this->assertSame(Freshness::UNKNOWN, Freshness::of('not a date', 5)['state']);
        $this->assertSame(Freshness::PAUSED, Freshness::of(now(), 5, pollingEnabled: false)['state']);
        $this->assertSame(Freshness::STALE, Freshness::of(now()->subMinutes(4), 1)['state'], 'interval 1 min → stale after 3 min');
        $this->assertSame(120, Freshness::of(now()->subMinutes(2)->toIso8601String(), 5)['age_seconds']);
    }

    public function test_dashboard_never_counts_a_stale_or_unpolled_olt_as_up(): void
    {
        $this->olt(['last_polled_at' => now()->subMinutes(3)]);
        $this->olt(['last_polled_at' => now()->subHours(2)]);
        $this->olt(['last_polled_at' => null]);
        $this->olt(['last_polled_at' => now()->subMinute(), 'polling_enabled' => false]);

        $rows = app(DashboardStatsService::class)->oltInventoryList();

        $this->assertSame([1, 0, 0, 0], $rows->pluck('up')->all());
        $this->assertSame([0, 0, 0, 0], $rows->pluck('down')->all());
        $this->assertSame([0, 1, 1, 1], $rows->pluck('not_fresh')->all());
        $this->assertSame(['fresh', 'stale', 'unknown', 'paused'], $rows->pluck('freshness.state')->all());
        $this->assertTrue($rows->every(fn ($r) => $r['reachable']), 'the snapshot itself still says ok');
    }

    public function test_dashboard_cards_do_not_count_stale_olts_or_their_onus_as_online(): void
    {
        $this->olt(['last_polled_at' => now()->subMinutes(3)]);
        $this->olt(['last_polled_at' => now()->subHours(2)]);

        $legacy = app(DashboardStatsService::class)->statCards();
        $this->assertSame(2, $legacy['olt']['online'], 'API v1/Telegram keep legacy counts until NMS-08');
        $this->assertArrayNotHasKey('not_fresh', $legacy['olt']);

        $cards = app(DashboardStatsService::class)->statCards(freshOnly: true);

        $this->assertSame(['total' => 2, 'online' => 1, 'offline' => 0, 'not_fresh' => 1], array_intersect_key($cards['olt'], array_flip(['total', 'online', 'offline', 'not_fresh'])));
        $this->assertSame(2, $cards['onu']['total']);
        $this->assertSame(1, $cards['onu']['online']);
        $this->assertSame(0, $cards['onu']['offline']);
        $this->assertSame(1, $cards['onu']['not_fresh']);
    }

    public function test_pages_carry_freshness_for_olt_list_detail_and_port(): void
    {
        $olt = $this->olt(['last_polled_at' => now()->subHours(3)]);
        $snapshot = $olt->last_test_result;
        $snapshot['port_onus']['1_1']['refreshed_at'] = now()->subHours(3)->toIso8601String();
        $olt->forceFill(['last_test_result' => $snapshot])->save();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('smartolt.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('olts.0.freshness.state', 'stale'));

        $this->actingAs($admin)->get(route('smartolt.port-onus', [$olt->id, 1, 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('snapshot.ok', true)
                ->where('snapshot.freshness.state', 'stale')
                ->where('snapshot.freshness.threshold_seconds', 900));

        $this->actingAs($admin)->get(route('smartolt.detail', $olt->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('snapshot.freshness.state', 'stale'));

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('olt_inventory.0.freshness.state', 'stale')->where('olt_inventory.0.up', 0));
    }

    public function test_onu_rows_and_map_pins_of_a_stale_olt_are_marked_stale(): void
    {
        $fresh = $this->olt(['last_polled_at' => now()->subMinutes(2)]);
        $stale = $this->olt(['last_polled_at' => now()->subHours(2)]);
        foreach ([$fresh, $stale] as $olt) {
            OnuMapPin::create([
                'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 1,
                'latitude' => -6.7, 'longitude' => 111.0,
            ]);
        }
        $admin = User::factory()->admin()->create();

        $rows = collect(app(OnuInventoryService::class)->collect()['onus'])->keyBy('olt_id');
        $this->assertFalse($rows[$fresh->id]['stale']);
        $this->assertTrue($rows[$stale->id]['stale']);
        $this->assertTrue($rows[$stale->id]['online'], 'the last known value is kept, only marked');

        $this->actingAs($admin)->get(route('monitoring.onu'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('olts.0.freshness.state', 'fresh')
                ->where('olts.1.freshness.state', 'stale')
                ->where('onus.1.stale', true));

        $this->actingAs($admin)->get(route('map.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('pins', 2, fn ($pin) => $pin
                ->where('stale', fn ($v) => is_bool($v))
                ->etc()));

        $pins = collect(app(OnuMapPayloadService::class)->pins(SnmpOlt::query()->get()))->keyBy('snmp_olt_id');
        $this->assertFalse($pins[$fresh->id]['stale']);
        $this->assertTrue($pins[$stale->id]['stale']);
    }

    public function test_alarm_page_names_olts_whose_alarms_are_not_evaluated(): void
    {
        $this->olt(['name' => 'OLT-A', 'last_polled_at' => now()->subMinutes(2)]);
        $this->olt(['name' => 'OLT-B', 'last_polled_at' => now()->subHours(5)]);
        $this->olt(['name' => 'OLT-C', 'last_polled_at' => now(), 'polling_enabled' => false]);

        $this->actingAs(User::factory()->admin()->create())->get(route('alarms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('not_evaluated', 2)
                ->where('not_evaluated.0.name', 'OLT-B')
                ->where('not_evaluated.0.freshness.state', 'stale')
                ->where('not_evaluated.1.name', 'OLT-C')
                ->where('not_evaluated.1.freshness.state', 'paused'));
    }

    public function test_cdata_and_hioso_pages_carry_olt_freshness(): void
    {
        $admin = User::factory()->admin()->create();
        foreach (['C-Data GPON 34592' => 'cdata-olt', 'HiOSO EPON' => 'hioso-olt'] as $vendor => $prefix) {
            // A recent onu_scanned_at keeps the page from scanning; the poll itself is old.
            $olt = $this->olt([
                'vendor' => $vendor,
                'last_polled_at' => now()->subHours(4),
                'last_test_result' => ['ok' => true, 'onu_scanned_at' => now()->toIso8601String(), 'port_onus' => []],
            ]);

            $this->actingAs($admin)->get(route("{$prefix}.detail", $olt))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('olt.freshness.state', 'stale'));
        }
    }
}
