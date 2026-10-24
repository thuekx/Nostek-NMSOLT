<?php

namespace App\Http\Controllers;

use App\Models\AlarmEvent;
use App\Models\OnuMapPin;
use App\Models\SnmpOlt;
use App\Services\GlobalSearchService;
use App\Services\OnuInventoryService;
use App\Support\RxBand;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UI-C1 "Konsol Lapangan": halaman HP untuk teknisi. Cari ONU (SN, nama, interface, MAC), lihat
 * alarm aktif berat, lalu satu kartu ONU besar dengan RX, ODP, lokasi dan arah ke pelanggan.
 *
 * Hanya baca dari snapshot lokal. Aksi tulis tetap di halaman lengkap (gerbang device writes).
 * OLT/alarm/pin di luar jangkauan pengguna tersaring global scope (Demo/PartnerOltScope).
 */
class FieldConsoleController extends Controller
{
    /** Alarm yang ditampilkan di konsol: yang membuat teknisi berangkat. */
    private const URGENT = [AlarmEvent::SEVERITY_CRITICAL, AlarmEvent::SEVERITY_MAJOR];

    public function __construct(
        private readonly GlobalSearchService $search,
        private readonly OnuInventoryService $inventory,
    ) {}

    public function index(Request $request): Response
    {
        $validated = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $q = trim((string) ($validated['q'] ?? ''));

        $results = mb_strlen($q) >= 3
            ? collect($this->search->search($q, 20))
                ->where('type', 'onu')
                ->filter(fn (array $item) => $item['slot'] !== null && $item['onu_id'] !== null)
                ->map(fn (array $item) => [
                    'olt_id' => $item['olt_id'],
                    'slot' => $item['slot'],
                    'port' => $item['port'],
                    'onu_id' => $item['onu_id'],
                    'label' => $item['label'],
                    'sublabel' => $item['sublabel'],
                ])
                ->values()
            : collect();

        $alarms = AlarmEvent::query()
            ->with('olt:id,name')
            ->where('status', AlarmEvent::STATUS_ACTIVE)
            ->whereIn('severity', self::URGENT)
            ->orderByRaw("case when severity = 'critical' then 0 else 1 end")
            ->latest('first_seen_at')
            ->limit(30)
            ->get()
            ->map(fn (AlarmEvent $alarm) => [
                'id' => $alarm->id,
                'severity' => $alarm->severity,
                'type' => $alarm->type,
                'olt_id' => $alarm->snmp_olt_id,
                'olt_name' => $alarm->olt?->name,
                'slot' => $alarm->slot,
                'port' => $alarm->port,
                'onu_id' => $alarm->onu_id,
                'serial_number' => $alarm->serial_number,
                'message' => $alarm->message,
                'first_seen_at' => $alarm->first_seen_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('Field/Index', [
            'q' => $q,
            'results' => $results,
            'alarms' => $alarms,
        ]);
    }

    public function onu(SnmpOlt $olt, int $slot, int $port, int $onuId): Response
    {
        $onu = $this->inventory->findOne($olt, $slot, $port, $onuId, withOdp: true);
        abort_if($onu === null, 404);

        $pin = OnuMapPin::query()
            ->where('snmp_olt_id', $olt->id)
            ->where('slot', $slot)
            ->where('port', $port)
            ->where('onu_id', $onuId)
            ->first(['latitude', 'longitude', 'customer_name', 'address', 'phone']);

        $alarms = AlarmEvent::query()
            ->where('snmp_olt_id', $olt->id)
            ->where('status', AlarmEvent::STATUS_ACTIVE)
            ->where('slot', $slot)
            ->where('port', $port)
            ->where(fn ($q) => $q->where('onu_id', $onuId)->orWhereNull('onu_id'))
            ->latest('first_seen_at')
            ->limit(10)
            ->get(['id', 'severity', 'type', 'onu_id', 'message', 'first_seen_at'])
            ->map(fn (AlarmEvent $alarm) => [
                'id' => $alarm->id,
                'severity' => $alarm->severity,
                'type' => $alarm->type,
                // Alarm port/ODP (tanpa onu_id) juga relevan: ONU ini ikut terdampak.
                'port_wide' => $alarm->onu_id === null,
                'message' => $alarm->message,
                'first_seen_at' => $alarm->first_seen_at?->toIso8601String(),
            ])
            ->values();

        $rx = $onu['online'] ? $onu['rx_power_dbm'] : null;

        return Inertia::render('Field/Onu', [
            'olt' => ['id' => $olt->id, 'name' => $olt->name, 'freshness' => $olt->freshness()],
            'onu' => [
                'slot' => $slot,
                'port' => $port,
                'onu_id' => $onuId,
                'interface' => $onu['interface'],
                'serial_number' => $onu['serial_number'],
                'name' => $pin?->customer_name ?: ($onu['customer_name'] ?: $onu['name']),
                'online' => $onu['online'],
                'phase_state' => $onu['phase_state'],
                'last_down_cause' => $onu['last_down_cause'],
                'rx_power_dbm' => is_numeric($rx) ? (float) $rx : null,
                'level' => RxBand::level($rx),
                'odp_id' => $onu['odp_id'],
                'odp_name' => $onu['odp_name'],
                'port_route' => $onu['port_route'],
                'fiber_color' => RxBand::fiberColor($port),
            ],
            'pin' => $pin ? [
                'latitude' => (float) $pin->latitude,
                'longitude' => (float) $pin->longitude,
                'address' => $pin->address,
                'phone' => $pin->phone,
            ] : null,
            'alarms' => $alarms,
        ]);
    }
}
