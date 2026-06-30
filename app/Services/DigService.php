<?php

namespace App\Services;

use App\Enums\DigAnswerType;
use App\Models\Dig;
use Illuminate\Support\Facades\DB;

class DigService
{
    /**
     * Create a dig together with its ordered layers.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Dig
    {
        return DB::transaction(function () use ($data): Dig {
            $dig = Dig::create([
                'title' => $data['title'],
                'type' => $data['type'],
                'status' => $data['status'] ?? true,
                'published_on' => $data['published_on'] ?? null,
            ]);

            $dig->layers()->createMany($this->buildLayers($data['layers']));

            return $this->loadAggregates($dig);
        });
    }

    /**
     * Update a dig. When `layers` is present it fully replaces the existing
     * layers; otherwise only the dig's own attributes are touched.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Dig $dig, array $data): Dig
    {
        return DB::transaction(function () use ($dig, $data): Dig {
            $attributes = array_intersect_key($data, array_flip(['title', 'type', 'status', 'published_on']));

            if ($attributes !== []) {
                $dig->update($attributes);
            }

            if (array_key_exists('layers', $data)) {
                $dig->layers()->delete();
                $dig->layers()->createMany($this->buildLayers($data['layers']));
            }

            return $this->loadAggregates($dig->refresh());
        });
    }

    /**
     * Delete a dig and (via cascade) all of its layers.
     */
    public function delete(Dig $dig): void
    {
        $dig->delete();
    }

    /**
     * Eager-load the layers and aggregate columns the resource exposes.
     */
    public function loadAggregates(Dig $dig): Dig
    {
        return $dig->load('layers')->loadCount('layers')->loadSum('layers', 'xp');
    }

    /**
     * Normalise validated layer payload into ordered, persistable rows.
     *
     * @param  array<int, array<string, mixed>>  $layers
     * @return array<int, array<string, mixed>>
     */
    private function buildLayers(array $layers): array
    {
        return collect($layers)->values()->map(function (array $layer, int $index): array {
            $isOption = $layer['answer_type'] === DigAnswerType::Option->value;

            return [
                'position' => $index + 1,
                'title' => $layer['title'],
                'question' => $layer['question'],
                'answer_type' => $layer['answer_type'],
                'xp' => $layer['xp'] ?? 0,
                'include_other' => $isOption ? (bool) ($layer['include_other'] ?? false) : false,
                'options' => $isOption ? array_values($layer['options'] ?? []) : null,
                'placeholder' => $isOption ? null : ($layer['placeholder'] ?? null),
            ];
        })->all();
    }
}
