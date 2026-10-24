<script setup>
// UI-F1b: pemilih "Organisasi ▸ Site" di header. Tampil hanya bila ada pilihan (lebih dari satu
// organisasi atau lebih dari satu site); instalasi satu ISP tanpa site tidak melihatnya.
// Site adalah penyaring halaman ringkasan; pilihan disimpan per user di server.
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Building2, ChevronRight, MapPin } from '@lucide/vue';

defineProps({
    stacked: { type: Boolean, default: false },
});

const { t } = useI18n({ useScope: 'global' });
const page = usePage();

const org = computed(() => page.props.org_context ?? null);
const visible = computed(() => Boolean(org.value?.show_picker));
const multiOrg = computed(() => (org.value?.organizations?.length ?? 0) > 1);

const switchOrg = (event) => {
    router.put(route('organization.switch'), { organization_id: event.target.value });
};

const selectSite = (event) => {
    router.put(route('organization.site'), { site_id: event.target.value || null }, { preserveScroll: true });
};
</script>

<template>
    <div
        v-if="visible"
        class="flex min-w-0 items-center gap-1.5 text-sm"
        :class="stacked ? 'flex-col items-stretch' : ''"
        data-testid="org-site-picker"
    >
        <label v-if="multiOrg" class="flex min-w-0 items-center gap-1.5">
            <Building2 class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span class="sr-only">{{ t('organization.picker_org') }}</span>
            <select
                class="nos-filter-control min-w-0 max-w-48"
                :class="stacked ? 'w-full max-w-none' : ''"
                :value="org.current?.id"
                data-testid="org-picker"
                @change="switchOrg"
            >
                <option v-for="o in org.organizations" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
        </label>
        <span v-else-if="!stacked" class="flex min-w-0 items-center gap-1.5 text-muted-foreground">
            <Building2 class="h-4 w-4 shrink-0" aria-hidden="true" />
            <span class="max-w-40 truncate">{{ org.current?.name }}</span>
        </span>
        <ChevronRight v-if="!stacked && org.sites.length" class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
        <label v-if="org.sites.length" class="flex min-w-0 items-center gap-1.5">
            <MapPin class="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
            <span class="sr-only">{{ t('organization.picker_site') }}</span>
            <select
                class="nos-filter-control min-w-0 max-w-48"
                :class="[stacked ? 'w-full max-w-none' : '', org.site_id ? 'border-primary/60 text-foreground' : '']"
                :value="org.site_id ?? ''"
                data-testid="site-picker"
                @change="selectSite"
            >
                <option value="">{{ t('organization.all_sites') }}</option>
                <option v-for="s in org.sites" :key="s.id" :value="s.id">{{ s.name }}</option>
            </select>
        </label>
    </div>
</template>
