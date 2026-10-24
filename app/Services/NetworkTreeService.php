<?php

namespace App\Services;

use App\Models\Odp;
use App\Models\OltPortLabel;
use App\Models\SmartOltInterfaceStatus;
use App\Models\SnmpOlt;
use App\Support\Freshness;
use App\Support\RxBand;

/**
 * UI-B1 "Peta Fiber": pohon jaringan satu OLT (OLT → PON → ODP → ONU) beserta pita redaman RX.
 *
 * Halaman hanya menerima ringkasan per PON dan per ODP. Daftar ONU (daun pohon) diambil per PON
 * lewat `onusFor()` saat cabang dibuka, jadi OLT C300 dengan ribuan ONU tidak dikirim utuh
 * (pelajaran NMS-07-E2: 27.6 MB pada 51.000 ONU). Semua baca dari snapshot lokal, tanpa SNMP.
 */
class NetworkTreeService
{
    /** Jumlah ONU di daftar "RX terburuk". */
    public const WORST_LIMIT = 10;

    public function __construct(private readonly OnuInventoryService $inventory) {}

    /**
     * @return array<string, mixed>
     */
    public function build(SnmpOlt $olt): array
    {
        $freshness = $olt->freshness();
        $stale = $freshness['state'] !== Freshness::FRESH;
        $onus = $this->inventory->collect(collect([$olt]))['onus'];

        $odps = Odp::query()
            ->where('snmp_olt_id', $olt->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slot', 'port', 'color', 'locked']);

        $labels = $this->portLabels($olt);
        $pons = [];

        // PON yang punya ODP tetap tampil walau belum ada ONU terbaca (ODP baru dipasang).
        foreach ($odps as $odp) {
            if ($odp->slot !== null && $odp->port !== null) {
                $pons["{$odp->slot}/{$odp->port}"] ??= $this->emptyPon($odp->slot, $odp->port, $labels);
            }
        }

        foreach ($onus as $onu) {
            $key = "{$onu['slot']}/{$onu['port']}";
            $pons[$key] ??= $this->emptyPon($onu['slot'], $onu['port'], $labels);
            $this->count($pons[$key]['totals'], $onu);

            $odpKey = $onu['odp_id'] === null ? 'none' : (string) $onu['odp_id'];
            $pons[$key]['odp_totals'][$odpKey] ??= RxBand::emptyTotals();
            $this->count($pons[$key]['odp_totals'][$odpKey], $onu);
        }

        foreach ($pons as $key => &$pon) {
            $pon['odps'] = $odps
                ->filter(fn (Odp $odp) => $odp->slot === $pon['slot'] && $odp->port === $pon['port'])
                ->map(fn (Odp $odp) => [
                    'id' => $odp->id,
                    'name' => $odp->name,
                    'color' => $odp->color,
                    'totals' => $pon['odp_totals'][(string) $odp->id] ?? RxBand::emptyTotals(),
                ])
                ->values()
                ->all();

            // ONU yang tertaut ke ODP yang tercatat di port lain tetap dihitung di PON ini
            // (sumber kebenaran posisi ONU = OLT), tapi ODP-nya tidak ikut digambar di sini.
            $pon['unassigned'] = $pon['odp_totals']['none'] ?? RxBand::emptyTotals();
            unset($pon['odp_totals']);
        }
        unset($pon);

        ksort($pons, SORT_NATURAL);

        // ODP tanpa port belum punya tempat di pohon; disebut supaya operator melengkapinya.
        $floating = $odps
            ->filter(fn (Odp $odp) => $odp->slot === null || $odp->port === null)
            ->map(fn (Odp $odp) => ['id' => $odp->id, 'name' => $odp->name])
            ->values()
            ->all();

        return [
            'olt' => ['id' => $olt->id, 'name' => $olt->name, 'freshness' => $freshness],
            'stale' => $stale,
            'pons' => array_values($pons),
            'floating_odps' => $floating,
            'band' => RxBand::histogram(array_column($this->measured($onus), 'rx_power_dbm')),
            'worst' => $this->worst($onus),
        ];
    }

    /**
     * Daun pohon: ONU satu PON, bentuk ringkas (tanpa kolom inventori lain).
     *
     * @return array{onus: list<array<string, mixed>>, stale: bool}
     */
    public function onusFor(SnmpOlt $olt, int $slot, int $port): array
    {
        $rows = $this->inventory->forPort($olt, $slot, $port)['onus'];

        return [
            'onus' => array_map(fn (array $onu) => $this->leaf($onu), $rows),
            'stale' => $olt->freshness()['state'] !== Freshness::FRESH,
        ];
    }

    /**
     * @param  array<string, mixed>  $onu
     * @return array<string, mixed>
     */
    private function leaf(array $onu): array
    {
        return [
            'slot' => $onu['slot'],
            'port' => $onu['port'],
            'onu_id' => $onu['onu_id'],
            'serial_number' => $onu['serial_number'],
            'name' => $onu['customer_name'] ?: $onu['name'],
            'online' => $onu['online'],
            'stale' => $onu['stale'],
            'rx_power_dbm' => $onu['online'] ? $onu['rx_power_dbm'] : null,
            'level' => RxBand::level($onu['online'] ? $onu['rx_power_dbm'] : null),
            'odp_id' => $onu['odp_id'],
            'odp_name' => $onu['odp_name'],
            'port_route' => $onu['port_route'],
        ];
    }

    /**
     * ONU terukur = online dan punya nilai RX. ONU offline membawa nilai RX lama dari OLT,
     * jadi tidak ikut pita (bukan kondisi sekarang).
     *
     * @param  array<int, array<string, mixed>>  $onus
     * @return array<int, array<string, mixed>>
     */
    private function measured(array $onus): array
    {
        return array_values(array_filter(
            $onus,
            fn (array $onu) => $onu['online'] && is_numeric($onu['rx_power_dbm']),
        ));
    }

    /**
     * Sepuluh ONU dengan RX terjauh dari rentang sehat: paling lemah di bawah, atau terlalu
     * kuat (≥ -8 dBm, receiver jenuh). Hanya level warning/critical yang masuk.
     *
     * @param  array<int, array<string, mixed>>  $onus
     * @return list<array<string, mixed>>
     */
    private function worst(array $onus): array
    {
        $bad = array_filter(
            $this->measured($onus),
            fn (array $onu) => in_array(RxBand::level((float) $onu['rx_power_dbm']), [RxBand::WARNING, RxBand::CRITICAL], true),
        );

        usort($bad, fn (array $a, array $b) => RxBand::distance((float) $b['rx_power_dbm']) <=> RxBand::distance((float) $a['rx_power_dbm']));

        return array_map(fn (array $onu) => $this->leaf($onu), array_slice($bad, 0, self::WORST_LIMIT));
    }

    /**
     * @param  array<string, int>  $totals
     * @param  array<string, mixed>  $onu
     */
    private function count(array &$totals, array $onu): void
    {
        $totals['onus']++;
        $totals[$onu['online'] ? 'online' : 'offline']++;
        $level = RxBand::level($onu['online'] ? $onu['rx_power_dbm'] : null);
        $totals[$level]++;
    }

    /**
     * @param  array<string, string>  $labels
     * @return array<string, mixed>
     */
    private function emptyPon(int $slot, int $port, array $labels): array
    {
        return [
            'slot' => $slot,
            'port' => $port,
            'label' => $labels["{$slot}/{$port}"] ?? null,
            'fiber_color' => RxBand::fiberColor($port),
            'totals' => RxBand::emptyTotals(),
            'odp_totals' => [],
        ];
    }

    /**
     * Label port: label sisi NMS bila operator mengisinya, selain itu deskripsi port di OLT.
     *
     * @return array<string, string>
     */
    private function portLabels(SnmpOlt $olt): array
    {
        $nms = OltPortLabel::query()
            ->where('snmp_olt_id', $olt->id)
            ->get(['slot', 'port', 'label'])
            ->mapWithKeys(fn (OltPortLabel $l) => ["{$l->slot}/{$l->port}" => $l->label])
            ->filter()
            ->all();

        return $nms + SmartOltInterfaceStatus::descriptionsBySlotPort($olt->id);
    }
}
