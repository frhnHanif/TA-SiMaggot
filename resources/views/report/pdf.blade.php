<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Laporan Kinerja Biokonversi SiMaggot</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 2cm;
        }

        /* 
         * PERBAIKAN: 
         * Global reset (*) untuk margin dan padding dihapus karena menyebabkan 
         * engine PDF (seperti DomPDF) mengabaikan margin @page 2cm di atas.
         * Hanya box-sizing yang dipertahankan secara global.
         */
        *, *::before, *::after {
            box-sizing: border-box;
        }

        /* Reset margin dipindahkan secara spesifik ke elemen pembentuk layout */
        body, div, p, table, th, td {
            margin: 0;
        }

        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            padding: 0;
        }

        /* ── HEADER (halaman pertama saja) ─────────────────────── */
        .report-header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .report-header table {
            width: 100%;
            border: none;
            border-collapse: collapse;
        }
        .report-header table td {
            border: none;
            padding: 4px 8px;
            vertical-align: middle;
        }
        .report-header .logo-cell {
            width: 80px;
            text-align: center;
        }
        .report-header .logo-cell img {
            max-width: 65px;
            max-height: 65px;
        }
        .report-header .title-cell {
            text-align: center;
        }
        .report-header .title-main {
            font-size: 14pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .report-header .title-sub {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 2px;
        }
        .report-header .title-inst {
            font-size: 11pt;
            margin-top: 2px;
        }

        /* ── INFO BOX ─────────────────────────────────────────── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .info-table td {
            padding: 4px 8px;
            border: 1px solid #000;
            font-size: 11pt;
        }
        .info-table .info-label {
            width: 180px;
            font-weight: bold;
            background-color: #f0f0f0;
        }

        /* ── SECTION TITLE ────────────────────────────────────── */
        .section-title {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 18px;
            margin-bottom: 8px;
            padding-bottom: 2px;
            border-bottom: 1px solid #000;
        }

        /* ── DATA TABLE ───────────────────────────────────────── */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 11pt;
        }
        .data-table th,
        .data-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            vertical-align: top;
        }
        .data-table th {
            background-color: #e0e0e0;
            font-weight: bold;
            text-align: center;
        }
        .data-table td.number {
            text-align: right;
        }
        .data-table td.center {
            text-align: center;
        }
        .data-table .total-row {
            font-weight: bold;
            background-color: #f5f5f5;
        }
        .data-table .total-row td {
            border-top: 2px solid #000;
        }

        /* ── PARAGRAPH ────────────────────────────────────────── */
        p {
            margin-bottom: 6px;
            text-align: justify;
        }
        .note {
            font-size: 10pt;
            font-style: italic;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .note-block {
            font-size: 10pt;
            font-style: italic;
            padding: 8px 12px;
            border-left: 2px solid #000;
            margin: 8px 0;
        }

        /* ── FOOTER ───────────────────────────────────────────── */
        .page-footer {
            text-align: center;
            font-size: 9pt;
            margin-top: 16px;
            border-top: 1px solid #999;
            padding-top: 6px;
        }

        /* ── PAGE NUMBER ──────────────────────────────────────── */
        .pagenum:after {
            content: counter(page);
        }
        .pagecount:after {
            content: counter(pages);
        }
    </style>
</head>
<body>

    {{-- ================================================================ --}}
    {{-- HEADER LAPORAN --}}
    {{-- ================================================================ --}}
    <div class="report-header">
        <table>
            <tr>
                <td class="logo-cell">
                    <img src="{{ public_path('logo-undip.png') }}" alt="Logo UNDIP">
                </td>
                <td class="title-cell">
                    <div class="title-main">LAPORAN KINERJA BIOKONVERSI</div>
                    <div class="title-sub">SMART VERTICAL BIOPOND (SIMAGGOT)</div>
                    <div class="title-inst">TPST UNIVERSITAS DIPONEGORO</div>
                </td>
                <td class="logo-cell">
                    <img src="{{ public_path('icon.png') }}" alt="Logo SiMaggot">
                </td>
            </tr>
        </table>
    </div>

    {{-- INFO LAPORAN --}}
    <table class="info-table">
        <tr>
            <td class="info-label">Periode Laporan</td>
            <td>{{ $periode }}</td>
        </tr>
        <tr>
            <td class="info-label">Tanggal Cetak</td>
            <td>{{ $generated_at }}</td>
        </tr>
        <tr>
            <td class="info-label">URL Sistem</td>
            <td>{{ $public_url }}</td>
        </tr>
    </table>

    {{-- ================================================================ --}}
    {{-- RINGKASAN LAPORAN --}}
    {{-- ================================================================ --}}
    <div class="section-title">Ringkasan Laporan</div>

    <table class="data-table">
        <tr>
            <th style="width: 55%;">Parameter</th>
            <th style="width: 45%;">Nilai</th>
        </tr>
        <tr>
            <td>Total Sampah Organik Diolah</td>
            <td class="number">{{ number_format($totalWasteInputTon, 3, ',', '.') }} Ton</td>
        </tr>
        <tr>
            <td>Total Hasil Panen Maggot</td>
            <td class="number">{{ number_format($totalHarvestKg, 1, ',', '.') }} Kg</td>
        </tr>
        <tr>
            <td>Total Kasgot</td>
            <td class="number">{{ number_format($totalResidueKg, 1, ',', '.') }} Kg</td>
        </tr>
        <tr>
            <td>Jumlah Batch</td>
            <td class="number">{{ $totalBatch }} Batch</td>
        </tr>
        <tr>
            <td>Rata-rata WRI</td>
            <td class="number">{{ number_format($avgWri, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Rata-rata ECI</td>
            <td class="number">{{ number_format($avgEci, 2, ',', '.') }} %</td>
        </tr>
    </table>

    <p>Seluruh data diperoleh secara otomatis dari sistem Smart Vertical Biopond (SiMaggot) selama periode laporan. Data mencakup hasil pencatatan sensor IoT, pencatatan siklus budidaya, serta perhitungan indikator kinerja biokonversi yang tersimpan pada basis data sistem.</p>

    {{-- ================================================================ --}}
    {{-- REKAPITULASI PENGOLAHAN SAMPAH ORGANIK (WS.3) --}}
    {{-- ================================================================ --}}
    <div class="section-title">Rekapitulasi Pengolahan Sampah Organik (WS.3)</div>

    <table class="data-table">
        <tr>
            <th>Bulan</th>
            <th>Sampah Masuk (Kg)</th>
            <th>Hasil Panen (Kg)</th>
            <th>Kasgot (Kg)</th>
        </tr>
        @php
            $totalWasteMonthly = 0;
            $totalHarvestMonthly = 0;
            $totalResidueMonthly = 0;
        @endphp
        @forelse ($monthlyRecap as $month => $recap)
            @php
                $totalWasteMonthly += $recap['waste_input_kg'];
                $totalHarvestMonthly += $recap['harvest_kg'];
                $totalResidueMonthly += $recap['residue_kg'];
            @endphp
            <tr>
                <td class="center">{{ \Carbon\Carbon::createFromFormat('Y-m', $month)->translatedFormat('F Y') }}</td>
                <td class="number">{{ number_format($recap['waste_input_kg'], 1, ',', '.') }}</td>
                <td class="number">{{ number_format($recap['harvest_kg'], 1, ',', '.') }}</td>
                <td class="number">{{ number_format($recap['residue_kg'], 1, ',', '.') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4" class="center">Tidak ada data siklus pada periode ini.</td>
            </tr>
        @endforelse
        <tr class="total-row">
            <td class="center">Total</td>
            <td class="number">{{ number_format($totalWasteMonthly, 1, ',', '.') }}</td>
            <td class="number">{{ number_format($totalHarvestMonthly, 1, ',', '.') }}</td>
            <td class="number">{{ number_format($totalResidueMonthly, 1, ',', '.') }}</td>
        </tr>
    </table>

    <table class="data-table">
        <tr>
            <th style="width: 55%;">Indikator</th>
            <th style="width: 45%;">Nilai</th>
        </tr>
        <tr>
            <td>Total Waste Input</td>
            <td class="number">{{ number_format($totalWasteInputTon, 3, ',', '.') }} Ton</td>
        </tr>
        <tr>
            <td>Total Harvest</td>
            <td class="number">{{ number_format($totalHarvestKg, 1, ',', '.') }} Kg</td>
        </tr>
        <tr>
            <td>Total Residue</td>
            <td class="number">{{ number_format($totalResidueKg, 1, ',', '.') }} Kg</td>
        </tr>
        <tr>
            <td>Waste Reduction Index (WRI)</td>
            <td class="number">{{ number_format($avgWri, 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>Efficiency of Conversion of Ingested Feed (ECI)</td>
            <td class="number">{{ number_format($avgEci, 2, ',', '.') }} %</td>
        </tr>
    </table>

    {{-- ================================================================ --}}
    {{-- REKAPITULASI MONITORING LINGKUNGAN IoT (GD.6 & GD.7) --}}
    {{-- ================================================================ --}}
    <div class="section-title">Rekapitulasi Monitoring Lingkungan IoT (GD.6 &amp; GD.7)</div>

    <table class="data-table">
        <tr>
            <th>Parameter</th>
            <th>Minimum</th>
            <th>Maksimum</th>
            <th>Rata-rata</th>
        </tr>
        <tr>
            <td>Suhu Udara (&deg;C)</td>
            <td class="number">{{ $iotSummary['temp_min'] !== '-' ? number_format($iotSummary['temp_min'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['temp_max'] !== '-' ? number_format($iotSummary['temp_max'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['temp_avg'] !== '-' ? number_format($iotSummary['temp_avg'], 1, ',', '.') : '-' }}</td>
        </tr>
        <tr>
            <td>Kelembapan Udara (%)</td>
            <td class="number">{{ $iotSummary['hum_min'] !== '-' ? number_format($iotSummary['hum_min'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['hum_max'] !== '-' ? number_format($iotSummary['hum_max'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['hum_avg'] !== '-' ? number_format($iotSummary['hum_avg'], 1, ',', '.') : '-' }}</td>
        </tr>
        <tr>
            <td>Kelembapan Tanah (%)</td>
            <td class="number">{{ $iotSummary['soil_min'] !== '-' ? number_format($iotSummary['soil_min'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['soil_max'] !== '-' ? number_format($iotSummary['soil_max'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['soil_avg'] !== '-' ? number_format($iotSummary['soil_avg'], 1, ',', '.') : '-' }}</td>
        </tr>
        <tr>
            <td>Gas NH&#8323; (ppm)</td>
            <td class="number">{{ $iotSummary['ammonia_min'] !== '-' ? number_format($iotSummary['ammonia_min'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['ammonia_max'] !== '-' ? number_format($iotSummary['ammonia_max'], 1, ',', '.') : '-' }}</td>
            <td class="number">{{ $iotSummary['ammonia_avg'] !== '-' ? number_format($iotSummary['ammonia_avg'], 1, ',', '.') : '-' }}</td>
        </tr>
    </table>

    <p class="note-block">Data diperoleh secara otomatis dari perangkat IoT ESP32 yang terintegrasi dengan sistem Smart Vertical Biopond selama periode laporan.</p>

    {{-- ================================================================ --}}
    {{-- RIWAYAT SIKLUS BUDIDAYA --}}
    {{-- ================================================================ --}}
    <div class="section-title">Riwayat Siklus Budidaya</div>

    <table class="data-table">
        <tr>
            <th>No.</th>
            <th>Batch</th>
            <th>Mulai</th>
            <th>Panen</th>
            <th>Durasi</th>
            <th>Waste Input</th>
            <th>Harvest</th>
            <th>Status</th>
        </tr>
        @php $no = 1; @endphp
        @forelse ($cycleHistory as $cycle)
            <tr>
                <td class="center">{{ $no++ }}</td>
                <td class="center">{{ $cycle->batch_id }}</td>
                <td class="center">{{ \Carbon\Carbon::parse($cycle->start_date)->format('d/m/Y') }}</td>
                <td class="center">{{ $cycle->end_date ? \Carbon\Carbon::parse($cycle->end_date)->format('d/m/Y') : '-' }}</td>
                <td class="center">
                    @php
                        $durasi = $cycle->end_date
                            ? \Carbon\Carbon::parse($cycle->start_date)->diffInDays($cycle->end_date)
                            : '-';
                    @endphp
                    {{ $durasi !== '-' ? $durasi . ' hari' : '-' }}
                </td>
                <td class="number">{{ number_format($cycle->total_waste_input / 1000, 1, ',', '.') }} Kg</td>
                <td class="number">{{ $cycle->harvest_mass ? number_format($cycle->harvest_mass / 1000, 1, ',', '.') . ' Kg' : '-' }}</td>
                <td class="center">{{ ucfirst($cycle->status) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="8" class="center">Tidak ada data siklus pada periode ini.</td>
            </tr>
        @endforelse
    </table>

    @if ($cycleHistoryCount > 20)
        <p class="note">Data lengkap tersedia pada sistem SiMaggot.</p>
    @endif

    {{-- ================================================================ --}}
    {{-- KESESUAIAN DENGAN UI GREENMETRIC 2026 --}}
    {{-- ================================================================ --}}
    <div class="section-title">Kesesuaian dengan UI GreenMetric 2026</div>

    <table class="data-table">
        <tr>
            <th style="width: 12%;">Kategori</th>
            <th style="width: 30%;">Indikator</th>
            <th style="width: 58%;">Bukti pada Laporan</th>
        </tr>
        <tr>
            <td class="center">WS.3</td>
            <td>Pengolahan Sampah Organik</td>
            <td>Rekapitulasi pengolahan sampah organik</td>
        </tr>
        <tr>
            <td class="center">GD.2</td>
            <td>Website Keberlanjutan</td>
            <td>URL Sistem SiMaggot</td>
        </tr>
        <tr>
            <td class="center">GD.6</td>
            <td>Pemanfaatan TIK</td>
            <td>Rekapitulasi data monitoring digital</td>
        </tr>
        <tr>
            <td class="center">GD.7</td>
            <td>Implementasi IoT</td>
            <td>Rekapitulasi data sensor lingkungan</td>
        </tr>
    </table>

    {{-- ================================================================ --}}
    {{-- PENUTUP --}}
    {{-- ================================================================ --}}
    <div class="section-title">Penutup</div>

    <p>Laporan ini dihasilkan secara otomatis oleh Sistem Informasi Smart Vertical Biopond (SiMaggot) berdasarkan data operasional yang tersimpan pada basis data sistem. Dokumen ini digunakan sebagai bukti pendukung pelaksanaan pengelolaan sampah organik berbasis digital dan implementasi teknologi Internet of Things (IoT) di TPST Universitas Diponegoro.</p>

    {{-- ================================================================ --}}
    {{-- FOOTER --}}
    {{-- ================================================================ --}}
    <div class="page-footer">
        <div>Smart Vertical Biopond (SiMaggot)</div>
        <div>TPST Universitas Diponegoro</div>
    </div>

</body>
</html>