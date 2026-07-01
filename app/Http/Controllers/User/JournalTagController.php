<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\User\JournalTagResource;
use App\Services\Journal\JournalTagService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('User - Journal Tags')]
class JournalTagController extends Controller
{
    public function __construct(private JournalTagService $tags) {}

    #[Endpoint(title: 'Search Journal Tags', description: 'Typeahead for the "Journal Tags" field. Returns matching tags; pass ?search= to filter. New tags are created on the fly when a journal is published with an unknown tag name.')]
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search', ''));

        return $this->successResponse(
            data: JournalTagResource::collection($this->tags->search($term !== '' ? $term : null)),
        );
    }
}
