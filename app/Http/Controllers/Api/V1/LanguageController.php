<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Side menu > Language. Saved on the user so push notifications are sent in the same language.
 */
class LanguageController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['required', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        $request->user()->update(['language' => $validated['language']]);

        app()->setLocale($validated['language']);

        return $this->success(['language' => $validated['language']], __('messages.language_updated'));
    }
}
