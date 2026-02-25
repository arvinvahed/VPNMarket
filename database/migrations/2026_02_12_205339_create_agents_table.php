<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // وضعیت نمایندگی
            $table->enum('status', ['pending', 'approved', 'rejected', 'suspended'])->default('pending');

            // اطلاعات درخواست
            $table->string('phone')->nullable(); // شماره تماس
            $table->string('telegram_id')->nullable(); // آیدی تلگرام
            $table->text('address')->nullable(); // آدرس
            $table->text('rejection_reason')->nullable(); // دلیل رد

            // تنظیمات نماینده
            $table->integer('max_accounts')->default(16); // حداکثر اکانت قابل ساخت
            $table->integer('created_accounts_count')->default(0); // تعداد ساخته شده

            // تعرفه خرید سرور (هر اکانت چقدر براش تموم میشه)
            $table->decimal('server_cost_per_account', 15, 0)->default(30000); // ۳۰ هزار تومن

            // کیف پول نماینده (جدا از کیف پول عادی کاربر)
            $table->decimal('agent_balance', 15, 0)->default(0);

            // اطلاعات پرداخت برای تایید
            $table->string('payment_receipt_path')->nullable(); // عکس رسید
            $table->decimal('payment_amount', 15, 0)->nullable(); // مبلغ واریزی

            $table->timestamp('approved_at')->nullable(); // زمان تایید
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
