@extends('layouts.app')

@section('title', 'Manajemen Siklus Biopond')

@section('content')

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

    @if($activeCycle)
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 sm:p-8 mb-6 relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-amber-50 rounded-full blur-3xl opacity-60"></div>
            
            <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                <div>
                    <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1.5 rounded-full uppercase tracking-wider">Siklus Berjalan</span>
                    <h2 class="text-2xl font-bold text-gray-800 mt-2">{{ $activeCycle->batch_id }}</h2>
                    <p class="text-sm text-gray-500 mt-1">Dimulai sejak: {{ \Carbon\Carbon::parse($activeCycle->start_date)->translatedFormat('d F Y (H:i)') }}</p>
                </div>
                <div class="flex flex-wrap gap-2 w-full sm:w-auto">
                    <button onclick="openModal('modalPakan')" class="flex-1 sm:flex-initial bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-4 rounded-xl transition-colors text-sm flex items-center justify-center gap-2">
                        + Catat Pakan
                    </button>
                    <button onclick="openModal('modalPanen')" class="flex-1 sm:flex-initial bg-amber-500 hover:bg-amber-600 text-white font-bold py-2.5 px-5 rounded-xl transition-colors text-sm shadow-sm flex items-center justify-center gap-2">
                        Selesaikan Siklus (Panen)
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium">Bibit Awal</p>
                    <p class="text-xl font-bold text-gray-700 mt-1">{{ number_format($activeCycle->initial_seed_mass, 0, ',', '.') }} <span class="text-xs font-normal text-gray-400">gram</span></p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium">Total Input Sampah</p>
                    <p class="text-xl font-bold text-gray-700 mt-1">{{ number_format($activeCycle->total_waste_input, 0, ',', '.') }} <span class="text-xs font-normal text-gray-400">gram</span></p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium">Rata-rata Suhu</p>
                    <p class="text-xl font-bold text-gray-700 mt-1">{{ $avgTemp }} <span class="text-xs font-normal text-gray-400">&deg;C</span></p>
                </div>
                <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100">
                    <p class="text-xs text-gray-400 font-medium">Durasi Berjalan</p>
                    <p class="text-xl font-bold text-gray-700 mt-1">{{ $activeCycle->days_elapsed }} <span class="text-xs font-normal text-gray-400">Hari</span></p>
                </div>
            </div>

            <div class="bg-gradient-to-r from-amber-50 to-orange-50 border border-amber-200/60 rounded-2xl p-5 relative overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-3">
                    <div>
                        <h4 class="text-sm font-bold text-amber-900 flex items-center gap-2">
                            <span>📅</span> Prediksi Waktu Panen
                        </h4>
                        <p class="text-xs text-amber-700/80 mt-0.5">*Perhitungan otomatis berdasarkan model Accumulated Degree Days (ADD)</p>
                    </div>
                    <div class="text-left md:text-right">
                        @if($estimatedRemainingDays === 0)
                            <span class="bg-green-500 text-white text-xs font-black px-3 py-1 rounded-lg uppercase animate-pulse shadow-sm">Siap Dipanen (Fase Prepupa)</span>
                        @else
                            <p class="text-xs text-amber-800 font-medium">Estimasi Sisa Waktu:</p>
                            <p class="text-lg font-black text-amber-900"><span class="text-2xl">{{ $estimatedRemainingDays }}</span> Hari Lagi</p>
                        @endif
                    </div>
                </div>

                <div class="w-full bg-amber-200/40 rounded-full h-3 p-0.5 border border-amber-200">
                    <div class="bg-gradient-to-r from-amber-500 to-orange-500 h-2 rounded-full transition-all duration-500" style="width: {{ $addProgress }}%"></div>
                </div>
            </div>
                
                <div class="flex justify-between text-[11px] font-bold text-amber-800">
                    </div>
            </div>
        </div>
        @else
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 sm:p-8 mb-6 flex flex-col md:flex-row justify-between items-center gap-6 relative overflow-hidden">
            <div class="absolute -left-20 -top-20 w-64 h-64 bg-gray-50 rounded-full blur-3xl opacity-60 pointer-events-none"></div>
            
            <div class="flex items-center gap-5 relative z-10">
                <div class="w-16 h-16 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-3xl shrink-0">
                    <i class="fa-solid fa-leaf"></i>
                </div>
                <div>
                    <h2 class="text-2xl font-bold text-gray-800">Sistem Standby</h2>
                    <p class="text-sm text-gray-500 mt-1">Belum ada siklus budidaya yang berjalan di rak Biopond saat ini.</p>
                </div>
            </div>
            
            <button onclick="openModal('modalMulai')" class="w-full md:w-auto bg-amber-500 hover:bg-amber-600 text-white font-bold py-3.5 px-6 rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2 relative z-10">
                <i class="fa-solid fa-play"></i> Mulai Siklus Baru
            </button>
        </div>
    @endif

    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 overflow-hidden flex flex-col">
        <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-clock-rotate-left text-gray-400"></i> Riwayat Siklus (Log Batch)
                </h2>
                <p class="text-sm text-gray-500 mt-1">Data performa ECI dan WRI dari siklus yang telah selesai dipanen.</p>
            </div>
        </div>

        <div class="overflow-x-auto p-6" style="contain:layout style">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Batch ID</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Durasi</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 tracking-wider uppercase">Total Pakan</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 tracking-wider uppercase">Hasil Panen</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 tracking-wider uppercase">Efisiensi Biokonversi (ECI)</th>
                        <th class="px-6 py-4 text-left text-xs font-bold text-gray-500 tracking-wider uppercase">Indeks Biokonversi (WRI)</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($finishedCycles as $fc)
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="font-bold text-gray-800">{{ $fc->batch_id }}</span>
                        </td>
                        
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                            {{ $fc->days_elapsed }} Hari
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">
                            {{ number_format($fc->total_waste_input, 0, ',', '.') }} gram
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 font-medium">
                            {{ number_format($fc->harvest_mass, 0, ',', '.') }} gram
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-blue-600">{{ number_format($fc->eci_result, 1) }} %</span>
                        </td>

                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-bold text-emerald-600">{{ number_format($fc->wri_result, 1) }} %</span>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-400 text-sm">Belum ada riwayat siklus yang diselesaikan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div id="modalMulai" class="fixed inset-0 z-[100] bg-gray-900/60 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-xl overflow-hidden transform transition-all">
            <div class="bg-amber-500 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold text-lg flex items-center gap-2"><i class="fa-solid fa-play"></i> Mulai Siklus Baru</h3>
                <button onclick="closeModal('modalMulai')" class="text-amber-100 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            
            <form action="{{ url('/cycle/start') }}" method="POST" class="p-6">
                @csrf
                <p class="text-sm text-gray-600 mb-5">Masukkan berat bibit ke rak biopond yang akan digunakan.</p>
                
                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-3">Massa Bibit Awal per Rak</label>
                    
                    <button type="button" onclick="tarikDataSensor('bibit')" id="btnTarikBibit" class="mb-3 w-full bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 font-bold py-2 px-4 rounded-lg text-xs flex items-center justify-center gap-2 transition-colors">
                        <i class="fa-solid fa-satellite-dish"></i> <span>Tarik Berat Aktual dari Load Cell</span>
                    </button>

                    <div class="grid grid-cols-2 gap-3">
                        @for ($i = 1; $i <= 6; $i++)
                            <div>
                                <label class="block text-[10px] font-bold text-gray-500 mb-1">Rak {{ $i }}</label>
                                <div class="relative">
                                    <input type="number" step="1" name="bibit_rak[{{ $i }}]" min="0" placeholder="0" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg focus:ring-amber-500 focus:border-amber-500 block w-full p-2.5 pr-8 outline-none transition-colors">
                                    <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400 font-bold text-[10px]">g</div>
                                </div>
                            </div>
                        @endfor
                    </div>
                    <p class="text-[10px] text-gray-400 mt-3">*Kosongkan rak sebelum diberi bibit.</p>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('modalMulai')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-4 rounded-xl transition-colors text-sm">Batal</button>
                    <button type="submit" class="flex-1 bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-4 rounded-xl transition-colors shadow-md text-sm">Simpan & Mulai</button>
                </div>
            </form>
        </div>
    </div>

    @if($activeCycle)
    <div id="modalPakan" class="fixed inset-0 z-[100] bg-gray-900/60 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-sm shadow-xl overflow-hidden transform transition-all">
            <div class="px-6 pt-6 pb-4 flex justify-between items-center border-b border-gray-100">
                <h3 class="text-gray-800 font-bold text-lg flex items-center gap-2"><i class="fa-solid fa-plus text-amber-500"></i> Catat Pakan</h3>
                <button onclick="closeModal('modalPakan')" class="text-gray-400 hover:text-gray-600 transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form action="{{ route('cycle.addWaste') }}" method="POST" class="p-6">                
                @csrf

                <button type="button" onclick="tarikDataSensor('pakan')" id="btnTarikPakan" class="mb-4 w-full bg-blue-50 hover:bg-blue-100 text-blue-600 border border-blue-200 font-bold py-2 px-4 rounded-lg text-xs flex items-center justify-center gap-2 transition-colors">
                    <i class="fa-solid fa-satellite-dish"></i> <span>Hitung Otomatis (Tarik Delta dari Load Cell)</span>
                </button>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    @for ($i = 1; $i <= 6; $i++)
                        <div>
                            <label class="block text-[10px] font-bold text-gray-600 uppercase tracking-wider mb-1">Pakan Rak {{ $i }}</label>
                            <div class="relative">
                                <input type="number" step="1" name="pakan_rak[{{ $i }}]" min="0" placeholder="0" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 pr-8 outline-none">
                                <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-gray-400 font-bold text-xs">g</div>
                            </div>
                        </div>
                    @endfor
                </div>
                <p class="text-[10px] text-gray-400 mb-4">*Kosongkan rak yang tidak diberi tambahan pakan. Total akan diakumulasi otomatis dalam hitungan gram.</p>
                
                <button type="submit" class="w-full text-white bg-blue-600 hover:bg-blue-700 font-medium rounded-xl text-sm px-5 py-3 text-center transition-colors">
                    Simpan Data Pakan
                </button>
            </form>
        </div>
    </div>

    <div id="modalPanen" class="fixed inset-0 z-[100] bg-gray-900/60 backdrop-blur-sm hidden items-center justify-center p-4 transition-opacity">
        <div class="bg-white rounded-2xl w-full max-w-md shadow-xl overflow-hidden transform transition-all">
            <div class="bg-gray-800 px-6 py-4 flex justify-between items-center">
                <h3 class="text-white font-bold text-lg flex items-center gap-2"><i class="fa-solid fa-flag-checkered text-green-400"></i> Selesaikan Siklus</h3>
                <button onclick="closeModal('modalPanen')" class="text-gray-400 hover:text-white transition-colors"><i class="fa-solid fa-xmark text-xl"></i></button>
            </div>
            <form action="{{ url('/cycle/finish') }}" method="POST" class="p-6">
                @csrf
                <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 mb-6">
                    <p class="text-sm text-blue-800 font-medium">Anda akan mengakhiri <strong>Siklus {{ $activeCycle->batch_id }}</strong>. Sistem akan menghitung indeks efisiensi (WRI & ECI) berdasarkan input akhir ini.</p>
                </div>
                
                <div class="mb-4">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Total Panen Prepupa Aktual</label>
                    <div class="relative">
                        <input type="number" step="1" name="panen_aktual" placeholder="Otomatis ditarik dari sensor (Rak 7)" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-3 pr-12 outline-none transition-colors">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400 font-bold text-sm">g</div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">*Kosongkan form ini untuk menggunakan data pembacaan sensor Load Cell Rak 7 saat ini.</p>
                </div>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Total Sisa Kasgot (Pupuk)</label>
                    <div class="relative">
                        <input type="number" step="1" name="kasgot_aktual" placeholder="Otomatis ditarik dari sensor (Rak 1-6)" class="bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-3 pr-12 outline-none transition-colors">
                        <div class="absolute inset-y-0 right-0 flex items-center pr-4 pointer-events-none text-gray-400 font-bold text-sm">g</div>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">*Kosongkan form ini untuk menggunakan total sisa bobot di Load Cell Rak 1-6.</p>
                </div>

                <div class="flex gap-3">
                    <button type="button" onclick="closeModal('modalPanen')" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-3 px-4 rounded-xl transition-colors">Batal</button>
                    <button type="submit" class="flex-1 bg-gray-800 hover:bg-gray-900 text-white font-bold py-3 px-4 rounded-xl transition-colors shadow-md">Simpan & Selesai</button>
                </div>
            </form>
        </div>
    </div>
    @endif

@endsection

@push('scripts')
<script>
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if(modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if(modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // ==========================================
    // LOGIKA PENARIKAN DATA SENSOR ON-DEMAND
    // ==========================================
    let pollInterval;

    function tarikDataSensor(mode) {
        let btnId = mode === 'bibit' ? 'btnTarikBibit' : 'btnTarikPakan';
        let btn = document.getElementById(btnId);
        let originalHtml = btn.innerHTML;
        
        // Ubah tampilan tombol jadi loading
        btn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin"></i> <span>Menunggu ESP32 (Maks 10s)...</span>`;
        btn.disabled = true;

        // 1. Ambil data Baseline (Waktu & Berat saat tombol ditekan)
        fetch('/sensor/latest-json')
        .then(res => res.json())
        .then(baseline => {
            let oldTime = baseline.created_at;
            let baselineWeights = baseline.biopond || [0,0,0,0,0,0];

            // 2. Tembak Sinyal Force Update ke ESP32
            fetch('/sensor/force-update', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
            }).then(() => {
                
                // 3. Mulai Polling (Cek setiap 2 detik)
                pollInterval = setInterval(() => {
                    fetch('/sensor/latest-json')
                    .then(r => r.json())
                    .then(newData => {
                        // Jika ada data baru masuk dari ESP32
                        if (newData.created_at !== oldTime) {
                            clearInterval(pollInterval); // Hentikan polling
                            
                            let newWeights = newData.biopond || [0,0,0,0,0,0];

                            // Isi Inputan HTML
                            for(let i = 1; i <= 6; i++) {
                                let inputEl = document.querySelector(`input[name="${mode}_rak[${i}]"]`);
                                if(inputEl) {
                                    let weightGrams = 0;
                                    if(mode === 'bibit') {
                                        weightGrams = newWeights[i-1]; // Absolute
                                    } else if(mode === 'pakan') {
                                        weightGrams = newWeights[i-1] - baselineWeights[i-1]; // Delta
                                        if(weightGrams < 0) weightGrams = 0; // Cegah minus
                                    }
                                    
                                    // Semua diproses murni dalam satuan Gram Utuh (Tanpa Desimal)
                                    let finalVal = Math.round(weightGrams);
                                    
                                    // Hanya isi jika ada perubahan yang signifikan (>0)
                                    if(finalVal > 0) {
                                        inputEl.value = finalVal;
                                    }
                                }
                            }

                            // Kembalikan tombol ke semula
                            btn.innerHTML = `<i class="fa-solid fa-check text-green-500"></i> <span class="text-green-600">Berhasil Ditarik!</span>`;
                            setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 3000);
                        }
                    });
                }, 2000); // Cek per 2 detik
                
                // Timeout jika ESP32 mati/offline (Berhenti setelah 15 detik)
                setTimeout(() => {
                    clearInterval(pollInterval);
                    if(btn.disabled) {
                        btn.innerHTML = `<i class="fa-solid fa-triangle-exclamation text-red-500"></i> <span class="text-red-500">Gagal! ESP32 Offline</span>`;
                        setTimeout(() => { btn.innerHTML = originalHtml; btn.disabled = false; }, 3000);
                    }
                }, 15000);
            });
        });
    }
</script>
@endpush