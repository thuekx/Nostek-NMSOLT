<script setup>
import { TabsRoot } from 'reka-ui';
import { cn } from '@/lib/utils';

// Props Reka (mis. value, modelValue) diteruskan apa adanya lewat $attrs.
defineOptions({ inheritAttrs: false });
const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <TabsRoot v-bind="$attrs" :class="cn('flex flex-col gap-2', props.class)">
        <slot />
    </TabsRoot>
</template>
