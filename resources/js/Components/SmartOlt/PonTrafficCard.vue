<script setup>
// TRF-1: traffic history of one PON port (IF-MIB octet counters read by the poller).
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
// Chart.js dimuat malas di dalam ChartCanvas — lihat komentar di sana.
import ChartCanvas from '@/Components/Charts/ChartCanvas.vue';
import DataNotCurrent from '@/Components/Shell/DataNotCurrent.vue';
import { areaFill, axisX, axisY, hairline, lineChartOptions, TIME_STEP_DAY, timeTicks } from '@/lib/chartOptions';
import { formatAxisTime, formatClock } from '@/lib/datetime';
import { formatBps, trafficSeries } from '@/lib/traffic';
import { tokenHex } from '@/lib/theme';
import { Activity } from '@lucide/vue';

const props = defineProps({
    // [{ t, in_bps, out_bps, in_max_bps, out_max_bps }]
    history: { type: Array, default: () => [] },
    range: { type: String, default: '7d' },
    // false: the last poll had no octet counters for this port (agent without ifHCIn/OutOctets).
    counters: { type: Boolean, default: null },
    freshness: { type: Object, default: null },
});

const RANGES = ['24h', '7d', '30d', '90d', '1y', '3y'];

const series = computed(() => trafficSeries(props.history));
const hasData = computed(() => series.value.stats !== null);

const chartData = computed(() => {
    const cyan = tokenHex('cyan-400', '#22d3ee');
    const violet = tokenHex('violet-400', '#a78bfa');
    const dense = series.value.inPoints.length > 60;
    const line = (label, data, color) => ({
        label,
        data,
        borderColor: color,
        backgroundColor: areaFill(color, 0.25, 0.02),
        pointBackgroundColor: color,
        borderWidth: 2,
        fill: 'start',
        cubicInterpolationMode: 'monotone',
        pointRadius: dense ? 0 : 3,
        pointBorderWidth: 0,
        pointHoverRadius: 5,
    });

    return {
        datasets: [
            line('Downstream', series.value.outPoints, cyan),
            line('Upstream', series.value.inPoints, violet),
        ],
    };
});

const chartOptions = computed(() => {
    const xs = series.value.inPoints.map((p) => p.x);
    const xMin = xs.length ? Math.min(...xs) : 0;
    const xMax = xs.length ? Math.max(...xs) : 0;
    const { step } = timeTicks(xMin, xMax, 6);
    const tick = { color: tokenHex('slate-400', '#94a3b8'), font: { size: 10 } };

    return lineChartOptions({
        legend: true,
        tooltipCallbacks: {
            title: (items) => (items.length ? formatClock(items[0].parsed.x) : ''),
            label: (ctx) => ` ${ctx.dataset.label}: ${formatBps(ctx.parsed.y)}`,
        },
        scales: {
            x: {
                ...axisX({ ...tick, callback: (v) => formatAxisTime(v, { date: step >= TIME_STEP_DAY, year: step >= 28 * TIME_STEP_DAY }) }),
                type: 'linear',
                min: xMin,
                max: xMax,
                border: { color: hairline(0.2) },
                afterBuildTicks: (scale) => {
                    scale.ticks = timeTicks(scale.min, scale.max, 6).values.map((value) => ({ value }));
                },
            },
            y: axisY({ ...tick, callback: (v) => formatBps(v) }, { min: 0, grid: { color: hairline(0.08) } }),
        },
    });
});

const setRange = (key) => {
    if (key === props.range) return;
    router.reload({
        data: { range: key },
        only: ['traffic_history', 'traffic_range'],
        preserveState: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="nos-surface overflow-hidden rounded-lg border border-white/10 bg-slate-900/40 shadow-lg shadow-black/30 backdrop-blur-xl" data-testid="pon-traffic-card">
        <div class="flex flex-wrap items-center gap-3 border-b border-white/10 px-4 py-4 sm:px-6">
            <Activity class="h-5 w-5 text-cyan-400" />
            <h3 class="text-base font-semibold text-white">{{ $t('traffic.title') }}</h3>
            <div class="ml-auto inline-flex rounded-lg border border-white/10 bg-slate-900/50 p-0.5">
                <button
                    v-for="key in RANGES"
                    :key="key"
                    type="button"
                    class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                    :class="key === range ? 'bg-cyan-500/20 text-cyan-300' : 'text-slate-400 hover:text-white'"
                    @click="setRange(key)"
                >
                    {{ $t(`shell.rx_range_${key}`) }}
                </button>
            </div>
        </div>

        <div class="flex flex-col gap-3 p-4 sm:p-6">
            <DataNotCurrent :freshness="freshness" />
            <template v-if="hasData">
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <div class="rounded-lg bg-slate-900/40 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ $t('traffic.last') }}</p>
                        <p class="text-sm font-semibold tabular-nums text-cyan-300">↓ {{ formatBps(series.stats.lastOut) }}</p>
                        <p class="text-sm font-semibold tabular-nums text-violet-300">↑ {{ formatBps(series.stats.lastIn) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-900/40 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ $t('traffic.avg') }}</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-200">↓ {{ formatBps(series.stats.avgOut) }}</p>
                        <p class="text-sm font-semibold tabular-nums text-slate-200">↑ {{ formatBps(series.stats.avgIn) }}</p>
                    </div>
                    <div class="rounded-lg bg-slate-900/40 px-3 py-2">
                        <p class="text-[10px] uppercase tracking-wider text-slate-500">{{ $t('traffic.peak') }}</p>
                        <p class="text-sm font-semibold tabular-nums text-amber-300">↓ {{ formatBps(series.stats.peakOut) }}</p>
                        <p class="text-sm font-semibold tabular-nums text-amber-300">↑ {{ formatBps(series.stats.peakIn) }}</p>
                    </div>
                </div>
                <ChartCanvas type="line" :height="240" :data="chartData" :options="chartOptions" :label="$t('traffic.title')" />
                <p class="text-xs text-slate-500">{{ $t('traffic.direction_note') }}</p>
            </template>
            <p v-else class="py-10 text-center text-sm text-slate-500" data-testid="pon-traffic-empty">
                {{ counters === false ? $t('traffic.no_counters') : $t('traffic.empty') }}
            </p>
        </div>
    </div>
</template>
