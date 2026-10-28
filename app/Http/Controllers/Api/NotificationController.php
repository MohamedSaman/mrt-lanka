<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends ApiController
{
    /**
     * Get list of notifications for the authenticated user
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Get notifications (already sorted by latest)
        $query = $user->notifications();

        // Filter by unread if requested
        if ($request->has('unread') && $request->unread == 'true') {
            $query = $user->unreadNotifications();
        }

        $notifications = $query->paginate(20);

        // Transform notifications
        $formattedNotifications = collect($notifications->items())->map(function ($notification) {
            return [
                'id' => $notification->id,
                'type' => $this->mapNotificationType($notification->type),
                'title' => $notification->data['title'] ?? 'Notification',
                'message' => $notification->data['message'] ?? '',
                'timestamp' => $notification->created_at->diffForHumans(),
                'created_at' => $notification->created_at,
                'isRead' => !is_null($notification->read_at),
                'data' => $notification->data,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Notifications retrieved successfully',
            'count' => $notifications->total(),
            'next' => $notifications->nextPageUrl(),
            'previous' => $notifications->previousPageUrl(),
            'results' => $formattedNotifications,
            'unread_count' => $user->unreadNotifications()->count()
        ]);
    }

    /**
     * Mark a specific notification as read
     */
    public function markAsRead($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->markAsRead();

        return $this->success(null, 'Notification marked as read');
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead()
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return $this->success(null, 'All notifications marked as read');
    }

    /**
     * Delete a notification
     */
    public function destroy($id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return $this->error('Notification not found', 404);
        }

        $notification->delete();

        return $this->success(null, 'Notification deleted');
    }

    /**
     * Map database notification type class name to frontend friendly type
     */
    private function mapNotificationType($type)
    {
        if (str_contains($type, 'NewSaleNotification')) {
            return 'order';
        }
        if (str_contains($type, 'PaymentNotification')) {
            return 'payment';
        }
        if (str_contains($type, 'LowStockNotification')) {
            return 'alert';
        }
        return 'system';
    }
}
