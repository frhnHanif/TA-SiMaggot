<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cycle;
use App\Models\SensorData;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    /**
     * Halaman form generate laporan dengan date picker.
     */
    public function index()
    {
        return view('report.index');
    }

    /**
     * Generate PDF Laporan Kinerja Biokonversi.
     */
    public function generate(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = Carbon::parse($request->start_date)->startOfDay();
        $endDate   = Carbon::parse($request->end_date)->endOfDay();

        // ─── AMBIL DATA ───────────────────────────────────────────
        $cycles = Cycle::where('status', 'selesai')
            ->whereBetween('end_date', [$startDate, $endDate])
            ->orderBy('end_date', 'asc')
            ->get();

        $sensorData = SensorData::whereBetween('created_at', [$startDate, $endDate])->get();

        // ─── RINGKASAN ────────────────────────────────────────────
        // total_waste_input & harvest_mass & residue_mass dalam Gram di DB
        $totalWasteInputGr  = $cycles->sum('total_waste_input');
        $totalHarvestGr     = $cycles->sum('harvest_mass');
        $totalResidueGr     = $cycles->sum('residue_mass');

        $totalWasteInputTon = round($totalWasteInputGr / 1_000_000, 3); // gram → ton
        $totalHarvestKg     = round($totalHarvestGr / 1_000, 1);
        $totalResidueKg     = round($totalResidueGr / 1_000, 1);
        $totalBatch         = $cycles->count();
        $avgWri             = $cycles->avg('wri_result') ?? 0;
        $avgEci             = $cycles->avg('eci_result') ?? 0;

        // ─── REKAPITULASI PER BULAN ───────────────────────────────
        $monthlyRecap = $cycles->groupBy(function ($c) {
            return Carbon::parse($c->end_date)->format('Y-m');
        })->map(function ($group) {
            return [
                'waste_input_kg' => round($group->sum('total_waste_input') / 1000, 1),
                'harvest_kg'     => round($group->sum('harvest_mass') / 1000, 1),
                'residue_kg'     => round($group->sum('residue_mass') / 1000, 1),
            ];
        })->sortKeys();

        // ─── MONITORING IOT ───────────────────────────────────────
        $iotSummary = [
            'temp_min'    => $sensorData->count() ? round($sensorData->min('temp'), 1) : '-',
            'temp_max'    => $sensorData->count() ? round($sensorData->max('temp'), 1) : '-',
            'temp_avg'    => $sensorData->count() ? round($sensorData->avg('temp'), 1) : '-',
            'hum_min'     => $sensorData->count() ? round($sensorData->min('hum'), 1) : '-',
            'hum_max'     => $sensorData->count() ? round($sensorData->max('hum'), 1) : '-',
            'hum_avg'     => $sensorData->count() ? round($sensorData->avg('hum'), 1) : '-',
            'soil_min'    => $sensorData->count() ? $this->avgSoilMin($sensorData) : '-',
            'soil_max'    => $sensorData->count() ? $this->avgSoilMax($sensorData) : '-',
            'soil_avg'    => $sensorData->count() ? $this->avgSoilOverall($sensorData) : '-',
            'ammonia_min' => $sensorData->count() ? round($sensorData->min('ammonia'), 1) : '-',
            'ammonia_max' => $sensorData->count() ? round($sensorData->max('ammonia'), 1) : '-',
            'ammonia_avg' => $sensorData->count() ? round($sensorData->avg('ammonia'), 1) : '-',
        ];

        // ─── RIWAYAT SIKLUS (maks 20) ─────────────────────────────
        $cycleHistory = $cycles->sortByDesc('end_date')->take(20);

        // ─── DATA VIEW ────────────────────────────────────────────
        $data = [
            'periode'            => $startDate->translatedFormat('j F Y') . ' – ' . $endDate->translatedFormat('j F Y'),
            'generated_at'       => now()->translatedFormat('j F Y, H:i') . ' WIB',
            'public_url'         => rtrim(config('app.url'), '/'),
            'totalWasteInputTon' => $totalWasteInputTon,
            'totalHarvestKg'     => $totalHarvestKg,
            'totalResidueKg'     => $totalResidueKg,
            'totalBatch'         => $totalBatch,
            'avgWri'             => round($avgWri, 2),
            'avgEci'             => round($avgEci, 2),
            'monthlyRecap'       => $monthlyRecap,
            'iotSummary'         => $iotSummary,
            'cycleHistory'       => $cycleHistory,
            'cycleHistoryCount'  => $cycles->count(),
        ];

        // ─── GENERATE PDF ─────────────────────────────────────────
        $pdf = Pdf::loadView('report.pdf', $data);
        $pdf->setPaper('A4', 'portrait');

        $filename = 'Laporan-Kinerja-Biokonversi-SiMaggot-'
            . $startDate->format('Ymd') . '-'
            . $endDate->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    // ─── HELPER UNTUK SOIL (JSON array 6 rak) ─────────────────────
    private function avgSoilMin($sensorData)
    {
        $mins = [];
        foreach ($sensorData as $s) {
            $soil = is_array($s->soil) ? $s->soil : json_decode($s->soil, true) ?? [];
            if (!empty($soil)) $mins[] = min($soil);
        }
        return !empty($mins) ? round(array_sum($mins) / count($mins), 1) : '-';
    }

    private function avgSoilMax($sensorData)
    {
        $maxs = [];
        foreach ($sensorData as $s) {
            $soil = is_array($s->soil) ? $s->soil : json_decode($s->soil, true) ?? [];
            if (!empty($soil)) $maxs[] = max($soil);
        }
        return !empty($maxs) ? round(array_sum($maxs) / count($maxs), 1) : '-';
    }

    private function avgSoilOverall($sensorData)
    {
        $all = [];
        foreach ($sensorData as $s) {
            $soil = is_array($s->soil) ? $s->soil : json_decode($s->soil, true) ?? [];
            $all = array_merge($all, $soil);
        }
        return !empty($all) ? round(array_sum($all) / count($all), 1) : '-';
    }
}
