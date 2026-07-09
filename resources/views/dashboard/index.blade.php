@extends('layouts.app')

@section('title', 'Dashboard SiMaggot')

@section('content')
    @if($latestData)
        <!-- ========================================== -->
        <!-- BAGIAN 1: KPI UTAMA (TOP CARDS)            -->
        <!-- ========================================== -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-6 mb-6">
            
            <!-- Suhu Udara -->
            <div class="bg-white rounded-[1.5rem] shadow-sm p-5 border border-gray-100 flex items-center gap-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-full bg-orange-50 text-orange-500 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-temperature-half"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-0.5">Suhu Udara</p>
                    <p class="text-2xl font-black text-gray-800">{{ number_format($latestData->temp, 2, ',', '.') }} <span class="text-sm font-medium text-gray-500">&deg;C</span></p>
                </div>
            </div>

            <!-- Kelembaban Udara -->
            <div class="bg-white rounded-[1.5rem] shadow-sm p-5 border border-gray-100 flex items-center gap-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-full bg-blue-50 text-blue-500 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-droplet"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-0.5">Kelembaban</p>
                    <p class="text-2xl font-black text-gray-800">{{ number_format($latestData->hum, 2, ',', '.') }} <span class="text-sm font-medium text-gray-500">%</span></p>
                </div>
            </div>

            <!-- Kelembaban Tanah (Avg) -->
            <div class="bg-white rounded-[1.5rem] shadow-sm p-5 border border-gray-100 flex items-center gap-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-seedling"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-0.5">Tanah (Avg)</p>
                    @php
                        $soilArray = is_array($latestData->soil) ? $latestData->soil : json_decode($latestData->soil, true) ?? [];
                        $avgSoil = count($soilArray) > 0 ? round(array_sum($soilArray) / count($soilArray), 1) : 0;
                    @endphp
                    <p class="text-2xl font-black text-gray-800">{{ $avgSoil }} <span class="text-sm font-medium text-gray-500">%</span></p>
                </div>
            </div>

            <!-- Amonia -->
            <div class="bg-white rounded-[1.5rem] shadow-sm p-5 border border-gray-100 flex items-center gap-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-full {{ $latestData->ammonia > config('maggot.thresholds.ammonia.max_safe') ? 'bg-red-50 text-red-500' : 'bg-green-50 text-green-500' }} flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-biohazard"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-0.5">Amonia</p>
                    <p class="text-2xl font-black {{ $latestData->ammonia > config('maggot.thresholds.ammonia.max_safe') ? 'text-red-600' : 'text-gray-800' }}">{{ $latestData->ammonia }} <span class="text-sm font-medium text-gray-500">ppm</span></p>
                </div>
            </div>

            <!-- Massa Total Maggot (Rak) -->
            <div class="bg-white rounded-[1.5rem] shadow-sm p-5 border border-gray-100 flex items-center gap-4 hover:shadow-md transition-shadow">
                <div class="w-12 h-12 rounded-full bg-purple-50 text-purple-500 flex items-center justify-center text-xl shrink-0">
                    <i class="fa-solid fa-weight-hanging"></i>
                </div>
                <div>
                    <p class="text-xs text-gray-500 font-bold uppercase tracking-wider mb-0.5">Massa Maggot</p>
                    @php
                        $biopondArray = is_array($latestData->biopond) ? $latestData->biopond : json_decode($latestData->biopond, true) ?? [];
                        $totalBerat = array_sum($biopondArray);
                    @endphp
                    <p class="text-2xl font-black text-gray-800">{{ number_format($totalBerat / 1000, 2, ',', '.') }} <span class="text-sm font-medium text-gray-500">kg</span></p>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- BAGIAN 1.5: SHORTCUT SIKLUS AKTIF (NEW)    -->
        <!-- ========================================== -->
        @auth
        <a href="/cycle" class="block bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-5 sm:p-6 mb-6 hover:shadow-md hover:border-amber-300 hover:ring-2 hover:ring-amber-50 transition-all group">
            @if(isset($activeCycle) && $activeCycle)
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 sm:gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-arrows-spin fa-spin" style="animation-duration: 3s;"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Siklus Aktif: {{ $activeCycle->batch_id }}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Klik untuk mengelola pakan atau panen siklus ini.</p>
                        </div>
                    </div>
                    
                    <div class="w-full md:flex-1 md:max-w-md mt-2 md:mt-0">
                        <div class="flex justify-between text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">
                            <span>Hari ke-{{ $activeCycle->days_elapsed }}</span>
                            @php $progress = min(100, ($activeCycle->days_elapsed / 21) * 100); @endphp
                            <span class="text-amber-600">{{ round($progress) }}% (Target 21 Hari)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-full rounded-full transition-all duration-1000" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>

                    <div class="hidden md:flex items-center justify-center w-8 h-8 rounded-full bg-gray-50 text-gray-400 group-hover:bg-amber-100 group-hover:text-amber-600 transition-colors shrink-0">
                        <i class="fa-solid fa-arrow-right -rotate-45 group-hover:rotate-0 transition-transform"></i>
                    </div>
                </div>
            @else
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-power-off"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Sistem Standby (Tidak Ada Siklus)</h3>
                            <p class="text-xs text-gray-500 mt-0.5">Klik kotak ini untuk masuk ke menu Manajemen Siklus dan memulai siklus baru.</p>
                        </div>
                    </div>
                    <div class="text-amber-500 font-bold text-sm bg-amber-50 px-4 py-2.5 rounded-xl flex items-center justify-center gap-2 group-hover:bg-amber-500 group-hover:text-white transition-colors">
                        Mulai Siklus Baru <i class="fa-solid fa-arrow-right"></i>
                    </div>
                </div>
            @endif
        </a>
        @endauth

        @guest
        <div class="block bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">
            @if(isset($activeCycle) && $activeCycle)
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4 sm:gap-6">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-amber-50 text-amber-500 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-arrows-spin fa-spin" style="animation-duration: 3s;"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Siklus Aktif: {{ $activeCycle->batch_id }}</h3>
                            <p class="text-xs text-gray-400 mt-1 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[10px]"></i> Live Progress 
                            </p>
                        </div>
                    </div>
                    
                    <div class="w-full md:flex-1 md:max-w-md mt-2 md:mt-0">
                        <div class="flex justify-between text-xs font-bold text-gray-400 mb-2 uppercase tracking-wider">
                            <span>Hari ke-{{ $activeCycle->days_elapsed }}</span>
                            @php $progress = min(100, ($activeCycle->days_elapsed / 21) * 100); @endphp
                            <span class="text-amber-600">{{ round($progress) }}% (Target 21 Hari)</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                            <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-full rounded-full transition-all duration-1000" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>
            @else
                <div class="flex flex-col md:flex-row justify-between md:items-center gap-4">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-xl shrink-0">
                            <i class="fa-solid fa-power-off"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Sistem Standby (Tidak Ada Siklus)</h3>
                            <p class="text-xs text-gray-400 mt-1 font-bold flex items-center gap-1">
                                <i class="fa-solid fa-lock text-[10px]"></i> Menunggu pengelola memulai siklus baru
                            </p>
                        </div>
                    </div>
                </div>
            @endif
        </div>
        @endguest

        <!-- ========================================== -->
        <!-- BAGIAN 2: DETAIL BIOPOND (MIDDLE)          -->
        <!-- ========================================== -->
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-5 sm:p-6 mb-6">
            <div class="flex justify-between items-center mb-5">
                <h3 class="text-lg font-bold text-gray-800">Status Rak Biopond</h3>
                <span class="text-xs font-bold bg-gray-100 text-gray-500 px-3 py-1 rounded-full"><i class="fa-regular fa-clock mr-1"></i> {{ $latestData->created_at->diffForHumans() }}</span>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
                @foreach($biopondArray as $index => $berat)
                    @php
                        $soilVal = $soilArray[$index] ?? null;
                        $soilDisplay = isset($soilVal) ? rtrim(rtrim(number_format((float)$soilVal, 2, ',', '.'), '0'), ',') : '--';
                        if ($berat >= 1000) {
                            $massDisplay = number_format($berat / 1000, 2, ',', '.');
                            $massUnit = 'kg';
                        } else {
                            $massDisplay = number_format(round($berat), 0, ',', '.');
                            $massUnit = 'g';
                        }
                    @endphp
                    <div class="bg-gray-50 rounded-2xl p-4 border border-gray-100 hover:border-amber-400 transition-colors group">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-xs font-black text-gray-800 uppercase tracking-wider">Rak {{ $index + 1 }}</span>
                            <i class="fa-solid fa-layer-group text-gray-300 group-hover:text-amber-400 transition-colors"></i>
                        </div>
                        
                        <!-- Massa -->
                        <div class="mb-3">
                            <span class="block text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-0.5">Massa Maggot</span>
                            <div class="flex items-baseline gap-1">
                                <span class="text-xl font-black text-gray-800">{{ $massDisplay }}</span>
                                <span class="text-xs text-gray-500 font-medium">{{ $massUnit }}</span>
                            </div>
                        </div>

                        <!-- Kelembaban Tanah -->
                        <div>
                            <span class="block text-[10px] text-gray-400 uppercase font-bold tracking-wider mb-1">Kelembaban Tanah</span>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-seedling text-amber-500 text-xs"></i>
                                <span class="text-sm font-bold text-gray-700">{{ $soilDisplay }} %</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1 mt-1.5 overflow-hidden">
                                <div class="bg-gradient-to-r from-amber-400 to-amber-500 h-1 rounded-full" style="width: {{ $soilArray[$index] ?? 0 }}%"></div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- ========================================== -->
        <!-- BAGIAN 3: BENTO PREVIEWS (BOTTOM)          -->
        <!-- ========================================== -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <a href="/statistik" class="block col-span-1 flex flex-col gap-3 group">
                @php
                    function formatMassDash($grams) {
                        // Database menyimpan dalam GRAM, konversi ke KG dulu
                        $kg = $grams / 1000;
                        if ($kg >= 1000) {
                            return number_format($kg / 1000, 2, ',', '.') . ' <span class="text-sm font-medium">ton</span>';
                        }
                        return number_format($kg, 2, ',', '.') . ' <span class="text-sm font-medium">kg</span>';
                    }
                @endphp

                <!-- Total Panen -->
                <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-[1.5rem] shadow-sm p-4 text-white flex items-center justify-between group-hover:-translate-y-1 group-hover:shadow-lg transition-all">
                    <div>
                        <p class="text-green-100 text-xs font-bold uppercase tracking-wider mb-1">Total Panen</p>
                        <h3 class="text-xl font-black">{!! formatMassDash($totalHarvest) !!}</h3>
                    </div>
                    <i class="fa-solid fa-box-open text-3xl opacity-30"></i>
                </div>

                <!-- Total Sampah Diolah -->
                <div class="bg-gradient-to-r from-amber-500 to-amber-600 rounded-[1.5rem] shadow-sm p-4 text-white flex items-center justify-between group-hover:-translate-y-1 group-hover:shadow-lg transition-all delay-75">
                    <div>
                        <p class="text-amber-100 text-xs font-bold uppercase tracking-wider mb-1">Sampah Diolah</p>
                        <h3 class="text-xl font-black">{!! formatMassDash($totalWasteInput) !!}</h3>
                    </div>
                    <i class="fa-solid fa-trash-can text-3xl opacity-30"></i>
                </div>

                <!-- Total Residu -->
                <div class="bg-gradient-to-r from-teal-500 to-teal-600 rounded-[1.5rem] shadow-sm p-4 text-white flex items-center justify-between group-hover:-translate-y-1 group-hover:shadow-lg transition-all delay-100">
                    <div>
                        <p class="text-teal-100 text-xs font-bold uppercase tracking-wider mb-1">Residu Kasgot</p>
                        <h3 class="text-xl font-black">{!! formatMassDash($totalResidue) !!}</h3>
                    </div>
                    <i class="fa-solid fa-seedling text-3xl opacity-30"></i>
                </div>
                
                <!-- WRI -->
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-[1.5rem] shadow-sm p-4 text-white flex items-center justify-between group-hover:-translate-y-1 group-hover:shadow-lg transition-all delay-150">
                    <div>
                        <p class="text-blue-100 text-xs font-bold uppercase tracking-wider mb-1">Rata-rata WRI</p>
                        <h3 class="text-xl font-black">{{ number_format($avgWri, 1, ',', '.') }} <span class="text-sm font-medium">%/hari</span></h3>
                    </div>
                    <i class="fa-solid fa-recycle text-3xl opacity-30"></i>
                </div>

                <!-- ECI -->
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-[1.5rem] shadow-sm p-4 text-white flex items-center justify-between group-hover:-translate-y-1 group-hover:shadow-lg transition-all delay-200">
                    <div>
                        <p class="text-purple-100 text-xs font-bold uppercase tracking-wider mb-1">Rata-rata ECI</p>
                        <h3 class="text-xl font-black">{{ number_format($avgEci, 1, ',', '.') }} <span class="text-sm font-medium">%</span></h3>
                    </div>
                    <i class="fa-solid fa-bug text-3xl opacity-30"></i>
                </div>
            </a>

            <!-- Kanan: Preview Logbook -->
            <div class="col-span-1 lg:col-span-2 bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 flex flex-col relative">
                <div class="flex justify-between items-center mb-5">
                    <div>
                        <h3 class="text-lg font-bold text-gray-800">Logbook Terbaru</h3>
                        <p class="text-xs text-gray-500 mt-0.5">5 Pembacaan telemetri terakhir dari ESP32</p>
                    </div>
                    <a href="/logbook" class="bg-gray-50 hover:bg-gray-100 text-gray-700 px-4 py-2 rounded-xl text-sm font-bold transition-colors border border-gray-200 flex items-center gap-2">
                        <span>Lihat Semua</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse min-w-[600px]">
                        <thead>
                            <tr class="border-b border-gray-100">
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider pl-2">Waktu</th>
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Suhu</th>
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Hum Udara</th>
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Hum Tanah (Avg)</th>
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Amonia</th>
                                <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider text-right pr-2">Total Massa Maggot</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($recentLogs as $log)
                                @php
                                    $biopondArray = is_array($log->biopond) ? $log->biopond : json_decode($log->biopond, true) ?? [];
                                    $totalBerat = array_sum($biopondArray) / 1000;
                                    
                                    $soilArray = is_array($log->soil) ? $log->soil : json_decode($log->soil, true) ?? [];
                                    $avgSoil = count($soilArray) > 0 ? array_sum($soilArray) / count($soilArray) : 0;
                                @endphp
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors group">
                                    <td class="py-3 pl-2 font-medium text-gray-800">
                                        {{ $log->created_at->format('H:i') }}
                                        <span class="text-xs text-gray-400 ml-1">{{ $log->created_at->format('d/m') }}</span>
                                    </td>
                                    <td class="py-3 text-gray-600">{{ number_format($log->temp, 2, ',', '.') }} &deg;C</td>
                                    <td class="py-3 text-gray-600">{{ number_format($log->hum, 2, ',', '.') }} %</td>
                                    <td class="py-3 text-gray-600">{{ number_format($avgSoil, 1, ',', '.') }} %</td>
                                    <td class="py-3">
                                        <span class="px-2 py-1 text-[10px] font-bold rounded-md {{ $log->ammonia > 30 ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                            {{ $log->ammonia }} ppm
                                        </span>
                                    </td>
                                    <td class="py-3 text-right pr-2 font-bold text-gray-700">
                                        {{ number_format($totalBerat, 2, ',', '.') }} kg
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-400 text-sm">Belum ada data terekam.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    @else
        <!-- State Kosong jika database benar-benar kosong -->
        <div class="bg-amber-50 border border-amber-200 rounded-[1.5rem] p-8 text-center max-w-lg mx-auto mt-10">
            <div class="w-20 h-20 bg-amber-100 text-amber-500 rounded-full flex items-center justify-center text-3xl mx-auto mb-4">
                <i class="fa-solid fa-satellite-dish"></i>
            </div>
            <h2 class="text-xl font-bold text-gray-800 mb-2">Menunggu Data Pertama...</h2>
            <p class="text-gray-500 text-sm">Sistem belum menerima paket telemetri apapun dari ESP32. Pastikan perangkat hardware sudah menyala dan terhubung ke internet.</p>
        </div>
    @endif
@endsection