<script setup>
// UI-B1 "Peta Fiber": OLT → PON → ODP → ONU dengan pita redaman RX dan daftar RX terburuk.
// Ringkasan per PON/ODP datang dari server; ONU satu PON diambil saat cabangnya dibuka.
import axios from 'axios';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { ChevronRight, ExternalLink, GitBranch, Network } from '@lucide/vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataNotCurrent from '@/Components/Shell/DataNotCurrent.vue';
import RxBandChart from '@/Components/Network/RxBandChart.vue';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { fiberHex, levelBg, levelText, totalsSegments } from '@/lib/fiber';
import { odpColor } from '@/lib/odpColors';

const props = defineProps({
    olts: { type: Array, default: () => [] },
    selected_olt_id: { type: Number, default: null },
    tree: { type: Object, default: null },
});

const { t } = useI18n({ useScope: 'global' });

const oltId = ref(props.selected_olt_id ?? '');
const stale = computed(() => Boolean(props.tree?.stale));
const selectedOlt = computed(() => props.olts.find((o) => o.id === props.selected_olt_id) ?? null);

const onOltChange = () => {
    open.pons = {};
    open.odps = {};
    router.get(route('network.tree'), { olt_id: oltId.value }, { preserveScroll: true, replace: true });
};

const ponKey = (p) => `${p.slot}/${p.port}`;

// Cabang yang terbuka + cache ONU per PON (satu permintaan per PON).
const open = reactive({ pons: {}, odps: {} });
const leaves = reactive({});

const loadPon = async (pon) => {
    const key = ponKey(pon);
    if (leaves[key]?.onus || leaves[key]?.loading) return;
    leaves[key] = { loading: true, error: false, onus: null };
    try {
        const { data } = await axios.get(route('network.tree.onus', [props.selected_olt_id, pon.slot, pon.port]));
        leaves[key] = { loading: false, error: false, onus: data.onus ?? [] };
    } catch {
        leaves[key] = { loading: false, error: true, onus: null };
    }
};

const togglePon = (pon) => {
    const key = ponKey(pon);
    open.pons[key] = !open.pons[key];
};

const toggleOdp = (pon, odpKey) => {
    const key = `${ponKey(pon)}#${odpKey}`;
    open.odps[key] = !open.odps[key];
    if (open.odps[key]) loadPon(pon);
};

const onusOf = (pon, odpKey) => {
    const all = leaves[ponKey(pon)]?.onus ?? [];
    return all.filter((o) => (odpKey === 'none' ? o.odp_id === null : o.odp_id === odpKey));
};

const branches = (pon) => [
    ...pon.odps.map((o) => ({ key: o.id, name: o.name, color: o.color, totals: o.totals, unassigned: false })),
    ...(pon.unassigned.onus > 0 ? [{ key: 'none', name: t('network.unassigned'), color: null, totals: pon.unassigned, unassigned: true }] : []),
];

const onuHref = (onu) =>
    `${route(onu.port_route ?? 'smartolt.port-onus', [props.selected_olt_id, onu.slot, onu.port])}?focus=${onu.onu_id}`;

const rxText = (onu) => (onu.rx_power_dbm === null ? '—' : `${Number(onu.rx_power_dbm).toFixed(1)}`);

const summary = computed(() => {
    const pons = props.tree?.pons ?? [];
    return pons.reduce(
        (acc, p) => ({ onus: acc.onus + p.totals.onus, online: acc.online + p.totals.online, odps: acc.odps + p.odps.length }),
        { onus: 0, online: 0, odps: 0 },
    );
});

const legend = ['good', 'warning', 'critical', 'none', 'offline'];
</script>

<template>
    <Head :title="t('network.title')" />

    <AuthenticatedLayout>
        <div class="space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-xl font-semibold tracking-tight text-foreground">{{ t('network.title') }}</h1>
                    <p class="text-sm text-muted-foreground">{{ t('network.subtitle') }}</p>
                </div>
                <label v-if="olts.length" class="flex w-full items-center gap-2 text-sm sm:w-auto">
                    <span class="shrink-0 text-muted-foreground">{{ t('network.olt') }}</span>
                    <select v-model="oltId" class="nos-filter-control w-full sm:w-64" data-testid="network-olt-select" @change="onOltChange">
                        <option v-for="o in olts" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </label>
            </div>

            <Card v-if="!tree" data-testid="network-empty">
                <CardContent class="py-10 text-center">
                    <Network class="mx-auto mb-3 h-8 w-8 text-muted-foreground" aria-hidden="true" />
                    <p class="font-medium text-foreground">{{ t('network.no_olt_title') }}</p>
                    <p class="text-sm text-muted-foreground">{{ t('network.no_olt_hint') }}</p>
                </CardContent>
            </Card>

            <template v-else>
                <DataNotCurrent :freshness="selectedOlt?.freshness ?? tree.olt.freshness" />

                <div class="grid gap-5 lg:grid-cols-12">
                    <!-- Pohon -->
                    <Card class="lg:col-span-7" data-testid="network-tree">
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <GitBranch class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                                {{ tree.olt.name }}
                            </CardTitle>
                            <CardDescription>
                                {{ t('network.tree_summary', { pons: tree.pons.length, odps: summary.odps, online: summary.online, onus: summary.onus }) }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-1 px-2 sm:px-4">
                            <div class="mb-3 flex flex-wrap gap-x-4 gap-y-1 px-2 text-xs text-muted-foreground">
                                <span v-for="l in legend" :key="l" class="flex items-center gap-1.5">
                                    <span class="h-2 w-2 rounded-full" :class="levelBg(l, stale)" />
                                    {{ t(`network.legend_${l}`) }}
                                </span>
                            </div>

                            <p v-if="!tree.pons.length" class="px-2 py-6 text-center text-sm text-muted-foreground">{{ t('network.no_pons') }}</p>

                            <div v-for="pon in tree.pons" :key="ponKey(pon)" class="rounded-lg" :data-testid="`pon-${pon.slot}-${pon.port}`">
                                <button
                                    type="button"
                                    class="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    :aria-expanded="Boolean(open.pons[ponKey(pon)])"
                                    @click="togglePon(pon)"
                                >
                                    <ChevronRight class="h-4 w-4 shrink-0 text-muted-foreground transition-transform" :class="open.pons[ponKey(pon)] ? 'rotate-90' : ''" aria-hidden="true" />
                                    <span
                                        class="h-3 w-3 shrink-0 rounded-full ring-1 ring-border"
                                        :style="{ backgroundColor: fiberHex(pon.fiber_color) }"
                                        :title="t('network.fiber_color', { color: t(`network.colors.${pon.fiber_color}`) })"
                                        :data-fiber="pon.fiber_color"
                                    />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-foreground">
                                            PON {{ pon.slot }}/{{ pon.port }}
                                            <span v-if="pon.label" class="font-normal text-muted-foreground">· {{ pon.label }}</span>
                                        </span>
                                        <span class="mt-1 flex h-1.5 w-full max-w-56 overflow-hidden rounded-full bg-muted">
                                            <span v-for="s in totalsSegments(pon.totals)" :key="s.level" :class="levelBg(s.level, stale)" :style="{ width: `${s.pct}%` }" />
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-right text-xs tabular-nums text-muted-foreground">
                                        <span class="font-semibold text-foreground">{{ pon.totals.online }}</span>/{{ pon.totals.onus }}
                                        <span v-if="!stale && pon.totals.critical" class="ml-2 text-destructive">{{ t('network.n_critical', { n: pon.totals.critical }) }}</span>
                                    </span>
                                </button>

                                <div v-if="open.pons[ponKey(pon)]" class="ml-5 border-l border-border pl-3 sm:ml-6">
                                    <p v-if="!branches(pon).length" class="px-2 py-2 text-xs text-muted-foreground">{{ t('network.no_odps') }}</p>
                                    <div v-for="b in branches(pon)" :key="b.key">
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-3 rounded-md px-2 py-1.5 text-left hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            :aria-expanded="Boolean(open.odps[`${ponKey(pon)}#${b.key}`])"
                                            :data-testid="`odp-${pon.slot}-${pon.port}-${b.key}`"
                                            @click="toggleOdp(pon, b.key)"
                                        >
                                            <ChevronRight class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform" :class="open.odps[`${ponKey(pon)}#${b.key}`] ? 'rotate-90' : ''" aria-hidden="true" />
                                            <span class="h-2.5 w-2.5 shrink-0 rotate-45 rounded-[2px]" :class="b.unassigned ? 'border border-dashed border-muted-foreground' : ''" :style="b.unassigned ? {} : { backgroundColor: odpColor(b) }" />
                                            <span class="min-w-0 flex-1 truncate text-sm" :class="b.unassigned ? 'italic text-muted-foreground' : 'text-foreground'">{{ b.name }}</span>
                                            <span class="flex h-1.5 w-16 shrink-0 overflow-hidden rounded-full bg-muted sm:w-24">
                                                <span v-for="s in totalsSegments(b.totals)" :key="s.level" :class="levelBg(s.level, stale)" :style="{ width: `${s.pct}%` }" />
                                            </span>
                                            <span class="w-12 shrink-0 text-right text-xs tabular-nums text-muted-foreground">{{ b.totals.online }}/{{ b.totals.onus }}</span>
                                        </button>

                                        <div v-if="open.odps[`${ponKey(pon)}#${b.key}`]" class="ml-4 border-l border-dashed border-border pl-3">
                                            <p v-if="leaves[ponKey(pon)]?.loading" class="px-2 py-1.5 text-xs text-muted-foreground">{{ t('network.loading') }}</p>
                                            <p v-else-if="leaves[ponKey(pon)]?.error" class="px-2 py-1.5 text-xs text-destructive">{{ t('network.load_error') }}</p>
                                            <template v-else>
                                                <p v-if="!onusOf(pon, b.key).length" class="px-2 py-1.5 text-xs text-muted-foreground">{{ t('network.no_onus') }}</p>
                                                <Link
                                                    v-for="onu in onusOf(pon, b.key)"
                                                    :key="onu.onu_id"
                                                    :href="onuHref(onu)"
                                                    class="group flex items-center gap-3 rounded-md px-2 py-1.5 hover:bg-accent"
                                                    :data-testid="`onu-${onu.slot}-${onu.port}-${onu.onu_id}`"
                                                >
                                                    <span class="h-2 w-2 shrink-0 rounded-full" :class="levelBg(onu.online ? onu.level : 'offline', stale)" />
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-sm text-foreground">{{ onu.name || onu.serial_number || `ONU ${onu.onu_id}` }}</span>
                                                        <span class="block truncate font-mono text-[11px] text-muted-foreground">#{{ onu.onu_id }} · {{ onu.serial_number ?? '—' }}</span>
                                                    </span>
                                                    <span class="shrink-0 text-xs tabular-nums" :class="onu.online ? levelText(onu.level, stale) : 'text-muted-foreground'">
                                                        {{ onu.online ? `${rxText(onu)} dBm` : t('network.offline') }}
                                                    </span>
                                                    <ExternalLink class="h-3.5 w-3.5 shrink-0 text-muted-foreground opacity-0 group-hover:opacity-100" aria-hidden="true" />
                                                </Link>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <p v-if="tree.floating_odps.length" class="px-2 pt-3 text-xs text-muted-foreground" data-testid="floating-odps">
                                {{ t('network.floating_odps', { names: tree.floating_odps.map((o) => o.name).join(', ') }) }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Pita RX + terburuk -->
                    <div class="space-y-5 lg:col-span-5">
                        <Card data-testid="rx-band">
                            <CardHeader>
                                <CardTitle>{{ t('network.band_title') }}</CardTitle>
                                <CardDescription>{{ t('network.band_subtitle') }}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <p v-if="!tree.band.total" class="py-6 text-center text-sm text-muted-foreground">{{ t('network.band_empty') }}</p>
                                <template v-else>
                                    <RxBandChart :band="tree.band" :stale="stale" />
                                    <div class="mt-3 grid grid-cols-4 gap-2 text-center">
                                        <div v-for="k in ['good', 'warning', 'critical']" :key="k" class="rounded-md bg-muted/50 px-2 py-1.5">
                                            <p class="text-base font-semibold tabular-nums" :class="levelText(k, stale)" :data-testid="`band-${k}`">{{ tree.band[k] }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ t(`network.legend_${k}`) }}</p>
                                        </div>
                                        <div class="rounded-md bg-muted/50 px-2 py-1.5">
                                            <p class="text-base font-semibold tabular-nums text-foreground">{{ tree.band.median ?? '—' }}</p>
                                            <p class="text-[11px] text-muted-foreground">{{ t('network.band_median') }}</p>
                                        </div>
                                    </div>
                                </template>
                            </CardContent>
                        </Card>

                        <Card data-testid="rx-worst">
                            <CardHeader>
                                <CardTitle>{{ t('network.worst_title') }}</CardTitle>
                                <CardDescription>{{ t('network.worst_subtitle') }}</CardDescription>
                            </CardHeader>
                            <CardContent class="px-2 sm:px-4">
                                <p v-if="!tree.worst.length" class="py-4 text-center text-sm text-muted-foreground">{{ t('network.worst_empty') }}</p>
                                <ol v-else class="divide-y divide-border">
                                    <li v-for="(onu, i) in tree.worst" :key="`${onu.slot}-${onu.port}-${onu.onu_id}`">
                                        <Link :href="onuHref(onu)" class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-accent">
                                            <span class="w-5 shrink-0 text-right text-xs tabular-nums text-muted-foreground">{{ i + 1 }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block truncate text-sm text-foreground">{{ onu.name || onu.serial_number || `ONU ${onu.onu_id}` }}</span>
                                                <span class="block truncate text-[11px] text-muted-foreground">PON {{ onu.slot }}/{{ onu.port }} · #{{ onu.onu_id }}<template v-if="onu.odp_name"> · {{ onu.odp_name }}</template></span>
                                            </span>
                                            <Badge :variant="stale ? 'muted' : onu.level === 'critical' ? 'destructive' : 'warning'" class="tabular-nums">{{ rxText(onu) }} dBm</Badge>
                                        </Link>
                                    </li>
                                </ol>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </template>
        </div>
    </AuthenticatedLayout>
</template>
