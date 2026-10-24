import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RxBandChart from '@/Components/Network/RxBandChart.vue';
import { fiberHex, levelBg, levelFill, levelText, totalsSegments } from '@/lib/fiber';

const bins = (counts) => Object.entries(counts).map(([dbm, count]) => {
    const d = Number(dbm);
    const mid = d + 0.5;
    const level = mid <= -28 || mid >= -8 ? 'critical' : mid <= -25 || mid >= -10 ? 'warning' : 'good';
    return { dbm: d, count, level };
});

describe('fiber helpers', () => {
    it('maps TIA-598 names to fixed colours and falls back to slate', () => {
        expect(fiberHex('blue')).toBe('#1f6feb');
        expect(fiberHex('nope')).toBe(fiberHex('slate'));
    });

    it('never shows stale data green or red', () => {
        expect(levelText('good')).toBe('text-success');
        expect(levelText('critical')).toBe('text-destructive');
        for (const level of ['good', 'warning', 'critical']) {
            expect(levelText(level, true)).toBe('text-muted-foreground');
            expect(levelBg(level, true)).not.toMatch(/success|warning|destructive/);
            expect(levelFill(level, true)).toBe('fill-muted-foreground');
        }
    });

    it('splits branch totals into segments that add up to 100%', () => {
        const segs = totalsSegments({ onus: 10, online: 8, offline: 2, good: 5, warning: 2, critical: 0, none: 3 });
        expect(segs.map((s) => s.level)).toEqual(['good', 'warning', 'none', 'offline']);
        expect(segs.find((s) => s.level === 'none').n).toBe(1);
        expect(segs.reduce((a, s) => a + s.pct, 0)).toBeCloseTo(100);
        expect(totalsSegments({ onus: 0 })).toEqual([]);
    });
});

describe('RxBandChart', () => {
    const band = { bins: bins({ '-32': 0, '-29': 2, '-20': 5, '-9': 1, '-6': 0 }), total: 8 };
    const opts = { global: { mocks: { $t: (k, p) => `${k}${p ? JSON.stringify(p) : ''}` } } };

    it('draws one bar per bin, scaled to the largest, coloured by level', () => {
        const w = mount(RxBandChart, { props: { band }, ...opts });
        const bars = w.findAll('rect[data-dbm]');
        expect(bars).toHaveLength(5);
        const bar = (d) => w.get(`rect[data-dbm="${d}"]`);
        expect(Number(bar(-20).attributes('height'))).toBeGreaterThan(Number(bar(-29).attributes('height')));
        expect(bar(-32).attributes('height')).toBe('0');
        expect(bar(-29).classes()).toContain('fill-destructive');
        expect(bar(-20).classes()).toContain('fill-success');
        expect(bar(-9).classes()).toContain('fill-warning');
    });

    it('turns every bar grey when the data is stale', () => {
        const w = mount(RxBandChart, { props: { band, stale: true }, ...opts });
        for (const bar of w.findAll('rect[data-dbm]')) {
            expect(bar.classes()).toContain('fill-muted-foreground');
        }
    });
});
