<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SocialLoginRequest;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\SocialTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * "Continue with Google" / "Continue with Apple". New accounts continue at next_step = verify_phone.
 */
class SocialLoginController extends Controller
{
    public function __invoke(SocialLoginRequest $request, SocialTokenVerifier $verifier): JsonResponse
    {
        $provider = $request->validated('provider');
        $profile = $verifier->verify($provider, $request->validated('id_token'));

        $user = DB::transaction(function () use ($provider, $profile, $request): User {
            $account = SocialAccount::with('user')
                ->where('provider', $provider)
                ->where('provider_user_id', $profile['id'])
                ->first();

            if ($account?->user) {
                return $account->user;
            }

            $email = $profile['email'] ? mb_strtolower($profile['email']) : null;
            $user = $email && $profile['email_verified'] ? User::where('email', $email)->first() : null;

            if ($user === null) {
                $user = User::create([
                    'name' => $profile['name'] ?? $request->validated('name'),
                    'email' => $email,
                    'language' => app()->getLocale(),
                ]);
            }

            if ($email && $profile['email_verified'] && $user->email === $email && $user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            $user->socialAccounts()->create([
                'provider' => $provider,
                'provider_user_id' => $profile['id'],
                'email' => $email,
            ]);

            return $user;
        });

        if ($user->status === 'suspended') {
            throw new AccessDeniedHttpException(__('messages.account_suspended'));
        }

        return $this->success($this->issueToken($user, $request), __('messages.login_success'));
    }
}
