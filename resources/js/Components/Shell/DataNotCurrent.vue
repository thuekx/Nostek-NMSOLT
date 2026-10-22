<script setup>
// NMS-07-D-2b: one notice for any page that shows cached OLT data. Renders nothing while the
// data is FRESH; otherwise says the values are the last known state (App\Support\Freshness).
import { formatRelative } from '@/lib/datetime';
import { isFresh } from '@/lib/freshness';
import { computed } from 'vue';

const props = defineProps({
    freshness: { type: Object, default: null },
});

const state = computed(() => props.freshness?.state ?? 'unknown');
</script>

<template>
    <div
        v-if="!isFresh(freshness)"
        data-testid="data-not-current"
        :data-state="state"
        class="rounded-lg border border-amber-400/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200"
    >
        {{ $t('onumonitor.data_not_current', {
            state: $t(`dashboard.status_${state}`),
            age: freshness?.at ? formatRelative(freshness.at) : '—',
        }) }}
    </div>
</template>
