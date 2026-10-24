# PEDOMAN KONSISTENSI UI & DESIGN SYSTEM
**Nostek-NMSOLT (thuekx) — Standar Antarmuka Pengguna**

Dokumen ini adalah aturan baku yang **WAJIB dipatuhi** oleh seluruh pengembang saat membuat atau memodifikasi komponen antarmuka pengguna (Vue 3, Inertia, Tailwind CSS) di lingkungan **Nostek-NMSOLT**.

---

## 1. ATURAN BAKU MODAL KONFIRMASI (CONFIRM MODAL)

### ⛔ LARANGAN KERAS:
**JANGAN PERNAH** menggunakan fungsi bawaan browser seperti `window.confirm()` atau `if (confirm(...))` di seluruh halaman aplikasi! Dialog browser merusak estetika dark-glass dan tidak ramah pengguna mobile.

### ✅ STANDAR WAJIB:
Gunakan komponen `@/Components/Shell/ConfirmModal.vue` untuk seluruh tindakan destruktif, pembatalan, pembersihan log, atau pengiriman massal.

```vue
<script setup>
import { ref } from 'vue';
import ConfirmModal from '@/Components/Shell/ConfirmModal.vue';

const confirmState = ref({
    show: false,
    title: '',
    message: '',
    confirmText: 'Ya, Lanjutkan',
    variant: 'danger', // 'danger' | 'warning' | 'info'
    action: null,
});

// Contoh 1: Aksi Hapus / Destruktif
const handleDelete = (item) => {
    confirmState.value = {
        show: true,
        title: 'Hapus Data',
        message: `Apakah Anda yakin ingin menghapus "${item.name}" secara permanen?`,
        confirmText: 'Ya, Hapus',
        variant: 'danger',
        action: () => {
            router.delete(route('items.destroy', item.id), {
                onSuccess: () => confirmState.value.show = false,
            });
        },
    };
};

// Contoh 2: Aksi Pembatalan / Warning
const handleRevert = (inv) => {
    confirmState.value = {
        show: true,
        title: 'Batalkan Status Lunas',
        message: `Kembalikan status invoice #${inv.invoice_no} ke Belum Lunas?`,
        confirmText: 'Ya, Batalkan',
        variant: 'warning',
        action: () => {
            router.put(route('invoices.revert', inv.id), {
                onSuccess: () => confirmState.value.show = false,
            });
        },
    };
};

// Contoh 3: Aksi Kirim Notifikasi / Info
const handleSendWa = (user) => {
    confirmState.value = {
        show: true,
        title: 'Kirim Notifikasi WhatsApp',
        message: `Kirim tagihan ke nomor WhatsApp ${user.phone}?`,
        confirmText: 'Kirim Sekarang',
        variant: 'info',
        action: () => {
            router.post(route('wa.send', user.id), {
                onSuccess: () => confirmState.value.show = false,
            });
        },
    };
};
</script>

<template>
    <!-- Di bagian paling bawah template -->
    <ConfirmModal
        :show="confirmState.show"
        :title="confirmState.title"
        :message="confirmState.message"
        :confirm-text="confirmState.confirmText"
        cancel-text="Batal"
        :variant="confirmState.variant"
        @cancel="confirmState.show = false"
        @confirm="confirmState.action"
    />
</template>
```

---

## 2. ATURAN BAKU TOAST & FLASH NOTIFICATIONS

### Desain & Perilaku:
- Notifikasi flash server (`$page.props.flash.success` / `$page.props.flash.error`) ditangani secara terpusat oleh shell `AuthenticatedLayout.vue`.
- Ditampilkan dalam bentuk **Floating Glass Card** di pojok kanan atas (`fixed top-5 right-5 z-50`).
- **Auto-Dismiss:** Menghilang otomatis setelah **4.5 detik**.
- **Manual Dismiss:** Tombol silang `X` untuk menutup seketika.
- **Pewarnaan:**
  - `success`: Border hijau neon (`border-emerald-500/40`), badge `BERHASIL`, background dark glass.
  - `error`: Border merah neon (`border-rose-500/40`), badge `PEMBERITAHUAN / GAGAL`, background dark glass.

---

## 3. PALET WARNA & DESIGN TOKENS (DARK GLASS CYAN)

| Token Nama | Kelas Tailwind / Nilai | Penggunaan |
| :--- | :--- | :--- |
| **Canvas Background** | `nos-grid-stage` (`#0b1329`) | Latar belakang seluruh halaman |
| **Grid Pattern** | `nos-grid-pattern` (32px x 32px) | Grid mesh neon semi-transparan |
| **Top Light Beam** | `nos-top-light` & `nos-ambient-glow` | Efek spotlight ambient cyan di atas layar |
| **Glass Panel** | `nos-glass-panel` | Wadah tabel, card statistik, dan form |
| **Aksen Utama** | `from-cyan-500 to-sky-500` | Tombol CTA primer, header branding, dan link aktif |
| **Aksen Sukses / Lunas**| `emerald-400` / `bg-emerald-500/20` | Status Aktif, Lunas, Online, Rx Power Normal |
| **Aksen Peringatan** | `amber-400` / `bg-amber-500/20` | Status Unpaid, Jatuh Tempo, Rx Power Waspada |
| **Aksen Bahaya / Error** | `rose-400` / `bg-rose-500/20` | Status Terisolir, Offline, Rx Power Kritis, Tombol Hapus |

---

## 3a. DUA TEMA (GELAP / TERANG / IKUTI SISTEM)

Seluruh warna aplikasi adalah CSS custom property yang nilainya ditetapkan per tema. Tema gelap
adalah bawaan; pengguna memilih Gelap / Terang / Ikuti Sistem.


- `tailwind.tokens.mjs` (daftar nama) + blok `@theme` di `resources/css/app.css` (Tailwind v4,
  sejak UI-T1) memetakan seluruh ramp ke `--color-<nama>: rgb(var(--nos-<nama>))`; nilainya di `resources/css/app.css` pada blok
  `[data-theme="dark"]` dan `[data-theme="light"]`. Dijaga `tests/Feature/ThemeTokenTest`.
- Server: `App\Support\Theme` (kolom `users.theme` → cookie `nos_theme` → bawaan gelap),
  `<html data-theme>` dari `AppServiceProvider::bootTheme`, skrip `system` di `<head>` ber-nonce CSP.
  Cookie **per app** (host-only) dan tidak dienkripsi (`bootstrap/app.php`).
- Klien: `@/lib/theme` (`useTheme`, `setTheme`, `chartTheme()`, `tokenHex()`, `themeHexA()`).
  Simpan pilihan lewat **axios PATCH `profile.theme` → 204**, BUKAN kunjungan Inertia: kunjungan
  memuat ulang halaman dan menghapus token API yang hanya tampil sekali, modal terbuka, dan isian form.
- Pemilih: `Shell/UserMenu.vue` (desktop), `Shell/ThemeSegmented.vue` (drawer HP),
  `Shell/ThemeToggle.vue` (Welcome & halaman tamu). Terbuka untuk semua peran; akun demo hanya
  disimpan di cookie (baris bersama tidak ditulis).


### Kelas bantu tema

Semua kelas di bawah hanya punya aturan di `[data-theme="light"]` (atau mengunci subpohon gelap),
jadi menambahkannya **tidak menggeser tema gelap satu piksel pun**.

| Kelas | Pasang di | Efek di tema terang |
| :--- | :--- | :--- |
| `nos-shell-sidebar` / `nos-shell-bar` | sidebar; header, footer, bar HP di `AuthenticatedLayout` | `surface-1` opak |
| `nos-popover` | panel mengambang: notifikasi, menu pengguna, pemilih app/bahasa, palet pencarian | `surface-1` opak, tanpa blur, bayangan lembut |
| `nos-toast` | kartu toast di `Shell/FlashMessages.vue` | kertas opak; warna tepi/ikon/teks tetap |
| `nos-surface` | kartu kaca ad-hoc tingkat atas (`bg-slate-900/30–60` + blur + `shadow-lg`) yang bukan `nos-card`/`nos-panel` | kertas opak + bayangan tipis |
| `nos-terminal` | blok keluaran CLI/skrip, bersama `data-theme="dark"` | tetap gelap, latar dibuat opak |
| `bg-canvas-3/<α>` | permukaan cekung: header tabel, kotak kode ringan, panel samping | token, ikut tema (bukan `bg-slate-950`) |

Helper JS (`@/lib/theme`): `useTheme`, `setTheme`, `cycleTheme`, `themeRgb`, `themeRgba`,
`themeHex`, `themeHexA`, `chartTheme()`, `tokenHex()`. `chartTheme()` sisa era ApexCharts dan tak lagi
dipakai sejak grafik pindah ke Chart.js (2 Okt 2026). Grafik wajib heks (`themeHex`/`tokenHex`),
bukan `themeRgb`.

### Aturan menulis kelas

| Situasi | Pakai | Jangan |
| :--- | :--- | :--- |
| Teks di atas tombol/gradien aksen | `text-onaccent` | `text-white` (berbalik jadi tinta gelap) |
| Permukaan cekung (header tabel, kotak kode ringan, panel samping) | `bg-canvas-3/<α>` | `bg-slate-950/<α>` — slate-950 **tetap gelap** di tema terang |
| Scrim modal | `bg-black/60–70` atau `bg-slate-950/60+` | — |
| Keluaran CLI/skrip OLT | `data-theme="dark"` + kelas `nos-terminal` | membiarkannya ikut tema (warna sintaks dirancang untuk latar gelap) |
| Gambar perangkat fisik (rak `OltChassis`, faceplate `OltFaceplate`) | **ikut tema**: rak memakai kelas token biasa; faceplate memakai variabel `--fp-*` dengan set perak di `[data-theme='light'] .fp` | — |
| Warna di JS — grafik (Chart.js) | `<ChartCanvas>` (`Components/Charts`) + potongan opsi `@/lib/chartOptions` (`lineChartOptions`, `axisX/Y`, `tooltip`, `areaFill`, `withAlpha`) dirakit di dalam `computed`; tema berganti → grafik di-update di tempat, tanpa `:key`. Gauge: `<ArcGauge>` (SVG) | heks literal untuk teks/grid/stop 300–400; impor statis `chart.js` |
| Warna di JS — Leaflet | `tokenHex('cyan-400')`, `themeHexA('--nos-white', .05)` di dalam `computed` | heks literal untuk teks/grid/stop 300–400 |
| `<style scoped>` | `rgb(var(--nos-slate-300))` dst.; varian terang dengan selektor `[data-theme="light"] .kelas` | `:global(...)` (Vue bisa menelan seluruh selektor) |

- Stop **500/600 setiap ramp aksen dipatok** sama di kedua tema; seri status grafik boleh heks 500.
- `backdrop-filter` **dimatikan untuk semua elemen di tema terang** (aturan global di `app.css`).
- Lihat halaman di **kedua tema** dan di lebar 360 px sebelum selesai.

## 3a-2. FONDASI BARU (UI-T1, Okt 2026): TOKEN SEMANTIK + KOMPONEN `ui/`

Persiapan perombakan tampilan. Halaman lama tetap memakai ramp dan kelas di bawah sampai
dimigrasi per kelompok halaman; **halaman atau komponen baru memakai fondasi ini**.

- **Tailwind v4** lewat `@tailwindcss/vite` (tanpa `tailwind.config.js` dan PostCSS). Konfigurasi
  ada di `resources/css/app.css`: `@import 'tailwindcss'`, `@plugin '@tailwindcss/forms'`,
  `@custom-variant dark`, `@theme`.
- **Token semantik** (`SEMANTIC` di `tailwind.tokens.mjs`): peran, bukan warna. `background`,
  `foreground`, `card`, `popover`, `primary`, `secondary`, `muted`, `accent`, `destructive`,
  `success`, `warning` (masing-masing dengan `-foreground`), `border`, `input`, `ring`.
  Kelasnya `bg-card`, `text-muted-foreground`, `border-border`, `bg-success/10`. Nilai per tema
  di blok `[data-theme]` yang sama; `ThemeTokenTest` menjaga setiap token punya nilai di kedua
  tema dan punya `--color-*` di `@theme`.
- **Komponen** `resources/js/Components/ui/*` bergaya shadcn-vue di atas **Reka UI**: `button`,
  `badge`, `card`, `input`, `label`, `separator`, `skeleton`, `switch`, `table`, `tabs`, `dialog`,
  `tooltip`. Pilihan nyala/mati memakai `Switch` (bukan checkbox); `tone="warning"` untuk saklar berisiko.
  Impor dari folder (`import { Button } from '@/Components/ui/button'`). Gabung kelas dengan
  `cn()` dari `@/lib/utils`. Tes: `tests/js/uiKit.spec.js`.
- `Button` punya satu elemen akar, jadi `v-device-write` (WRITE-UI-1) dipasang langsung:
  `<Button v-device-write variant="destructive">`.
- Warna status (success/warning/destructive) hanya untuk status, bukan hiasan. Status basi atau
  tidak diketahui tidak pernah memakai `success`.
- Teks UI tetap lewat `vue-i18n` (`resources/js/lang/{id,en}.json`).

### Tampilan datar (UI-S1, owner memilih arah A + B + C, 24 Okt 2026)

- Ramp `slate`, `cyan`, `sky` dan token `canvas`/`surface` dipetakan ulang ke palet netral + aksen biru
  serat. Kelas lama (`bg-slate-900/40`, `text-cyan-400`) otomatis ikut gaya baru.
- Blok **di luar @layer** di akhir `app.css` meratakan semua halaman: tanpa grid neon, pendar, blur,
  dan halo; `nos-glass-*`, `nos-panel`, `nos-card`, `nos-surface` = permukaan `card` + `border`.
  Aturan kaca di bawah (3b) berlaku sebagai struktur, bukan lagi tampilannya.
- Ikon judul kartu (`nos-circle-*`) = kotak 32 px membulat, bukan lingkaran 48 px.
- Halaman baru tetap memakai `Components/ui` dan token semantik.

### Peta Fiber (UI-B1, arah B)

- Halaman `/network` (`Pages/Network/Tree.vue`): pohon OLT → PON → ODP → ONU, satu OLT per halaman.
  Server mengirim ringkasan per PON/ODP saja; daun ONU diambil per PON (`network.tree.onus`) saat
  cabang ODP dibuka. Jangan mengirim semua ONU sebuah OLT ke halaman (pelajaran NMS-07-E2).
- Warna PON = warna serat TIA-598 ke-n (`lib/fiber.js`, `App\Support\RxBand::FIBER_COLORS`): warna
  fisik, sengaja tidak ikut tema. Warna ODP tetap dari `OdpColors`.
- Level RX satu sumber per sisi: `App\Support\RxBand::level()` = `useRxLevel.js` (kritis ≤ -28 / ≥ -8,
  peringatan ≤ -25 / ≥ -10). ONU offline tidak masuk pita (RX lamanya bukan kondisi sekarang).
- Status jujur: bila OLT tidak FRESH, semua warna level jadi abu-abu (`levelText/levelBg/levelFill`
  dengan `stale = true`) dan `DataNotCurrent` tampil.

### Konsol Lapangan (UI-C1, arah C)

- `/field` dan `/field/onu/{olt}/{slot}/{port}/{onu}` (`Pages/Field/*`): untuk HP teknisi. Lebar maks
  `max-w-2xl`, teks dasar 16 px, angka RX 48 px, target sentuh minimal 56 px (`min-h-14`/`h-14`).
- Hanya baca. Tombol tulis (reboot, ganti nama) tidak diletakkan di sini; "Detail lengkap" membuka
  halaman port yang memakai `v-device-write`.
- `RxGauge` menandai posisi RX di pita -32..-6 dBm; abu-abu bila data tidak segar.

## 3b. TAMPILAN BAKU KOMPONEN

Ukuran dan varian di bawah diimplementasikan oleh komponen dasar (`PrimaryButton`,
`SecondaryButton`, `DangerButton`, `IconButton`, `Modal`, `ConfirmModal`, kelas `nos-pill-*`).
Ubah komponennya, jangan menulis ulang gaya per halaman.

| Unsur | Spesifikasi |
|---|---|
| Font | **Manrope 400–800** (`font-sans`). |
| Tombol teks | tinggi **44 px** (`min-h-11`), `sm` = 34 px untuk footer modal & bilah massal; `rounded-xl`; `text-sm font-semibold`, tanpa uppercase; ikon 16 px + `gap-2`. |
| Varian tombol | primary `from-cyan-500 to-sky-500` → hover `-600`, `text-onaccent` · secondary `border-slate-700/80 bg-slate-900/80 text-slate-200` · success `from-emerald-600 to-teal-600` · warning `from-amber-500 to-orange-500` teks `slate-950` · danger `from-rose-600 to-red-600`. |
| Fokus & nonaktif | `focus-visible:ring-2 ring-cyan-400/60 ring-offset-2 ring-offset-canvas` (danger: ring rose) · `disabled:opacity-50 cursor-not-allowed`. |
| Tombol ikon (aksi baris) | 36 px di `sm:` ke atas, 44 px di HP; `rounded-lg`; isi tipis `bg-{c}-500/15 text-{c}-300 ring-1 ring-{c}-500/30`; varian default/primary/info/success/warning/danger; selalu `title` (otomatis jadi `aria-label`). |
| Konfirmasi | varian **danger / warning / info**; lingkaran ikon berwarna varian; judul `text-base font-semibold`, pesan `text-sm text-slate-400`; Batal lalu tombol varian di kanan-bawah; Escape & klik scrim = batal. |
| Modal | scrim `bg-slate-950/80`; panel `rounded-2xl` opak, `max-h-[90vh] overflow-y-auto`. |
| Lencana status | `nos-pill` = `rounded-md px-2.5 py-0.5 text-xs font-semibold ring-1`; success emerald · warning amber · danger rose · info sky · muted slate. |
| Tabel | sel `px-4 py-3`; di HP pola kartu `nos-mobile-list`, di layar lebar `nos-table-desktop`. |

---

## 4. FORM CONTROLS & FILTER BAR

- Selalu gunakan kelas `nos-filter-control` untuk `<input>`, `<select>`, dan `<textarea>`.
- Tombol Filter & Submit Form:
  - Tombol Primer: `nos-filter-apply` atau `nos-btn-cyan`
  - Tombol Batal / Reset: `nos-filter-reset`
  - Tombol Destruktif: `nos-btn-danger` / `bg-rose-600`
- Tanggal dan Format Waktu:
  - Gunakan helper `@/utils/format`:
    - `formatDate(dateStr)` &rarr; `25 Agu 2026`
    - `formatDateTime(dateStr)` &rarr; `25 Agu 2026, 14:08 WIB`
    - `formatPeriod(month, year)` &rarr; `Agustus 2026`
    - `formatRupiah(number)` &rarr; `Rp 150.000`

---

## 5. CHECKLIST SEBELUM PENGEMBANGAN SELESAI

- [ ] Tidak ada dialog bawaan browser `alert()`, `confirm()`, atau `prompt()`.
- [ ] Semua tombol hapus/batal terhubung ke `ConfirmModal`.
- [ ] Pesan flash sukses/gagal dari controller muncul otomatis via Floating Toast.
- [ ] Tidak ada format tanggal ISO mentah `2026-08-25T...Z` yang terlihat oleh pengguna.
- [ ] Dropdown bulan menampilkan nama bulan bahasa Indonesia (`Januari` s/d `Desember`).
- [ ] Seluruh unit & feature test lulus 100% (`php artisan test`).
- [ ] Aset frontend berhasil di-compile tanpa error (`npm run build`).
