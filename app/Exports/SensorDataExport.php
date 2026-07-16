<?php

namespace App\Exports;

use App\Models\SensorData;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SensorDataExport
{
    protected $data;
    protected $startDate;
    protected $endDate;

    public function __construct($data, $startDate = null, $endDate = null)
    {
        $this->data = $data;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Generate a formatted Excel file and return as StreamedResponse.
     */
    public function download(): StreamedResponse
    {
        set_time_limit(120);

        $spreadsheet = new Spreadsheet();
        // Nonaktifkan pre-kalkulasi formula untuk hemat memori
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($spreadsheet)
            ->setCalculationCacheEnabled(false);
        $sheet = $spreadsheet->getActiveSheet();

        // ─── SETUP KOLOM ───────────────────────────────────────────
        // Kolom: A=Tanggal, B=Waktu, C=Suhu, D=Hum Udara,
        //        E..J=Hum Tanah R1..R6, K..P=Massa R1..R6,
        //        Q=Amonia, R=Total Massa
        $columns = [
            'A' => 'Tanggal',
            'B' => 'Waktu',
            'C' => 'Suhu (°C)',
            'D' => 'Hum Udara (%)',
            'E' => 'Hum Tanah R1 (%)',
            'F' => 'Hum Tanah R2 (%)',
            'G' => 'Hum Tanah R3 (%)',
            'H' => 'Hum Tanah R4 (%)',
            'I' => 'Hum Tanah R5 (%)',
            'J' => 'Hum Tanah R6 (%)',
            'K' => 'Massa R1 (g)',
            'L' => 'Massa R2 (g)',
            'M' => 'Massa R3 (g)',
            'N' => 'Massa R4 (g)',
            'O' => 'Massa R5 (g)',
            'P' => 'Massa R6 (g)',
            'Q' => 'Amonia (ppm)',
            'R' => 'Total Massa (kg)',
        ];

        $lastCol = 'R';
        $colKeys = array_keys($columns);

        // ═══════════════════════════════════════════════════════════
        // BARIS 1: JUDUL LAPORAN
        // ═══════════════════════════════════════════════════════════
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', 'LAPORAN DATA SENSOR SiMaggot');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 16,
                'color' => ['rgb' => '0F766E'], // teal-700
                'name' => 'Calibri',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'F0FDFA'], // teal-50
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // ═══════════════════════════════════════════════════════════
        // BARIS 2: SUBTITLE (Rentang Tanggal & Jumlah Data)
        // ═══════════════════════════════════════════════════════════
        $sheet->mergeCells("A2:{$lastCol}2");
        $periode = $this->buildPeriodeText();
        $downloadDate = now()->translatedFormat('j F Y, H:i');
        $subtitle = 'Periode: ' . $periode . ' | Total Data: ' . count($this->data) . ' record | Diunduh tanggal: ' . $downloadDate;
        $sheet->setCellValue('A2', $subtitle);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => [
                'size' => 10,
                'color' => ['rgb' => '6B7280'], // gray-500
                'name' => 'Calibri',
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(22);

        // ═══════════════════════════════════════════════════════════
        // BARIS 4: HEADER KOLOM (baris 3 dikosongkan sebagai spacer)
        // ═══════════════════════════════════════════════════════════
        $headerRow = 4;
        foreach ($columns as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }

        // Style header
        $headerStyle = $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}");
        $headerStyle->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 10,
                'color' => ['rgb' => 'FFFFFF'],
                'name' => 'Calibri',
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '0D9488'], // teal-600
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '0F766E'],
                ],
            ],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        // ═══════════════════════════════════════════════════════════
        // ISI DATA — tulis semua data dulu, lalu styling per range
        // ═══════════════════════════════════════════════════════════
        $row = $headerRow + 1;
        $ammoniaThreshold = config('maggot.thresholds.ammonia.max_safe', 20);

        $chunks = array_chunk(iterator_to_array($this->data), 2000);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $index => $record) {
                $biopondArray = is_array($record->biopond)
                    ? $record->biopond
                    : json_decode($record->biopond, true) ?? [];
                $soilArray = is_array($record->soil)
                    ? $record->soil
                    : json_decode($record->soil, true) ?? [];
                $totalBerat = array_sum($biopondArray) / 1000;

                // Tulis data per kolom (tanpa styling berat)
                $sheet->setCellValue("A{$row}", $record->created_at->format('d/m/Y'));
                $sheet->setCellValue("B{$row}", $record->created_at->format('H:i:s'));
                $sheet->setCellValue("C{$row}", (float) $record->temp);
                $sheet->setCellValue("D{$row}", (float) $record->hum);

                // Hum Tanah per rak (E..J)
                for ($i = 0; $i < 6; $i++) {
                    $colLetter = chr(69 + $i);
                    $val = $soilArray[$i] ?? null;
                    $sheet->setCellValue("{$colLetter}{$row}", $val !== null ? (float) $val : null);
                }

                // Massa per rak (K..P)
                for ($i = 0; $i < 6; $i++) {
                    $colLetter = chr(75 + $i);
                    $val = $biopondArray[$i] ?? null;
                    $sheet->setCellValue("{$colLetter}{$row}", $val !== null ? (float) $val : null);
                }

                // Amonia
                $sheet->setCellValue("Q{$row}", (float) $record->ammonia);

                // Total Massa
                $sheet->setCellValue("R{$row}", round($totalBerat, 2));

                $row++;
            }
            // Bersihkan memori setelah tiap chunk
            unset($chunk);
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }
        }

        $dataStartRow = $headerRow + 1;
        $dataEndRow   = $row - 1;

        // ── STYLING RANGE: Terapkan ke seluruh data sekaligus ──
        if ($dataEndRow >= $dataStartRow) {
            $dataRange = "A{$dataStartRow}:{$lastCol}{$dataEndRow}";
            $sheet->getStyle($dataRange)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'E5E7EB'],
                    ],
                ],
                'font' => [
                    'size' => 10,
                    'name' => 'Calibri',
                    'color' => ['rgb' => '374151'],
                ],
            ]);

            // Alignment: A-B center, C-R center
            $sheet->getStyle("A{$dataStartRow}:B{$dataEndRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$dataStartRow}:{$lastCol}{$dataEndRow}")->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Zebra striping per 2 baris (lebih hemat: gunakan conditional formatting)
            for ($r = $dataStartRow; $r <= $dataEndRow; $r += 2) {
                $bgRow = ($r - $dataStartRow) % 2 === 0;
                if (!$bgRow) {
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('F9FAFB');
                }
            }

            // Conditional: Amonia MERAH jika > threshold
            $redRows = [];
            $globalIndex = 0;
            foreach ($this->data as $record) {
                $currentRow = $dataStartRow + $globalIndex;
                if ((float) $record->ammonia > $ammoniaThreshold) {
                    $sheet->getStyle("Q{$currentRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'DC2626'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FEE2E2'],
                        ],
                    ]);
                }
                $globalIndex++;
            }
        }

        // Tinggi baris data (set per range, bukan per baris)
        if ($dataEndRow >= $dataStartRow) {
            for ($r = $dataStartRow; $r <= $dataEndRow; $r++) {
                $sheet->getRowDimension($r)->setRowHeight(22);
            }
        }

        // ═══════════════════════════════════════════════════════════
        // LEBAR KOLOM OTOMATIS
        // ═══════════════════════════════════════════════════════════
        foreach ($colKeys as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // ═══════════════════════════════════════════════════════════
        // FREEZE PANE: Header + 4 baris pertama tetap terlihat
        // ═══════════════════════════════════════════════════════════
        $sheet->freezePane('A' . ($headerRow + 1));

        // ═══════════════════════════════════════════════════════════
        // AUTO-FILTER pada header
        // ═══════════════════════════════════════════════════════════
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$headerRow}");

        // ═══════════════════════════════════════════════════════════
        // PRINT SETUP (agar rapi saat dicetak)
        // ═══════════════════════════════════════════════════════════
        $sheet->getPageSetup()
            ->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE)
            ->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageMargins()
            ->setTop(0.5)
            ->setBottom(0.5)
            ->setLeft(0.5)
            ->setRight(0.5);

        // ═══════════════════════════════════════════════════════════
        // RETURN SEBAGAI DOWNLOAD RESPONSE
        // ═══════════════════════════════════════════════════════════
        $filename = 'Laporan_SiMaggot_' . date('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Bangun teks periode dari filter tanggal.
     */
    protected function buildPeriodeText(): string
    {
        if ($this->startDate && $this->endDate) {
            return \Carbon\Carbon::parse($this->startDate)->translatedFormat('j F Y')
                . ' s/d '
                . \Carbon\Carbon::parse($this->endDate)->translatedFormat('j F Y');
        }

        if ($this->startDate) {
            return 'Sejak ' . \Carbon\Carbon::parse($this->startDate)->translatedFormat('j F Y');
        }

        if ($this->endDate) {
            return 'Sampai ' . \Carbon\Carbon::parse($this->endDate)->translatedFormat('j F Y');
        }

        return 'Semua Data';
    }
}
