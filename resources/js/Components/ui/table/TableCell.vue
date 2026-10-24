<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <td data-slot="table-cell" :class="cn('whitespace-nowrap px-3 py-2 align-middle tabular-nums', props.class)">
        <slot />
    </td>
</template>
