<?php

namespace App\Http\Controllers\User\Murmuration;

use App\Http\Controllers\Controller;
use App\Http\Resources\MurmurationTopicResource;
use App\Services\MurmurationTopicService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Murmuration - Topics')]
class TopicController extends Controller
{
    public function __construct(private MurmurationTopicService $topics) {}

    #[Endpoint(title: 'Search Topics', description: 'Typeahead for the "Choose a Topic" field. Returns matching active topics; pass ?search= to filter. A new topic is created on the fly when a post is published with an unknown topic name.')]
    public function index(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('search', ''));

        return $this->successResponse(
            data: MurmurationTopicResource::collection($this->topics->search($term !== '' ? $term : null)),
        );
    }
}
