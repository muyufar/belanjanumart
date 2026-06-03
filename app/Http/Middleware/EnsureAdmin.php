<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) env('MARKETPLACE_ADMIN_EMAILS', ''))));

        if ($allowed === [] || ! in_array($request->user()?->email, $allowed, true)) {
            abort(403, 'Akses admin ditolak.');
        }

        return $next($request);
    }
}
