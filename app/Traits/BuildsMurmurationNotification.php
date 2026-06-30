<?php

namespace App\Traits;

use App\Models\User;

trait BuildsMurmurationNotification
{
    /**
     * Resolve the semantic notification type (e.g. "post_liked", "comment").
     * Used as the single source of truth across the database "type" column,
     * the broadcast envelope, and the stored payload.
     */
    abstract protected function notificationType(): string;

    /**
     * Use the semantic type for the database "type" column instead of the
     * default fully-qualified notification class name.
     */
    public function databaseType(object $notifiable): string
    {
        return $this->notificationType();
    }

    /**
     * Use the semantic type for the broadcast envelope instead of the default
     * fully-qualified notification class name.
     */
    public function broadcastType(): string
    {
        return $this->notificationType();
    }

    /**
     * Build the shared Murmuration notification payload so every notification
     * type is delivered with the same shape.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(
        int $postId,
        ?User $actor,
        string $message,
        ?int $commentId = null,
        ?int $parentId = null,
        ?string $body = null,
    ): array {
        return [
            'type' => $this->notificationType(),
            'post_id' => $postId,
            'comment_id' => $commentId,
            'parent_id' => $parentId,
            'body' => $body,
            'actor' => $this->actor($actor),
            'message' => $message,
        ];
    }

    /**
     * Build the compact actor payload.
     *
     * @return array<string, mixed>|null
     */
    protected function actor(?User $user): ?array
    {
        return $user ? [
            'id' => $user->id,
            'name' => $this->actorName($user),
            'avatar' => $user->avatarUrl(),
        ] : null;
    }

    /**
     * Resolve the actor's display name, falling back when the user is missing.
     */
    protected function actorName(?User $user): string
    {
        return $user ? trim("{$user->first_name} {$user->last_name}") : 'Someone';
    }
}
