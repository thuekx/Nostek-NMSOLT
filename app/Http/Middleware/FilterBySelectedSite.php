<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI-F1b: halaman ringkasan mengikuti site pilihan di header ("Organisasi ▸ Site").
 * Dipasang hanya pada halaman ringkasan web; API, halaman detail dan formulir tidak tersaring.
 */
class FilterBySelectedSite
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User) {
            $context = app(OrganizationContext::class);
            $context->filterBySite($request, $context->selectedSiteId($user));
        }

        return $next($request);
    }
}
