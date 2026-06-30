<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cycle;
use App\Models\SensorData;
use Carbon\Carbon;

class CycleController extends Controller
{
    // 1. Menampilkan halaman Manajemen Siklus & Prediksi ADD
    public function index()
    {
        $activeCycle = Cycle::where('status', 'berjalan')->first();
        $finishedCycles = Cycle::where('status', 'selesai')->orderBy('end_date', 'desc')->get();

        $avgTemp = 0;
        $avgHum = 0;
        
        // Variabel Tambahan untuk Model Thermal ADD
        $accumulatedADD = 0;
        $targetADD = 500; // Batas ambang thermal target BSF untuk siap panen (prepupa)
        $addProgress = 0;
        $estimatedRemainingDays = '-';
        
        $latestSensor = SensorData::latest()->first(); // Ambil data sensor paling mutakhir
        
        if ($activeCycle) {
            $sensorHistory = SensorData::where('created_at', '>=', $activeCycle->start_date)->get();
            
            if ($sensorHistory->count() > 0) {
                $avgTemp = round($sensorHistory->avg('temp'), 1);
                $avgHum = round($sensorHistory->avg('hum'), 1);

                // --- LOGIKA HITUNG MODEL ACCUMULATED DEGREE DAYS (ADD) ---
                // Kelompokkan log sensor berdasarkan tanggal pengerjaan
                $perDayLogs = $sensorHistory->groupBy(function($data) {
                    return Carbon::parse($data->created_at)->format('Y-m-d');
                });

                foreach ($perDayLogs as $date => $logs) {
                    $dailyAvgTemp = $logs->avg('temp');
                    
                    // 15&deg;C adalah Batas Suhu Dasar (Base Temperature) Biologis Maggot BSF
                    if ($dailyAvgTemp > 15) { 
                        $accumulatedADD += ($dailyAvgTemp - 15);
                    }
                }
                
                $accumulatedADD = round($accumulatedADD, 1);
                
                // Hitung Persentase Progress Kematangan Larva
                $addProgress = min(round(($accumulatedADD / $targetADD) * 100, 1), 100);
                
                // Hitung Estimasi Sisa Hari Menuju Panen
                $avgDailyADD = $avgTemp - 15;
                if ($avgDailyADD > 0 && $accumulatedADD < $targetADD) {
                    $remainingADD = $targetADD - $accumulatedADD;
                    $estimatedRemainingDays = max(1, ceil($remainingADD / $avgDailyADD));
                } else {
                    $estimatedRemainingDays = 0; // Nilai 0 mengindikasikan fase Prepupa / Siap Panen
                }
            }
        }

        return view('cycle.index', compact(
            'activeCycle', 
            'finishedCycles', 
            'avgTemp', 
            'avgHum', 
            'latestSensor', 
            'accumulatedADD', 
            'targetADD', 
            'addProgress', 
            'estimatedRemainingDays'
        ));
    }

    // 2. Aksi: Memulai Siklus Baru
    public function store(Request $request)
    {
        // Validasi input: dua array (maggot dan pakan) per rak
        $request->validate([
            'maggot_rak' => 'required|array',
            'maggot_rak.*' => 'nullable|numeric|min:0',
            'pakan_rak' => 'required|array',
            'pakan_rak.*' => 'nullable|numeric|min:0',
        ]);

        if (Cycle::where('status', 'berjalan')->exists()) {
            return back()->with('error', 'Gagal! Masih ada siklus yang sedang berjalan.');
        }

        $batchId = '#BCH-' . now()->format('Ym') . '-' . str_pad(Cycle::count() + 1, 2, '0', STR_PAD_LEFT);

        // Hitung akumulasi maggot dan pakan dari seluruh rak
        $totalMaggot = 0;
        foreach ($request->maggot_rak as $val) {
            if ($val !== null && $val !== '' && is_numeric($val)) {
                $totalMaggot += (float) $val;
            }
        }

        $totalPakan = 0;
        foreach ($request->pakan_rak as $val) {
            if ($val !== null && $val !== '' && is_numeric($val)) {
                $totalPakan += (float) $val;
            }
        }

        // Cegah jika semua rak kosong (baik maggot maupun pakan = 0)
        if ($totalMaggot <= 0 && $totalPakan <= 0) {
            return back()->with('error', 'Gagal! Minimal salah satu massa (maggot atau pakan) harus diisi.');
        }

        Cycle::create([
            'batch_id' => $batchId,
            'start_date' => now(),
            'initial_seed_mass' => $totalMaggot,      // Total massa maggot (bibit)
            'total_waste_input' => $totalPakan,        // Total massa pakan awal
            'status' => 'berjalan'
        ]);

        return back()->with('success', "Siklus baru berhasil dimulai! Maggot: {$totalMaggot} g, Pakan: {$totalPakan} g.");
    }

    // 3. Aksi: Menambah Catatan Pakan Manual
    public function addWaste(Request $request)
    {
        // Validasi bahwa input pakan_rak adalah sebuah array
        $request->validate([
            'pakan_rak' => 'required|array',
            'pakan_rak.*' => 'nullable|numeric|min:0',
        ]);

        $cycle = Cycle::where('status', 'berjalan')->first();
        
        if ($cycle) {
            // Hitung total tambahan pakan dari seluruh rak yang diisi (Sekarang dalam GRAM)
            $totalTambahan = 0;
            foreach ($request->pakan_rak as $pakan) {
                // Gunakan is_numeric + strlen agar nilai "0" tidak diabaikan
                if ($pakan !== null && $pakan !== '' && is_numeric($pakan)) {
                    $totalTambahan += (float) $pakan;
                }
            }

            // Jika ada pakan yang ditambahkan, update database
            if ($totalTambahan > 0) {
                // Pastikan nilai di-cast ke float sebelum operasi += (hindari string concat)
                $current = (float) $cycle->total_waste_input;
                $cycle->total_waste_input = $current + $totalTambahan;
                $cycle->save();
                
                return back()->with('success', "Berhasil! Total " . number_format($totalTambahan, 0, ',', '.') . " gram pakan ditambahkan ke dalam siklus.");
            } else {
                return back()->with('error', 'Tidak ada data pakan yang dimasukkan.');
            }
        }

        return back()->with('error', 'Gagal! Tidak ada siklus yang sedang berjalan.');
    }

    // 4. Aksi: Akhiri Siklus (SANGAT OTOMATIS)
    public function finish(Request $request)
    {
        $cycle = Cycle::where('status', 'berjalan')->first();
        
        if (!$cycle) {
            return back()->with('error', 'Tidak ada siklus yang sedang berjalan.');
        }

        // SNAPSHOT PANEN: Ambil data sensor pada detik tombol "Panen" ditekan
        $latestSensor = SensorData::latest()->first();

        if (!$latestSensor) {
            return back()->with('error', 'Data sensor tidak ditemukan. Pastikan ESP32 aktif.');
        }

        // KASGOT: Jika kosong, ambil dari Load Cell (Tidak lagi dibagi 1000 agar tetap dalam Gram)
        $kasgot = $request->kasgot_aktual;
        if (empty($kasgot)) {
            $biopondArray = is_array($latestSensor->biopond) ? $latestSensor->biopond : json_decode($latestSensor->biopond, true) ?? [];
            $kasgot = array_sum($biopondArray); // Absolute Grams
        }

        // PANEN: Jika kosong, ambil dari Load Cell (Rak 7)
        $panen = $request->panen_aktual;
        if (empty($panen)) {
            $panen = $latestSensor->harvest; // Absolute Grams
        }

        $input = $cycle->total_waste_input;
        $days = $cycle->days_elapsed;

        // RUMUS 1: ECI -> (Panen / Input Sampah) * 100%
        $eci = ($input > 0) ? ($panen / $input) * 100 : 0;

        // RUMUS 2: WRI -> ((Input - Kasgot) / Input) / Durasi Hari * 100%
        $wri = ($input > 0 && $days > 0) ? ((($input - $kasgot) / $input) / $days) * 100 : 0;

        $cycle->update([
            'end_date' => now(),
            'harvest_mass' => $panen,
            'residue_mass' => $kasgot,
            'eci_result' => $eci,
            'wri_result' => $wri,
            'status' => 'selesai'
        ]);

        return back()->with('success', 'Siklus berhasil dipanen! Data aktual otomatis ditarik dari sensor Load Cell.');
    }
}