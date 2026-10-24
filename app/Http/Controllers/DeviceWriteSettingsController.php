<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\DeviceWriteSetting;
use App\Models\SnmpOlt;
use App\Support\AuditLogger;
use App\Support\DeviceWritePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * WRITE-UI-1: admin switches for device writes (global with optional auto-off, per channel,
 * per OLT block). Only active when the installation mode is `admin`
 * (NMSOLT_DEVICE_WRITES_ENABLED=admin); `false` stays a hard off the UI cannot lift.
 * Every change needs a reason and writes an audit row.
 */
class DeviceWriteSettingsController extends Controller
{
    /** Auto-off choices in minutes (null = until switched off). */
    public const DURATIONS = [15, 60, 240, 480, 1440];

    public function index(): Response
    {
        DeviceWritePolicy::flush();
        $rows = DeviceWriteSetting::query()->with('updater:id,name')->get()->keyBy('key');
        $describe = fn (?DeviceWriteSetting $row) => $row === null ? null : [
            'enabled' => $row->enabled,
            'reason' => $row->reason,
            'expires_at' => $row->expires_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
            'updated_by' => $row->updater?->name,
        ];

        $channels = [];
        foreach (DeviceWriteSetting::CHANNELS as $channel) {
            $channels[$channel] = $describe($rows->get(DeviceWriteSetting::channelKey($channel)));
        }

        return Inertia::render('Settings/DeviceWrites', [
            'mode' => DeviceWritePolicy::mode(),
            'effective' => [
                'global' => DeviceWritePolicy::globalOn(),
                'channels' => collect(DeviceWriteSetting::CHANNELS)
                    ->mapWithKeys(fn (string $c) => [$c => DeviceWritePolicy::allows($c)])
                    ->all(),
            ],
            'global' => $describe($rows->get(DeviceWriteSetting::globalKey())),
            'channels' => $channels,
            'olts' => SnmpOlt::query()->orderBy('name')->get(['id', 'name', 'ip'])
                ->map(function (SnmpOlt $olt) use ($rows, $describe) {
                    $row = $rows->get(DeviceWriteSetting::oltKey((int) $olt->id));

                    return [
                        'id' => $olt->id,
                        'name' => $olt->name,
                        'ip' => $olt->ip,
                        'blocked' => $row !== null && ! $row->enabled,
                        'setting' => $describe($row),
                    ];
                })->values(),
            'durations' => self::DURATIONS,
            'history' => AuditLog::query()
                ->where('event', AuditLog::EVENT_DEVICE_WRITE_SETTING)
                ->latest('id')
                ->limit(20)
                ->get(['id', 'user_name', 'properties', 'created_at'])
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'user' => $log->user_name,
                    'properties' => $log->properties,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAdminMode();
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'channels' => ['required', 'array'],
            'channels.olt' => ['required', 'boolean'],
            'channels.acs' => ['required', 'boolean'],
            'duration' => ['nullable', 'integer', Rule::in(self::DURATIONS)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $actor = $request->user();
        $reason = trim($validated['reason']);
        $before = $this->snapshot();
        $enabled = (bool) $validated['enabled'];
        $duration = $enabled ? ($validated['duration'] ?? null) : null;

        DB::transaction(function () use ($validated, $actor, $reason, $enabled, $duration) {
            DeviceWriteSetting::query()->updateOrCreate(
                ['key' => DeviceWriteSetting::globalKey()],
                [
                    'scope' => DeviceWriteSetting::SCOPE_GLOBAL,
                    'enabled' => $enabled,
                    'expires_at' => $duration !== null ? now()->addMinutes((int) $duration) : null,
                    'reason' => $reason,
                    'updated_by' => $actor?->id,
                ],
            );
            foreach (DeviceWriteSetting::CHANNELS as $channel) {
                DeviceWriteSetting::query()->updateOrCreate(
                    ['key' => DeviceWriteSetting::channelKey($channel)],
                    [
                        'scope' => DeviceWriteSetting::SCOPE_CHANNEL,
                        'channel' => $channel,
                        'enabled' => (bool) $validated['channels'][$channel],
                        'reason' => $reason,
                        'updated_by' => $actor?->id,
                    ],
                );
            }
        });
        DeviceWritePolicy::flush();

        AuditLogger::log(
            event: AuditLog::EVENT_DEVICE_WRITE_SETTING,
            properties: [
                'scope' => DeviceWriteSetting::SCOPE_GLOBAL,
                'before' => $before,
                'after' => $this->snapshot(),
                'duration_minutes' => $duration,
                'reason' => $reason,
            ],
            description: 'Device write switches changed by an admin.',
            actor: $actor,
        );

        return back()->with('success', __('flash.device_writes_saved'));
    }

    public function updateOlt(Request $request, SnmpOlt $olt): RedirectResponse
    {
        $this->ensureAdminMode();
        $validated = $request->validate([
            'blocked' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $blocked = (bool) $validated['blocked'];
        $reason = trim($validated['reason']);
        $wasBlocked = DeviceWritePolicy::oltBlocked((int) $olt->id);

        DeviceWriteSetting::query()->updateOrCreate(
            ['key' => DeviceWriteSetting::oltKey((int) $olt->id)],
            [
                'scope' => DeviceWriteSetting::SCOPE_OLT,
                'snmp_olt_id' => $olt->id,
                // A per-OLT row can only take writes away: enabled = "follow global".
                'enabled' => ! $blocked,
                'reason' => $reason,
                'updated_by' => $request->user()?->id,
            ],
        );
        DeviceWritePolicy::flush();

        AuditLogger::log(
            event: AuditLog::EVENT_DEVICE_WRITE_SETTING,
            auditable: $olt,
            properties: [
                'scope' => DeviceWriteSetting::SCOPE_OLT,
                'olt_id' => $olt->id,
                'olt_name' => $olt->name,
                'before' => ['blocked' => $wasBlocked],
                'after' => ['blocked' => $blocked],
                'reason' => $reason,
            ],
            description: $blocked ? 'Device writes blocked for one OLT.' : 'Device write block lifted for one OLT.',
        );

        return back()->with('success', __('flash.device_writes_saved'));
    }

    /** The env stays the upper limit: off (or legacy on) cannot be changed from the UI. */
    private function ensureAdminMode(): void
    {
        abort_unless(
            DeviceWritePolicy::mode() === DeviceWritePolicy::MODE_ADMIN,
            403,
            'Instalasi ini tidak mengizinkan admin mengubah device writes (NMSOLT_DEVICE_WRITES_ENABLED bukan "admin").',
        );
    }

    /** @return array{global: bool, expires_at: ?string, channels: array<string, bool>} */
    private function snapshot(): array
    {
        DeviceWritePolicy::flush();
        $channels = [];
        foreach (DeviceWriteSetting::CHANNELS as $channel) {
            $channels[$channel] = DeviceWritePolicy::channelOn($channel);
        }

        return [
            'global' => DeviceWritePolicy::globalOn(),
            'expires_at' => DeviceWritePolicy::globalExpiresAt()?->toIso8601String(),
            'channels' => $channels,
        ];
    }
}
