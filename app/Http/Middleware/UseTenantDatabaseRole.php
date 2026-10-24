<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use App\Support\TenantDatabase;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * UI-F1d: setiap request web/API berjalan sebagai role tenant PostgreSQL dengan organisasi user
 * ({@see TenantDatabase}). Diurutkan setelah autentikasi dan sebelum route model binding, jadi
 * binding pun sudah tersaring RLS. Role selalu dikembalikan di akhir request.
 */
class UseTenantDatabaseRole
{
    public function handle(Request $request, Closure $next): Response
    {
        $database = app(TenantDatabase::class);
        $context = app(OrganizationContext::class);

        $database->enter($context->currentId(), $context->isPrimary($context->currentId()));

        try {
            return $next($request);
        } finally {
            $database->leave();
        }
    }
}
