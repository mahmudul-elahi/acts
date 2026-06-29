<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MurmurationPostResource;
use App\Models\MurmurationPost;
use App\Services\MurmurationPostService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Murmuration')]
class MurmurationPostController extends Controller
{
    public function __construct(private MurmurationPostService $posts) {}

    #[Endpoint(title: 'List Murmuration Posts', description: 'Get a paginated list of community posts for moderation. Filter by status (filter[status]=active|inactive) and topic (filter[topic]=topic-slug).')]
    public function index(Request $request): JsonResponse
    {
        $posts = QueryBuilder::for(MurmurationPost::query()->with(['user', 'topic'])->withCount(['comments', 'likers']))
            ->allowedFilters(
                AllowedFilter::scope('status'),
                AllowedFilter::scope('topic', 'topicSlug'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(MurmurationPostResource::collection($posts));
    }

    #[Endpoint(title: 'Show Murmuration Post', description: 'Get a single community post.')]
    public function show(MurmurationPost $murmurationPost): JsonResponse
    {
        $murmurationPost->load(['user', 'topic'])->loadCount(['comments', 'likers']);

        return $this->successResponse(data: new MurmurationPostResource($murmurationPost));
    }

    #[Endpoint(title: 'Toggle Post Status', description: 'Activate or deactivate a post. Deactivated posts are hidden from the feed.')]
    public function toggle(MurmurationPost $murmurationPost): JsonResponse
    {
        $this->posts->toggleStatus($murmurationPost);

        $murmurationPost->load(['user', 'topic'])->loadCount(['comments', 'likers']);

        return $this->successResponse(
            data: new MurmurationPostResource($murmurationPost),
            message: $murmurationPost->status ? 'Post activated successfully.' : 'Post deactivated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Murmuration Post', description: 'Permanently delete a community post and its media.')]
    public function destroy(MurmurationPost $murmurationPost): JsonResponse
    {
        $this->posts->delete($murmurationPost);

        return $this->successResponse(message: 'Post deleted successfully.');
    }
}
