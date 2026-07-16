@extends('layouts.app')

@section('title', 'Laporan')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    {{-- HEADER --}}
    <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-amber-100 mb-4">
            <i class="fa-solid fa-file-pdf text-amber-600 text-2xl"></i>
        </div>
        <h2 class="text-xl font-black text-gray-800">Laporan Kinerja Biokonversi</h2>
        <p class="text-sm text-gray-500 mt-1">
            Generate dokumen PDF resmi untuk keperluan UI GreenMetric & dokumentasi internal
        </p>
    </div>

    {{-- PILIH RENTANG TANGGAL --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-regular fa-calendar text-amber-500"></i> Pilih Periode Laporan
        </h3>

        <form method="POST" action="{{ route('report.generate') }}" id="reportForm">
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tanggal Mulai</label>
                    <input type="date" name="start_date" id="start_date" required
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition bg-gray-50"
                        value="{{ request('start_date', now()->subYear()->format('Y-m-d')) }}">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-600 mb-1">Tanggal Akhir</label>
                    <input type="date" name="end_date" id="end_date" required
                        class="w-full px-4 py-3 rounded-xl border border-gray-300 focus:ring-2 focus:ring-amber-500 focus:border-amber-500 transition bg-gray-50"
                        value="{{ request('end_date', now()->format('Y-m-d')) }}">
                </div>
            </div>

            <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-6 rounded-xl transition-all shadow-sm flex items-center justify-center gap-2 text-lg">
                <i class="fa-solid fa-download"></i> Generate & Unduh PDF
            </button>
        </form>
    </div>

    {{-- SHORTCUT TAHUNAN & BULANAN --}}
    <div class="bg-white rounded-2xl border border-gray-200 p-6">
        <h3 class="font-bold text-gray-700 mb-4 flex items-center gap-2">
            <i class="fa-solid fa-bolt text-amber-500"></i> Ekspor Cepat
        </h3>

        {{-- Tahunan --}}
        <div class="mb-4">
            <p class="text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wider">Tahunan</p>
            <div class="flex flex-wrap gap-2">
                @php
                    $currentYear = now()->year;
                    $years = range($currentYear, $currentYear - 2);
                @endphp
                @foreach ($years as $year)
                    <button type="button" onclick="quickExport('{{ $year }}-01-01', '{{ $year }}-12-31')"
                        class="px-4 py-2 bg-gray-100 hover:bg-amber-100 text-gray-700 hover:text-amber-700 rounded-xl text-sm font-medium transition border border-gray-200 hover:border-amber-300">
                        <i class="fa-solid fa-calendar-days mr-1"></i> {{ $year }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Bulanan (6 bulan terakhir) --}}
        <div>
            <p class="text-sm font-semibold text-gray-500 mb-2 uppercase tracking-wider">Bulanan</p>
            <div class="flex flex-wrap gap-2">
                @php
                    Carbon\Carbon::setLocale('id');
                @endphp
                @for ($i = 5; $i >= 0; $i--)
                    @php
                        $d = now()->subMonths($i)->startOfMonth();
                        $start = $d->copy()->format('Y-m-d');
                        $end = $d->copy()->endOfMonth()->format('Y-m-d');
                        $label = $d->translatedFormat('M Y');
                    @endphp
                    <button type="button" onclick="quickExport('{{ $start }}', '{{ $end }}')"
                        class="px-4 py-2 bg-gray-100 hover:bg-amber-100 text-gray-700 hover:text-amber-700 rounded-xl text-sm font-medium transition border border-gray-200 hover:border-amber-300">
                        <i class="fa-regular fa-calendar mr-1"></i> {{ $label }}
                    </button>
                @endfor
            </div>
        </div>
    </div>

    {{-- INFORMASI --}}
    <div class="bg-blue-50 border border-blue-200 rounded-2xl p-5">
        <div class="flex gap-3">
            <div class="shrink-0 mt-0.5">
                <i class="fa-solid fa-circle-info text-blue-500 text-lg"></i>
            </div>
            <div class="text-sm text-blue-800">
                <p class="font-semibold mb-1">Tentang Laporan Ini</p>
                <p class="text-blue-700">
                    Laporan Kinerja Biokonversi disusun sesuai standar UI GreenMetric 2026 sebagai dokumen evidence untuk kategori <strong>WS.3</strong> (Pengolahan Sampah Organik), <strong>GD.6</strong> (Pemanfaatan TIK), dan <strong>GD.7</strong> (Implementasi IoT). Format PDF menggunakan tata letak resmi dengan kop TPST Universitas Diponegoro.
                </p>
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
    function quickExport(startDate, endDate) {
        document.getElementById('start_date').value = startDate;
        document.getElementById('end_date').value = endDate;
        document.getElementById('reportForm').submit();
    }
</script>
@endpush
@endsection
