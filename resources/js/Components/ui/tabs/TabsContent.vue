<script setup>
import { TabsContent } from 'reka-ui';
import { cn } from '@/lib/utils';

// Props Reka (mis. value, modelValue) diteruskan apa adanya lewat $attrs.
defineOptions({ inheritAttrs: false });
const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });
</script>

<template>
    <TabsContent v-bind="$attrs" :class="cn('flex-1 outline-none', props.class)">
        <slot />
    </TabsContent>
</template>
