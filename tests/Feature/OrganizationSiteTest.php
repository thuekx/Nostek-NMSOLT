<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AlarmEvent;
use App\Models\Organization;
use App\Models\Site;
use App\Models\SnmpOlt;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * UI-F1b: site organisasi, penempatan OLT ke site, pemilih "Organisasi ▸ Site" di header, dan
 * penyaring site pada halaman ringkasan. Site organisasi lain tidak ada (404).
 */
class OrganizationSiteTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private int $ip = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    }

    public function test_admin_manages_sites_of_own_organisation(): void
    {
        $this->actingAs($this->admin)->post(route('organization.sites.store'), [
            'key' => 'juwana', 'name' => 'POP Juwana', 'kind' => 'pop', 'region' => 'Pati', 'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasNoErrors();

        $site = Site::query()->where('key', 'juwana')->firstOrFail();
        $this->assertSame(app(OrganizationContext::class)->primaryId(), $site->organization_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created', 'description' => 'Menambahkan Site POP Juwana',
            'organization_id' => $site->organization_id, 'user_id' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)->put(route('organization.sites.update', $site), [
            'key' => 'juwana', 'name' => 'POP Juwana Kota', 'kind' => 'area', 'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasNoErrors();
        $this->assertSame('POP Juwana Kota', $site->fresh()->name);

        $this->actingAs($this->admin)->get(route('organization.index'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Organization/Index')
                ->where('sites.0.key', 'juwana')
                ->where('sites.0.olt_count', 0));
    }

    public function test_site_key_is_validated_and_unique_per_organisation(): void
    {
        $this->site('pati', 'Pati');

        $this->actingAs($this->admin)->post(route('organization.sites.store'), [
            'key' => 'pati', 'name' => 'Lagi', 'kind' => 'pop', 'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasErrors('key');

        $this->actingAs($this->admin)->post(route('organization.sites.store'), [
            'key' => 'Bukan Kunci!', 'name' => 'x', 'kind' => 'pop', 'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasErrors('key');

        // Kunci yang sama boleh di organisasi lain.
        [$adminB] = $this->otherOrganisation();
        $this->actingAs($adminB)->post(route('organization.sites.store'), [
            'key' => 'pati', 'name' => 'Pati B', 'kind' => 'pop', 'timezone' => 'Asia/Jakarta',
        ])->assertSessionHasNoErrors();
    }

    public function test_sites_and_olts_of_another_organisation_are_404(): void
    {
        [$adminB, $orgB] = $this->otherOrganisation();
        $siteA = $this->site('juwana', 'Juwana');
        $oltA = $this->olt('OLT-A');

        $this->actingAs($adminB)->put(route('organization.sites.update', $siteA), [
            'key' => 'x', 'name' => 'x', 'kind' => 'pop', 'timezone' => 'Asia/Jakarta',
        ])->assertNotFound();
        $this->actingAs($adminB)->delete(route('organization.sites.destroy', $siteA))->assertNotFound();
        $this->actingAs($adminB)->put(route('organization.olts.site', $oltA), ['site_id' => null])->assertNotFound();

        // Site organisasi A tidak bisa dipakai untuk OLT atau pilihan header organisasi B.
        $this->actingAs($adminB);
        $oltB = $this->olt('OLT-B');
        $this->put(route('organization.olts.site', $oltB), ['site_id' => $siteA->id])->assertSessionHasErrors('site_id');
        $this->put(route('organization.site'), ['site_id' => $siteA->id])->assertSessionHasErrors('site_id');

        $this->assertNotNull($siteA->fresh());
        $this->assertSame($orgB->id, $oltB->fresh()->organization_id);
    }

    public function test_assign_olt_to_site_and_delete_site_keeps_the_olt(): void
    {
        $site = $this->site('juwana', 'Juwana');
        $olt = $this->olt('OLT-1');

        $this->actingAs($this->admin)->put(route('organization.olts.site', $olt), ['site_id' => $site->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($site->id, $olt->fresh()->site_id);

        $this->actingAs($this->admin)->delete(route('organization.sites.destroy', $site))->assertRedirect();
        $this->assertNull($olt->fresh()->site_id);
        $this->assertNotNull($olt->fresh());
    }

    public function test_site_id_cannot_come_from_olt_input(): void
    {
        $olt = new SnmpOlt(['name' => 'x', 'site_id' => 'f00df00d-0000-4000-8000-000000000000']);

        $this->assertNull($olt->site_id);
    }

    public function test_non_admin_cannot_manage_sites_but_can_pick_one(): void
    {
        $operator = User::factory()->create(['role' => UserRole::Operator->value]);
        $site = $this->site('juwana', 'Juwana');

        $this->actingAs($operator)->get(route('organization.index'))->assertForbidden();
        $this->actingAs($operator)->post(route('organization.sites.store'), [])->assertForbidden();

        $this->actingAs($operator)->put(route('organization.site'), ['site_id' => $site->id])->assertRedirect();
        $this->assertSame($site->id, $operator->fresh()->current_site_id);
    }

    public function test_picker_is_hidden_for_a_single_organisation_without_sites(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('org_context.show_picker', false)
                ->where('org_context.site_id', null));

        $this->site('juwana', 'Juwana');
        $this->site('pati', 'Pati');

        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('org_context.show_picker', true)
                ->has('org_context.sites', 2));
    }

    public function test_switch_organisation_only_to_a_membership(): void
    {
        [, $orgB] = $this->otherOrganisation();

        $this->actingAs($this->admin)->put(route('organization.switch'), ['organization_id' => $orgB->id])->assertNotFound();

        DB::table('organization_user')->insert([
            'organization_id' => $orgB->id, 'user_id' => $this->admin->id, 'role' => 'operator',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        app(OrganizationContext::class)->forget();

        $this->actingAs($this->admin)->put(route('organization.switch'), ['organization_id' => $orgB->id])
            ->assertRedirect(route('dashboard'));
        $this->assertSame($orgB->id, $this->admin->fresh()->current_organization_id);

        $this->actingAs($this->admin->fresh())->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('org_context.current.id', $orgB->id)
                ->has('org_context.organizations', 2)
                ->where('org_context.show_picker', true));
    }

    public function test_selected_site_filters_overview_pages_but_not_detail_pages(): void
    {
        $juwana = $this->site('juwana', 'Juwana');
        $pati = $this->site('pati', 'Pati');
        $oltJuwana = $this->olt('OLT-JUWANA', $juwana);
        $oltPati = $this->olt('OLT-PATI', $pati);
        $this->alarm($oltPati, 'LOS di Pati');

        $this->admin->forceFill(['current_site_id' => $juwana->id])->save();

        $this->actingAs($this->admin)->get(route('network.tree'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('olts', 1)
                ->where('olts.0.name', 'OLT-JUWANA'));

        $this->actingAs($this->admin)->get(route('alarms.index'))->assertOk()->assertDontSee('LOS di Pati');
        $this->actingAs($this->admin)->get(route('smartolt.index'))->assertOk()->assertDontSee('OLT-PATI');

        // Detail OLT site lain tetap terbuka (site = penyaring, bukan batas akses).
        $this->actingAs($this->admin)->get(route('smartolt.edit', $oltPati))->assertOk();

        // "Semua site" mengembalikan semuanya.
        $this->actingAs($this->admin)->put(route('organization.site'), ['site_id' => null]);
        $this->actingAs($this->admin->fresh())->get(route('network.tree'))
            ->assertInertia(fn (Assert $page) => $page->has('olts', 2));
        $this->actingAs($this->admin->fresh())->get(route('alarms.index'))->assertSee('LOS di Pati');

        $this->assertNotNull($oltJuwana);
    }

    public function test_api_is_not_filtered_by_the_selected_site(): void
    {
        $juwana = $this->site('juwana', 'Juwana');
        $this->olt('OLT-JUWANA', $juwana);
        $this->olt('OLT-TANPA-SITE');
        $this->admin->forceFill(['current_site_id' => $juwana->id])->save();

        $this->actingAs($this->admin, 'sanctum')->getJson('/api/v1/olts')->assertOk()
            ->assertSee('OLT-TANPA-SITE');
    }

    /**
     * @return array{0: User, 1: Organization}
     */
    private function otherOrganisation(): array
    {
        $org = Organization::create(['slug' => 'isp-b', 'name' => 'ISP B']);
        $user = User::factory()->create(['role' => UserRole::Admin->value]);
        DB::table('organization_user')->where('user_id', $user->id)->delete();
        DB::table('organization_user')->insert([
            'organization_id' => $org->id, 'user_id' => $user->id, 'role' => 'org_admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user->forceFill(['current_organization_id' => $org->id])->saveQuietly();
        app(OrganizationContext::class)->forget();

        return [$user, $org];
    }

    private function site(string $key, string $name): Site
    {
        $site = new Site(['key' => $key, 'name' => $name, 'kind' => 'pop', 'timezone' => 'Asia/Jakarta']);
        $site->organization_id = app(OrganizationContext::class)->primaryId();
        $site->save();

        return $site;
    }

    private function olt(string $name, ?Site $site = null): SnmpOlt
    {
        $olt = SnmpOlt::create([
            'name' => $name,
            'vendor' => 'ZTE C320',
            'ip' => '10.71.0.'.(++$this->ip),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'port_onus' => []],
        ]);

        if ($site) {
            $olt->forceFill(['site_id' => $site->id])->saveQuietly();
        }

        return $olt;
    }

    private function alarm(SnmpOlt $olt, string $message): AlarmEvent
    {
        return AlarmEvent::create([
            'snmp_olt_id' => $olt->id, 'signature' => uniqid('sig', true), 'type' => AlarmEvent::TYPE_LOS,
            'severity' => AlarmEvent::SEVERITY_CRITICAL, 'status' => AlarmEvent::STATUS_ACTIVE, 'scope' => 'onu',
            'slot' => 1, 'port' => 1, 'onu_id' => 1, 'message' => $message,
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);
    }
}
