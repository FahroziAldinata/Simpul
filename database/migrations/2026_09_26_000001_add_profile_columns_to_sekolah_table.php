<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->text('alamat')->nullable()->after('status');
            $table->decimal('latitude', 10, 8)->nullable()->after('alamat');
            $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            $table->unsignedInteger('radius_absen_meter')->default(150)->after('longitude');
            $table->string('logo_path')->nullable()->after('radius_absen_meter');
            $table->string('kepala_sekolah')->nullable()->after('logo_path');
            $table->string('akreditasi', 10)->nullable()->after('kepala_sekolah');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sekolah', function (Blueprint $table) {
            $table->dropColumn([
                'alamat',
                'latitude',
                'longitude',
                'radius_absen_meter',
                'logo_path',
                'kepala_sekolah',
                'akreditasi',
            ]);
        });
    }
};
