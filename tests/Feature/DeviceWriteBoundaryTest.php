<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDeviceWritesEnabled;
use App\Jobs\CopyOnusToPortJob;
use App\Jobs\Tr069BulkConfigJob;
use App\Models\AuditLog;
use App\Models\CopyOnuTask;
use App\Models\SnmpOlt;
use App\Models\Tr069BulkTask;
use App\Models\User;
use App\Services\ZteOnuCopyService;
use App\Services\ZteTr069BulkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class DeviceWriteBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_device_write_is_denied_and_audited_when_installation_is_read_only(): void
    {
        config()->set('nostek.device_writes_enabled', false);

        $olt = $this->makeOlt();
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->post(route('smartolt.onu.reboot', [$olt, 1, 2, 3]))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => AuditLog::EVENT_DEVICE_WRITE_BLOCKED,
            'auditable_type' => $olt->getMorphClass(),
            'auditable_id' => $olt->id,
        ]);
    }

    public function test_preview_or_dry_run_condition_bypasses_the_write_gate(): void
    {
        config()->set('nostek.device_writes_enabled', false);
        $request = Request::create('/preview', 'POST', ['execute' => false]);

        $response = app(EnsureDeviceWritesEnabled::class)->handle(
            $request,
            fn () => new Response('', 204),
            'execute',
        );

        $this->assertSame(204, $response->getStatusCode());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_queued_execute_tasks_recheck_the_gate_before_calling_services(): void
    {
        config()->set('nostek.device_writes_enabled', false);
        $olt = $this->makeOlt();

        $copy = CopyOnuTask::create([
            'snmp_olt_id' => $olt->id,
            'src_slot' => 1,
            'src_port' => 1,
            'dst_slot' => 1,
            'dst_port' => 2,
            'execute' => true,
            'onu_ids' => [1],
            'total' => 1,
            'status' => 'queued',
        ]);
        (new CopyOnusToPortJob($copy->id))->handle(app(ZteOnuCopyService::class));

        $tr069 = Tr069BulkTask::create([
            'snmp_olt_id' => $olt->id,
            'execute' => true,
            'total' => 1,
            'status' => 'queued',
        ]);
        (new Tr069BulkConfigJob($tr069->id))->handle(app(ZteTr069BulkService::class));

        $this->assertSame('failed', $copy->fresh()->status);
        $this->assertSame('failed', $tr069->fresh()->status);
        $this->assertStringContainsString('dinonaktifkan', (string) $copy->fresh()->error);
        $this->assertStringContainsString('dinonaktifkan', (string) $tr069->fresh()->error);
    }

    public function test_every_known_device_mutation_route_has_the_boundary(): void
    {
        $names = [
            'cdata-olt.config.save',
            'cdata-olt.onu.reboot',
            'cdata-olt.onu.state',
            'cdata-olt.onu.info',
            'cdata-olt.onu.remote-access',
            'cdata-olt.onu.delete',
            'cdata-olt.vlans.store',
            'cdata-olt.port.vlan',
            'hioso-olt.config.save',
            'hioso-olt.onu.reboot',
            'hioso-olt.onu.state',
            'hioso-olt.onu.info',
            'hioso-olt.onu.delete',
            'map.pins.reboot',
            'map.pins.rename',
            'genieacs.onu.wifi',
            'smartolt.port.vlan',
            'smartolt.port.description',
            'smartolt.port.admin-state',
            'smartolt.register.advanced.store',
            'smartolt.register.store',
            'smartolt.registrations.execute',
            'smartolt.profiles.store',
            'smartolt.profiles.update',
            'smartolt.profiles.destroy',
            'smartolt.telnet.token',
            'smartolt.config.save',
            'smartolt.port-onus.delete',
            'smartolt.port-onus.copy',
            'smartolt.tr069-bulk',
            'smartolt.onu.reboot',
            'smartolt.onu.delete',
            'smartolt.onu.replace',
            'smartolt.onu.state',
            'smartolt.onu.info',
            'smartolt.onu.configure.apply',
            'smartolt.onu.configure.item',
            'smartolt.onu.configure.unbind-profile',
            'api.olts.register',
            'api.olts.onu.reboot',
            'api.olts.onu.name',
            'api.onus.acs-wifi',
            'api.olts.onu.delete',
        ];

        foreach ($names as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "Route {$name} tidak ditemukan.");
            $this->assertTrue(
                collect($route->gatherMiddleware())->contains(
                    fn (string $middleware): bool => str_starts_with($middleware, 'device.write'),
                ),
                "Route {$name} tidak memakai device.write.",
            );
        }
    }

    private function makeOlt(): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => 'OLT-R0',
            'vendor' => 'ZTE C320',
            'ip' => '192.0.2.10',
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'cli_transport' => 'telnet',
            'cli_port' => 23,
            'cli_username' => 'admin',
            'cli_password' => 'test-only',
        ]);
    }
}
