<?php

namespace App\Models;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ringkasan RX per hari per ONU (NMS-07-B2), sumber grafik rentang sangat panjang (1–3 tahun).
 * Dibangun dari {@see OnuRxHourly} oleh `optical:aggregate-rx-daily`.
 */
class OnuRxDaily extends Model
{
    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    protected $table = 'onu_rx_daily';

    public $timestamps = false;

    protected $fillable = [
        'snmp_olt_id',
        'slot',
        'port',
        'onu_id',
        'serial_number',
        'bucket_day',
        'rx_min_dbm',
        'rx_avg_dbm',
        'rx_max_dbm',
        'sample_count',
    ];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'port' => 'integer',
            'onu_id' => 'integer',
            'bucket_day' => 'date',
            'rx_min_dbm' => 'float',
            'rx_avg_dbm' => 'float',
            'rx_max_dbm' => 'float',
            'sample_count' => 'integer',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(SnmpOlt::class, 'snmp_olt_id');
    }

    /**
     * Riwayat RX satu ONU sejak $since, satu titik per hari, urut menaik. Bentuk keluarannya
     * sama dengan {@see OnuRxHourly::seriesFor()}.
     *
     * @return Collection<int, array{polled_at:string, rx_power_dbm:float, rx_min_dbm:float, rx_max_dbm:float}>
     */
    public static function seriesFor(int $oltId, int $slot, int $port, int $onuId, Carbon $since): Collection
    {
        return static::query()
            ->where('snmp_olt_id', $oltId)
            ->where('slot', $slot)
            ->where('port', $port)
            ->where('onu_id', $onuId)
            ->where('bucket_day', '>=', $since->toDateString())
            ->orderBy('bucket_day')
            ->get(['bucket_day', 'rx_min_dbm', 'rx_avg_dbm', 'rx_max_dbm'])
            ->map(fn (self $row) => [
                'polled_at' => $row->bucket_day->toIso8601String(),
                'rx_power_dbm' => (float) $row->rx_avg_dbm,
                'rx_min_dbm' => (float) $row->rx_min_dbm,
                'rx_max_dbm' => (float) $row->rx_max_dbm,
            ]);
    }
}
