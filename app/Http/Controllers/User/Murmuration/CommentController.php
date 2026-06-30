<?php

namespace App\Http\Controllers\User\Murmuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Murmuration\StoreCommentRequest;
use App\Http\Resources\MurmurationCommentResource;
use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Services\MurmurationCommentService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('Murmuration - Comments')]
class CommentController extends Controller
{
    public function __construct(private MurmurationCommentService $comments) {}

    #[Endpoint(title: 'List Comments', description: 'Get a post\'s top-level comments, newest first, each with its single author reply (if any).')]
    public function index(Request $request, MurmurationPost $post): JsonResponse
    {
        $userId = $request->user()->getKey();

        if (! $post->status && $post->user_id !== $userId) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        $comments = $post->comments()->topLevel()
            ->with(['user', 'reply.user'])
            ->withCount([
                'likers',
                'likers as liked_by_user' => fn (Builder $query) => $query->whereKey($userId),
            ])
            ->latest()
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(MurmurationCommentResource::collection($comments));
    }

    #[Endpoint(title: 'Add Comment', description: 'Add a top-level comment to a post. Available to any member.')]
    public function store(StoreCommentRequest $request, MurmurationPost $post): JsonResponse
    {
        if (! $post->status) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        $comment = $this->comments->comment($post, $request->user(), $request->validated('body'));

        return $this->createdResponse(
            data: new MurmurationCommentResource($comment),
            message: 'Comment added.',
        );
    }

    #[Endpoint(title: 'Reply to Comment', description: 'Reply to a top-level comment. Only the post author may reply, and only once per comment.')]
    public function reply(StoreCommentRequest $request, MurmurationComment $comment): JsonResponse
    {
        $user = $request->user();
        $post = $comment->post;

        if (! $post || ! $post->status) {
            return $this->errorResponse(message: 'Post not found.', status: Response::HTTP_NOT_FOUND);
        }

        if ($post->user_id !== $user->getKey()) {
            return $this->errorResponse(
                message: 'Only the post author can reply to comments.',
                status: Response::HTTP_FORBIDDEN,
            );
        }

        if ($comment->parent_id !== null) {
            return $this->errorResponse(
                message: 'You can only reply to a top-level comment.',
                status: Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        if ($comment->reply()->exists()) {
            return $this->errorResponse(
                message: 'This comment already has a reply.',
                status: Response::HTTP_CONFLICT,
            );
        }

        $reply = $this->comments->reply($comment, $user, $request->validated('body'));

        return $this->createdResponse(
            data: new MurmurationCommentResource($reply),
            message: 'Reply added.',
        );
    }

    #[Endpoint(title: 'Like / Unlike Comment', description: 'Toggle the authenticated user\'s like on a comment.')]
    public function like(Request $request, MurmurationComment $comment): JsonResponse
    {
        $liked = $this->comments->toggleLike($comment, $request->user());

        return $this->successResponse(
            data: ['liked' => $liked, 'likes_count' => $comment->likers()->count()],
            message: $liked ? 'Comment liked.' : 'Comment unliked.',
        );
    }
}
