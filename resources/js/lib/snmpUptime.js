// Net-SNMP may return Timeticks either as raw centiseconds ("12345") or as
// "(12345) 0:02:03.45". Keep the parser fail-closed so the UI never renders NaN.
export function formatSnmpUptime(value) {
    if (value === null || value === undefined || value === '') return '-';

    const raw = String(value).trim();
    const parenthesized = raw.match(/^\((\d+)\)/);
    const ticks = Number(parenthesized?.[1] ?? raw);

    if (!Number.isFinite(ticks) || ticks < 0) return raw || '-';

    const totalSeconds = Math.floor(ticks / 100);
    const days = Math.floor(totalSeconds / 86400);
    const hours = Math.floor((totalSeconds % 86400) / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    const parts = [];

    if (days) parts.push(`${days}h`);
    if (hours) parts.push(`${hours}j`);
    if (minutes) parts.push(`${minutes}m`);
    if (seconds || parts.length === 0) parts.push(`${seconds}d`);

    return parts.join(' ');
}
