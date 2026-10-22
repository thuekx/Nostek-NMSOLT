<?php

namespace Tests\Feature;

use App\Jobs\PollOltJob;
use App\Models\AlarmEvent;
use App\Models\AuditLog;
use App\Models\OltConfigBackup;
use App\Models\PollingEvent;
use App\Models\SnmpOlt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * NMS-07-B: bounded growth of history tables, duplicate RX index removed,
 * OLT polls on their own queue.
 */
class HistoryPruneTest extends TestCase
{
    use RefreshDatabase;

    private int $host = 2;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function olt(): SnmpOlt
    {
        return SnmpOlt::create([
            'name' => 'PRUNE-OLT-'.$this->host,
            'vendor' => 'ZTE C320',
            'ip' => '10.70.0.'.$this->host++,
            'snmp_port' => 161,
            'snmp_read_community' => 'public',
            'snmp_version' => 'v2c',
        ]);
    }

    private function pollingEvent(SnmpOlt $olt, Carbon $at): PollingEvent
    {
        $event = PollingEvent::create(['snmp_olt_id' => $olt->id, 'kind' => 'poll', 'success' => true]);
        DB::table('polling_events')->where('id', $event->id)->update(['created_at' => $at, 'updated_at' => $at]);

        return $event;
    }

    private function alarm(SnmpOlt $olt, string $status, ?Carbon $clearedAt): AlarmEvent
    {
        return AlarmEvent::create([
            'snmp_olt_id' => $olt->id,
            'signature' => 'sig-'.uniqid(),
            'type' => 'onu_offline',
            'severity' => 'major',
            'status' => $status,
            'scope' => 'onu',
            'message' => 'synthetic',
            'first_seen_at' => now()->subYears(2),
            'last_seen_at' => now()->subYears(2),
            'cleared_at' => $clearedAt,
        ]);
    }

    private function audit(Carbon $at): AuditLog
    {
        $log = AuditLog::create(['event' => 'synthetic.event', 'description' => 'synthetic']);
        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $at]);

        return $log;
    }

    private function backup(SnmpOlt $olt, Carbon $at, string $status = 'ok'): OltConfigBackup
    {
        $row = new OltConfigBackup;
        $row->forceFill([
            'snmp_olt_id' => $olt->id,
            'content' => $status === 'ok' ? 'synthetic-config-'.$at->format('YmdHis') : null,
            'size_bytes' => 10,
            'sha256' => hash('sha256', $at->toIso8601String()),
            'trigger' => 'scheduled',
            'status' => $status,
            'captured_at' => $at,
        ])->save();

        return $row;
    }

    public function test_prunes_polling_events_cleared_alarms_and_audit_logs_past_retention(): void
    {
        Carbon::setTestNow('2026-10-22 03:30:00');
        $olt = $this->olt();

        $oldPoll = $this->pollingEvent($olt, now()->subDays(31));
        $newPoll = $this->pollingEvent($olt, now()->subDays(29));

        $oldCleared = $this->alarm($olt, AlarmEvent::STATUS_CLEARED, now()->subDays(181));
        $newCleared = $this->alarm($olt, AlarmEvent::STATUS_CLEARED, now()->subDays(179));
        $oldActive = $this->alarm($olt, AlarmEvent::STATUS_ACTIVE, null);
        $oldPending = $this->alarm($olt, AlarmEvent::STATUS_PENDING, null);

        $oldAudit = $this->audit(now()->subDays(401));
        $newAudit = $this->audit(now()->subDays(399));

        $this->artisan('history:prune')
            ->expectsOutputToContain('polling_events: pruned 1')
            ->expectsOutputToContain('alarm_events: pruned 1')
            ->expectsOutputToContain('audit_logs: pruned 1')
            ->assertSuccessful();

        $this->assertDatabaseMissing('polling_events', ['id' => $oldPoll->id]);
        $this->assertDatabaseHas('polling_events', ['id' => $newPoll->id]);
        $this->assertDatabaseMissing('alarm_events', ['id' => $oldCleared->id]);
        $this->assertDatabaseHas('alarm_events', ['id' => $newCleared->id]);
        // Active and pending alarms are never removed, however old.
        $this->assertDatabaseHas('alarm_events', ['id' => $oldActive->id]);
        $this->assertDatabaseHas('alarm_events', ['id' => $oldPending->id]);
        $this->assertDatabaseMissing('audit_logs', ['id' => $oldAudit->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $newAudit->id]);
    }

    public function test_dry_run_counts_without_deleting(): void
    {
        Carbon::setTestNow('2026-10-22 03:30:00');
        $olt = $this->olt();
        $this->pollingEvent($olt, now()->subDays(60));
        $this->audit(now()->subDays(500));
        // Creating the OLT writes its own (recent) audit row.
        $auditRows = DB::table('audit_logs')->count();

        $this->artisan('history:prune --dry-run')
            ->expectsOutputToContain('polling_events: would prune 1')
            ->expectsOutputToContain('audit_logs: would prune 1')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('polling_events')->count());
        $this->assertSame($auditRows, DB::table('audit_logs')->count());
    }

    public function test_config_backups_keep_latest_and_first_of_each_month(): void
    {
        Carbon::setTestNow('2026-10-22 03:30:00');
        config(['nostek.retention.config_backups_keep_latest' => 3, 'nostek.retention.config_backups_monthly_months' => 2]);
        $olt = $this->olt();
        $other = $this->olt();

        // Five daily backups this month: the newest three stay, plus the month's first.
        $daily = [];
        foreach ([1, 2, 3, 4, 5] as $day) {
            $daily[$day] = $this->backup($olt, Carbon::parse("2026-10-{$day} 02:30:00"));
        }
        // September: first successful one stays (a failed one before it does not count).
        $sepFailed = $this->backup($olt, Carbon::parse('2026-09-01 02:30:00'), 'failed');
        $sepFirst = $this->backup($olt, Carbon::parse('2026-09-02 02:30:00'));
        $sepLater = $this->backup($olt, Carbon::parse('2026-09-20 02:30:00'));
        // August is inside the 2-month window (from 2026-08-01): its first stays.
        $augFirst = $this->backup($olt, Carbon::parse('2026-08-03 02:30:00'));
        // July is outside the window.
        $julFirst = $this->backup($olt, Carbon::parse('2026-07-01 02:30:00'));
        // Another OLT is counted on its own.
        $otherOnly = $this->backup($other, Carbon::parse('2025-01-01 02:30:00'));

        $this->artisan('history:prune')
            ->expectsOutputToContain('olt_config_backups: pruned 4')
            ->assertSuccessful();

        $kept = OltConfigBackup::query()->withoutGlobalScopes()->pluck('id')->all();
        sort($kept);
        $expected = [$daily[1]->id, $daily[3]->id, $daily[4]->id, $daily[5]->id, $sepFirst->id, $augFirst->id, $otherOnly->id];
        sort($expected);
        $this->assertSame($expected, $kept);
        $this->assertNotContains($sepFailed->id, $kept);
        $this->assertNotContains($sepLater->id, $kept);
        $this->assertNotContains($julFirst->id, $kept);
        $this->assertNotContains($daily[2]->id, $kept);
    }

    public function test_invalid_retention_refuses_without_deleting(): void
    {
        Carbon::setTestNow('2026-10-22 03:30:00');
        config(['nostek.retention.polling_events_days' => 0]);
        $olt = $this->olt();
        $this->pollingEvent($olt, now()->subDays(60));

        $this->artisan('history:prune')->assertFailed();
        $this->assertSame(1, DB::table('polling_events')->count());
    }

    public function test_prune_is_scheduled_before_the_platform_backup_window(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('history:prune')
            ->assertSuccessful();

        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'history:prune'));
        $this->assertCount(1, $events);
        $this->assertSame('30 3 * * *', $events->first()->expression);
    }

    public function test_duplicate_rx_hourly_lookup_index_is_gone_and_unique_index_stays(): void
    {
        $this->assertFalse(Schema::hasIndex('onu_rx_hourly', 'onu_rx_hourly_lookup_idx'));
        $this->assertTrue(Schema::hasIndex('onu_rx_hourly', 'onu_rx_hourly_bucket_unique'));
    }

    public function test_poll_jobs_use_the_dedicated_poll_queue(): void
    {
        Queue::fake();
        $olt = $this->olt();
        $olt->forceFill(['polling_enabled' => true])->save();

        $this->artisan('olts:poll')->assertSuccessful();

        Queue::assertPushedOn('polling', PollOltJob::class);
    }
}
