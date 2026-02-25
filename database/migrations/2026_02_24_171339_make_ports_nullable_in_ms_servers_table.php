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
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->integer('port')->nullable()->change();
            $table->integer('subscription_port')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ms_servers', function (Blueprint $table) {
            $table->integer('port')->nullable(false)->change();
            $table->integer('subscription_port')->nullable(false)->change();
        });
    }
};
