<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\DeviceWriteSetting;
use App\Models\SnmpOlt;
use App\Support\AuditLogger;
use App\Support\DeviceWritePolicy;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceWritesEnabled
{
    /**
     * Deny OLT/ONT mutations unless {@see DeviceWritePolicy} allows them
     * (installation mode, then the admin switches: global, channel, per OLT).
     *
     * Arguments, in any order:
     * - a channel (`device.write:acs`; default `olt`);
     * - a request field (for example `device.write:execute`) when the route is safe in
     *   preview/dry-run mode and only becomes mutating when that field is truthy.
     */
    public function handle(Request $request, Closure $next, string ...$args): Response
    {
        [$channel, $conditionalField] = self::parseArgs($args);

        if ($conditionalField !== null && ! $request->boolean($conditionalField)) {
            return $next($request);
        }

        $route = $request->route();
        $parameters = collect($route?->parameters() ?? []);
        $olt = self::oltFrom($parameters->all());
        $decision = DeviceWritePolicy::decide($channel, $olt);
        $enabled = $decision['allowed'];
        $actor = $request->user() ?? $request->user('sanctum');
        $auditable = $parameters->first(fn ($value) => $value instanceof Model);

        AuditLogger::log(
            event: $enabled ? AuditLog::EVENT_DEVICE_WRITE_ALLOWED : AuditLog::EVENT_DEVICE_WRITE_BLOCKED,
            auditable: $auditable instanceof Model ? $auditable : null,
            properties: [
                'decision' => $enabled ? 'allowed' : 'blocked',
                'gate_reason' => $decision['reason'],
                'gate_mode' => DeviceWritePolicy::mode(),
                'write_channel' => $channel,
                'olt_id' => $olt?->getKey(),
                'route' => $route?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
            ],
            description: $enabled
                ? 'Device write passed the safety boundary.'
                : 'Device write blocked by the safety boundary.',
            actor: $actor,
        );

        abort_unless($enabled, 403, DeviceWritePolicy::message($decision['reason']));

        return $next($request);
    }

    /**
     * @param  list<string>  $args
     * @return array{0: string, 1: ?string}
     */
    public static function parseArgs(array $args): array
    {
        $channel = DeviceWriteSetting::CHANNEL_OLT;
        $field = null;
        foreach ($args as $arg) {
            if ($arg === '') {
                continue;
            }
            if (in_array($arg, DeviceWriteSetting::CHANNELS, true)) {
                $channel = $arg;
            } else {
                $field = $arg;
            }
        }

        return [$channel, $field];
    }

    /**
     * The OLT a request writes to: a bound SnmpOlt parameter, or the `olt` relation of a bound
     * model (map pin). Null when the route names no OLT (then only global/channel apply).
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function oltFrom(array $parameters): ?SnmpOlt
    {
        foreach ($parameters as $value) {
            if ($value instanceof SnmpOlt) {
                return $value;
            }
        }
        if (isset($parameters['olt']) && is_numeric($parameters['olt'])) {
            return SnmpOlt::query()->find((int) $parameters['olt']);
        }
        foreach ($parameters as $value) {
            if ($value instanceof Model && method_exists($value, 'olt')) {
                $related = $value->getRelationValue('olt');
                if ($related instanceof SnmpOlt) {
                    return $related;
                }
            }
        }

        return null;
    }
}
