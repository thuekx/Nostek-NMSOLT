<?php

use App\Support\TenantDatabase;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * UI-F1d: PostgreSQL Row Level Security under the app scope (owner decision D3, 2026-10-25:
 * straight after the app scope, before launch).
 *
 * - Role `nmsolt_tenant` (config nostek.rls.role): NOLOGIN, no BYPASSRLS, DML on every table.
 *   Web/API requests `SET ROLE` to it ({@see TenantDatabase}); console/queue stay the owner.
 * - Policy `nostek_org` on:
 *   - snmp_olts, sites: organization_id = app.org_id;
 *   - every table with snmp_olt_id: the OLT is visible (subquery on snmp_olts, itself under RLS);
 *     rows without an OLT only for the installation organisation (app.installation = on);
 *   - audit_logs: own organisation, NULL rows only for the installation organisation;
 *   - alarm_notification_reads: through alarm_events.
 * - Not under RLS: users, organizations, organization_user (needed to find the organisation),
 *   installation settings, sessions/cache/jobs.
 *
 * Needs a role that may create roles (the Docker POSTGRES_USER is a superuser). Without that
 * right the migration skips RLS with a warning instead of blocking the update; the app scope stays
 * in force and `php artisan nmsolt:rls-status` reports FAIL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $can = DB::selectOne('select rolsuper or rolcreaterole as ok from pg_roles where rolname = current_user');
        if (! ($can->ok ?? false)) {
            Log::warning('UI-F1d: database user cannot create roles; Row Level Security NOT enabled (app scope only).');

            return;
        }

        $role = TenantDatabase::role();
        $quoted = TenantDatabase::quotedRole();

        DB::statement(<<<SQL
            DO \$\$
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '{$role}') THEN
                    CREATE ROLE {$quoted} NOLOGIN NOSUPERUSER NOBYPASSRLS NOCREATEDB NOCREATEROLE;
                END IF;
            END
            \$\$
            SQL);

        // A non-superuser owner must be a member to SET ROLE; a superuser may anyway.
        DB::statement("GRANT {$quoted} TO CURRENT_USER");
        DB::statement("GRANT USAGE ON SCHEMA public TO {$quoted}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO {$quoted}");
        DB::statement("GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO {$quoted}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO {$quoted}");
        DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO {$quoted}");

        foreach ($this->policies() as $table => $condition) {
            DB::statement("ALTER TABLE \"{$table}\" ENABLE ROW LEVEL SECURITY");
            DB::statement("DROP POLICY IF EXISTS nostek_org ON \"{$table}\"");
            DB::statement("CREATE POLICY nostek_org ON \"{$table}\" FOR ALL TO {$quoted} USING ({$condition}) WITH CHECK ({$condition})");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys($this->policies()) as $table) {
            DB::statement("DROP POLICY IF EXISTS nostek_org ON \"{$table}\"");
            DB::statement("ALTER TABLE \"{$table}\" DISABLE ROW LEVEL SECURITY");
        }

        // The role is cluster-wide (other databases may use it): only this database's grants go.
        if (DB::selectOne('select 1 as ok from pg_roles where rolname = ?', [TenantDatabase::role()])) {
            $quoted = TenantDatabase::quotedRole();
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM {$quoted}");
            DB::statement("ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM {$quoted}");
            DB::statement("REVOKE ALL ON ALL TABLES IN SCHEMA public FROM {$quoted}");
            DB::statement("REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM {$quoted}");
            DB::statement("REVOKE USAGE ON SCHEMA public FROM {$quoted}");
        }
    }

    /**
     * Table => policy condition. Every table with `snmp_olt_id` is included automatically, so a
     * table added before this migration cannot be missed; later tables get their policy in their
     * own migration (tests/Feature/RowLevelSecurityTest checks every table).
     *
     * @return array<string, string>
     */
    private function policies(): array
    {
        $org = "NULLIF(current_setting('app.org_id', true), '')::uuid";
        $installation = "current_setting('app.installation', true) = 'on'";

        $policies = [
            'snmp_olts' => "organization_id = {$org}",
            'sites' => "organization_id = {$org}",
            'audit_logs' => "(organization_id = {$org} OR (organization_id IS NULL AND {$installation}))",
            'alarm_notification_reads' => 'alarm_event_id IN (SELECT id FROM alarm_events)',
        ];

        $tables = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'snmp_olt_id')
            ->orderBy('table_name')
            ->pluck('table_name');

        foreach ($tables as $table) {
            $policies[$table] = "(snmp_olt_id IN (SELECT id FROM snmp_olts) OR (snmp_olt_id IS NULL AND {$installation}))";
        }

        return $policies;
    }
};
