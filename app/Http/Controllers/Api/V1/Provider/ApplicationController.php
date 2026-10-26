<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\ApplicationBusinessDetailsRequest;
use App\Http\Requests\Provider\ApplicationServiceAreaRequest;
use App\Http\Requests\Provider\ApplicationServicesRequest;
use App\Http\Requests\Provider\SubmitApplicationRequest;
use App\Http\Resources\ProviderApplicationResource;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Become a Service Provider": Select Service -> Service Area -> Business Details -> Upload Logo -> Application Submitted.
 *
 * Every "Next" saves its step, so the user can close the app and continue later (see `next_step`).
 * "Submit details" / "Skip" sends the application to the admin team; the user becomes a provider
 * (`is_provider = true`) only when an admin approves it.
 */
class ApplicationController extends Controller
{
    /**
     * Current application: status, next screen and the saved answers (to pre-fill the steps).
     */
    public function show(Request $request): JsonResponse
    {
        return $this->applicationResponse($request->user());
    }

    /**
     * Step 1 "Select Service": replaces the selected categories.
     */
    public function services(ApplicationServicesRequest $request): JsonResponse
    {
        $user = $request->user();
       
        $this->ensureCanEdit($user);

        $user->providedServices()->sync($request->validated('service_ids'));

        // The draft profile is created on the first step, so the status becomes "draft".
        $user->providerProfile()->firstOrCreate([]);

        return $this->applicationResponse($user, __('messages.details_saved'));
    }

    /**
     * Step 2 "Choose Service Area": one city (and optional district), replaces the previous one.
     */
    public function serviceArea(ApplicationServiceAreaRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanEdit($user);

        $user->serviceAreas()->delete();
        $user->serviceAreas()->create($request->validated());

        $user->providerProfile()->firstOrCreate([]);

        return $this->applicationResponse($user, __('messages.details_saved'));
    }

    /**
     * Step 3 "Business Details": business name, tagline, skills and experience, website.
     */
    public function businessDetails(ApplicationBusinessDetailsRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanEdit($user);

        $user->providerProfile()->updateOrCreate([], $request->validated());

        return $this->applicationResponse($user, __('messages.details_saved'));
    }

    /**
     * Step 4 "Upload Business Logo": send `logo` for "Submit details", or nothing for "Skip".
     * Sends the application for admin review.
     */
    public function submit(SubmitApplicationRequest $request): JsonResponse
    {
        $user = $request->user();
        $this->ensureCanEdit($user);

        $nextStep = $user->nextProviderApplicationStep();

        if ($nextStep !== User::APPLICATION_STEP_LOGO) {
            throw ValidationException::withMessages([
                'next_step' => __('messages.provider_application_incomplete', ['step' => $nextStep]),
            ]);
        }

        $profile = $user->providerProfile;

        // "logo" is a single-file media collection, so a new logo replaces the old one.
        if ($request->hasFile('logo')) {
            $profile->addMediaFromRequest('logo')->toMediaCollection('logo');
        }

        // A rejected application can be fixed and sent again: it goes back to "pending".
        $profile->forceFill([
            'status' => ProviderProfile::STATUS_PENDING,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ])->save();

        return $this->applicationResponse($user, __('messages.provider_application_submitted'));
    }

    /**
     * Steps are locked while the application waits for review, and after it is approved.
     */
    private function ensureCanEdit(User $user): void
    {
        $profile = $user->providerProfile;

        if ($profile !== null && ! $profile->isEditable()) {
            throw ValidationException::withMessages([
                'application' => $profile->status === ProviderProfile::STATUS_PENDING
                    ? __('messages.provider_application_under_review')
                    : __('messages.provider_application_closed'),
            ]);
        }
    }

    private function applicationResponse(User $user, ?string $message = null): JsonResponse
    {
        $user->load([
            'providerProfile.media',
            'providedServices' => fn ($query) => $query->orderBy('sort_order')->with('media'),
            'serviceAreas.city',
            'serviceAreas.district',
        ]);

        return $this->success(new ProviderApplicationResource($user), $message);
    }
}
