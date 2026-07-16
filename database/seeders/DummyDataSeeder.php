<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SensorData;
use App\Models\Cycle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DummyDataSeeder extends Seeder
{
    public function run()
    {
        // 1. Bersihkan database dengan aman
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        SensorData::truncate();
        Cycle::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Kita gunakan Timestamp Murni (Detik) agar perhitungan progres 100% presisi
        $now = Carbon::now();
        $nowTs = $now->getTimestamp();
        
        $startTime = $now->copy()->subDays(90);
        $startTs = $startTime->getTimestamp();

        // ==========================================
        // 2. BUAT DATA SIKLUS (CYCLES) — 3 Bulan
        // ==========================================
        // Pola: 21 hari aktif + 3 hari jeda = 24 hari per siklus
        // Total ~90 hari → 3 siklus selesai + 1 berjalan

        $DAY = 24 * 3600;

        // Siklus 1: Selesai
        $c1StartTs = $startTs;
        $c1EndTs   = $c1StartTs + (21 * $DAY);
        $c1Waste   = 180.5;
        $c1Harvest = 28.4;
        $c1Residue = 45.2;

        Cycle::create([
            'batch_id'         => '#BCH-' . date('Ym', $c1StartTs) . '-01',
            'start_date'       => Carbon::createFromTimestamp($c1StartTs),
            'end_date'         => Carbon::createFromTimestamp($c1EndTs),
            'initial_seed_mass' => 50,
            'total_waste_input' => $c1Waste,
            'harvest_mass'      => $c1Harvest,
            'residue_mass'      => $c1Residue,
            'wri_result'        => ((($c1Waste - $c1Residue) / $c1Waste) / 21) * 100,
            'eci_result'        => ($c1Harvest / $c1Waste) * 100,
            'status'            => 'selesai',
        ]);

        // Siklus 2: Selesai
        $c2StartTs = $c1EndTs + (3 * $DAY);
        $c2EndTs   = $c2StartTs + (21 * $DAY);
        $c2Waste   = 195.2;
        $c2Harvest = 31.0;
        $c2Residue = 49.8;

        Cycle::create([
            'batch_id'         => '#BCH-' . date('Ym', $c2StartTs) . '-02',
            'start_date'       => Carbon::createFromTimestamp($c2StartTs),
            'end_date'         => Carbon::createFromTimestamp($c2EndTs),
            'initial_seed_mass' => 50,
            'total_waste_input' => $c2Waste,
            'harvest_mass'      => $c2Harvest,
            'residue_mass'      => $c2Residue,
            'wri_result'        => ((($c2Waste - $c2Residue) / $c2Waste) / 21) * 100,
            'eci_result'        => ($c2Harvest / $c2Waste) * 100,
            'status'            => 'selesai',
        ]);

        // Siklus 3: Selesai
        $c3StartTs = $c2EndTs + (3 * $DAY);
        $c3EndTs   = $c3StartTs + (21 * $DAY);
        $c3Waste   = 210.8;
        $c3Harvest = 33.5;
        $c3Residue = 52.1;

        Cycle::create([
            'batch_id'         => '#BCH-' . date('Ym', $c3StartTs) . '-03',
            'start_date'       => Carbon::createFromTimestamp($c3StartTs),
            'end_date'         => Carbon::createFromTimestamp($c3EndTs),
            'initial_seed_mass' => 50,
            'total_waste_input' => $c3Waste,
            'harvest_mass'      => $c3Harvest,
            'residue_mass'      => $c3Residue,
            'wri_result'        => ((($c3Waste - $c3Residue) / $c3Waste) / 21) * 100,
            'eci_result'        => ($c3Harvest / $c3Waste) * 100,
            'status'            => 'selesai',
        ]);

        // Siklus 4: Berjalan (mulai 8 hari lalu)
        $c4StartTs = $nowTs - (8 * $DAY);
        $c4Waste   = 72.5;

        Cycle::create([
            'batch_id'         => '#BCH-' . date('Ym', $c4StartTs) . '-04',
            'start_date'       => Carbon::createFromTimestamp($c4StartTs),
            'end_date'         => null,
            'initial_seed_mass' => 50,
            'total_waste_input' => $c4Waste,
            'harvest_mass'      => null,
            'residue_mass'      => null,
            'wri_result'        => null,
            'eci_result'        => null,
            'status'            => 'berjalan',
        ]);

        // Metadata siklus untuk simulasi sensor (urutan waktu)
        $cycleMeta = [
            ['start' => $c1StartTs, 'end' => $c1EndTs, 'harvestGr' => $c1Harvest * 1000, 'residueGr' => $c1Residue * 1000],
            ['start' => $c2StartTs, 'end' => $c2EndTs, 'harvestGr' => $c2Harvest * 1000, 'residueGr' => $c2Residue * 1000],
            ['start' => $c3StartTs, 'end' => $c3EndTs, 'harvestGr' => $c3Harvest * 1000, 'residueGr' => $c3Residue * 1000],
            ['start' => $c4StartTs, 'end' => null,        'harvestGr' => null,            'residueGr' => null],
        ];

        // ==========================================
        // 3. GENERATE DATA SENSOR (Tiap 10 Menit)
        // ==========================================
        $sensorRecords = [];
        $currentTs = $startTs;

        while ($currentTs <= $nowTs) {
            $harvest = 0;
            $baseMass = 0;

            // Cari siklus aktif pada timestamp ini
            foreach ($cycleMeta as $meta) {
                if ($currentTs >= $meta['start'] && ($meta['end'] === null || $currentTs <= $meta['end'])) {
                    $cycleDuration = 21 * $DAY;
                    $progress = ($currentTs - $meta['start']) / $cycleDuration;
                    $baseMass = 8.33 + ($progress * 12258); // Massa tumbuh dari 8.3g ke ~12.2kg

                    // 10 menit terakhir siklus → Maggot Migrasi (Panen)
                    if ($meta['end'] !== null && ($meta['end'] - $currentTs) <= 600) {
                        $harvest = $meta['harvestGr'];
                        $baseMass = $meta['residueGr'] / 6; // Hanya tersisa Kasgot per rak
                    }
                    break;
                }
            }

            // Fluktuasi noise hanya 2% dari berat aslinya, dibulatkan murni ke integer
            $noise = (int) max(1, $baseMass * 0.02); 
            
            $biopond = [];
            $soil = [];
            for($i=0; $i<6; $i++) {
                if ($baseMass == 0) {
                    $biopond[] = 0;
                } else {
                    $biopond[] = max(0, (int) round($baseMass + rand(-$noise, $noise))); 
                }
                $soil[] = rand(55, 75); // Kelembaban tanah normal 55-75%
            }

            // Simulasi Cuaca (Suhu 28-33C, Hum 60-80%, Amonia 10-35 ppm)
            $temp = round(29.0 + (rand(-15, 20) / 10), 1);
            $hum = round(70.0 + (rand(-60, 60) / 10), 1);
            $ammonia = rand(15, 28);
            
            if (rand(1, 100) > 95) $ammonia = rand(30, 40); // Sesekali gas amonia naik

            // Kembalikan ke format String Datetime untuk database
            $timestampStr = Carbon::createFromTimestamp($currentTs)->format('Y-m-d H:i:s');

            $sensorRecords[] = [
                'biopond' => json_encode($biopond),
                'harvest' => $harvest,
                'temp' => $temp,
                'hum' => $hum,
                'soil' => json_encode($soil),
                'ammonia' => $ammonia,
                'created_at' => $timestampStr,
                'updated_at' => $timestampStr,
            ];

            // Bulk Insert per 500 baris
            if (count($sensorRecords) >= 500) {
                SensorData::insert($sensorRecords);
                $sensorRecords = [];
            }

            // Tambah 10 menit (600 detik)
            $currentTs += 600; 
        }

        if (count($sensorRecords) > 0) {
            SensorData::insert($sensorRecords);
        }
    }
}