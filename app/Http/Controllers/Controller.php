<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class Controller
{
    /**
     * Standard API envelope: { success, message, data }.
     */
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data instanceof JsonResource ? $data->resolve() : $data,
        ], $status);
    }

    /**
     * Log the user in: create a Sanctum token, remember the device push token and build the auth payload.
     *
     * @return array{token: string, token_type: string, next_step: string, user: UserResource}
     */
    protected function issueToken(User $user, Request $request): array
    {
        $token = $user->createToken($request->input('device_name') ?: 'mobile')->plainTextToken;

        if ($request->filled('fcm_token')) {
            $user->deviceTokens()->updateOrCreate(
                ['token' => $request->input('fcm_token'), 'platform' => $request->input('platform')],
            );
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return [
            'token' => $token,
            'token_type' => 'Bearer',
            'next_step' => $user->nextRegistrationStep(),
            'user' => new UserResource($user->load('defaultAddress.city', 'defaultAddress.district')),
        ];
    }
}
