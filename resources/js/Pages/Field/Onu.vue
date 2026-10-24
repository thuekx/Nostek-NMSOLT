<script setup>
// UI-C1 "Konsol Lapangan": satu ONU untuk teknisi di lokasi. Angka besar, tombol besar,
// arah ke pelanggan. Hanya baca: aksi tulis tetap di halaman lengkap (gerbang device writes).
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { ArrowLeft, ExternalLink, MapPin, Navigation, Phone, Waypoints } from '@lucide/vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import DataNotCurrent from '@/Components/Shell/DataNotCurrent.vue';
import RxGauge from '@/Components/Field/RxGauge.vue';
import { Badge } from '@/Components/ui/badge';
import { alarmTypeLabel } from '@/lib/alarm';
import { formatRelative } from '@/lib/datetime';
import { fiberHex, levelText } from '@/lib/fiber';
import { isFresh } from '@/lib/freshness';
import { lastDownCauseLabel } from '@/lib/onu';

const props = defineProps({
    olt: { type: Object, required: true },
    onu: { type: Object, required: true },
    pin: { type: Object, default: null },
    alarms: { type: Array, default: () => [] },
});

const { t } = useI18n({ useScope: 'global' });
const stale = computed(() => !isFresh(props.olt.freshness));

const statusClass = computed(() => {
    if (stale.value) return 'bg-muted text-muted-foreground';
    return props.onu.online ? 'bg-success/15 text-success' : 'bg-destructive/15 text-destructive';
});

const statusText = computed(() => {
    if (stale.value) return t('field.status_unknown');
    return props.onu.online ? t('field.status_online') : t('field.status_offline');
});

const rx = computed(() => (props.onu.rx_power_dbm === null ? '—' : props.onu.rx_power_dbm.toFixed(1)));

const fullHref = computed(() =>
    `${route(props.onu.port_route ?? 'smartolt.port-onus', [props.olt.id, props.onu.slot, props.onu.port])}?focus=${props.onu.onu_id}`);
const mapHref = computed(() =>
    route('map.index', { focus_olt: props.olt.id, focus_slot: props.onu.slot, focus_port: props.onu.port, focus_onu: props.onu.onu_id }));
// Arah dari posisi teknisi ke pelanggan (aplikasi peta di HP). Koordinat dari pin peta.
const directionsHref = computed(() =>
    props.pin ? `https://www.google.com/maps/dir/?api=1&destination=${props.pin.latitude},${props.pin.longitude}` : null);
</script>

<template>
    <Head :title="onu.name || onu.serial_number || t('field.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl space-y-5 px-4 py-6 sm:px-6">
            <Link :href="route('field.index')" class="inline-flex min-h-11 items-center gap-2 text-base text-muted-foreground">
                <ArrowLeft class="h-5 w-5" aria-hidden="true" /> {{ t('field.back') }}
            </Link>

            <DataNotCurrent :freshness="olt.freshness" />

            <section class="rounded-2xl border border-border bg-card p-5" data-testid="field-onu-card">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h1 class="break-words text-2xl font-semibold leading-tight text-foreground">{{ onu.name || t('field.no_name') }}</h1>
                        <p class="mt-1 break-all font-mono text-base text-muted-foreground">{{ onu.serial_number ?? '—' }}</p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-base font-semibold" :class="statusClass" data-testid="field-onu-status">{{ statusText }}</span>
                </div>

                <p v-if="!onu.online && onu.last_down_cause" class="mt-3 rounded-lg bg-muted px-3 py-2 text-base text-foreground">
                    {{ t('field.down_cause') }}: <strong>{{ lastDownCauseLabel(onu.last_down_cause) }}</strong>
                </p>

                <div class="mt-5">
                    <p class="text-sm text-muted-foreground">{{ t('field.rx_now') }}</p>
                    <p class="text-5xl font-semibold tabular-nums tracking-tight" :class="onu.online ? levelText(onu.level, stale) : 'text-muted-foreground'" data-testid="field-onu-rx">
                        {{ rx }}<span class="ml-1 text-xl font-normal text-muted-foreground">dBm</span>
                    </p>
                    <p v-if="onu.online && onu.rx_power_dbm !== null" class="mt-1 text-base" :class="levelText(onu.level, stale)">{{ t(`network.legend_${onu.level}`) }}</p>
                    <div class="mt-8">
                        <RxGauge :dbm="onu.online ? onu.rx_power_dbm : null" :stale="stale" />
                    </div>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-3 text-base">
                    <div>
                        <dt class="text-sm text-muted-foreground">OLT</dt>
                        <dd class="truncate text-foreground">{{ olt.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted-foreground">PON · ONU</dt>
                        <dd class="flex items-center gap-2 text-foreground">
                            <span class="h-3 w-3 rounded-full ring-1 ring-border" :style="{ backgroundColor: fiberHex(onu.fiber_color) }" :title="t('network.fiber_color', { color: t(`network.colors.${onu.fiber_color}`) })" />
                            {{ onu.slot }}/{{ onu.port }} · #{{ onu.onu_id }}
                        </dd>
                    </div>
                    <div class="col-span-2">
                        <dt class="text-sm text-muted-foreground">ODP</dt>
                        <dd class="flex items-center gap-2 text-foreground">
                            <Waypoints class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            {{ onu.odp_name ?? t('field.no_odp') }}
                        </dd>
                    </div>
                    <div v-if="pin?.address" class="col-span-2">
                        <dt class="text-sm text-muted-foreground">{{ t('field.address') }}</dt>
                        <dd class="text-foreground">{{ pin.address }}</dd>
                    </div>
                </dl>
            </section>

            <div class="grid grid-cols-2 gap-3">
                <a v-if="directionsHref" :href="directionsHref" target="_blank" rel="noopener noreferrer" class="flex min-h-14 items-center justify-center gap-2 rounded-xl bg-primary px-4 text-base font-semibold text-primary-foreground" data-testid="field-directions">
                    <Navigation class="h-5 w-5" aria-hidden="true" /> {{ t('field.directions') }}
                </a>
                <a v-if="pin?.phone" :href="`tel:${pin.phone}`" class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-base font-semibold text-foreground">
                    <Phone class="h-5 w-5" aria-hidden="true" /> {{ t('field.call') }}
                </a>
                <Link :href="mapHref" class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-base font-semibold text-foreground">
                    <MapPin class="h-5 w-5" aria-hidden="true" /> {{ t('field.on_map') }}
                </Link>
                <Link :href="fullHref" class="flex min-h-14 items-center justify-center gap-2 rounded-xl border border-border bg-card px-4 text-base font-semibold text-foreground">
                    <ExternalLink class="h-5 w-5" aria-hidden="true" /> {{ t('field.full_detail') }}
                </Link>
            </div>

            <section data-testid="field-onu-alarms">
                <h2 class="mb-2 text-lg font-semibold text-foreground">{{ t('field.onu_alarms') }}</h2>
                <p v-if="!alarms.length" class="text-base text-muted-foreground">{{ t('field.onu_alarms_empty') }}</p>
                <ul v-else class="space-y-2">
                    <li v-for="a in alarms" :key="a.id" class="flex items-center gap-3 rounded-xl border border-border bg-card px-4 py-3">
                        <span class="min-w-0 flex-1">
                            <span class="block text-base font-semibold text-foreground">{{ alarmTypeLabel(t, a.type) }}</span>
                            <span class="block text-sm text-muted-foreground">
                                {{ a.port_wide ? t('field.port_wide') : t('field.this_onu') }} · {{ formatRelative(a.first_seen_at) }}
                            </span>
                        </span>
                        <Badge :variant="a.severity === 'critical' ? 'destructive' : ['major', 'minor'].includes(a.severity) ? 'warning' : 'muted'">{{ a.severity }}</Badge>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
