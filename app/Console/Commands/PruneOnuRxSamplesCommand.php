<?php

namespace App\Console\Commands;

use App\Models\OnuRxDaily;
use App\Models\OnuRxHourly;
use App\Models\OnuRxSample;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class PruneOnuRxSamplesCommand extends Command
{
    protected $signature = 'optical:prune-rx {--days= : Hapus sample RX lebih lama dari N hari (default dari config)}';

    protected $description = 'Prune riwayat RX power ONU (onu_rx_samples, onu_rx_hourly & onu_rx_daily) yang melewati masa retensi';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('services.snmp_poller.rx_sample_retention_days', 3));

        if ($days < 1) {
            $this->error('Retensi minimal 1 hari.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);

        // Sampel mentah baru aman dibuang setelah jamnya terangkum. Kalau
        // agregasi tertinggal (scheduler mati, migrasi belum jalan), lewati
        // prune daripada menghapus riwayat yang belum punya salinan.
        if ($this->hourlyLagsBehind($cutoff)) {
            $this->warn('Agregasi per jam belum mencapai cutoff — prune dilewati agar riwayat tidak hilang.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $chunk = 5000;

        // Hapus bertahap (pilih id lalu hapus whereIn) agar portabel lintas driver
        // (sqlite/pgsql) dan tidak mengunci tabel saat volume besar.
        do {
            $ids = OnuRxSample::query()
                ->where('polled_at', '<', $cutoff)
                ->limit($chunk)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += OnuRxSample::whereIn('id', $ids)->delete();
        } while ($ids->count() === $chunk);

        $this->info("Pruned {$deleted} RX sample(s) older than {$days} day(s) (sebelum {$cutoff->toDateTimeString()}).");

        $this->pruneHourly();

        return self::SUCCESS;
    }

    /**
     * Benar bila onu_rx_hourly belum mencakup seluruh jam yang hendak dibuang.
     */
    private function hourlyLagsBehind(Carbon $cutoff): bool
    {
        if (! Schema::hasTable('onu_rx_hourly')) {
            return true;
        }

        $last = OnuRxHourly::query()->max('bucket_hour');

        return $last === null || Carbon::parse($last) < $cutoff;
    }

    /**
     * Buang ringkasan per jam yang sudah lewat masa simpannya sendiri.
     */
    private function pruneHourly(): void
    {
        if (! Schema::hasTable('onu_rx_hourly')) {
            return;
        }

        $days = (int) config('services.snmp_poller.rx_hourly_retention_days', 45);

        if ($days < 1) {
            return;
        }

        $cutoff = now()->subDays($days);

        // NMS-07-B2: jam yang belum terangkum ke onu_rx_daily tidak boleh dibuang. Hari terakhir
        // yang terangkum diproses ulang oleh agregasi harian, jadi jam-jamnya ikut dipertahankan.
        if (Schema::hasTable('onu_rx_daily') && (int) config('services.snmp_poller.rx_daily_retention_days', 1095) >= 1) {
            $lastDay = OnuRxDaily::query()->max('bucket_day');
            $rolledUpTo = $lastDay === null ? null : Carbon::parse($lastDay)->startOfDay();
            if ($rolledUpTo === null || $rolledUpTo < $cutoff) {
                $this->warn('Agregasi harian belum mencapai cutoff per jam — ringkasan jam dipertahankan sampai '
                    .($rolledUpTo?->toDateString() ?? 'agregasi harian pertama').'.');
                $cutoff = $rolledUpTo;
            }
        }

        $deleted = $cutoff === null ? 0 : OnuRxHourly::query()->where('bucket_hour', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ringkasan jam lebih lama dari {$days} hari.");

        $this->pruneDaily();
    }

    /**
     * Buang ringkasan harian yang sudah lewat masa simpannya (default 3 tahun, NMS-AI-Q5).
     */
    private function pruneDaily(): void
    {
        if (! Schema::hasTable('onu_rx_daily')) {
            return;
        }

        $days = (int) config('services.snmp_poller.rx_daily_retention_days', 1095);

        if ($days < 1) {
            return;
        }

        $cutoff = now()->subDays($days)->toDateString();
        $deleted = OnuRxDaily::query()->where('bucket_day', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} ringkasan hari lebih lama dari {$days} hari.");
    }
}
