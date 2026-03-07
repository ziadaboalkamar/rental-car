<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'items' => [],
                'unread_count' => 0,
            ]);
        }

        $items = $user->notifications()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->formatNotification($notification))
            ->values();

        return response()->json([
            'items' => $items,
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function read(Request $request, string $notificationId): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401);

        $notification = $user->notifications()->whereKey($notificationId)->first();
        abort_if(!$notification, 404);

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'ok' => true,
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user, 401);

        $user->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        $title = (string) ($data['title'] ?? 'Notification');
        $message = (string) ($data['message'] ?? '');
        $url = (string) ($data['url'] ?? '');
        $kind = (string) ($data['kind'] ?? 'generic');

        return [
            'id' => (string) $notification->id,
            'kind' => $kind,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'read_at' => optional($notification->read_at)?->toDateTimeString(),
            'created_at' => optional($notification->created_at)?->toDateTimeString(),
        ];
    }
}

