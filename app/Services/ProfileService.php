<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileService
{
    private const AVATAR_DISK = 'public';

    private const AVATAR_DIR = 'avatars';

    /**
     * Update the user's profile, replacing the avatar if a new one is uploaded.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        if ($avatar instanceof UploadedFile) {
            $this->deleteStoredAvatar($user);
            $data['avatar'] = $avatar->store(self::AVATAR_DIR, self::AVATAR_DISK);
        }

        $user->update($data);

        return $user;
    }

    /**
     * Remove the user's avatar.
     */
    public function deleteAvatar(User $user): User
    {
        $this->deleteStoredAvatar($user);
        $user->update(['avatar' => null]);

        return $user;
    }

    /**
     * Update the user's password. The hashed cast handles hashing.
     */
    public function updatePassword(User $user, string $password): void
    {
        $user->update(['password' => $password]);
    }

    /**
     * Delete an uploaded avatar. Social-login avatars (absolute URLs) are left untouched.
     */
    private function deleteStoredAvatar(User $user): void
    {
        if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
            Storage::disk(self::AVATAR_DISK)->delete($user->avatar);
        }
    }
}
