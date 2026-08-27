<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyNumartApiSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('marketplace.wa_api_secret', '');

        if ($secret === '' || ! hash_equals($secret, (string) $request->header('X-Marketplace-Secret', ''))) {
            abort(403, 'Unauthorized');
        }

        return $next($request);
    }
}
