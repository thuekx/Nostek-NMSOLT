<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('olts:poll')->everyMinute()->withoutOverlapping();
// Agregasi wajib mendahului prune: sampel mentah baru boleh dibuang setelah
// jamnya terangkum, kalau tidak riwayat panjangnya hilang permanen.
Schedule::command('optical:aggregate-rx')->hourlyAt(5)->withoutOverlapping();
// NMS-07-B2: after the 00:05 hourly run has summarised the last hour of yesterday.
Schedule::command('optical:aggregate-rx-daily')->dailyAt('00:25')->withoutOverlapping();
Schedule::command('optical:prune-rx')->dailyAt('03:15')->withoutOverlapping();
// TRF-1: trafik PON — ringkasan jam & hari dulu, prune sesudahnya (prune menolak melewati yang belum terangkum).
Schedule::command('traffic:aggregate')->hourlyAt(10)->withoutOverlapping();
Schedule::command('traffic:prune')->dailyAt('03:20')->withoutOverlapping();

// Sesi aplikasi yang kedaluwarsa (sanctum.expiration) dibuang — token push FCM
// yang terkait ikut terhapus lewat FK cascade, jadi ponselnya berhenti dikirimi alarm.
Schedule::command('sanctum:prune-expired --hours=24')->dailyAt('03:40');
// Retensi tabel yang tumbuh tanpa batas (polling/alarm cleared/audit/backup config).
// 03:30: setelah prune RX, sebelum dump backup platform (04:00) dan restic (04:30).
Schedule::command('history:prune')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('olts:backup-config')->dailyAt('02:30')->withoutOverlapping();
// Pencocokan device GenieACS ke posisi ONU. Sengaja 15 menit, BUKAN tiap menit:
// peta ONU tidak berubah secepat itu dan server ini sudah sibuk oleh poller SNMP.
// Satu jalannya menarik ±688 KB dari NBI (projection ringan) dan selesai <1 detik.
// Tanpa NBI di Pengaturan perintah ini selesai diam-diam (bukan kegagalan).
Schedule::command('genieacs:match-onu')->everyFifteenMinutes()->withoutOverlapping(10)->runInBackground();
