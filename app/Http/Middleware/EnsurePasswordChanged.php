<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password) {
            // Allowed routes: security password change page, password update, logout, password confirm
            if ($request->routeIs('security.edit')
                || $request->routeIs('user-password.update')
                || $request->routeIs('logout')
                || $request->routeIs('password.confirm')
                || $request->routeIs('password.confirm.store')
            ) {
                // Ensure auth.password_confirmed_at is set so user is not blocked by RequirePassword
                if (! $request->session()->has('auth.password_confirmed_at')) {
                    $request->session()->put('auth.password_confirmed_at', time());
                }

                return $next($request);
            }

            // Redirect any other route to security.edit
            if ($request->header('X-Inertia')) {
                return Inertia::location(route('security.edit'));
            }

            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
