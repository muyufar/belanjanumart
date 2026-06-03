<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            if (! $request->routeIs('password.change', 'password.change.store', 'logout')) {
                return redirect()
                    ->route('password.change')
                    ->with('error', 'Anda harus mengganti password sementara terlebih dahulu.');
            }
        }

        return $next($request);
    }
}
