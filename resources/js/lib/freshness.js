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

/**
 * NMS-07-D-2c partial state: true when a port snapshot's RX values lag its ONU status by more
 * than the freshness threshold (the RX read failed on later polls and older values were kept),
 * or when RX was never read although the status was.
 */
export function rxLags(snapshot) {
    if (!snapshot?.ok || !snapshot.refreshed_at || !(snapshot.onus?.length > 0)) return false;
    const polledAt = snapshot.rx_power?.polled_at;
    if (!polledAt) return snapshot.rx_power?.ok === false;
    const threshold = (snapshot.freshness?.threshold_seconds ?? 900) * 1000;
    return new Date(snapshot.refreshed_at) - new Date(polledAt) > threshold;
}
