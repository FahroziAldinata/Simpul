<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotDeleted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isUserDeleted = false;

        if ($user) {
            $rawAttributes = $user->getAttributes();
            if (array_key_exists('deleted_at', $rawAttributes)) {
                if ($rawAttributes['deleted_at'] !== null) {
                    $isUserDeleted = true;
                }
            } elseif ($user->trashed()) {
                $isUserDeleted = true;
            }
        } elseif ($request->hasSession()) {
            $guardName = Auth::guard('web')->getName();
            $sessionUserId = $request->session()->get($guardName);
            if ($sessionUserId) {
                $trashedUser = User::withTrashed()->find($sessionUserId);
                if ($trashedUser && $trashedUser->trashed()) {
                    $isUserDeleted = true;
                }
            }
        }

        if ($isUserDeleted) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() || $request->header('X-Inertia')) {
                if ($request->header('X-Inertia')) {
                    return Inertia::location(route('login'));
                }

                return response()->json([
                    'message' => 'Akun Anda telah dinonaktifkan.',
                ], 401);
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Akun Anda telah dinonaktifkan.',
            ]);
        }

        return $next($request);
    }
}
