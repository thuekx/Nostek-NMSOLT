<?php

namespace App\Console\Commands;

use App\Support\TenantDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * UI-F1d: is PostgreSQL Row Level Security in force? For the deploy VERIFY step and the launch gate.
 *
 * PASS = the tenant role exists without BYPASSRLS/superuser, and every table with `snmp_olt_id` or
 * `organization_id` (except the membership tables) has RLS enabled and a `nostek_org` policy.
 * Prints one RESULT line; exit 1 on FAIL. SQLite = SKIP (exit 0).
 */
class RlsStatusCommand extends Command
{
    /** Needed to find the organisation before a request enters the tenant role. */
    public const EXEMPT = ['organization_user', 'organizations'];

    protected $signature = 'nmsolt:rls-status';

    protected $description = 'Show whether PostgreSQL Row Level Security (UI-F1d) is in force';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->line('RESULT nmsolt-rls SKIP driver='.DB::getDriverName());

            return self::SUCCESS;
        }

        $role = DB::selectOne('select rolsuper, rolbypassrls from pg_roles where rolname = ?', [TenantDatabase::role()]);
        $problems = [];

        if ($role === null) {
            $problems[] = 'role '.TenantDatabase::role().' missing';
        } elseif ($role->rolsuper || $role->rolbypassrls) {
            $problems[] = 'role '.TenantDatabase::role().' bypasses RLS';
        }

        if (! config('nostek.rls.enabled', true)) {
            $problems[] = 'NMSOLT_RLS_ENABLED=false';
        }

        $tables = static::coveredTables();
        foreach ($tables as $table) {
            $state = DB::selectOne(
                "select c.relrowsecurity as rls,
                        exists (select 1 from pg_policies p where p.schemaname = 'public' and p.tablename = c.relname and p.policyname = 'nostek_org') as policy
                 from pg_class c join pg_namespace n on n.oid = c.relnamespace
                 where n.nspname = 'public' and c.relname = ?",
                [$table],
            );

            if (! ($state->rls ?? false) || ! ($state->policy ?? false)) {
                $problems[] = "table {$table} without RLS policy";
            }
        }

        foreach ($problems as $problem) {
            $this->line("  - {$problem}");
        }

        if ($problems !== []) {
            $this->line('RESULT nmsolt-rls FAIL problems='.count($problems));

            return self::FAILURE;
        }

        $this->line('RESULT nmsolt-rls PASS tables='.count($tables).' role='.TenantDatabase::role());

        return self::SUCCESS;
    }

    /**
     * Every table that holds organisation data and must be under RLS.
     *
     * @return list<string>
     */
    public static function coveredTables(): array
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('column_name', ['snmp_olt_id', 'organization_id', 'alarm_event_id'])
            ->whereNotIn('table_name', self::EXEMPT)
            ->distinct()
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();
    }
}
