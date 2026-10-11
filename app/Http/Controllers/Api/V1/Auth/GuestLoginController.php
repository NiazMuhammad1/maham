<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Api\V1\Auth\Concerns\IssuesAuthTokens;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GuestLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * "Continue as a guest": a browse-only account with its own token.
 */
class GuestLoginController extends Controller
{
    use IssuesAuthTokens;

    public function __invoke(GuestLoginRequest $request): JsonResponse
    {
        $user = User::create([
            'is_guest' => true,
            'language' => app()->getLocale(),
        ]);

        return $this->success($this->issueToken($user, $request), __('messages.guest_login_success'));
    }
}
