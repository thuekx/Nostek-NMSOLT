<script setup>
import { DialogDescription } from 'reka-ui';
import { cn } from '@/lib/utils';

// Props Reka (mis. value, modelValue) diteruskan apa adanya lewat $attrs.
defineOptions({ inheritAttrs: false });
const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <DialogDescription v-bind="$attrs" :class="cn('text-sm text-muted-foreground', props.class)">
        <slot />
    </DialogDescription>
</template>
