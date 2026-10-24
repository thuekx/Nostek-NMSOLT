<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <tbody data-slot="table-body" :class="cn('[&_tr:last-child]:border-0', props.class)">
        <slot />
    </tbody>
</template>
