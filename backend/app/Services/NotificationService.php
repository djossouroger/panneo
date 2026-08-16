<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class NotificationService
{
    public function send(User $user, string $type, string $title, string $message, array $data = []): Notification
    {
        return Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => empty($data) ? null : $data,
        ]);
    }

    public function sendIfDistinct(
        User $user,
        string $type,
        string $title,
        string $message,
        array $data = []
    ): ?Notification {
        $exists = $this->query()
            ->where('user_id', $user->id)
            ->where('type', $type)
            ->where('title', $title)
            ->where('message', $message)
            ->when(empty($data), fn (Builder $query) => $query->whereNull('data'))
            ->exists();

        if ($exists) {
            return null;
        }

        return $this->send($user, $type, $title, $message, $data);
    }

    public function query(): Builder
    {
        return Notification::query();
    }
}
