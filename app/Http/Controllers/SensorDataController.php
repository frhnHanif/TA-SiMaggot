<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SensorData;
use App\Models\DeviceControl;
use App\Models\Cycle;
use App\Exports\SensorDataExport;

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

        // 2. Reset Force Update
        $control = DeviceControl::first();
        if ($control && $control->force_sensor_update) {
            $control->update(['force_sensor_update' => false]);
        }

        return response()->json(['message' => 'Data berhasil disimpan', 'data' => $data], 201);
    }

    // Mengirimkan status kontrol terbaru ke ESP32 (GET)
   // Mengirimkan status kontrol terbaru ke ESP32 atau Postman (GET)
    public function getControl()
    {
        // Gunakan with() untuk memuat relasi data User sekaligus (Eager Loading)
        $control = DeviceControl::with('controllerUser')->first();

        // Catat timestamp "last ping" sebagai indikator ESP32 online
        if ($control) {
            $control->update(['last_ping_at' => now()]);
        }

        // Panggil evaluasi timer dan fail-safe SEBELUM mengirim ke ESP32
        $control = $this->evaluateSystemState($control);
        
        return response()->json([
            'is_manual' => $control->is_manual,
            'force_sensor_update' => (bool) $control->force_sensor_update,
            'fan' => $control->fan,
            'mist' => $control->mist,
            'mist_stop_at' => $control->mist_stop_at,
            
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

        // Hitung Akumulasi dan Rata-rata Performa dari Siklus yang Selesai (12 bulan terakhir)
        $finishedLastYear = \App\Models\Cycle::where('status', 'selesai')
            ->where('end_date', '>=', now()->subYear());

        $totalHarvest = $finishedLastYear->sum('harvest_mass');
        $totalWasteInput = $finishedLastYear->sum('total_waste_input');
        $totalResidue = $finishedLastYear->sum('residue_mass');
        $avgWri = $finishedLastYear->avg('wri_result') ?? 0;
        $avgEci = $finishedLastYear->avg('eci_result') ?? 0;

        return view('dashboard.index', compact('latestData', 'recentLogs', 'dailyStats', 'activeCycle', 'totalHarvest', 'totalWasteInput', 'totalResidue', 'avgWri', 'avgEci'));
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

        // 2. Logika Pengurutan (Terbaru/Terlama)
        $sort = $request->query('sort', 'desc');
        if ($sort === 'asc') {
            $query->oldest();
        } else {
            $query->latest();
        }

        // 3. Logika Export ke Excel (format .xlsx rapi) — maks 30 hari
        if ($request->query('export') === 'excel') {
            $startDate = $request->query('start_date');
            $endDate   = $request->query('end_date');

            // Jika tidak ada filter, default ke 30 hari terakhir
            if (!$startDate && !$endDate) {
                $endDate   = now()->format('Y-m-d');
                $startDate = now()->subDays(29)->format('Y-m-d');
            } elseif ($startDate && $endDate) {
                $diffDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate));
                if ($diffDays > 30) {
                    return back()->with('error', '⚠️ Export Excel dibatasi maksimal 30 hari. Silakan persempit rentang tanggal.');
                }
            } elseif ($startDate && !$endDate) {
                // Hanya start_date: batasi sampai start_date + 30 hari atau hari ini
                $endDate = now()->format('Y-m-d');
                $diffDays = \Carbon\Carbon::parse($startDate)->diffInDays(\Carbon\Carbon::parse($endDate));
                if ($diffDays > 30) {
                    return back()->with('error', '⚠️ Export Excel dibatasi maksimal 30 hari. Silakan tentukan tanggal akhir yang lebih dekat.');
                }
            } elseif (!$startDate && $endDate) {
                $startDate = \Carbon\Carbon::parse($endDate)->subDays(29)->format('Y-m-d');
            }

            // Terapkan filter tanggal ke query
            if ($startDate) {
                $query->whereDate('created_at', '>=', $startDate);
            }
            if ($endDate) {
                $query->whereDate('created_at', '<=', $endDate);
            }

            $dataExport = $query->get();
            return (new SensorDataExport(
                $dataExport,
                $startDate,
                $endDate
            ))->download();
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

        // Evaluasi state saat web direfresh
        $control = $this->evaluateSystemState($control);

        // --- DETEKSI STATUS ONLINE/OFFLINE ESP32 ---
        $isOffline = false;
        $offlineSeconds = 0;
        if (!$control->last_ping_at) {
            // Belum pernah ping sama sekali
            $isOffline = true;
        } else {
            $offlineSeconds = (int) now()->diffInSeconds($control->last_ping_at, true);
            if ($offlineSeconds > 30) {
                $isOffline = true;
            }
        }

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

        // --- HITUNG SISA WAKTU (COUNTDOWN) UNTUK DIKIRIM KE JAVASCRIPT ---
        $mistStopAt = is_array($control->mist_stop_at) ? $control->mist_stop_at : json_decode($control->mist_stop_at, true) ?? [null,null,null,null,null,null];
        $mistRemaining = [];
        $now = now();
        foreach($mistStopAt as $stop) {
            if ($stop) {
                $stopTime = \Carbon\Carbon::parse($stop);
                $diff = $now->diffInSeconds($stopTime, false); 
                $mistRemaining[] = $diff > 0 ? $diff : 0;
            } else {
                $mistRemaining[] = 0;
            }
        }
        
        return view('control.index', compact('control', 'latestData', 'isLockedByOthers', 'lockMessage', 'mistRemaining', 'isOffline', 'offlineSeconds'));
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

        // KPI: 12 bulan terakhir (periode pelaporan UI GreenMetric)
        $finishedLastYear = \App\Models\Cycle::where('status', 'selesai')
            ->where('end_date', '>=', now()->subYear());

        $totalHarvest = $finishedLastYear->sum('harvest_mass');
        $totalWasteInput = $finishedLastYear->sum('total_waste_input');
        $totalResidue = $finishedLastYear->sum('residue_mass');
        $avgWri = $finishedLastYear->avg('wri_result') ?? 0;
        $avgEci = $finishedLastYear->avg('eci_result') ?? 0;

        // Data awal grafik Tren Pengolahan Sampah (default: 12 bulan terakhir per bulan)
        $wasteTrend = \App\Models\Cycle::where('status', 'selesai')
            ->where('start_date', '>=', now()->subYear())
            ->selectRaw("DATE_FORMAT(start_date, '%b %Y') as label, SUM(total_waste_input) as total")
            ->groupBy('label')
            ->orderByRaw('MIN(start_date) asc')
            ->get();

        $wasteTrendLabels = $wasteTrend->pluck('label');
        $wasteTrendData = $wasteTrend->pluck('total')->map(fn($v) => round((float) $v, 2));

        return view('statistik.index', compact(
            'timestamps', 'tempData', 'humData', 'ammoniaData', 'totalMassData', 
            'totalHarvest', 'totalWasteInput', 'totalResidue', 'avgWri', 'avgEci',
            'wasteTrendLabels', 'wasteTrendData'
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
            'wasteTrendLabels' => $this->getWasteTrend('yearly', 'labels'),
            'wasteTrendData' => $this->getWasteTrend('yearly', 'data'),
        ]);
    }

    /**
     * Ambil data tren pengolahan sampah dari tabel cycles sesuai periode filter.
     */
    private function getWasteTrend(string $period, string $field)
    {
        $query = Cycle::where('status', 'selesai');

        if ($period === 'yearly') {
            $query->where('start_date', '>=', now()->subYear());
            $groups = $query->selectRaw("DATE_FORMAT(start_date, '%b %Y') as label, SUM(total_waste_input) as total")
                ->groupBy('label')->orderByRaw('MIN(start_date) asc')->get();
        } elseif ($period === 'monthly') {
            $query->where('start_date', '>=', now()->subDays(30));
            $groups = $query->selectRaw("DATE_FORMAT(start_date, '%d %b') as label, SUM(total_waste_input) as total")
                ->groupBy('label')->orderByRaw('MIN(start_date) asc')->get();
        } elseif ($period === 'weekly') {
            $query->where('start_date', '>=', now()->subDays(7));
            $groups = $query->selectRaw("DATE_FORMAT(start_date, '%d %b') as label, SUM(total_waste_input) as total")
                ->groupBy('label')->orderByRaw('MIN(start_date) asc')->get();
        } else {
            // daily: 24 jam terakhir — fallback ke per hari
            $query->where('start_date', '>=', now()->subDay());
            $groups = $query->selectRaw("DATE_FORMAT(start_date, '%d %b') as label, SUM(total_waste_input) as total")
                ->groupBy('label')->orderByRaw('MIN(start_date) asc')->get();
        }

        if ($field === 'labels') {
            return $groups->pluck('label');
        }
        return $groups->pluck('total')->map(fn($v) => round((float) $v, 2));
    }

    // Method KHUSUS WEB untuk menerima klik tombol dan menyimpannya ke DB
    public function updateControl(Request $request)
    {
        $control = DeviceControl::first();
        $control = $this->evaluateSystemState($control);
        $currentUser = auth()->id();
        $now = now();
        $isUpdated = false; // Flag untuk mengecek apakah benar-benar ada data yang diproses

        // =========================================================================
        // VALIDASI STATUS ONLINE/OFFLINE ESP32
        // =========================================================================
        // Cek apakah ESP32 (Smart Vertical Biopond) sedang offline
        $isOffline = false;
        if (!$control->last_ping_at) {
            $isOffline = true;
        } else {
            $offlineSeconds = (int) $now->diffInSeconds($control->last_ping_at, true);
            if ($offlineSeconds > 30) {
                $isOffline = true;
            }
        }

        // Jika ESP32 offline, tolak SEMUA permintaan kontrol manual
        if ($isOffline && $request->has('is_manual') && $request->boolean('is_manual')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Smart Vertical Biopond sedang offline. Tidak dapat mengaktifkan mode manual. Periksa koneksi ESP32.'
            ], 503);
        }

        // Jika ESP32 offline, tolak perubahan aktuator juga
        if ($isOffline && ($request->has('fan') || $request->has('mist') || $request->has('mist_index'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Smart Vertical Biopond sedang offline. Kontrol manual tidak tersedia.'
            ], 503);
        }

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
                $mistArray = is_array($control->mist) ? $control->mist : json_decode($control->mist, true) ?? [0,0,0,0,0,0];
                $mistStopArray = is_array($control->mist_stop_at) ? $control->mist_stop_at : json_decode($control->mist_stop_at, true) ?? [null,null,null,null,null,null];
                
                $idx = $request->mist_index;
                $val = (int) $request->mist_value;
                
                $mistArray[$idx] = $val;
                
                // Set Timer jika Mist dinyalakan (10) dan membawa parameter duration
                if ($val === 10 && $request->has('duration')) {
                    $mistStopArray[$idx] = now()->addSeconds((int) $request->duration)->toDateTimeString();
                } else {
                    // Jika mist dimatikan manual (0), hilangkan timernya
                    $mistStopArray[$idx] = null;
                }
                
                $control->mist = $mistArray;
                $control->mist_stop_at = $mistStopArray;
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
                    'message' => "Suhu mencapai {$latestData->temp}&deg;C (Batas fatal >35&deg;C). Segera nyalakan kipas exhaust secara penuh!",
                    'time' => $timeStr
                ];
            } elseif ($latestData->temp > config('maggot.thresholds.temp.max_ideal')) {
                $alerts[] = [
                    'id' => 'temp_warn_high_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Peringatan Suhu Tinggi',
                    'message' => "Suhu saat ini {$latestData->temp}&deg;C (Ideal 24-30&deg;C). Masih stabil namun pantau ventilasi udara.",
                    'time' => $timeStr
                ];
            } elseif ($latestData->temp < config('maggot.thresholds.temp.min_ideal')) {
                $alerts[] = [
                    'id' => 'temp_warn_low_' . $latestData->id,
                    'type' => 'warning',
                    'title' => 'Suhu Terlalu Rendah',
                    'message' => "Suhu udara turun ke {$latestData->temp}&deg;C. Aktivitas dan metabolisme larva berpotensi menurun.",
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
    
    
    // =========================================================================
    // FUNGSI PRIVATE: PENGECEKAN TIMER & FAIL-SAFE (LAZY EVALUATION)
    // =========================================================================
    private function evaluateSystemState($control)
    {
        $now = now();
        $isChanged = false;

        // 1. FAIL-SAFE: Jika lock manual habis (5 menit tidak ada aktivitas), KEMBALIKAN KE OTOMATIS
        if ($control->is_manual && $control->locked_until && $control->locked_until->isPast()) {
            $control->is_manual = false;
            $control->controlled_by = null;
            $control->locked_until = null;
            $control->mist = [0,0,0,0,0,0];
            $control->mist_stop_at = [null,null,null,null,null,null];
            $isChanged = true;
        }

        // 2. MIST MAKER TIMER: Cek jika ada timer yang sudah melewati waktu sekarang
        if ($control->is_manual) {
            $mist = is_array($control->mist) ? $control->mist : json_decode($control->mist, true) ?? [0,0,0,0,0,0];
            $mistStop = is_array($control->mist_stop_at) ? $control->mist_stop_at : json_decode($control->mist_stop_at, true) ?? [null,null,null,null,null,null];

            for ($i = 0; $i < count($mist); $i++) {
                if ($mist[$i] == 10 && $mistStop[$i]) {
                    $stopTime = \Carbon\Carbon::parse($mistStop[$i]);
                    // Jika waktu sekarang melebihi atau sama dengan waktu stop
                    if ($now->greaterThanOrEqualTo($stopTime)) {
                        $mist[$i] = 0; // Matikan mist
                        $mistStop[$i] = null; // Reset timer
                        $isChanged = true;
                    }
                }
            }

            if ($isChanged) {
                $control->mist = $mist;
                $control->mist_stop_at = $mistStop;
            }
        }

        // Simpan hanya jika ada perubahan (menghindari query tidak perlu)
        if ($isChanged) {
            $control->save();
        }

        return $control;
    }

    // Memicu sinyal tarik data ke ESP32
    public function triggerForceUpdate() {
        $control = DeviceControl::first();
        $control->update(['force_sensor_update' => true]);
        return response()->json(['status' => 'success', 'message' => 'Sinyal dikirim ke ESP32']);
    }

    // Mengambil data terakhir untuk AJAX Web
    public function getLatestJson() {
        $latest = SensorData::latest()->first();
        return response()->json([
            'created_at' => $latest->created_at->format('Y-m-d H:i:s'),
            'biopond' => is_array($latest->biopond) ? $latest->biopond : json_decode($latest->biopond, true)
        ]);
    }
}