// UI-B1: warna serat TIA-598 (1–12). Warna fisik, sengaja tidak ikut tema: teknisi
// mencocokkannya dengan tube/serat di lapangan. Nama warna dari server (App\Support\RxBand).
export const FIBER_HEX = {
    blue: '#1f6feb',
    orange: '#f0883e',
    green: '#2ea043',
    brown: '#8b5a2b',
    slate: '#8b949e',
    white: '#f6f8fa',
    red: '#da3633',
    black: '#161b22',
    yellow: '#e3b341',
    violet: '#8957e5',
    rose: '#f778ba',
    aqua: '#39c5cf',
};

export function fiberHex(name) {
    return FIBER_HEX[name] ?? FIBER_HEX.slate;
}

// Kelas warna per level RX. Data tidak segar (STALE/UNKNOWN/PAUSED) tidak pernah tampil
// hijau/merah seolah langsung: semua jadi abu-abu (NMS-07-D-2).
const LEVEL_TEXT = { good: 'text-success', warning: 'text-warning', critical: 'text-destructive', none: 'text-muted-foreground' };
const LEVEL_BG = { good: 'bg-success', warning: 'bg-warning', critical: 'bg-destructive', none: 'bg-muted-foreground/40', offline: 'bg-muted-foreground/20' };
const LEVEL_FILL = { good: 'fill-success', warning: 'fill-warning', critical: 'fill-destructive', none: 'fill-muted-foreground' };

export function levelText(level, stale = false) {
    return stale ? 'text-muted-foreground' : LEVEL_TEXT[level] ?? LEVEL_TEXT.none;
}

export function levelBg(level, stale = false) {
    if (stale) return level === 'offline' ? LEVEL_BG.offline : 'bg-muted-foreground/50';
    return LEVEL_BG[level] ?? LEVEL_BG.none;
}

export function levelFill(level, stale = false) {
    return stale ? 'fill-muted-foreground' : LEVEL_FILL[level] ?? LEVEL_FILL.none;
}

// Segmen bar ringkas sebuah cabang: baik / peringatan / kritis / online-tanpa-RX / offline.
export function totalsSegments(totals) {
    const total = totals?.onus ?? 0;
    if (!total) return [];
    // `none` menghitung juga ONU offline (RX-nya tidak dihitung), jadi offline dipisah.
    const noneOnline = Math.max(0, (totals.none ?? 0) - (totals.offline ?? 0));
    return [
        ['good', totals.good ?? 0],
        ['warning', totals.warning ?? 0],
        ['critical', totals.critical ?? 0],
        ['none', noneOnline],
        ['offline', totals.offline ?? 0],
    ]
        .filter(([, n]) => n > 0)
        .map(([level, n]) => ({ level, n, pct: (n / total) * 100 }));
}
