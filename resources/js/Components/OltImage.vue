<script setup>
import { computed } from 'vue';
import { Server } from '@lucide/vue';

const props = defineProps({
    olt: { type: Object, required: true },
    model: { type: String, default: '' },
});
const modelLabel = computed(() => props.model || props.olt.model || props.olt.driver || props.olt.vendor || 'OLT');
</script>

<template>
    <div class="nos-surface flex min-h-48 flex-col items-center justify-center gap-3 rounded-lg border border-white/10 bg-slate-900/40 px-6 py-10 text-center">
        <Server class="h-10 w-10 text-cyan-400" aria-hidden="true" />
        <p class="text-base font-semibold text-slate-200">{{ olt.name || 'Perangkat OLT' }}</p>
        <p class="text-sm text-slate-400">{{ modelLabel }}</p>
    </div>
</template>
