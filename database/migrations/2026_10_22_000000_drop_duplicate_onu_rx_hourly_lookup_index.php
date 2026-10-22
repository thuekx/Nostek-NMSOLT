<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NMS-07-B: onu_rx_hourly_lookup_idx covers exactly the same five columns, in
 * the same order, as the unique index onu_rx_hourly_bucket_unique. The unique
 * index already serves the chart lookups; the copy only doubles write cost and
 * disk use on every hourly aggregation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('onu_rx_hourly') || ! Schema::hasIndex('onu_rx_hourly', 'onu_rx_hourly_lookup_idx')) {
            return;
        }

        Schema::table('onu_rx_hourly', function (Blueprint $table) {
            $table->dropIndex('onu_rx_hourly_lookup_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('onu_rx_hourly') || Schema::hasIndex('onu_rx_hourly', 'onu_rx_hourly_lookup_idx')) {
            return;
        }

        Schema::table('onu_rx_hourly', function (Blueprint $table) {
            $table->index(
                ['snmp_olt_id', 'slot', 'port', 'onu_id', 'bucket_hour'],
                'onu_rx_hourly_lookup_idx'
            );
        });
    }
};
