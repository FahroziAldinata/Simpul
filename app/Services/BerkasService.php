<?php

namespace App\Services;

use App\Enums\JenisBerkasSiswa;
use App\Models\BerkasSiswa;
use App\Models\Siswa;
use finfo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use Throwable;

class BerkasService
{
    protected string $disk;

    public function __construct(?string $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.berkas_disk', 's3');
    }

    /**
     * Inspect file real MIME (magic bytes) and validate size and extensions.
     *
     * @return array{real_mime: string, extension: string, sanitized_name: string, file_size: int}
     *
     * @throws ValidationException
     */
    public function validateFile(UploadedFile $file, JenisBerkasSiswa $jenis): array
    {
        $maxSizeBytes = 2 * 1024 * 1024; // 2 MB
        $fileSize = $file->getSize();

        if ($fileSize === false || $fileSize <= 0 || $fileSize > $maxSizeBytes) {
            throw ValidationException::withMessages([
                'berkas' => 'Ukuran berkas tidak boleh melebihi 2 MB.',
            ]);
        }

        // Real MIME inspection via finfo magic bytes
        $realPath = $file->getRealPath();
        if (! $realPath || ! file_exists($realPath)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas tidak valid atau tidak dapat dibaca.',
            ]);
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $realMime = (string) $finfo->file($realPath);

        if ($jenis === JenisBerkasSiswa::Foto) {
            $allowedMimes = ['image/jpeg', 'image/png'];
            if (! in_array($realMime, $allowedMimes, true)) {
                throw ValidationException::withMessages([
                    'berkas' => 'Format foto harus berupa file gambar JPG atau PNG asli.',
                ]);
            }
        } else {
            $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (! in_array($realMime, $allowedMimes, true)) {
                throw ValidationException::withMessages([
                    'berkas' => 'Format berkas harus berupa JPG, PNG, atau PDF asli.',
                ]);
            }
        }

        $ext = match ($realMime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'pdf',
        };

        // Sanitize original filename (keep only alphanumeric, hyphens, underscores, dots)
        $rawClientName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanedBase = (string) preg_replace('/[^a-zA-Z0-9_\-\s\.]/', '', $rawClientName);
        $cleanedBase = trim($cleanedBase);
        if ($cleanedBase === '') {
            $cleanedBase = 'berkas_'.$jenis->value;
        }
        $sanitizedName = substr($cleanedBase, 0, 80).'.'.$ext;

        return [
            'real_mime' => $realMime,
            'extension' => $ext,
            'sanitized_name' => $sanitizedName,
            'file_size' => $fileSize,
        ];
    }

    /**
     * Atomically upload a new file, update database, and delete old file after commit.
     * Cleans up new file if database transaction fails.
     *
     * @throws ValidationException|Throwable
     */
    public function storeBerkas(Siswa $siswa, JenisBerkasSiswa $jenis, UploadedFile $file): BerkasSiswa
    {
        $validated = $this->validateFile($file, $jenis);

        $uuid = (string) Str::uuid();
        $storagePath = "{$siswa->sekolah_id}/{$uuid}.{$validated['extension']}";

        // Upload new file to storage
        $stored = Storage::disk($this->disk)->put($storagePath, (string) file_get_contents($file->getRealPath()));
        if (! $stored) {
            throw new \RuntimeException('Gagal menyimpan file ke penyimpanan berkas.');
        }

        try {
            return DB::transaction(function () use ($siswa, $jenis, $storagePath, $validated) {
                /** @var BerkasSiswa|null $existing */
                $existing = BerkasSiswa::where('siswa_id', $siswa->id)
                    ->where('jenis', $jenis->value)
                    ->first();

                $oldPath = $existing?->file_path;

                if ($existing) {
                    $existing->update([
                        'file_path' => $storagePath,
                        'nama_file_asli' => $validated['sanitized_name'],
                        'mime_type' => $validated['real_mime'],
                        'file_size_bytes' => $validated['file_size'],
                    ]);
                    $record = $existing;
                } else {
                    $record = BerkasSiswa::create([
                        'sekolah_id' => $siswa->sekolah_id,
                        'siswa_id' => $siswa->id,
                        'jenis' => $jenis,
                        'file_path' => $storagePath,
                        'nama_file_asli' => $validated['sanitized_name'],
                        'mime_type' => $validated['real_mime'],
                        'file_size_bytes' => $validated['file_size'],
                    ]);
                }

                // If replacing an existing file, clean up old file after transaction commits
                if ($oldPath && $oldPath !== $storagePath) {
                    DB::afterCommit(function () use ($oldPath) {
                        if (Storage::disk($this->disk)->exists($oldPath)) {
                            Storage::disk($this->disk)->delete($oldPath);
                        }
                    });
                }

                return $record;
            });
        } catch (Throwable $e) {
            // Clean up newly uploaded file if database transaction fails
            if (Storage::disk($this->disk)->exists($storagePath)) {
                Storage::disk($this->disk)->delete($storagePath);
            }
            throw $e;
        }
    }

    /**
     * Generate an on-demand signed temporary URL (default 5 minutes / 300 seconds).
     */
    public function getSignedUrl(BerkasSiswa $berkas, int $expiresInSeconds = 300): string
    {
        $expiration = now()->addSeconds($expiresInSeconds);
        $diskInstance = Storage::disk($this->disk);

        if ($diskInstance->getAdapter() instanceof AwsS3V3Adapter) {
            $browserEndpoint = (string) config('filesystems.disks.s3.browser_endpoint', 'http://localhost:9002');
            $currentEndpoint = (string) config('filesystems.disks.s3.endpoint');

            if ($browserEndpoint !== '' && $browserEndpoint !== $currentEndpoint) {
                config(['filesystems.disks.s3_browser' => array_merge(
                    (array) (config('filesystems.disks.s3') ?? []),
                    [
                        'endpoint' => $browserEndpoint,
                        'url' => $browserEndpoint,
                        'use_path_style_endpoint' => true,
                    ]
                )]);

                return Storage::disk('s3_browser')->temporaryUrl($berkas->file_path, $expiration);
            }
        }

        return $diskInstance->temporaryUrl($berkas->file_path, $expiration);
    }

    /**
     * Delete berkas from database and storage after transaction commits.
     */
    public function deleteBerkas(BerkasSiswa $berkas): bool
    {
        $filePath = $berkas->file_path;

        return (bool) DB::transaction(function () use ($berkas, $filePath) {
            $deleted = $berkas->delete();

            if ($deleted && $filePath) {
                DB::afterCommit(function () use ($filePath) {
                    if (Storage::disk($this->disk)->exists($filePath)) {
                        Storage::disk($this->disk)->delete($filePath);
                    }
                });
            }

            return $deleted;
        });
    }
}
