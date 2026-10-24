<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * UI-F1a: organisations (the ISP that owns OLTs) and sites (POP/area inside an organisation).
 *
 * Keys match the Control Center (org_id + site key), so CC can link an NMSOLT organisation by
 * `cc_org_id` and a site by `key`. Every row that hangs off an OLT keeps only `snmp_olt_id`: the
 * organisation comes through the OLT, so no second column can drift.
 *
 * Backfill: one organisation (config nostek.organization, `is_installation`) gets every existing OLT and user, so an
 * existing installation sees no change. Roles map admin → org_admin, demo → viewer, others → operator
 * (partners move to their own organisation in F1c).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('kind', 16)->default('isp'); // isp | reseller | demo
            $table->uuid('cc_org_id')->nullable()->unique();
            $table->string('status', 16)->default('active');
            // The installation's own organisation (exactly one, set here): owns installation-level
            // rows and settings. Never set from the UI or API.
            $table->boolean('is_installation')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('sites', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('key', 64); // = CC site_key, e.g. "juwana"
            $table->string('name');
            $table->string('kind', 16)->default('pop'); // pop | datacenter | area
            $table->string('region')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        Schema::create('organization_user', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->constrained('organizations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 16)->default('operator'); // org_admin | operator | technician | viewer
            $table->timestamps();
            $table->primary(['organization_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('current_organization_id')->nullable()->after('role')
                ->constrained('organizations')->nullOnDelete();
        });

        Schema::table('snmp_olts', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('id')
                ->constrained('organizations')->restrictOnDelete();
            $table->foreignUuid('site_id')->nullable()->after('organization_id')
                ->constrained('sites')->nullOnDelete();
            $table->index('organization_id');
        });

        // Audit rows carry the organisation of their subject (or actor); NULL = installation-level.
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('user_id')
                ->constrained('organizations')->nullOnDelete();
            $table->index(['organization_id', 'created_at']);
        });

        $orgId = (string) Str::uuid();
        $now = now();

        DB::table('organizations')->insert([
            'id' => $orgId,
            'slug' => Str::slug((string) config('nostek.organization.slug', 'default')) ?: 'default',
            'name' => (string) config('nostek.organization.name', 'NMSOLT'),
            'kind' => 'isp',
            'status' => 'active',
            'is_installation' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('snmp_olts')->update(['organization_id' => $orgId]);
        DB::table('users')->update(['current_organization_id' => $orgId]);
        DB::table('audit_logs')->update(['organization_id' => $orgId]);

        DB::table('users')->orderBy('id')->select(['id', 'role'])->chunk(500, function ($users) use ($orgId, $now) {
            DB::table('organization_user')->insert($users->map(fn ($user) => [
                'organization_id' => $orgId,
                'user_id' => $user->id,
                'role' => match ($user->role) {
                    'admin' => 'org_admin',
                    'demo' => 'viewer',
                    default => 'operator',
                },
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        // Every OLT now has an organisation; new ones get it from App\Models\SnmpOlt::creating.
        Schema::table('snmp_olts', function (Blueprint $table) {
            $table->uuid('organization_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id', 'created_at']);
            $table->dropColumn('organization_id');
        });

        Schema::table('snmp_olts', function (Blueprint $table) {
            $table->dropForeign(['site_id']);
            $table->dropForeign(['organization_id']);
            $table->dropIndex(['organization_id']);
            $table->dropColumn(['site_id', 'organization_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropColumn('current_organization_id');
        });

        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('sites');
        Schema::dropIfExists('organizations');
    }
};
