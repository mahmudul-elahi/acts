<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\SubmitDigLayerRequest;
use App\Http\Resources\User\DigResource;
use App\Models\Dig;
use App\Models\DigLayer;
use App\Services\DigProgressService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

#[Group('User - Digs')]
class DigController extends Controller
{
    public function __construct(private DigProgressService $digs) {}

    #[Endpoint(title: "Today's Dig", description: "Get the active dig scheduled for today with the user's progress, or null when none is scheduled.")]
    public function today(Request $request): JsonResponse
    {
        $dig = $this->digs->today($request->user());

        return $this->successResponse(
            data: $dig ? new DigResource($dig) : null,
        );
    }

    #[Endpoint(title: 'Dig Stats', description: "Get the user's total dig XP and completion counts for the Exercises header.")]
    public function stats(Request $request): JsonResponse
    {
        return $this->successResponse(data: $this->digs->stats($request->user()));
    }

    #[Endpoint(title: 'Show Dig', description: "Get a single active dig with the user's per-layer progress.")]
    public function show(Request $request, Dig $dig): JsonResponse
    {
        abort_if(! $dig->status, Response::HTTP_NOT_FOUND);

        return $this->successResponse(
            data: new DigResource($this->digs->forUser($dig, $request->user())),
        );
    }

    #[Endpoint(title: 'Answer a Layer', description: 'Submit (or update) the answer to a layer. The layer awards its XP the first time it is answered; editing the answer later never re-awards XP.')]
    public function submitLayer(SubmitDigLayerRequest $request, Dig $dig, DigLayer $layer): JsonResponse
    {
        abort_if(! $dig->status, Response::HTTP_NOT_FOUND);

        $this->digs->submitLayer($request->user(), $layer, $request->validated());

        return $this->successResponse(
            data: new DigResource($this->digs->forUser($dig, $request->user())),
            message: 'Answer saved.',
        );
    }
}
