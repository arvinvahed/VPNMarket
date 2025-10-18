<?php

namespace App\Services;

use App\Models\PromoCode;
use App\Models\Order;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * Validate a promo code for a given user and plan.
     *
     * @param string $code
     * @param int|null $userId
     * @param int|null $planId
     * @return array
     */
    public function validateCode(string $code, ?int $userId = null, ?int $planId = null): array
    {
        $promoCode = PromoCode::where('code', strtoupper($code))->first();

        if (!$promoCode) {
            return [
                'valid' => false,
                'message' => 'کد تخفیف معتبر نیست.',
            ];
        }

        if (!$promoCode->isValid()) {
            if (!$promoCode->active) {
                return [
                    'valid' => false,
                    'message' => 'این کد تخفیف غیرفعال است.',
                ];
            }

            if ($promoCode->start_at && now()->isBefore($promoCode->start_at)) {
                return [
                    'valid' => false,
                    'message' => 'این کد تخفیف هنوز فعال نشده است.',
                ];
            }

            if ($promoCode->expires_at && now()->isAfter($promoCode->expires_at)) {
                return [
                    'valid' => false,
                    'message' => 'این کد تخفیف منقضی شده است.',
                ];
            }

            if ($promoCode->max_uses && $promoCode->uses_count >= $promoCode->max_uses) {
                return [
                    'valid' => false,
                    'message' => 'این کد تخفیف به حد مجاز استفاده رسیده است.',
                ];
            }
        }

        if ($userId && !$promoCode->canBeUsedByUser($userId)) {
            return [
                'valid' => false,
                'message' => 'شما قبلاً از این کد تخفیف استفاده کرده‌اید.',
            ];
        }

        if ($planId && !$promoCode->appliesToPlan($planId)) {
            return [
                'valid' => false,
                'message' => 'این کد تخفیف برای این پلن قابل استفاده نیست.',
            ];
        }

        return [
            'valid' => true,
            'promo_code' => $promoCode,
            'message' => 'کد تخفیف معتبر است.',
        ];
    }

    /**
     * Calculate discount for a given price and promo code.
     *
     * @param PromoCode $promoCode
     * @param float $price
     * @return array
     */
    public function calculateDiscount(PromoCode $promoCode, float $price): array
    {
        $discountAmount = $promoCode->calculateDiscount($price);
        $finalPrice = max(0, $price - $discountAmount);

        return [
            'original_price' => $price,
            'discount_amount' => $discountAmount,
            'final_price' => $finalPrice,
            'discount_type' => $promoCode->discount_type,
            'discount_value' => $promoCode->discount_value,
        ];
    }

    /**
     * Apply a promo code to an order.
     *
     * @param Order $order
     * @param string $code
     * @return array
     */
    public function applyToOrder(Order $order, string $code): array
    {
        $validation = $this->validateCode($code, $order->user_id, $order->plan_id);

        if (!$validation['valid']) {
            return $validation;
        }

        $promoCode = $validation['promo_code'];
        $price = $order->plan->price ?? $order->amount;
        
        $discountCalculation = $this->calculateDiscount($promoCode, $price);

        // Update order with promo code details
        $order->update([
            'promo_code_id' => $promoCode->id,
            'original_amount' => $discountCalculation['original_price'],
            'discount_amount' => $discountCalculation['discount_amount'],
            'amount' => $discountCalculation['final_price'],
        ]);

        return [
            'valid' => true,
            'message' => 'کد تخفیف با موفقیت اعمال شد.',
            'discount' => $discountCalculation,
            'promo_code' => $promoCode,
        ];
    }

    /**
     * Remove promo code from an order.
     *
     * @param Order $order
     * @return void
     */
    public function removeFromOrder(Order $order): void
    {
        if ($order->promo_code_id) {
            $order->update([
                'promo_code_id' => null,
                'discount_amount' => null,
                'amount' => $order->original_amount ?? ($order->plan ? $order->plan->price : $order->amount),
                'original_amount' => null,
            ]);
        }
    }

    /**
     * Increment the uses count of a promo code (call this when order is paid).
     *
     * @param PromoCode $promoCode
     * @return void
     */
    public function incrementUsage(PromoCode $promoCode): void
    {
        // Use atomic increment to prevent race conditions
        DB::table('promo_codes')
            ->where('id', $promoCode->id)
            ->increment('uses_count');
    }
}
