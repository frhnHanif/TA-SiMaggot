<?php

namespace Tests\Unit;

use Tests\TestCase;
use Carbon\Carbon;

/**
 * WHITEBOX UNIT TEST — Kalkulasi Murni & Logika Threshold
 * 
 * Menguji semua logika perhitungan bisnis secara terisolasi (tanpa database).
 * Mencakup: ECI, WRI, ADD, ADD Progress, Days Elapsed, Total Mass,
 *           Soil Average, Batch ID, Threshold Alerts.
 */
class WhiteboxCalculationTest extends TestCase
{
    // =========================================================================
    // A1. ECI — Efficiency Conversion Index
    // Rumus: (panen / input) * 100
    // =========================================================================

    private function calculateEci(float $harvestMass, float $inputMass): float
    {
        return ($inputMass > 0) ? ($harvestMass / $inputMass) * 100 : 0;
    }

    public function test_eci_normal_input(): void
    {
        $this->assertEquals(50.0, $this->calculateEci(5000, 10000));
    }

    public function test_eci_harvest_exceeds_input(): void
    {
        $this->assertEquals(120.0, $this->calculateEci(12000, 10000));
    }

    public function test_eci_zero_input_returns_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateEci(5000, 0));
    }

    public function test_eci_zero_harvest(): void
    {
        $this->assertEquals(0.0, $this->calculateEci(0, 10000));
    }

    public function test_eci_both_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateEci(0, 0));
    }

    public function test_eci_exact_equal(): void
    {
        $this->assertEquals(100.0, $this->calculateEci(7500, 7500));
    }

    public function test_eci_decimal_small(): void
    {
        $this->assertEquals(2.505, $this->calculateEci(250.5, 10000));
    }

    // =========================================================================
    // A2. WRI — Waste Reduction Index
    // Rumus: ((input - kasgot) / input) / hari * 100
    // =========================================================================

    private function calculateWri(float $inputMass, float $residueMass, int $days): float
    {
        return ($inputMass > 0 && $days > 0)
            ? ((($inputMass - $residueMass) / $inputMass) / $days) * 100
            : 0;
    }

    public function test_wri_normal_21_days(): void
    {
        // ((30000-9000)/30000)/21*100 = (21000/30000)/21*100 = 0.7/21*100 = 3.333...
        $result = $this->calculateWri(30000, 9000, 21);
        $this->assertEqualsWithDelta(3.33, $result, 0.01);
    }

    public function test_wri_zero_residue(): void
    {
        // ((30000-0)/30000)/21*100 = 1/21*100 = 4.7619...
        $result = $this->calculateWri(30000, 0, 21);
        $this->assertEqualsWithDelta(4.76, $result, 0.01);
    }

    public function test_wri_residue_equals_input(): void
    {
        $this->assertEquals(0.0, $this->calculateWri(30000, 30000, 21));
    }

    public function test_wri_zero_input_returns_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateWri(0, 500, 21));
    }

    public function test_wri_zero_days_returns_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateWri(30000, 9000, 0));
    }

    public function test_wri_one_day(): void
    {
        // ((30000-9000)/30000)/1*100 = 70%
        $result = $this->calculateWri(30000, 9000, 1);
        $this->assertEqualsWithDelta(70.0, $result, 0.01);
    }

    public function test_wri_short_cycle_small_input(): void
    {
        // ((5000-1000)/5000)/7*100 = 0.8/7*100 = 11.428...
        $result = $this->calculateWri(5000, 1000, 7);
        $this->assertEqualsWithDelta(11.43, $result, 0.01);
    }

    // =========================================================================
    // A3. ADD — Accumulated Degree Days
    // Rumus: SUM(daily_avg_temp - 15) untuk setiap hari di mana daily_avg_temp > 15
    // =========================================================================

    private function calculateAdd(array $dailyAvgTemps, float $baseTemp = 15): float
    {
        $accumulated = 0;
        foreach ($dailyAvgTemps as $temp) {
            if ($temp > $baseTemp) {
                $accumulated += ($temp - $baseTemp);
            }
        }
        return round($accumulated, 1);
    }

    public function test_add_constant_ideal_temp(): void
    {
        // 7 hari × 30°C
        $temps = array_fill(0, 7, 30.0);
        $this->assertEquals(105.0, $this->calculateAdd($temps));
    }

    public function test_add_all_below_base_temp(): void
    {
        // 3 hari: 12°C, 14°C, 13°C → semua ≤15°C
        $temps = [12.0, 14.0, 13.0];
        $this->assertEquals(0.0, $this->calculateAdd($temps));
    }

    public function test_add_mixed_temps(): void
    {
        // 4 hari: 28°C (hitung), 12°C (skip), 30°C (hitung), 15°C (skip)
        $temps = [28.0, 12.0, 30.0, 15.0];
        // (28-15) + 0 + (30-15) + 0 = 13 + 15 = 28
        $this->assertEquals(28.0, $this->calculateAdd($temps));
    }

    public function test_add_exact_base_temp(): void
    {
        // 5 hari × 15°C → 15−15=0, tapi kondisi >15, bukan >=15
        $temps = array_fill(0, 5, 15.0);
        $this->assertEquals(0.0, $this->calculateAdd($temps));
    }

    public function test_add_one_day_high_temp(): void
    {
        $temps = [40.0];
        $this->assertEquals(25.0, $this->calculateAdd($temps));
    }

    public function test_add_very_high_temp(): void
    {
        $temps = [50.0];
        $this->assertEquals(35.0, $this->calculateAdd($temps));
    }

    // =========================================================================
    // A4. ADD Progress & Estimasi Sisa Hari
    // =========================================================================

    private function calculateAddProgress(float $accumulatedAdd, float $targetAdd = 500): float
    {
        return min(round(($accumulatedAdd / $targetAdd) * 100, 1), 100);
    }

    private function calculateEstimatedRemainingDays(
        float $accumulatedAdd,
        float $avgTemp,
        float $targetAdd = 500,
        float $baseTemp = 15
    ): int {
        $avgDailyAdd = $avgTemp - $baseTemp;
        if ($avgDailyAdd > 0 && $accumulatedAdd < $targetAdd) {
            $remainingAdd = $targetAdd - $accumulatedAdd;
            return max(1, (int) ceil($remainingAdd / $avgDailyAdd));
        }
        return 0;
    }

    public function test_add_progress_50_percent(): void
    {
        $this->assertEquals(50.0, $this->calculateAddProgress(250));
    }

    public function test_add_progress_exact_100(): void
    {
        $this->assertEquals(100.0, $this->calculateAddProgress(500));
    }

    public function test_add_progress_exceeds_100_capped(): void
    {
        $this->assertEquals(100.0, $this->calculateAddProgress(600));
    }

    public function test_add_progress_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateAddProgress(0));
    }

    public function test_estimated_days_50_percent(): void
    {
        // ADD=250, avgTemp=30 → avgDaily=15, remaining=250, ceil(250/15)=17
        $this->assertEquals(17, $this->calculateEstimatedRemainingDays(250, 30));
    }

    public function test_estimated_days_reached_target(): void
    {
        // ADD=500 → siap panen
        $this->assertEquals(0, $this->calculateEstimatedRemainingDays(500, 30));
    }

    public function test_estimated_days_low_temp(): void
    {
        // avgTemp=14°C → avgDailyAdd = -1 ≤ 0 → 0
        $this->assertEquals(0, $this->calculateEstimatedRemainingDays(100, 14));
    }

    public function test_estimated_days_exact_base_temp(): void
    {
        // avgTemp=15°C → avgDailyAdd = 0 → 0 (siap panen)
        $this->assertEquals(0, $this->calculateEstimatedRemainingDays(100, 15));
    }

    public function test_estimated_days_from_scratch(): void
    {
        // ADD=0, avgTemp=30 → ceil(500/15)=34
        $this->assertEquals(34, $this->calculateEstimatedRemainingDays(0, 30));
    }

    // =========================================================================
    // A5. Days Elapsed (Cycle Model Accessor Simulation)
    // Rumus: max(1, (int) diffInDays(start, end ?? now))
    // =========================================================================

    private function calculateDaysElapsed(Carbon $startDate, ?Carbon $endDate = null): int
    {
        $end = $endDate ?? now();
        return max(1, (int) $startDate->diffInDays($end));
    }

    public function test_days_elapsed_5_days_running(): void
    {
        $start = now()->subDays(5);
        $this->assertEquals(5, $this->calculateDaysElapsed($start));
    }

    public function test_days_elapsed_first_day_minimum_one(): void
    {
        $start = now();
        $this->assertEquals(1, $this->calculateDaysElapsed($start));
    }

    public function test_days_elapsed_finished_cycle(): void
    {
        $start = now()->subDays(15);
        $end = now()->subDays(5);
        $this->assertEquals(10, $this->calculateDaysElapsed($start, $end));
    }

    public function test_days_elapsed_same_day_start_and_end(): void
    {
        $start = now();
        $end = now();
        $this->assertEquals(1, $this->calculateDaysElapsed($start, $end));
    }

    public function test_days_elapsed_30_days(): void
    {
        $start = now()->subDays(30);
        $this->assertEquals(30, $this->calculateDaysElapsed($start));
    }

    // =========================================================================
    // A6. Total Massa Maggot (kg)
    // Rumus: array_sum(biopond) / 1000
    // =========================================================================

    private function calculateTotalMass(array $biopond): float
    {
        return array_sum($biopond) / 1000;
    }

    public function test_total_mass_six_racks_normal(): void
    {
        $mass = $this->calculateTotalMass([1000, 2000, 1500, 3000, 2500, 2000]);
        $this->assertEquals(12.0, $mass);
    }

    public function test_total_mass_all_zero(): void
    {
        $this->assertEquals(0.0, $this->calculateTotalMass([0, 0, 0, 0, 0, 0]));
    }

    public function test_total_mass_one_rack_only(): void
    {
        $this->assertEquals(5.0, $this->calculateTotalMass([5000, 0, 0, 0, 0, 0]));
    }

    public function test_total_mass_empty_array(): void
    {
        $this->assertEquals(0.0, $this->calculateTotalMass([]));
    }

    public function test_total_mass_decimal_values(): void
    {
        $mass = $this->calculateTotalMass([1500.5, 2000.3, 1800.2, 0, 0, 0]);
        $this->assertEqualsWithDelta(5.301, $mass, 0.001);
    }

    public function test_total_mass_negative_sensor_error(): void
    {
        // Harusnya tidak crash, hanya menghasilkan nilai (mungkin negatif)
        $mass = $this->calculateTotalMass([-500, 2000, 1500, 0, 0, 0]);
        $this->assertEquals(3.0, $mass);
    }

    // =========================================================================
    // A7. Rata-rata Soil Moisture
    // Rumus: array_sum(soil) / count(soil)
    // =========================================================================

    private function calculateAvgSoil(array $soil): float
    {
        if (count($soil) === 0) {
            return 0;
        }
        return array_sum($soil) / count($soil);
    }

    public function test_avg_soil_normal(): void
    {
        $avg = $this->calculateAvgSoil([70, 75, 80, 72, 68, 78]);
        $this->assertEqualsWithDelta(73.83, $avg, 0.01);
    }

    public function test_avg_soil_one_extreme_dry(): void
    {
        $avg = $this->calculateAvgSoil([70, 75, 20, 72, 68, 78]);
        $this->assertEqualsWithDelta(63.83, $avg, 0.01);
    }

    public function test_avg_soil_all_identical(): void
    {
        $this->assertEquals(75.0, $this->calculateAvgSoil([75, 75, 75, 75, 75, 75]));
    }

    public function test_avg_soil_empty_array(): void
    {
        $this->assertEquals(0, $this->calculateAvgSoil([]));
    }

    public function test_avg_soil_single_sensor(): void
    {
        $this->assertEquals(80.0, $this->calculateAvgSoil([80]));
    }

    // =========================================================================
    // A8. Batch ID Generation
    // Format: #BCH-YYYYMM-NN
    // =========================================================================

    private function generateBatchId(int $existingCount): string
    {
        $prefix = '#BCH-' . now()->format('Ym') . '-';
        $number = str_pad($existingCount + 1, 2, '0', STR_PAD_LEFT);
        return $prefix . $number;
    }

    public function test_batch_id_first_cycle(): void
    {
        $this->assertStringEndsWith('-01', $this->generateBatchId(0));
    }

    public function test_batch_id_tenth_cycle(): void
    {
        $this->assertStringEndsWith('-10', $this->generateBatchId(9));
    }

    public function test_batch_id_99th_cycle(): void
    {
        $this->assertStringEndsWith('-99', $this->generateBatchId(98));
    }

    public function test_batch_id_over_100(): void
    {
        // str_pad with 2 digits won't truncate, it just won't pad if longer
        $this->assertStringEndsWith('-100', $this->generateBatchId(99));
    }

    // =========================================================================
    // B1-B4. THRESHOLD ALERT LOGIC
    // =========================================================================

    /**
     * Simulasi evaluasi threshold suhu.
     * Returns: null (no alert), 'danger', atau 'warning'
     */
    private function evaluateTempAlert(float $temp, array $thresholds): ?array
    {
        if ($temp > $thresholds['max_safe']) {
            return ['type' => 'danger', 'title' => 'Bahaya Suhu Kritis!'];
        } elseif ($temp > $thresholds['max_ideal']) {
            return ['type' => 'warning', 'title' => 'Peringatan Suhu Tinggi'];
        } elseif ($temp < $thresholds['min_ideal']) {
            return ['type' => 'warning', 'title' => 'Suhu Terlalu Rendah'];
        }
        return null;
    }

    public function test_temp_alert_critical(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        $alert = $this->evaluateTempAlert(36.5, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('danger', $alert['type']);
    }

    public function test_temp_alert_warning_high(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        $alert = $this->evaluateTempAlert(32.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('warning', $alert['type']);
    }

    public function test_temp_alert_warning_low(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        $alert = $this->evaluateTempAlert(22.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('warning', $alert['type']);
    }

    public function test_temp_alert_ideal_no_alert(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        $this->assertNull($this->evaluateTempAlert(27.0, $thresholds));
    }

    public function test_temp_alert_at_max_ideal_boundary(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        // 30.0 tidak >30, jadi tidak alert
        $this->assertNull($this->evaluateTempAlert(30.0, $thresholds));
    }

    public function test_temp_alert_at_min_ideal_boundary(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        // 24.0 tidak <24, jadi tidak alert
        $this->assertNull($this->evaluateTempAlert(24.0, $thresholds));
    }

    public function test_temp_alert_at_max_safe_boundary(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        // 35.0 tidak >35 (bukan danger), tapi >30 → warning "Suhu Tinggi"
        $alert = $this->evaluateTempAlert(35.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('warning', $alert['type']);
        $this->assertStringContainsString('Suhu Tinggi', $alert['title']);
    }

    public function test_temp_alert_just_above_max_safe(): void
    {
        $thresholds = ['min_ideal' => 24, 'max_ideal' => 30, 'max_safe' => 35];
        $alert = $this->evaluateTempAlert(35.1, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('danger', $alert['type']);
    }

    /**
     * Simulasi evaluasi threshold kelembapan udara.
     */
    private function evaluateHumAlert(float $hum, array $thresholds): ?array
    {
        if ($hum > $thresholds['max_ideal']) {
            return ['type' => 'warning', 'title' => 'Udara Terlalu Lembap'];
        } elseif ($hum < $thresholds['min_ideal']) {
            return ['type' => 'warning', 'title' => 'Udara Terlalu Kering'];
        }
        return null;
    }

    public function test_hum_alert_too_humid(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $alert = $this->evaluateHumAlert(85.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertStringContainsString('Lembap', $alert['title']);
    }

    public function test_hum_alert_too_dry(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $alert = $this->evaluateHumAlert(45.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertStringContainsString('Kering', $alert['title']);
    }

    public function test_hum_alert_ideal(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $this->assertNull($this->evaluateHumAlert(70.0, $thresholds));
    }

    public function test_hum_alert_at_boundaries(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $this->assertNull($this->evaluateHumAlert(80.0, $thresholds));
        $this->assertNull($this->evaluateHumAlert(60.0, $thresholds));
    }

    public function test_hum_alert_just_above_80(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $this->assertNotNull($this->evaluateHumAlert(80.1, $thresholds));
    }

    public function test_hum_alert_just_below_60(): void
    {
        $thresholds = ['min_ideal' => 60, 'max_ideal' => 80];
        $this->assertNotNull($this->evaluateHumAlert(59.9, $thresholds));
    }

    /**
     * Simulasi evaluasi threshold kelembapan media (soil).
     */
    private function evaluateSoilAlert(float $avgSoil, array $thresholds): ?array
    {
        if ($avgSoil > $thresholds['max_safe']) {
            return ['type' => 'danger', 'title' => 'Media Terlalu Basah!'];
        } elseif ($avgSoil < $thresholds['min_safe']) {
            return ['type' => 'warning', 'title' => 'Media Terlalu Kering'];
        }
        return null;
    }

    public function test_soil_alert_too_wet(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $alert = $this->evaluateSoilAlert(92.5, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('danger', $alert['type']);
    }

    public function test_soil_alert_too_dry(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $alert = $this->evaluateSoilAlert(50.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('warning', $alert['type']);
    }

    public function test_soil_alert_safe(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $this->assertNull($this->evaluateSoilAlert(75.0, $thresholds));
    }

    public function test_soil_alert_at_boundaries(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $this->assertNull($this->evaluateSoilAlert(90.0, $thresholds));
        $this->assertNull($this->evaluateSoilAlert(60.0, $thresholds));
    }

    public function test_soil_alert_just_above_90(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $this->assertNotNull($this->evaluateSoilAlert(90.1, $thresholds));
    }

    public function test_soil_alert_just_below_60(): void
    {
        $thresholds = ['min_safe' => 60, 'max_safe' => 90];
        $this->assertNotNull($this->evaluateSoilAlert(59.9, $thresholds));
    }

    /**
     * Simulasi evaluasi threshold amonia.
     */
    private function evaluateAmmoniaAlert(float $ammonia, array $thresholds): ?array
    {
        if ($ammonia > $thresholds['max_safe']) {
            return ['type' => 'danger', 'title' => 'Bahaya Amonia Beracun!'];
        }
        return null;
    }

    public function test_ammonia_alert_toxic(): void
    {
        $thresholds = ['max_safe' => 20];
        $alert = $this->evaluateAmmoniaAlert(25.0, $thresholds);
        $this->assertNotNull($alert);
        $this->assertEquals('danger', $alert['type']);
    }

    public function test_ammonia_alert_safe(): void
    {
        $thresholds = ['max_safe' => 20];
        $this->assertNull($this->evaluateAmmoniaAlert(15.0, $thresholds));
    }

    public function test_ammonia_alert_at_boundary(): void
    {
        $thresholds = ['max_safe' => 20];
        // 20.0 tidak >20
        $this->assertNull($this->evaluateAmmoniaAlert(20.0, $thresholds));
    }

    public function test_ammonia_alert_just_above(): void
    {
        $thresholds = ['max_safe' => 20];
        $this->assertNotNull($this->evaluateAmmoniaAlert(20.1, $thresholds));
    }

    public function test_ammonia_alert_very_high(): void
    {
        $thresholds = ['max_safe' => 20];
        $alert = $this->evaluateAmmoniaAlert(85.0, $thresholds);
        $this->assertNotNull($alert);
    }

    public function test_ammonia_alert_zero(): void
    {
        $thresholds = ['max_safe' => 20];
        $this->assertNull($this->evaluateAmmoniaAlert(0.0, $thresholds));
    }

    // =========================================================================
    // B5. CYCLE REMINDER LOGIC
    // =========================================================================

    /**
     * Simulasi evaluasi pengingat siklus.
     */
    private function evaluateCycleReminders(int $daysElapsed): array
    {
        $alerts = [];

        if ($daysElapsed >= 21) {
            $alerts[] = ['type' => 'warning', 'title' => 'Waktunya Panen!'];
        } elseif ($daysElapsed > 0 && $daysElapsed % 3 === 0) {
            $alerts[] = ['type' => 'info', 'title' => 'Jadwal Pakan Tiba'];
        }

        return $alerts;
    }

    public function test_cycle_reminder_harvest_day_21(): void
    {
        $alerts = $this->evaluateCycleReminders(21);
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Panen', $alerts[0]['title']);
    }

    public function test_cycle_reminder_past_harvest_day_25(): void
    {
        $alerts = $this->evaluateCycleReminders(25);
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Panen', $alerts[0]['title']);
    }

    public function test_cycle_reminder_feeding_day_3(): void
    {
        $alerts = $this->evaluateCycleReminders(3);
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Pakan', $alerts[0]['title']);
    }

    public function test_cycle_reminder_feeding_day_6(): void
    {
        $alerts = $this->evaluateCycleReminders(6);
        $this->assertCount(1, $alerts);
        $this->assertStringContainsString('Pakan', $alerts[0]['title']);
    }

    public function test_cycle_reminder_day_4_no_alert(): void
    {
        $alerts = $this->evaluateCycleReminders(4);
        $this->assertCount(0, $alerts);
    }

    public function test_cycle_reminder_day_1_no_alert(): void
    {
        $alerts = $this->evaluateCycleReminders(1);
        $this->assertCount(0, $alerts);
    }
}
