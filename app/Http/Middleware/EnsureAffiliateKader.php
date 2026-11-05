<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAffiliateKader
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user?->isAffiliateKader() || ! $user->affiliate_active) {
            abort(403, 'Halaman ini khusus mitra kader NU.');
        }

        return $next($request);
    }
}
