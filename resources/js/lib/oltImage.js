// Vendor/model classification retained for compatibility with existing tests.
// No bundled product photos or demo screenshots in the standalone baseline.

/**
 * Kunci gambar untuk satu OLT.
 * @param {{ driver?: string, vendor?: string, name?: string, model?: string, capabilities?: object }} olt
 */
export function oltImageKey(olt = {}) {
    const driver = String(olt.driver ?? '');
    const hay = `${olt.name ?? ''} ${olt.vendor ?? ''} ${olt.model ?? ''}`.toLowerCase();

    // GPON kecil punya panel sendiri (1/2 PON, GE RJ45, 10GE, CONSOLE); model dari faceplate `device.model`.
    if (driver.startsWith('cdata-gpon')) {
        if (hay.includes('fd1601')) return 'cdata-gpon-fd1601s';
        if (hay.includes('fd1602')) return 'cdata-gpon-fd1602s';
        return 'cdata-gpon';
    }
    if (driver.startsWith('cdata-epon')) return 'cdata-epon';
    // HA7302 dikenali backend dari firmware/sysDescr juga (capabilities.is_ha7302), bukan cuma nama.
    if (driver.startsWith('hioso')) return olt.capabilities?.is_ha7302 || hay.includes('ha7302') ? 'hioso-ha7302' : 'hioso-epon';

    if (hay.includes('c320')) return 'zte-c320';
    if (hay.includes('c600') || hay.includes('titan')) return 'zte-c600';
    if (hay.includes('c300')) return 'zte-c300';

    return driver === 'zte' ? 'zte-c300' : 'generic';
}

/** Deprecated compatibility API. No bundled vendor-product image assets. */
export function oltImageCandidates(_key) {
    return [];
}
