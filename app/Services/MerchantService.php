<?php

namespace App\Services;

use Illuminate\Support\Facades\Hash;
use App\Jobs\PayoutOrderJob;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use DB;

class MerchantService
{
    /**
     * Register a new user and associated merchant.
     * Hint: Use the password field to store the API key.
     * Hint: Be sure to set the correct user type according to the constants in the User model.
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return Merchant
     */
    public function register(array $data): Merchant
    {
        $validatedData = validator()->make($data, [
            'name' => 'required',
            'email' => 'required|email',
            'api_key' => 'required',
            'domain' => 'required',
            'display_name' => 'required'
        ]);

        $validatedData = $validatedData->validated();

        try {
            DB::beginTransaction();

            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => $validatedData['api_key'],
                'type' => User::TYPE_MERCHANT,
            ]);

            $merchant = $user->merchant()->create([
                'domain' => $validatedData['domain'],
                'display_name' => $validatedData['name'],
            ]);

            DB::commit();

            return $merchant;

        } catch (\Exception $e) {
            DB::rollBack();

            throw new \Exception('Failed to create merchant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update the user
     *
     * @param array{domain: string, name: string, email: string, api_key: string} $data
     * @return void
     */
    public function updateMerchant(User $user, array $data)
    {
        // TODO: Complete this method
        try {
            DB::beginTransaction();

            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['api_key'],
            ]);

            $merchant = $user->merchant;
            $merchant->update([
                'domain' => $data['domain'],
                'display_name' => $data['name'],
            ]);

            DB::commit();

            return $merchant;

        } catch (\Exception $e) {
            DB::rollBack();
            // Handle exception or log error
            throw new \Exception('Failed to update merchant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find a merchant by their email.
     * Hint: You'll need to look up the user first.
     *
     * @param string $email
     * @return Merchant|null
     */
    public function findMerchantByEmail(string $email): ?Merchant
    {
        try {
            $user = User::where('email', $email)->first();

            if (! $user) return null;

            $merchant = $user->merchant;

            return $merchant;

        } catch (\Exception $e) {
            // Handle exception or log error
            throw new \Exception('Failed to find merchant: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Pay out all of an affiliate's orders.
     * Hint: You'll need to dispatch the job for each unpaid order.
     *
     * @param Affiliate $affiliate
     * @return void
     */
    public function payout(Affiliate $affiliate)
    {
        try {
            $affiliateUnpaidOrders = $affiliate->orders()->where('payout_status', Order::STATUS_UNPAID);
            $affiliateUnpaidOrders->each(fn (Order $order) => PayoutOrderJob::dispatch($order));

            return true;

        } catch (\Exception $e) {
            throw new \Exception('Failed to payout affiliate: ' . $e->getMessage(), 0, $e);
        }
    }
}
