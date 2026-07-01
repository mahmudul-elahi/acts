<?php

namespace App\Http\Controllers\User\Murmuration;

use App\Enums\MurmurationPostType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Murmuration\StorePostRequest;
use App\Http\Resources\User\MurmurationPostResource;
use App\Models\MurmurationPost;
use App\Services\Murmuration\MurmurationPostService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Murmuration Posts')]
class PostController extends Controller
{
    public function __construct(private MurmurationPostService $posts) {}

    #[Endpoint(title: 'Murmuration Feed', description: 'Get the paginated community feed of active posts, newest first. Filter by topic with filter[topic]=topic-slug.')]
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $posts = QueryBuilder::for(
            MurmurationPost::query()->active()->with(['user', 'topic'])->withCount($this->viewerCounts($userId))
        )
            ->allowedFilters(AllowedFilter::scope('topic', 'topicSlug'))
            ->defaultSort('-created_at', '-id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(MurmurationPostResource::collection($posts));
    }

    #[Endpoint(title: 'Create Post', description: 'Publish a text, image or audio post. Audio is a premium feature. Send multipart/form-data with type, topic, optional body and (for image/audio) a media file up to 12 MB.')]
    public function store(StorePostRequest $request): JsonResponse
    {
        $user = $request->user();

        if (MurmurationPostType::from($request->validated('type'))->isPremium() && ! $user->hasPremiumAccess()) {
            return $this->errorResponse(
                message: 'Audio posts are a premium feature.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $post = $this->posts->create($user, $request->safe()->except('media'), $request->file('media'));

        return $this->createdResponse(
            data: new MurmurationPostResource($post),
            message: 'Post published successfully.',
        );
    }

    #[Endpoint(title: 'Saved Posts', description: 'Get the authenticated user\'s saved (bookmarked) posts.')]
    public function saved(Request $request): JsonResponse
    {
        $userId = $request->user()->getKey();

        $posts = MurmurationPost::query()->active()
            ->whereHas('savers', fn (Builder $query) => $query->whereKey($userId))
            ->with(['user', 'topic'])
            ->withCount($this->viewerCounts($userId))
            ->latest()
            ->latest('id')
            ->cursorPaginate(perPage: $this->perPage($request))
            ->withQueryString();

        return $this->cursorPaginatedResponse(MurmurationPostResource::collection($posts));
    }

    #[Endpoint(title: 'Show Post', description: 'Get a single post. Deactivated posts are only visible to their author.')]
    public function show(Request $request, MurmurationPost $post): JsonResponse
    {
        $userId = $request->user()->getKey();

        if (! $post->status && $post->user_id !== $userId) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        $post->load(['user', 'topic'])->loadCount($this->viewerCounts($userId));

        return $this->successResponse(data: new MurmurationPostResource($post));
    }

    #[Endpoint(title: 'Delete Post', description: 'Delete one of your own posts along with its media.')]
    public function destroy(Request $request, MurmurationPost $post): JsonResponse
    {
        if ($post->user_id !== $request->user()->getKey()) {
            return $this->errorResponse(
                message: 'You can only delete your own posts.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        $this->posts->delete($post);

        return $this->successResponse(message: 'Post deleted successfully.');
    }

    #[Endpoint(title: 'Like / Unlike Post', description: 'Toggle the authenticated user\'s like on a post.')]
    public function like(Request $request, MurmurationPost $post): JsonResponse
    {
        if (! $post->status) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        $liked = $this->posts->toggleLike($post, $request->user());

        return $this->successResponse(
            data: ['liked' => $liked, 'likes_count' => $post->likers()->count()],
            message: $liked ? 'Post liked.' : 'Post unliked.',
        );
    }

    #[Endpoint(title: 'Save / Unsave Post', description: 'Toggle the authenticated user\'s save (bookmark) on a post.')]
    public function save(Request $request, MurmurationPost $post): JsonResponse
    {
        if (! $post->status) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        $saved = $this->posts->toggleSave($post, $request->user());

        return $this->successResponse(
            data: ['saved' => $saved, 'saves_count' => $post->savers()->count()],
            message: $saved ? 'Post saved.' : 'Post removed from saved.',
        );
    }

    /**
     * The withCount definitions that add totals plus the viewer's like/save flags.
     *
     * @return array<int|string, mixed>
     */
    private function viewerCounts(int $userId): array
    {
        return [
            'comments',
            'likers',
            'likers as liked_by_user' => fn (Builder $query) => $query->whereKey($userId),
            'savers as saved_by_user' => fn (Builder $query) => $query->whereKey($userId),
        ];
    }
}
