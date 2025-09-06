<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;

class NotificationController extends Controller
{
    use ApiResponseTrait;
    // عرض كل الإشعارات للمستخدم
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->paginate(15);
        
        return $this->paginatedResponse($notifications, 'Notifications retrieved successfully');
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
