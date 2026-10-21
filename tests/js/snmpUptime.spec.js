import { describe, expect, it } from 'vitest';
import { formatSnmpUptime } from '@/lib/snmpUptime';

describe('formatSnmpUptime', () => {
    it('formats raw centiseconds', () => {
        expect(formatSnmpUptime('9006100')).toBe('1h 1j 1m 1d');
    });

    it('extracts ticks from Net-SNMP Timeticks output', () => {
        expect(formatSnmpUptime('(9006100) 1 day, 1:01:01.00')).toBe('1h 1j 1m 1d');
    });

    it('never renders NaN for missing or vendor-specific values', () => {
        expect(formatSnmpUptime(null)).toBe('-');
        expect(formatSnmpUptime('vendor uptime unavailable')).toBe('vendor uptime unavailable');
    });
});
