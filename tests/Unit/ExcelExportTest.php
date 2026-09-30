<?php

namespace Tests\Unit;

use App\Helpers\ExcelExport;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExcelExportTest extends TestCase
{
    public static function exportCases(): array
    {
        return [
            'single-letter columns' => [26, 'Z', true],
            'request DO columns' => [29, 'AC', true],
            'request DO without rows' => [29, 'AC', false],
            'columns beyond AZ' => [53, 'BA', true],
        ];
    }

    #[DataProvider('exportCases')]
    public function test_download_produces_readable_excel_with_all_columns(int $columnCount, string $lastColumn, bool $withRows): void
    {
        $headers = array_map(fn ($column) => "Header for column {$column}", range(1, $columnCount));
        $rows = $withRows ? [range(1, $columnCount), range(101, 100 + $columnCount)] : [];

        $response = ExcelExport::download('request-orders', $headers, $rows, 'Request DO');

        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $this->assertSame('attachment; filename="request-orders.xlsx"', $response->headers->get('Content-Disposition'));

        ob_start();
        try {
            $response->sendContent();
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $path = tempnam(sys_get_temp_dir(), 'excel-export-');
        $spreadsheet = null;
        try {
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertSame('Request DO', $sheet->getTitle());
            $this->assertSame($lastColumn, $sheet->getHighestColumn());
            $this->assertSame([$headers, ...$rows], $sheet->toArray(formatData: false));
            $this->assertTrue($sheet->getStyle("{$lastColumn}1")->getFont()->getBold());
            $this->assertSame('1E3A5F', $sheet->getStyle("{$lastColumn}1")->getFill()->getStartColor()->getRGB());

            // A saved width for every column verifies auto-sizing past Z.
            $this->assertCount($columnCount, $sheet->getColumnDimensions());
            foreach ($sheet->getColumnDimensions() as $dimension) {
                $this->assertGreaterThan(0, $dimension->getWidth());
            }

            if ($withRows) {
                $this->assertSame('F8FAFC', $sheet->getStyle("{$lastColumn}2")->getFill()->getStartColor()->getRGB());
                $this->assertSame(Border::BORDER_THIN, $sheet->getStyle("{$lastColumn}3")->getBorders()->getBottom()->getBorderStyle());
            }
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_download_applies_requested_vertical_merges(): void
    {
        $response = ExcelExport::download(
            'merged-rows',
            ['Request DO', 'Service'],
            [['RDO-001', 'Trucking'], ['RDO-001', 'Bongkar']],
            'Request DO',
            ['A2:A3']
        );

        ob_start();
        try {
            $response->sendContent();
            $content = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $path = tempnam(sys_get_temp_dir(), 'excel-merge-');
        $spreadsheet = null;
        try {
            file_put_contents($path, $content);
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getActiveSheet();

            $this->assertContains('A2:A3', array_values($sheet->getMergeCells()));
            $this->assertSame('RDO-001', $sheet->getCell('A2')->getValue());
            $this->assertSame('Trucking', $sheet->getCell('B2')->getValue());
            $this->assertSame('Bongkar', $sheet->getCell('B3')->getValue());
            $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('A2')->getBorders()->getTop()->getBorderStyle());
            $this->assertSame(Border::BORDER_THIN, $sheet->getStyle('A3')->getBorders()->getBottom()->getBorderStyle());
            $this->assertSame('9CA3AF', $sheet->getStyle('A2')->getBorders()->getLeft()->getColor()->getRGB());
        } finally {
            $spreadsheet?->disconnectWorksheets();
            unlink($path);
        }
    }
}
