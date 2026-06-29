<?php

namespace App\Models;

use Database\Factories\NotificationSettingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'meditation_reminders', 'comment_alerts', 'subscription_alerts', 'post_react_alerts'])]
class NotificationSetting extends Model
{
    /** @use HasFactory<NotificationSettingFactory> */
    use HasFactory;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'meditation_reminders' => true,
        'comment_alerts' => true,
        'subscription_alerts' => true,
        'post_react_alerts' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meditation_reminders' => 'boolean',
            'comment_alerts' => 'boolean',
            'subscription_alerts' => 'boolean',
            'post_react_alerts' => 'boolean',
        ];
    }

    /**
     * The user the notification settings belong to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
