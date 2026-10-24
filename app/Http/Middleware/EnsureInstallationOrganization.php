<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI-F1a: setelan instalasi (bot Telegram global, FCM, ACS/GenieACS, saklar device write, token
 * API semua user) berlaku untuk seluruh instalasi, jadi hanya admin organisasi instalasi yang
 * boleh membukanya. Bagi organisasi lain halaman ini tidak ada (404). Setelan per organisasi
 * menyusul di F1c.
 */
class EnsureInstallationOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(OrganizationContext::class);

        abort_unless($context->isPrimary($context->currentId()), 404);

        return $next($request);
    }
}
