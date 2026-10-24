<?php

namespace Tests\Feature;

use App\Models\SnmpOlt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

/**
 * NMS-07-D-2c: permission states per role (admin, operator, partner, demo).
 *
 * - Every sidebar link a role sees opens (200), so the UI never leads into a 403.
 * - Every signed-in GET page without parameters, and every page of an OLT the role can see,
 *   answers without a server error.
 * - A page a role is refused is not in that role's sidebar.
 */
class RoleJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const LAYOUT = 'resources/js/Layouts/AuthenticatedLayout.vue';

    /** Sidebar of AuthenticatedLayout.vue, by role (mirrors its `can` conditions). */
    private const COMMON_NAV = [
        'dashboard', 'smartolt.index', 'smartolt.unconfigured-all', 'monitoring.onu', 'network.tree',
        'map.index', 'odp.index', 'field.index', 'alarms.index', 'reports.index', 'panduan',
    ];

    private const ROLE_NAV = [
        'admin' => ['users.index', 'organization.index', 'audit-logs.index', 'settings.edit'],
        'operator' => [],
        'partner' => ['partner.telegram.edit'],
        'demo' => [],
    ];

    private int $ip = 1;

    /**
     * @return array<string, array{0: User, 1: SnmpOlt}>
     */
    private function roles(): array
    {
        $admin = User::factory()->admin()->create();
        $operator = User::factory()->create();
        $partner = User::factory()->partner()->create();
        $demo = User::factory()->demo()->create();

        return [
            'admin' => [$admin, $this->olt()],
            'operator' => [$operator, $this->olt()],
            'partner' => [$partner, $this->olt(['owner_user_id' => $partner->id])],
            'demo' => [$demo, $this->olt(['is_demo' => true])],
        ];
    }

    private function olt(array $attributes = []): SnmpOlt
    {
        $olt = SnmpOlt::query()->withoutGlobalScopes()->create([
            'name' => 'OLT-ROLE-'.$this->ip,
            'vendor' => 'ZTE C320',
            'ip' => '10.50.0.'.(++$this->ip),
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
            'last_polled_at' => now(),
            'last_test_result' => ['ok' => true, 'onu_scanned_at' => now()->toIso8601String(), 'port_onus' => []],
        ]);
        // is_demo / owner_user_id may be guarded: set them explicitly.
        $olt->forceFill($attributes)->save();

        return $olt;
    }

    public function test_the_role_nav_list_matches_the_layout(): void
    {
        preg_match_all("/href: route\\('([a-z0-9.\\-]+)'\\)/", (string) file_get_contents(base_path(self::LAYOUT)), $m);
        $expected = array_unique(array_merge(self::COMMON_NAV, ...array_values(self::ROLE_NAV)));
        sort($expected);
        $layout = array_unique($m[1]);
        sort($layout);

        $this->assertSame($expected, $layout, 'a sidebar link was added or removed: update ROLE_NAV / COMMON_NAV');
    }

    public function test_every_sidebar_link_opens_for_its_role(): void
    {
        foreach ($this->roles() as $role => [$user]) {
            foreach ([...self::COMMON_NAV, ...self::ROLE_NAV[$role]] as $name) {
                $this->actingAs($user)->get(route($name))
                    ->assertStatus(200, "{$role} → {$name}");
            }
        }
    }

    public function test_no_page_fails_with_a_server_error_and_refused_pages_are_not_linked(): void
    {
        $checked = 0;
        foreach ($this->roles() as $role => [$user, $olt]) {
            $nav = [...self::COMMON_NAV, ...self::ROLE_NAV[$role]];
            foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
                /** @var Route $route */
                if (! in_array('GET', $route->methods(), true) || ! in_array('auth', $route->gatherMiddleware(), true)) {
                    continue;
                }
                $params = $route->parameterNames();
                if ($params !== [] && $params !== ['olt']) {
                    continue;
                }
                $uri = '/'.ltrim(str_replace('{olt}', (string) $olt->id, $route->uri()), '/');
                $status = $this->actingAs($user)->get($uri)->getStatusCode();
                $checked++;

                $this->assertLessThan(500, $status, "{$role} GET {$uri} → {$status}");
                if (in_array($status, [403, 404], true)) {
                    $this->assertNotContains($route->getName(), $nav, "{$role} sees a sidebar link to a refused page: {$uri}");
                }
            }
        }

        $this->assertGreaterThan(100, $checked, 'the route sweep must cover the app');
    }

    public function test_hidden_controls_match_server_permissions(): void
    {
        foreach ($this->roles() as $role => [$user]) {
            $can = $this->actingAs($user)->get(route('dashboard'))->viewData('page')['props']['auth']['can'];
            $create = $this->actingAs($user)->get(route('smartolt.create'))->getStatusCode();
            $acs = $this->actingAs($user)->getJson(route('genieacs.devices.search'))->getStatusCode();

            // The "Add OLT" button shows only with add_olt; the ACS pin search only with manage_acs.
            $this->assertSame($can['add_olt'], $create === 200, "{$role}: add_olt={$can['add_olt']} but create={$create}");
            $this->assertSame($can['manage_acs'], $acs !== 403, "{$role}: manage_acs={$can['manage_acs']} but search={$acs}");
        }
    }
}
