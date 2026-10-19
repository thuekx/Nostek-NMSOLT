<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\AuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDeviceWritesEnabled
{
    /**
     * Deny OLT/ONT mutations unless the installation explicitly enables them.
     *
     * Pass a request field (for example `device.write:execute`) when the route
     * is safe in preview/dry-run mode and only becomes mutating when that field
     * is truthy.
     */
    public function handle(Request $request, Closure $next, ?string $conditionalField = null): Response
    {
        if ($conditionalField !== null && ! $request->boolean($conditionalField)) {
            return $next($request);
        }

        $enabled = (bool) config('nostek.device_writes_enabled', false);
        $route = $request->route();
        $actor = $request->user() ?? $request->user('sanctum');
        $auditable = collect($route?->parameters() ?? [])->first(fn ($value) => $value instanceof Model);

        AuditLogger::log(
            event: $enabled ? AuditLog::EVENT_DEVICE_WRITE_ALLOWED : AuditLog::EVENT_DEVICE_WRITE_BLOCKED,
            auditable: $auditable instanceof Model ? $auditable : null,
            properties: [
                'decision' => $enabled ? 'allowed' : 'blocked',
                'route' => $route?->getName(),
                'method' => $request->method(),
                'path' => $request->path(),
            ],
            description: $enabled
                ? 'Device write passed the installation safety boundary.'
                : 'Device write blocked by the installation safety boundary.',
            actor: $actor,
        );

        abort_unless(
            $enabled,
            403,
            'Aksi tulis OLT/ONT dinonaktifkan untuk instalasi ini.',
        );

        return $next($request);
    }
}
