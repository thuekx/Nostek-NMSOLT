<script setup>
import { DialogClose, DialogContent, DialogOverlay, DialogPortal } from 'reka-ui';
import { X } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = defineProps({
    class: { type: [String, Array, Object], default: undefined },
    showClose: { type: Boolean, default: true },
});
const { t } = useI18n({ useScope: 'global' });
</script>

<template>
    <DialogPortal>
        <DialogOverlay
            data-slot="dialog-overlay"
            class="fixed inset-0 z-50 bg-black/50 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 motion-reduce:animate-none"
        />
        <DialogContent
            data-slot="dialog-content"
            v-bind="$attrs"
            :class="cn(
                'fixed left-1/2 top-1/2 z-50 grid w-full max-w-[calc(100%-2rem)] -translate-x-1/2 -translate-y-1/2 gap-4 rounded-lg border border-border bg-popover p-6 text-popover-foreground shadow-lg duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 motion-reduce:animate-none sm:max-w-lg',
                props.class,
            )"
        >
            <slot />
            <DialogClose
                v-if="showClose"
                class="absolute right-4 top-4 rounded-xs opacity-70 transition-opacity hover:opacity-100 focus:outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50 [&_svg]:size-4"
            >
                <X />
                <span class="sr-only">{{ t('common.close') }}</span>
            </DialogClose>
        </DialogContent>
    </DialogPortal>
</template>
