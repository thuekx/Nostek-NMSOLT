<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureDeviceWritesEnabled;
use App\Jobs\Tr069BulkConfigJob;
use App\Models\AuditLog;
use App\Models\DeviceWriteSetting;
use App\Models\SnmpOlt;
use App\Models\Tr069BulkTask;
use App\Models\User;
use App\Services\ZteTr069BulkService;
use App\Support\DeviceWriteGate;
use App\Support\DeviceWritePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * WRITE-UI-1: admins switch device writes in the UI. The env is the upper limit
 * (false = hard off, admin = UI decides, true = legacy on); in admin mode the global switch
 * (with timer), the channel (olt/acs) and a per-OLT block decide, and every change is audited.
 */
class DeviceWriteSwitchTest extends TestCase
{
    use RefreshDatabase;

    private const ON = ['enabled' => true, 'channels' => ['olt' => true, 'acs' => true], 'duration' => 60, 'reason' => 'ticket 42 maintenance'];

    public function test_config_accepts_admin_and_keeps_bool_values(): void
    {
        $saved = [getenv('NMSOLT_DEVICE_WRITES_ENABLED'), $_ENV['NMSOLT_DEVICE_WRITES_ENABLED'] ?? null, $_SERVER['NMSOLT_DEVICE_WRITES_ENABLED'] ?? null];
        try {
            foreach (['admin' => 'admin', ' ADMIN ' => 'admin', 'true' => true, 'false' => false, '' => false] as $env => $expected) {
                putenv("NMSOLT_DEVICE_WRITES_ENABLED={$env}");
                $_ENV['NMSOLT_DEVICE_WRITES_ENABLED'] = $_SERVER['NMSOLT_DEVICE_WRITES_ENABLED'] = $env;
                $config = require base_path('config/nostek.php');
                $this->assertSame($expected, $config['device_writes_enabled'], "env '{$env}'");
            }
        } finally {
            // phpunit.xml sets the variable for every other test in this process.
            putenv('NMSOLT_DEVICE_WRITES_ENABLED='.($saved[0] === false ? '' : $saved[0]));
            $_ENV['NMSOLT_DEVICE_WRITES_ENABLED'] = $saved[1];
            $_SERVER['NMSOLT_DEVICE_WRITES_ENABLED'] = $saved[2];
        }
    }

    public function test_admin_mode_is_off_until_an_admin_switches_it_on(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('smartolt.onu.reboot', [$olt, 1, 2, 3]))->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::EVENT_DEVICE_WRITE_BLOCKED]);
        $this->assertSame('global', AuditLog::query()->where('event', AuditLog::EVENT_DEVICE_WRITE_BLOCKED)->latest('id')->first()->properties['gate_reason']);

        $this->actingAs($admin)->put(route('settings.device-writes.update'), self::ON)->assertRedirect();

        $this->assertTrue(DeviceWritePolicy::allows('olt', $olt));
        $this->assertTrue(DeviceWritePolicy::allows('acs', $olt));
        $global = DeviceWriteSetting::query()->where('key', 'global')->first();
        $this->assertTrue($global->enabled);
        $this->assertSame($admin->id, $global->updated_by);
        $this->assertEqualsWithDelta(now()->addHour()->timestamp, $global->expires_at->timestamp, 5);

        $log = AuditLog::query()->where('event', AuditLog::EVENT_DEVICE_WRITE_SETTING)->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('ticket 42 maintenance', $log->properties['reason']);
        $this->assertFalse($log->properties['before']['global']);
        $this->assertTrue($log->properties['after']['global']);
        $this->assertSame(60, $log->properties['duration_minutes']);
    }

    public function test_only_admins_reach_the_switch_page(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();

        foreach (['operator', 'partner'] as $state) {
            $user = $state === 'partner' ? User::factory()->partner()->create() : User::factory()->create();
            $this->actingAs($user)->get(route('settings.device-writes'))->assertForbidden();
            $this->actingAs($user)->put(route('settings.device-writes.update'), self::ON)->assertForbidden();
            // A partner does not even see an OLT outside its scope (404); both refuse.
            $status = $this->actingAs($user)->put(route('settings.device-writes.olt', $olt), ['blocked' => true, 'reason' => 'not allowed'])->status();
            $this->assertContains($status, [403, 404]);
        }
        auth()->logout();
        $this->put(route('settings.device-writes.update'), self::ON)->assertRedirect(route('login'));

        $this->assertDatabaseCount('device_write_settings', 0);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('settings.device-writes'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Settings/DeviceWrites')->where('mode', 'admin')->has('olts', 1));
    }

    public function test_env_off_is_a_hard_limit_the_ui_cannot_lift(): void
    {
        config(['nostek.device_writes_enabled' => false]);
        $olt = $this->makeOlt();
        $admin = User::factory()->admin()->create();
        DeviceWriteSetting::query()->create(['key' => 'global', 'scope' => 'global', 'enabled' => true]);
        DeviceWriteSetting::query()->create(['key' => 'channel:olt', 'scope' => 'channel', 'channel' => 'olt', 'enabled' => true]);

        $this->assertFalse(DeviceWritePolicy::allows('olt', $olt));
        $this->actingAs($admin)->post(route('smartolt.onu.reboot', [$olt, 1, 2, 3]))->assertForbidden();
        $this->actingAs($admin)->put(route('settings.device-writes.update'), self::ON)->assertForbidden();
        $this->actingAs($admin)->put(route('settings.device-writes.olt', $olt), ['blocked' => true, 'reason' => 'try it'])->assertForbidden();
        $this->actingAs($admin)->get(route('settings.device-writes'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('mode', 'off'));
    }

    public function test_reason_is_required_and_durations_are_fixed(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.device-writes.update'), ['reason' => ''] + self::ON)->assertSessionHasErrors('reason');
        $this->actingAs($admin)->put(route('settings.device-writes.update'), ['duration' => 7] + self::ON)->assertSessionHasErrors('duration');
        $this->assertDatabaseCount('device_write_settings', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => AuditLog::EVENT_DEVICE_WRITE_SETTING]);
    }

    public function test_timer_switches_writes_off_by_itself(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();
        $this->actingAs(User::factory()->admin()->create())->put(route('settings.device-writes.update'), ['duration' => 15] + self::ON);
        $this->assertTrue(DeviceWritePolicy::allows('olt', $olt));

        $this->travel(16)->minutes();
        DeviceWritePolicy::flush();
        $this->assertSame(['allowed' => false, 'reason' => 'global'], DeviceWritePolicy::decide('olt', $olt));
        $this->assertFalse(DeviceWriteGate::sharedPayload()['enabled']);
    }

    public function test_channel_switch_separates_olt_and_acs_writes(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('settings.device-writes.update'), ['channels' => ['olt' => false, 'acs' => true]] + self::ON);

        $this->assertSame('channel', DeviceWritePolicy::decide('olt', $olt)['reason']);
        $this->assertTrue(DeviceWritePolicy::allows('acs', $olt));

        $this->actingAs($admin)->post(route('smartolt.onu.reboot', [$olt, 1, 2, 3]))->assertForbidden();
        $blocked = AuditLog::query()->where('event', AuditLog::EVENT_DEVICE_WRITE_BLOCKED)->latest('id')->first();
        $this->assertSame('channel', $blocked->properties['gate_reason']);
        $this->assertSame('olt', $blocked->properties['write_channel']);

        // The ACS routes declare their channel; everything else is the OLT channel.
        $this->assertContains('device.write:acs', Route::getRoutes()->getByName('genieacs.onu.wifi')->gatherMiddleware());
        $this->assertContains('device.write:acs', Route::getRoutes()->getByName('api.onus.acs-wifi')->gatherMiddleware());

        $payload = DeviceWriteGate::sharedPayload();
        $this->assertTrue($payload['enabled']);
        $this->assertSame(['olt' => false, 'acs' => true], $payload['channels']);
        $channels = array_count_values(array_column($payload['gated'], 'channel'));
        $this->assertSame(2, $channels['acs']);
        $this->assertGreaterThanOrEqual(40, $channels['olt']);
    }

    public function test_per_olt_block_only_takes_writes_away(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $blocked = $this->makeOlt('OLT-BLOCKED', '192.0.2.11');
        $open = $this->makeOlt('OLT-OPEN', '192.0.2.12');
        $admin = User::factory()->admin()->create();

        // A block while the global switch is off does not turn anything on.
        $this->actingAs($admin)->put(route('settings.device-writes.olt', $open), ['blocked' => false, 'reason' => 'follow global'])->assertRedirect();
        $this->assertFalse(DeviceWritePolicy::allows('olt', $open));

        $this->actingAs($admin)->put(route('settings.device-writes.update'), self::ON);
        $this->actingAs($admin)->put(route('settings.device-writes.olt', $blocked), ['blocked' => true, 'reason' => 'customer freeze'])->assertRedirect();

        $this->assertSame('olt', DeviceWritePolicy::decide('olt', $blocked)['reason']);
        $this->assertSame('olt', DeviceWritePolicy::decide('acs', $blocked)['reason']);
        $this->assertTrue(DeviceWritePolicy::allows('olt', $open));
        $this->actingAs($admin)->post(route('smartolt.onu.reboot', [$blocked, 1, 2, 3]))->assertForbidden();

        $payload = DeviceWriteGate::sharedPayload();
        $this->assertSame([$blocked->id], $payload['blocked_olts']);
        $reboot = collect($payload['gated'])->firstWhere('pattern', DeviceWriteGate::pattern('smartolt/{olt}/ports/{slot}/{port}/onus/{onuId}/reboot'));
        $this->assertSame(1, $reboot['olt_segment']);
        $api = collect($payload['gated'])->firstWhere('pattern', DeviceWriteGate::pattern('api/v1/olts/{olt}/onus/{slot}/{port}/{onuId}/reboot'));
        $this->assertSame(3, $api['olt_segment']);

        $log = AuditLog::query()->where('event', AuditLog::EVENT_DEVICE_WRITE_SETTING)->latest('id')->first();
        $this->assertSame('olt', $log->properties['scope']);
        $this->assertTrue($log->properties['after']['blocked']);
        $this->assertSame($blocked->id, $log->auditable_id);

        $this->actingAs($admin)->put(route('settings.device-writes.olt', $blocked), ['blocked' => false, 'reason' => 'freeze over'])->assertRedirect();
        $this->assertTrue(DeviceWritePolicy::allows('olt', $blocked));
    }

    public function test_map_pin_routes_find_their_olt_through_the_pin(): void
    {
        $olt = $this->makeOlt();
        $pin = new class extends Model
        {
            public function olt()
            {
                return null;
            }
        };
        $pin->setRelation('olt', $olt);

        $this->assertSame($olt, EnsureDeviceWritesEnabled::oltFrom(['pin' => $pin]));
        $this->assertSame($olt->id, EnsureDeviceWritesEnabled::oltFrom(['olt' => (string) $olt->id])?->id);
        $this->assertNull(EnsureDeviceWritesEnabled::oltFrom(['slot' => '1']));
        $this->assertSame(['acs', 'execute'], EnsureDeviceWritesEnabled::parseArgs(['execute', 'acs']));
        $this->assertSame(['olt', null], EnsureDeviceWritesEnabled::parseArgs([]));
    }

    public function test_middleware_reads_the_effective_value_with_conditional_field(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();
        $route = (new RoutingRoute(['POST'], 'x/{olt}', fn () => null))->bind(Request::create('/x/'.$olt->id, 'POST'));
        $route->setParameter('olt', $olt);
        $make = function (bool $execute) use ($route) {
            $request = Request::create('/x', 'POST', ['execute' => $execute]);
            $request->setRouteResolver(fn () => $route);

            return $request;
        };
        $next = fn () => new Response('', 204);
        $middleware = app(EnsureDeviceWritesEnabled::class);

        $this->assertSame(204, $middleware->handle($make(false), $next, 'execute')->getStatusCode());
        try {
            $middleware->handle($make(true), $next, 'execute');
            $this->fail('expected 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            $this->assertStringContainsString('admin', $e->getMessage());
        }

        DeviceWriteSetting::query()->create(['key' => 'global', 'scope' => 'global', 'enabled' => true]);
        DeviceWriteSetting::query()->create(['key' => 'channel:olt', 'scope' => 'channel', 'channel' => 'olt', 'enabled' => true]);
        DeviceWritePolicy::flush();
        $this->assertSame(204, $middleware->handle($make(true), $next, 'execute')->getStatusCode());
        $this->assertSame(403, rescue(fn () => $middleware->handle($make(true), $next, 'acs', 'execute'), fn (HttpException $e) => new Response('', $e->getStatusCode()), false)->getStatusCode());
    }

    public function test_queued_job_rechecks_the_admin_switch_at_run_time(): void
    {
        config(['nostek.device_writes_enabled' => 'admin']);
        $olt = $this->makeOlt();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->put(route('settings.device-writes.update'), self::ON);

        $task = Tr069BulkTask::create(['snmp_olt_id' => $olt->id, 'execute' => true, 'total' => 1, 'status' => 'queued']);
        $this->actingAs($admin)->put(route('settings.device-writes.olt', $olt), ['blocked' => true, 'reason' => 'stop the bulk job']);

        (new Tr069BulkConfigJob($task->id))->handle(app(ZteTr069BulkService::class));
        $this->assertSame('failed', $task->fresh()->status);
        $this->assertStringContainsString('diblokir oleh admin', (string) $task->fresh()->error);
    }

    public function test_legacy_true_mode_ignores_the_switches(): void
    {
        config(['nostek.device_writes_enabled' => true]);
        $olt = $this->makeOlt();
        DeviceWriteSetting::query()->create(['key' => 'olt:'.$olt->id, 'scope' => 'olt', 'snmp_olt_id' => $olt->id, 'enabled' => false]);

        $this->assertSame(['allowed' => true, 'reason' => 'installation'], DeviceWritePolicy::decide('olt', $olt));
        $this->actingAs(User::factory()->admin()->create())
            ->put(route('settings.device-writes.update'), self::ON)
            ->assertForbidden();
    }

    private function makeOlt(string $name = 'OLT-W1', string $ip = '192.0.2.10'): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => $name,
            'vendor' => 'ZTE C320',
            'ip' => $ip,
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
        ]);
    }
}
