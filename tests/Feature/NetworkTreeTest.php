<?php

namespace Tests\Feature;

use App\Models\Odp;
use App\Models\OnuOdpLink;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Support\RxBand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-B1 "Peta Fiber": pohon OLT → PON → ODP → ONU dan pita redaman RX.
 */
class NetworkTreeTest extends TestCase
{
    use RefreshDatabase;

    private int $ip = 1;

    /**
     * @param  array<string, list<array<string, mixed>>>  $ports  key "slot_port" → ONU rows
     */
    private function olt(array $ports, array $attributes = []): SnmpOlt
    {
        $portOnus = [];
        foreach ($ports as $key => $rows) {
            [$slot, $port] = array_map('intval', explode('_', $key));
            $portOnus[$key] = [
                'ok' => true, 'slot' => $slot, 'port' => $port, 'count' => count($rows),
                'refreshed_at' => now()->toIso8601String(),
                'onus' => array_map(fn (array $r) => $r + ['slot' => $slot, 'port' => $port], $rows),
            ];
        }

        $olt = SnmpOlt::create([
            'name' => 'OLT-TREE-'.$this->ip,
            'vendor' => 'ZTE C320',
            'ip' => '10.61.0.'.(++$this->ip),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => $portOnus],
        ]);
        $olt->forceFill($attributes)->save();

        return $olt;
    }

    private function onu(int $id, bool $online, ?float $rx): array
    {
        return ['onu_id' => $id, 'online' => $online, 'rx_power_dbm' => $rx, 'serial_number' => sprintf('ZTEG%08d', $id)];
    }

    public function test_tree_groups_onus_by_pon_and_odp_with_rx_totals(): void
    {
        $olt = $this->olt([
            '1_1' => [$this->onu(1, true, -18.0), $this->onu(2, true, -26.0), $this->onu(3, false, -30.0)],
            '1_2' => [$this->onu(1, true, -29.5)],
        ]);
        $odp = Odp::create(['snmp_olt_id' => $olt->id, 'name' => 'ODP-A', 'slot' => 1, 'port' => 1, 'latitude' => -6.2, 'longitude' => 106.8]);
        OnuOdpLink::create(['odp_id' => $odp->id, 'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 1]);
        OnuOdpLink::create(['odp_id' => $odp->id, 'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 1, 'onu_id' => 2]);

        $this->actingAs(User::factory()->admin()->create())->get(route('network.tree', ['olt_id' => $olt->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Network/Tree')
                ->where('selected_olt_id', $olt->id)
                ->where('tree.stale', false)
                ->has('tree.pons', 2)
                ->where('tree.pons.0.slot', 1)
                ->where('tree.pons.0.port', 1)
                ->where('tree.pons.0.fiber_color', 'blue')
                ->where('tree.pons.1.fiber_color', 'orange')
                ->where('tree.pons.0.totals.onus', 3)
                ->where('tree.pons.0.totals.online', 2)
                ->where('tree.pons.0.totals.offline', 1)
                ->where('tree.pons.0.totals.good', 1)
                ->where('tree.pons.0.totals.warning', 1)
                // Offline ONU: its old RX is not current, so it is not counted as critical.
                ->where('tree.pons.0.totals.critical', 0)
                ->where('tree.pons.0.totals.none', 1)
                ->where('tree.pons.0.odps.0.name', 'ODP-A')
                ->where('tree.pons.0.odps.0.totals.onus', 2)
                ->where('tree.pons.0.unassigned.onus', 1)
                ->where('tree.band.total', 3)
                ->where('tree.band.critical', 1)
                ->has('tree.worst', 2)
                ->where('tree.worst.0.rx_power_dbm', -29.5)
                ->where('tree.worst.1.rx_power_dbm', -26)
                ->missing('tree.pons.0.onus'));
    }

    public function test_first_olt_is_selected_by_default_and_none_gives_an_empty_page(): void
    {
        $user = User::factory()->admin()->create();
        $this->actingAs($user)->get(route('network.tree'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tree', null)->where('selected_olt_id', null));

        $olt = $this->olt(['1_1' => [$this->onu(1, true, -20.0)]]);
        $this->actingAs($user)->get(route('network.tree'))
            ->assertInertia(fn ($page) => $page->where('selected_olt_id', $olt->id)->has('tree.pons', 1));
    }

    public function test_stale_olt_is_marked_stale(): void
    {
        $olt = $this->olt(['1_1' => [$this->onu(1, true, -20.0)]], ['last_polled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->admin()->create())->get(route('network.tree', ['olt_id' => $olt->id]))
            ->assertInertia(fn ($page) => $page->where('tree.stale', true)->where('tree.olt.freshness.state', 'stale'));
    }

    public function test_leaf_endpoint_returns_one_pon_in_short_form(): void
    {
        $olt = $this->olt([
            '1_1' => [$this->onu(1, true, -18.0), $this->onu(2, false, -31.0)],
            '1_2' => [$this->onu(1, true, -20.0)],
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('network.tree.onus', [$olt->id, 1, 1]))
            ->assertOk()
            ->assertJsonCount(2, 'onus')
            ->assertJsonPath('onus.0.level', 'good')
            ->assertJsonPath('onus.1.online', false)
            ->assertJsonPath('onus.1.rx_power_dbm', null)
            ->assertJsonPath('onus.1.level', 'none')
            ->assertJsonPath('stale', false)
            ->assertJsonMissingPath('onus.0.mac');
    }

    public function test_partner_cannot_see_another_olt(): void
    {
        $partner = User::factory()->partner()->create();
        $own = $this->olt(['1_1' => [$this->onu(1, true, -20.0)]], ['owner_user_id' => $partner->id]);
        $foreign = $this->olt(['1_1' => [$this->onu(1, true, -20.0)]]);

        $this->actingAs($partner)->get(route('network.tree', ['olt_id' => $foreign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('olts', 1)->where('selected_olt_id', $own->id));

        $this->actingAs($partner)->getJson(route('network.tree.onus', [$foreign->id, 1, 1]))->assertNotFound();
    }

    public function test_rx_band_levels_match_the_frontend_thresholds(): void
    {
        $this->assertSame(RxBand::CRITICAL, RxBand::level(-28.0));
        $this->assertSame(RxBand::WARNING, RxBand::level(-27.9));
        $this->assertSame(RxBand::WARNING, RxBand::level(-25.0));
        $this->assertSame(RxBand::GOOD, RxBand::level(-24.9));
        $this->assertSame(RxBand::GOOD, RxBand::level(-10.1));
        $this->assertSame(RxBand::WARNING, RxBand::level(-10.0));
        $this->assertSame(RxBand::CRITICAL, RxBand::level(-8.0));
        $this->assertSame(RxBand::NONE, RxBand::level(null));
        $this->assertSame('aqua', RxBand::fiberColor(12));
        $this->assertSame('blue', RxBand::fiberColor(13));

        $band = RxBand::histogram([-40.0, -18.2, -18.9, -3.0]);
        $bins = collect($band['bins'])->keyBy('dbm');
        $this->assertSame(1, $bins[-32]['count'], 'below range lands in the edge bin');
        $this->assertSame(2, $bins[-19]['count']);
        $this->assertSame(1, $bins[-6]['count']);
        $this->assertSame(-18.6, $band['median']);
        $this->assertSame(2, $band['critical']);
    }
}
