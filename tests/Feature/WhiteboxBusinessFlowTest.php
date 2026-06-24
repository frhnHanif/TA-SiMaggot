<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Cycle;
use App\Models\SensorData;
use App\Models\DeviceControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

/**
 * WHITEBOX FEATURE TEST — Logika Flow Bisnis & Kontrol
 * 
 * Menguji logika bisnis melalui HTTP endpoint dengan database.
 * Mencakup: Siklus (start, addWaste, finish), Kontrol (lock, offline, mist),
 *           Fail-safe, Force update, Alert endpoints.
 */
class WhiteboxBusinessFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;
    private string $apiKey = 'test-iot-key-2024';

    protected function setUp(): void
    {
        parent::setUp();
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();

        // Set API key untuk test
        config(['maggot.api_key' => $this->apiKey]);

        // Pastikan DeviceControl default ada
        DeviceControl::create([
            'is_manual' => false,
            'fan' => 0,
            'mist' => [0, 0, 0, 0, 0, 0],
            'mist_stop_at' => [null, null, null, null, null, null],
            'last_ping_at' => now(),
        ]);
    }

    // =========================================================================
    // D1. MULAI SIKLUS BARU (CycleController@store)
    // =========================================================================

    #[Test]
    public function cycle_start_valid_with_six_racks(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [500, 600, 700, 800, 500, 400],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle = Cycle::first();
        $this->assertNotNull($cycle);
        $this->assertEquals(3500, $cycle->initial_seed_mass); // 500+600+700+800+500+400
        $this->assertEquals('berjalan', $cycle->status);
        $this->assertEquals(0, $cycle->total_waste_input);
        $this->assertStringStartsWith('#BCH-', $cycle->batch_id);
    }

    #[Test]
    public function cycle_start_all_racks_zero_rejected(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [0, 0, 0, 0, 0, 0],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('cycles', 0);
    }

    #[Test]
    public function cycle_start_when_active_cycle_exists_rejected(): void
    {
        // Buat siklus berjalan dulu
        Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now(),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 0,
            'status' => 'berjalan',
        ]);

        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [500, 600, 700, 800, 500, 400],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertEquals(1, Cycle::count());
    }

    #[Test]
    public function cycle_start_partial_racks_filled(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [500, null, 700, null, 500, 400],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle = Cycle::first();
        $this->assertEquals(2100, $cycle->initial_seed_mass); // 500+700+500+400
    }

    #[Test]
    public function cycle_start_negative_value_rejected(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [-500, 600, 700],
        ]);

        $response->assertSessionHasErrors(['bibit_rak.0']);
    }

    #[Test]
    public function cycle_start_all_null_rejected(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/start', [
            'bibit_rak' => [null, null, null, null, null, null],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('cycles', 0);
    }

    // =========================================================================
    // D2. CATAT PAKAN (CycleController@addWaste)
    // =========================================================================

    #[Test]
    public function add_waste_valid_accumulates_total(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now(),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 5000,
            'status' => 'berjalan',
        ]);

        $response = $this->actingAs($this->userA)->post('/cycle/add-waste', [
            'pakan_rak' => [1000, 1500, 2000, 1000, 1500, 2000],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle->refresh();
        $this->assertEquals(14000, $cycle->total_waste_input); // 5000 + 9000
    }

    #[Test]
    public function add_waste_all_zero_rejected(): void
    {
        Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now(),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 5000,
            'status' => 'berjalan',
        ]);

        $response = $this->actingAs($this->userA)->post('/cycle/add-waste', [
            'pakan_rak' => [0, 0, 0, 0, 0, 0],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function add_waste_no_active_cycle_rejected(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/add-waste', [
            'pakan_rak' => [1000, 2000, 3000],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function add_waste_multiple_times_accumulates(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now(),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 0,
            'status' => 'berjalan',
        ]);

        // 3 kali tambah @3000g
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($this->userA)->post('/cycle/add-waste', [
                'pakan_rak' => [500, 500, 500, 500, 500, 500],
            ]);
        }

        $cycle->refresh();
        $this->assertEquals(9000, $cycle->total_waste_input);
    }

    #[Test]
    public function add_waste_partial_racks(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now(),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 0,
            'status' => 'berjalan',
        ]);

        $this->actingAs($this->userA)->post('/cycle/add-waste', [
            'pakan_rak' => [1000, null, 500, null, null, null],
        ]);

        $cycle->refresh();
        $this->assertEquals(1500, $cycle->total_waste_input);
    }

    // =========================================================================
    // D3. SELESAIKAN SIKLUS / PANEN (CycleController@finish)
    // =========================================================================

    #[Test]
    public function finish_cycle_with_manual_input(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(21),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 20000,
            'status' => 'berjalan',
        ]);

        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 9000,
            'temp' => 28,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 10,
        ]);

        $response = $this->actingAs($this->userA)->post('/cycle/finish', [
            'kasgot_aktual' => 5000,
            'panen_aktual' => 8000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle->refresh();
        $this->assertEquals('selesai', $cycle->status);
        $this->assertEquals(5000, $cycle->residue_mass);
        $this->assertEquals(8000, $cycle->harvest_mass);

        // ECI = (8000/20000)*100 = 40%
        $this->assertEquals(40.0, $cycle->eci_result);

        // WRI = ((20000-5000)/20000)/21*100 = (15000/20000)/21*100 ≈ 3.571
        $this->assertEqualsWithDelta(3.57, $cycle->wri_result, 0.01);
    }

    #[Test]
    public function finish_cycle_auto_from_sensor(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(21),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 20000,
            'status' => 'berjalan',
        ]);

        SensorData::create([
            'biopond' => [1000, 2000, 1500, 1000, 500, 2000],
            'harvest' => 7500,
            'temp' => 28,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 10,
        ]);

        // Kirim tanpa kasgot & panen → auto dari sensor
        $response = $this->actingAs($this->userA)->post('/cycle/finish', []);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle->refresh();
        $this->assertEquals('selesai', $cycle->status);
        $this->assertEquals(8000, $cycle->residue_mass); // sum biopond = 8000
        $this->assertEquals(7500, $cycle->harvest_mass); // harvest field
    }

    #[Test]
    public function finish_cycle_no_active_cycle_rejected(): void
    {
        $response = $this->actingAs($this->userA)->post('/cycle/finish', [
            'kasgot_aktual' => 5000,
            'panen_aktual' => 8000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function finish_cycle_no_sensor_data_rejected(): void
    {
        Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(21),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 20000,
            'status' => 'berjalan',
        ]);

        // Tidak ada SensorData
        $response = $this->actingAs($this->userA)->post('/cycle/finish', []);

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    #[Test]
    public function finish_cycle_zero_input_eci_wri_zero(): void
    {
        $cycle = Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(21),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 0, // Tidak ada pakan
            'status' => 'berjalan',
        ]);

        SensorData::create([
            'biopond' => [1000, 1000, 1000, 0, 0, 0],
            'harvest' => 2000,
            'temp' => 28,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 10,
        ]);

        $response = $this->actingAs($this->userA)->post('/cycle/finish', [
            'kasgot_aktual' => 5000,
            'panen_aktual' => 2000,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $cycle->refresh();
        $this->assertEquals(0.0, $cycle->eci_result);
        $this->assertEquals(0.0, $cycle->wri_result);
    }

    // =========================================================================
    // C1. CONCURRENCY LOCK (SensorDataController@updateControl)
    // =========================================================================

    #[Test]
    public function lock_user_a_switch_to_manual(): void
    {
        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['is_manual' => true]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');

        $control = DeviceControl::first();
        $this->assertTrue($control->is_manual);
        $this->assertEquals($this->userA->id, $control->controlled_by);
        $this->assertNotNull($control->locked_until);
        $this->assertTrue($control->locked_until->isFuture());
    }

    #[Test]
    public function lock_user_b_blocked_when_user_a_has_lock(): void
    {
        // User A ambil lock
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->addMinutes(5),
        ]);

        // User B coba kontrol
        $response = $this->actingAs($this->userB)
            ->postJson('/web-control', ['fan' => 255]);

        $response->assertForbidden();
        $response->assertJsonPath('status', 'error');
    }

    #[Test]
    public function lock_user_a_can_control_when_has_lock(): void
    {
        // User A ambil lock
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->addMinutes(5),
        ]);

        // User A kirim kontrol fan
        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['fan' => 255]);

        $response->assertOk();
        $this->assertEquals(255, DeviceControl::first()->fan);
    }

    #[Test]
    public function lock_released_when_switch_to_auto(): void
    {
        // User A ambil lock dulu
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->addMinutes(5),
        ]);

        // User A switch ke auto
        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['is_manual' => false]);

        $response->assertOk();

        $control = DeviceControl::first();
        $this->assertFalse($control->is_manual);
        $this->assertNull($control->controlled_by);
        $this->assertNull($control->locked_until);
    }

    #[Test]
    public function lock_actuator_rejected_when_auto_mode(): void
    {
        // Mode auto
        DeviceControl::first()->update(['is_manual' => false]);

        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['fan' => 255]);

        $response->assertStatus(400);
        $response->assertJsonPath('status', 'error');
    }

    // =========================================================================
    // C2. ESP32 OFFLINE DETECTION
    // =========================================================================

    #[Test]
    public function offline_control_rejected_when_esp32_offline(): void
    {
        // Simulasi offline: last_ping >30 detik
        DeviceControl::first()->update([
            'last_ping_at' => now()->subSeconds(60),
        ]);

        // Coba switch manual
        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['is_manual' => true]);

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'error');
    }

    #[Test]
    public function offline_actuator_rejected_when_esp32_offline(): void
    {
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->addMinutes(5),
            'last_ping_at' => now()->subSeconds(60), // Offline
        ]);

        // Coba kirim fan (meskipun sudah manual & lock)
        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['fan' => 128]);

        $response->assertStatus(503);
    }

    #[Test]
    public function online_control_accepted_when_esp32_recently_pinged(): void
    {
        DeviceControl::first()->update([
            'last_ping_at' => now()->subSeconds(10), // Online
        ]);

        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['is_manual' => true]);

        $response->assertOk();
        $this->assertTrue(DeviceControl::first()->is_manual);
    }

    #[Test]
    public function never_pinged_considered_offline(): void
    {
        DeviceControl::first()->update(['last_ping_at' => null]);

        $response = $this->actingAs($this->userA)
            ->postJson('/web-control', ['is_manual' => true]);

        $response->assertStatus(503);
    }

    // =========================================================================
    // C3. MIST TIMER & FAIL-SAFE (getControl endpoint)
    // =========================================================================

    #[Test]
    public function mist_timer_expired_auto_shutoff_on_get_control(): void
    {
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->addMinutes(5),
            'mist' => [10, 10, 0, 0, 0, 0],
            'mist_stop_at' => [
                now()->subSeconds(5)->toDateTimeString(), // Expired
                now()->addSeconds(30)->toDateTimeString(), // Masih aktif
                null, null, null, null,
            ],
        ]);

        $response = $this->withHeader('X-API-KEY', $this->apiKey)
            ->getJson('/api/control');

        $response->assertOk();
        $mist = $response->json('mist');

        // Rak 0 harus mati (timer expired)
        $this->assertEquals(0, $mist[0]);
        // Rak 1 harus tetap menyala (timer belum expired)
        $this->assertEquals(10, $mist[1]);
    }

    #[Test]
    public function fail_safe_lock_expired_auto_release_on_get_control(): void
    {
        DeviceControl::first()->update([
            'is_manual' => true,
            'controlled_by' => $this->userA->id,
            'locked_until' => now()->subMinutes(1), // Expired
            'fan' => 255,
            'mist' => [10, 10, 10, 0, 0, 0],
            'mist_stop_at' => [
                now()->addSeconds(30)->toDateTimeString(),
                now()->addSeconds(30)->toDateTimeString(),
                now()->addSeconds(30)->toDateTimeString(),
                null, null, null,
            ],
        ]);

        $response = $this->withHeader('X-API-KEY', $this->apiKey)
            ->getJson('/api/control');

        $response->assertOk();

        // Harus kembali ke auto
        $this->assertFalse($response->json('is_manual'));
    }

    // =========================================================================
    // C5. FORCE SENSOR UPDATE
    // =========================================================================

    #[Test]
    public function force_update_flag_reset_when_esp_sends_data(): void
    {
        DeviceControl::first()->update(['force_sensor_update' => true]);

        $response = $this->withHeader('X-API-KEY', $this->apiKey)
            ->postJson('/api/sensor', [
                'biopond' => [1000, 2000, 1500, 3000, 2500, 2000],
                'harvest' => 0,
                'temp' => 28.5,
                'hum' => 72.0,
                'soil' => [70, 75, 80, 72, 68, 78],
                'ammonia' => 5.0,
            ]);

        $response->assertStatus(201);

        $control = DeviceControl::first();
        $this->assertFalse($control->force_sensor_update);
    }

    #[Test]
    public function force_update_flag_unchanged_when_already_false(): void
    {
        DeviceControl::first()->update(['force_sensor_update' => false]);

        $response = $this->withHeader('X-API-KEY', $this->apiKey)
            ->postJson('/api/sensor', [
                'biopond' => [1000, 2000, 1500, 3000, 2500, 2000],
                'harvest' => 0,
                'temp' => 28.5,
                'hum' => 72.0,
                'soil' => [70, 75, 80, 72, 68, 78],
                'ammonia' => 5.0,
            ]);

        $response->assertStatus(201);

        $control = DeviceControl::first();
        $this->assertFalse($control->force_sensor_update);
    }

    // =========================================================================
    // ALERT ENDPOINT (checkAlerts)
    // =========================================================================

    #[Test]
    public function alert_temp_critical_triggers_danger(): void
    {
        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 0,
            'temp' => 36.5,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 10,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/check-alerts');
        $response->assertOk();

        $alerts = $response->json('alerts');
        $dangerAlerts = array_filter($alerts, fn($a) => $a['type'] === 'danger');

        // Harus ada danger alert untuk suhu
        $tempDangers = array_filter($alerts, fn($a) => str_contains($a['id'], 'temp_danger'));
        $this->assertNotEmpty($tempDangers);
    }

    #[Test]
    public function alert_ammonia_toxic_triggers_danger(): void
    {
        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 0,
            'temp' => 28,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 25.0,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/check-alerts');
        $response->assertOk();

        $alerts = $response->json('alerts');
        $nh3Alerts = array_filter($alerts, fn($a) => str_contains($a['id'], 'nh3_danger'));
        $this->assertNotEmpty($nh3Alerts);
    }

    #[Test]
    public function alert_no_alerts_when_all_ideal(): void
    {
        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 0,
            'temp' => 27,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 5,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/check-alerts');
        $response->assertOk();

        $alerts = $response->json('alerts');
        // Tidak ada alert sensor (mungkin ada alert siklus jika ada siklus aktif)
        $sensorAlerts = array_filter($alerts, fn($a) => !str_contains($a['id'], 'pakan_') && !str_contains($a['id'], 'panen_'));
        $this->assertEmpty($sensorAlerts);
    }

    #[Test]
    public function alert_cycle_harvest_reminder_day_21(): void
    {
        Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(21),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 20000,
            'status' => 'berjalan',
        ]);

        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 0,
            'temp' => 27,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 5,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/check-alerts');
        $response->assertOk();

        $alerts = $response->json('alerts');
        $panenAlerts = array_filter($alerts, fn($a) => str_contains($a['id'], 'panen_'));
        $this->assertNotEmpty($panenAlerts);
    }

    #[Test]
    public function alert_cycle_feeding_reminder_day_3(): void
    {
        Cycle::create([
            'batch_id' => '#BCH-202606-01',
            'start_date' => now()->subDays(3),
            'initial_seed_mass' => 3000,
            'total_waste_input' => 5000,
            'status' => 'berjalan',
        ]);

        SensorData::create([
            'biopond' => [1000, 1000, 1000, 1000, 1000, 1000],
            'harvest' => 0,
            'temp' => 27,
            'hum' => 70,
            'soil' => [75, 75, 75, 75, 75, 75],
            'ammonia' => 5,
        ]);

        $response = $this->actingAs($this->userA)->getJson('/api/check-alerts');
        $response->assertOk();

        $alerts = $response->json('alerts');
        $pakanAlerts = array_filter($alerts, fn($a) => str_contains($a['id'], 'pakan_'));
        $this->assertNotEmpty($pakanAlerts);
    }
}
