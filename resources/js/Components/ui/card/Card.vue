<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <div data-slot="card" :class="cn('flex flex-col gap-4 rounded-card border border-border bg-card py-5 text-card-foreground shadow-xs', props.class)">
        <slot />
    </div>
</template>
