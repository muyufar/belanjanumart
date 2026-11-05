<?php

namespace App\Http\Middleware;

use App\Support\MarketplaceAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! MarketplaceAccess::isAdmin($request->user())) {
            abort(403, 'Akses admin ditolak.');
        }

        return $next($request);
    }
}
