<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use App\Traits\ApiResponseTrait;
use App\Traits\AuthorizesRequests;

class NotificationController extends Controller
{
    use ApiResponseTrait,AuthorizesRequests;
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
    public function markAsRead(Request $request)
    {
        $atts=$request->validate([
            'id' => 'required|uuid',
        ]);
        $notification = $request->user()->notifications()->whereKey($atts['id'])->first();

        if (! $notification) {
            return $this->notFoundResponse('Notification not found');
        }

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
