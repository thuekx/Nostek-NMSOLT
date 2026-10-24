<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <th data-slot="table-head" :class="cn('h-10 whitespace-nowrap px-3 text-left align-middle text-xs font-medium text-muted-foreground', props.class)">
        <slot />
    </th>
</template>
