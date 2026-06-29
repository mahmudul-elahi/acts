<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkUploadQuoteRequest;
use App\Http\Requests\Admin\StoreQuoteRequest;
use App\Http\Requests\Admin\UpdateQuoteRequest;
use App\Http\Resources\Admin\QuoteResource;
use App\Models\Quote;
use App\Services\QuoteService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Content Management (Quotes)')]
class QuoteController extends Controller
{
    public function __construct(private QuoteService $quotes) {}

    #[Endpoint(title: 'List Quotes', description: 'Get a paginated list of quotes. Filter by status (filter[status]=active|inactive|all, defaults to all).')]
    public function index(Request $request): JsonResponse
    {
        $quotes = QueryBuilder::for(Quote::query())
            ->allowedFilters(
                AllowedFilter::scope('status'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(QuoteResource::collection($quotes));
    }

    #[Endpoint(title: 'Create Quote', description: 'Add a single quote.')]
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $quote = $this->quotes->create($request->user(), $request->validated());

        return $this->createdResponse(
            data: new QuoteResource($quote),
            message: 'Quote created successfully.',
        );
    }

    #[Endpoint(title: 'Bulk Upload Quotes', description: 'Upload a CSV/XLSX/XLS file to create many quotes at once.')]
    public function bulkUpload(BulkUploadQuoteRequest $request): JsonResponse
    {
        $result = $this->quotes->import($request->user(), $request->file('file'));

        return $this->successResponse(
            data: $result,
            message: "Imported {$result['imported']} quote(s), skipped {$result['skipped']}.",
        );
    }

    #[Endpoint(title: 'Show Quote', description: 'Get a single quote.')]
    public function show(Quote $quote): JsonResponse
    {
        return $this->successResponse(data: new QuoteResource($quote));
    }

    #[Endpoint(title: 'Update Quote', description: 'Update an existing quote.')]
    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        $quote = $this->quotes->update($quote, $request->validated());

        return $this->successResponse(
            data: new QuoteResource($quote),
            message: 'Quote updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Quote', description: 'Delete a quote.')]
    public function destroy(Quote $quote): JsonResponse
    {
        $this->quotes->delete($quote);

        return $this->successResponse(message: 'Quote deleted successfully.');
    }
}
