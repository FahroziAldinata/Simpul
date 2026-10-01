<?php

namespace Tests\Unit\Services;

use App\Models\Sekolah;
use App\Models\TitikAbsen;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QrTokenServiceTest extends TestCase
{
    use RefreshDatabase;

    private QrTokenService $service;

    private TitikAbsen $titik;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QrTokenService;

        $sekolah = Sekolah::factory()->create();
        $this->titik = TitikAbsen::factory()->create([
            'sekolah_id' => $sekolah->id,
            'secret' => 'test-secret-key-12345',
            'is_aktif' => true,
        ]);
    }

    public function test_payload_qr_dapat_dibuat_dan_divalidasi_untuk_window_saat_ini(): void
    {
        $payload = $this->service->buatPayloadQr($this->titik);

        $hasil = $this->service->validasiPayload($payload);

        $this->assertTrue($hasil['valid']);
        $this->assertEquals($this->titik->sekolah_id, $hasil['sekolah_id']);
        $this->assertEquals($this->titik->id, $hasil['titik_absen_id']);
        $this->assertNull($hasil['alasan']);
    }

    public function test_token_window_plus_satu_tetap_valid(): void
    {
        $window = $this->service->currentWindow() + 1;
        $token = $this->service->computeToken($this->titik->sekolah_id, $this->titik->id, $window, $this->titik->secret);

        $payload = base64_encode("{$this->titik->sekolah_id}.{$this->titik->id}.{$window}.{$token}");

        $hasil = $this->service->validasiPayload($payload);

        $this->assertTrue($hasil['valid']);
    }

    public function test_token_window_minus_satu_tetap_valid(): void
    {
        $window = $this->service->currentWindow() - 1;
        $token = $this->service->computeToken($this->titik->sekolah_id, $this->titik->id, $window, $this->titik->secret);

        $payload = base64_encode("{$this->titik->sekolah_id}.{$this->titik->id}.{$window}.{$token}");

        $hasil = $this->service->validasiPayload($payload);

        $this->assertTrue($hasil['valid']);
    }

    public function test_token_window_plus_dua_ditolak(): void
    {
        $window = $this->service->currentWindow() + 2;
        $token = $this->service->computeToken($this->titik->sekolah_id, $this->titik->id, $window, $this->titik->secret);

        $payload = base64_encode("{$this->titik->sekolah_id}.{$this->titik->id}.{$window}.{$token}");

        $hasil = $this->service->validasiPayload($payload);

        $this->assertFalse($hasil['valid']);
        $this->assertStringContainsString('kedaluwarsa', $hasil['alasan']);
    }

    public function test_token_window_minus_dua_ditolak_screenshot_lama(): void
    {
        $window = $this->service->currentWindow() - 2;
        $token = $this->service->computeToken($this->titik->sekolah_id, $this->titik->id, $window, $this->titik->secret);

        $payload = base64_encode("{$this->titik->sekolah_id}.{$this->titik->id}.{$window}.{$token}");

        $hasil = $this->service->validasiPayload($payload);

        $this->assertFalse($hasil['valid']);
        $this->assertStringContainsString('kedaluwarsa', $hasil['alasan']);
    }

    public function test_token_palsu_ditolak(): void
    {
        $window = $this->service->currentWindow();
        $payload = base64_encode("{$this->titik->sekolah_id}.{$this->titik->id}.{$window}.fake-token-value");

        $hasil = $this->service->validasiPayload($payload);

        $this->assertFalse($hasil['valid']);
        $this->assertStringContainsString('tidak valid', $hasil['alasan']);
    }

    public function test_titik_absen_nonaktif_ditolak(): void
    {
        $this->titik->update(['is_aktif' => false]);

        $payload = $this->service->buatPayloadQr($this->titik);

        $hasil = $this->service->validasiPayload($payload);

        $this->assertFalse($hasil['valid']);
        $this->assertStringContainsString('tidak aktif', $hasil['alasan']);
    }

    public function test_source_code_menggunakan_hash_equals_untuk_keamanan_timing_attack(): void
    {
        $reflectionClass = new \ReflectionClass(QrTokenService::class);
        $fileName = $reflectionClass->getFileName();
        $this->assertNotFalse($fileName);

        $source = file_get_contents($fileName);
        $this->assertStringContainsString('hash_equals(', $source, 'QrTokenService wajib menggunakan hash_equals() untuk membandingkan HMAC.');
        $this->assertStringNotContainsString('$expectedToken === $claimedToken', $source, 'Tidak boleh membandingkan token HMAC dengan operator ===.');
    }
}
