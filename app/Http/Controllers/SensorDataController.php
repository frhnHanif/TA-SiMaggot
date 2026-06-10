<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SensorData;
use App\Models\DeviceControl;
use App\Models\Cycle;

class SensorDataController extends Controller
{
    // =========================================================================
    // KELOMPOK 1: API UNTUK HARDWARE (ESP32)
    // =========================================================================

    // Menerima data sensor dari ESP32 (POST)
    public function store(Request $request)
    {
        $data = SensorData::create([
            'biopond' => $request->biopond,
            'harvest' => $request->harvest,
            'temp' => $request->temp,
            'hum' => $request->hum,
            'soil' => $request->soil,
            'ammonia' => $request->ammonia,
        ]);

        return response()->json(['message' => 'Data berhasil disimpan', 'data' => $data], 201);
    }

    // Mengirimkan status kontrol terbaru ke ESP32 (GET)
   // Mengirimkan status kontrol terbaru ke ESP32 atau Postman (GET)
    public function getControl()
    {
        // Gunakan with() untuk memuat relasi data User sekaligus (Eager Loading)
        $control = DeviceControl::with('controllerUser')->first();
        
        return response()->json([
            'is_manual' => $control->is_manual,
            'fan' => $control->fan,
            'mist' => $control->mist,
            
            // Tampilkan ID-nya
            'controlled_by_id' => $control->controlled_by,
            
            // Tampilkan NAMA ASLI pengelolanya (Ambil dari relasi tabel users)
            'controlled_by_name' => $control->controllerUser ? $control->controllerUser->name : null,
            
            'locked_until' => $control->locked_until ? $control->locked_until->format('Y-m-d H:i:s') : null
        ]);
    }

    // =========================================================================
    // KELOMPOK 2: MENAMPILKAN HALAMAN WEB (VIEWS)
    // =========================================================================

    // 1. Halaman Utama (Summary Kondisi Terkini)
    public function index()
    {
        $latestData = SensorData::latest()->first(); 
        $recentLogs = SensorData::latest()->take(5)->get();

        $todayData = SensorData::whereDate('created_at', \Carbon\Carbon::today())->get();
        $dailyStats = [
            'avg_temp' => $todayData->count() > 0 ? round($todayData->avg('temp'), 1) : ($latestData ? $latestData->temp : 0),
            'avg_hum' => $todayData->count() > 0 ? round($todayData->avg('hum'), 1) : ($latestData ? $latestData->hum : 0),
            'max_ammonia' => $todayData->count() > 0 ? $todayData->max('ammonia') : ($latestData ? $latestData->ammonia : 0),
        ];

        // Ambil Data Siklus yang Sedang Berjalan
        $activeCycle = \App\Models\Cycle::where('status', 'berjalan')->first();

        // Hitung Akumulasi dan Rata-rata Performa dari Siklus yang Selesai
        $totalHarvest = \App\Models\Cycle::where('status', 'selesai')->sum('harvest_mass');
        $avgWri = \App\Models\Cycle::where('status', 'selesai')->avg('wri_result') ?? 0;
        $avgEci = \App\Models\Cycle::where('status', 'selesai')->avg('eci_result') ?? 0;

        return view('dashboard.index', compact('latestData', 'recentLogs', 'dailyStats', 'activeCycle', 'totalHarvest', 'avgWri', 'avgEci'));
    }

    // 2. Halaman e-Logbook (Riwayat Data Tabel)
     public function logbook(Request $request)
    {
        $query = SensorData::query();

        // 1. Logika Filter Tanggal
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Jika tidak ada filter tanggal, tampilkan default 1 hari terakhir (24 jam)
        if (!$request->filled('start_date') && !$request->filled('end_date')) {
            $query->where('created_at', '>=', now()->subDay());
        }


        // 2. Logika Pengurutan (Terbaru/Terlama)
        $sort = $request->query('sort', 'desc');
        if ($sort === 'asc') {
            $query->oldest();
        } else {
            $query->latest();
        }

        // 3. Logika Export ke Excel (CSV)
        if ($request->query('export') === 'excel') {
            // Ambil semua data yang sudah difilter tanpa Pagination
            $dataExport = $query->get();
            
            $filename = "Laporan_SiMaggot_" . date('Ymd_His') . ".csv";
            $headers = [
                "Content-type"        => "text/csv",
                "Content-Disposition" => "attachment; filename=$filename",
                "Pragma"              => "no-cache",
                "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
                "Expires"             => "0"
            ];
            
            // Kolom Header Excel
            $columns = ['Tanggal', 'Waktu', 'Suhu (C)', 'Kelembaban Udara (%)', 'Kelembaban Tanah (Avg %)', 'Amonia (ppm)', 'Total Massa Maggot (kg)'];
            
            $callback = function() use($dataExport, $columns) {
                $file = fopen('php://output', 'w');
                fputcsv($file, $columns);
                
                foreach ($dataExport as $row) {
                    $biopondArray = is_array($row->biopond) ? $row->biopond : json_decode($row->biopond, true) ?? [];
                    $totalBerat = array_sum($biopondArray) / 1000;
                    
                    $soilArray = is_array($row->soil) ? $row->soil : json_decode($row->soil, true) ?? [];
                    $avgSoil = count($soilArray) > 0 ? array_sum($soilArray) / count($soilArray) : 0;
                    
                    fputcsv($file, [
                        $row->created_at->format('d/m/Y'),
                        $row->created_at->format('H:i:s'),
                        $row->temp,
                        $row->hum,
                        number_format($avgSoil, 1, '.', ''),
                        $row->ammonia,
                        number_format($totalBerat, 2, '.', '')
                    ]);
                }
                fclose($file);
            };
            
            return response()->stream($callback, 200, $headers);
        }

        // 4. Tampilkan halaman web seperti biasa (dengan pagination)
        // Gunakan withQueryString() agar saat pindah halaman, filter tanggalnya tidak hilang
        $sensors = $query->paginate(20)->withQueryString(); 
        
        return view('logbook.index', compact('sensors'));
    }

    // 3. Halaman Kontrol Aktuator Jarak Jauh
    public function controlPanel()
    {
        // Ambil status terkini untuk ditampilkan di tombol web
        $control = DeviceControl::first();
        // Ambil data sensor terbaru untuk panduan manual override
        $latestData = SensorData::latest()->first(); 
        
        $isLockedByOthers = false;
        $lockMessage = "";

        // Cek apakah sistem sedang dikunci oleh orang lain
        if ($control->is_manual && $control->controlled_by && $control->locked_until && $control->locked_until->isFuture()) {
            // Jika yang mengunci BUKAN user yang sedang login saat ini
            if ($control->controlled_by !== auth()->id()) {
                $isLockedByOthers = true;
                $lockMessage = "Akses terkunci! Pengelola bernama <strong>" . $control->controllerUser->name . "</strong> sedang mengendalikan sistem ini secara manual hingga pukul " . $control->locked_until->format('H:i') . " WIB.";
            }
        }
        
        return view('control.index', compact('control', 'latestData', 'isLockedByOthers', 'lockMessage'));
    }

    // 4. Halaman Statistik & Analitik
    public function statistik()
    {
        $sensorHistory = SensorData::latest()->take(50)->get()->reverse()->values();

        $timestamps = $sensorHistory->pluck('created_at')->map(function($date) {
            return $date->format('H:i');
        });

        $tempData = $sensorHistory->pluck('temp');
        $humData = $sensorHistory->pluck('hum');
        $ammoniaData = $sensorHistory->pluck('ammonia');
        
        $totalMassData = $sensorHistory->map(function($item) {
            $biopond = is_array($item->biopond) ? $item->biopond : json_decode($item->biopond, true) ?? [];
            return array_sum($biopond) / 1000; 
        });

        $latestData = $sensorHistory->last();
        $latestBiopond = $latestData ? (is_array($latestData->biopond) ? $latestData->biopond : json_decode($latestData->biopond, true)) : [0,0,0,0,0,0];
        $latestSoil = $latestData ? (is_array($latestData->soil) ? $latestData->soil : json_decode($latestData->soil, true)) : [0,0,0,0,0,0];

        // Hitung Akumulasi dan Rata-rata Performa dari Siklus yang Selesai
        $totalHarvest = \App\Models\Cycle::where('status', 'selesai')->sum('harvest_mass');
        $avgWri = \App\Models\Cycle::where('status', 'selesai')->avg('wri_result') ?? 0;
        $avgEci = \App\Models\Cycle::where('status', 'selesai')->avg('eci_result') ?? 0;

        return view('statistik.index', compact(
            'timestamps', 'tempData', 'humData', 'ammoniaData', 'totalMassData', 
            'latestBiopond', 'latestSoil', 'totalHarvest', 'avgWri', 'avgEci'
        ));
    }

    // =========================================================================
    // KELOMPOK 3: AKSI DARI HALAMAN WEB (WEB ACTIONS & API FETCH)
    // =========================================================================

    // Method KHUSUS WEB untuk melayani Request AJAX (Filter Harian, Mingguan, Bulanan, Tahunan)
    public function getStatisticsData(Request $request)
    {
        $period = $request->query('period', 'daily');
        
        if ($period === 'daily') {
            // Harian: Dikelompokkan per JAM (misal: 08:00, 09:00)
            $sensors = SensorData::where('created_at', '>=', now()->subDay())
                ->orderBy('created_at', 'asc')->get()
                ->groupBy(function($date) { return \Carbon\Carbon::parse($date->created_at)->format('H:00'); });
        } elseif ($period === 'weekly') {
            // Mingguan: Dikelompokkan per HARI (misal: 12 Okt, 13 Okt)
            $sensors = SensorData::where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at', 'asc')->get()
                ->groupBy(function($date) { return \Carbon\Carbon::parse($date->created_at)->format('d M'); });
        } elseif ($period === 'monthly') {
            // Bulanan: Dikelompokkan per HARI (misal: 12 Okt, 13 Okt)
            $sensors = SensorData::where('created_at', '>=', now()->subDays(30))
                ->orderBy('created_at', 'asc')->get()
                ->groupBy(function($date) { return \Carbon\Carbon::parse($date->created_at)->format('d M'); });
        } else {
            // Tahunan: Dikelompokkan per BULAN (misal: Jan 2026, Feb 2026)
            $sensors = SensorData::where('created_at', '>=', now()->subYear())
                ->orderBy('created_at', 'asc')->get()
                ->groupBy(function($date) { return \Carbon\Carbon::parse($date->created_at)->format('M Y'); });
        }

        // Siapkan penampung data
        $timestamps = []; $tempData = []; $humData = []; 
        $ammoniaData = []; $totalMassData = [];

        // Hitung nilai RATA-RATA untuk setiap kelompok waktu (Jam / Hari)
        foreach ($sensors as $timeLabel => $dataGroup) {
            $timestamps[] = $timeLabel;
            $tempData[] = round($dataGroup->avg('temp'), 1);
            $humData[] = round($dataGroup->avg('hum'), 1);
            $ammoniaData[] = round($dataGroup->avg('ammonia'), 1);
            
            // Hitung rata-rata total massa di hari/jam tersebut
            $avgMass = $dataGroup->map(function($item) {
                $biopond = is_array($item->biopond) ? $item->biopond : json_decode($item->biopond, true) ?? [];
                return array_sum($biopond) / 1000;
            })->avg();
            
            $totalMassData[] = round($avgMass, 2);
        }

        return response()->json([
            'timestamps' => $timestamps,
            'tempData' => $tempData,
            'humData' => $humData,
            'ammoniaData' => $ammoniaData,
            'totalMassData' => $totalMassData,
        ]);
    }

    // Method KHUSUS WEB untuk menerima klik tombol dan menyimpannya ke DB
    public function updateControl(Request $request)
    {
        $control = DeviceControl::first();
        $currentUser = auth()->id();
        $now = now();
        $isUpdated = false; // Flag untuk mengecek apakah benar-benar ada data yang diproses

        // =========================================================================
        // VALIDASI PROTEKSI CONCURRENCY LOCKING
        // =========================================================================
        // Cek apakah sistem dalam mode manual dan sedang dikunci oleh orang lain
        if ($control->is_manual && $control->controlled_by && $control->locked_until && $control->locked_until->isFuture()) {
            if ($control->controlled_by !== $currentUser) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Gagal! Sistem sedang dikunci dan dikendalikan secara manual oleh pengelola lain.'
                ], 403);
            }
        }

        // =========================================================================
        // PROCESS REQUEST DATA
        // =========================================================================
        
        // 1. Tangkap perubahan Mode (Manual/Auto)
        if ($request->has('is_manual')) {
            $control->is_manual = $request->boolean('is_manual');
            $isUpdated = true;
            
            if ($control->is_manual) {
                // Jika diubah ke MANUAL, langsung kunci hak akses untuk user ini selama 5 menit
                $control->controlled_by = $currentUser;
                $control->locked_until = $now->addMinutes(5);
            } else {
                // Jika dikembalikan ke OTOMATIS, lepas kunci dari database
                $control->controlled_by = null;
                $control->locked_until = null;
            }
        }

        // 2. Pembaruan Atribut Aktuator (Hanya diproses jika sistem dalam Mode MANUAL)
        if ($control->is_manual) {
            
            // Otomatis perpanjang masa berlaku kunci (+5 menit dari aksi terakhir)
            $control->controlled_by = $currentUser;
            $control->locked_until = $now->addMinutes(5);

            // Jika user menggeser/menekan tingkat kecepatan kipas
            if ($request->has('fan')) {
                $control->fan = (int) $request->fan;
                $isUpdated = true;
            }

            // --- LOGIKA MIST MAKER ---
            // Skenario A: Jika menerima satu array penuh (Bagus untuk test Postman / sinkronisasi massal)
            if ($request->has('mist') && is_array($request->mist)) {
                $control->mist = $request->mist;
                $isUpdated = true;
            } 
            // Skenario B: Jika menerima update satuan dari tombol Web (mist_index & mist_value)
            elseif ($request->has('mist_index') && $request->has('mist_value')) {
                $mistArray = is_array($control->mist) ? $control->mist : json_decode($control->mist, true);
                $mistArray[$request->mist_index] = (int) $request->mist_value;
                $control->mist = $mistArray;
                $isUpdated = true;
            }
        }

        // =========================================================================
        // SAVE & RESPONSE INTERACTION
        // =========================================================================
        if ($isUpdated) {
            $control->save();
            return response()->json([
                'status' => 'success',
                'message' => 'Status berhasil diupdate!',
                'locked_until' => $control->locked_until ? $control->locked_until->format('H:i') : null,
                'data_tersimpan' => [
                    'is_manual' => $control->is_manual,
                    'fan' => $control->fan,
                    'mist' => $control->mist,
                    'controlled_by' => $control->controlled_by
                ]
            ]);
        }

        // Jika tidak ada key data yang cocok atau mencoba merubah aktuator saat mode otomatis aktif
        return response()->json([
            'status' => 'error',
            'message' => 'Tidak ada data valid yang diproses. Pastikan key JSON sesuai dan sistem berada dalam mode MANUAL.'
        ], 400);
    }

    // Method Global Alert (AJAX Polling)
    public function checkAlerts()
    {
        $alerts = [];
        
        // 1. Cek Peringatan Sensor (Kondisi Real-time)
        $latestData = \App\Models\SensorData::latest()->first();
        if ($latestData) {
            $timeStr = $latestData->created_at->format('H:i'); // Ambil waktu sensor

            // --- A. PERINGATAN SUHU UDARA ---
            if ($latestData->temp > config('maggot.thresholds.temp.max_safe')) {
                $alerts[] = [
                    'id' => 'temp_danger_' . $latestData->id,
                    'type' => 'danger',
                    'title' => 'Bahaya Suhu Kritis!',
                    'message' => "Suhu mencapai {$latestData->temp}°C (Batas fatal >35°C). Segera nyalakan kipas exhaust secara penuh!",
                    'time' => $timeStr
                ];
            } elseif ($latestData->temp > config('maggot.thresholds.temp.max_ideal')) {
                $alerts[] = [
                    'id' => 'temp_warn_high_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Peringatan Suhu Tinggi',
                    'message' => "Suhu saat ini {$latestData->temp}°C (Ideal 24-30°C). Masih stabil namun pantau ventilasi udara.",
                    'time' => $timeStr
                ];
            } elseif ($latestData->temp < config('maggot.thresholds.temp.min_ideal')) {
                $alerts[] = [
                    'id' => 'temp_warn_low_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Suhu Terlalu Rendah',
                    'message' => "Suhu udara turun ke {$latestData->temp}°C. Aktivitas dan metabolisme larva berpotensi menurun.",
                    'time' => $timeStr
                ];
            }

            // --- B. PERINGATAN KELEMBAPAN UDARA (RH) ---
            if ($latestData->hum > config('maggot.thresholds.hum.max_ideal')) {
                $alerts[] = [
                    'id' => 'hum_high_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Udara Terlalu Lembap',
                    'message' => "Kelembapan udara mencapai {$latestData->hum}% (Batas ideal <80%). Sirkulasi udara perlu ditingkatkan.",
                    'time' => $timeStr
                ];
            } elseif ($latestData->hum < config('maggot.thresholds.hum.min_ideal')) {
                $alerts[] = [
                    'id' => 'hum_low_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Udara Terlalu Kering',
                    'message' => "Kelembapan udara hanya {$latestData->hum}% (Batas ideal >60%). Pertimbangkan menyalakan mist maker.",
                    'time' => $timeStr
                ];
            }

            // --- C. PERINGATAN KELEMBAPAN MEDIA ---
            $soilArray = is_array($latestData->soil) ? $latestData->soil : json_decode($latestData->soil, true) ?? [];
            if (count($soilArray) > 0) {
                // Hitung rata-rata kelembapan media dari seluruh rak
                $avgSoil = array_sum($soilArray) / count($soilArray);
                $avgSoilFormat = number_format($avgSoil, 1);

                if ($avgSoil > config('maggot.thresholds.soil.max_safe')) {
                    $alerts[] = [
                        'id' => 'soil_danger_' . $latestData->id,
                        'type' => 'danger',
                        'title' => 'Media Terlalu Basah!',
                        'message' => "Kelembapan media rata-rata {$avgSoilFormat}% (Batas >90%). Berisiko menghambat pertumbuhan larva.",
                        'time' => $timeStr
                    ];
                } elseif ($avgSoil < config('maggot.thresholds.soil.min_safe')) {
                    $alerts[] = [
                        'id' => 'soil_warn_' . $latestData->id,
                        'type' => 'warning',
                        'title' => 'Media Terlalu Kering',
                        'message' => "Kelembapan media rata-rata {$avgSoilFormat}% (Batas aman >60%). Tambahkan pakan basah atau air.",
                        'time' => $timeStr
                    ];
                }
            }

            // --- D. PERINGATAN AMONIA ---
            if ($latestData->ammonia > config('maggot.thresholds.ammonia.max_safe')) {
                $alerts[] = [
                    'id' => 'nh3_danger_' . $latestData->id,
                    'type' => 'danger',
                    'title' => 'Bahaya Amonia Beracun!',
                    'message' => "Kadar amonia mencapai {$latestData->ammonia} PPM (Batas aman ≤20 PPM). Lingkungan beracun bagi maggot!",
                    'time' => $timeStr
                ];
            }
        }

        // 2. Cek Pengingat Siklus (Operasional)
        $activeCycle = \App\Models\Cycle::where('status', 'berjalan')->first();
        if ($activeCycle) {
            $days = $activeCycle->days_elapsed;
            
            // Reminder Panen (Target Hari ke-21)
            if ($days >= 21) {
                $alerts[] = [
                    'id' => 'panen_' . $activeCycle->id . '_' . $days,
                    'type' => 'warning',
                    'title' => 'Waktunya Panen!',
                    'message' => "Siklus {$activeCycle->batch_id} sudah mencapai hari ke-{$days}. Harap segera selesaikan siklus.",
                    'time' => 'Hari ini'
                ];
            } 
            // Reminder Pakan (Asumsi jadwal pakan setiap kelipatan 3 hari)
            elseif ($days > 0 && $days % 3 == 0) {
                $alerts[] = [
                    'id' => 'pakan_' . $activeCycle->id . '_' . $days,
                    'type' => 'info',
                    'title' => 'Jadwal Pakan Tiba',
                    'message' => "Hari ke-{$days} pada siklus aktif. Waktunya menambahkan sampah organik baru ke rak.",
                    'time' => 'Hari ini'
                ];
            }
        }

        return response()->json(['alerts' => $alerts]);
    }
}