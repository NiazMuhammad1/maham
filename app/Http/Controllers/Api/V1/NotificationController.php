<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notifications screen (newest first, 20 per page).
 *
 * Customer and provider notifications are separate lists: send ?mode=provider in Provider Mode
 * (default: customer).
 */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $mode = $this->mode($request);

        $notifications = $request->user()->notificationsFor($mode)->latest()->paginate(20);

        return $this->success([
            'mode' => $mode,
            'notifications' => NotificationResource::collection($notifications)->resolve(),
            'unread_count' => $request->user()->notificationsFor($mode)->whereNull('read_at')->count(),
            'current_page' => $notifications->currentPage(),
            'last_page' => $notifications->lastPage(),
            'total' => $notifications->total(),
        ]);
    }

    /**
     * Call when the user taps a notification.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);

        $notification->markAsRead();

        return $this->success(new NotificationResource($notification));
    }

    /**
     * Marks all notifications of one list as read (?mode=provider for the provider list).
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->notificationsFor($this->mode($request))->whereNull('read_at')->update(['read_at' => now()]);

        return $this->success(null, __('messages.notifications_marked_read'));
    }

    /**
     * Which list: customer (default) or provider.
     */
    private function mode(Request $request): string
    {
        $request->validate(['mode' => ['nullable', Rule::in([User::MODE_CUSTOMER, User::MODE_PROVIDER])]]);

        return $request->input('mode', User::MODE_CUSTOMER);
    }
}
