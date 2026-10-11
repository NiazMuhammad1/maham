<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live "username available?" check while typing on the personal details screen.
 */
class UsernameAvailabilityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->merge(['username' => mb_strtolower(trim((string) $request->query('username')))]);
        $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z0-9_.]+$/'],
        ]);

        $isTaken = User::withTrashed()
            ->where('username', $request->input('username'))
            ->when($request->user('sanctum'), fn ($query, User $user) => $query->whereKeyNot($user->id))
            ->exists();

        return $this->success([
            'username' => $request->input('username'),
            'available' => ! $isTaken,
        ], $isTaken ? __('messages.username_taken') : __('messages.username_available'));
    }
}
