<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // صاحب سرور (نماینده)

            // اطلاعات سرور
            $table->string('name'); // نام سرور
            $table->string('panel_type')->default('xui'); // xui یا marzban
            $table->string('host'); // آدرس پنل
            $table->string('username'); // یوزر پنل
            $table->string('password'); // پسورد پنل
            $table->integer('inbound_id')->nullable(); // آیدی اینباند

            // ظرفیت و مصرف
            $table->integer('capacity')->default(100); // ظرفیت کل
            $table->integer('current_users')->default(0); // تعداد کاربر فعلی

            // وضعیت
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable(); // انقضای سرور

            // قیمت‌گذاری
            $table->decimal('monthly_cost', 15, 0)->default(0); // هزینه ماهانه
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_servers');
    }
};
