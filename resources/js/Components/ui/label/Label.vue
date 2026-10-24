<script setup>
import { computed } from 'vue';
import { Label } from 'reka-ui';
import { cn, omitClass } from '@/lib/utils';

const props = defineProps({
    for: { type: String, default: undefined },
    class: { type: [String, Array, Object], default: undefined },
});
const delegated = computed(() => omitClass(props));
</script>

<template>
    <Label
        data-slot="label"
        v-bind="delegated"
        :class="cn('flex select-none items-center gap-2 text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-50', props.class)"
    >
        <slot />
    </Label>
</template>
