<?php

namespace App\Services;

use App\Models\MurmurationTopic;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MurmurationTopicService
{
    /**
     * Search active topics for the typeahead, ordered alphabetically.
     *
     * @return Collection<int, MurmurationTopic>
     */
    public function search(?string $term, int $limit = 10): Collection
    {
        return MurmurationTopic::query()
            ->active()
            ->when($term, fn ($query) => $query->search($term))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve an existing topic by name (case-insensitive via slug) or create it.
     */
    public function findOrCreate(string $name): MurmurationTopic
    {
        $name = Str::squish($name);

        return MurmurationTopic::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'status' => true],
        );
    }
}
