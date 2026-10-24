<?php

namespace App\Support;

use App\Models\DeviceWriteSetting;
use App\Models\SnmpOlt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * WRITE-UI-1: the one place that decides whether a device write may run.
 *
 * Layers, most restrictive wins:
 * 1. installation mode (env NMSOLT_DEVICE_WRITES_ENABLED): off | admin | on (legacy, always on);
 * 2. in admin mode, the global switch (with an optional auto-off time);
 * 3. the channel switch (olt = SNMP set / CLI / telnet through the OLT, acs = GenieACS);
 * 4. a per-OLT block.
 * Missing rows mean off (global, channel) and "follow global" (OLT): a fresh install with
 * mode admin still writes nothing until an admin switches it on.
 */
class DeviceWritePolicy
{
    public const MODE_OFF = 'off';

    public const MODE_ADMIN = 'admin';

    public const MODE_ON = 'on';

    /** @var Collection<string, DeviceWriteSetting>|null */
    private static ?Collection $rows = null;

    public static function mode(): string
    {
        $value = config('nostek.device_writes_enabled', false);
        if ($value === self::MODE_ADMIN) {
            return self::MODE_ADMIN;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL) ? self::MODE_ON : self::MODE_OFF;
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    public static function decide(string $channel = DeviceWriteSetting::CHANNEL_OLT, ?SnmpOlt $olt = null): array
    {
        $mode = self::mode();
        if ($mode === self::MODE_ON) {
            return ['allowed' => true, 'reason' => 'installation'];
        }
        if ($mode === self::MODE_OFF) {
            return ['allowed' => false, 'reason' => 'installation'];
        }
        if (! self::globalOn()) {
            return ['allowed' => false, 'reason' => 'global'];
        }
        if (! self::channelOn($channel)) {
            return ['allowed' => false, 'reason' => 'channel'];
        }
        if ($olt !== null && self::oltBlocked((int) $olt->getKey())) {
            return ['allowed' => false, 'reason' => 'olt'];
        }

        return ['allowed' => true, 'reason' => 'admin'];
    }

    public static function allows(string $channel = DeviceWriteSetting::CHANNEL_OLT, ?SnmpOlt $olt = null): bool
    {
        return self::decide($channel, $olt)['allowed'];
    }

    /** True when at least one channel can write somewhere (drives the read-only banner). */
    public static function anyAllowed(): bool
    {
        foreach (DeviceWriteSetting::CHANNELS as $channel) {
            if (self::allows($channel)) {
                return true;
            }
        }

        return false;
    }

    public static function globalOn(): bool
    {
        $row = self::rows()->get(DeviceWriteSetting::globalKey());

        return $row !== null && $row->enabled && ! self::expired($row);
    }

    public static function globalExpiresAt(): ?Carbon
    {
        $row = self::rows()->get(DeviceWriteSetting::globalKey());

        return $row?->enabled ? $row->expires_at : null;
    }

    public static function channelOn(string $channel): bool
    {
        if (! in_array($channel, DeviceWriteSetting::CHANNELS, true)) {
            return false;
        }

        return (bool) self::rows()->get(DeviceWriteSetting::channelKey($channel))?->enabled;
    }

    public static function oltBlocked(int $oltId): bool
    {
        $row = self::rows()->get(DeviceWriteSetting::oltKey($oltId));

        return $row !== null && ! $row->enabled;
    }

    /** @return list<int> */
    public static function blockedOltIds(): array
    {
        return self::rows()
            ->filter(fn (DeviceWriteSetting $row) => $row->scope === DeviceWriteSetting::SCOPE_OLT && ! $row->enabled)
            ->map(fn (DeviceWriteSetting $row) => (int) $row->snmp_olt_id)
            ->values()
            ->all();
    }

    /** @return Collection<string, DeviceWriteSetting> */
    private static function rows(): Collection
    {
        // Cached for one request/job only; flush() after every write of the settings.
        return self::$rows ??= DeviceWriteSetting::query()->get()->keyBy('key');
    }

    private static function expired(DeviceWriteSetting $row): bool
    {
        return $row->expires_at !== null && $row->expires_at->isPast();
    }

    /** Readable refusal for a decide() reason (403 body, job error, Telegram reply). */
    public static function message(string $reason): string
    {
        return match ($reason) {
            'global' => 'Aksi tulis OLT/ONT sedang dimatikan oleh admin (Pengaturan → Device writes).',
            'channel' => 'Aksi tulis untuk jalur ini sedang dimatikan oleh admin (Pengaturan → Device writes).',
            'olt' => 'Aksi tulis ke OLT ini sedang diblokir oleh admin (Pengaturan → Device writes).',
            default => 'Aksi tulis OLT/ONT dinonaktifkan untuk instalasi ini.',
        };
    }

    public static function flush(): void
    {
        self::$rows = null;
    }
}
