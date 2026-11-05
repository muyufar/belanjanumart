<?php

namespace App\Http\Middleware;

use App\Support\MarketplaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBackoffice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MarketplaceAccess::isBackoffice($request->user())) {
            abort(403, 'Akses backoffice ditolak.');
        }

        return $next($request);
    }
}
