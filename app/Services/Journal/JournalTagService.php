<?php

namespace App\Services\Journal;

use App\Models\JournalTag;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class JournalTagService
{
    /**
     * Search tags for the typeahead, ordered alphabetically.
     *
     * @return Collection<int, JournalTag>
     */
    public function search(?string $term, int $limit = 10): Collection
    {
        return JournalTag::query()
            ->when($term, fn ($query) => $query->search($term))
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }

    /**
     * Resolve a list of tag names to their ids, creating any that are new.
     * Names are squished and de-duplicated by slug so "Morning" and "morning "
     * collapse onto a single tag.
     *
     * @param  array<int, string>  $names
     * @return array<int, int>
     */
    public function resolveIds(array $names): array
    {
        return collect($names)
            ->map(fn (string $name): string => Str::squish($name))
            ->filter()
            ->unique(fn (string $name): string => Str::slug($name))
            ->map(fn (string $name): int => JournalTag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            )->getKey())
            ->values()
            ->all();
    }
}
