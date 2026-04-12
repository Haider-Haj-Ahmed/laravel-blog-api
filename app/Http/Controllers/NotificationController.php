<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class NotificationController extends Controller
{
    use ApiResponseTrait;
    // عرض كل الإشعارات للمستخدم
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(15);
        
        return $this->paginatedResponse(
            NotificationResource::collection($notifications),
            'Notifications retrieved successfully'
        );
    }

    public function unreadCount(Request $request)
    {
        return $this->successResponse([
            'unread_count' => $request->user()->unreadNotifications()->count(),
        ], 'Unread notifications count retrieved successfully');
    }

    // تعليم إشعار واحد كمقروء
    public function markAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->successResponse(null, 'Notification marked as read');
    }

    // تعليم كل الإشعارات كمقروءة
    public function markAllAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->successResponse(null, 'All notifications marked as read');
    }
}
