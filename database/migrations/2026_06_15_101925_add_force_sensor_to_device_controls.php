<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('device_controls', function (Blueprint $table) {
            $table->boolean('force_sensor_update')->default(false)->after('is_manual');
        });
    }
    public function down(): void {
        Schema::table('device_controls', function (Blueprint $table) {
            $table->dropColumn('force_sensor_update');
        });
    }
};