<?php

namespace Tests\Feature;

use App\Models\SnmpOlt;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GlobalSearchReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function olt(int $number, array $onus = []): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => 'Fixture-'.$number,
            'vendor' => 'ZTE C320',
            'ip' => '192.0.2.'.$number,
            'snmp_port' => 161,
            'snmp_version' => 'v2c',
            'last_test_result' => ['port_onus' => ['1_1' => [
                'slot' => 1, 'port' => 1, 'onus' => $onus,
            ]]],
        ]);
    }

    public function test_mac_only_match_keeps_the_web_navigation_filter(): void
    {
        $olt = $this->olt(1, [['onu_id' => 7, 'mac' => 'AA:BB:CC:DD:EE:FF', 'name' => '']]);
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson('/dashboard/search?q=AA:BB');
        $response->assertOk()->assertJsonPath('results.0.type', 'onu');
        $url = $response->json('results.0.url');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $query['q']);
        $this->assertSame('7', $query['focus']);
        $this->assertStringContainsString((string) $olt->id, $url);
    }

    public function test_search_stops_fetching_snapshots_after_first_full_batch(): void
    {
        $onus = array_map(fn ($id) => ['onu_id' => $id, 'name' => 'needle-'.$id], range(1, 10));
        $this->olt(1, $onus);
        foreach (range(2, 60) as $number) {
            $this->olt($number, [['onu_id' => 1, 'name' => 'needle-later']]);
        }
        $this->actingAs(User::factory()->admin()->create());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $results = app(GlobalSearchService::class)->search('needle');
        $queries = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'last_test_result'));
        DB::disableQueryLog();
        $this->assertCount(10, $results);
        $this->assertCount(2, $queries); // OLT name lookup + first telemetry batch only.
        $this->assertStringContainsString('limit 25', $queries->last()['query']);
    }

    public function test_later_batches_remain_searchable_and_partner_scopes_are_preserved(): void
    {
        foreach (range(1, 26) as $number) {
            $last = $this->olt($number, [['onu_id' => 1, 'name' => 'needle-'.$number]]);
        }
        $partner = User::factory()->partner()->create();
        $partner->partnerOlts()->sync([$last->id]);
        $this->actingAs($partner, 'sanctum')->getJson('/api/v1/search?q=needle')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.olt_id', $last->id);
        $this->actingAs(User::factory()->admin()->create());
        $results = app(GlobalSearchService::class)->search('needle-26');
        $this->assertCount(1, $results);
        $this->assertSame($last->id, $results[0]['olt_id']);
    }

    public function test_invalid_search_shapes_and_oversized_queries_are_rejected(): void
    {
        $user = User::factory()->create();
        foreach (['/dashboard/search', '/api/v1/search'] as $endpoint) {
            $this->actingAs($user, 'sanctum')->getJson($endpoint.'?q[]=needle')
                ->assertUnprocessable()->assertJsonValidationErrors('q');
            $this->getJson($endpoint.'?q='.str_repeat('x', 201))
                ->assertUnprocessable()->assertJsonValidationErrors('q');
        }
    }

    public function test_zero_limit_returns_no_results_and_small_limit_is_respected(): void
    {
        $this->olt(1);
        $this->olt(2);
        $this->actingAs(User::factory()->admin()->create());
        $search = app(GlobalSearchService::class);
        $this->assertSame([], $search->search('Fixture', 0));
        $this->assertCount(1, $search->search('Fixture', 1));
    }
}
