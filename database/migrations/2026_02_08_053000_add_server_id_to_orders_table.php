<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // اضافه کردن ستون server_id برای پشتیبانی از MultiServer (فقط اگر از قبل وجود نداشته باشد)
            if (!Schema::hasColumn('orders', 'server_id')) {
                $table->unsignedBigInteger('server_id')->nullable()->after('plan_id');
            }
            
            // اگر جدول ms_servers وجود دارد، foreign key اضافه کن
            if (Schema::hasTable('ms_servers')) {
                // بررسی کنیم که آیا کلید خارجی از قبل وجود دارد یا خیر (در لاراول ۶ به بالا کمی دشوار است، ساده‌ترین راه try-catch است یا نادیده گرفتن)
                try {
                    $table->foreign('server_id')
                        ->references('id')
                        ->on('ms_servers')
                        ->onDelete('set null');
                } catch (\Exception $e) {
                    // احتمالا کلید خارجی از قبل وجود دارد یا مشکلی در ایجاد آن است
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // حذف foreign key اگر وجود دارد
            if (Schema::hasTable('ms_servers')) {
                $table->dropForeign(['server_id']);
            }
            
            $table->dropColumn('server_id');
        });
    }
};
