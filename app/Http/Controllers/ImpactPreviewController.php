<?php

namespace App\Http\Controllers;

use App\Models\AlarmEvent;
use App\Models\SnmpOlt;
use App\Services\OnuInventoryService;
use App\Support\Freshness;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * UI-F6: pratinjau dampak sebelum aksi tulis (fondasi NMS-11). Menjawab "siapa yang kena"
 * untuk satu PON atau sebagian ONU di PON itu: jumlah ONU/online, pelanggan, ODP, alarm aktif.
 *
 * Hanya baca dari snapshot lokal dan DB; tidak menyentuh perangkat dan tidak mengubah gerbang
 * device writes. Bila pratinjau gagal, aksinya tetap bisa dikonfirmasi (bukan penghalang).
 */
class ImpactPreviewController extends Controller
{
    /** Batas nama pelanggan yang dikirim (cukup untuk dibaca, bukan daftar penuh). */
    private const NAME_LIMIT = 8;

    public function __construct(private readonly OnuInventoryService $inventory) {}

    public function __invoke(Request $request, SnmpOlt $olt, int $slot, int $port): JsonResponse
    {
        $validated = $request->validate([
            'onu_ids' => ['nullable', 'array', 'max:256'],
            'onu_ids.*' => ['integer', 'min:0'],
        ]);
        $ids = array_map('intval', $validated['onu_ids'] ?? []);

        $onus = $this->inventory->forPort($olt, $slot, $port)['onus'];
        if ($ids !== []) {
            $onus = array_values(array_filter($onus, fn (array $o) => in_array($o['onu_id'], $ids, true)));
        }

        $odps = [];
        $names = [];
        $named = 0;
        foreach ($onus as $onu) {
            $odp = (string) ($onu['odp_name'] ?? '');
            $odps[$odp] = ($odps[$odp] ?? 0) + 1;
            $name = $onu['customer_name'] ?: $onu['name'];
            if ($name) {
                $named++;
                if (count($names) < self::NAME_LIMIT) {
                    $names[] = $name;
                }
            }
        }
        arsort($odps);

        $alarms = AlarmEvent::query()
            ->where('snmp_olt_id', $olt->id)
            ->where('status', AlarmEvent::STATUS_ACTIVE)
            ->where('slot', $slot)
            ->where('port', $port)
            ->when($ids !== [], fn ($q) => $q->where(fn ($w) => $w->whereIn('onu_id', $ids)->orWhereNull('onu_id')))
            ->count();

        return response()->json([
            'scope' => $ids === [] ? 'port' : 'onus',
            'onus' => count($onus),
            'online' => count(array_filter($onus, fn (array $o) => $o['online'])),
            'customers' => $names,
            'more_customers' => $named - count($names),
            'odps' => array_map(
                fn (int|string $name, int $count) => ['name' => $name === '' ? null : (string) $name, 'onus' => $count],
                array_keys($odps),
                array_values($odps),
            ),
            'active_alarms' => $alarms,
            // Data tidak segar: angka di atas adalah keadaan terakhir yang diketahui.
            'stale' => $olt->freshness()['state'] !== Freshness::FRESH,
        ]);
    }
}
