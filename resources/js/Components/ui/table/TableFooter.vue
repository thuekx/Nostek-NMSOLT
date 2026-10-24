<script setup>
import { cn } from '@/lib/utils';

const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <tfoot data-slot="table-footer" :class="cn('border-t border-border bg-muted/50 font-medium [&>tr]:last:border-b-0', props.class)">
        <slot />
    </tfoot>
</template>
