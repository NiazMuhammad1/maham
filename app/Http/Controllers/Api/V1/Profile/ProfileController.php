<?php

namespace App\Http\Controllers\Api\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateAddressRequest;
use App\Http\Requests\Profile\UpdateNotificationSettingsRequest;
use App\Http\Requests\Profile\UpdatePersonalDetailsRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Account Settings screens: profile card, Personal Details, Home Address and Notification Settings.
 */
class ProfileController extends Controller
{
    /**
     * Everything the Account Settings screens show (name, photo, details, address, notification switches).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->userResponse($request->user());
    }

    /**
     * Personal Details > Save Changes.
     */
    public function updatePersonalDetails(UpdatePersonalDetailsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->userResponse($user, __('messages.changes_saved'));
    }

    /**
     * Home Address > Save Changes. Replaces the user's default (Home) address.
     */
    public function updateAddress(UpdateAddressRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->addresses()->updateOrCreate(
            ['is_default' => true],
            [...$request->validated(), 'label' => 'Home'],
        );

        return $this->userResponse($user, __('messages.changes_saved'));
    }

    /**
     * Notification Settings > Push / Email switches. Saved on every toggle.
     */
    public function updateNotifications(UpdateNotificationSettingsRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return $this->success([
            'push_notifications' => $user->push_notifications,
            'email_notifications' => $user->email_notifications,
        ], __('messages.changes_saved'));
    }

    private function userResponse(User $user, ?string $message = null): JsonResponse
    {
        $user->load('defaultAddress.city', 'defaultAddress.district');

        return $this->success(['user' => new UserResource($user)], $message);
    }
}
