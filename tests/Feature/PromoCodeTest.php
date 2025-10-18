<?php

use App\Models\PromoCode;
use App\Models\Plan;
use App\Models\User;
use App\Models\Order;
use App\Services\CouponService;

beforeEach(function () {
    $this->couponService = new CouponService();
});

test('promo code is created successfully', function () {
    $promoCode = PromoCode::factory()->create([
        'code' => 'TEST10',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'active' => true,
    ]);

    expect($promoCode->code)->toBe('TEST10')
        ->and($promoCode->active)->toBeTrue();
});

test('promo code is automatically uppercased', function () {
    $promoCode = PromoCode::factory()->create([
        'code' => 'test20',
    ]);

    expect($promoCode->code)->toBe('TEST20');
});

test('valid promo code can be validated', function () {
    $promoCode = PromoCode::factory()->create([
        'code' => 'VALID10',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'active' => true,
    ]);

    $result = $this->couponService->validateCode('VALID10');

    expect($result['valid'])->toBeTrue()
        ->and($result['promo_code']->code)->toBe('VALID10');
});

test('inactive promo code fails validation', function () {
    PromoCode::factory()->create([
        'code' => 'INACTIVE',
        'active' => false,
    ]);

    $result = $this->couponService->validateCode('INACTIVE');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('غیرفعال');
});

test('expired promo code fails validation', function () {
    PromoCode::factory()->create([
        'code' => 'EXPIRED',
        'expires_at' => now()->subDay(),
        'active' => true,
    ]);

    $result = $this->couponService->validateCode('EXPIRED');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('منقضی');
});

test('promo code with max uses reached fails validation', function () {
    PromoCode::factory()->create([
        'code' => 'MAXED',
        'max_uses' => 5,
        'uses_count' => 5,
        'active' => true,
    ]);

    $result = $this->couponService->validateCode('MAXED');

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('حد مجاز');
});

test('percent discount is calculated correctly', function () {
    $promoCode = PromoCode::factory()->create([
        'discount_type' => 'percent',
        'discount_value' => 20,
    ]);

    $result = $this->couponService->calculateDiscount($promoCode, 10000);

    expect($result['discount_amount'])->toBe(2000.0)
        ->and($result['final_price'])->toBe(8000.0);
});

test('fixed discount is calculated correctly', function () {
    $promoCode = PromoCode::factory()->create([
        'discount_type' => 'fixed',
        'discount_value' => 5000,
    ]);

    $result = $this->couponService->calculateDiscount($promoCode, 10000);

    expect($result['discount_amount'])->toBe(5000.0)
        ->and($result['final_price'])->toBe(5000.0);
});

test('fixed discount does not exceed order amount', function () {
    $promoCode = PromoCode::factory()->create([
        'discount_type' => 'fixed',
        'discount_value' => 15000,
    ]);

    $result = $this->couponService->calculateDiscount($promoCode, 10000);

    expect($result['discount_amount'])->toBe(10000.0)
        ->and($result['final_price'])->toBe(0);
});

test('promo code can be applied to order', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 50000]);
    $order = Order::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => 'pending',
    ]);

    PromoCode::factory()->create([
        'code' => 'APPLY10',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'active' => true,
    ]);

    $result = $this->couponService->applyToOrder($order, 'APPLY10');

    $order->refresh();

    expect($result['valid'])->toBeTrue()
        ->and($order->promo_code_id)->not->toBeNull()
        ->and((float)$order->original_amount)->toBe(50000.0)
        ->and((float)$order->discount_amount)->toBe(5000.0)
        ->and((float)$order->amount)->toBe(45000.0);
});

test('promo code can be removed from order', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 50000]);
    $promoCode = PromoCode::factory()->create([
        'code' => 'REMOVE10',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'active' => true,
    ]);

    $order = Order::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'promo_code_id' => $promoCode->id,
        'original_amount' => 50000,
        'discount_amount' => 5000,
        'amount' => 45000,
        'status' => 'pending',
    ]);

    $this->couponService->removeFromOrder($order);
    $order->refresh();

    expect($order->promo_code_id)->toBeNull()
        ->and($order->discount_amount)->toBeNull()
        ->and($order->original_amount)->toBeNull()
        ->and((float)$order->amount)->toBe(50000.0);
});

test('promo code usage is incremented atomically', function () {
    $promoCode = PromoCode::factory()->create([
        'code' => 'INCREMENT',
        'uses_count' => 0,
        'active' => true,
    ]);

    $this->couponService->incrementUsage($promoCode);
    $promoCode->refresh();

    expect($promoCode->uses_count)->toBe(1);
});

test('promo code with per-user limit is enforced', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => 50000]);
    $promoCode = PromoCode::factory()->create([
        'code' => 'USERMAX',
        'discount_type' => 'percent',
        'discount_value' => 10,
        'max_uses_per_user' => 1,
        'active' => true,
    ]);

    // Create a paid order with this promo code
    Order::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'promo_code_id' => $promoCode->id,
        'status' => 'paid',
    ]);

    $result = $this->couponService->validateCode('USERMAX', $user->id);

    expect($result['valid'])->toBeFalse()
        ->and($result['message'])->toContain('قبلاً');
});

test('promo code applies only to specific plan when configured', function () {
    $plan1 = Plan::factory()->create(['price' => 50000]);
    $plan2 = Plan::factory()->create(['price' => 60000]);

    PromoCode::factory()->create([
        'code' => 'PLAN1ONLY',
        'discount_type' => 'percent',
        'discount_value' => 15,
        'applies_to' => 'plan',
        'plan_id' => $plan1->id,
        'active' => true,
    ]);

    $resultValid = $this->couponService->validateCode('PLAN1ONLY', null, $plan1->id);
    $resultInvalid = $this->couponService->validateCode('PLAN1ONLY', null, $plan2->id);

    expect($resultValid['valid'])->toBeTrue()
        ->and($resultInvalid['valid'])->toBeFalse();
});
