// TRF-1: PON port traffic helpers (rates come from the server in bits per second).

const UNITS = ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'];

/** "1.25 Gbps"; '—' for a missing value (never "0 bps" for unknown). */
export function formatBps(bps) {
    const value = Number(bps);
    if (bps === null || bps === undefined || !Number.isFinite(value) || value < 0) return '—';
    let unit = 0;
    let scaled = value;
    while (scaled >= 1000 && unit < UNITS.length - 1) {
        scaled /= 1000;
        unit += 1;
    }
    return `${scaled.toFixed(unit === 0 || scaled >= 100 ? 0 : 2)} ${UNITS[unit]}`;
}

/**
 * Chart points and summary of a traffic series [{ t, in_bps, out_bps, in_max_bps, out_max_bps }].
 * `in` = into the OLT from the PON (customer upstream), `out` = OLT to the PON (downstream).
 * The peak uses the rollup maxima when present (hourly/daily rows), else the raw rates.
 */
// Number(null) is 0: a missing rate must stay missing, never become "no traffic".
const num = (value) => (value === null || value === undefined || value === '' ? NaN : Number(value));

export function trafficSeries(history) {
    const rows = (history ?? [])
        .map((row) => ({
            x: new Date(row.t).getTime(),
            inBps: num(row.in_bps),
            outBps: num(row.out_bps),
            inMax: Number.isFinite(num(row.in_max_bps)) ? num(row.in_max_bps) : num(row.in_bps),
            outMax: Number.isFinite(num(row.out_max_bps)) ? num(row.out_max_bps) : num(row.out_bps),
        }))
        .filter((r) => Number.isFinite(r.x) && Number.isFinite(r.inBps) && Number.isFinite(r.outBps));

    if (rows.length === 0) return { inPoints: [], outPoints: [], stats: null };

    const avg = (key) => rows.reduce((sum, r) => sum + r[key], 0) / rows.length;
    const last = rows[rows.length - 1];

    return {
        inPoints: rows.map((r) => ({ x: r.x, y: r.inBps })),
        outPoints: rows.map((r) => ({ x: r.x, y: r.outBps })),
        stats: {
            lastIn: last.inBps,
            lastOut: last.outBps,
            avgIn: avg('inBps'),
            avgOut: avg('outBps'),
            peakIn: Math.max(...rows.map((r) => r.inMax)),
            peakOut: Math.max(...rows.map((r) => r.outMax)),
        },
    };
}
