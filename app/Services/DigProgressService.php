<?php

namespace App\Services;

use App\Models\Dig;
use App\Models\DigLayer;
use App\Models\DigLayerAnswer;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DigProgressService
{
    public function __construct(private LevelService $levels) {}

    /**
     * Get the active dig scheduled for today, with the user's progress, or null.
     */
    public function today(User $user): ?Dig
    {
        $dig = Dig::query()->publishedOn(now()->toDateString())->latest('id')->first();

        return $dig ? $this->withProgress($dig, $user) : null;
    }

    /**
     * Load an explicit dig together with the user's progress (history / re-open).
     */
    public function forUser(Dig $dig, User $user): Dig
    {
        return $this->withProgress($dig, $user);
    }

    /**
     * Record (or update) the user's answer to a layer. XP is awarded once, on
     * the first answer, so editing a response never grants XP again.
     *
     * @param  array<string, mixed>  $data
     */
    public function submitLayer(User $user, DigLayer $layer, array $data): DigLayerAnswer
    {
        $answer = DigLayerAnswer::firstOrNew([
            'user_id' => $user->getKey(),
            'dig_layer_id' => $layer->getKey(),
        ]);

        $answer->dig_id = $layer->dig_id;
        $answer->selected_option = $data['selected_option'] ?? null;
        $answer->response = $data['response'] ?? null;

        if (! $answer->exists) {
            $answer->xp_awarded = $layer->xp;
        }

        $answer->save();

        return $answer;
    }

    /**
     * Summarise the user's dig engagement for the Exercises header, including
     * the derived excavation level for the "Grow Yourself" card.
     *
     * @return array<string, int>
     */
    public function stats(User $user): array
    {
        return [
            ...$this->levels->forXp($user->digXp()),
            'layers_completed' => $user->digLayerAnswers()->count(),
            'digs_completed' => $this->digsCompleted($user),
        ];
    }

    /**
     * Eager-load the layers and only this user's answer for each.
     */
    private function withProgress(Dig $dig, User $user): Dig
    {
        return $dig->load([
            'layers.answers' => fn (HasMany $query) => $query->where('user_id', $user->getKey()),
        ]);
    }

    /**
     * Count the digs where the user has answered every layer.
     */
    private function digsCompleted(User $user): int
    {
        $answeredPerDig = DigLayerAnswer::query()
            ->where('user_id', $user->getKey())
            ->selectRaw('dig_id, COUNT(*) as answered')
            ->groupBy('dig_id')
            ->pluck('answered', 'dig_id');

        if ($answeredPerDig->isEmpty()) {
            return 0;
        }

        $layerCounts = DigLayer::query()
            ->whereIn('dig_id', $answeredPerDig->keys())
            ->selectRaw('dig_id, COUNT(*) as total')
            ->groupBy('dig_id')
            ->pluck('total', 'dig_id');

        return $answeredPerDig
            ->filter(fn (int $answered, int $digId): bool => $answered >= ($layerCounts[$digId] ?? PHP_INT_MAX))
            ->count();
    }
}
