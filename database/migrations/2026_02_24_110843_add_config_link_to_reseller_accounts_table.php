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
        Schema::table('reseller_accounts', function (Blueprint $table) {
            $table->text('config_link')->nullable()->after('subscription_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reseller_accounts', function (Blueprint $table) {
            $table->dropColumn('config_link');
        });
    }
};
