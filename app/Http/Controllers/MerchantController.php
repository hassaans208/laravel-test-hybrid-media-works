<?php

namespace App\Http\Controllers;

use App\Models\Merchant;
use App\Services\MerchantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MerchantController extends Controller
{
    public function __construct(
        MerchantService $merchantService
    ) {}

    /**
     * Useful order statistics for the merchant API.
     * 
     * @param Request $request Will include a from and to date
     * @return JsonResponse Should be in the form {count: total number of orders in range, commission_owed: amount of unpaid commissions for orders with an affiliate, revenue: sum order subtotals}
     */
    public function orderStats(Request $request): JsonResponse
    {
        try {
            $from = Carbon::parse($request->input('from', now()->subDay()));
            $to = Carbon::parse($request->input('to', now()));
            $merchant = $request->user()->merchant;

            throw_if(! $merchant, new \Exception('Merchant Not Found!'));

            $ordersQuery = $merchant->orders()
            ->whereBetween('created_at', [$from, $to])
            ->get();
            
            $noAffiliateCommissionsOwed = $ordersQuery->whereNull('affiliate_id')->sum('commission_owed');

            $orderCount = $ordersQuery->count();
            $orderCommissionsOwed = $ordersQuery->sum('commission_owed');
            $orderCommissionsOwed -= $noAffiliateCommissionsOwed;
            $orderRevenue = $ordersQuery->sum('subtotal');

            return response()->json([
                'count' => $orderCount,
                'commissions_owed' => $orderCommissionsOwed,
                'revenue' => $orderRevenue
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
