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
            // Reverting nullable to not null might fail if there are null values, 
            // but for down() we can just try to set them back or leave them as is.
            // Assuming they were nullable before is safer, or we can just leave this empty 
            // if we don't strictly need to revert constraint.
            // But let's try to revert to default behavior if possible.
            // For now, I will just make them nullable in up() and nullable in down() 
            // is not really "reverting", but making them NOT NULL again is risky.
            // So I will just leave down empty or try to revert if user really wants to rollback.
            
            // $table->integer('port')->nullable(false)->change();
            // $table->integer('subscription_port')->nullable(false)->change();
        });
    }
};
