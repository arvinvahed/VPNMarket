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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->decimal('discount_value', 10, 2);
            $table->string('currency', 10)->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('max_uses_per_user')->nullable();
            $table->integer('uses_count')->default(0);
            $table->timestamp('start_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('active')->default(true);
            $table->enum('applies_to', ['all', 'plan', 'provider'])->default('all');
            $table->foreignId('plan_id')->nullable()->constrained('plans')->onDelete('cascade');
            $table->foreignId('provider_id')->nullable();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
