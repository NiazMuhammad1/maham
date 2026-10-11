<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * The logged in user. Call on app start to validate a saved token and resume sign-up.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('defaultAddress.city', 'defaultAddress.district');

        return $this->success([
            'next_step' => $user->nextRegistrationStep(),
            'user' => new UserResource($user),
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($request->filled('fcm_token')) {
            $user->deviceTokens()->where('token', $request->input('fcm_token'))->delete();
        }

        $user->currentAccessToken()->delete();

        return $this->success(null, __('messages.logout_success'));
    }
}
