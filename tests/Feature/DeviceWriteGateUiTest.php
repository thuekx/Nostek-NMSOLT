<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Support\DeviceWriteGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NMS-07-D: the UI must show the device write boundary. The server stays the gate
 * (EnsureDeviceWritesEnabled); these tests pin what the browser is told and that every
 * screen that calls a gated route marks its controls.
 */
class DeviceWriteGateUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        DeviceWriteGate::flush();
    }

    /**
     * @return array<string, array{name: string, field: ?string}>
     */
    private function gatedRouteNames(): array
    {
        $names = [];
        foreach (Route::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && preg_match('/^(device\.write|App\\\\Http\\\\Middleware\\\\EnsureDeviceWritesEnabled)(?::(.+))?$/', $middleware, $m)) {
                    $names[$route->uri().' '.implode('|', $route->methods())] = ['name' => (string) $route->getName(), 'field' => $m[2] ?? null];
                }
            }
        }

        return $names;
    }

    public function test_pages_share_the_locked_gate_with_every_gated_route(): void
    {
        config(['nostek.device_writes_enabled' => false]);
        $gated = $this->gatedRouteNames();
        $this->assertGreaterThanOrEqual(40, count($gated));

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('device_writes.enabled', false)
                ->has('device_writes.gated', count($gated))
            );

        $payload = DeviceWriteGate::sharedPayload();
        $fields = array_filter(array_column($payload['gated'], 'field'));
        $this->assertContains('execute', $fields, 'preview/execute routes keep their condition');
        $this->assertContains('execute_cli', $fields);
    }

    public function test_writes_enabled_shares_no_guard_list_and_guests_get_nothing(): void
    {
        config(['nostek.device_writes_enabled' => true]);
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('device_writes', ['enabled' => true, 'gated' => []]));

        auth()->logout();
        $this->get(route('login'))->assertInertia(fn ($page) => $page->where('device_writes', null));
    }

    public function test_patterns_match_real_urls_and_nothing_else(): void
    {
        $pattern = DeviceWriteGate::pattern('smartolt/{olt}/ports/{slot}/{port}/onus/{onuId}/reboot');
        $this->assertSame('^/smartolt/[^/]+/ports/[^/]+/[^/]+/onus/[^/]+/reboot/?$', $pattern);

        $re = '#'.$pattern.'#';
        $this->assertMatchesRegularExpression($re, '/smartolt/5/ports/1/2/onus/7/reboot');
        $this->assertDoesNotMatchRegularExpression($re, '/smartolt/5/ports/1/2/onus/7/reboot/x');
        $this->assertDoesNotMatchRegularExpression($re, '/smartolt/5/ports/1/2/onus');

        // Every gated route URL, built with sample parameters, matches its own pattern.
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $entry = collect(DeviceWriteGate::gatedRoutes())->firstWhere('pattern', DeviceWriteGate::pattern($route->uri()));
            if ($entry === null) {
                continue;
            }
            $path = '/'.preg_replace('/\{[^}]+\}/', '7', $route->uri());
            $this->assertMatchesRegularExpression('#'.$entry['pattern'].'#', $path, $route->getName() ?? $route->uri());
        }
    }

    public function test_every_screen_calling_a_gated_route_marks_its_controls(): void
    {
        // TelnetWindow opens its session on mount; the request guard plus the banner cover it.
        $allow = ['resources/js/Components/Shell/TelnetWindow.vue'];
        $missing = [];

        $files = collect(File::allFiles(resource_path('js')))
            ->filter(fn ($f) => $f->getExtension() === 'vue')
            ->mapWithKeys(fn ($f) => ['resources/js/'.str_replace('\\', '/', $f->getRelativePathname()) => $f->getContents()]);

        foreach ($this->gatedRouteNames() as $route) {
            if ($route['name'] === '' || str_starts_with($route['name'], 'api.')) {
                continue;
            }
            foreach ($files as $path => $source) {
                if (str_contains($source, "route('{$route['name']}'") && ! in_array($path, $allow, true)
                    && ! str_contains($source, 'v-device-write')) {
                    $missing[] = "{$path} ({$route['name']})";
                }
            }
        }

        $this->assertSame([], $missing, 'Add v-device-write="$page.props.device_writes" to the control that sends this request.');
    }

    public function test_the_server_boundary_still_refuses_and_audits(): void
    {
        config(['nostek.device_writes_enabled' => false]);
        $olt = SnmpOlt::create([
            'name' => 'OLT-GATE', 'vendor' => 'ZTE C320', 'ip' => '10.30.0.5', 'snmp_port' => 161,
            'snmp_read_community' => 'public', 'snmp_version' => 'v2c',
        ]);

        $this->actingAs(User::factory()->admin()->create())
            ->post(route('smartolt.onu.reboot', [$olt->id, 1, 1, 1]))
            ->assertForbidden();

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLog::EVENT_DEVICE_WRITE_BLOCKED]);
    }
}
