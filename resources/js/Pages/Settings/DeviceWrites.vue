<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { formatDateTime } from '@/lib/datetime';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, History, Lock, Server, ShieldAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

// WRITE-UI-1: admin switches for device writes. The server (DeviceWritePolicy) decides on
// every request; this page only edits the switches. The installation mode (env) is the upper
// limit: anything other than "admin" shows the page read-only.
const { t } = useI18n({ useScope: 'global' });

const props = defineProps({
    mode: { type: String, required: true },
    effective: { type: Object, default: () => ({ global: false, channels: {} }) },
    global: { type: Object, default: null },
    channels: { type: Object, default: () => ({}) },
    olts: { type: Array, default: () => [] },
    durations: { type: Array, default: () => [] },
    history: { type: Array, default: () => [] },
});

const editable = computed(() => props.mode === 'admin');
const channelKeys = ['olt', 'acs'];

const form = useForm({
    enabled: Boolean(props.effective.global),
    channels: {
        olt: Boolean(props.channels.olt?.enabled),
        acs: Boolean(props.channels.acs?.enabled),
    },
    duration: 60,
    reason: '',
});

const submit = () => {
    form.transform((data) => ({ ...data, duration: data.enabled ? data.duration : null }))
        .put(route('settings.device-writes.update'), {
            preserveScroll: true,
            onSuccess: () => form.reset('reason'),
        });
};

const oltReason = ref('');
const oltBusy = ref(null);
const oltErrors = ref({});
const toggleOlt = (olt) => {
    oltBusy.value = olt.id;
    useForm({ blocked: !olt.blocked, reason: oltReason.value }).put(route('settings.device-writes.olt', { olt: olt.id }), {
        preserveScroll: true,
        onError: (errors) => { oltErrors.value = errors; },
        onSuccess: () => { oltErrors.value = {}; oltReason.value = ''; },
        onFinish: () => { oltBusy.value = null; },
    });
};

const globalState = computed(() => {
    if (props.mode === 'on') return t('devicewrites.state_installation_on');
    if (props.mode === 'off') return t('devicewrites.state_installation_off');
    if (!props.effective.global) {
        return props.global?.enabled && props.global?.expires_at ? t('devicewrites.state_expired') : t('devicewrites.state_off');
    }
    return props.global?.expires_at
        ? t('devicewrites.state_on_until', { time: formatDateTime(props.global.expires_at) })
        : t('devicewrites.state_on');
});

const historyLine = (entry) => {
    const p = entry.properties ?? {};
    if (p.scope === 'olt') {
        return t(p.after?.blocked ? 'devicewrites.hist_olt_blocked' : 'devicewrites.hist_olt_unblocked', { olt: p.olt_name ?? `#${p.olt_id}` });
    }
    const after = p.after ?? {};
    const on = channelKeys.filter((c) => after.channels?.[c]).map((c) => t(`devicewrites.channel_${c}`));
    return after.global
        ? t('devicewrites.hist_global_on', { channels: on.join(', ') || '—', minutes: p.duration_minutes ?? '∞' })
        : t('devicewrites.hist_global_off');
};
</script>

<template>
    <Head :title="$t('devicewrites.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('settings.edit')" class="text-slate-400 hover:text-white" :aria-label="$t('devicewrites.back')">
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <h2 class="text-lg font-semibold leading-tight sm:text-xl text-white">{{ $t('devicewrites.title') }}</h2>
            </div>
        </template>

        <div class="min-h-[60vh] pt-5 pb-16 sm:pt-8">
            <div class="w-full space-y-6 px-4 sm:px-6 lg:px-8">
                <div
                    v-if="!editable"
                    class="flex items-start gap-3 rounded-lg border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm text-sky-100"
                    data-testid="device-writes-mode-locked"
                >
                    <Lock class="mt-0.5 h-4 w-4 flex-shrink-0" />
                    <span>{{ mode === 'on' ? $t('devicewrites.mode_on_hint') : $t('devicewrites.mode_off_hint') }}</span>
                </div>

                <!-- Global switch + channels -->
                <form
                    class="overflow-hidden rounded-lg border border-white/10 bg-slate-900/40 shadow-lg shadow-black/30 backdrop-blur-xl"
                    @submit.prevent="submit"
                >
                    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-4 sm:px-6">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-amber-500/20 ring-1 ring-amber-500/30">
                            <ShieldAlert class="h-5 w-5 text-amber-300" />
                        </div>
                        <div class="min-w-0">
                            <h3 class="text-base font-semibold text-white">{{ $t('devicewrites.global_title') }}</h3>
                            <p class="text-sm text-slate-400" data-testid="device-writes-state">{{ globalState }}</p>
                        </div>
                    </div>

                    <div class="space-y-5 px-5 py-5 sm:px-6">
                        <p class="text-sm text-slate-400">{{ $t('devicewrites.global_sub') }}</p>

                        <label class="flex items-start gap-3">
                            <Checkbox v-model:checked="form.enabled" class="mt-0.5 h-5 w-5 shrink-0" :disabled="!editable" />
                            <span>
                                <span class="block text-sm font-medium text-white">{{ $t('devicewrites.global_label') }}</span>
                                <span class="block text-xs text-slate-400">{{ $t('devicewrites.global_hint') }}</span>
                            </span>
                        </label>

                        <div v-if="form.enabled">
                            <InputLabel for="dw_duration" :value="$t('devicewrites.duration')" />
                            <select
                                id="dw_duration"
                                v-model="form.duration"
                                :disabled="!editable"
                                class="mt-1 block min-h-11 w-full rounded-md border border-white/10 bg-slate-900/60 py-2.5 px-3 text-sm text-white shadow-xs focus:border-cyan-500 focus:ring-cyan-500 sm:max-w-xs"
                            >
                                <option v-for="m in durations" :key="m" :value="m">{{ $t('devicewrites.duration_minutes', { n: m }) }}</option>
                                <option :value="null">{{ $t('devicewrites.duration_none') }}</option>
                            </select>
                            <InputError :message="form.errors.duration" class="mt-2" />
                        </div>

                        <fieldset class="space-y-3">
                            <legend class="text-sm font-medium text-white">{{ $t('devicewrites.channels_title') }}</legend>
                            <label v-for="c in channelKeys" :key="c" class="flex items-start gap-3">
                                <Checkbox v-model:checked="form.channels[c]" class="mt-0.5 h-5 w-5 shrink-0" :disabled="!editable" />
                                <span>
                                    <span class="block text-sm text-white">
                                        {{ $t(`devicewrites.channel_${c}`) }}
                                        <span
                                            class="ml-2 rounded-full px-2 py-0.5 text-xs"
                                            :class="effective.channels?.[c] ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700/60 text-slate-300'"
                                        >{{ effective.channels?.[c] ? $t('devicewrites.on') : $t('devicewrites.off') }}</span>
                                    </span>
                                    <span class="block text-xs text-slate-400">{{ $t(`devicewrites.channel_${c}_hint`) }}</span>
                                </span>
                            </label>
                        </fieldset>

                        <div>
                            <InputLabel for="dw_reason" :value="$t('devicewrites.reason')" />
                            <TextInput id="dw_reason" v-model="form.reason" class="mt-1 block w-full" :disabled="!editable" maxlength="500" />
                            <InputError :message="form.errors.reason" class="mt-2" />
                            <p class="mt-1 text-xs text-slate-400">{{ $t('devicewrites.reason_hint') }}</p>
                        </div>

                        <div class="flex justify-end">
                            <PrimaryButton :disabled="!editable || form.processing || form.reason.trim().length < 5" data-testid="device-writes-save">
                                {{ $t('devicewrites.save') }}
                            </PrimaryButton>
                        </div>
                    </div>
                </form>

                <!-- Per-OLT block -->
                <section class="overflow-hidden rounded-lg border border-white/10 bg-slate-900/40 shadow-lg shadow-black/30 backdrop-blur-xl">
                    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-4 sm:px-6">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-cyan-500/20 ring-1 ring-cyan-500/30">
                            <Server class="h-5 w-5 text-cyan-300" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-white">{{ $t('devicewrites.olt_title') }}</h3>
                            <p class="text-sm text-slate-400">{{ $t('devicewrites.olt_sub') }}</p>
                        </div>
                    </div>
                    <div class="space-y-4 px-5 py-5 sm:px-6">
                        <div>
                            <InputLabel for="dw_olt_reason" :value="$t('devicewrites.olt_reason')" />
                            <TextInput id="dw_olt_reason" v-model="oltReason" class="mt-1 block w-full" :disabled="!editable" maxlength="500" />
                            <InputError :message="oltErrors.reason" class="mt-2" />
                        </div>
                        <p v-if="olts.length === 0" class="text-sm text-slate-500">{{ $t('devicewrites.olt_empty') }}</p>
                        <ul v-else class="divide-y divide-white/5">
                            <li v-for="olt in olts" :key="olt.id" class="flex flex-wrap items-center gap-3 py-3">
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-white">{{ olt.name }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ olt.ip }}
                                        <template v-if="olt.blocked && olt.setting?.reason"> · {{ olt.setting.reason }}</template>
                                    </p>
                                </div>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs"
                                    :class="olt.blocked ? 'bg-red-500/15 text-red-300' : 'bg-slate-700/60 text-slate-300'"
                                >{{ olt.blocked ? $t('devicewrites.olt_blocked') : $t('devicewrites.olt_follows') }}</span>
                                <SecondaryButton
                                    type="button"
                                    :disabled="!editable || oltBusy !== null || oltReason.trim().length < 5"
                                    :data-testid="`device-writes-olt-${olt.id}`"
                                    @click="toggleOlt(olt)"
                                >
                                    {{ olt.blocked ? $t('devicewrites.olt_unblock') : $t('devicewrites.olt_block') }}
                                </SecondaryButton>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- History -->
                <section class="overflow-hidden rounded-lg border border-white/10 bg-slate-900/40 shadow-lg shadow-black/30 backdrop-blur-xl">
                    <div class="flex items-center gap-3 border-b border-white/10 px-5 py-4 sm:px-6">
                        <History class="h-5 w-5 text-slate-300" />
                        <h3 class="text-base font-semibold text-white">{{ $t('devicewrites.history_title') }}</h3>
                    </div>
                    <p v-if="history.length === 0" class="px-5 py-5 text-sm text-slate-500 sm:px-6">{{ $t('devicewrites.history_empty') }}</p>
                    <ul v-else class="divide-y divide-white/5 px-5 sm:px-6">
                        <li v-for="entry in history" :key="entry.id" class="py-3 text-sm">
                            <p class="text-white">{{ historyLine(entry) }}</p>
                            <p class="text-xs text-slate-500">
                                {{ formatDateTime(entry.created_at) }} · {{ entry.user ?? '—' }} · {{ entry.properties?.reason ?? '' }}
                            </p>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
