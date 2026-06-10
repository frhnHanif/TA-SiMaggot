@extends('layouts.app')

@section('title', 'Panel Kontrol Aktuator')

@section('content')

@php
    // Mengambil data sensor terbaru langsung di View
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

    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 mb-6 flex justify-between items-center">
        <div>
            <h2 class="text-xl font-bold text-gray-800">Mode Operasional</h2>
            <p class="text-xs text-gray-500 mt-1">Tentukan siapa yang mengambil alih kendali aktuator.</p>
        </div>
        
        <label class="relative inline-flex items-center cursor-pointer select-none">
            <input type="checkbox" id="modeSwitch" class="sr-only peer" {{ $control->is_manual ? 'checked' : '' }} onchange="toggleMode()">
            <div class="w-14 h-7 bg-gray-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:left-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-red-500"></div>
            <span class="ml-3 text-sm sm:text-base font-black tracking-wider w-24 {{ $control->is_manual ? 'text-red-500' : 'text-gray-400' }}" id="modeLabel">
                {{ $control->is_manual ? 'MANUAL' : 'OTOMATIS' }}
            </span>
        </label>
    </div>

    <div id="autoAlert" class="bg-blue-50 border border-blue-200 text-blue-700 px-6 py-4 rounded-2xl mb-6 flex items-start gap-4 {{ $control->is_manual ? 'hidden' : 'flex' }}">
        <i class="fa-solid fa-robot text-2xl mt-1"></i>
        <div>
            <h4 class="font-bold">Sistem Berjalan Otomatis (Fuzzy Logic)</h4>
            <p class="text-sm mt-1">Saat ini ESP32 mengendalikan aktuator secara mandiri berdasarkan data sensor. Anda tidak dapat menekan tombol di bawah sebelum mengubah sakelar ke mode <strong>MANUAL</strong>.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 relative overflow-hidden flex flex-col">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center text-gray-600 shrink-0">
                    <i class="fa-solid fa-fan {{ $control->fan > 0 ? 'fa-spin' : '' }}" id="fanIcon"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">Kipas Exhaust</h3>
                    <p class="text-xs text-gray-500">Kendalikan kecepatan motor</p>
                </div>
            </div>

            <div class="flex gap-3 mb-5">
                <div class="flex-1 bg-amber-50/50 rounded-xl p-3 border border-amber-100 flex items-center gap-3">
                    <i class="fa-solid fa-temperature-half text-amber-500 text-lg"></i>
                    <div>
                        <p class="text-[10px] text-amber-600/70 font-bold uppercase tracking-wider mb-0.5">Suhu Udara</p>
                        <p class="text-lg font-black text-amber-600">{{ $temp }} <span class="text-xs font-normal">°C</span></p>
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
                        // Margin error 25 PWM
                        $isActive = abs($currentFan - $level['val']) <= 25;
                    @endphp
                    <button type="button" 
                        onclick="sendFanData({{ $level['val'] }})"
                        data-val="{{ $level['val'] }}"
                        class="fan-btn relative py-3 rounded-xl border-2 font-bold transition-all text-xs
                        {{ !$control->is_manual ? 'opacity-50 cursor-not-allowed border-gray-100 bg-gray-50 text-gray-400' : 
                            ($isActive ? 'border-gray-800 bg-gray-800 text-white shadow-md active-fan' : 'border-gray-200 bg-white text-gray-600 hover:border-gray-400') }}"
                        {{ !$control->is_manual ? 'disabled' : '' }}>
                        {{ $level['label'] }}
                    </button>
                @endforeach
            </div>
            <p class="text-[10px] text-center text-gray-400 mt-4">*Sistem mengirim nilai PWM (0-255) ke ESP32.</p>
        </div>

        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 flex flex-col">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 rounded-full bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                    <i class="fa-solid fa-cloud-showers-water"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800">Mist Maker Rak</h3>
                    <p class="text-xs text-gray-500">Nyalakan/Matikan pelembap substrat secara manual</p>
                </div>
            </div>

            <div class="grid grid-cols-3 gap-3 mt-auto">
                @php
                    $mistArray = is_array($control->mist) ? $control->mist : json_decode($control->mist, true) ?? [0,0,0,0,0,0];
                @endphp
                
                @foreach($mistArray as $index => $val)
                    @php 
                        $isOn = $val > 0; // Apapun angkanya (misal 10), selama > 0 dianggap ON
                        $soilMoisture = isset($soilData[$index]) ? rtrim(rtrim(number_format((float)$soilData[$index], 1), '0'), '.') : '--';
                    @endphp
                    <button type="button" 
                        id="btn-mist-{{ $index }}"
                        onclick="toggleMist({{ $index }}, {{ $val }})"
                        {{ !$control->is_manual ? 'disabled' : '' }}
                        class="relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all 
                        {{ !$control->is_manual ? 'opacity-50 cursor-not-allowed border-gray-100 bg-gray-50 text-gray-400' : 
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
            <div class="mt-4 bg-red-50 border border-red-100 p-3 rounded-xl text-center">
                <p class="text-[10px] text-red-600 font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i> Peringatan:</p>
                <p class="text-[10px] text-red-500 mt-0.5">Jangan lupa matikan kembali (OFF) agar media rak tidak kebanjiran.</p>
            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    let isManualMode = {{ $control->is_manual ? 'true' : 'false' }};

    // Aksi 1: Ubah Mode Manual/Otomatis
    function toggleMode() {
        let checkbox = document.getElementById('modeSwitch');
        isManualMode = checkbox.checked;
        sendToServer({ is_manual: isManualMode ? 1 : 0 }, function() {
            location.reload(); 
        });
    }

    // Aksi 2: Kirim Data Kipas via Tombol
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

    // Aksi 3: Klik Tombol Mist Maker (State Toggle 10 atau 0)
    function toggleMist(index, currentVal) {
        if (!isManualMode) return;

        // LOGIKA BARU: Jika saat ini ON (>0), maka kirim 0 (Matikan). 
        // Jika saat ini OFF (0), maka kirim 10 (Nyalakan).
        let sendValue = (currentVal > 0) ? 0 : 10; 

        // Kirim ke server
        sendToServer({ mist_index: index, mist_value: sendValue }, function() {
            // Optimistic Update UI
            let btn = document.getElementById('btn-mist-' + index);
            let badge = document.getElementById('badge-mist-' + index);
            let soilInfo = document.getElementById('soil-info-' + index);
            
            // Perbarui parameter onClick agar tombol bisa ditekan bolak-balik
            btn.setAttribute('onclick', `toggleMist(${index}, ${sendValue})`);
            
            if (sendValue > 0) {
                // Tampilan ON
                btn.className = "relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all border-blue-500 bg-blue-50 text-blue-600 shadow-sm";
                badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-md bg-blue-200 text-blue-800";
                badge.innerText = "ON";
                soilInfo.className = "flex items-center gap-1 text-[10px] font-bold mb-2 text-blue-500";
            } else {
                // Tampilan OFF
                btn.className = "relative flex flex-col items-center justify-center p-3 rounded-xl border-2 transition-all border-gray-200 bg-white text-gray-600 hover:border-blue-300";
                badge.className = "text-[10px] font-bold px-2 py-0.5 rounded-md bg-gray-200 text-gray-500";
                badge.innerText = "OFF";
                soilInfo.className = "flex items-center gap-1 text-[10px] font-bold mb-2 text-gray-400";
            }
        });
    }

    // Fungsi Utama AJAX (Kirim ke Laravel)
    function sendToServer(payload, onSuccess = null) {
        fetch('/web-control', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(data => {
            console.log('Success:', data);
            if(onSuccess) onSuccess();
        })
        .catch((error) => {
            console.error('Error:', error);
            alert('Gagal mengirim data ke server. Periksa koneksi internet Anda.');
        });
    }
</script>
@endpush