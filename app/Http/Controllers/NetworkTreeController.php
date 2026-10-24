<?php

namespace App\Http\Controllers;

use App\Models\SnmpOlt;
use App\Services\NetworkTreeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * UI-B1 "Peta Fiber": pohon OLT → PON → ODP → ONU dan pita redaman RX, satu OLT per halaman.
 * Hanya baca snapshot lokal. OLT di luar jangkauan pengguna tersaring `PartnerOltScope`
 * (pilihan diabaikan di halaman, 404 di endpoint ONU).
 */
class NetworkTreeController extends Controller
{
    public function __construct(private readonly NetworkTreeService $tree) {}

    public function index(Request $request): Response
    {
        $olts = SnmpOlt::query()->orderBy('name')->get(['id', 'name', 'last_polled_at', 'poll_interval_minutes', 'polling_enabled']);
        $selected = $olts->firstWhere('id', $request->integer('olt_id')) ?? $olts->first();

        return Inertia::render('Network/Tree', [
            'olts' => $olts->map(fn (SnmpOlt $olt) => [
                'id' => $olt->id,
                'name' => $olt->name,
                'freshness' => $olt->freshness(),
            ])->values(),
            'selected_olt_id' => $selected?->id,
            'tree' => $selected ? $this->tree->build(SnmpOlt::query()->findOrFail($selected->id)) : null,
        ]);
    }

    public function onus(SnmpOlt $olt, int $slot, int $port): JsonResponse
    {
        return response()->json($this->tree->onusFor($olt, $slot, $port));
    }
}
