<?php

namespace App\Http\Controllers;

use App\Models\OvertimeRequest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class NotificationController extends Controller
{
    public function getAdminNotifications()
    {
        $notifications = DatabaseNotification::whereNull('read_at')
            ->latest()
            ->get()
            ->map(function ($notification) {

                return [
                    'notification_id' => $notification->id,
                    'type' => $notification->type,
                    'created_at' => $notification->created_at,
                    'data' => $notification->data
                ];
            });

        $unreadCount = DB::table('notifications')
            ->whereNull('read_at')
            ->count();

        $totalNotifications = DB::table('notifications')->count();

        return response()->json([
            'success' => true,
            'status' => 200,
            'unread_count' => $unreadCount,
            'total_notifications' => $totalNotifications,
            'data' => $notifications
        ]);
    }
}
