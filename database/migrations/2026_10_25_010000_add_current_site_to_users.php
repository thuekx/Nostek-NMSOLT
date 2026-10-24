<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UI-F1b: the site a user filters by (header picker "Organisation ▸ Site"), remembered per user.
 * NULL = all sites. A filter, not a security boundary (the organisation is the boundary).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('current_site_id')->nullable()->after('current_organization_id')
                ->constrained('sites')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_site_id']);
            $table->dropColumn('current_site_id');
        });
    }
};
