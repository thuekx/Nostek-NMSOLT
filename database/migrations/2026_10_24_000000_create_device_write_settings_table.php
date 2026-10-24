<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WRITE-UI-1: the device write switch an admin sets in the UI. Rows:
 * - scope "global": the master switch (one row), with an optional auto-off time;
 * - scope "channel": one row per write channel (olt = SNMP set / CLI / telnet through the OLT,
 *   acs = GenieACS / TR-069 to the ONT);
 * - scope "olt": a per-OLT block (enabled = false), absent = follows the global switch.
 * A missing row means off (global, channel) or "follow global" (olt). The installation env
 * NMSOLT_DEVICE_WRITES_ENABLED stays the upper limit (false = read-only instance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_write_settings', function (Blueprint $table) {
            $table->id();
            // "global", "channel:olt", "channel:acs" or "olt:<id>": unique even where the
            // nullable columns are NULL (PostgreSQL treats NULLs as distinct in a unique index).
            $table->string('key', 40)->unique();
            $table->string('scope', 16);
            $table->string('channel', 16)->nullable();
            $table->foreignId('snmp_olt_id')->nullable()->constrained('snmp_olts')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->string('reason', 500)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_write_settings');
    }
};
