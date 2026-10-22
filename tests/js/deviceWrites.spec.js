import { describe, expect, it, vi } from 'vitest';
import { deviceWriteDirective, installDeviceWriteGuard, isGatedRequest, setDeviceWriteGate, writesEnabled } from '@/lib/deviceWrites';

// Same shape the server shares (App\Support\DeviceWriteGate::sharedPayload).
const gate = {
    enabled: false,
    gated: [
        { methods: ['POST'], pattern: '^/smartolt/[^/]+/ports/[^/]+/[^/]+/onus/[^/]+/reboot/?$', field: null },
        { methods: ['POST'], pattern: '^/smartolt/[^/]+/register/?$', field: 'execute' },
        { methods: ['DELETE'], pattern: '^/smartolt/[^/]+/profiles/[^/]+/?$', field: 'execute_cli' },
        { methods: ['POST'], pattern: '^/cdata\\-olt/[^/]+/vlans/?$', field: null },
    ],
};

describe('isGatedRequest', () => {
    it('matches gated writes on absolute and relative URLs', () => {
        expect(isGatedRequest(gate, 'post', 'https://nms.example.test/smartolt/5/ports/1/2/onus/7/reboot', {})).toBe(true);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/ports/1/2/onus/7/reboot?x=1', {})).toBe(true);
        expect(isGatedRequest(gate, 'POST', '/cdata-olt/3/vlans', {})).toBe(true);
    });

    it('ignores reads, other methods, other paths and prefixes', () => {
        expect(isGatedRequest(gate, 'GET', '/smartolt/5/ports/1/2/onus/7/reboot', {})).toBe(false);
        expect(isGatedRequest(gate, 'PUT', '/smartolt/5/ports/1/2/onus/7/reboot', {})).toBe(false);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/ports/1/2/onus/7/reboot/extra', {})).toBe(false);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/refresh', {})).toBe(false);
    });

    it('treats preview requests of conditional routes as allowed', () => {
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/register', { execute: false })).toBe(false);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/register', { execute: true })).toBe(true);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/register', '{"execute":true}')).toBe(true);
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/register', 'execute=0')).toBe(false);
        const form = new FormData();
        form.append('execute', '1');
        expect(isGatedRequest(gate, 'POST', '/smartolt/5/register', form)).toBe(true);
        expect(isGatedRequest(gate, 'DELETE', '/smartolt/5/profiles/9', { execute_cli: false })).toBe(false);
    });

    it('never blocks when writes are enabled or the gate is unknown', () => {
        expect(isGatedRequest({ ...gate, enabled: true }, 'POST', '/cdata-olt/3/vlans', {})).toBe(false);
        expect(isGatedRequest(null, 'POST', '/cdata-olt/3/vlans', {})).toBe(false);
        expect(writesEnabled(null)).toBe(true);
        expect(writesEnabled(gate)).toBe(false);
    });
});

describe('installDeviceWriteGuard', () => {
    const setup = () => {
        const handlers = {};
        const router = { on: (name, fn) => { handlers[name] = fn; } };
        let interceptor;
        const axios = { interceptors: { request: { use: (fn) => { interceptor = fn; } } } };
        const notify = vi.fn();
        installDeviceWriteGuard({ router, axios, getGate: () => gate, notify, message: () => 'writes off' });
        return { handlers, interceptor: (c) => interceptor(c), notify };
    };

    it('cancels a gated Inertia visit with a toast and lets others through', () => {
        const { handlers, notify } = setup();
        const visit = (method, url, data = {}) => handlers.before({ detail: { visit: { method, url: new URL(url, 'http://x'), data } } });

        expect(visit('post', '/smartolt/5/ports/1/2/onus/7/reboot')).toBe(false);
        expect(notify).toHaveBeenCalledWith('writes off');
        expect(visit('post', '/smartolt/5/register', { execute: false })).toBe(true);
    });

    it('rejects a gated axios request with a 403-shaped error before it is sent', async () => {
        const { interceptor, notify } = setup();

        await expect(interceptor({ method: 'post', url: '/cdata-olt/3/vlans', data: {} })).rejects.toMatchObject({
            deviceWritesDisabled: true,
            response: { status: 403, data: { message: 'writes off' } },
        });
        expect(notify).toHaveBeenCalledOnce();
        expect(interceptor({ method: 'get', url: '/cdata-olt/3/vlans' })).toMatchObject({ method: 'get' });
    });
});

describe('v-device-write', () => {
    it('locks the control while writes are off and unlocks it when they are on', () => {
        const directive = deviceWriteDirective(() => 'Locked: writes off');
        const el = document.createElement('button');

        directive.mounted(el, { value: gate });
        expect(el.disabled).toBe(true);
        expect(el.getAttribute('aria-disabled')).toBe('true');
        expect(el.title).toBe('Locked: writes off');

        directive.updated(el, { value: { enabled: true, gated: [] } });
        expect(el.disabled).toBe(false);
        expect(el.hasAttribute('title')).toBe(false);
    });

    it('reads the current gate when used without a value', () => {
        const directive = deviceWriteDirective(() => 'x');
        const el = document.createElement('button');
        setDeviceWriteGate(gate);
        directive.mounted(el, { value: undefined });
        expect(el.disabled).toBe(true);
        setDeviceWriteGate(null);
    });

    it('leaves a control alone when the gate prop is missing (guest pages)', () => {
        const directive = deviceWriteDirective(() => 'x');
        const el = document.createElement('button');
        directive.mounted(el, { value: undefined });
        expect(el.disabled).toBe(false);
    });
});
