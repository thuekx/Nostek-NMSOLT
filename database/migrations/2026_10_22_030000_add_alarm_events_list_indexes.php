<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NMS-07-E3: the alarm page, the notification bell and the active summary all filter by
 * status and sort by last_seen_at. Without an index PostgreSQL sorted the whole table on
 * every page view (measured in docs/nostek/NMS-07-PERFORMANCE.md). Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alarm_events', function (Blueprint $table) {
            $table->index(['status', 'last_seen_at'], 'alarm_events_status_last_seen_idx');
            $table->index('last_seen_at', 'alarm_events_last_seen_idx');
        });
    }

    public function down(): void
    {
        Schema::table('alarm_events', function (Blueprint $table) {
            $table->dropIndex('alarm_events_status_last_seen_idx');
            $table->dropIndex('alarm_events_last_seen_idx');
        });
    }
};
