import { describe, expect, it } from 'vitest';
import { formatBps, trafficSeries } from '@/lib/traffic';

describe('formatBps', () => {
    it('scales to the largest whole unit', () => {
        expect(formatBps(0)).toBe('0 bps');
        expect(formatBps(950)).toBe('950 bps');
        expect(formatBps(1500)).toBe('1.50 Kbps');
        expect(formatBps(2_500_000_000)).toBe('2.50 Gbps');
        expect(formatBps(250_000_000)).toBe('250 Mbps');
    });

    it('never turns an unknown value into zero', () => {
        expect(formatBps(null)).toBe('—');
        expect(formatBps(undefined)).toBe('—');
        expect(formatBps('x')).toBe('—');
        expect(formatBps(-1)).toBe('—');
    });
});

describe('trafficSeries', () => {
    it('returns no stats for an empty history', () => {
        expect(trafficSeries([]).stats).toBeNull();
        expect(trafficSeries(null).inPoints).toEqual([]);
    });

    it('uses raw rates for the peak when rows carry no maxima', () => {
        const s = trafficSeries([
            { t: '2026-10-22T12:00:00Z', in_bps: 100, out_bps: 1000, in_max_bps: null, out_max_bps: null },
            { t: '2026-10-22T12:05:00Z', in_bps: 300, out_bps: 3000, in_max_bps: null, out_max_bps: null },
        ]);
        expect(s.outPoints.map((p) => p.y)).toEqual([1000, 3000]);
        expect(s.stats).toEqual({ lastIn: 300, lastOut: 3000, avgIn: 200, avgOut: 2000, peakIn: 300, peakOut: 3000 });
    });

    it('takes the peak from rollup maxima, not from the averages', () => {
        const s = trafficSeries([
            { t: '2026-10-21T00:00:00Z', in_bps: 100, out_bps: 1000, in_max_bps: 900, out_max_bps: 9000 },
            { t: '2026-10-21T01:00:00Z', in_bps: 200, out_bps: 2000, in_max_bps: 250, out_max_bps: 2500 },
        ]);
        expect(s.stats.peakIn).toBe(900);
        expect(s.stats.peakOut).toBe(9000);
        expect(s.stats.avgOut).toBe(1500);
    });

    it('drops rows with an unreadable time or rate', () => {
        const s = trafficSeries([
            { t: 'not-a-date', in_bps: 1, out_bps: 1 },
            { t: '2026-10-22T12:00:00Z', in_bps: null, out_bps: 1 },
            { t: '2026-10-22T12:05:00Z', in_bps: 5, out_bps: 6 },
        ]);
        expect(s.inPoints).toHaveLength(1);
    });
});
