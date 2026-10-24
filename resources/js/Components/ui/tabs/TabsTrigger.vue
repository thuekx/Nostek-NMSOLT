<script setup>
import { TabsTrigger } from 'reka-ui';
import { cn } from '@/lib/utils';

// Props Reka (mis. value, modelValue) diteruskan apa adanya lewat $attrs.
defineOptions({ inheritAttrs: false });
const props = defineProps({ class: { type: [String, Array, Object], default: undefined } });

const base = "inline-flex h-full flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-md border border-transparent px-2 py-1 text-sm font-medium text-muted-foreground transition-[color,box-shadow] focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:opacity-50 data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs [&_svg:not([class*='size-'])]:size-4";
</script>

<template>
    <TabsTrigger v-bind="$attrs" :class="cn(base, props.class)">
        <slot />
    </TabsTrigger>
</template>
