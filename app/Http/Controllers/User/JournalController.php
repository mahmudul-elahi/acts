<?php

namespace App\Http\Controllers\User;

use App\Enums\JournalType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Journal\StoreJournalRequest;
use App\Http\Requests\Journal\UpdateJournalRequest;
use App\Http\Resources\User\JournalResource;
use App\Models\Journal;
use App\Services\Journal\JournalService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Journals')]
class JournalController extends Controller
{
    public function __construct(private JournalService $journals) {}

    #[Endpoint(title: 'Journal Feed', description: "Get the paginated community feed of active journals, newest first. Pass ?mine=1 for the user's own entries (Personal Journal tab), filter[tag]=tag-slug to scope to a tag, or filter[search]=term to search title, body, and tags.")]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $base = Journal::query()->active()->with(['user', 'tags'])->withCount($this->viewerCounts($userId));

        if ($request->boolean('mine')) {
            $base->where('user_id', $userId);
        }

        $journals = QueryBuilder::for($base)
            ->allowedFilters(
                AllowedFilter::scope('tag', 'tagSlug'),
                AllowedFilter::scope('search'),
            )
            ->defaultSort('-created_at', '-id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(JournalResource::collection($journals));
    }

    #[Endpoint(title: 'Create Journal', description: 'Publish a text, image or audio journal entry. Audio is a premium feature. Send multipart/form-data with type, title, optional body, optional tags[] and (for image/audio) a media file up to 12 MB.')]
    public function store(StoreJournalRequest $request): JsonResponse
    {
        $user = $request->user();

        if (JournalType::from($request->validated('type'))->isPremium() && ! $user->hasPremiumAccess()) {
            return $this->errorResponse(
                message: 'Audio entries are a premium feature.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $journal = $this->journals->create($user, $request->safe()->except('media'), $request->file('media'));

        return $this->createdResponse(
            data: new JournalResource($journal),
            message: 'Journal published successfully.',
        );
    }

    #[Endpoint(title: 'Favorite Journals', description: "Get the authenticated user's favorited journals.")]
    public function favorites(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $journals = Journal::query()->active()
            ->whereHas('favoriters', fn (Builder $query) => $query->whereKey($userId))
            ->with(['user', 'tags'])
            ->withCount($this->viewerCounts($userId))
            ->latest()
            ->latest('id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(JournalResource::collection($journals));
    }

    #[Endpoint(title: 'Show Journal', description: 'Get a single journal entry. Deactivated entries are only visible to their author.')]
    public function show(Request $request, Journal $journal): JsonResponse
    {
        $userId = $request->user()->getKey();

        if (! $journal->status && $journal->user_id !== $userId) {
            return $this->errorResponse(message: 'Journal not found.', status: Response::HTTP_NOT_FOUND);
        }

        $journal->load(['user', 'tags'])->loadCount($this->viewerCounts($userId));

        return $this->successResponse(data: new JournalResource($journal));
    }

    #[Endpoint(title: 'Update Journal', description: 'Edit one of your own journal entries. Audio entries require premium access. Existing media is kept unless a new file is supplied.')]
    public function update(UpdateJournalRequest $request, Journal $journal): JsonResponse
    {
        $user = $request->user();

        if ($journal->user_id !== $user->getKey()) {
            return $this->errorResponse(
                message: 'You can only edit your own journals.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if (JournalType::from($request->validated('type'))->isPremium() && ! $user->hasPremiumAccess()) {
            return $this->errorResponse(
                message: 'Audio entries are a premium feature.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $journal = $this->journals->update($journal, $request->safe()->except('media'), $request->file('media'));

        return $this->successResponse(
            data: new JournalResource($journal->loadCount($this->viewerCounts($user->getKey()))),
            message: 'Journal updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Journal', description: 'Delete one of your own journal entries along with its media.')]
    public function destroy(Request $request, Journal $journal): JsonResponse
    {
        if ($journal->user_id !== $request->user()->getKey()) {
            return $this->errorResponse(
                message: 'You can only delete your own journals.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $this->journals->delete($journal);

        return $this->successResponse(message: 'Journal deleted successfully.');
    }

    #[Endpoint(title: 'Favorite / Unfavorite Journal', description: "Toggle the authenticated user's favorite on a journal entry.")]
    public function favorite(Request $request, Journal $journal): JsonResponse
    {
        if (! $journal->status) {
            return $this->errorResponse(message: 'Journal not found.', status: Response::HTTP_NOT_FOUND);
        }

        $favorited = $this->journals->toggleFavorite($journal, $request->user());

        return $this->successResponse(
            data: ['favorited' => $favorited, 'favorites_count' => $journal->favoriters()->count()],
            message: $favorited ? 'Journal favorited.' : 'Journal removed from favorites.',
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
