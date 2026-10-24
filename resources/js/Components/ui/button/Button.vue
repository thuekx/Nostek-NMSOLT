<script setup>
import { Primitive } from 'reka-ui';
import { cn } from '@/lib/utils';
import { buttonVariants } from '.';

// Satu elemen akar, supaya v-device-write (WRITE-UI-1) bisa dipasang langsung di <Button>.
const props = defineProps({
    variant: { type: String, default: undefined },
    size: { type: String, default: undefined },
    class: { type: [String, Array, Object], default: undefined },
    as: { type: [String, Object], default: 'button' },
    asChild: { type: Boolean, default: false },
});
</script>

<template>
    <Primitive
        data-slot="button"
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <slot />
    </Primitive>
</template>
