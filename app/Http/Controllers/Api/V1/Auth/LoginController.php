<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * "Welcome Back": email + password login.
 */
class LoginController extends Controller
{
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if ($user === null || $user->password === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if ($user->status === 'suspended') {
            throw new AccessDeniedHttpException(__('messages.account_suspended'));
        }

        return $this->success($this->issueToken($user, $request), __('messages.login_success'));
    }
}
