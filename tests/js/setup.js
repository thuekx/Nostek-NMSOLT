import { config } from '@vue/test-utils';

// v-device-write is registered globally in app.js; component tests mount without it.
config.global.directives = { ...(config.global.directives ?? {}), 'device-write': {} };
