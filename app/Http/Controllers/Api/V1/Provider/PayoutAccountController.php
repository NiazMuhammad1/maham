<?php

namespace App\Http\Controllers\Api\V1\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\Provider\UpdatePayoutAccountRequest;
use App\Models\Bank;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Business Profile -> "Payment Settings" (Payout Settings): the bank account where Maham sends
 * the provider's earnings for completed bookings.
 */
class PayoutAccountController extends Controller
{
    /**
     * Banks for "Select Bank" + the saved account (null until the provider saves one).
     */
    public function show(Request $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        return $this->payoutResponse($provider);
    }

    /**
     * "Save Changes": creates or replaces the provider's payout account.
     */
    public function update(UpdatePayoutAccountRequest $request): JsonResponse
    {
        $provider = $request->user();
        $this->ensureProviderCanUseDashboard($provider);

        $data = $request->validated();

        // Swift / BIC left empty: use the code of the selected bank
        $data['swift_code'] = ($data['swift_code'] ?? null) ?: Bank::find($data['bank_id'])->swift_code;

        $provider->payoutAccount()->updateOrCreate([], $data);

        return $this->payoutResponse($provider, __('messages.changes_saved'));
    }

    private function payoutResponse(User $provider, ?string $message = null): JsonResponse
    {
        $account = $provider->payoutAccount()->with('bank')->first();

        $banks = Bank::query()->active()->orderBy('sort_order')->get()->map(fn (Bank $bank) => [
            'id' => $bank->id,
            'name' => $bank->localized('name'),
            'swift_code' => $bank->swift_code,
        ]);

        return $this->success([
            'banks' => $banks,
            'payout_account' => $account ? [
                'bank' => $account->bank ? ['id' => $account->bank->id, 'name' => $account->bank->localized('name')] : null,
                'account_holder_name' => $account->account_holder_name,
                'iban' => $account->iban,
                'swift_code' => $account->swift_code,
                'updated_at' => $account->updated_at->toIso8601String(),
            ] : null,
        ], $message);
    }
}
