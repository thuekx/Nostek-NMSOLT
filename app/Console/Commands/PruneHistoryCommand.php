<?php

namespace App\Console\Commands;

use App\Models\AlarmEvent;
use App\Models\AuditLog;
use App\Models\OltConfigBackup;
use App\Models\PollingEvent;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Bounded retention for tables that otherwise grow without limit
 * (polling_events, cleared alarm_events, audit_logs, olt_config_backups).
 * Optical RX history keeps its own command (optical:prune-rx). Global
 * scopes (demo/partner) are bypassed: retention applies to every row.
 */
class PruneHistoryCommand extends Command
{
    protected $signature = 'history:prune {--dry-run : Hitung baris yang akan dihapus tanpa menghapus}';

    protected $description = 'Prune polling events, cleared alarms, audit logs and old OLT config backups past their retention';

    private const CHUNK = 5000;

    public function handle(): int
    {
        $retention = (array) config('nostek.retention', []);
        $dryRun = (bool) $this->option('dry-run');

        $polling = (int) ($retention['polling_events_days'] ?? 30);
        $alarms = (int) ($retention['cleared_alarm_days'] ?? 180);
        $audit = (int) ($retention['audit_log_days'] ?? 400);
        $keepLatest = (int) ($retention['config_backups_keep_latest'] ?? 7);
        $months = (int) ($retention['config_backups_monthly_months'] ?? 12);

        foreach (['polling_events_days' => $polling, 'cleared_alarm_days' => $alarms, 'audit_log_days' => $audit] as $key => $days) {
            if ($days < 1) {
                $this->error("Retensi {$key} minimal 1 hari.");

                return self::FAILURE;
            }
        }
        if ($keepLatest < 1 || $months < 0) {
            $this->error('Retensi backup config: keep_latest minimal 1, months minimal 0.');

            return self::FAILURE;
        }

        $results = [
            'polling_events' => $this->prune(
                PollingEvent::query()->withoutGlobalScopes()->where('created_at', '<', now()->subDays($polling)),
                $dryRun,
            ),
            'alarm_events' => $this->prune(
                AlarmEvent::query()
                    ->withoutGlobalScopes()
                    ->where('status', AlarmEvent::STATUS_CLEARED)
                    ->whereNotNull('cleared_at')
                    ->where('cleared_at', '<', now()->subDays($alarms)),
                $dryRun,
            ),
            'audit_logs' => $this->prune(
                AuditLog::query()->withoutGlobalScopes()->where('created_at', '<', now()->subDays($audit)),
                $dryRun,
            ),
            'olt_config_backups' => $this->pruneConfigBackups($keepLatest, $months, $dryRun),
        ];

        foreach ($results as $table => $count) {
            $verb = $dryRun ? 'would prune' : 'pruned';
            $this->info("{$table}: {$verb} {$count}");
        }

        return self::SUCCESS;
    }

    /**
     * Chunked delete (select ids, then whereIn) so large tables are not locked
     * in one statement; portable across sqlite and pgsql.
     */
    private function prune(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return (clone $query)->count();
        }

        $deleted = 0;
        do {
            $ids = (clone $query)->orderBy('id')->limit(self::CHUNK)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += $query->getModel()->newQueryWithoutScopes()->whereIn('id', $ids)->delete();
        } while ($ids->count() === self::CHUNK);

        return $deleted;
    }

    /**
     * Keep the newest N backups per OLT and the first successful backup of
     * each of the last M months; everything else is removed.
     */
    private function pruneConfigBackups(int $keepLatest, int $months, bool $dryRun): int
    {
        $monthlySince = now()->startOfMonth()->subMonths($months);
        $remove = collect();

        OltConfigBackup::query()
            ->withoutGlobalScopes()
            ->select('snmp_olt_id')
            ->distinct()
            ->pluck('snmp_olt_id')
            ->each(function ($oltId) use ($keepLatest, $monthlySince, $months, &$remove) {
                /** @var Collection<int, OltConfigBackup> $rows */
                $rows = OltConfigBackup::query()
                    ->withoutGlobalScopes()
                    ->where('snmp_olt_id', $oltId)
                    ->orderByDesc('captured_at')
                    ->orderByDesc('id')
                    ->get(['id', 'status', 'captured_at', 'created_at']);

                $keep = $rows->take($keepLatest)->pluck('id')->all();

                if ($months > 0) {
                    $rows->filter(fn (OltConfigBackup $row) => $row->status === 'ok')
                        ->filter(fn (OltConfigBackup $row) => ($row->captured_at ?? $row->created_at) >= $monthlySince)
                        ->groupBy(fn (OltConfigBackup $row) => ($row->captured_at ?? $row->created_at)->format('Y-m'))
                        ->each(function (Collection $month) use (&$keep) {
                            $keep[] = $month->sortBy(fn ($row) => [($row->captured_at ?? $row->created_at)->getTimestamp(), $row->id])->first()->id;
                        });
                }

                $remove = $remove->merge($rows->pluck('id')->diff($keep));
            });

        if ($dryRun || $remove->isEmpty()) {
            return $remove->count();
        }

        $deleted = 0;
        foreach ($remove->chunk(self::CHUNK) as $chunk) {
            $deleted += OltConfigBackup::query()->withoutGlobalScopes()->whereIn('id', $chunk->values())->delete();
        }

        return $deleted;
    }
}
