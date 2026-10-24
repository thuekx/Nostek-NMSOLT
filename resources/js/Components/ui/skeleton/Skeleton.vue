<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <div data-slot="skeleton" :class="cn('animate-pulse rounded-md bg-muted motion-reduce:animate-none', props.class)">
        <slot />
    </div>
</template>
