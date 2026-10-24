<script setup>
import { computed } from 'vue';
import { Separator } from 'reka-ui';
import { cn, omitClass } from '@/lib/utils';

const props = defineProps({
    orientation: { type: String, default: 'horizontal' },
    decorative: { type: Boolean, default: true },
    class: { type: [String, Array, Object], default: undefined },
});
const delegated = computed(() => omitClass(props));
</script>

<template>
    <Separator
        data-slot="separator"
        v-bind="delegated"
        :class="cn('shrink-0 bg-border data-[orientation=horizontal]:h-px data-[orientation=horizontal]:w-full data-[orientation=vertical]:h-full data-[orientation=vertical]:w-px', props.class)"
    />
</template>
