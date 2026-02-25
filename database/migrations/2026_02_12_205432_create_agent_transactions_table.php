<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 15, 0); // مبلغ
            $table->enum('type', ['deposit', 'server_purchase', 'account_sale', 'withdraw', 'manual'])->default('deposit');
            $table->enum('status', ['pending', 'completed', 'failed'])->default('pending');

            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable(); // عکس رسید برای واریز

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_transactions');
    }
};
