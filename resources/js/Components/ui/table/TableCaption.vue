<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <caption data-slot="table-caption" :class="cn('mt-4 text-sm text-muted-foreground', props.class)">
        <slot />
    </caption>
</template>
