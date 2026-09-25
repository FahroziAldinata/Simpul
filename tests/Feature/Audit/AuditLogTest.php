<?php

namespace Tests\Feature\Audit;

use App\Models\Pegawai;
use App\Models\Sekolah;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_activity_on_sensitive_models(): void
    {
        $sekolah = Sekolah::factory()->create([
            'nama' => 'SMA Negeri 1 Lama',
        ]);

        $sekolah->update([
            'nama' => 'SMA Negeri 1 Baru',
        ]);

        $activity = Activity::where('subject_type', Sekolah::class)
            ->where('subject_id', $sekolah->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('updated', $activity->event);
        $this->assertEquals('SMA Negeri 1 Baru', $activity->properties['attributes']['nama'] ?? null);
        $this->assertEquals('SMA Negeri 1 Lama', $activity->properties['old']['nama'] ?? null);
    }

    public function test_it_redacts_password_and_sensitive_fields_in_activity_log(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('old-secret-password'),
        ]);

        // Clear creation activity
        Activity::query()->delete();

        $user->name = 'Updated Name';
        $user->password = bcrypt('new-secret-password');
        $user->two_factor_secret = 'super-secret-totp-key';
        $user->save();

        $activity = Activity::where('subject_type', User::class)
            ->where('subject_id', (string) $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);

        $properties = $activity->properties->toArray();

        // 1. Password must be redacted, NOT the raw hash or plain text
        $this->assertEquals('[REDACTED]', $properties['attributes']['password']);
        $this->assertNotEquals('new-secret-password', $properties['attributes']['password']);
        $this->assertStringNotContainsString('$', $properties['attributes']['password']);

        // 2. Old password must also be redacted
        if (isset($properties['old']['password'])) {
            $this->assertEquals('[REDACTED]', $properties['old']['password']);
            $this->assertNotEquals('old-secret-password', $properties['old']['password']);
        }

        // 3. Two-factor secret must be redacted
        $this->assertEquals('[REDACTED]', $properties['attributes']['two_factor_secret']);

        // 4. Non-sensitive fields are logged normally
        $this->assertEquals('Updated Name', $properties['attributes']['name']);
    }

    public function test_it_logs_pegawai_changes(): void
    {
        $sekolah = Sekolah::factory()->create();
        $pegawai = Pegawai::withoutGlobalScopes()->create([
            'sekolah_id' => $sekolah->id,
            'nama' => 'Guru Awal',
            'jenis' => 'guru',
            'status_kepegawaian' => 'gty',
        ]);

        $pegawai->update([
            'nama' => 'Guru Baru',
            'status_kepegawaian' => 'pppk',
        ]);

        $activity = Activity::where('subject_type', Pegawai::class)
            ->where('subject_id', $pegawai->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity);
        $this->assertEquals('Guru Baru', $activity->properties['attributes']['nama'] ?? null);
        $this->assertEquals('pppk', $activity->properties['attributes']['status_kepegawaian'] ?? null);
    }
}
