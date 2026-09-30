<?php

namespace App\Http\Controllers;

use App\Exports\FailedRowsExport;
use App\Exports\TemplateSiswaExport;
use App\Http\Requests\Impor\SaveMappingRequest;
use App\Http\Requests\Impor\UploadExcelRequest;
use App\Jobs\ExecuteImportBatchJob;
use App\Jobs\ValidateImportBatchJob;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Siswa;
use App\Services\Impor\ColumnMapper;
use App\Services\Impor\DuplicateDetector;
use App\Services\Impor\ExcelReaderService;
use App\Services\Impor\RollbackImportService;
use App\Services\Impor\RowNormalizer;
use App\Services\Impor\RowValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImporSiswaController extends Controller
{
    public function __construct(
        protected ExcelReaderService $readerService,
        protected ColumnMapper $columnMapper,
        protected RowNormalizer $rowNormalizer,
        protected DuplicateDetector $duplicateDetector,
        protected RowValidator $rowValidator,
        protected RollbackImportService $rollbackService
    ) {}

    /**
     * Resolve active school ID for current user.
     */
    protected function getActiveSekolahId(Request $request): string
    {
        $user = $request->user();
        if (! $user) {
            abort(401);
        }

        $sekolahId = $user->hasRole('super_admin') ? session('sekolah_id') : $user->sekolah_id;
        if (! $sekolahId) {
            abort(404, 'Sekolah aktif tidak ditemukan.');
        }

        return (string) $sekolahId;
    }

    /**
     * Display import dashboard & upload page (T-07.03).
     */
    public function index(Request $request): Response
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        $recentBatches = ImportBatch::withoutGlobalScopes()
            ->where('sekolah_id', $sekolahId)
            ->where('tipe', 'siswa')
            ->orderByDesc('created_at')
            ->take(10)
            ->get();

        return Inertia::render('Siswa/Impor/Index', [
            'recentBatches' => $recentBatches,
        ]);
    }

    /**
     * Download template Excel for student import (T-07.02).
     */
    public function downloadTemplate(Request $request): BinaryFileResponse
    {
        Gate::authorize('create', Siswa::class);

        return Excel::download(new TemplateSiswaExport, 'template_impor_siswa.xlsx');
    }

    /**
     * Upload Excel file, parse preview, create ImportBatch (T-07.03).
     */
    public function upload(UploadExcelRequest $request): RedirectResponse|JsonResponse
    {
        $sekolahId = $this->getActiveSekolahId($request);
        $user = $request->user();

        $file = $request->file('file');
        if (! $file) {
            throw ValidationException::withMessages(['file' => 'File tidak ditemukan.']);
        }

        $uuid = (string) Str::uuid();
        $ext = $file->getClientOriginalExtension();
        $storedPath = "imports/{$sekolahId}/{$uuid}.{$ext}";

        Storage::disk('local')->putFileAs("imports/{$sekolahId}", $file, "{$uuid}.{$ext}");
        $fullPath = Storage::disk('local')->path($storedPath);

        try {
            $preview = $this->readerService->parsePreview($fullPath);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($storedPath);
            throw ValidationException::withMessages(['file' => 'Gagal membaca file: '.$e->getMessage()]);
        }

        if ($preview['total_rows'] > 5000) {
            Storage::disk('local')->delete($storedPath);
            throw ValidationException::withMessages([
                'file' => 'Jumlah baris data ('.number_format($preview['total_rows']).') melebihi batas maksimal 5.000 baris.',
            ]);
        }

        /** @var ImportBatch $batch */
        $batch = ImportBatch::create([
            'sekolah_id' => $sekolahId,
            'user_id' => $user->id,
            'tipe' => 'siswa',
            'nama_file' => $file->getClientOriginalName(),
            'path' => $storedPath,
            'pemetaan_kolom' => $preview['predicted_mapping'],
            'total_baris' => $preview['total_rows'],
            'status' => 'uploaded',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'batch' => $batch,
                'preview' => $preview,
                'redirect_url' => route('siswa.impor.mapping', $batch->id),
            ]);
        }

        return redirect()->route('siswa.impor.mapping', $batch->id);
    }

    /**
     * Show column mapping UI (T-07.05).
     */
    public function showMapping(Request $request, ImportBatch $batch): Response
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        $fullPath = Storage::disk('local')->path($batch->path);
        $preview = $this->readerService->parsePreview($fullPath);

        $user = $request->user();
        $savedTemplates = $user->preferences['import_mapping_templates'] ?? [];

        return Inertia::render('Siswa/Impor/Mapping', [
            'batch' => $batch,
            'headers' => $preview['headers'],
            'predictedMapping' => $batch->pemetaan_kolom ?? $preview['predicted_mapping'],
            'sampleRows' => $preview['sample_rows'],
            'systemFields' => ColumnMapper::SYSTEM_FIELDS,
            'savedTemplates' => $savedTemplates,
        ]);
    }

    /**
     * Save column mapping and dispatch background validation job (T-07.05 & T-07.08).
     */
    public function saveMapping(SaveMappingRequest $request, ImportBatch $batch): RedirectResponse|JsonResponse
    {
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        $request->validateMandatoryFields();
        $mapping = $request->input('mapping');

        $batch->update([
            'pemetaan_kolom' => $mapping,
            'status' => 'validating',
        ]);

        // Save custom mapping as user preference template if requested
        $templateName = $request->input('template_name');
        if (! empty($templateName)) {
            $user = $request->user();
            $prefs = $user->preferences ?? [];
            $prefs['import_mapping_templates'][$templateName] = $mapping;
            $user->update(['preferences' => $prefs]);
        }

        // Dispatch background validation job (Decision #1: explicit sekolah_id)
        ValidateImportBatchJob::dispatch(
            sekolahId: $sekolahId,
            batchId: $batch->id,
            headerRowIndex: 1,
            storageDisk: 'local'
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Pemetaan berhasil disimpan, validasi sedang berjalan.',
                'redirect_url' => route('siswa.impor.preview', $batch->id),
            ]);
        }

        return redirect()->route('siswa.impor.preview', $batch->id);
    }

    /**
     * Show tabbed preview: Valid | Peringatan | Gagal (T-07.09).
     */
    public function showPreview(Request $request, ImportBatch $batch): Response|JsonResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        $statusFilter = $request->query('status', 'semua');
        $query = $batch->rows()->orderBy('nomor_baris');

        if (in_array($statusFilter, ['valid', 'peringatan', 'gagal'], true)) {
            $query->where('status', $statusFilter);
        }

        $rows = $query->paginate(50)->withQueryString();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'batch' => $batch,
                'rows' => $rows,
            ]);
        }

        $canRollback = $batch->status === 'done' &&
            $batch->dapat_dirollback_hingga !== null &&
            now()->lt($batch->dapat_dirollback_hingga);

        return Inertia::render('Siswa/Impor/Preview', [
            'batch' => $batch,
            'rows' => $rows,
            'currentFilter' => $statusFilter,
            'canRollback' => $canRollback,
            'reverbChannel' => "sekolah.{$sekolahId}.impor.{$batch->id}",
        ]);
    }

    /**
     * Inline row repair in preview and immediate re-validation (T-07.10).
     */
    public function updateRow(Request $request, ImportBatch $batch, ImportRow $row): JsonResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId || $row->import_batch_id !== $batch->id) {
            abort(403);
        }

        $validated = $request->validate([
            'data' => ['required', 'array'],
        ]);

        // Normalize updated row
        $clean = $this->rowNormalizer->normalize($validated['data']);

        // Check duplicates
        $nisn = $clean['nisn'] ?? null;
        $dbDups = $nisn ? $this->duplicateDetector->detectDatabaseDuplicates($sekolahId, [$nisn]) : ['same_school' => [], 'other_school' => []];

        $dbSameSchool = $nisn ? ($dbDups['same_school'][$nisn] ?? null) : null;
        $dbOtherSchool = $nisn ? ($dbDups['other_school'][$nisn] ?? false) : false;

        $validation = $this->rowValidator->validateRow(
            data: $clean,
            inFileDuplicateWith: null,
            dbDuplicateSameSchool: $dbSameSchool,
            dbDuplicateOtherSchool: $dbOtherSchool
        );

        $errorsCombined = array_merge($validation['errors'], $validation['warnings']);

        $row->update([
            'data_bersih' => $clean,
            'status' => $validation['status'],
            'errors' => ! empty($errorsCombined) ? $errorsCombined : null,
            'aksi_duplikat' => $validation['aksi_duplikat'],
            'model_id' => $validation['model_id'],
        ]);

        // Recalculate batch statistics
        $batch->update([
            'valid' => $batch->rows()->where('status', 'valid')->count(),
            'peringatan' => $batch->rows()->where('status', 'peringatan')->count(),
            'gagal' => $batch->rows()->where('status', 'gagal')->count(),
        ]);

        return response()->json([
            'message' => 'Baris berhasil diperbarui dan divalidasi ulang.',
            'row' => $row->fresh(),
            'batch' => $batch->fresh(),
        ]);
    }

    /**
     * Resolve duplicate action: lewati | perbarui | buat_baru (T-07.11).
     */
    public function resolveDuplicate(Request $request, ImportBatch $batch, ImportRow $row): JsonResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId || $row->import_batch_id !== $batch->id) {
            abort(403);
        }

        $validated = $request->validate([
            'aksi' => ['required', 'in:lewati,perbarui,buat_baru'],
        ]);

        $row->update([
            'aksi_duplikat' => $validated['aksi'],
        ]);

        return response()->json([
            'message' => 'Aksi duplikat berhasil diperbarui.',
            'row' => $row->fresh(),
        ]);
    }

    /**
     * Execute import in chunks of 200 (T-07.12).
     */
    public function execute(Request $request, ImportBatch $batch): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        if ($batch->status === 'done') {
            throw ValidationException::withMessages(['batch' => 'Batch impor ini sudah selesai diproses.']);
        }

        ExecuteImportBatchJob::dispatch(
            sekolahId: $sekolahId,
            batchId: $batch->id
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Proses impor dimulai di latar belakang.',
                'batch' => $batch->fresh(),
            ]);
        }

        return redirect()->route('siswa.impor.preview', $batch->id);
    }

    /**
     * Rollback import within 24 hours (T-07.13).
     */
    public function rollback(Request $request, ImportBatch $batch): RedirectResponse|JsonResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        $result = $this->rollbackService->rollback($batch);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Impor berhasil dibatalkan.',
                'result' => $result,
            ]);
        }

        $message = "Impor berhasil dibatalkan. {$result['dihapus']} siswa dihapus.";
        if ($result['tersentuh_dilewati'] > 0) {
            $message .= " {$result['tersentuh_dilewati']} siswa dilewati karena data telah dimodifikasi (tersentuh).";
        }

        return redirect()->route('siswa.impor.preview', $batch->id)
            ->with('success', $message)
            ->with('rollback_result', $result);
    }

    /**
     * Download failed rows as Excel spreadsheet (T-07.14).
     */
    public function exportFailed(Request $request, ImportBatch $batch): BinaryFileResponse
    {
        Gate::authorize('create', Siswa::class);
        $sekolahId = $this->getActiveSekolahId($request);

        if ($batch->sekolah_id !== $sekolahId) {
            abort(403);
        }

        $filename = 'baris_gagal_impor_'.Str::slug($batch->nama_file).'.xlsx';

        return Excel::download(new FailedRowsExport($batch), $filename);
    }
}
