<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DeviceControl;
use App\Models\SensorData;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // --- Playwright Black Box Test User ---
        // Credentials: test@si-maggot.id / Test1234!
        if (!User::where('email', 'test@si-maggot.id')->exists()) {
            User::factory()->create([
                'name' => 'Test User',
                'email' => 'test@si-maggot.id',
                'password' => Hash::make('Test1234!'),
            ]);
        }

        // --- Super Admin (jika belum ada) ---
        if (!User::where('email', 'admin@si-maggot.id')->exists()) {
            User::factory()->create([
                'name' => 'Super Admin',
                'email' => 'admin@si-maggot.id',
                'password' => Hash::make('Admin1234!'),
            ]);
        }

        // --- DeviceControl Default (jika belum ada) ---
        if (!DeviceControl::exists()) {
            DeviceControl::create([
                'is_manual' => false,
                'force_sensor_update' => false,
                'fan' => 0,
                'mist' => array_fill(0, 6, 0),
                'mist_stop_at' => array_fill(0, 6, null),
                'controlled_by' => null,
                'locked_until' => null,
                'last_ping_at' => now(),
            ]);
        }

        // --- Dummy Data: Sensor & Siklus ---
        $this->call(DummyDataSeeder::class);
    }
}
