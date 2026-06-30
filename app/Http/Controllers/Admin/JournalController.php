<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\JournalResource;
use App\Models\Journal;
use App\Services\JournalService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Journals')]
class JournalController extends Controller
{
    public function __construct(private JournalService $journals) {}

    #[Endpoint(title: 'List Journals', description: 'Get a paginated list of community journals for moderation. Filter by status (filter[status]=active|inactive), tag (filter[tag]=tag-slug), and search title/body/tags (filter[search]=term).')]
    public function index(Request $request): JsonResponse
    {
        $journals = QueryBuilder::for(Journal::query()->with(['user', 'tags'])->withCount('favoriters'))
            ->allowedFilters(
                AllowedFilter::scope('status'),
                AllowedFilter::scope('tag', 'tagSlug'),
                AllowedFilter::scope('search'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(JournalResource::collection($journals));
    }

    #[Endpoint(title: 'Show Journal', description: 'Get a single community journal entry.')]
    public function show(Journal $journal): JsonResponse
    {
        $journal->load(['user', 'tags'])->loadCount('favoriters');

        return $this->successResponse(data: new JournalResource($journal));
    }

    #[Endpoint(title: 'Toggle Journal Status', description: 'Activate or deactivate a journal. Deactivated journals are hidden from the feed.')]
    public function toggle(Journal $journal): JsonResponse
    {
        $this->journals->toggleStatus($journal);

        $journal->load(['user', 'tags'])->loadCount('favoriters');

        return $this->successResponse(
            data: new JournalResource($journal),
            message: $journal->status ? 'Journal activated successfully.' : 'Journal deactivated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Journal', description: 'Permanently delete a community journal and its media.')]
    public function destroy(Journal $journal): JsonResponse
    {
        $this->journals->delete($journal);

        return $this->successResponse(message: 'Journal deleted successfully.');
    }
}
