// NMS-07-D: client side of the device write boundary.
//
// The server (EnsureDeviceWritesEnabled) stays the gate. This module only uses the
// shared `device_writes` prop to (1) stop a request the server would refuse before it
// leaves the browser, with a readable message instead of a bare 403 page, and (2) let
// controls show themselves as locked (v-device-write).

const compiled = new WeakMap();

// Current `device_writes` prop, kept by app.js on every navigation (read by v-device-write).
let currentGate = null;

export function setDeviceWriteGate(gate) {
    currentGate = gate ?? null;
}

export function currentDeviceWriteGate() {
    return currentGate;
}

function entries(gate) {
    if (!gate || gate.enabled || !Array.isArray(gate.gated)) return [];
    if (!compiled.has(gate)) {
        compiled.set(
            gate,
            gate.gated.map((e) => ({ ...e, re: new RegExp(e.pattern) })),
        );
    }
    return compiled.get(gate);
}

function pathOf(url) {
    try {
        return new URL(String(url), 'http://local.invalid').pathname;
    } catch {
        return String(url ?? '');
    }
}

function truthy(value) {
    if (typeof value === 'string') return !['', '0', 'false', 'off', 'no'].includes(value.toLowerCase());
    return Boolean(value);
}

function fieldValue(data, field) {
    if (!data) return undefined;
    if (typeof FormData !== 'undefined' && data instanceof FormData) return data.get(field);
    if (typeof data === 'string') {
        try {
            return JSON.parse(data)?.[field];
        } catch {
            return new URLSearchParams(data).get(field);
        }
    }
    return data[field];
}

/**
 * True when the server would refuse this request because device writes are off.
 * Conditional routes (preview vs execute) count only when their field is truthy.
 */
export function isGatedRequest(gate, method, url, data) {
    const m = String(method || 'GET').toUpperCase();
    if (m === 'GET' || m === 'HEAD') return false;
    const path = pathOf(url);

    return entries(gate).some(
        (e) => e.methods.includes(m) && e.re.test(path) && (e.field === null || truthy(fieldValue(data, e.field))),
    );
}

export function writesEnabled(gate) {
    return gate == null || Boolean(gate.enabled);
}

/**
 * Install the request guard on the Inertia router and on axios.
 * getGate() returns the current `device_writes` prop; notify(message) shows a toast.
 */
export function installDeviceWriteGuard({ router, axios, getGate, notify, message }) {
    router.on('before', (event) => {
        const visit = event.detail.visit;
        if (isGatedRequest(getGate(), visit.method, visit.url, visit.data)) {
            notify(message());
            return false;
        }
        return true;
    });

    axios.interceptors.request.use((config) => {
        if (isGatedRequest(getGate(), config.method, config.url, config.data)) {
            const text = message();
            notify(text);
            const error = new Error(text);
            // Same shape as a server 403, so existing catch blocks show the message.
            error.response = { status: 403, data: { message: text, device_writes_disabled: true } };
            error.config = config;
            error.deviceWritesDisabled = true;
            return Promise.reject(error);
        }
        return config;
    });
}

/**
 * v-device-write: lock a mutating control while device writes are off.
 * Usage: <button v-device-write>…</button> (a value, when given, overrides the current gate).
 */
export function deviceWriteDirective(lockedTitle) {
    const apply = (el, binding) => {
        const gate = binding.value !== undefined ? binding.value : currentGate;
        const locked = !writesEnabled(gate);
        if (locked) {
            el.setAttribute('disabled', '');
            el.setAttribute('aria-disabled', 'true');
            el.setAttribute('title', lockedTitle());
            el.dataset.deviceWriteLocked = 'true';
        } else if (el.dataset.deviceWriteLocked === 'true') {
            el.removeAttribute('disabled');
            el.removeAttribute('aria-disabled');
            el.removeAttribute('title');
            delete el.dataset.deviceWriteLocked;
        }
    };

    return { mounted: apply, updated: apply };
}
