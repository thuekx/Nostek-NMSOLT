<script setup>
import { formatDateTime } from '@/lib/datetime';
import { isFresh } from '@/lib/freshness';
import { computed } from 'vue';

// Kartu ringkasan port (status data, total, online, refresh terakhir) — padanan
// kartu statistik halaman ONU per port ZTE, dipakai bersama halaman Port ONU
// C-Data & HiOSO. `snapshot` = `last_test_result.port_onus.{slot}_{port}`.
const props = defineProps({
    snapshot: { type: Object, default: null },
    // NMS-07-D-2b: OLT freshness (App\Support\Freshness); only fresh data is green.
    freshness: { type: Object, default: null },
});

const onus = computed(() => props.snapshot?.onus ?? []);
const ok = computed(() => Boolean(props.snapshot?.ok));
const total = computed(() => props.snapshot?.count ?? onus.value.length);
const online = computed(() => onus.value.filter((o) => o.online).length);
const fresh = computed(() => isFresh(props.freshness));
const dataState = computed(() => (ok.value ? (props.freshness?.state ?? 'unknown') : 'empty'));
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
        <div class="nos-stat">
            <div class="flex items-center justify-between">
                <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ $t('portonus.stat_status') }}</p>
                <span class="h-2 w-2 rounded-full" :class="ok && fresh ? 'bg-emerald-500' : ok ? 'bg-amber-400' : 'bg-slate-300'"></span>
            </div>
            <p class="mt-3 text-2xl font-semibold" :class="ok && fresh ? 'text-emerald-400' : ok ? 'text-amber-300' : 'text-slate-400'" :data-state="dataState">
                {{ !ok ? $t('portonus.stat_empty') : fresh ? $t('portonus.stat_available') : $t(`dashboard.status_${dataState}`) }}
            </p>
        </div>
        <div class="nos-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ $t('portonus.stat_total_onu') }}</p>
            <p class="mt-3 text-2xl font-semibold tabular-nums text-white">{{ total }}</p>
        </div>
        <div class="nos-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ $t('portonus.stat_online') }}</p>
            <div class="mt-3 flex items-end gap-2">
                <p class="text-2xl font-semibold tabular-nums" :class="fresh ? 'text-emerald-400' : 'text-slate-400'">{{ online }}</p>
                <p class="mb-0.5 text-sm tabular-nums text-slate-400">/ {{ total }}</p>
            </div>
        </div>
        <div class="nos-stat">
            <p class="text-xs font-medium uppercase tracking-wider text-slate-500">{{ $t('portonus.stat_last_refresh') }}</p>
            <p class="mt-3 text-sm font-semibold text-white">{{ formatDateTime(snapshot?.refreshed_at) }}</p>
        </div>
    </div>
</template>
