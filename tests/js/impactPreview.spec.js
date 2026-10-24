import { describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import axios from 'axios';
import ImpactPreview from '@/Components/ImpactPreview.vue';
import { useConfirm } from '@/Composables/useConfirm';

vi.mock('axios', () => ({ default: { get: vi.fn() } }));

const i18n = createI18n({ legacy: false, locale: 'en', missingWarn: false, fallbackWarn: false, messages: { en: {} } });
// Ziggy's route() is a global in the app.
globalThis.route = (name, params) => `/${name.replace('.', '/')}/${params.join('/')}`;

const target = { oltId: 3, slot: 1, port: 2, onuIds: [5] };

describe('ImpactPreview (UI-F6)', () => {
    it('asks the impact endpoint for the selected ONUs and shows the result', async () => {
        axios.get.mockResolvedValueOnce({ data: { onus: 1, online: 1, customers: ['Budi'], more_customers: 0, odps: [{ name: 'ODP-A', onus: 1 }], active_alarms: 0, stale: false } });
        const w = mount(ImpactPreview, { props: { target }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(axios.get).toHaveBeenCalledWith(expect.stringContaining('impact'), { params: { onu_ids: [5] } });
        expect(w.get('[data-testid=impact-preview]').attributes('data-state')).toBe('ready');
        expect(w.text()).toContain('Budi');
        expect(w.text()).toContain('ODP-A (1)');
    });

    it('fails open: an error is shown, nothing blocks the dialog', async () => {
        axios.get.mockRejectedValueOnce(new Error('boom'));
        const w = mount(ImpactPreview, { props: { target }, global: { plugins: [i18n] } });
        await flushPromises();
        expect(w.get('[data-testid=impact-preview]').attributes('data-state')).toBe('error');
    });
});

describe('useConfirm impact option', () => {
    it('carries the impact target and resets it on the next confirm', () => {
        const { confirmState, confirm } = useConfirm();
        confirm({ impact: target });
        expect(confirmState.impact).toEqual(target);
        confirm({});
        expect(confirmState.impact).toBeNull();
    });
});
