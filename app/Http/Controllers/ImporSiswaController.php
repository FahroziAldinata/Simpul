<?php

namespace App\Http\Controllers;

use App\Exports\TemplateSiswaExport;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImporSiswaController extends Controller
{
    /**
     * Download template Excel for student import (T-07.02).
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        Gate::authorize('create', Siswa::class);

        return Excel::download(new TemplateSiswaExport, 'template_impor_siswa.xlsx');
    }
}
