<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TRF-1: PON port traffic history. The poller reads IF-MIB ifHCIn/OutOctets for each GPON port;
 * the poll job turns two successive counter readings into a rate (bits per second) and writes one
 * row per port per poll. Rates, not counters, are stored: a counter is only useful next to the
 * previous one, and that previous reading already sits in snmp_olts.last_test_result.
 *
 * Retention follows the RX pattern: raw rows for the 24 h / 7 d charts, an hourly rollup (avg +
 * peak) for 30–90 days, a daily rollup for 1–3 years. A PON port is far cheaper than an ONU:
 * a C300 with 128 ports writes ~37k raw rows a day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pon_traffic_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snmp_olt_id')->constrained('snmp_olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot');
            $table->unsignedSmallInteger('port');
            $table->unsignedBigInteger('in_bps');
            $table->unsignedBigInteger('out_bps');
            $table->timestamp('polled_at');

            $table->index(['snmp_olt_id', 'slot', 'port', 'polled_at'], 'pon_traffic_samples_lookup');
            $table->index('polled_at');
        });

        foreach (['pon_traffic_hourly' => 'bucket_hour', 'pon_traffic_daily' => 'bucket_day'] as $name => $bucket) {
            Schema::create($name, function (Blueprint $table) use ($name, $bucket) {
                $table->id();
                $table->foreignId('snmp_olt_id')->constrained('snmp_olts')->cascadeOnDelete();
                $table->unsignedSmallInteger('slot');
                $table->unsignedSmallInteger('port');
                $bucket === 'bucket_day' ? $table->date($bucket) : $table->timestamp($bucket);
                $table->unsignedBigInteger('in_avg_bps');
                $table->unsignedBigInteger('in_max_bps');
                $table->unsignedBigInteger('out_avg_bps');
                $table->unsignedBigInteger('out_max_bps');
                $table->unsignedInteger('sample_count');

                // Upsert key and the chart lookup (one port over a time range) in one index.
                $table->unique(['snmp_olt_id', 'slot', 'port', $bucket], "{$name}_bucket_unique");
                $table->index($bucket);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pon_traffic_daily');
        Schema::dropIfExists('pon_traffic_hourly');
        Schema::dropIfExists('pon_traffic_samples');
    }
};
