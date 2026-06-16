<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SensorDataController;
use App\Http\Controllers\CycleController;
use App\Http\Middleware\CheckIotApiKey;
// Tambahkan untuk manajemen akun
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

// Rute Publik (Bisa diakses siapa saja tanpa login)
Route::get('/', [SensorDataController::class, 'index'])->name('dashboard');
Route::redirect('/dashboard', '/');
Route::get('/statistik', [SensorDataController::class, 'statistik']);
Route::get('/logbook', [SensorDataController::class, 'logbook']);

// Rute Terproteksi (Hanya bisa diakses oleh Pengelola TPST yang sudah Login)
Route::middleware('auth')->group(function () {
    // Kontrol Aktuator
    Route::get('/control', [SensorDataController::class, 'controlPanel']);
    Route::post('/web-control', [SensorDataController::class, 'updateControl']);

    // Manajemen Siklus
    Route::get('/cycle', [CycleController::class, 'index']);
    Route::post('/cycle/start', [CycleController::class, 'store']);
    Route::post('/cycle/add-waste', [CycleController::class, 'addWaste'])->name('cycle.addWaste');
    Route::post('/cycle/finish', [CycleController::class, 'finish']);

    // Rute Global Alert AJAX
    Route::get('/api/check-alerts', [SensorDataController::class, 'checkAlerts']);

    Route::post('/sensor/force-update', [\App\Http\Controllers\SensorDataController::class, 'triggerForceUpdate']);
    Route::get('/sensor/latest-json', [\App\Http\Controllers\SensorDataController::class, 'getLatestJson']);
});




// URL RAHASIA MANAJEMEN AKUN (Akses ala Router / Access Point)
Route::prefix('configuration-panel')->group(function () {
    
    // 1. Halaman Utama (Menampilkan Form PIN atau Tabel CRUD)
    Route::get('/', function () {
        if (!session('admin_unlocked')) {
            return view('akun.index'); 
        }
        $users = App\Models\User::all();
        return view('akun.index', compact('users'));
    });

    // 2. Proses Buka Kunci (Submit PIN)
    Route::post('/unlock', function (Illuminate\Http\Request $request) {
        $pinRahasia = config('maggot.admin_pin'); // <-- GANTI PIN RAHASIAMU DI SINI
        
        if ($request->pin === $pinRahasia) {
            session(['admin_unlocked' => true]);
            return back()->with('success', 'Akses Konfigurasi Terbuka!');
        }
        return back()->with('error', 'PIN Tidak Valid!');
    });

    // 3. Proses Kunci Kembali
    Route::post('/lock', function () {
        session()->forget('admin_unlocked');
        return back();
    });

    // ==========================================
    // AREA CRUD (HANYA BISA DIAKSES JIKA UNLOCKED)
    // ==========================================
    Route::post('/store', function (Illuminate\Http\Request $request) {
        if (!session('admin_unlocked')) abort(403);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        App\Models\User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Illuminate\Support\Facades\Hash::make($request->password),
        ]);

        return back()->with('success', 'Akun Pengelola baru berhasil dibuat!');
    });

    Route::post('/update/{id}', function (Illuminate\Http\Request $request, $id) {
        if (!session('admin_unlocked')) abort(403);
        
        $user = App\Models\User::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        if ($request->filled('password')) {
            $user->password = Illuminate\Support\Facades\Hash::make($request->password);
        }
        $user->save();

        return back()->with('success', 'Data akun berhasil diperbarui!');
    });

    Route::delete('/destroy/{id}', function ($id) {
        if (!session('admin_unlocked')) abort(403);
        
        App\Models\User::findOrFail($id)->delete();
        return back()->with('success', 'Akun berhasil dihapus!');
    });

    Route::post('/force-unlock', function () {
    if (!session('admin_unlocked')) abort(403);
    
    $control = \App\Models\DeviceControl::first();
    $control->update([
        'is_manual' => false,
        'controlled_by' => null,
        'locked_until' => null
    ]);
    
    return back()->with('success', 'Sistem telah dibuka paksa ke mode OTOMATIS!');
    });
});

require __DIR__.'/auth.php'; // Ini bawaan Laravel Breeze untuk route login/logout
