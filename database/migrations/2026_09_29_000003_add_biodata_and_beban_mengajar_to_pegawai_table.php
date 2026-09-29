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
        Schema::table('pegawai', function (Blueprint $table) {
            $table->string('jenis_kelamin', 1)->nullable()->after('nama'); // L, P
            $table->string('tempat_lahir', 100)->nullable()->after('jenis_kelamin');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('agama', 50)->nullable()->after('tanggal_lahir');
            $table->text('alamat')->nullable()->after('agama');
            $table->string('no_hp', 30)->nullable()->after('alamat');
            $table->string('email', 100)->nullable()->after('no_hp');

            // Beban Mengajar (T-06.05 & US-14)
            $table->integer('jam_maks_per_minggu')->default(24)->after('status_kepegawaian');
            $table->jsonb('hari_tidak_mengajar')->nullable()->after('jam_maks_per_minggu');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pegawai', function (Blueprint $table) {
            $table->dropColumn([
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'alamat',
                'no_hp',
                'email',
                'jam_maks_per_minggu',
                'hari_tidak_mengajar',
            ]);
        });
    }
};
