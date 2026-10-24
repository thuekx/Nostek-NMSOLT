<?php

namespace Tests\Feature;

use App\Models\AlarmEvent;
use App\Models\Odp;
use App\Models\OnuOdpLink;
use App\Models\SnmpOlt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-F6: impact preview before a device write (who is affected on one PON).
 */
class ImpactPreviewTest extends TestCase
{
    use RefreshDatabase;

    private int $ip = 1;

    private function olt(array $attributes = []): SnmpOlt
    {
        $n = ++$this->ip;
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = ['slot' => 1, 'port' => 2, 'onu_id' => $i, 'online' => $i <= 7, 'serial_number' => sprintf('ZTEGI%05d', $n * 100 + $i), 'name' => "Pelanggan {$i}"];
        }

        $olt = SnmpOlt::create([
            'name' => 'OLT-IMPACT-'.$n,
            'vendor' => 'ZTE C320',
            'ip' => '10.63.0.'.$n,
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => ['1_2' => [
                'ok' => true, 'slot' => 1, 'port' => 2, 'count' => 10, 'refreshed_at' => now()->toIso8601String(), 'onus' => $rows,
            ]]],
        ]);
        $olt->forceFill($attributes)->save();

        return $olt;
    }

    public function test_whole_port_counts_onus_customers_odps_and_alarms(): void
    {
        $olt = $this->olt();
        $odp = Odp::create(['snmp_olt_id' => $olt->id, 'name' => 'ODP-X', 'slot' => 1, 'port' => 2, 'latitude' => -6.2, 'longitude' => 106.8]);
        foreach ([1, 2, 3] as $id) {
            OnuOdpLink::create(['odp_id' => $odp->id, 'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 2, 'onu_id' => $id]);
        }
        AlarmEvent::create([
            'snmp_olt_id' => $olt->id, 'signature' => 'x1', 'type' => AlarmEvent::TYPE_LOS, 'severity' => AlarmEvent::SEVERITY_MAJOR,
            'status' => AlarmEvent::STATUS_ACTIVE, 'scope' => 'onu', 'slot' => 1, 'port' => 2, 'onu_id' => 9,
            'message' => 'los', 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('impact.port', [$olt->id, 1, 2]))
            ->assertOk()
            ->assertJsonPath('scope', 'port')
            ->assertJsonPath('onus', 10)
            ->assertJsonPath('online', 7)
            ->assertJsonCount(8, 'customers')
            ->assertJsonPath('more_customers', 2)
            ->assertJsonPath('odps.0.name', null)
            ->assertJsonPath('odps.0.onus', 7)
            ->assertJsonPath('odps.1.name', 'ODP-X')
            ->assertJsonPath('odps.1.onus', 3)
            ->assertJsonPath('active_alarms', 1)
            ->assertJsonPath('stale', false);
    }

    public function test_selected_onus_only(): void
    {
        $olt = $this->olt();

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('impact.port', [$olt->id, 1, 2, 'onu_ids' => [2, 8]]))
            ->assertOk()
            ->assertJsonPath('scope', 'onus')
            ->assertJsonPath('onus', 2)
            ->assertJsonPath('online', 1)
            ->assertJsonPath('customers', ['Pelanggan 2', 'Pelanggan 8']);
    }

    public function test_partner_cannot_preview_a_foreign_olt(): void
    {
        $partner = User::factory()->partner()->create();
        $foreign = $this->olt();
        $own = $this->olt(['owner_user_id' => $partner->id]);

        $this->actingAs($partner)->getJson(route('impact.port', [$foreign->id, 1, 2]))->assertNotFound();
        $this->actingAs($partner)->getJson(route('impact.port', [$own->id, 1, 2]))->assertOk()->assertJsonPath('onus', 10);
    }

    public function test_stale_olt_is_flagged(): void
    {
        $olt = $this->olt(['last_polled_at' => now()->subDay()]);

        $this->actingAs(User::factory()->admin()->create())
            ->getJson(route('impact.port', [$olt->id, 1, 2]))
            ->assertJsonPath('stale', true);
    }
}
