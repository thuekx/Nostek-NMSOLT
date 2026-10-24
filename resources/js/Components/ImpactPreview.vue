<script setup>
// UI-F6: "siapa yang kena" sebelum aksi tulis. Dimuat saat dialog konfirmasi terbuka; kalau
// gagal, dialog tetap bisa dikonfirmasi (pratinjau membantu, bukan gerbang).
import axios from 'axios';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Users } from '@lucide/vue';

const props = defineProps({
    // { oltId, slot, port, onuIds? }
    target: { type: Object, required: true },
});

const { t } = useI18n({ useScope: 'global' });
const state = ref('loading');
const data = ref(null);

onMounted(async () => {
    try {
        const { target } = props;
        const params = target.onuIds?.length ? { onu_ids: target.onuIds } : {};
        const res = await axios.get(route('impact.port', [target.oltId, target.slot, target.port]), { params });
        data.value = res.data;
        state.value = 'ready';
    } catch {
        state.value = 'error';
    }
});
</script>

<template>
    <div class="mt-4 rounded-lg border border-border bg-muted/40 p-3 text-sm" data-testid="impact-preview" :data-state="state">
        <p class="mb-1.5 flex items-center gap-2 font-medium text-foreground">
            <Users class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
            {{ t('impact.title') }}
        </p>
        <p v-if="state === 'loading'" class="text-muted-foreground">{{ t('impact.loading') }}</p>
        <p v-else-if="state === 'error'" class="text-muted-foreground">{{ t('impact.error') }}</p>
        <template v-else>
            <p class="text-foreground" data-testid="impact-summary">
                {{ t('impact.summary', { onus: data.onus, online: data.online }) }}
                <span v-if="data.active_alarms" class="text-warning">· {{ t('impact.alarms', { n: data.active_alarms }) }}</span>
            </p>
            <p v-if="data.odps.length" class="mt-1 text-muted-foreground">
                ODP: {{ data.odps.map((o) => `${o.name ?? t('impact.no_odp')} (${o.onus})`).join(', ') }}
            </p>
            <p v-if="data.customers.length" class="mt-1 text-muted-foreground">
                {{ t('impact.customers') }}: {{ data.customers.join(', ') }}<template v-if="data.more_customers"> {{ t('impact.more', { n: data.more_customers }) }}</template>
            </p>
            <p v-if="data.stale" class="mt-1 text-warning">{{ t('impact.stale') }}</p>
        </template>
    </div>
</template>
