<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // اگر سفارش توسط نماینده ثبت شده
            $table->foreignId('agent_id')->nullable()->constrained()->onDelete('set null');

            // اگر از سرور نماینده استفاده شده
            $table->foreignId('agent_server_id')->nullable()->constrained('agent_servers')->onDelete('set null');

            // قیمت تمام شده برای نماینده (با تخفیف)
            $table->decimal('agent_cost_price', 15, 0)->nullable();

            // سود نماینده از این فروش
            $table->decimal('agent_profit', 15, 0)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['agent_id']);
            $table->dropForeign(['agent_server_id']);
            $table->dropColumn(['agent_id', 'agent_server_id', 'agent_cost_price', 'agent_profit']);
        });
    }
};
