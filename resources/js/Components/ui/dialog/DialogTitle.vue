<script setup>
import { DialogTitle } from 'reka-ui';
import { cn } from '@/lib/utils';

// Props Reka (mis. value, modelValue) diteruskan apa adanya lewat $attrs.
defineOptions({ inheritAttrs: false });
const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <DialogTitle v-bind="$attrs" :class="cn('text-lg font-semibold leading-none', props.class)">
        <slot />
    </DialogTitle>
</template>
