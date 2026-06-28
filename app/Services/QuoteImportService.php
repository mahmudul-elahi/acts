<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;

class QuoteImportService
{
    /**
     * Parse the uploaded spreadsheet and create quote records.
     *
     * Accepts CSV/XLSX/XLS with either a header row (columns: quote, author,
     * status, notes) or plain columns in that order. Rows without a quote are
     * skipped.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(UploadedFile $file, User $user): array
    {
        $rows = $this->readRows($file);

        if ($rows === []) {
            return ['imported' => 0, 'skipped' => 0];
        }

        $map = $this->resolveColumnMap($rows[0]);

        if ($map['has_header']) {
            array_shift($rows);
        }

        $imported = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $quote = $this->valueAt($row, $map['quote']);

            if (blank($quote)) {
                $skipped++;

                continue;
            }

            $user->quotes()->create([
                'quote' => Str::squish((string) $quote),
                'author' => $this->cleanAuthor($this->valueAt($row, $map['author'])),
                'status' => $this->toStatus($this->valueAt($row, $map['status'])),
                'notes' => $this->cleanNotes($this->valueAt($row, $map['notes'])),
            ]);

            $imported++;
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * Read every sheet row into a plain array.
     *
     * @return array<int, array<int, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $reader = $this->readerFor($file);
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($file->getRealPath());

        return $spreadsheet->getActiveSheet()->toArray();
    }

    private function readerFor(UploadedFile $file): IReader
    {
        $extension = Str::lower($file->getClientOriginalExtension() ?: (string) $file->guessExtension());

        return match ($extension) {
            'xlsx' => new XlsxReader,
            'xls' => new XlsReader,
            default => new CsvReader,
        };
    }

    /**
     * Map known columns to their index, detecting an optional header row.
     *
     * @param  array<int, mixed>  $firstRow
     * @return array{has_header: bool, quote: int, author: ?int, status: ?int, notes: ?int}
     */
    private function resolveColumnMap(array $firstRow): array
    {
        $headers = array_map(
            fn (mixed $cell): string => Str::lower(trim((string) $cell)),
            $firstRow,
        );

        if (in_array('quote', $headers, true)) {
            return [
                'has_header' => true,
                'quote' => (int) array_search('quote', $headers, true),
                'author' => $this->findHeader($headers, ['author']),
                'status' => $this->findHeader($headers, ['status']),
                'notes' => $this->findHeader($headers, ['notes', 'note']),
            ];
        }

        return [
            'has_header' => false,
            'quote' => 0,
            'author' => 1,
            'status' => 2,
            'notes' => 3,
        ];
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, string>  $candidates
     */
    private function findHeader(array $headers, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $headers, true);

            if ($index !== false) {
                return (int) $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function valueAt(array $row, ?int $index): mixed
    {
        if ($index === null) {
            return null;
        }

        return $row[$index] ?? null;
    }

    private function cleanAuthor(mixed $value): string
    {
        $author = Str::squish((string) ($value ?? ''));

        return $author !== '' ? $author : 'Unknown';
    }

    private function cleanNotes(mixed $value): ?string
    {
        $notes = Str::squish((string) ($value ?? ''));

        return $notes !== '' ? $notes : null;
    }

    private function toStatus(mixed $value): bool
    {
        if (blank($value)) {
            return true;
        }

        $normalized = Str::lower(trim((string) $value));

        return ! in_array($normalized, ['inactive', 'deactive', 'disabled', 'false', 'no', '0'], true);
    }
}
