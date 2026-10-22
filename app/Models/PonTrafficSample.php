<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Laju trafik satu port PON per poll (TRF-1), dalam bit per detik. Ringkasan per jam dan per
 * hari ada di `pon_traffic_hourly` / `pon_traffic_daily` (dibangun `traffic:aggregate`).
 */
class PonTrafficSample extends Model
{
    public $timestamps = false;

    protected $fillable = ['snmp_olt_id', 'slot', 'port', 'in_bps', 'out_bps', 'polled_at'];

    protected function casts(): array
    {
        return [
            'slot' => 'integer',
            'port' => 'integer',
            'in_bps' => 'integer',
            'out_bps' => 'integer',
            'polled_at' => 'datetime',
        ];
    }

    public function olt(): BelongsTo
    {
        return $this->belongsTo(SnmpOlt::class, 'snmp_olt_id');
    }

    /**
     * Riwayat trafik satu port sejak $since, urut menaik. Sumber dipilih di sini seperti
     * {@see OnuRxSample::seriesFor()}: mentah selama masih disimpan, lalu per jam (rata-rata +
     * puncak), lalu per hari untuk rentang lebih dari 90 hari. `in` = arah masuk ke OLT dari
     * PON (upstream pelanggan), `out` = keluar dari OLT ke PON (downstream pelanggan).
     *
     * @return Collection<int, array{t:string, in_bps:int, out_bps:int, in_max_bps:int|null, out_max_bps:int|null}>
     */
    public static function seriesFor(int $oltId, int $slot, int $port, Carbon $since): Collection
    {
        // One hour of slack on each limit: $since is computed a moment before this runs, so an
        // exact "90 days" or "7 days" request must not tip over to the coarser table.
        if ($since < now()->subDays(90)->subHour()) {
            return static::rollupSeries('pon_traffic_daily', 'bucket_day', $oltId, $slot, $port, $since->copy()->startOfDay());
        }

        $rawDays = (int) config('services.snmp_poller.traffic_sample_retention_days', 8);
        if ($since < now()->subDays(max(1, $rawDays - 1))->subHour()) {
            return static::rollupSeries('pon_traffic_hourly', 'bucket_hour', $oltId, $slot, $port, $since);
        }

        return static::query()
            ->where('snmp_olt_id', $oltId)
            ->where('slot', $slot)
            ->where('port', $port)
            ->where('polled_at', '>=', $since)
            ->orderBy('polled_at')
            ->get(['polled_at', 'in_bps', 'out_bps'])
            ->map(fn (self $row) => [
                't' => $row->polled_at->toIso8601String(),
                'in_bps' => (int) $row->in_bps,
                'out_bps' => (int) $row->out_bps,
                'in_max_bps' => null,
                'out_max_bps' => null,
            ]);
    }

    /**
     * @return Collection<int, array{t:string, in_bps:int, out_bps:int, in_max_bps:int, out_max_bps:int}>
     */
    private static function rollupSeries(string $table, string $bucket, int $oltId, int $slot, int $port, Carbon $since): Collection
    {
        return DB::table($table)
            ->where('snmp_olt_id', $oltId)
            ->where('slot', $slot)
            ->where('port', $port)
            ->where($bucket, '>=', $since)
            ->orderBy($bucket)
            ->get([$bucket, 'in_avg_bps', 'in_max_bps', 'out_avg_bps', 'out_max_bps'])
            ->map(fn ($row) => [
                't' => Carbon::parse($row->{$bucket})->toIso8601String(),
                'in_bps' => (int) $row->in_avg_bps,
                'out_bps' => (int) $row->out_avg_bps,
                'in_max_bps' => (int) $row->in_max_bps,
                'out_max_bps' => (int) $row->out_max_bps,
            ]);
    }
}
