<script setup>
// UI-F1b: site organisasi (POP/datacenter/area) dan penempatan OLT ke site. Site adalah
// penyaring tampilan dan pengelompokan; batas keamanan tetap organisasi.
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { Building2, MapPin, Pencil, Plus, Server, Trash2 } from '@lucide/vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { useConfirm } from '@/Composables/useConfirm';

const props = defineProps({
    organization: { type: Object, required: true },
    sites: { type: Array, default: () => [] },
    olts: { type: Array, default: () => [] },
    siteKinds: { type: Array, default: () => [] },
});

const { t } = useI18n({ useScope: 'global' });
const { confirmState, confirm, handleConfirm, handleCancel } = useConfirm();

const blank = () => ({ key: '', name: '', kind: 'pop', region: '', timezone: 'Asia/Jakarta' });
const editing = ref(null); // null = tutup, 'new' = tambah, id = ubah
const form = useForm(blank());

const startNew = () => {
    form.defaults(blank());
    form.reset();
    form.clearErrors();
    editing.value = 'new';
};

const startEdit = (site) => {
    form.defaults({ key: site.key, name: site.name, kind: site.kind, region: site.region ?? '', timezone: site.timezone });
    form.reset();
    form.clearErrors();
    editing.value = site.id;
};

const cancel = () => {
    editing.value = null;
};

// Kunci site = site_key Control Center: diturunkan dari nama sampai operator mengubahnya sendiri.
const keyTouched = ref(false);
const onNameInput = () => {
    if (editing.value === 'new' && !keyTouched.value) {
        form.key = form.name.toLowerCase().normalize('NFKD').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 64);
    }
};

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { editing.value = null; keyTouched.value = false; } };
    if (editing.value === 'new') {
        form.post(route('organization.sites.store'), options);
    } else {
        form.put(route('organization.sites.update', editing.value), options);
    }
};

const destroy = async (site) => {
    const ok = await confirm({
        title: t('organization.delete_title', { name: site.name }),
        message: site.olt_count
            ? t('organization.delete_message_olts', { n: site.olt_count })
            : t('organization.delete_message'),
        confirmLabel: t('organization.delete'),
        cancelLabel: t('organization.cancel'),
        variant: 'danger',
    });
    if (ok) {
        router.delete(route('organization.sites.destroy', site.id), { preserveScroll: true });
    }
};

const oltBusy = ref(null);
const assign = (olt, siteId) => {
    oltBusy.value = olt.id;
    router.put(route('organization.olts.site', olt.id), { site_id: siteId || null }, {
        preserveScroll: true,
        onFinish: () => { oltBusy.value = null; },
    });
};

const unassigned = computed(() => props.olts.filter((o) => !o.site_id).length);
</script>

<template>
    <Head :title="t('organization.title')" />

    <AuthenticatedLayout>
        <div class="space-y-5 px-4 py-6 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="flex items-center gap-2 text-xl font-semibold tracking-tight text-foreground">
                        <Building2 class="h-5 w-5 text-muted-foreground" aria-hidden="true" />
                        <span data-testid="organization-name">{{ organization.name }}</span>
                    </h1>
                    <p class="text-sm text-muted-foreground">{{ t('organization.subtitle') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <Badge variant="outline">{{ t(`organization.kind_${organization.kind}`) }}</Badge>
                    <Badge variant="outline" class="font-mono">{{ organization.slug }}</Badge>
                    <Badge v-if="organization.cc_linked" variant="secondary">{{ t('organization.cc_linked') }}</Badge>
                </div>
            </div>

            <div class="grid gap-5 lg:grid-cols-12">
                <!-- Sites -->
                <Card class="min-w-0 lg:col-span-7" data-testid="organization-sites">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <MapPin class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            {{ t('organization.sites_title') }}
                        </CardTitle>
                        <CardDescription>{{ t('organization.sites_hint') }}</CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <p v-if="!sites.length && editing !== 'new'" class="py-4 text-center text-sm text-muted-foreground" data-testid="organization-no-sites">
                            {{ t('organization.no_sites') }}
                        </p>

                        <div
                            v-for="site in sites"
                            :key="site.id"
                            class="flex items-center gap-3 rounded-lg border border-border px-3 py-2.5"
                            :data-testid="`site-${site.key}`"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-foreground">{{ site.name }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    <span class="font-mono">{{ site.key }}</span>
                                    · {{ t(`organization.site_kind_${site.kind}`) }}
                                    <template v-if="site.region"> · {{ site.region }}</template>
                                    · {{ t('organization.olt_count', { n: site.olt_count }) }}
                                </p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                                :aria-label="t('organization.edit')"
                                @click="startEdit(site)"
                            >
                                <Pencil class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="inline-flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                                :aria-label="t('organization.delete')"
                                :data-testid="`site-delete-${site.key}`"
                                @click="destroy(site)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>

                        <form
                            v-if="editing"
                            class="space-y-3 rounded-lg border border-border bg-muted/40 p-3"
                            data-testid="site-form"
                            @submit.prevent="submit"
                        >
                            <div class="grid gap-3 sm:grid-cols-2">
                                <label class="block text-sm">
                                    <span class="text-muted-foreground">{{ t('organization.field_name') }}</span>
                                    <input v-model="form.name" class="nos-filter-control mt-1 w-full" maxlength="255" required data-testid="site-name" @input="onNameInput" />
                                    <InputError :message="form.errors.name" class="mt-1" />
                                </label>
                                <label class="block text-sm">
                                    <span class="text-muted-foreground">{{ t('organization.field_key') }}</span>
                                    <input v-model="form.key" class="nos-filter-control mt-1 w-full font-mono" maxlength="64" required data-testid="site-key" @input="keyTouched = true" />
                                    <InputError :message="form.errors.key" class="mt-1" />
                                </label>
                                <label class="block text-sm">
                                    <span class="text-muted-foreground">{{ t('organization.field_kind') }}</span>
                                    <select v-model="form.kind" class="nos-filter-control mt-1 w-full">
                                        <option v-for="k in siteKinds" :key="k" :value="k">{{ t(`organization.site_kind_${k}`) }}</option>
                                    </select>
                                    <InputError :message="form.errors.kind" class="mt-1" />
                                </label>
                                <label class="block text-sm">
                                    <span class="text-muted-foreground">{{ t('organization.field_region') }}</span>
                                    <input v-model="form.region" class="nos-filter-control mt-1 w-full" maxlength="100" />
                                    <InputError :message="form.errors.region" class="mt-1" />
                                </label>
                                <label class="block text-sm sm:col-span-2">
                                    <span class="text-muted-foreground">{{ t('organization.field_timezone') }}</span>
                                    <input v-model="form.timezone" class="nos-filter-control mt-1 w-full" maxlength="64" required />
                                    <InputError :message="form.errors.timezone" class="mt-1" />
                                </label>
                            </div>
                            <p class="text-xs text-muted-foreground">{{ t('organization.key_hint') }}</p>
                            <div class="flex justify-end gap-2">
                                <SecondaryButton type="button" size="sm" @click="cancel">{{ t('organization.cancel') }}</SecondaryButton>
                                <PrimaryButton size="sm" :disabled="form.processing" data-testid="site-save">{{ t('organization.save') }}</PrimaryButton>
                            </div>
                        </form>

                        <div v-if="!editing" class="flex justify-end">
                            <PrimaryButton type="button" size="sm" data-testid="site-add" @click="startNew">
                                <Plus class="h-4 w-4" aria-hidden="true" />
                                {{ t('organization.add_site') }}
                            </PrimaryButton>
                        </div>
                    </CardContent>
                </Card>

                <!-- OLT -> site -->
                <Card class="min-w-0 lg:col-span-5" data-testid="organization-olts">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <Server class="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            {{ t('organization.olts_title') }}
                        </CardTitle>
                        <CardDescription>
                            {{ unassigned ? t('organization.olts_unassigned', { n: unassigned }) : t('organization.olts_hint') }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-2">
                        <p v-if="!olts.length" class="py-4 text-center text-sm text-muted-foreground">{{ t('organization.no_olts') }}</p>
                        <label
                            v-for="olt in olts"
                            :key="olt.id"
                            class="flex items-center gap-3 rounded-lg border border-border px-3 py-2"
                            :data-testid="`olt-site-${olt.id}`"
                        >
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-foreground">{{ olt.name }}</span>
                                <span class="block truncate text-xs text-muted-foreground">{{ olt.vendor }}</span>
                            </span>
                            <select
                                class="nos-filter-control w-40 shrink-0"
                                :value="olt.site_id ?? ''"
                                :disabled="!sites.length || oltBusy === olt.id"
                                :aria-label="t('organization.olt_site_label', { olt: olt.name })"
                                @change="assign(olt, $event.target.value)"
                            >
                                <option value="">{{ t('organization.no_site') }}</option>
                                <option v-for="s in sites" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </label>
                    </CardContent>
                </Card>
            </div>
        </div>

        <ConfirmModal :state="confirmState" @confirm="handleConfirm" @cancel="handleCancel" />
    </AuthenticatedLayout>
</template>
