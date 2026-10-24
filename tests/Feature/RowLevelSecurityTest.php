<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Odp;
use App\Models\Organization;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Support\OrganizationContext;
use App\Support\TenantDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UI-F1d: PostgreSQL Row Level Security under the app scope. Runs only on PostgreSQL (CI job
 * "Laravel tests (PostgreSQL + RLS)"); skipped on SQLite.
 *
 * The point of RLS: even code that bypasses the app scope (raw SQL, DB::table, withoutGlobalScopes)
 * cannot read or write another organisation's rows during a web/API request.
 */
class RowLevelSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Organization $b;

    private SnmpOlt $oltA;

    private SnmpOlt $oltB;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Row Level Security needs PostgreSQL.');
        }

        $this->oltA = $this->olt('OLT-A', '10.72.0.1');
        $this->b = Organization::create(['slug' => 'isp-b', 'name' => 'ISP B']);
        $this->oltB = $this->olt('OLT-B', '10.72.0.2');
        $this->oltB->forceFill(['organization_id' => $this->b->id])->saveQuietly();
        Odp::query()->create(['snmp_olt_id' => $this->oltB->id, 'name' => 'ODP-B', 'slot' => 1, 'port' => 1, 'latitude' => -6.7, 'longitude' => 111.0]);
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            app(TenantDatabase::class)->leave();
        }

        parent::tearDown();
    }

    public function test_every_organisation_table_is_under_rls(): void
    {
        $exit = Artisan::call('nmsolt:rls-status');
        $output = Artisan::output();

        $this->assertSame(0, $exit, $output);
        $this->assertStringContainsString('RESULT nmsolt-rls PASS', $output);
    }

    public function test_tenant_role_cannot_bypass_rls(): void
    {
        $role = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = ?', [TenantDatabase::role()]);

        $this->assertFalse((bool) $role->rolsuper);
        $this->assertFalse((bool) $role->rolbypassrls);
    }

    public function test_raw_sql_inside_the_tenant_role_sees_only_its_organisation(): void
    {
        $this->enter(app(OrganizationContext::class)->primaryId(), true);

        $this->assertSame([$this->oltA->id], array_map('intval', DB::table('snmp_olts')->pluck('id')->all()));
        $this->assertSame(0, DB::table('odps')->count());
        $this->assertSame(1, SnmpOlt::query()->withoutGlobalScopes()->count());
        $this->assertSame(0, (int) DB::selectOne('select count(*) as n from odps where snmp_olt_id = ?', [$this->oltB->id])->n);
    }

    public function test_no_organisation_means_no_rows(): void
    {
        $this->enter(null, false);

        $this->assertSame(0, DB::table('snmp_olts')->count());
        $this->assertSame(0, DB::table('audit_logs')->count());
    }

    public function test_writes_into_another_organisation_are_refused(): void
    {
        $this->enter(app(OrganizationContext::class)->primaryId(), true);

        $refused = false;
        try {
            // Savepoint: transaksi test tetap bisa dipakai setelah penolakan.
            DB::transaction(fn () => DB::table('odps')->insert([
                'snmp_olt_id' => $this->oltB->id, 'name' => 'SELIPAN', 'created_at' => now(), 'updated_at' => now(),
            ]));
        } catch (QueryException $e) {
            $refused = str_contains($e->getMessage(), 'row-level security');
        }

        $this->assertTrue($refused, 'insert into another organisation must violate the RLS policy');
    }

    public function test_updates_cannot_reach_another_organisation(): void
    {
        $this->enter(app(OrganizationContext::class)->primaryId(), true);

        $this->assertSame(0, DB::table('snmp_olts')->where('id', $this->oltB->id)->update(['name' => 'DIUBAH']));

        app(TenantDatabase::class)->leave();
        $this->assertSame('OLT-B', DB::table('snmp_olts')->where('id', $this->oltB->id)->value('name'));
    }

    public function test_installation_rows_only_for_the_installation_organisation(): void
    {
        DB::table('audit_logs')->insert(['event' => 'updated', 'description' => 'entri instalasi', 'created_at' => now()]);

        $this->enter($this->b->id, false);
        $this->assertSame(0, DB::table('audit_logs')->whereNull('organization_id')->count());

        $this->enter(app(OrganizationContext::class)->primaryId(), true);
        $this->assertSame(1, DB::table('audit_logs')->whereNull('organization_id')->count());
    }

    public function test_console_context_sees_every_organisation(): void
    {
        // Owner role (scheduler, queue, poller): no tenant role, every OLT.
        $this->assertSame(2, DB::table('snmp_olts')->count());
    }

    public function test_a_web_request_runs_in_the_tenant_role_and_resets_it(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin->value]);
        $owner = DB::selectOne('select current_user as u')->u;
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $this->actingAs($admin)->get(route('smartolt.edit', $this->oltA))->assertOk();
        $this->assertContains('SET ROLE '.TenantDatabase::quotedRole(), $statements);
        $this->assertContains('RESET ROLE', $statements);
        // RLS answers 404 by itself for B's OLT, even before the app scope.
        $this->actingAs($admin)->get(route('smartolt.edit', $this->oltB))->assertNotFound();

        $this->assertSame($owner, DB::selectOne('select current_user as u')->u);
        $this->assertSame('', (string) DB::selectOne("select current_setting('app.org_id', true) as v")->v);
    }

    public function test_public_status_is_scoped_by_rls_to_the_installation_organisation(): void
    {
        $body = $this->getJson('/api/v1/public/status')->assertOk()->getContent();

        $this->assertStringContainsString('OLT-A', $body);
        $this->assertStringNotContainsString('OLT-B', $body);
    }

    public function test_login_and_failed_login_are_audited_from_a_guest_request(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin->value, 'password' => bcrypt('rahasia-uji')]);
        DB::table('organization_user')->where('user_id', $user->id)->update(['organization_id' => $this->b->id]);
        app(OrganizationContext::class)->forget();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'salah'])->assertSessionHasErrors();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'rahasia-uji'])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id, 'organization_id' => $this->b->id]);
        // Login gagal tanpa aktor = baris instalasi (UI-F1a); tetap tertulis dari request tamu.
        $this->assertDatabaseHas('audit_logs', ['event' => 'login_failed', 'organization_id' => null]);
        $this->assertSame('', (string) DB::selectOne("select current_setting('app.org_id', true) as v")->v);
    }

    private function enter(?string $organizationId, bool $installation): void
    {
        $database = app(TenantDatabase::class);
        $database->leave();
        $database->enter($organizationId, $installation);
    }

    private function olt(string $name, string $ip): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => $name, 'vendor' => 'ZTE C320', 'ip' => $ip, 'snmp_port' => 161,
            'snmp_read_community' => 'public', 'snmp_version' => 'v2c', 'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => []],
        ]);
    }
}
