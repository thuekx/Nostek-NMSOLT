<script setup>
import { TooltipContent, TooltipPortal } from 'reka-ui';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    class: { type: [String, Array, Object], default: undefined },
    sideOffset: { type: Number, default: 4 },
});
</script>

<template>
    <TooltipPortal>
        <TooltipContent
            data-slot="tooltip-content"
            v-bind="$attrs"
            :side-offset="sideOffset"
            :class="cn('z-50 w-fit rounded-md bg-foreground px-3 py-1.5 text-xs text-background data-[state=closed]:animate-out data-[state=closed]:fade-out-0 animate-in fade-in-0 zoom-in-95 motion-reduce:animate-none', props.class)"
        >
            <slot />
        </TooltipContent>
    </TooltipPortal>
</template>
