import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import DataNotCurrent from '@/Components/Shell/DataNotCurrent.vue';

vi.mock('@/lib/datetime', () => ({ formatRelative: () => '3 h ago' }));

// NMS-07-D-2b: the shared "data not current" notice on cached-data pages.
const mountNotice = (freshness) =>
    mount(DataNotCurrent, { props: { freshness }, global: { mocks: { $t: (k, p) => `${k}|${p?.state ?? ''}|${p?.age ?? ''}` } } });

describe('DataNotCurrent', () => {
    it('renders nothing for fresh data', () => {
        expect(mountNotice({ state: 'fresh' }).find('[data-testid="data-not-current"]').exists()).toBe(false);
    });

    it.each(['stale', 'unknown', 'paused'])('names the %s state and the age', (state) => {
        const notice = mountNotice({ state, at: '2026-10-22T10:00:00+07:00' }).find('[data-testid="data-not-current"]');

        expect(notice.exists()).toBe(true);
        expect(notice.attributes('data-state')).toBe(state);
        expect(notice.text()).toContain(`dashboard.status_${state}`);
        expect(notice.text()).toContain('3 h ago');
    });

    it('missing freshness is unknown, never silent', () => {
        expect(mountNotice(null).find('[data-testid="data-not-current"]').attributes('data-state')).toBe('unknown');
    });
});
