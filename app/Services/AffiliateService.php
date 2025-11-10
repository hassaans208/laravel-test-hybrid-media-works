<?php

namespace App\Services;

use App\Exceptions\AffiliateCreateException;
use Illuminate\Support\Facades\Hash;
use App\Mail\AffiliateCreated;
use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use DB;

class AffiliateService
{
    public function __construct(
        protected ApiService $apiService
    ) {}

    /**
     * Create a new affiliate for the merchant with the given commission rate.
     *
     * @param  Merchant $merchant
     * @param  string $email
     * @param  string $name
     * @param  float $commissionRate
     * @return Affiliate
     */
    public function register(Merchant $merchant, string $email, string $name, float $commissionRate): Affiliate
    {
        try {
            throw_if($userExists = User::where('email', $email)->first(), new AffiliateCreateException('User with email ' . $email . ' already exists as ' . ucwords($userExists->type ?? '') . '.'));

            DB::beginTransaction();

            $user = User::create([
                'email' => $email,
                'name' => $name,
                'type' => User::TYPE_AFFILIATE,
                'password' => Hash::make('password')
            ]);

            $affiliate = $user->affiliate()->create([
                'merchant_id' => $merchant->id,
                'commission_rate' => $commissionRate,
                'discount_code' => $this->apiService->createDiscountCode($merchant)['code'] ?? null
            ]);
            
            DB::commit();

            Mail::to($user->email)->send(new AffiliateCreated($affiliate));

            return $affiliate; 

        } catch (\Exception $e) {
            DB::rollBack();

            throw new AffiliateCreateException('Failed to create affiliate: ' . $e->getMessage(), 0, $e);
        }
    }
}
