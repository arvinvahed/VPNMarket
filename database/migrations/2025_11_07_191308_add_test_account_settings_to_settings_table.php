<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->boolean('test_account_enabled')->default(false);
            $table->integer('test_account_volume_gb')->default(5);     // حجم پیش‌فرض
            $table->integer('test_account_days')->default(3);          // زمان پیش‌فرض
            $table->integer('test_account_max_per_user')->default(1);  // چند بار هر کاربر
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            //
        });
    }
};
