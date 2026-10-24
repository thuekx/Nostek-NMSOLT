<script setup>
// UI-C1 "Konsol Lapangan": layar pertama teknisi di HP. Cari ONU, lalu alarm berat yang aktif.
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { BellRing, ChevronRight, Search } from '@lucide/vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Badge } from '@/Components/ui/badge';
import { alarmTypeLabel } from '@/lib/alarm';
import { formatRelative } from '@/lib/datetime';

const props = defineProps({
    q: { type: String, default: '' },
    results: { type: Array, default: () => [] },
    alarms: { type: Array, default: () => [] },
});

const { t } = useI18n({ useScope: 'global' });
const query = ref(props.q);
const searching = ref(false);

const submit = () => {
    searching.value = true;
    router.get(route('field.index'), query.value.trim() ? { q: query.value.trim() } : {}, {
        preserveState: true,
        replace: true,
        onFinish: () => { searching.value = false; },
    });
};

const onuHref = (r) => route('field.onu', [r.olt_id, r.slot, r.port, r.onu_id]);
const alarmHref = (a) =>
    a.onu_id !== null && a.slot !== null ? onuHref(a) : route('network.tree', { olt_id: a.olt_id });
</script>

<template>
    <Head :title="t('field.title')" />

    <AuthenticatedLayout>
        <div class="mx-auto max-w-2xl space-y-6 px-4 py-6 sm:px-6">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-foreground">{{ t('field.title') }}</h1>
                <p class="text-base text-muted-foreground">{{ t('field.subtitle') }}</p>
            </div>

            <form class="flex gap-2" role="search" @submit.prevent="submit">
                <label for="field-q" class="sr-only">{{ t('field.search_label') }}</label>
                <div class="relative flex-1">
                    <Search class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                    <input
                        id="field-q"
                        v-model="query"
                        type="search"
                        inputmode="search"
                        autocomplete="off"
                        autocapitalize="characters"
                        :placeholder="t('field.search_placeholder')"
                        class="h-14 w-full rounded-xl border border-input bg-card pl-12 pr-4 text-lg text-foreground placeholder:text-muted-foreground focus:border-ring focus:outline-none focus:ring-2 focus:ring-ring/40"
                        data-testid="field-search"
                    />
                </div>
                <button type="submit" class="h-14 shrink-0 rounded-xl bg-primary px-5 text-base font-semibold text-primary-foreground disabled:opacity-60" :disabled="searching">
                    {{ t('field.search_btn') }}
                </button>
            </form>

            <section v-if="q" aria-live="polite" data-testid="field-results">
                <p v-if="q.length < 3" class="text-base text-muted-foreground">{{ t('field.too_short') }}</p>
                <p v-else-if="!results.length" class="text-base text-muted-foreground">{{ t('field.no_results', { q }) }}</p>
                <ul v-else class="divide-y divide-border overflow-hidden rounded-xl border border-border bg-card">
                    <li v-for="r in results" :key="`${r.olt_id}-${r.slot}-${r.port}-${r.onu_id}`">
                        <Link :href="onuHref(r)" class="flex min-h-16 items-center gap-3 px-4 py-3 active:bg-accent">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-mono text-base font-semibold text-foreground">{{ r.label }}</span>
                                <span class="block truncate text-sm text-muted-foreground">{{ r.sublabel }}</span>
                            </span>
                            <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                        </Link>
                    </li>
                </ul>
            </section>

            <section data-testid="field-alarms">
                <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold text-foreground">
                    <BellRing class="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                    {{ t('field.alarms_title') }}
                    <span class="text-base font-normal text-muted-foreground">({{ alarms.length }})</span>
                </h2>
                <p v-if="!alarms.length" class="rounded-xl border border-border bg-card px-4 py-6 text-center text-base text-muted-foreground">
                    {{ t('field.alarms_empty') }}
                </p>
                <ul v-else class="space-y-2">
                    <li v-for="a in alarms" :key="a.id">
                        <Link
                            :href="alarmHref(a)"
                            class="flex min-h-16 items-center gap-3 rounded-xl border bg-card px-4 py-3 active:bg-accent"
                            :class="a.severity === 'critical' ? 'border-destructive/50' : 'border-warning/50'"
                        >
                            <span class="h-10 w-1.5 shrink-0 rounded-full" :class="a.severity === 'critical' ? 'bg-destructive' : 'bg-warning'" />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-2">
                                    <span class="truncate text-base font-semibold text-foreground">{{ alarmTypeLabel(t, a.type) }}</span>
                                    <Badge :variant="a.severity === 'critical' ? 'destructive' : 'warning'">{{ t(`dashboard.severity_${a.severity}`) }}</Badge>
                                </span>
                                <span class="block truncate text-sm text-muted-foreground">
                                    {{ a.olt_name }}<template v-if="a.slot !== null"> · PON {{ a.slot }}/{{ a.port }}</template><template v-if="a.onu_id !== null"> · #{{ a.onu_id }}</template>
                                    · {{ formatRelative(a.first_seen_at) }}
                                </span>
                            </span>
                            <ChevronRight class="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
