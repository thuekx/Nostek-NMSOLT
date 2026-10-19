# Nostek-NMSOLT

**NMS FTTH, OLT, PON, ONU, ODP dan TR-069** — pengembangan oleh **thuekx** (`thuekx@kolu.web.id`).

Repository ini adalah **baseline awal rebranding**, bukan rilis produksi. Modul web menggunakan Laravel/Vue, poller menggunakan Go, serta PostgreSQL dan Redis terpisah. Deployment target menggunakan Docker pada VM Maung dan VM Nostek yang sudah ada, dengan database dan konfigurasi terisolasi.

GenieACS disiapkan sebagai Compose tambahan untuk pengujian, bukan dijalankan otomatis dan bukan pengganti monitoring SNMP. Hanya uji menggunakan ONT lab sampai jalur keamanan, konfigurasi dan izin tindakan lulus verifikasi.

**Penting:** Source masih memiliki endpoint perubahan perangkat; pengamanan integrasi broker dan Maung SSO belum selesai. Jangan membuka ke OLT produksi sebelum audit.

Dokumentasi awal: [Baseline Review](docs/nostek/BASELINE-REVIEW.md), [Integration Plan](docs/nostek/INTEGRATION-PLAN.md) dan [GenieACS Staging](docs/nostek/GENIEACS-STAGING.md).

Lisensi: MIT, dengan pemberitahuan copyright original yang wajib dipertahankan di `LICENSE`. Perangkat lunak GenieACS memiliki kewajiban lisensi AGPLv3 tersendiri.
