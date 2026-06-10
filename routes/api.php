<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SensorDataController;
use App\Http\Middleware\CheckIotApiKey; // Panggil middleware pengaman API

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


// =========================================================================
// JALUR API TERPROTEKSI: WAJIB MENYERTAKAN X-API-KEY DI HTTP HEADER
// =========================================================================
Route::middleware([CheckIotApiKey::class])->group(function () {
    
    // Menerima data sensor dari ESP32 (POST)
    Route::post('/sensor', [SensorDataController::class, 'store']);
    
    // Mengirimkan status kontrol terbaru ke ESP32 (GET)
    Route::get('/control', [SensorDataController::class, 'getControl']);
    
    // Mengubah data kontrol aktuator dari ESP32 / Postman (POST)
    Route::post('/control', [SensorDataController::class, 'updateControl']);
    
});

// =========================================================================
// JALUR API PUBLIK: TIDAK PERLU API KEY
// =========================================================================
// Jalur statistik data untuk grafik Chart.js di frontend web
Route::get('/statistik-data', [SensorDataController::class, 'getStatisticsData']);