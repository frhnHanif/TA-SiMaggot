@extends('layouts.app')

@section('title', 'Panel Kontrol Aktuator')

@section('content')

@php
    // Mengambil data sensor terbaru langsung di View untuk panduan monitoring
    $latestData = \App\Models\SensorData::latest()->first();
    $temp = $latestData ? $latestData->temp : '--';
    $hum = $latestData ? $latestData->hum : '--';
    $soilData = ($latestData && $latestData->soil) ? (is_array($latestData->soil) ? $latestData->soil : json_decode($latestData->soil, true)) : [0,0,0,0,0,0];
@endphp

<div class="max-w-4xl mx-auto">
    
    @if(session('success'))
        <div class="mb-6 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-xl relative shadow-sm text-sm" role="alert">
            <strong class="font-bold">Berhasil!</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="mb-6 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-xl relative shadow-sm text-sm" role="alert">
            <strong class="font-bold">Gagal!</strong>
            <span class="block sm:inline">{{ session('error') }}</span>
        </div>
    @endif

    @if($isLockedByOthers)
        <div class="bg-amber-50 border border-amber-200 text-amber-800 px-6 py-4 rounded-2xl mb-6 flex items-start gap-4 shadow-sm animate-pulse">
            <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center text-amber-600 shrink-0 text-lg">
                <i class="fa-solid fa-user-lock"></i>
            </div>
            <div>
                <h4 class="font-bold text-base">Akses Kontrol Terkunci</h4>
                <p class="text-sm mt-1">{!! $lockMessage !!}</p>
                <p class="text-[11px] text-amber-600 font-medium mt-2">*Kunci akan terbuka otomatis dan kembali ke mode OTOMATIS jika akun tersebut tidak melakukan aktivitas perubahan kontrol selama 5 menit.</p>
            </div>
        </div>
    @endif

    <div id="autoAlert" class="bg-blue-50 border border-blue-200 text-blue-700 px-6 py-4 rounded-2xl mb-6 flex items-start gap-4 {{ $control->is_manual ? 'hidden' : 'flex' }}">
        <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center text-blue-600 shrink-0 text-lg">
            <i class="fa-solid fa-robot"></i>
        </div>
        <div>
            <h4 class="font-bold">Sistem Berjalan Otomatis (Fuzzy Logic)</h4>
            <p class="text-sm mt-0.5">Saat ini ESP32 mengendalikan aktuator secara mandiri berdasarkan data sensor. Anda harus mengubah sakelar ke mode <strong class="text-red-600">MANUAL</strong> terlebih dahulu untuk mengambil alih kontrol.</p>
        </div>
    </div>

    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 mb-6 flex justify-between items-center {{ $isLockedByOthers ? 'opacity-50 pointer-events-none select-none' : '' }}">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Mode Operasional</h2>
            <p class="text-xs text-gray-500 mt-1">Tentukan siapa yang mengambil alih kendali penuh aktuator.</p>
        </div>
        
        <label class="relative inline-flex items-center cursor-pointer select-none">
            <input type="checkbox" id="modeSwitch" class="sr-only peer" {{ $control->is_manual ? 'checked' : '' }} onchange="toggleMode()" {{ $isLockedByOthers ? 'disabled' : '' }}>
            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-500"></div>
            <span class="ml-3 text-sm sm:text-base font-black tracking-wider w-24 {{ $control->is_manual ? 'text-red-500' : 'text-gray-400' }}" id="modeLabel">
                {{ $control->is_manual ? 'MANUAL' : 'OTOMATIS' }}
            </span>
        </label>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 {{ ($isLockedByOthers || !$control->is_manual) ? 'opacity-50 pointer-events-none select-none' : '' }}">
        
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 relative overflow-hidden flex flex-col">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 shrink-0">
                    <i class="fa-solid fa-fan {{ $control->fan > 0 ? 'fa-spin' : '' }}" id="fanIcon"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">Kipas Exhaust</h3>
                    <p class="text-xs text-gray-500">Kendalikan kecepatan putaran motor (Sinyal PWM)</p>
                </div>
            </div>

            <div class="flex gap-3 mb-6">
                <div class="flex-1 bg-amber-50/50 rounded-xl p-3 border border-amber-100 flex items-center gap-3">
                    <i class="fa-solid fa-temperature-half text-amber-500 text-lg"></i>
                    <div>
                        <p class="text-[10px] text-amber-600/70 font-bold uppercase tracking-wider mb-0.5">Suhu Udara</p>
                        <p class="text-lg font-black text-amber-600">{{ $temp }} <span class="text-xs font-normal">&deg;C</span></p>
                    </div>
                </div>
                <div class="flex-1 bg-blue-50/50 rounded-xl p-3 border border-blue-100 flex items-center gap-3">
                    <i class="fa-solid fa-droplet text-blue-500 text-lg"></i>
                    <div>
                        <p class="text-[10px] text-blue-600/70 font-bold uppercase tracking-wider mb-0.5">Kelembapan</p>
                        <p class="text-lg font-black text-blue-600">{{ $hum }} <span class="text-xs font-normal">%</span></p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-6 gap-2 mt-auto">
                @php
                    $fanLevels = [
                        ['label' => 'OFF', 'val' => 0],
                        ['label' => '1', 'val' => 51],
                        ['label' => '2', 'val' => 102],
                        ['label' => '3', 'val' => 153],
                        ['label' => '4', 'val' => 204],
                        ['label' => 'MAX', 'val' => 255],
                    ];
                    $currentFan = (int)$control->fan;
                @endphp
                
                @foreach($fanLevels as $level)
                    @php
                        $isActive = abs($currentFan - $level['val']) <= 25;
                    @endphp
                    <button type="button" 
                        onclick="sendFanData({{ $level['val'] }})"
                        data-val="{{ $level['val'] }}"
                        class="fan-btn relative py-3 rounded-xl border-2 font-bold transition-all text-xs
                        {{ ($isLockedByOthers || !$control->is_manual) ? 'opacity-50 cursor-not-allowed border-gray-100 bg-gray-50 text-gray-400' : 
                            ($isActive ? 'border-gray-800 bg-gray-800 text-white shadow-md active-fan' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-400') }}"
                        {{ ($isLockedByOthers || !$control->is_manual) ? 'disabled' : '' }}>
                        {{ $level['label'] }}
                    </button>
                @endforeach
            </div>
            <p class="text-[10px] text-center text-gray-400 mt-4">*Sistem mengonversi tingkatan ke format data PWM dinamis (0-255) untuk register mikroprosesor ESP32.</p>
        </div>

        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 flex flex-col">
            
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                    <i class="fa-solid fa-cloud-showers-water"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">Mist Maker Substrat</h3>
                    <p class="text-xs text-gray-500">Nyalakan pelembap otomatis mati berdasar timer</p>
                </div>
            </div>

            <div class="flex justify-center mb-6">
                <div class="inline-flex gap-1 bg-gray-100 p-1 rounded-xl select-none {{ ($isLockedByOthers || !$control->is_manual) ? 'opacity-50 pointer-events-none' : '' }}">
                    <button type="button" onclick="selectDuration(10)" id="duration-btn-10" class="duration-btn px-4 py-1.5 rounded-lg text-xs font-black transition-all bg-gray-800 text-white shadow-sm border border-transparent">10s</button>
                    <button type="button" onclick="selectDuration(30)" id="duration-btn-30" class="duration-btn px-4 py-1.5 rounded-lg text-xs font-black transition-all text-gray-500 hover:text-gray-800 hover:bg-white/50">30s</button>
                    <button type="button" onclick="selectDuration(60)" id="duration-btn-60" class="duration-btn px-4 py-1.5 rounded-lg text-xs font-black transition-all text-gray-500 hover:text-gray-800 hover:bg-white/50">60s</button>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mt-auto">
                @php
                    $mistArray = is_array($control->mist) ? $control->mist : json_decode($control->mist, true) ?? [0,0,0,0,0,0];
                @endphp
                
                @foreach($mistArray as $index => $val)
                    @php 
                        $isOn = $val === 10; 
                        $soilMoisture = isset($soilData[$index]) ? rtrim(rtrim(number_format((float)$soilData[$index], 1), '0'), '.') : '--';
                    @endphp
                    <button type="button" 
                        id="btn-mist-{{ $index }}"
                        onclick="toggleMist({{ $index }}, {{ $val }})"
                        {{ ($isLockedByOthers || !$control->is_manual) ? 'disabled' : '' }}
                        class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all 
                        {{ ($isLockedByOthers || !$control->is_manual) ? 'opacity-50 cursor-not-allowed border-gray-100 bg-gray-50 text-gray-400' : 
                            ($isOn ? 'border-blue-500 bg-blue-50 text-blue-600 shadow-sm' : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300') }}">
                        
                        <span class="text-[11px] font-black uppercase tracking-wider mb-1">Rak {{ $index + 1 }}</span>
                        
                        <div class="flex items-center gap-1 text-[10px] font-bold mb-2 {{ $isOn ? 'text-blue-500' : 'text-gray-400' }}" id="soil-info-{{ $index }}">
                            <i class="fa-solid fa-seedling"></i> {{ $soilMoisture }}%
                        </div>

                        <span id="badge-mist-{{ $index }}" class="text-[10px] px-2 py-0.5 rounded-md font-bold {{ $isOn ? 'bg-blue-200 text-blue-800' : 'bg-gray-200 text-gray-500' }}">
                            {{ $isOn ? 'ON' : 'OFF' }}
                        </span>
                    </button>
                @endforeach
            </div>

        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    let isManualMode = {{ $control->is_manual ? 'true' : 'false' }};
    
    // Default pilihan waktu aktif (dalam hitungan detik)
    let selectedDuration = 10;
    
    // Inisialisasi variabel sisa waktu hitung mundur pompa dari backend controller
    let mistRemaining = {!! json_encode($mistRemaining) !!}; 

    // Aksi 1: Modifikasi Status Pintu Gerbang Mode Operasional
    function toggleMode() {
        let checkbox = document.getElementById('modeSwitch');
        isManualMode = checkbox.checked;
        sendToServer({ is_manual: isManualMode ? 1 : 0 }, function() {
            location.reload(); 
        });
    }

    // Aksi 2: Pembaruan Sinyal PWM Kipas
    function sendFanData(pwmValue) {
        if (!isManualMode) return;
        
        document.querySelectorAll('.fan-btn').forEach(btn => {
            let btnVal = parseInt(btn.getAttribute('data-val'));
            if (Math.abs(pwmValue - btnVal) <= 25) {
                btn.className = "fan-btn relative py-3 rounded-xl border-2 font-bold transition-all text-xs border-gray-800 bg-gray-800 text-white shadow-md active-fan";
            } else {
                btn.className = "fan-btn relative py-3 rounded-xl border-2 font-bold transition-all text-xs border-gray-200 bg-white text-gray-600 hover:border-gray-400";
            }
        });

        let icon = document.getElementById('fanIcon');
        if(pwmValue > 0) {
            icon.classList.add('fa-spin');
        } else {
            icon.classList.remove('fa-spin');
        }

        sendToServer({ fan: pwmValue });
    }

    // Fungsi Baru: Mengganti Pilihan Waktu Timer Pompa (Highlight Switcher)
    function selectDuration(seconds) {
        if (!isManualMode) return;
        selectedDuration = seconds;
        
        document.querySelectorAll('.duration-btn').forEach(btn => {
            if (btn.id === 'duration-btn-' + seconds) {
                btn.className = "duration-btn px-4 py-1.5 rounded-lg text-xs font-black transition-all bg-gray-800 text-white shadow-sm border border-transparent";
            } else {
                btn.className = "duration-btn px-4 py-1.5 rounded-lg text-xs font-black transition-all text-gray-500 hover:text-gray-800 hover:bg-white/50";
            }
        });
    }

    // Aksi 3: Sakelar State Mist Maker dengan Manajemen Timer Kompleks
    function toggleMist(index, currentVal) {
        if (!isManualMode) return;

        let sendValue = currentVal === 0 ? 10 : 0;
        let duration = selectedDuration;

        let payload = { 
            mist_index: index, 
            mist_value: sendValue 
        };
        
        if(sendValue === 10) {
            payload.duration = duration;
        }

        sendToServer(payload, function() {
            if(sendValue === 10) {
                mistRemaining[index] = duration;
            } else {
                mistRemaining[index] = 0;
            }
            updateMistUI(index, sendValue, mistRemaining[index]);
        });
    }

    // Fungsi Pengendali Sinkronisasi Tampilan UI Rak Pompa
    function updateMistUI(index, val, secondsLeft) {
        let btn = document.getElementById('btn-mist-' + index);
        let badge = document.getElementById('badge-mist-' + index);
        let soilInfo = document.getElementById('soil-info-' + index);
        
        btn.setAttribute('onclick', `toggleMist(${index}, ${val})`);
        
        if (val === 10) {
            btn.className = "relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all border-blue-500 bg-blue-50 text-blue-600 shadow-sm";
            badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-200 text-blue-800";
            badge.innerText = `ON (${secondsLeft}s)`;
            soilInfo.className = "flex items-center gap-1 text-[10px] font-bold mb-2 text-blue-500";
        } else {
            btn.className = "relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all border-gray-200 bg-white text-gray-600 hover:border-blue-300";
            badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-200 text-gray-500";
            badge.innerText = "OFF";
            soilInfo.className = "flex items-center gap-1 text-[10px] font-bold mb-2 text-gray-400";
        }
    }

    // LIVE INTERACTIVE COUNTDOWN LOOP (Berjalan setiap 1 detik di Sisi Browser)
    setInterval(() => {
        for (let i = 0; i < 6; i++) {
            if (mistRemaining[i] > 0) {
                mistRemaining[i]--;
                
                let badge = document.getElementById('badge-mist-' + i);
                if (badge && badge.innerText.includes('ON')) {
                    badge.innerText = `ON (${mistRemaining[i]}s)`;
                }
                
                if (mistRemaining[i] === 0) {
                    updateMistUI(i, 0, 0);
                }
            }
        }
    }, 1000);

    // Render Awal saat Halaman Selesai Dimuat (Menjaga Sinkronisasi Jika Halaman Direfresh)
    document.addEventListener("DOMContentLoaded", () => {
        for (let i = 0; i < 6; i++) {
            if (mistRemaining[i] > 0) {
                updateMistUI(i, 10, mistRemaining[i]);
            }
        }
    });

    // Jembatan Komunikasi Utama AJAX Fetch API ke Laravel Backend
    function sendToServer(payload, onSuccess = null) {
        fetch('/web-control', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (response.status === 403) {
                alert('Akses Ditolak! Sistem telah dikunci oleh pengelola lain.');
                location.reload();
                throw new Error('Locked by another user');
            }
            return response.json();
        })
        .then(data => {
            console.log('Server Integration Success:', data);
            if(onSuccess) onSuccess();
        })
        .catch((error) => {
            console.error('Network or Guard Error:', error);
        });
    }
</script>
@endpush