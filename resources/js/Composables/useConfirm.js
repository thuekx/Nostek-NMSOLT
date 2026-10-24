import { reactive } from 'vue';

export function useConfirm() {
    const confirmState = reactive({
        show: false,
        title: 'Konfirmasi',
        message: '',
        confirmLabel: 'Konfirmasi',
        cancelLabel: 'Batal',
        variant: 'danger',
        // UI-F6: { oltId, slot, port, onuIds? } → ConfirmModal menampilkan pratinjau dampak.
        impact: null,
        resolve: null,
    });

    const confirm = (options = {}) => {
        confirmState.title = options.title ?? 'Konfirmasi';
        confirmState.message = options.message ?? '';
        confirmState.confirmLabel = options.confirmLabel ?? 'Konfirmasi';
        confirmState.cancelLabel = options.cancelLabel ?? 'Batal';
        confirmState.variant = options.variant ?? 'danger';
        confirmState.impact = options.impact ?? null;
        confirmState.show = true;

        return new Promise((resolve) => {
            confirmState.resolve = resolve;
        });
    };

    const settle = (value) => {
        confirmState.show = false;
        confirmState.resolve?.(value);
        confirmState.resolve = null;
    };

    return {
        confirmState,
        confirm,
        handleConfirm: () => settle(true),
        handleCancel: () => settle(false),
    };
}
