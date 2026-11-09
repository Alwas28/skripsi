<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

/**
 * Holds a student on the change-password page while their password is still
 * the default NIM. Logging out stays possible.
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.force', 'password.force.update', 'logout')) {
            return Redirect::route('password.force');
        }

        return $next($request);
    }
}
