<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDigRequest;
use App\Http\Requests\Admin\UpdateDigRequest;
use App\Http\Resources\Admin\DigResource;
use App\Models\Dig;
use App\Services\DigService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

#[Group('Admin - Content Management (Digs)')]
class DigController extends Controller
{
    public function __construct(private DigService $digs) {}

    #[Endpoint(title: 'List Digs', description: 'Get a paginated list of digs with their layer count and total points. Filter by status (filter[status]=active|inactive|all, defaults to all).')]
    public function index(Request $request): JsonResponse
    {
        $digs = QueryBuilder::for(Dig::query())
            ->allowedFilters(
                AllowedFilter::scope('status'),
            )
            ->withCount('layers')
            ->withSum('layers', 'xp')
            ->defaultSort('-created_at')
            ->paginate(perPage: $this->perPage($request))
            ->appends($request->query());

        return $this->paginatedResponse(DigResource::collection($digs));
    }

    #[Endpoint(title: 'Create Dig', description: 'Create a dig together with its ordered layers (questions).')]
    public function store(StoreDigRequest $request): JsonResponse
    {
        $dig = $this->digs->create($request->validated());

        return $this->createdResponse(
            data: new DigResource($dig),
            message: 'Dig created successfully.',
        );
    }

    #[Endpoint(title: 'Show Dig', description: 'Get a single dig with its layers.')]
    public function show(Dig $dig): JsonResponse
    {
        return $this->successResponse(data: new DigResource($this->digs->loadAggregates($dig)));
    }

    #[Endpoint(title: 'Update Dig', description: 'Update a dig. Provide "layers" to fully replace its existing layers, or omit it to update only the dig attributes.')]
    public function update(UpdateDigRequest $request, Dig $dig): JsonResponse
    {
        $dig = $this->digs->update($dig, $request->validated());

        return $this->successResponse(
            data: new DigResource($dig),
            message: 'Dig updated successfully.',
        );
    }

    #[Endpoint(title: 'Delete Dig', description: 'Delete a dig and all of its layers.')]
    public function destroy(Dig $dig): JsonResponse
    {
        $this->digs->delete($dig);

        return $this->successResponse(message: 'Dig deleted successfully.');
    }
}
