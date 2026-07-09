@extends('layouts.app')

@section('title', 'Riwayat Telemetri')

@section('content')
    <!-- ========================================== -->
    <!-- PANEL FILTER LAPORAN (collapsible)          -->
    <!-- ========================================== -->
    <div class="mb-6" x-data="{ filterOpen: {{ request()->has('start_date') || request()->has('end_date') ? 'true' : 'false' }} }">
        <!-- Tombol Toggle Filter -->
        <button @click="filterOpen = !filterOpen"
                class="w-full flex items-center justify-between bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-teal-50 text-teal-600 flex items-center justify-center">
                    <i class="fa-solid fa-filter"></i>
                </div>
                <div class="text-left">
                    <span class="font-bold text-gray-800 text-sm">Filter Laporan</span>
                    @if(request()->has('start_date') || request()->has('end_date'))
                        <span class="block text-[11px] text-teal-600 font-medium">Filter aktif</span>
                    @else
                        <span class="block text-[11px] text-gray-400">Semua data ditampilkan</span>
                    @endif
                </div>
            </div>
            <i class="fa-solid text-gray-400 transition-transform duration-200" :class="filterOpen ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
        </button>

        <!-- Isi Filter -->
        <div x-show="filterOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 mt-3">
            <form action="{{ url('/logbook') }}" method="GET" class="flex flex-col md:flex-row items-end gap-4">
                
                <!-- Tanggal Mulai -->
                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Tanggal Mulai:</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <i class="fa-regular fa-calendar"></i>
                        </div>
                        <input type="date" name="start_date" value="{{ request('start_date') }}" class="bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-xl focus:ring-teal-500 focus:border-teal-500 block w-full pl-10 p-2.5 outline-none transition-colors">
                    </div>
                </div>

                <!-- Tanggal Akhir -->
                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Tanggal Akhir:</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                            <i class="fa-regular fa-calendar"></i>
                        </div>
                        <input type="date" name="end_date" value="{{ request('end_date') }}" class="bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-xl focus:ring-teal-500 focus:border-teal-500 block w-full pl-10 p-2.5 outline-none transition-colors">
                    </div>
                </div>

                <!-- Pengurutan -->
                <div class="w-full md:w-1/4">
                    <label class="block text-xs font-bold text-gray-600 uppercase tracking-wider mb-2">Urutan Data:</label>
                    <select name="sort" class="bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-xl focus:ring-teal-500 focus:border-teal-500 block w-full p-2.5 outline-none transition-colors cursor-pointer">
                        <option value="desc" {{ request('sort') == 'desc' ? 'selected' : '' }}>Terbaru ke Terlama</option>
                        <option value="asc" {{ request('sort') == 'asc' ? 'selected' : '' }}>Terlama ke Terbaru</option>
                    </select>
                </div>

                <!-- Tombol Submit -->
                <div class="w-full md:w-1/4">
                    <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-2.5 px-4 rounded-xl transition-colors shadow-sm flex items-center justify-center gap-2">
                        <i class="fa-solid fa-magnifying-glass"></i> Terapkan Filter
                    </button>
                </div>
                
                <!-- Tombol Reset -->
                @if(request()->has('start_date') || request()->has('end_date'))
                    <a href="/logbook" class="w-full md:w-auto bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-2.5 px-4 rounded-xl transition-colors shadow-sm flex items-center justify-center">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                @endif
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- PANEL HASIL LAPORAN                        -->
    <!-- ========================================== -->
    <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 overflow-hidden flex flex-col" x-data="{ viewMode: (typeof window !== 'undefined' && window.innerWidth < 1024) ? 'card' : 'table', rows: {} }">
        
        <!-- Header Hasil & Tombol Export -->
        <div class="px-4 sm:px-6 py-4 sm:py-5 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h2 class="text-lg sm:text-xl font-bold text-gray-800 flex items-center gap-2">
                    <i class="fa-solid fa-list-check text-blue-600"></i> Hasil Laporan
                </h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Menampilkan {{ $sensors->total() }} data</p>
            </div>
            
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <!-- Toggle Tabel / Kartu -->
                <div class="bg-gray-100 p-1 rounded-xl flex gap-1 mr-auto sm:mr-0">
                    <button @click="viewMode = 'table'" 
                            :class="viewMode === 'table' ? 'bg-white shadow-sm text-gray-800 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-table-list text-[12px]"></i> Tabel
                    </button>
                    <button @click="viewMode = 'card'"
                            :class="viewMode === 'card' ? 'bg-white shadow-sm text-gray-800 font-bold' : 'text-gray-400 hover:text-gray-600 font-medium'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1.5">
                        <i class="fa-solid fa-rectangle-list text-[12px]"></i> Kartu
                    </button>
                </div>

                <!-- Export Excel -->
                <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-3 sm:px-4 rounded-xl transition-colors shadow-sm flex items-center gap-1.5 text-xs sm:text-sm shrink-0">
                    <i class="fa-solid fa-file-excel"></i> <span class="hidden sm:inline">Export Excel</span>
                </a>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODE TABEL                                 -->
        <!-- ========================================== -->
        <div x-show="viewMode === 'table'" class="overflow-x-auto" style="contain:layout style">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b-2 border-gray-100">
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider w-10"></th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider pl-2">Tanggal</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider hidden sm:table-cell">Waktu</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Suhu</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider hidden md:table-cell">Hum Udara</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Tanah (Avg)</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider">Amonia</th>
                        <th class="pb-3 text-xs font-black text-gray-400 uppercase tracking-wider text-right pr-2">Total Massa</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @forelse($sensors as $index => $log)
                        @php
                            $biopondArray = is_array($log->biopond) ? $log->biopond : json_decode($log->biopond, true) ?? [];
                            $totalBerat = array_sum($biopondArray) / 1000;
                            
                            $soilArray = is_array($log->soil) ? $log->soil : json_decode($log->soil, true) ?? [];
                            $avgSoil = count($soilArray) > 0 ? array_sum($soilArray) / count($soilArray) : 0;
                            $rowId = 'row-' . $log->id;
                        @endphp

                        <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors cursor-pointer group"
                            @click="rows['{{ $rowId }}'] = !rows['{{ $rowId }}']" style="contain:layout style">
                            <td class="py-4 pl-3">
                                <i class="fa-solid text-gray-400 transition-transform duration-200 text-xs"
                                   :class="rows['{{ $rowId }}'] ? 'fa-chevron-down rotate-0' : 'fa-chevron-right'"></i>
                            </td>
                            <td class="py-4 pl-2 font-medium text-gray-800">
                                {{ $log->created_at->translatedFormat('j F Y') }}
                                <span class="sm:hidden block text-[11px] text-gray-400 font-normal">{{ $log->created_at->format('H:i') }}</span>
                            </td>
                            <td class="py-4 text-gray-600 hidden sm:table-cell">{{ $log->created_at->format('H:i') }}</td>
                            <td class="py-4 text-gray-600">{{ number_format($log->temp, 2, ',', '.') }} &deg;C</td>
                            <td class="py-4 text-gray-600 hidden md:table-cell">{{ number_format($log->hum, 2, ',', '.') }} %</td>
                            <td class="py-4 text-gray-600">{{ number_format($avgSoil, 1, ',', '.') }} %</td>
                            <td class="py-3">
                                <span class="px-2 py-1 text-[10px] font-bold rounded-md {{ $log->ammonia > config('maggot.thresholds.ammonia.max_safe') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                                    {{ $log->ammonia }} ppm
                                </span>
                            </td>
                            <td class="py-4 text-right pr-2 font-bold text-gray-700">
                                {{ number_format($totalBerat, 2, ',', '.') }} kg
                            </td>
                        </tr>

                        <tr x-show="rows['{{ $rowId }}']" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="bg-gray-50/70" style="content-visibility:auto;contain-intrinsic-size:auto 120px">
                            <td colspan="8" class="p-2 sm:p-3">
                                <div class="grid grid-cols-3 lg:grid-cols-6 gap-1.5 sm:gap-2">
                                    @for ($i = 0; $i < 6; $i++)
                                        @php
                                            $colors = [
                                                ['bg', 'text', 'border', 'bar'],
                                                ['bg-green-50', 'text-green-600', 'border-green-200', 'bg-green-500'],
                                                ['bg-teal-50', 'text-teal-600', 'border-teal-200', 'bg-teal-500'],
                                                ['bg-blue-50', 'text-blue-600', 'border-blue-200', 'bg-blue-500'],
                                                ['bg-purple-50', 'text-purple-600', 'border-purple-200', 'bg-purple-500'],
                                                ['bg-pink-50', 'text-pink-600', 'border-pink-200', 'bg-pink-500'],
                                                ['bg-red-50', 'text-red-600', 'border-red-200', 'bg-red-500'],
                                            ];
                                            $c = $colors[$i + 1];
                                            $mass = $biopondArray[$i] ?? 0;
                                            $soil = $soilArray[$i] ?? null;
                                            $soilDisplay = isset($soil) ? rtrim(rtrim(number_format((float)$soil, 2, ',', '.'), '0'), ',') : null;
                                            if ($mass >= 1000) {
                                                $massDisplay = number_format($mass / 1000, 2, ',', '.');
                                                $massUnit = 'kg';
                                            } else {
                                                $massDisplay = number_format(round($mass), 0, ',', '.');
                                                $massUnit = 'g';
                                            }
                                        @endphp
                                        <div class="bg-white rounded-lg p-2 border {{ $c[1] }} flex flex-col">
                                            <span class="text-[9px] font-black text-gray-500 uppercase tracking-wider mb-0.5">Rak {{ $i + 1 }}</span>
                                            <span class="text-sm font-black text-gray-800">{{ $massDisplay }}<span class="text-[9px] font-medium text-gray-400 ml-0.5">{{ $massUnit }}</span></span>
                                            @if (isset($soil))
                                                <div class="flex items-center gap-1 mt-0.5">
                                                    <div class="flex-1 bg-gray-200 rounded-full h-1 overflow-hidden">
                                                        <div class="{{ $c[3] }} h-1 rounded-full" style="width: {{ $soil }}%"></div>
                                                    </div>
                                                    <span class="text-[9px] font-bold text-gray-500 w-7 text-right">{{ $soilDisplay }}%</span>
                                                </div>
                                            @else
                                                <span class="text-[9px] text-gray-300 mt-0.5">--</span>
                                            @endif
                                        </div>
                                    @endfor
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center">
                                <div class="text-gray-300 mb-3"><i class="fa-solid fa-folder-open text-4xl"></i></div>
                                <p class="text-gray-500 font-medium">Tidak ada data ditemukan pada rentang waktu tersebut.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- ========================================== -->
        <!-- MODE KARTU                                 -->
        <!-- ========================================== -->
        <div x-show="viewMode === 'card'" class="p-3 sm:p-4 space-y-3">
            @forelse($sensors as $log)
                @php
                    $biopondArray = is_array($log->biopond) ? $log->biopond : json_decode($log->biopond, true) ?? [];
                    $totalBerat = array_sum($biopondArray) / 1000;
                    $soilArray = is_array($log->soil) ? $log->soil : json_decode($log->soil, true) ?? [];
                    $avgSoil = count($soilArray) > 0 ? array_sum($soilArray) / count($soilArray) : 0;
                    $cardBg = $loop->odd ? 'bg-white' : 'bg-blue-50/50';
                    $cardBorder = $loop->odd ? 'border-gray-100' : 'border-blue-200';
                @endphp
                <div class="{{ $cardBg }} rounded-xl p-3 sm:p-4 border {{ $cardBorder }}" style="content-visibility:auto;contain-intrinsic-size:auto 200px">
                    <!-- Header: Tanggal & Waktu -->
                    <div class="flex justify-between items-center mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-black text-gray-800">{{ $log->created_at->translatedFormat('j F Y') }}</span>
                            <span class="text-xs text-gray-400">{{ $log->created_at->format('H:i') }}</span>
                        </div>
                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-md {{ $log->ammonia > config('maggot.thresholds.ammonia.max_safe') ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' }}">
                            NH₃ {{ $log->ammonia }} ppm
                        </span>
                    </div>

                    <!-- Row Metrik -->
                    <div class="grid grid-cols-4 gap-2 mb-3">
                        <div class="bg-white rounded-lg p-2 text-center border border-gray-100">
                            <span class="block text-[9px] text-gray-400 uppercase font-bold mb-0.5">Suhu</span>
                            <span class="text-sm font-black text-gray-800">{{ number_format($log->temp, 2, ',', '.') }}&deg;</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center border border-gray-100">
                            <span class="block text-[9px] text-gray-400 uppercase font-bold mb-0.5">Hum Udara</span>
                            <span class="text-sm font-black text-gray-800">{{ number_format($log->hum, 2, ',', '.') }}%</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center border border-gray-100">
                            <span class="block text-[9px] text-gray-400 uppercase font-bold mb-0.5">Tanah Avg</span>
                            <span class="text-sm font-black text-gray-800">{{ number_format($avgSoil, 1, ',', '.') }}%</span>
                        </div>
                        <div class="bg-white rounded-lg p-2 text-center border border-gray-100">
                            <span class="block text-[9px] text-gray-400 uppercase font-bold mb-0.5">Total Massa</span>
                            <span class="text-sm font-black text-gray-800">{{ number_format($totalBerat, 1, ',', '.') }}<span class="text-[9px] font-medium text-gray-400">kg</span></span>
                        </div>
                    </div>

                    <!-- Grid Biopond 3x2 -->
                    <div class="grid grid-cols-3 gap-1.5">
                        @for ($i = 0; $i < 6; $i++)
                            @php
                                $colors = [
                                    ['bg', 'text', 'border', 'bar'],
                                    ['bg-green-50', 'text-green-600', 'border-green-200', 'bg-green-500'],
                                    ['bg-teal-50', 'text-teal-600', 'border-teal-200', 'bg-teal-500'],
                                    ['bg-blue-50', 'text-blue-600', 'border-blue-200', 'bg-blue-500'],
                                    ['bg-purple-50', 'text-purple-600', 'border-purple-200', 'bg-purple-500'],
                                    ['bg-pink-50', 'text-pink-600', 'border-pink-200', 'bg-pink-500'],
                                    ['bg-red-50', 'text-red-600', 'border-red-200', 'bg-red-500'],
                                ];
                                $c = $colors[$i + 1];
                                $mass = $biopondArray[$i] ?? 0;
                                $soil = $soilArray[$i] ?? null;
                                $soilDisplay = isset($soil) ? rtrim(rtrim(number_format((float)$soil, 2, ',', '.'), '0'), ',') : null;
                                if ($mass >= 1000) {
                                    $massDisplay = number_format($mass / 1000, 2, ',', '.');
                                    $massUnit = 'kg';
                                } else {
                                    $massDisplay = number_format(round($mass), 0, ',', '.');
                                    $massUnit = 'g';
                                }
                            @endphp
                            <div class="bg-white rounded-lg p-1.5 border {{ $c[1] }} flex flex-col">
                                <span class="text-[8px] font-black text-gray-500 uppercase tracking-wider mb-0.5">Rak {{ $i + 1 }}</span>
                                <span class="text-xs font-black text-gray-800">{{ $massDisplay }}<span class="text-[8px] text-gray-400 ml-0.5">{{ $massUnit }}</span></span>
                                @if (isset($soil))
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <div class="flex-1 bg-gray-200 rounded-full h-1 overflow-hidden">
                                            <div class="{{ $c[3] }} h-1 rounded-full" style="width: {{ $soil }}%"></div>
                                        </div>
                                        <span class="text-[8px] font-bold text-gray-500 w-6 text-right">{{ $soilDisplay }}%</span>
                                    </div>
                                @endif
                            </div>
                        @endfor
                    </div>
                </div>
            @empty
                <div class="py-12 text-center">
                    <div class="text-gray-300 mb-3"><i class="fa-solid fa-folder-open text-4xl"></i></div>
                    <p class="text-gray-500 font-medium">Tidak ada data ditemukan pada rentang waktu tersebut.</p>
                </div>
            @endforelse
        </div>

        <!-- Pagination (Halaman) -->
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50 flex items-center justify-between">
            <span class="text-sm text-gray-500">Halaman {{ $sensors->currentPage() }} dari {{ $sensors->lastPage() }}</span>
            <div class="flex items-center gap-2">
                {{ $sensors->links('pagination::tailwind') }}
            </div>
        </div>
        
    </div>
@endsection