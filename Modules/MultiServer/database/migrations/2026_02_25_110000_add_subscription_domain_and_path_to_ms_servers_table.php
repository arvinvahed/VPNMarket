<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            // اضافه کردن ستون subscription_domain اگر وجود نداشته باشد
            if (!Schema::hasColumn('ms_servers', 'subscription_domain')) {
                $table->string('subscription_domain')->nullable()->after('updated_at');
            }
            // اضافه کردن ستون subscription_path اگر وجود نداشته باشد
            if (!Schema::hasColumn('ms_servers', 'subscription_path')) {
                $table->string('subscription_path')->nullable()->after('subscription_domain');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            // حذف ستون‌ها در صورت وجود
            if (Schema::hasColumn('ms_servers', 'subscription_domain')) {
                $table->dropColumn('subscription_domain');
            }
            if (Schema::hasColumn('ms_servers', 'subscription_path')) {
                $table->dropColumn('subscription_path');
            }
        });
    }
};
