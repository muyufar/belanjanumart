<?php

namespace App\Http\Middleware;

use App\Support\MarketplaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MarketplaceAccess::isSuperAdmin($request->user())) {
            abort(403, 'Akses super admin ditolak.');
        }

        return $next($request);
    }
}
