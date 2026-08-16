<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\ApiPagination;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(ApiPagination::perPage($request, 25));

        return response()->json([
            'success' => true,
            'message' => 'Notifications récupérées.',
            'data' => $notifications->map(fn (Notification $notification) => $this->format($notification)),
            'meta' => ApiPagination::meta($notifications),
        ]);
    }

    public function unreadCount(Request $request)
    {
        $count = $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'success' => true,
            'message' => 'Compteur récupéré.',
            'data' => ['count' => $count],
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            abort(403, 'Vous ne pouvez modifier que vos propres notifications.');
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
            'data' => $this->format($notification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = $request->user()
            ->notifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => $updated > 0 ? 'Notifications marquées comme lues.' : 'Aucune notification non lue.',
            'data' => ['updated' => $updated],
        ]);
    }

    private function format(Notification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->type,
            'title' => $notification->title,
            'message' => $notification->message,
            'data' => $notification->data,
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at->toISOString(),
        ];
    }
}
