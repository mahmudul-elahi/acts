<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\Quote\UpdateQuoteRequest;
use App\Http\Resources\User\QuoteResource;
use App\Models\Quote;
use App\Services\Quote\QuoteService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Quotes')]
class QuoteController extends Controller
{
    public function __construct(private QuoteService $quotes) {}

    #[Endpoint(title: 'Quote Feed', description: "Get the paginated community feed of active quotes, newest first. Pass ?mine=1 for the user's own quotes (My Quotes tab) or filter[search]=term to search quote, author, and notes.")]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $base = Quote::query()->active()->withCount($this->viewerCounts($userId));

        if ($request->boolean('mine')) {
            $base->where('user_id', $userId);
        }

        $quotes = QueryBuilder::for($base)
            ->allowedFilters(AllowedFilter::scope('search'))
            ->defaultSort('-created_at', '-id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(QuoteResource::collection($quotes));
    }

    #[Endpoint(title: 'Create Quote', description: 'Publish a quote with its author and optional notes.')]
    public function store(StoreQuoteRequest $request): JsonResponse
    {
        $quote = $this->quotes->create($request->user(), $request->validated());

        return $this->createdResponse(
            data: new QuoteResource($quote),
            message: 'Quote published successfully.',
        );
    }

    #[Endpoint(title: 'Favorite Quotes', description: "Get the authenticated user's favorited quotes.")]
    public function favorites(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $quotes = Quote::query()->active()
            ->whereHas('favoriters', fn (Builder $query) => $query->whereKey($userId))
            ->withCount($this->viewerCounts($userId))
            ->latest()
            ->latest('id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(QuoteResource::collection($quotes));
    }

    #[Endpoint(title: 'Show Quote', description: 'Get a single quote. Deactivated quotes are only visible to their author.')]
    public function show(Request $request, Quote $quote): JsonResponse
    {
        $userId = $request->user()->getKey();

        if (! $quote->status && $quote->user_id !== $userId) {
            return $this->errorResponse(message: 'Quote not found.', status: Response::HTTP_NOT_FOUND);
        }

        $quote->loadCount($this->viewerCounts($userId));

        return $this->successResponse(data: new QuoteResource($quote));
    }

    #[Endpoint(title: 'Update Quote', description: 'Edit one of your own quotes.')]
    public function update(UpdateQuoteRequest $request, Quote $quote): JsonResponse
    {
        $userId = $request->user()->getKey();

        if ($quote->user_id !== $userId) {
            return $this->errorResponse(
                message: 'You can only edit your own quotes.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $quote = $this->quotes->update($quote, $request->validated());

        return $this->successResponse(
            data: new QuoteResource($quote->loadCount($this->viewerCounts($userId))),
            message: 'Quote updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Quote', description: 'Delete one of your own quotes.')]
    public function destroy(Request $request, Quote $quote): JsonResponse
    {
        if ($quote->user_id !== $request->user()->getKey()) {
            return $this->errorResponse(
                message: 'You can only delete your own quotes.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $this->quotes->delete($quote);

        return $this->successResponse(message: 'Quote deleted successfully.');
    }

    #[Endpoint(title: 'Favorite / Unfavorite Quote', description: "Toggle the authenticated user's favorite on a quote.")]
    public function favorite(Request $request, Quote $quote): JsonResponse
    {
        if (! $quote->status) {
            return $this->errorResponse(message: 'Quote not found.', status: Response::HTTP_NOT_FOUND);
        }

        $favorited = $this->quotes->toggleFavorite($quote, $request->user());

        return $this->successResponse(
            data: ['favorited' => $favorited, 'favorites_count' => $quote->favoriters()->count()],
            message: $favorited ? 'Quote favorited.' : 'Quote removed from favorites.',
        );
    }

    /**
     * The withCount definitions that add the favorites total plus the viewer's favorite flag.
     *
     * @return array<int|string, mixed>
     */
    private function viewerCounts(int $userId): array
    {
        return [
            'favoriters',
            'favoriters as favorited_by_user' => fn (Builder $query) => $query->whereKey($userId),
        ];
    }
}
