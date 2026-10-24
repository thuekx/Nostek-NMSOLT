<script setup>
// UI-C1: posisi RX satu ONU di pita -32..-6 dBm, besar dan terbaca di bawah matahari.
// Zona sama dengan RxBand/useRxLevel; data tidak segar = abu-abu (tidak pernah hijau).
import { computed } from 'vue';
import { levelBg } from '@/lib/fiber';

const props = defineProps({
    dbm: { type: Number, default: null },
    stale: { type: Boolean, default: false },
});

const MIN = -32;
const MAX = -6;
const pct = (v) => ((Math.min(MAX, Math.max(MIN, v)) - MIN) / (MAX - MIN)) * 100;

const zones = [
    { from: MIN, to: -28, level: 'critical' },
    { from: -28, to: -25, level: 'warning' },
    { from: -25, to: -10, level: 'good' },
    { from: -10, to: -8, level: 'warning' },
    { from: -8, to: MAX, level: 'critical' },
];

const marker = computed(() => (props.dbm === null ? null : pct(props.dbm)));
</script>

<template>
    <div class="w-full" data-testid="rx-gauge">
        <div class="relative h-4 w-full overflow-hidden rounded-full">
            <span
                v-for="z in zones"
                :key="z.from"
                class="absolute inset-y-0 opacity-60"
                :class="levelBg(z.level, stale)"
                :style="{ left: `${pct(z.from)}%`, width: `${pct(z.to) - pct(z.from)}%` }"
            />
        </div>
        <div class="relative h-4">
            <span
                v-if="marker !== null"
                class="absolute -top-6 h-8 w-1.5 -translate-x-1/2 rounded-full bg-foreground ring-2 ring-background"
                :style="{ left: `${marker}%` }"
                data-testid="rx-gauge-marker"
            />
        </div>
        <div class="relative h-4 text-xs tabular-nums text-muted-foreground">
            <span v-for="t in [-28, -25, -10]" :key="t" class="absolute -translate-x-1/2" :style="{ left: `${pct(t)}%` }">{{ t }}</span>
        </div>
    </div>
</template>
