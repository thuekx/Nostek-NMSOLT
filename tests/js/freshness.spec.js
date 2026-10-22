import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { isFresh, oltStatus, STATUS_PILL } from '@/lib/freshness';
import OltInventoryList from '@/Components/Dashboard/OltInventoryList.vue';

describe('freshness', () => {
    it('is green only when reachable and fresh', () => {
        expect(oltStatus(true, { state: 'fresh' })).toBe('up');
        expect(oltStatus(false, { state: 'fresh' })).toBe('down');
        expect(oltStatus(true, { state: 'stale' })).toBe('stale');
        expect(oltStatus(true, { state: 'paused' })).toBe('paused');
        expect(oltStatus(true, undefined)).toBe('unknown');
        expect(isFresh(undefined)).toBe(false);
        expect(STATUS_PILL.stale).toBe('nos-pill-warning');
    });

    it('dashboard list shows a stale OLT as stale, not up, and counts it', () => {
        const wrapper = mount(OltInventoryList, {
            props: {
                items: [
                    { id: 1, name: 'A', model: 'C320', reachable: true, freshness: { state: 'fresh', at: new Date().toISOString() }, unit: 1, up: 1, down: 0, not_fresh: 0 },
                    { id: 2, name: 'B', model: 'C320', reachable: true, freshness: { state: 'stale', at: '2026-10-20T00:00:00Z' }, unit: 1, up: 0, down: 0, not_fresh: 1 },
                ],
            },
            global: { mocks: { $t: (key) => key, route: () => '/smartolt' }, stubs: { Link: { template: '<a><slot /></a>' } } },
        });

        const pills = wrapper.findAll('[data-status]');
        expect(pills.map((p) => p.attributes('data-status'))).toEqual(['up', 'stale']);
        expect(pills[1].classes()).toContain('nos-pill-warning');
        expect(wrapper.text()).toContain('dashboard.status_not_fresh 1');
    });
});
