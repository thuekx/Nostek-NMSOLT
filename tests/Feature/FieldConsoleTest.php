<?php

namespace Tests\Feature;

use App\Models\AlarmEvent;
use App\Models\OnuMapPin;
use App\Models\SnmpOlt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * UI-C1 "Konsol Lapangan": phone pages for technicians (search, urgent alarms, ONU card).
 */
class FieldConsoleTest extends TestCase
{
    use RefreshDatabase;

    private int $ip = 1;

    private function olt(array $attributes = []): SnmpOlt
    {
        $n = ++$this->ip;
        $rows = [
            ['slot' => 1, 'port' => 3, 'onu_id' => 1, 'online' => true, 'rx_power_dbm' => -27.1, 'serial_number' => sprintf('ZTEGF%05d', $n * 10 + 1), 'name' => 'Budi'],
            ['slot' => 1, 'port' => 3, 'onu_id' => 2, 'online' => false, 'rx_power_dbm' => -31.0, 'serial_number' => sprintf('ZTEGF%05d', $n * 10 + 2), 'last_down_cause' => 'LOS'],
        ];

        $olt = SnmpOlt::create([
            'name' => 'OLT-FIELD-'.$n,
            'vendor' => 'ZTE C320',
            'ip' => '10.62.0.'.$n,
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => ['1_3' => [
                'ok' => true, 'slot' => 1, 'port' => 3, 'count' => 2, 'refreshed_at' => now()->toIso8601String(), 'onus' => $rows,
            ]]],
        ]);
        $olt->forceFill($attributes)->save();

        return $olt;
    }

    private function alarm(SnmpOlt $olt, string $severity, ?int $onuId, string $status = AlarmEvent::STATUS_ACTIVE): AlarmEvent
    {
        return AlarmEvent::create([
            'snmp_olt_id' => $olt->id,
            'signature' => uniqid('sig', true),
            'type' => $onuId ? AlarmEvent::TYPE_LOS : AlarmEvent::TYPE_PORT_DOWN,
            'severity' => $severity,
            'status' => $status,
            'scope' => $onuId ? 'onu' : 'port',
            'slot' => 1,
            'port' => 3,
            'onu_id' => $onuId,
            'message' => 'test',
            'first_seen_at' => now()->subMinutes(5),
            'last_seen_at' => now(),
        ]);
    }

    public function test_index_lists_only_urgent_active_alarms_critical_first(): void
    {
        $olt = $this->olt();
        $this->alarm($olt, AlarmEvent::SEVERITY_MAJOR, 2);
        $this->alarm($olt, AlarmEvent::SEVERITY_CRITICAL, null);
        $this->alarm($olt, AlarmEvent::SEVERITY_WARNING, 1);
        $this->alarm($olt, AlarmEvent::SEVERITY_CRITICAL, 1, AlarmEvent::STATUS_CLEARED);

        $this->actingAs(User::factory()->admin()->create())->get(route('field.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Field/Index')
                ->has('alarms', 2)
                ->where('alarms.0.severity', 'critical')
                ->where('alarms.1.severity', 'major')
                ->has('results', 0));
    }

    public function test_search_finds_onu_by_serial_and_needs_three_characters(): void
    {
        $olt = $this->olt();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)->get(route('field.index', ['q' => 'zt']))
            ->assertInertia(fn ($page) => $page->has('results', 0)->where('q', 'zt'));

        $this->actingAs($user)->get(route('field.index', ['q' => 'ZTEGF00021']))
            ->assertInertia(fn ($page) => $page
                ->has('results', 1)
                ->where('results.0.olt_id', $olt->id)
                ->where('results.0.slot', 1)
                ->where('results.0.port', 3)
                ->where('results.0.onu_id', 1));
    }

    public function test_onu_card_shows_rx_level_odp_pin_and_alarms(): void
    {
        $olt = $this->olt();
        OnuMapPin::create([
            'snmp_olt_id' => $olt->id, 'slot' => 1, 'port' => 3, 'onu_id' => 1,
            'latitude' => -6.75, 'longitude' => 111.04, 'customer_name' => 'Budi Santoso',
            'address' => 'Jl. Contoh 1', 'phone' => '0800000000',
        ]);
        $this->alarm($olt, AlarmEvent::SEVERITY_MAJOR, 1);
        $this->alarm($olt, AlarmEvent::SEVERITY_CRITICAL, null);
        $this->alarm($olt, AlarmEvent::SEVERITY_MAJOR, 2);

        $this->actingAs(User::factory()->admin()->create())->get(route('field.onu', [$olt->id, 1, 3, 1]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Field/Onu')
                ->where('onu.name', 'Budi Santoso')
                ->where('onu.online', true)
                ->where('onu.rx_power_dbm', -27.1)
                ->where('onu.level', 'warning')
                ->where('onu.fiber_color', 'green')
                ->where('pin.latitude', -6.75)
                ->where('pin.phone', '0800000000')
                // This ONU's alarm and the port-wide one; not the other ONU's.
                ->has('alarms', 2));
    }

    public function test_offline_onu_has_no_current_rx(): void
    {
        $olt = $this->olt();

        $this->actingAs(User::factory()->admin()->create())->get(route('field.onu', [$olt->id, 1, 3, 2]))
            ->assertInertia(fn ($page) => $page
                ->where('onu.online', false)
                ->where('onu.rx_power_dbm', null)
                ->where('onu.level', 'none')
                ->where('onu.last_down_cause', 'LOS')
                ->where('pin', null));
    }

    public function test_unknown_onu_is_404(): void
    {
        $olt = $this->olt();

        $this->actingAs(User::factory()->admin()->create())->get(route('field.onu', [$olt->id, 1, 3, 9]))->assertNotFound();
    }

    public function test_partner_sees_only_own_olt_alarms_and_onus(): void
    {
        $partner = User::factory()->partner()->create();
        $own = $this->olt(['owner_user_id' => $partner->id]);
        $foreign = $this->olt();
        $this->alarm($own, AlarmEvent::SEVERITY_CRITICAL, 1);
        $this->alarm($foreign, AlarmEvent::SEVERITY_CRITICAL, 1);

        $this->actingAs($partner)->get(route('field.index', ['q' => 'ZTEGF']))
            ->assertInertia(fn ($page) => $page
                ->has('alarms', 1)
                ->where('alarms.0.olt_id', $own->id)
                ->has('results', 2)
                ->where('results.0.olt_id', $own->id)
                ->where('results.1.olt_id', $own->id));

        $this->actingAs($partner)->get(route('field.onu', [$foreign->id, 1, 3, 1]))->assertNotFound();
    }
}
