<?php

namespace App\Models;

use App\Support\DeviceWritePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * WRITE-UI-1: one row of the admin device write switch (see the migration for the scopes).
 * Read through {@see DeviceWritePolicy}, never directly by a gate.
 */
class DeviceWriteSetting extends Model
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_CHANNEL = 'channel';

    public const SCOPE_OLT = 'olt';

    /** Writes sent through the OLT: SNMP set, CLI scripts and the interactive telnet. */
    public const CHANNEL_OLT = 'olt';

    /** Writes sent to the ONT through GenieACS (TR-069). */
    public const CHANNEL_ACS = 'acs';

    public const CHANNELS = [self::CHANNEL_OLT, self::CHANNEL_ACS];

    protected $fillable = ['key', 'scope', 'channel', 'snmp_olt_id', 'enabled', 'expires_at', 'reason', 'updated_by'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public static function globalKey(): string
    {
        return self::SCOPE_GLOBAL;
    }

    public static function channelKey(string $channel): string
    {
        return self::SCOPE_CHANNEL.':'.$channel;
    }

    public static function oltKey(int $oltId): string
    {
        return self::SCOPE_OLT.':'.$oltId;
    }

    /** @return BelongsTo<SnmpOlt, $this> */
    public function olt(): BelongsTo
    {
        return $this->belongsTo(SnmpOlt::class, 'snmp_olt_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
