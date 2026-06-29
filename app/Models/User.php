<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Cashier\Billable;
use Laravel\Sanctum\HasApiTokens;
use OwenIt\Auditing\Contracts\Auditable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['first_name', 'last_name', 'email', 'password', 'status', 'provider', 'provider_id', 'avatar', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<UserFactory> */
    use Billable, HasApiTokens, HasFactory, HasRoles, Notifiable, \OwenIt\Auditing\Auditable;

    /**
     * Give every newly created user a default set of notification settings.
     */
    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->notificationSettings()->create();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => 'boolean',
        ];
    }

    /**
     * Get the quotes created by this user.
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    /**
     * Get the payments recorded for this user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the user's notification preferences.
     */
    public function notificationSettings(): HasOne
    {
        return $this->hasOne(NotificationSetting::class);
    }

    /**
     * Resolve a displayable URL for the avatar.
     *
     * Avatars from social login are stored as absolute URLs, while uploaded
     * photos are stored as paths on the public disk.
     */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar) {
            return null;
        }

        return Str::startsWith($this->avatar, ['http://', 'https://'])
            ? $this->avatar
            : Storage::disk('public')->url($this->avatar);
    }

    /**
     * Limit results to users created on the given calendar date.
     */
    #[Scope]
    protected function createdDate(Builder $query, string $date): void
    {
        $query->whereDate('created_at', $date);
    }

    /**
     * Limit results by account status. Anything other than "active"/"deactive" applies no filter.
     */
    #[Scope]
    protected function status(Builder $query, string $value): void
    {
        match ($value) {
            'active' => $query->where('status', true),
            'deactive' => $query->where('status', false),
            default => $query,
        };
    }
}
