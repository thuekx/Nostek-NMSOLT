<?php

namespace App\Support;

use App\Http\Middleware\EnsureDeviceWritesEnabled;
use App\Models\DeviceWriteSetting;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * Write-gate state for the UI (NMS-07-D). The server-side boundary stays
 * {@see EnsureDeviceWritesEnabled}; this only tells the browser which requests
 * would be refused, so mutating controls can be shown as locked and a click
 * never ends in a bare 403.
 */
class DeviceWriteGate
{
    /** @var list<array{methods: list<string>, pattern: string, field: ?string, channel: string, olt_segment: ?int}>|null */
    private static ?array $gated = null;

    /** True when some write can run somewhere (drives the read-only banner and v-device-write). */
    public static function enabled(): bool
    {
        return DeviceWritePolicy::anyAllowed();
    }

    /**
     * WRITE-UI-1: besides the overall flag the browser gets the channel switches and the
     * blocked OLT ids, so a request to a switched-off channel or a blocked OLT is stopped
     * client-side too (the server decides again on every request).
     *
     * @return array{enabled: bool, mode: string, expires_at: ?string, channels: array<string, bool>, blocked_olts: list<int>, gated: list<array{methods: list<string>, pattern: string, field: ?string, channel: string, olt_segment: ?int}>}
     */
    public static function sharedPayload(): array
    {
        $mode = DeviceWritePolicy::mode();
        $channels = [];
        foreach (DeviceWriteSetting::CHANNELS as $channel) {
            $channels[$channel] = DeviceWritePolicy::allows($channel);
        }
        $blocked = $mode === DeviceWritePolicy::MODE_ADMIN ? DeviceWritePolicy::blockedOltIds() : [];
        $everythingOpen = ! in_array(false, $channels, true) && $blocked === [];

        return [
            'enabled' => in_array(true, $channels, true),
            'mode' => $mode,
            'expires_at' => $mode === DeviceWritePolicy::MODE_ADMIN ? DeviceWritePolicy::globalExpiresAt()?->toIso8601String() : null,
            'channels' => $channels,
            'blocked_olts' => $blocked,
            // Nothing to guard client-side when every write is allowed.
            'gated' => $everythingOpen ? [] : self::gatedRoutes(),
        ];
    }

    /**
     * Every route behind the device-write middleware, as a path regex. `field` is set for
     * routes that only mutate when that request field is truthy (preview vs execute),
     * `channel` is the write channel and `olt_segment` the path segment (0-based) holding
     * the OLT id, when the route has one.
     *
     * @return list<array{methods: list<string>, pattern: string, field: ?string, channel: string, olt_segment: ?int}>
     */
    public static function gatedRoutes(): array
    {
        if (self::$gated !== null) {
            return self::$gated;
        }

        $gated = [];
        /** @var Route $route */
        foreach (Router::getRoutes()->getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }
                [$name, $args] = array_pad(explode(':', $middleware, 2), 2, null);
                if ($name !== 'device.write' && $name !== EnsureDeviceWritesEnabled::class) {
                    continue;
                }
                [$channel, $field] = EnsureDeviceWritesEnabled::parseArgs($args === null ? [] : explode(',', $args));

                $gated[] = [
                    'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    'pattern' => self::pattern($route->uri()),
                    'field' => $field,
                    'channel' => $channel,
                    'olt_segment' => self::oltSegment($route->uri()),
                ];
                break;
            }
        }

        return self::$gated = $gated;
    }

    /** 0-based index of the `{olt}` segment in the browser path ("/smartolt/{olt}/…" → 1). */
    public static function oltSegment(string $uri): ?int
    {
        $index = array_search('{olt}', explode('/', trim($uri, '/')), true);

        return $index === false ? null : (int) $index;
    }

    /**
     * Laravel URI ("smartolt/{olt}/ports/{slot}") → anchored path regex for JavaScript.
     */
    public static function pattern(string $uri): string
    {
        $parts = preg_split('/(\{[^}]+\})/', trim($uri, '/'), -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
        $regex = '';
        foreach ($parts as $part) {
            // Escape only what JavaScript RegExp treats as special ("-" and "/" stay literal).
            $regex .= str_starts_with($part, '{') ? '[^/]+' : addcslashes($part, '.*+?^$()[]{}|\\');
        }

        return '^/'.$regex.'/?$';
    }

    /** Test seam: routes can change between application instances. */
    public static function flush(): void
    {
        self::$gated = null;
    }
}
