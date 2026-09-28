<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPreferenceController extends Controller
{
    /**
     * Update user preferences in users.preferences column.
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
        ]);

        $user = $request->user();
        $current = $user->preferences ?? [];
        $merged = array_merge($current, $validated['preferences']);

        $user->update(['preferences' => $merged]);

        return response()->json([
            'message' => 'Preferensi berhasil diperbarui.',
            'preferences' => $user->preferences,
        ]);
    }
}
