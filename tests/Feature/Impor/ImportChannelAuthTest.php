<?php

namespace Tests\Feature\Impor;

use App\Models\ImportBatch;
use App\Models\Sekolah;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Tests\TestCase;

class ImportChannelAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_reverb_channel_authorization_blocks_users_from_other_schools(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'simpul-key',
            'broadcasting.connections.reverb.secret' => 'simpul-secret',
            'broadcasting.connections.reverb.app_id' => 'simpul-app',
        ]);
        Broadcast::setDefaultDriver('reverb');
        Broadcast::purge();
        require base_path('routes/channels.php');

        $this->seed(RoleAndPermissionSeeder::class);

        $sekolahA = Sekolah::factory()->create(['nama' => 'Sekolah A']);
        $sekolahB = Sekolah::factory()->create(['nama' => 'Sekolah B']);

        setPermissionsTeamId($sekolahA->id);
        $operatorA = User::factory()->create(['sekolah_id' => $sekolahA->id]);
        $operatorA->assignRole('operator');

        setPermissionsTeamId($sekolahB->id);
        $operatorB = User::factory()->create(['sekolah_id' => $sekolahB->id]);
        $operatorB->assignRole('operator');

        $batchA = ImportBatch::factory()->create([
            'sekolah_id' => $sekolahA->id,
            'user_id' => $operatorA->id,
        ]);

        $channelName = "private-sekolah.{$sekolahA->id}.impor.{$batchA->id}";

        // 1. Operator A authorized for Sekolah A's channel
        $responseA = $this->actingAs($operatorA)
            ->withSession(['sekolah_id' => $sekolahA->id])
            ->post('/broadcasting/auth', [
                'channel_name' => $channelName,
                'socket_id' => '1234.5678',
            ]);
        $responseA->assertOk();

        // 2. Operator B (from Sekolah B) is blocked from Sekolah A's channel (403)
        $responseB = $this->actingAs($operatorB)
            ->withSession(['sekolah_id' => $sekolahB->id])
            ->post('/broadcasting/auth', [
                'channel_name' => $channelName,
                'socket_id' => '1234.5678',
            ]);
        $responseB->assertForbidden();
    }
}
