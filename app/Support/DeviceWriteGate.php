<?php

namespace App\Support;

use App\Http\Middleware\EnsureDeviceWritesEnabled;
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
    /** @var list<array{methods: list<string>, pattern: string, field: ?string}>|null */
    private static ?array $gated = null;

    public static function enabled(): bool
    {
        return (bool) config('nostek.device_writes_enabled', false);
    }

    /**
     * @return array{enabled: bool, gated: list<array{methods: list<string>, pattern: string, field: ?string}>}
     */
    public static function sharedPayload(): array
    {
        $enabled = self::enabled();

        // Nothing to guard client-side when writes are allowed.
        return ['enabled' => $enabled, 'gated' => $enabled ? [] : self::gatedRoutes()];
    }

    /**
     * Every route behind the device-write middleware, as a path regex. `field` is set for
     * routes that only mutate when that request field is truthy (preview vs execute).
     *
     * @return list<array{methods: list<string>, pattern: string, field: ?string}>
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

                $gated[] = [
                    'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
                    'pattern' => self::pattern($route->uri()),
                    'field' => $args !== null && $args !== '' ? $args : null,
                ];
                break;
            }
        }

        return self::$gated = $gated;
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
