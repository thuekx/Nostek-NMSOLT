<script setup>
import { computed } from 'vue';
// Chart.js dimuat malas di dalam ChartCanvas — lihat komentar di sana.
import ChartCanvas from '@/Components/Charts/ChartCanvas.vue';
import { areaFill } from '@/lib/chartOptions';
import { tokenHex } from '@/lib/theme';

const props = defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: [Object, Function], required: true },
    accent: { type: String, default: 'sky' },
    sublabels: { type: Array, default: () => [] },
    sparkline: { type: Array, default: () => [] },
    sparklineLabel: { type: String, default: 'Trend' },
});


// Dibaca dari token, jadi ikut tema: cyan-400 & slate-500 menggelap di tema
// terang (stop 500 lainnya dipatok sama di kedua tema).
const accentHex = computed(() => ({
    sky: tokenHex('sky-500', '#0ea5e9'),
    cyan: tokenHex('cyan-400', '#22d3ee'),
    emerald: tokenHex('emerald-500', '#10b981'),
    purple: tokenHex('purple-500', '#a855f7'),
    red: tokenHex('red-500', '#ef4444'),
    amber: tokenHex('amber-500', '#f59e0b'),
    slate: tokenHex('slate-500', '#64748b'),
}[props.accent] ?? tokenHex('sky-500', '#0ea5e9')));

// Sparkline murni hiasan: wadahnya pointer-events-none, jadi tanpa tooltip,
// sumbu, maupun interaksi.
const sparkOptions = {
    responsive: true,
    maintainAspectRatio: false,
    animation: false,
    events: [],
    layout: { padding: { top: 2, bottom: 2 } },
    plugins: { legend: { display: false }, tooltip: { enabled: false } },
    scales: { x: { display: false }, y: { display: false, grace: '8%' } },
};

const hasVariance = computed(() => {
    const arr = props.sparkline ?? [];
    if (arr.length < 2) return false;
    const min = Math.min(...arr);
    const max = Math.max(...arr);
    return max - min > 0;
});

const sparkData = computed(() => {
    const values = props.sparkline.length > 0 ? props.sparkline : [0, 0, 0, 0, 0, 0, 0];

    return {
        labels: values.map((_, i) => i),
        datasets: [{
            data: values,
            borderColor: accentHex.value,
            backgroundColor: areaFill(accentHex.value, 0.25, 0),
            borderWidth: 1.5,
            fill: 'start',
            cubicInterpolationMode: 'monotone',
            pointRadius: 0,
        }],
    };
});
</script>

<template>
    <!-- UI-S1: kartu datar. Label + angka di kiri, ikon kecil kanan-atas, sparkline tipis di bawah. -->
    <div class="nos-glass-card relative flex flex-col gap-3 overflow-hidden p-4!">
        <div class="flex items-start justify-between gap-3">
            <p class="text-[13px] font-medium text-muted-foreground">{{ label }}</p>
            <component :is="icon" class="h-4 w-4 shrink-0" :style="{ color: accentHex }" aria-hidden="true" />
        </div>
        <p class="text-[1.75rem] font-semibold leading-none tracking-tight tabular-nums text-foreground">{{ value }}</p>

        <div v-if="hasVariance" class="pointer-events-none h-10 w-full">
            <ChartCanvas
                type="line"
                :height="40"
                :data="sparkData"
                :options="sparkOptions"
                :label="sparklineLabel"
            />
        </div>

        <div v-if="sublabels.length > 0" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border pt-3 text-xs">
            <div v-for="sub in sublabels" :key="sub.label" class="flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: sub.color ?? '#64748b' }" />
                <span class="text-muted-foreground">{{ sub.label }}</span>
                <span class="font-semibold tabular-nums text-foreground">{{ sub.value }}</span>
            </div>
        </div>
    </div>
</template>
