<script setup>
import { CircleCheck, CircleMinus, FileText, Power, RotateCcw, UploadCloud } from '@lucide/vue';

const emit = defineEmits(['select']);

const actions = [
    { key: 'reboot',  label: 'Reboot',   icon: RotateCcw,   accent: 'nos-circle-cyan' },
    { key: 'reset',   label: 'Reset',    icon: Power,       accent: 'nos-circle-purple' },
    { key: 'upgrade', label: 'Upgrade',  icon: UploadCloud, accent: 'nos-circle-emerald' },
    { key: 'enable',  label: 'Enable',   icon: CircleCheck, accent: 'nos-circle-emerald' },
    { key: 'disable', label: 'Disable',  icon: CircleMinus, accent: 'nos-circle-amber' },
    { key: 'log',     label: 'Log',      icon: FileText,    accent: 'nos-circle-sky' },
];
</script>

<template>
    <div class="nos-glass-panel flex h-full flex-col">
        <div class="nos-glass-header">
            <span class="nos-circle-cyan">
                <Power class="h-5 w-5" />
            </span>
            <h3 class="text-base font-semibold text-white">{{ $t('dashboard.remote_actions') }}</h3>
        </div>
        <div class="grid flex-1 grid-cols-3 gap-2 p-4">
            <button
                v-for="action in actions"
                :key="action.key"
                type="button"
                class="group flex flex-col items-center justify-center gap-2 rounded-xl border border-white/5 bg-slate-900/40 px-2 py-3 text-center transition-all hover:border-cyan-500/30 hover:bg-slate-900/70 hover:shadow-lg hover:shadow-cyan-500/10"
                :title="`${action.label} ONU`"
                @click="emit('select', action.key)"
            >
                <span :class="action.accent" class="!h-10 !w-10 transition-transform group-hover:scale-105">
                    <component :is="action.icon" class="h-4 w-4" />
                </span>
                <span class="text-xs font-semibold text-white">{{ action.label }}</span>
            </button>
        </div>
    </div>
</template>
