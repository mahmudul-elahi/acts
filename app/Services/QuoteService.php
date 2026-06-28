<?php

namespace App\Services;

use App\Models\Quote;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class QuoteService
{
    public function __construct(private QuoteImportService $importer) {}

    /**
     * Create a single quote owned by the given user.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Quote
    {
        return $user->quotes()->create($data);
    }

    /**
     * Update an existing quote.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Quote $quote, array $data): Quote
    {
        $quote->update($data);

        return $quote;
    }

    /**
     * Delete a quote.
     */
    public function delete(Quote $quote): void
    {
        $quote->delete();
    }

    /**
     * Bulk import quotes for the given user from an uploaded spreadsheet.
     *
     * @return array{imported: int, skipped: int}
     */
    public function import(User $user, UploadedFile $file): array
    {
        return $this->importer->import($file, $user);
    }
}
