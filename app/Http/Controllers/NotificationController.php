<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{

    public function getAdminNotifications()
    {
        $notifications = DB::table('notifications')

            ->whereNull('read_at')

            ->orderBy('created_at', 'desc')

            ->get()

            ->map(function ($notification) {

                return [

                    'notification_id' => $notification->id,

                    'type' => $notification->type,

                    'created_at' => $notification->created_at,

                    'data' => json_decode(
                        $notification->data,
                        true
                    )
                ];
            });
        $unreadCount = DB::table('notifications')

            ->whereNull('read_at')

            ->count();

        $totalNotifications = DB::table('notifications')
            ->count();

        return response()->json([

            'success' => true,

            'status' => 200,

            'unread_count' => $unreadCount,

            'total_notifications' => $totalNotifications,

            'data' => $notifications
        ]);
    }
}
