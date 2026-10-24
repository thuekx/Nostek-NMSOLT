<script setup>
import { SwitchRoot, SwitchThumb } from 'reka-ui';
import { cn } from '@/lib/utils';

// Saklar on/off (Reka UI, role="switch"). Kenop bg-paper: putih di kedua tema (bg-white berbalik di tema terang). v-model = boolean. `tone="warning"` untuk saklar
// yang menyalakan sesuatu berisiko (mis. aksi tulis perangkat): warna nyala = amber.
defineOptions({ inheritAttrs: false });
const props = defineProps({
    class: { type: [String, Array, Object], default: undefined },
    tone: { type: String, default: 'primary' },
});
const model = defineModel({ type: Boolean, default: false });
</script>

<template>
    <SwitchRoot
        v-model="model"
        data-slot="switch"
        v-bind="$attrs"
        :class="cn(
            'peer inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border border-transparent shadow-xs outline-none transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 data-[state=unchecked]:bg-input',
            tone === 'warning' ? 'data-[state=checked]:bg-warning' : 'data-[state=checked]:bg-primary',
            props.class,
        )"
    >
        <SwitchThumb
            data-slot="switch-thumb"
            class="pointer-events-none block size-5 rounded-full bg-paper shadow-sm ring-0 transition-transform data-[state=checked]:translate-x-5 data-[state=unchecked]:translate-x-0.5 motion-reduce:transition-none"
        />
    </SwitchRoot>
</template>
