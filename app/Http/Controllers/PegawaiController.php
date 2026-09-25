<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use Illuminate\Http\JsonResponse;

class PegawaiController extends Controller
{
    /**
     * Display the specified resource.
     */
    public function show(Pegawai $pegawai): JsonResponse
    {
        return response()->json([
            'id' => $pegawai->id,
            'nama' => $pegawai->nama,
            'sekolah_id' => $pegawai->sekolah_id,
        ]);
    }
}
