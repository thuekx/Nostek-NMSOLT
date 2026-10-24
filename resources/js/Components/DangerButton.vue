<script setup>
/* Tombol destruktif: datar, token `destructive` (UI-S2). */
import { computed } from 'vue';

const props = defineProps({
    type: { type: String, default: 'submit' },
    size: { type: String, default: 'md' },
});

const sizeClass = computed(() => (props.size === 'sm' ? 'min-h-8 gap-1.5 rounded-md px-3' : 'min-h-11 gap-2 rounded-lg px-4 sm:min-h-9'));
</script>

<template>
    <button
        :type="type"
        class="inline-flex items-center justify-center border border-transparent bg-destructive text-sm font-medium text-destructive-foreground shadow-xs transition-colors duration-150 hover:bg-destructive/90 focus:outline-hidden focus-visible:ring-[3px] focus-visible:ring-destructive/40 disabled:cursor-not-allowed disabled:opacity-50"
        :class="sizeClass"
    >
        <slot />
    </button>
</template>
