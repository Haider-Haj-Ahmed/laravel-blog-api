<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // عرض كل الإشعارات للمستخدم
    public function index(Request $request)
    {
        return response()->json([
            'unread' => $request->user()->unreadNotifications,
            'all' => $request->user()->notifications
        ]);
    }

    // تعليم إشعار واحد كمقروء
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    // تعليم كل الإشعارات كمقروءة
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['message' => 'All notifications marked as read']);
    }
}
