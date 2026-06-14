<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_controls', function (Blueprint $table) {
            // Menambahkan kolom setelah kolom mist
            $table->json('mist_stop_at')->nullable()->after('mist');
        });
    }

    public function down(): void
    {
        Schema::table('device_controls', function (Blueprint $table) {
            $table->dropColumn('mist_stop_at');
        });
    }
};