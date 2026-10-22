<?php

namespace Tests\Feature;

use App\Models\SnmpOlt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NMS-07-E2: the ONU monitor sends only the ONUs of the selected OLT, never every ONU of
 * every OLT (27.6 MB at 51,000 ONUs, NMS-07-PERFORMANCE.md).
 */
class OnuMonitorScopeTest extends TestCase
{
    use RefreshDatabase;

    private int $ip = 1;

    private function olt(int $onus, array $attributes = []): SnmpOlt
    {
        $rows = [];
        for ($i = 1; $i <= $onus; $i++) {
            $rows[] = ['onu_id' => $i, 'slot' => 1, 'port' => 1, 'online' => true, 'serial_number' => sprintf('ZTEG%08d', $this->ip * 1000 + $i)];
        }

        $olt = SnmpOlt::create([
            'name' => 'OLT-SCOPE-'.$this->ip,
            'vendor' => 'ZTE C320',
            'ip' => '10.60.0.'.(++$this->ip),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => ['1_1' => [
                'ok' => true, 'slot' => 1, 'port' => 1, 'count' => $onus,
                'refreshed_at' => now()->toIso8601String(), 'onus' => $rows,
            ]]],
        ]);
        $olt->forceFill($attributes)->save();

        return $olt;
    }

    public function test_no_olt_selected_sends_no_onus(): void
    {
        $this->olt(3);
        $this->olt(4);

        $this->actingAs(User::factory()->admin()->create())->get(route('monitoring.onu'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('olts', 2)
                ->has('onus', 0)
                ->where('selected_olt_id', null));
    }

    public function test_selected_olt_sends_only_its_onus(): void
    {
        $this->olt(3);
        $b = $this->olt(4);

        $this->actingAs(User::factory()->admin()->create())->get(route('monitoring.onu', ['olt_id' => $b->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('olts', 2)
                ->has('onus', 4)
                ->where('selected_olt_id', $b->id)
                ->where('onus.0.olt_id', $b->id)
                ->has('refreshed_at.'.$b->id));
    }

    public function test_an_olt_the_user_cannot_see_selects_nothing(): void
    {
        $partner = User::factory()->partner()->create();
        $own = $this->olt(2, ['owner_user_id' => $partner->id]);
        $foreign = $this->olt(5);

        $this->actingAs($partner)->get(route('monitoring.onu', ['olt_id' => $foreign->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('olts', 1)
                ->where('olts.0.id', $own->id)
                ->has('onus', 0)
                ->where('selected_olt_id', null));
    }
}
