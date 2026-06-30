@extends('layouts.app')

@section('title', 'Statistik & Analitik Lanjutan')

@section('content')
    <!-- KPI Cards (Indikator Kinerja Utama) -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-gradient-to-r from-green-500 to-green-600 rounded-[1.5rem] shadow-sm p-6 text-white flex items-center justify-between hover:shadow-md transition-shadow">
            <div>
                <p class="text-green-100 text-sm font-bold uppercase tracking-wider mb-1">Total Akumulasi Panen</p>
                <h3 class="text-3xl font-black">{{ number_format($totalHarvest, 2, ',', '.') }} <span class="text-lg font-medium">kg</span></h3>
            </div>
            <i class="fa-solid fa-box-open text-5xl opacity-30"></i>
        </div>
        
        <div class="bg-gradient-to-r from-blue-500 to-blue-600 rounded-[1.5rem] shadow-sm p-6 text-white flex items-center justify-between hover:shadow-md transition-shadow">
            <div>
                <p class="text-blue-100 text-sm font-bold uppercase tracking-wider mb-1">Rata-rata Waste Reduction (WRI)</p>
                <h3 class="text-3xl font-black">{{ number_format($avgWri, 1, ',', '.') }} <span class="text-lg font-medium">%/hari</span></h3>
            </div>
            <i class="fa-solid fa-recycle text-5xl opacity-30"></i>
        </div>

        <div class="bg-gradient-to-r from-purple-500 to-purple-600 rounded-[1.5rem] shadow-sm p-6 text-white flex items-center justify-between hover:shadow-md transition-shadow">
            <div>
                <p class="text-purple-100 text-sm font-bold uppercase tracking-wider mb-1">Efisiensi Biokonversi (ECI)</p>
                <h3 class="text-3xl font-black">{{ number_format($avgEci, 1, ',', '.') }} <span class="text-lg font-medium">%</span></h3>
            </div>
            <i class="fa-solid fa-bug text-5xl opacity-30"></i>
        </div>
    </div>

    <!-- Filter Toggle -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6 gap-4">
        <h2 class="text-xl font-bold text-gray-800">Visualisasi Tren Data</h2>
        
        <!-- PERBAIKAN: Menggunakan Grid 2 kolom di HP agar tidak overflow, lalu flex memanjang di sm/laptop -->
        <div class="bg-gray-200 p-1.5 rounded-xl grid grid-cols-2 sm:flex w-full md:w-auto shadow-inner gap-1.5">
            <button onclick="updateCharts('daily')" id="btn-daily" class="filter-btn px-3 sm:px-6 py-2 text-xs sm:text-sm font-bold rounded-lg bg-white shadow-sm text-primary transition-all">Harian</button>
            <button onclick="updateCharts('weekly')" id="btn-weekly" class="filter-btn px-3 sm:px-6 py-2 text-xs sm:text-sm font-medium rounded-lg text-gray-500 hover:text-gray-800 transition-all">Mingguan</button>
            <button onclick="updateCharts('monthly')" id="btn-monthly" class="filter-btn px-3 sm:px-6 py-2 text-xs sm:text-sm font-medium rounded-lg text-gray-500 hover:text-gray-800 transition-all">Bulanan</button>
            <button onclick="updateCharts('yearly')" id="btn-yearly" class="filter-btn px-3 sm:px-6 py-2 text-xs sm:text-sm font-medium rounded-lg text-gray-500 hover:text-gray-800 transition-all">Tahunan</button>
        </div>
    </div>

    <!-- Area Grafik -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
        
        <!-- Grafik Suhu & Kelembaban (Dual Axis) -->
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 relative">
            <div id="loader-TempHum" class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 hidden flex justify-center items-center rounded-[1.5rem]"><i class="fa-solid fa-spinner fa-spin text-3xl text-primary"></i></div>
            <h3 class="text-lg font-bold text-gray-700 mb-2">Tren Suhu & Kelembaban Udara</h3>
            <p class="text-xs text-gray-400 mb-4" id="desc-TempHum">Pergerakan rata-rata per jam (24 jam terakhir)</p>
            <div id="chartTempHum" style="min-height:300px"></div>
        </div>

        <!-- Grafik Gas Amonia -->
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 relative">
            <div id="loader-Ammonia" class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 hidden flex justify-center items-center rounded-[1.5rem]"><i class="fa-solid fa-spinner fa-spin text-3xl text-primary"></i></div>
            <div class="flex justify-between items-center mb-2">
                <h3 class="text-lg font-bold text-gray-700">Fluktuasi Gas Amonia</h3>
                <span class="bg-red-100 text-red-700 text-xs font-bold px-2 py-1 rounded">Batas: 30 ppm</span>
            </div>
            <p class="text-xs text-gray-400 mb-4" id="desc-Ammonia">Pemantauan ambang batas bahaya gas</p>
            <div id="chartAmmonia" style="min-height:300px"></div>
        </div>

        <!-- Grafik Tren Total Massa -->
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6 relative">
            <div id="loader-TotalMass" class="absolute inset-0 bg-white/70 backdrop-blur-sm z-10 hidden flex justify-center items-center rounded-[1.5rem]"><i class="fa-solid fa-spinner fa-spin text-3xl text-primary"></i></div>
            <h3 class="text-lg font-bold text-gray-700 mb-2">Tren Pertumbuhan Massa Total</h3>
            <p class="text-xs text-gray-400 mb-4" id="desc-TotalMass">Akumulasi bobot keseluruhan (Kg) dari waktu ke waktu</p>
            <div id="chartTotalMass" style="min-height:300px"></div>
        </div>

        <!-- Grafik Distribusi Massa per Rak (Bar) -->
        <div class="bg-white rounded-[1.5rem] shadow-sm border border-gray-100 p-6">
            <h3 class="text-lg font-bold text-gray-700 mb-2">Distribusi Massa Terkini Per Rak</h3>
            <p class="text-xs text-gray-400 mb-4">Bobot maggot di 6 tingkat biopond saat ini</p>
            <div id="chartBiopondMass" style="min-height:300px"></div>
        </div>

    </div>

@endsection

@push('scripts')
{{-- ApexCharts: deferred agar tidak blocking render --}}
<script src="https://cdn.jsdelivr.net/npm/apexcharts" defer></script>
<script>
    // Inisialisasi Objek Chart secara global
    let chartTempHum, chartAmmonia, chartTotalMass, chartBiopondMass;

    // Data Awal dari Controller saat halaman pertama dimuat
    let timeLabels = {!! json_encode($timestamps) !!};
    let tempData = {!! json_encode($tempData) !!};
    let humData = {!! json_encode($humData) !!};
    let ammoniaData = {!! json_encode($ammoniaData) !!};
    let totalMassData = {!! json_encode($totalMassData) !!};
    let latestBiopond = {!! json_encode($latestBiopond) !!};
    
    // Perbaikan: Menampilkan Label Sumbu X agar rentang waktu terlihat jelas
    const commonOptions = {
        chart: { toolbar: { show: false }, zoom: { enabled: false }, fontFamily: 'inherit' },
        stroke: { curve: 'smooth', width: 2 },
        xaxis: { 
            categories: timeLabels, 
            labels: { 
                show: true, 
                rotate: -45, // Memiringkan teks agar tidak menumpuk
                style: { colors: '#9ca3af', fontSize: '11px', fontFamily: 'inherit' }
            }, 
            axisTicks: { show: true },
            tickAmount: 6 // Membatasi jumlah label yang tampil di bawah grafik
        },
        tooltip: {
            theme: 'light',
            x: { show: true }
        }
    };

    // Fungsi render semua grafik
    function renderAllCharts() {
        chartTempHum = new ApexCharts(document.querySelector("#chartTempHum"), {
            ...commonOptions,
            series: [
                { name: 'Suhu (&deg;C)', type: 'line', data: tempData },
                { name: 'Kelembaban (%)', type: 'line', data: humData }
            ],
            chart: { height: 300, type: 'line', toolbar: { show: false } },
            colors: ['#f97316', '#3b82f6'],
            yaxis: [
                { title: { text: 'Suhu (&deg;C)', style: { color: '#f97316', fontSize: '10px' } }, labels: { style: { colors: '#f97316' } } },
                { opposite: true, title: { text: 'Kelembaban (%)', style: { color: '#3b82f6', fontSize: '10px' } }, labels: { style: { colors: '#3b82f6' } } }
            ],
            legend: { position: 'top' }
        });
        chartTempHum.render();

        chartAmmonia = new ApexCharts(document.querySelector("#chartAmmonia"), {
            ...commonOptions,
            series: [{ name: 'Amonia (ppm)', data: ammoniaData }],
            chart: { height: 300, type: 'area', toolbar: { show: false } },
            colors: ['#ef4444'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.1, stops: [0, 90, 100] } },
            dataLabels: { enabled: false },
            annotations: { yaxis: [{ y: 30, borderColor: '#dc2626', label: { borderColor: '#dc2626', style: { color: '#fff', background: '#dc2626' }, text: 'Bahaya (>30 ppm)' } }] }
        });
        chartAmmonia.render();

        chartTotalMass = new ApexCharts(document.querySelector("#chartTotalMass"), {
            ...commonOptions,
            series: [{ name: 'Total Massa (kg)', data: totalMassData }],
            chart: { height: 300, type: 'area', toolbar: { show: false } },
            colors: ['#8b5cf6'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1, stops: [0, 90, 100] } },
            dataLabels: { enabled: false },
            yaxis: { labels: { formatter: (value) => { return value ? value.toFixed(1) + " kg" : "0 kg" } } }
        });
        chartTotalMass.render();

        chartBiopondMass = new ApexCharts(document.querySelector("#chartBiopondMass"), {
            series: [{ name: 'Massa (gram)', data: latestBiopond }],
            chart: { height: 300, type: 'bar', toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, horizontal: false, columnWidth: '50%', distributed: true } },
            dataLabels: { enabled: false },
            xaxis: { categories: ['Rak 1', 'Rak 2', 'Rak 3', 'Rak 4', 'Rak 5', 'Rak 6'] },
            colors: ['#38a169', '#319795', '#3182ce', '#805ad5', '#d53f8c', '#e53e3e'],
            legend: { show: false }
        });
        chartBiopondMass.render();
    }

    // Inisialisasi grafik setelah DOM & ApexCharts siap (defer script jalan sebelum DOMContentLoaded)
    document.addEventListener('DOMContentLoaded', renderAllCharts);

    // ==========================================
    // FUNGSI UPDATE GRAFIK DINAMIS (AJAX FETCH)
    // ==========================================
    function updateCharts(period) {
        // 1. Ubah warna tombol filter
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('bg-white', 'shadow-sm', 'text-primary', 'font-bold');
            btn.classList.add('text-gray-500', 'hover:text-gray-800', 'font-medium');
        });
        const activeBtn = document.getElementById('btn-' + period);
        activeBtn.classList.remove('text-gray-500', 'hover:text-gray-800', 'font-medium');
        activeBtn.classList.add('bg-white', 'shadow-sm', 'text-primary', 'font-bold');

        // 2. Ubah Deskripsi Waktu di UI & Tampilkan Loader
        const textDesc = period === 'daily' ? 'Pergerakan rata-rata per jam (24 jam terakhir)' : 
                         (period === 'weekly' ? 'Pergerakan rata-rata per hari (7 hari terakhir)' : 
                         (period === 'monthly' ? 'Pergerakan rata-rata per hari (30 hari terakhir)' : 
                         'Pergerakan rata-rata per bulan (1 tahun terakhir)'));
        document.getElementById('desc-TempHum').innerText = textDesc;
        document.getElementById('desc-Ammonia').innerText = textDesc;
        document.getElementById('desc-TotalMass').innerText = textDesc;
        
        document.querySelectorAll('[id^="loader-"]').forEach(el => el.classList.remove('hidden'));

        // 3. Ambil data baru dari Server API
        fetch(`/api/statistik-data?period=${period}`)
            .then(res => res.json())
            .then(data => {
                // Update Grafik Suhu Hum
                chartTempHum.updateOptions({ xaxis: { categories: data.timestamps } });
                chartTempHum.updateSeries([
                    { name: 'Suhu (&deg;C)', data: data.tempData },
                    { name: 'Kelembaban (%)', data: data.humData }
                ]);

                // Update Grafik Amonia
                chartAmmonia.updateOptions({ xaxis: { categories: data.timestamps } });
                chartAmmonia.updateSeries([{ name: 'Amonia (ppm)', data: data.ammoniaData }]);

                // Update Grafik Total Massa
                chartTotalMass.updateOptions({ xaxis: { categories: data.timestamps } });
                chartTotalMass.updateSeries([{ name: 'Total Massa (kg)', data: data.totalMassData }]);

                // Sembunyikan Loader
                document.querySelectorAll('[id^="loader-"]').forEach(el => el.classList.add('hidden'));
            })
            .catch(err => {
                console.error("Gagal mengambil data statistik", err);
                document.querySelectorAll('[id^="loader-"]').forEach(el => el.classList.add('hidden'));
            });
    }
</script>
@endpush