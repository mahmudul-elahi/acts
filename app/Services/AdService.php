<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\AdImpression;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class AdService
{
    private const IMAGE_DISK = 'public';

    private const IMAGE_DIR = 'ads';

    /**
     * Create an ad, storing the uploaded image if one is provided.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?UploadedFile $image = null): Ad
    {
        if ($image instanceof UploadedFile) {
            $data['image'] = $this->storeImage($image);
        }

        return Ad::create($data)->refresh();
    }

    /**
     * Update an ad. A new image replaces (and deletes) the previous one.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Ad $ad, array $data, ?UploadedFile $image = null): Ad
    {
        if ($image instanceof UploadedFile) {
            $this->deleteImage($ad);
            $data['image'] = $this->storeImage($image);
        }

        $ad->update($data);

        return $ad;
    }

    /**
     * Delete an ad along with its stored image.
     */
    public function delete(Ad $ad): void
    {
        $this->deleteImage($ad);
        $ad->delete();
    }

    /**
     * Get a random active ad the user has not seen in the last 24 hours.
     * Logs the impression and returns the ad, or null if none available.
     */
    public function next(User $user): ?Ad
    {
        $adId = Ad::active()
            ->whereDoesntHave(
                'impressions',
                fn($query) => $query
                    ->where('user_id', $user->getKey())
                    ->where('created_at', '>', now()->subHours(24)),
            )
            ->inRandomOrder() // fine for small/medium tables; see note below
            ->value('id');

        if (! $adId) {
            return null;
        }

        /** @var Ad $ad */
        $ad = Ad::findOrFail($adId);

        $ad->impressions()->create([
            'user_id' => $user->getKey(),
        ]);

        return $ad;
    }

    /**
     * Flip an ad between live and paused.
     */
    public function toggle(Ad $ad): Ad
    {
        $ad->update(['status' => ! $ad->status]);

        return $ad;
    }

    private function storeImage(UploadedFile $image): string
    {
        return $image->store(self::IMAGE_DIR, self::IMAGE_DISK);
    }

    private function deleteImage(Ad $ad): void
    {
        if ($ad->image) {
            Storage::disk(self::IMAGE_DISK)->delete($ad->image);
        }
    }
}
