<?php

namespace App\Services;

use App\Models\Affiliate;
use App\Models\Merchant;
use App\Models\Order;
use App\Models\User;

class OrderService
{
    public function __construct(
        protected AffiliateService $affiliateService
    ) {}

    /**
     * Process an order and log any commissions.
     * This should create a new affiliate if the customer_email is not already associated with one.
     * This method should also ignore duplicates based on order_id.
     *
     * @param  array{order_id: string, subtotal_price: float, merchant_domain: string, discount_code: string, customer_email: string, customer_name: string} $data
     * @return void
     */
    public function processOrder(array $data)
    {
        try {            
            if (Order::where('external_order_id', $data['order_id'])->first()) {
                return;
            }

            $merchant = Merchant::where('domain', $data['merchant_domain'])->firstOrFail();

            $user = User::whereEmail($data['customer_email'])->whereType(User::TYPE_AFFILIATE)->first();

            $affiliate = $user ? $user->affiliate : null;

            if(! $affiliate) {
                $affiliate = $this->affiliateService->register(
                    $merchant,
                    $data['customer_email'],
                    $data['customer_name'],
                    0.1
                );
            }

            $commissionRate = $affiliate->commission_rate;
            $commissionOwned = $data['subtotal_price'] * $commissionRate;

            Order::create([
                'external_order_id' => $data['order_id'],
                'merchant_id' => $merchant->id,
                'affiliate_id' => $affiliate->id,
                'subtotal' => $data['subtotal_price'],
                'commission_owed' => $commissionOwned,
                'payout_status' => Order::STATUS_UNPAID,
                'discount_code' => $data['discount_code']
            ]);

        } catch (\Exception $e) {
            // dd($e);;
            // Handle exception or log error
            throw new \Exception('Failed to process order: ' . $e->getMessage(), 0, $e);
        }
    }
}
