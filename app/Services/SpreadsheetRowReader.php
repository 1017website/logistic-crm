<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;

class SpreadsheetRowReader
{
    /**
     * Read the first worksheet and map its headers to application field names.
     *
     * @param  array<string, array<int, string>>  $columns
     * @param  array<int, string>  $requiredColumns
     * @return array<int, array{row: int, data: array<string, mixed>}>
     */
    public function read(UploadedFile $file, array $columns, array $requiredColumns): array
    {
        try {
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            $reader->setReadDataOnly(true);

            if ($reader instanceof Csv) {
                $reader->setDelimiter($this->detectCsvDelimiter($file->getRealPath()));
            }

            $spreadsheet = $reader->load($file->getRealPath());
            $sheet = $spreadsheet->getActiveSheet();
            $lastRow = $sheet->getHighestDataRow();
            $lastColumn = $sheet->getHighestDataColumn();
            $values = $sheet->rangeToArray("A1:{$lastColumn}{$lastRow}", null, true, true, false);
            $spreadsheet->disconnectWorksheets();
        } catch (\Throwable $exception) {
            throw ValidationException::withMessages([
                'file' => 'File tidak dapat dibaca. Pastikan file Excel/CSV tidak rusak dan formatnya sesuai template.',
            ]);
        }

        if (empty($values)) {
            throw ValidationException::withMessages(['file' => 'File tidak memiliki header.']);
        }

        $headerRow = array_shift($values);
        $headerIndexes = [];
        foreach ($headerRow as $index => $header) {
            $normalized = $this->normalizeHeader($header);
            if ($normalized !== '') {
                $headerIndexes[$normalized] = $index;
            }
        }

        $fieldIndexes = [];
        foreach ($columns as $field => $aliases) {
            foreach (array_merge([$field], $aliases) as $alias) {
                $alias = $this->normalizeHeader($alias);
                if (array_key_exists($alias, $headerIndexes)) {
                    $fieldIndexes[$field] = $headerIndexes[$alias];
                    break;
                }
            }
        }

        $missing = array_values(array_filter(
            $requiredColumns,
            fn (string $field) => ! array_key_exists($field, $fieldIndexes)
        ));

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'file' => 'Kolom wajib tidak ditemukan: '.implode(', ', array_map(
                    fn (string $field) => $columns[$field][0] ?? $field,
                    $missing
                )).'. Gunakan file template yang tersedia.',
            ]);
        }

        $rows = [];
        foreach ($values as $offset => $row) {
            $mapped = [];
            foreach ($columns as $field => $aliases) {
                $mapped[$field] = array_key_exists($field, $fieldIndexes)
                    ? ($row[$fieldIndexes[$field]] ?? null)
                    : null;
            }

            if ($this->isEmptyRow($mapped)) {
                continue;
            }

            $rows[] = ['row' => $offset + 2, 'data' => $mapped];
        }

        return $rows;
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $value));
        $value = mb_strtolower($value);

        return trim((string) preg_replace('/[^a-z0-9]+/u', '_', $value), '_');
    }

    /** @param array<string, mixed> $row */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function detectCsvDelimiter(string $path): string
    {
        $line = (string) file_get_contents($path, false, null, 0, 4096);
        $line = strtok($line, "\r\n") ?: '';
        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
