<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdRequest;
use App\Http\Requests\Admin\UpdateAdRequest;
use App\Http\Resources\Admin\AdResource;
use App\Models\Ad;
use App\Services\AdService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Ad Management')]
class AdController extends Controller
{
    public function __construct(private AdService $ads) {}

    #[Endpoint(title: 'List Ads', description: 'Get a paginated list of ads. Filter by run state (filter[status]=live|paused|all, defaults to all) and publish date (filter[publish_date]=2026-04-29).')]
    public function index(Request $request): JsonResponse
    {
        $ads = QueryBuilder::for(Ad::query())
            ->allowedFilters(
                AllowedFilter::scope('status'),
                AllowedFilter::scope('publish_date'),
            )
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(AdResource::collection($ads));
    }

    #[Endpoint(title: 'Create Ad', description: 'Publish a new ad. Accepts multipart/form-data with an optional image upload.')]
    public function store(StoreAdRequest $request): JsonResponse
    {
        $ad = $this->ads->create($request->safe()->except('image'), $request->file('image'));

        return $this->createdResponse(
            data: new AdResource($ad),
            message: 'Ad created successfully.',
        );
    }

    #[Endpoint(title: 'Show Ad', description: 'Get a single ad.')]
    public function show(Ad $ad): JsonResponse
    {
        return $this->successResponse(data: new AdResource($ad));
    }

    #[Endpoint(title: 'Update Ad', description: 'Update an existing ad. A new image replaces the previous one.')]
    public function update(UpdateAdRequest $request, Ad $ad): JsonResponse
    {
        $ad = $this->ads->update($ad, $request->safe()->except('image'), $request->file('image'));

        return $this->successResponse(
            data: new AdResource($ad),
            message: 'Ad updated successfully.',
        );
    }

    #[Endpoint(title: 'Toggle Ad Status', description: 'Flip an ad between live and paused.')]
    public function toggle(Ad $ad): JsonResponse
    {
        $ad = $this->ads->toggle($ad);

        return $this->successResponse(
            data: new AdResource($ad),
            message: $ad->status ? 'Ad set live successfully.' : 'Ad paused successfully.',
        );
    }

    #[Endpoint(title: 'Delete Ad', description: 'Delete an ad and its image.')]
    public function destroy(Ad $ad): JsonResponse
    {
        $this->ads->delete($ad);

        return $this->successResponse(message: 'Ad deleted successfully.');
    }
}
