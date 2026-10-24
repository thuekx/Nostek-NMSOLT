<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AlarmEvent;
use App\Models\Odp;
use App\Models\OnuMapPin;
use App\Models\Organization;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\Fcm\FcmAlarmNotifier;
use App\Support\AuditLogger;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

/**
 * UI-F1a: isolasi antar-organisasi. Organisasi B memakai instalasi yang sama dengan organisasi
 * instalasi (A). Semua yang milik B tidak boleh terlihat oleh A (dan sebaliknya): id milik
 * organisasi lain = 404, bukan 403, dan halaman daftar tidak memuat namanya.
 *
 * Test rute bersifat generik: SETIAP rute ber-{olt}/{odp}/{pin}/{alarm} diuji, jadi rute baru
 * otomatis ikut tercakup.
 */
class OrganizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = 'ORGB-RAHASIA';

    private const SERIAL = 'ORGB00000001';

    private Organization $b;

    private User $adminA;

    private User $adminB;

    private SnmpOlt $oltA;

    private SnmpOlt $oltB;

    private Odp $odpB;

    private OnuMapPin $pinB;

    private AlarmEvent $alarmB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminA = User::factory()->create(['role' => UserRole::Admin->value, 'name' => 'Admin A']);
        $this->oltA = $this->makeOlt('OLT-A-MILIK-INSTALASI', '10.70.0.1', 'ORGA00000001');

        $this->b = Organization::create(['slug' => 'isp-b', 'name' => 'ISP B', 'kind' => Organization::KIND_ISP]);
        $this->adminB = $this->moveTo(User::factory()->create(['role' => UserRole::Admin->value, 'name' => 'Admin B']), $this->b);

        // Data B dibuat oleh admin B, seperti di aplikasi (audit ikut organisasi B).
        $this->actingAs($this->adminB);
        $this->oltB = $this->makeOlt('OLT-'.self::MARK, '10.70.0.2', self::SERIAL);

        $this->odpB = Odp::create([
            'snmp_olt_id' => $this->oltB->id, 'name' => 'ODP-'.self::MARK, 'slot' => 1, 'port' => 1,
            'latitude' => -6.7, 'longitude' => 111.0,
        ]);
        $this->pinB = OnuMapPin::create([
            'snmp_olt_id' => $this->oltB->id, 'slot' => 1, 'port' => 1, 'onu_id' => 1,
            'latitude' => -6.7, 'longitude' => 111.0, 'customer_name' => 'Pelanggan '.self::MARK,
        ]);
        $this->alarmB = AlarmEvent::create([
            'snmp_olt_id' => $this->oltB->id, 'signature' => 'orgb-los-1', 'type' => AlarmEvent::TYPE_LOS,
            'severity' => AlarmEvent::SEVERITY_CRITICAL, 'status' => AlarmEvent::STATUS_ACTIVE, 'scope' => 'onu',
            'slot' => 1, 'port' => 1, 'onu_id' => 1, 'serial_number' => self::SERIAL,
            'message' => 'LOS '.self::MARK, 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        auth()->forgetGuards();
        app(OrganizationContext::class)->forget();
    }

    public function test_migration_puts_existing_data_in_the_installation_organisation(): void
    {
        $context = app(OrganizationContext::class);

        $this->assertNotNull($context->primaryId());
        $this->assertSame($context->primaryId(), $this->oltA->fresh()->organization_id);
        $this->assertSame($context->primaryId(), $this->adminA->currentOrganizationId());
        $this->assertSame('org_admin', DB::table('organization_user')->where('user_id', $this->adminA->id)->value('role'));
    }

    public function test_each_organisation_sees_only_its_own_olts(): void
    {
        $this->actingAs($this->adminA);
        $this->assertSame([$this->oltA->id], SnmpOlt::query()->pluck('id')->all());
        $this->assertSame(0, Odp::query()->whereKey($this->odpB->id)->count());
        $this->assertSame(0, AlarmEvent::query()->whereKey($this->alarmB->id)->count());

        $this->actingAs($this->adminB);
        $this->assertSame([$this->oltB->id], SnmpOlt::query()->pluck('id')->all());
        $this->assertSame(1, AlarmEvent::query()->whereKey($this->alarmB->id)->count());
    }

    public function test_every_web_and_api_route_with_another_organisations_id_is_404(): void
    {
        $checked = 0;

        foreach (RouteFacade::getRoutes() as $route) {
            $uri = $this->fill($route);
            if ($uri === null) {
                continue;
            }

            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $api = str_starts_with($route->uri(), 'api/');

            if ($api) {
                Sanctum::actingAs($this->adminA);
            } else {
                $this->actingAs($this->adminA);
            }

            $response = $this->json($method, $uri);
            $this->assertSame(404, $response->status(), "$method /$uri must be 404 for another organisation, got {$response->status()}");
            $checked++;
        }

        // Pengaman: kalau pengisian parameter rusak, test ini jangan diam-diam lolos tanpa memeriksa.
        $this->assertGreaterThan(100, $checked);
    }

    public function test_own_olt_routes_still_open(): void
    {
        $this->actingAs($this->adminA)->get(route('smartolt.edit', $this->oltA))->assertOk();
        $this->actingAs($this->adminB)->get(route('smartolt.edit', $this->oltB))->assertOk();
        $this->actingAs($this->adminB)->get(route('smartolt.edit', $this->oltA))->assertNotFound();
    }

    public function test_list_pages_do_not_show_another_organisations_data(): void
    {
        $paths = ['/dashboard', '/smartolt', '/cdata-olt', '/hioso-olt', '/odps', '/map', '/alarms', '/onus',
            '/network', '/field', '/field?q=ORGB', '/search?q=ORGB', '/users', '/audit-logs', '/notifications'];

        foreach ($paths as $path) {
            $response = $this->actingAs($this->adminA)->get($path);
            if ($response->status() === 404) {
                continue; // halaman tidak ada di build ini
            }
            $this->assertStringNotContainsString(self::MARK, $response->getContent(), "$path leaks organisation B");
            $this->assertStringNotContainsString(self::SERIAL, $response->getContent(), "$path leaks organisation B");
        }

        Sanctum::actingAs($this->adminA);
        foreach (['/api/v1/summary', '/api/v1/olts', '/api/v1/onus', '/api/v1/alarms', '/api/v1/odps', '/api/v1/search?q=ORGB'] as $path) {
            $response = $this->getJson($path);
            $this->assertStringNotContainsString(self::MARK, $response->getContent(), "$path leaks organisation B");
            $this->assertStringNotContainsString(self::SERIAL, $response->getContent(), "$path leaks organisation B");
        }
    }

    public function test_users_of_another_organisation_are_invisible(): void
    {
        $this->actingAs($this->adminA)->get('/users')->assertOk()
            ->assertDontSee(self::MARK);

        $this->actingAs($this->adminA)->put(route('users.update', $this->adminB), [
            'name' => 'x', 'email' => 'x@example.test', 'role' => 'admin',
        ])->assertNotFound();
        $this->actingAs($this->adminA)->delete(route('users.destroy', $this->adminB))->assertNotFound();
        $this->assertNotNull($this->adminB->fresh());
    }

    public function test_user_cannot_be_assigned_an_olt_of_another_organisation(): void
    {
        $this->actingAs($this->adminA)->post(route('users.store'), [
            'name' => 'Teknisi', 'email' => 'teknisi@example.test', 'role' => 'operator',
            'password' => 'Rahasia-Uji-123!', 'olt_ids' => [$this->oltB->id],
        ])->assertSessionHasErrors('olt_ids.0');
    }

    public function test_new_user_and_new_olt_join_the_creators_organisation(): void
    {
        $this->actingAs($this->adminB);
        $user = User::factory()->create();
        $olt = $this->makeOlt('OLT-BARU-B', '10.70.0.9', 'ORGB00000009');

        $this->assertSame($this->b->id, $olt->fresh()->organization_id);
        $this->assertTrue(app(OrganizationContext::class)->isMember($user, $this->b->id));
        $this->assertFalse(app(OrganizationContext::class)->isMember($user, app(OrganizationContext::class)->primaryId()));
    }

    public function test_organisation_id_cannot_come_from_input(): void
    {
        $olt = new SnmpOlt(['name' => 'x', 'organization_id' => $this->b->id]);

        $this->assertNull($olt->organization_id);
    }

    public function test_user_without_membership_sees_nothing(): void
    {
        $orphan = User::factory()->create(['role' => UserRole::Admin->value]);
        DB::table('organization_user')->where('user_id', $orphan->id)->delete();
        app(OrganizationContext::class)->forget();

        $this->actingAs($orphan);
        $this->assertSame(0, SnmpOlt::query()->count());
        $this->get(route('smartolt.edit', $this->oltA))->assertNotFound();
    }

    public function test_audit_log_shows_only_own_members_and_installation_entries(): void
    {
        $this->actingAs($this->adminB);
        AuditLogger::log('updated', $this->oltB, description: 'aksi '.self::MARK);
        auth()->forgetGuards();
        AuditLogger::log('updated', null, description: 'entri sistem instalasi');

        $this->actingAs($this->adminA)->get('/audit-logs')->assertOk()
            ->assertDontSee(self::MARK)
            ->assertSee('entri sistem instalasi');

        $this->actingAs($this->adminB)->get('/audit-logs')->assertOk()
            ->assertSee(self::MARK)
            ->assertDontSee('entri sistem instalasi');
    }

    public function test_installation_settings_are_404_for_another_organisation(): void
    {
        $this->actingAs($this->adminA)->get(route('settings.edit'))->assertOk();
        $this->actingAs($this->adminB)->get(route('settings.edit'))->assertNotFound();
        $this->actingAs($this->adminB)->get(route('settings.device-writes'))->assertNotFound();
        $this->actingAs($this->adminB)->put(route('settings.telegram.update'), [])->assertNotFound();
    }

    public function test_push_recipients_of_an_alarm_are_members_of_the_olt_organisation(): void
    {
        $method = new ReflectionMethod(FcmAlarmNotifier::class, 'recipientUserIds');

        $recipients = $method->invoke(app(FcmAlarmNotifier::class), $this->oltB->fresh());

        $this->assertContains($this->adminB->id, $recipients);
        $this->assertNotContains($this->adminA->id, $recipients);
    }

    public function test_public_status_shows_only_the_installation_organisation(): void
    {
        $body = $this->getJson('/api/v1/public/status')->assertOk()->getContent();

        $this->assertStringContainsString('OLT-A-MILIK-INSTALASI', $body);
        $this->assertStringNotContainsString(self::MARK, $body);
    }

    private function moveTo(User $user, Organization $organization): User
    {
        DB::table('organization_user')->where('user_id', $user->id)->delete();
        DB::table('organization_user')->insert([
            'organization_id' => $organization->id, 'user_id' => $user->id,
            'role' => Organization::ROLE_ORG_ADMIN, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->forceFill(['current_organization_id' => $organization->id])->saveQuietly();
        app(OrganizationContext::class)->forget();

        return $user;
    }

    private function makeOlt(string $name, string $ip, string $serial): SnmpOlt
    {
        $onu = ['onu_id' => 1, 'slot' => 1, 'port' => 1, 'online' => true, 'rx_power_dbm' => -20.0,
            'serial_number' => $serial, 'name' => 'ONU '.$name, 'interface' => 'gpon-onu_1/1/1:1'];

        return SnmpOlt::create([
            'name' => $name,
            'vendor' => 'ZTE C320',
            'ip' => $ip,
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'cli_transport' => 'telnet',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => ['1_1' => [
                'ok' => true, 'slot' => 1, 'port' => 1, 'count' => 1,
                'refreshed_at' => now()->toIso8601String(), 'onus' => [$onu],
            ]]],
        ]);
    }

    /** URI rute dengan id milik organisasi B; null = rute tanpa parameter organisasi. */
    private function fill(Route $route): ?string
    {
        $uri = $route->uri();
        $values = [
            'olt' => $this->oltB->id,
            'odp' => $this->odpB->id,
            'pin' => $this->pinB->id,
            'alarm' => $this->alarmB->id,
        ];

        if (! preg_match('/\{(olt|odp|pin|alarm)\??\}/', $uri)) {
            return null;
        }

        $values += ['slot' => 1, 'port' => 1, 'onuId' => 1, 'kind' => 'gpon',
            'backup' => 1, 'task' => 1, 'profile' => 1, 'registration' => 1];

        return preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($values[$m[1]] ?? 1), $uri);
    }
}
