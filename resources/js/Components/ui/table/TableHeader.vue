<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <thead data-slot="table-header" :class="cn('[&_tr]:border-b [&_tr]:border-border', props.class)">
        <slot />
    </thead>
</template>
