<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceTokenRequest;
use Illuminate\Http\JsonResponse;

/**
 * Firebase Cloud Messaging token for push notifications. Call whenever FCM refreshes the token.
 */
class DeviceTokenController extends Controller
{
    public function store(DeviceTokenRequest $request): JsonResponse
    {
        $request->user()->deviceTokens()->updateOrCreate(
            ['token' => $request->input('fcm_token'), 'platform' => $request->input('platform')],
        );

        return $this->success(null, __('messages.device_token_saved'));
    }
}
