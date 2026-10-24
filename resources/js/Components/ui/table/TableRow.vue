<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <tr data-slot="table-row" :class="cn('border-b border-border transition-colors hover:bg-muted/50 data-[state=selected]:bg-muted', props.class)">
        <slot />
    </tr>
</template>
