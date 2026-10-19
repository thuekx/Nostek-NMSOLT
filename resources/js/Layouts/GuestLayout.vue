<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import LanguageSwitcher from '@/Components/Shell/LanguageSwitcher.vue';
import ThemeToggle from '@/Components/Shell/ThemeToggle.vue';

const page = usePage();
const appName = computed(() => page.props.branding?.name ?? 'Nostek-NMSOLT');
const owner = computed(() => page.props.branding?.owner ?? 'thuekx');
const copyrightYear = computed(() => page.props.branding?.copyright_year ?? '2026');
</script>

<template>
    <div class="relative flex min-h-screen flex-col items-center justify-center bg-slate-950 px-4 py-10">
        <div class="absolute right-4 top-4 flex items-center gap-2 sm:right-6 sm:top-6">
            <ThemeToggle />
            <LanguageSwitcher />
        </div>

        <Link href="/" class="flex flex-col items-center gap-1 text-center">
            <span class="text-xl font-bold tracking-wide text-slate-100">Nostek-NMSOLT</span>
            <span class="text-xs text-slate-400">FTTH / OLT / ONT Network Management</span>
        </Link>

        <div class="mt-6 w-full sm:max-w-md">
            <div class="rounded-2xl border border-white/10 bg-slate-900 px-6 py-6 shadow-xl sm:px-8 sm:py-7">
                <slot />
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-slate-500">
            &copy; {{ copyrightYear }} {{ appName }} &middot; {{ owner }}
        </p>
    </div>
</template>
