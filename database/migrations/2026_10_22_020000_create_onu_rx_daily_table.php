<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NMS-07-B2: daily RX rollup per ONU (owner decision NMS-AI-Q5 A: hourly kept 400 days,
 * daily kept 3 years). Built from onu_rx_hourly by optical:aggregate-rx-daily; one row per
 * ONU per day is ~1/24 of the hourly cost, so three years of history stays small.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onu_rx_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snmp_olt_id')->constrained('snmp_olts')->cascadeOnDelete();
            $table->unsignedSmallInteger('slot');
            $table->unsignedSmallInteger('port');
            $table->unsignedSmallInteger('onu_id');
            $table->string('serial_number', 64)->nullable();
            $table->date('bucket_day');
            $table->decimal('rx_min_dbm', 6, 2);
            $table->decimal('rx_avg_dbm', 6, 2);
            $table->decimal('rx_max_dbm', 6, 2);
            $table->unsignedInteger('sample_count');

            // Upsert key and the chart lookup (one ONU over a date range) in one index.
            $table->unique(['snmp_olt_id', 'slot', 'port', 'onu_id', 'bucket_day'], 'onu_rx_daily_bucket_unique');
            $table->index('bucket_day');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onu_rx_daily');
    }
};
