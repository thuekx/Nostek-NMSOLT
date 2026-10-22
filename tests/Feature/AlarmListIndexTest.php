<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * NMS-07-E3: the alarm page, bell and summary filter by status and sort by last_seen_at;
 * without these indexes PostgreSQL sorted the whole table on every view.
 */
class AlarmListIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_alarm_list_indexes_exist_and_roll_back(): void
    {
        $this->assertTrue(Schema::hasIndex('alarm_events', 'alarm_events_status_last_seen_idx'));
        $this->assertTrue(Schema::hasIndex('alarm_events', 'alarm_events_last_seen_idx'));

        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_10_22_030000_add_alarm_events_list_indexes.php', '--force' => true]);
        $this->assertFalse(Schema::hasIndex('alarm_events', 'alarm_events_status_last_seen_idx'));
        $this->assertFalse(Schema::hasIndex('alarm_events', 'alarm_events_last_seen_idx'));
    }
}
