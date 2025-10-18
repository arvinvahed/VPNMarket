<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PromoCode;
use Carbon\Carbon;

class PromoCodeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $promoCodes = [
            [
                'code' => 'WELCOME10',
                'description' => 'کد تخفیف خوش‌آمدگویی ۱۰ درصد برای کاربران جدید',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'max_uses' => 100,
                'max_uses_per_user' => 1,
                'active' => true,
                'applies_to' => 'all',
            ],
            [
                'code' => 'SAVE20',
                'description' => 'کد تخفیف ۲۰ درصدی ویژه',
                'discount_type' => 'percent',
                'discount_value' => 20,
                'max_uses' => 50,
                'max_uses_per_user' => 2,
                'active' => true,
                'applies_to' => 'all',
                'expires_at' => Carbon::now()->addDays(30),
            ],
            [
                'code' => 'OFF5000',
                'description' => 'تخفیف ۵۰۰۰ تومانی',
                'discount_type' => 'fixed',
                'discount_value' => 5000,
                'currency' => 'تومان',
                'max_uses' => 20,
                'active' => true,
                'applies_to' => 'all',
                'start_at' => Carbon::now(),
                'expires_at' => Carbon::now()->addDays(15),
            ],
            [
                'code' => 'EXPIRED',
                'description' => 'کد تخفیف منقضی شده (برای تست)',
                'discount_type' => 'percent',
                'discount_value' => 50,
                'active' => true,
                'applies_to' => 'all',
                'expires_at' => Carbon::now()->subDays(1),
            ],
            [
                'code' => 'MAXEDOUT',
                'description' => 'کد تخفیف با استفاده کامل (برای تست)',
                'discount_type' => 'percent',
                'discount_value' => 15,
                'max_uses' => 5,
                'uses_count' => 5,
                'active' => true,
                'applies_to' => 'all',
            ],
            [
                'code' => 'INACTIVE',
                'description' => 'کد تخفیف غیرفعال (برای تست)',
                'discount_type' => 'percent',
                'discount_value' => 25,
                'active' => false,
                'applies_to' => 'all',
            ],
        ];

        foreach ($promoCodes as $promoCode) {
            PromoCode::updateOrCreate(
                ['code' => $promoCode['code']],
                $promoCode
            );
        }
    }
}
