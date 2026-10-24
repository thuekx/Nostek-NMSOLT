<script setup>
// UI-B1: pita redaman RX satu OLT. Batang 1 dB dari -32 s.d. -6 dBm (histogram dari server),
// zona ambang di belakangnya. SVG biasa: ringan, tajam di HP, dan tanpa pustaka grafik.
import { computed } from 'vue';
import { levelFill } from '@/lib/fiber';

const props = defineProps({
    band: { type: Object, required: true },
    stale: { type: Boolean, default: false },
});

const W = 520;
const H = 140;
const PAD_X = 8;
const AXIS = 18;

const bins = computed(() => props.band.bins ?? []);
const minDbm = computed(() => bins.value[0]?.dbm ?? -32);
const span = computed(() => Math.max(1, bins.value.length));
const max = computed(() => Math.max(1, ...bins.value.map((b) => b.count)));
const barW = computed(() => (W - PAD_X * 2) / span.value);

const x = (dbm) => PAD_X + (dbm - minDbm.value) * barW.value;
const barH = (count) => (count === 0 ? 0 : Math.max(2, (count / max.value) * (H - AXIS - 8)));

// Zona latar: kritis (≤ -28), peringatan (-28..-25), baik (-25..-10), peringatan (-10..-8), kritis (≥ -8).
const zones = computed(() => {
    const end = minDbm.value + span.value;
    return [
        { from: minDbm.value, to: -28, level: 'critical' },
        { from: -28, to: -25, level: 'warning' },
        { from: -25, to: -10, level: 'good' },
        { from: -10, to: -8, level: 'warning' },
        { from: -8, to: end, level: 'critical' },
    ];
});

const ticks = [-30, -25, -20, -15, -10];
</script>

<template>
    <svg
        :viewBox="`0 0 ${W} ${H}`"
        class="h-auto w-full"
        role="img"
        :aria-label="$t('network.band_aria', { total: band.total })"
        data-testid="rx-band-chart"
    >
        <g aria-hidden="true">
            <rect
                v-for="z in zones"
                :key="z.from"
                :x="x(z.from)"
                y="0"
                :width="Math.max(0, x(z.to) - x(z.from))"
                :height="H - AXIS"
                :class="levelFill(z.level, stale)"
                opacity="0.07"
            />
            <line
                v-for="t in [-28, -25, -10, -8]"
                :key="`th${t}`"
                :x1="x(t)"
                :x2="x(t)"
                y1="0"
                :y2="H - AXIS"
                class="stroke-border"
                stroke-dasharray="3 3"
            />
            <rect
                v-for="b in bins"
                :key="b.dbm"
                :x="x(b.dbm) + 1"
                :y="H - AXIS - barH(b.count)"
                :width="Math.max(1, barW - 2)"
                :height="barH(b.count)"
                rx="1.5"
                :class="levelFill(b.level, stale)"
                :data-dbm="b.dbm"
                :data-count="b.count"
            >
                <title>{{ $t('network.band_bar', { from: b.dbm, to: b.dbm + 1, count: b.count }) }}</title>
            </rect>
            <line :x1="PAD_X" :x2="W - PAD_X" :y1="H - AXIS" :y2="H - AXIS" class="stroke-border" />
            <text
                v-for="t in ticks"
                :key="`tk${t}`"
                :x="x(t)"
                :y="H - 4"
                text-anchor="middle"
                class="fill-muted-foreground text-[10px] tabular-nums"
            >{{ t }}</text>
        </g>
    </svg>
</template>
