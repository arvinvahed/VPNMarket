<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // مثلاً YALDA1404
            $table->string('name'); // نام کمپین: تخفیف شب یلدا
            $table->text('description')->nullable();

            $table->enum('type', ['percent', 'fixed']); // درصدی یا مبلغی
            $table->decimal('value', 12, 2); // مثلاً 30 (درصد) یا 50000 (تومان)

            $table->decimal('max_discount_amount', 12, 2)->nullable(); // حداکثر تخفیف (برای درصدی)
            $table->integer('usage_limit')->nullable(); // تعداد کل استفاده
            $table->integer('usage_limit_per_user')->default(1); // چند بار هر کاربر
            $table->integer('used_count')->default(0);

            $table->decimal('min_order_amount', 12, 2)->nullable(); // حداقل مبلغ سفارش
            $table->boolean('applies_to_wallet')->default(false); // روی شارژ کیف پول هم کار کنه؟
            $table->boolean('applies_to_renewal')->default(true); // روی تمدید هم کار کنه؟

            $table->json('plan_ids')->nullable(); // فقط روی این پلن‌ها (null = همه)

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_codes');
    }
};
