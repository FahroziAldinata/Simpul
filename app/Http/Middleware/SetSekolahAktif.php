<?php

namespace App\Http\Middleware;

use App\Models\Sekolah;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetSekolahAktif
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->sekolah_id !== null) {
                // User terikat sekolah: sinkronkan sesi ke sekolah miliknya
                session(['sekolah_id' => $user->sekolah_id]);
            } else {
                // Super Admin: jika belum ada di sesi atau tidak valid, pilih sekolah pertama secara deterministik via npsn
                $currentSessionId = session('sekolah_id');
                if (! $currentSessionId || ! Sekolah::where('id', $currentSessionId)->exists()) {
                    $defaultSekolah = Sekolah::orderBy('npsn')->first();
                    if ($defaultSekolah) {
                        session(['sekolah_id' => $defaultSekolah->id]);
                    }
                }
            }

            if (function_exists('setPermissionsTeamId') && session()->has('sekolah_id')) {
                setPermissionsTeamId(session('sekolah_id'));
            }
        }

        return $next($request);
    }
}
