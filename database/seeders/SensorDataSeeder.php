<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SensorDataSeeder extends Seeder
{
    public function run()
    {
        DB::table('sensor_data')->truncate();
        $data = [];
        // Mulai dari 30 hari yang lalu
        $currentDate = Carbon::now()->subDays(30);
        $endDate = Carbon::now();

        // Berat awal bibit maggot per rak (gram)
        $biopondBases = [500, 520, 480, 550, 490, 510];

        while ($currentDate <= $endDate) {
            $hour = $currentDate->hour;
            $daysElapsed = 30 - $currentDate->diffInDays($endDate);

            // 1. Logika Suhu & Udara (Siang lebih panas & kering, Malam lebih dingin & lembab)
            $isDaytime = ($hour >= 7 && $hour <= 17);
            $temp = $isDaytime ? rand(290, 350) / 10 : rand(250, 285) / 10;
            $hum = $isDaytime ? rand(550, 700) / 10 : rand(750, 900) / 10;

            // 2. Logika Amonia (Fluktuasi 15 - 35 ppm)
            $ammonia = rand(15, 35);

            // 3. Logika Tanah & Massa Maggot (Tumbuh seiring berjalannya hari)
            $biopond = [];
            $soil = [];
            $growth = $daysElapsed * rand(40, 60); // Tumbuh ~50g per hari

            for ($i = 0; $i < 6; $i++) {
                $biopond[] = $biopondBases[$i] + $growth + rand(-20, 20); // + Noise acak
                $soil[] = rand(50, 75); // Kelembaban tanah stabil 50-75%
            }

            // 4. Logika Panen (Simulasi panen di hari ke-28)
            $harvest = 0;
            if ($daysElapsed == 28 && $hour == 8 && $currentDate->minute == 0) {
                $harvest = array_sum($biopond); // Panen total
                $biopondBases = [100, 100, 100, 100, 100, 100]; // Reset ke bibit baru
            }

            // Masukkan ke array
            $data[] = [
                'biopond' => json_encode($biopond),
                'harvest' => $harvest,
                'temp' => $temp,
                'hum' => $hum,
                'soil' => json_encode($soil),
                'ammonia' => $ammonia,
                'created_at' => $currentDate->format('Y-m-d H:i:s'),
                'updated_at' => $currentDate->format('Y-m-d H:i:s'),
            ];

            // Tambah 10 Menit
            $currentDate->addMinutes(10);

            // Insert per 500 baris agar RAM laptop tidak jebol
            if (count($data) >= 500) {
                DB::table('sensor_data')->insert($data);
                $data = [];
            }
        }

        // Insert sisa data
        if (count($data) > 0) {
            DB::table('sensor_data')->insert($data);
        }

        $this->command->info('Berhasil menyuntikkan 4.320 data sensor realistis!');
    }
}