// NMS-07-D-2: status honesty. Cached device data older than 3 poll intervals is STALE,
// never-polled data is UNKNOWN, an OLT with polling off is PAUSED; none of them is shown green.
// `freshness` comes from App\Support\Freshness on the server.

export function isFresh(freshness) {
    // Missing freshness (older payloads) is treated as unknown, not as fresh.
    return freshness?.state === 'fresh';
}

/**
 * Status of an OLT row for pills and dots: 'up' only when reachable AND fresh.
 * @returns {'up'|'down'|'stale'|'unknown'|'paused'}
 */
export function oltStatus(reachable, freshness) {
    const state = freshness?.state ?? 'unknown';
    if (state === 'paused') return 'paused';
    if (state === 'unknown') return 'unknown';
    if (state === 'stale') return 'stale';
    return reachable ? 'up' : 'down';
}

export const STATUS_PILL = {
    up: 'nos-pill-success',
    down: 'nos-pill-danger',
    stale: 'nos-pill-warning',
    unknown: 'nos-pill-muted',
    paused: 'nos-pill-muted',
};
